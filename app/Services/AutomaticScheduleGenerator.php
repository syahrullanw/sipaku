<?php

namespace App\Services;

use App\Models\AutomaticSchedule;
use App\Models\SchoolYear;

class AutomaticScheduleGenerator
{
    /**
     * @param array<int> $classIds
     * @return array<string, mixed>
     */
    public function generateDraft(int $schoolYearId, int $semester, ?int $level = null, ?int $preserveDraftId = null, ?int $createdBy = null, array $classIds = []): array
    {
        AutomaticSchedule::seedDefaultSettings($schoolYearId);
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));

        $schoolYear = SchoolYear::find($schoolYearId);
        if ($schoolYear === null) {
            throw new \InvalidArgumentException('Tahun ajaran tidak ditemukan.');
        }

        $classes = AutomaticSchedule::classroomsForContext($schoolYearId, $level, $classIds);
        $assignments = AutomaticSchedule::assignmentsForGeneration($schoolYearId, $level, $classIds);

        if (empty($classes)) {
            throw new \RuntimeException('Tidak ada kelas/rombel untuk filter yang dipilih.');
        }

        if (empty($assignments)) {
            throw new \RuntimeException('Belum ada data guru pengampu mapel per kelas untuk filter yang dipilih.');
        }

        $periods = AutomaticSchedule::periodsByDay($schoolYearId);
        $activities = AutomaticSchedule::fixedActivities($schoolYearId);
        $rooms = AutomaticSchedule::rooms();
        $unavailable = $this->buildUnavailableMap(AutomaticSchedule::teacherAvailabilityBlocks($schoolYearId));
        $limits = AutomaticSchedule::teacherLimits($schoolYearId);
        $preferences = AutomaticSchedule::preferences($schoolYearId);
        $parallelGroups = AutomaticSchedule::parallelClassGroups($schoolYearId, $level, $classIds);
        $activeTargets = AutomaticSchedule::activeHourTargets($schoolYearId, $semester, $classIds);
        $lockedSourceItems = AutomaticSchedule::lockedItemsForRegenerate($preserveDraftId, $schoolYearId, $semester, $level, $classIds);
        $activityMap = $this->buildActivityMap($activities);

        $draftName = sprintf(
            'Draft Generate %s Semester %d%s%s - %s',
            (string) ($schoolYear['nama'] ?? 'Tahun Ajaran'),
            $semester,
            $level !== null && $level > 0 ? ' Tingkat ' . $level : '',
            !empty($classIds) ? ' - ' . count($classes) . ' kelas' : '',
            date('d/m/Y H:i')
        );

        $now = date('Y-m-d H:i:s');
        $draftId = AutomaticSchedule::createDraft([
            'tahun_ajaran_id' => $schoolYearId,
            'semester' => $semester,
            'tingkat' => $level !== null && $level > 0 ? $level : null,
            'nama' => $draftName,
            'status' => 'draft',
            'total_item' => 0,
            'total_gagal' => 0,
            'conflict_json' => null,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($draftId <= 0) {
            throw new \RuntimeException('Gagal membuat draft jadwal.');
        }

        $occupancy = $this->emptyOccupancy();
        $teacherLoad = [];
        $classLoad = [];
        $classSubjectsByDay = [];
        $classHeavySlots = [];
        $itemsToInsert = [];
        $lockedHoursByAssignmentClass = [];

        foreach ($lockedSourceItems as $sourceItem) {
            $lockedItem = $this->normalizeLockedItem($sourceItem, $draftId, $schoolYearId, $semester, $periods);
            if ($lockedItem === null) {
                continue;
            }

            $itemsToInsert[] = $lockedItem;
            $this->reserveItem($lockedItem, $occupancy, $teacherLoad, $classLoad, $classSubjectsByDay, $classHeavySlots);

            $key = $this->assignmentClassKey((int) $lockedItem['guru_mata_pelajaran_id'], (int) $lockedItem['kelas_id']);
            $lockedHoursByAssignmentClass[$key] = ($lockedHoursByAssignmentClass[$key] ?? 0) + (int) $lockedItem['jumlah_jam'];
        }

        $tasks = $this->buildTasks($assignments, $activeTargets, $lockedHoursByAssignmentClass, $preferences, $parallelGroups);
        $tasks = $this->sortTasks($tasks);
        $failed = 0;
        $placed = 0;

        foreach ($tasks as $task) {
            $slot = $this->findBestSlot(
                $task,
                $periods,
                $activityMap,
                $occupancy,
                $teacherLoad,
                $classLoad,
                $classSubjectsByDay,
                $classHeavySlots,
                $unavailable,
                $limits,
                $rooms,
                $preferences
            );

            if ($slot === null) {
                $failed++;
                foreach ($this->taskClassIds($task) as $taskClassId) {
                    $itemsToInsert[] = [
                        'draft_id' => $draftId,
                        'tahun_ajaran_id' => $schoolYearId,
                        'semester' => $semester,
                        'guru_mata_pelajaran_id' => $task['guru_mata_pelajaran_id'],
                        'guru_id' => $task['guru_id'],
                        'kelas_id' => $taskClassId,
                        'ruangan_id' => null,
                        'hari' => null,
                        'jam_ke_mulai' => null,
                        'jam_ke_selesai' => null,
                        'waktu_mulai' => null,
                        'waktu_selesai' => null,
                        'jumlah_jam' => $task['block_hours'],
                        'parallel_group_id' => $task['parallel_group_id'] ?? null,
                        'status' => 'failed',
                        'is_locked' => 0,
                        'catatan' => 'Gagal ditempatkan tanpa bentrok pada slot yang tersedia.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                continue;
            }

            foreach ($this->taskClassIds($task) as $taskClassId) {
                $itemsToInsert[] = [
                    'draft_id' => $draftId,
                    'tahun_ajaran_id' => $schoolYearId,
                    'semester' => $semester,
                    'guru_mata_pelajaran_id' => $task['guru_mata_pelajaran_id'],
                    'guru_id' => $task['guru_id'],
                    'kelas_id' => $taskClassId,
                    'ruangan_id' => $slot['room_id'],
                    'hari' => $slot['day'],
                    'jam_ke_mulai' => $slot['start_no'],
                    'jam_ke_selesai' => $slot['end_no'],
                    'waktu_mulai' => $slot['start_time'],
                    'waktu_selesai' => $slot['end_time'],
                    'jumlah_jam' => $task['block_hours'],
                    'parallel_group_id' => $task['parallel_group_id'] ?? null,
                    'status' => 'generated',
                    'is_locked' => 0,
                    'catatan' => !empty($task['parallel_group_id']) ? 'Kelas paralel: ' . (string) ($task['parallel_label'] ?? '') : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'mata_pelajaran_nama' => $task['mata_pelajaran_nama'] ?? null,
                    'mata_pelajaran_jenis' => $task['mata_pelajaran_jenis'] ?? null,
                ];
            }
            $this->reserveTaskPlacement($task, $slot, $occupancy, $teacherLoad, $classLoad, $classSubjectsByDay, $classHeavySlots);
            $placed++;
        }

        AutomaticSchedule::insertDraftItems($itemsToInsert);
        $conflicts = $this->validateDraft($draftId);

        return [
            'draft_id' => $draftId,
            'placed' => $placed,
            'failed' => $failed,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validateDraft(int $draftId): array
    {
        $draft = AutomaticSchedule::findDraft($draftId);
        if ($draft === null) {
            return [];
        }

        $schoolYearId = (int) $draft['tahun_ajaran_id'];
        $semester = (int) $draft['semester'];
        $level = isset($draft['tingkat']) ? (int) $draft['tingkat'] : null;
        $level = $level > 0 ? $level : null;

        $items = AutomaticSchedule::draftItems($draftId);
        $classIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['kelas_id'] ?? 0),
            $items
        ))));
        $periods = AutomaticSchedule::periodsByDay($schoolYearId);
        $activities = AutomaticSchedule::fixedActivities($schoolYearId);
        $activityMap = $this->buildActivityMap($activities);
        $unavailable = $this->buildUnavailableMap(AutomaticSchedule::teacherAvailabilityBlocks($schoolYearId));
        $limits = AutomaticSchedule::teacherLimits($schoolYearId);

        $teacherSlots = [];
        $classSlots = [];
        $roomSlots = [];
        $teacherLoad = [];
        $teacherLoadKeys = [];
        $scheduledHours = [];
        $classSlotsFilled = [];

        $conflicts = [
            'teacher_conflicts' => [],
            'class_conflicts' => [],
            'room_conflicts' => [],
            'blocked_slots' => [],
            'unavailable_teachers' => [],
            'missing_hours' => [],
            'teacher_overloads' => [],
            'empty_slots' => [],
            'failed_items' => [],
        ];

        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'failed') {
                $conflicts['failed_items'][] = $this->describeItem($item) . ' gagal ditempatkan.';
                continue;
            }

            $day = (string) ($item['hari'] ?? '');
            $startNo = (int) ($item['jam_ke_mulai'] ?? 0);
            $endNo = (int) ($item['jam_ke_selesai'] ?? 0);
            $teacherId = (int) ($item['guru_id'] ?? 0);
            $classId = (int) ($item['kelas_id'] ?? 0);
            $roomId = (int) ($item['ruangan_id'] ?? 0);
            $hours = max(1, (int) ($item['jumlah_jam'] ?? 1));

            if ($day === '' || $startNo <= 0 || $endNo < $startNo) {
                $conflicts['blocked_slots'][] = $this->describeItem($item) . ' belum memiliki slot hari/jam valid.';
                continue;
            }

            $key = $this->assignmentClassKey((int) $item['guru_mata_pelajaran_id'], $classId);
            $scheduledHours[$key] = ($scheduledHours[$key] ?? 0) + $hours;

            for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
                $period = $periods[$day][$lessonNo] ?? null;
                if ($period === null || ($period['tipe'] ?? 'pelajaran') !== 'pelajaran') {
                    $label = $period['label'] ?? 'slot tidak tersedia';
                    $conflicts['blocked_slots'][] = sprintf('%s memakai %s jam ke-%d.', $this->describeItem($item), $label, $lessonNo);
                }

                if (isset($activityMap[$day][$lessonNo])) {
                    $conflicts['blocked_slots'][] = sprintf('%s menimpa kegiatan tetap %s.', $this->describeItem($item), $activityMap[$day][$lessonNo]);
                }

                if (isset($unavailable[$teacherId][$day][$lessonNo])) {
                    $conflicts['unavailable_teachers'][] = sprintf('%s dijadwalkan saat guru tidak tersedia.', $this->describeItem($item));
                }

                $teacherSlotKey = $teacherId . ':' . $day . ':' . $lessonNo;
                if (isset($teacherSlots[$teacherSlotKey])) {
                    if (!$this->isAllowedParallelCollision($teacherSlots[$teacherSlotKey], $item)) {
                        $conflicts['teacher_conflicts'][] = sprintf(
                            '%s bentrok dengan %s pada %s jam ke-%d.',
                            $this->describeItem($item),
                            $this->describeItem($teacherSlots[$teacherSlotKey]),
                            ucfirst($day),
                            $lessonNo
                        );
                    }
                } else {
                    $teacherSlots[$teacherSlotKey] = $item;
                }

                $classSlotKey = $classId . ':' . $day . ':' . $lessonNo;
                if (isset($classSlots[$classSlotKey])) {
                    $conflicts['class_conflicts'][] = sprintf(
                        '%s bentrok dengan %s pada %s jam ke-%d.',
                        $this->describeItem($item),
                        $this->describeItem($classSlots[$classSlotKey]),
                        ucfirst($day),
                        $lessonNo
                    );
                }
                $classSlots[$classSlotKey] = $item;
                $classSlotsFilled[$classId][$day][$lessonNo] = true;

                if ($roomId > 0) {
                    $roomSlotKey = $roomId . ':' . $day . ':' . $lessonNo;
                    if (isset($roomSlots[$roomSlotKey])) {
                        if (!$this->isAllowedParallelCollision($roomSlots[$roomSlotKey], $item)) {
                            $conflicts['room_conflicts'][] = sprintf(
                                '%s bentrok ruang dengan %s pada %s jam ke-%d.',
                                $this->describeItem($item),
                                $this->describeItem($roomSlots[$roomSlotKey]),
                                ucfirst($day),
                                $lessonNo
                            );
                        }
                    } else {
                        $roomSlots[$roomSlotKey] = $item;
                    }
                }
            }

            $loadKey = $this->teacherLoadKey($item);
            if (!isset($teacherLoadKeys[$teacherId][$loadKey])) {
                $teacherLoad[$teacherId]['weekly'] = ($teacherLoad[$teacherId]['weekly'] ?? 0) + $hours;
                $teacherLoad[$teacherId]['daily'][$day] = ($teacherLoad[$teacherId]['daily'][$day] ?? 0) + $hours;
                $teacherLoadKeys[$teacherId][$loadKey] = true;
            }
        }

        $targets = $this->targetHoursForContext($schoolYearId, $semester, $level, $classIds);
        foreach ($targets as $target) {
            $key = $this->assignmentClassKey((int) $target['guru_mata_pelajaran_id'], (int) $target['kelas_id']);
            $planned = (int) $target['target_hours'];
            $scheduled = (int) ($scheduledHours[$key] ?? 0);
            if ($scheduled < $planned) {
                $conflicts['missing_hours'][] = sprintf(
                    '%s kelas %s kurang %d JP dari target %d JP.',
                    $target['mata_pelajaran_kode'] . ' - ' . $target['mata_pelajaran_nama'],
                    $this->classLabel($target),
                    $planned - $scheduled,
                    $planned
                );
            }
        }

        foreach ($teacherLoad as $teacherId => $load) {
            $limit = $limits[$teacherId] ?? $limits['default'] ?? ['daily' => 8, 'weekly' => 40];
            $weekly = (int) ($load['weekly'] ?? 0);
            if ($weekly > (int) $limit['weekly']) {
                $conflicts['teacher_overloads'][] = sprintf('Guru #%d melebihi batas mingguan: %d/%d JP.', $teacherId, $weekly, (int) $limit['weekly']);
            }

            foreach (($load['daily'] ?? []) as $day => $daily) {
                if ((int) $daily > (int) $limit['daily']) {
                    $conflicts['teacher_overloads'][] = sprintf('Guru #%d melebihi batas harian %s: %d/%d JP.', $teacherId, ucfirst((string) $day), (int) $daily, (int) $limit['daily']);
                }
            }
        }

        foreach ($classSlotsFilled as $classId => $days) {
            foreach ($days as $day => $slots) {
                $slotNumbers = array_keys($slots);
                sort($slotNumbers);
                if (count($slotNumbers) < 2) {
                    continue;
                }
                $first = (int) reset($slotNumbers);
                $last = (int) end($slotNumbers);
                $holes = 0;
                for ($lessonNo = $first; $lessonNo <= $last; $lessonNo++) {
                    if (($periods[$day][$lessonNo]['tipe'] ?? 'pelajaran') !== 'pelajaran') {
                        continue;
                    }
                    if (!isset($slots[$lessonNo])) {
                        $holes++;
                    }
                }
                if ($holes > 0) {
                    $conflicts['empty_slots'][] = sprintf('Kelas #%d memiliki %d slot kosong di tengah hari %s.', $classId, $holes, ucfirst((string) $day));
                }
            }
        }

        foreach ($conflicts as $key => $messages) {
            $conflicts[$key] = array_values(array_unique($messages));
        }

        AutomaticSchedule::updateDraftStats($draftId, $conflicts);

        return $conflicts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function periodRange(int $schoolYearId, string $day, int $startNo, int $hours): ?array
    {
        $periods = AutomaticSchedule::periodsByDay($schoolYearId);
        if (!isset($periods[$day][$startNo]) || $hours <= 0) {
            return null;
        }

        $endNo = $startNo + $hours - 1;
        for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
            if (!isset($periods[$day][$lessonNo])) {
                return null;
            }
        }

        return [
            'start_no' => $startNo,
            'end_no' => $endNo,
            'start_time' => $periods[$day][$startNo]['waktu_mulai'],
            'end_time' => $periods[$day][$endNo]['waktu_selesai'],
        ];
    }

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public function targetHoursForContext(int $schoolYearId, int $semester, ?int $level = null, array $classIds = []): array
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        $assignments = AutomaticSchedule::assignmentsForGeneration($schoolYearId, $level, $classIds);
        $activeTargets = AutomaticSchedule::activeHourTargets($schoolYearId, $semester, $classIds);
        $targets = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) $assignment['guru_mata_pelajaran_id'];
            $classId = (int) $assignment['kelas_id'];
            $key = $this->assignmentClassKey($assignmentId, $classId);
            $targetHours = $activeTargets[$key] ?? $this->defaultTargetHours($assignment);

            $targets[] = $assignment + [
                'target_hours' => max(1, (int) $targetHours),
                'target_source' => isset($activeTargets[$key]) ? 'jadwal_aktif' : 'default',
            ];
        }

        return $targets;
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @param array<string, int> $activeTargets
     * @param array<string, int> $lockedHours
     * @param array<int, array<string, mixed>> $parallelGroups
     * @return array<int, array<string, mixed>>
     */
    private function buildTasks(array $assignments, array $activeTargets, array $lockedHours, array $preferences, array $parallelGroups = []): array
    {
        $tasks = [];
        $assignmentClassRows = [];
        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['guru_mata_pelajaran_id'] ?? 0);
            $classId = (int) ($assignment['kelas_id'] ?? 0);
            if ($assignmentId <= 0 || $classId <= 0) {
                continue;
            }
            $assignmentClassRows[$assignmentId][$classId] = $assignment;
        }

        $parallelHours = [];
        foreach ($parallelGroups as $group) {
            $assignmentId = (int) ($group['guru_mata_pelajaran_id'] ?? 0);
            if ($assignmentId <= 0 || empty($assignmentClassRows[$assignmentId])) {
                continue;
            }

            $groupClassIds = array_values(array_unique(array_filter(array_map('intval', $group['kelas_ids'] ?? []))));
            $groupClassIds = array_values(array_filter(
                $groupClassIds,
                static fn (int $classId): bool => isset($assignmentClassRows[$assignmentId][$classId])
            ));
            if (count($groupClassIds) < 2) {
                continue;
            }

            $remainingByClass = [];
            foreach ($groupClassIds as $classId) {
                $assignment = $assignmentClassRows[$assignmentId][$classId];
                $key = $this->assignmentClassKey($assignmentId, $classId);
                $targetHours = (int) ($activeTargets[$key] ?? $this->defaultTargetHours($assignment));
                $remainingByClass[$classId] = max(0, $targetHours - (int) ($lockedHours[$key] ?? 0) - (int) ($parallelHours[$key] ?? 0));
            }

            $parallelTarget = min($remainingByClass);
            if ($parallelTarget <= 0) {
                continue;
            }

            $baseAssignment = $assignmentClassRows[$assignmentId][$groupClassIds[0]];
            $isProductive = $this->isProductiveSubject($baseAssignment);
            $parallelLabel = trim((string) ($group['nama'] ?? ''));
            if ($parallelLabel === '') {
                $parallelLabel = implode(', ', array_map('strval', $group['kelas_labels'] ?? $groupClassIds));
            }

            foreach ($this->splitIntoBlocks($parallelTarget, $isProductive, $preferences) as $blockHours) {
                $tasks[] = $baseAssignment + [
                    'target_hours' => $parallelTarget,
                    'block_hours' => $blockHours,
                    'productive' => $isProductive,
                    'parallel_group_id' => (int) ($group['id'] ?? 0),
                    'parallel_class_ids' => $groupClassIds,
                    'parallel_label' => $parallelLabel,
                ];
            }

            foreach ($groupClassIds as $classId) {
                $key = $this->assignmentClassKey($assignmentId, $classId);
                $parallelHours[$key] = ($parallelHours[$key] ?? 0) + $parallelTarget;
            }
        }

        foreach ($assignments as $assignment) {
            $assignmentId = (int) $assignment['guru_mata_pelajaran_id'];
            $classId = (int) $assignment['kelas_id'];
            $key = $this->assignmentClassKey($assignmentId, $classId);
            $targetHours = (int) ($activeTargets[$key] ?? $this->defaultTargetHours($assignment));
            $remaining = max(0, $targetHours - (int) ($lockedHours[$key] ?? 0) - (int) ($parallelHours[$key] ?? 0));

            if ($remaining <= 0) {
                continue;
            }

            foreach ($this->splitIntoBlocks($remaining, $this->isProductiveSubject($assignment), $preferences) as $blockHours) {
                $tasks[] = $assignment + [
                    'target_hours' => $targetHours,
                    'block_hours' => $blockHours,
                    'productive' => $this->isProductiveSubject($assignment),
                ];
            }
        }

        return $tasks;
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<int, array<string, mixed>>
     */
    private function sortTasks(array $tasks): array
    {
        usort($tasks, static function (array $left, array $right): int {
            $leftScore = ((int) ($left['block_hours'] ?? 0) * 10) + (!empty($left['productive']) ? 5 : 0);
            $rightScore = ((int) ($right['block_hours'] ?? 0) * 10) + (!empty($right['productive']) ? 5 : 0);

            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            return strcmp((string) ($left['mata_pelajaran_nama'] ?? ''), (string) ($right['mata_pelajaran_nama'] ?? ''));
        });

        return $tasks;
    }

    /**
     * @return array<int>
     */
    private function splitIntoBlocks(int $hours, bool $productive, array $preferences): array
    {
        $blocks = [];
        $productiveMax = min(4, max(2, (int) ($preferences['blok_produktif_maks'] ?? 4)));
        $productiveMin = min($productiveMax, max(1, (int) ($preferences['blok_produktif_min'] ?? 2)));
        $generalMax = min(2, max(1, (int) ($preferences['blok_umum_maks'] ?? 2)));

        while ($hours > 0) {
            if ($productive) {
                if ($hours >= $productiveMax) {
                    $blocks[] = $productiveMax;
                    $hours -= $productiveMax;
                    continue;
                }

                if ($hours >= $productiveMin) {
                    $blocks[] = $hours;
                    $hours = 0;
                    continue;
                }

                $blocks[] = $hours;
                $hours = 0;
                continue;
            }

            if ($hours >= $generalMax) {
                $blocks[] = $generalMax;
                $hours -= $generalMax;
                continue;
            }

            $blocks[] = 1;
            $hours--;
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBestSlot(
        array $task,
        array $periods,
        array $activityMap,
        array $occupancy,
        array $teacherLoad,
        array $classLoad,
        array $classSubjectsByDay,
        array $classHeavySlots,
        array $unavailable,
        array $limits,
        array $rooms,
        array $preferences
    ): ?array {
        $best = null;
        $bestScore = PHP_INT_MAX;
        $blockHours = (int) $task['block_hours'];

        foreach (array_keys(AutomaticSchedule::DAYS) as $day) {
            if (!isset($periods[$day])) {
                continue;
            }

            $periodNumbers = array_keys($periods[$day]);
            sort($periodNumbers);

            foreach ($periodNumbers as $startNo) {
                $endNo = (int) $startNo + $blockHours - 1;
                $roomId = $this->chooseRoomForTask($task, $rooms, $occupancy, $day, (int) $startNo, $endNo);

                if (!$this->canPlace($task, $periods, $activityMap, $occupancy, $teacherLoad, $unavailable, $limits, $day, (int) $startNo, $endNo, $roomId)) {
                    continue;
                }

                $score = $this->scoreSlot(
                    $task,
                    $occupancy,
                    $teacherLoad,
                    $classLoad,
                    $classSubjectsByDay,
                    $classHeavySlots,
                    $day,
                    (int) $startNo,
                    $endNo,
                    $roomId,
                    $preferences
                );
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'day' => $day,
                        'start_no' => (int) $startNo,
                        'end_no' => $endNo,
                        'start_time' => $periods[$day][$startNo]['waktu_mulai'],
                        'end_time' => $periods[$day][$endNo]['waktu_selesai'],
                        'room_id' => $roomId,
                    ];
                }
            }
        }

        return $best;
    }

    private function canPlace(
        array $task,
        array $periods,
        array $activityMap,
        array $occupancy,
        array $teacherLoad,
        array $unavailable,
        array $limits,
        string $day,
        int $startNo,
        int $endNo,
        ?int $roomId
    ): bool {
        $teacherId = (int) $task['guru_id'];
        $classIds = $this->taskClassIds($task);
        $blockHours = (int) $task['block_hours'];
        $limit = $limits[$teacherId] ?? $limits['default'] ?? ['daily' => 8, 'weekly' => 40];
        $dailyLoad = (int) ($teacherLoad[$teacherId]['daily'][$day] ?? 0);
        $weeklyLoad = (int) ($teacherLoad[$teacherId]['weekly'] ?? 0);

        if ($dailyLoad + $blockHours > (int) $limit['daily'] || $weeklyLoad + $blockHours > (int) $limit['weekly']) {
            return false;
        }

        for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
            $period = $periods[$day][$lessonNo] ?? null;
            if ($period === null || ($period['tipe'] ?? 'pelajaran') !== 'pelajaran') {
                return false;
            }

            if (isset($activityMap[$day][$lessonNo])) {
                return false;
            }

            if (isset($occupancy['teacher'][$teacherId][$day][$lessonNo])) {
                return false;
            }

            foreach ($classIds as $classId) {
                if (isset($occupancy['class'][$classId][$day][$lessonNo])) {
                    return false;
                }
            }

            if ($roomId !== null && isset($occupancy['room'][$roomId][$day][$lessonNo])) {
                return false;
            }

            if (isset($unavailable[$teacherId][$day][$lessonNo])) {
                return false;
            }
        }

        return true;
    }

    private function scoreSlot(
        array $task,
        array $occupancy,
        array $teacherLoad,
        array $classLoad,
        array $classSubjectsByDay,
        array $classHeavySlots,
        string $day,
        int $startNo,
        int $endNo,
        ?int $roomId,
        array $preferences
    ): int {
        $teacherId = (int) $task['guru_id'];
        $classIds = $this->taskClassIds($task);
        $assignmentId = (int) $task['guru_mata_pelajaran_id'];
        $score = 0;
        $isProductive = !empty($task['productive']);
        $spreadTeacher = !empty($preferences['sebar_beban_guru']);
        $compactClass = !empty($preferences['rapatkan_jadwal_kelas']);

        $score += $startNo * ($isProductive && !empty($preferences['prioritas_praktik_pagi']) ? 3 : 6);
        if ($spreadTeacher) {
            $score += (int) ($teacherLoad[$teacherId]['daily'][$day] ?? 0) * (int) ($preferences['bobot_jam_guru_harian'] ?? 7);
        }
        if ($compactClass) {
            foreach ($classIds as $classId) {
                $score += (int) ($classLoad[$classId][$day] ?? 0) * (int) ($preferences['bobot_jam_kelas_harian'] ?? 3);
            }
        }

        if ($isProductive && !empty($preferences['prioritas_praktik_pagi']) && $startNo > 6) {
            $score += (int) ($preferences['penalti_slot_sore_produktif'] ?? 25);
        }

        if (!empty($preferences['hindari_mapel_sama_per_hari'])) {
            foreach ($classIds as $classId) {
                if (isset($classSubjectsByDay[$classId][$day][$assignmentId])) {
                    $score += (int) ($preferences['penalti_mapel_sama_hari'] ?? 30);
                }
            }
        }

        if ($spreadTeacher && (int) ($teacherLoad[$teacherId]['daily'][$day] ?? 0) > 0) {
            $teacherTouchesExisting = isset($occupancy['teacher'][$teacherId][$day][$startNo - 1])
                || isset($occupancy['teacher'][$teacherId][$day][$endNo + 1]);
            if (!$teacherTouchesExisting) {
                $score += (int) ($preferences['penalti_jam_kosong_guru'] ?? 18);
            }
        }

        if ($compactClass) {
            foreach ($classIds as $classId) {
                if ((int) ($classLoad[$classId][$day] ?? 0) <= 0) {
                    continue;
                }
                $classTouchesExisting = isset($occupancy['class'][$classId][$day][$startNo - 1])
                    || isset($occupancy['class'][$classId][$day][$endNo + 1]);
                if (!$classTouchesExisting) {
                    $score += (int) ($preferences['penalti_jam_kosong_kelas'] ?? 15);
                }
            }
        }

        if ($isProductive) {
            $maxHeavySequence = max(1, (int) ($preferences['maks_mapel_berat_berurutan'] ?? 2));
            foreach ($classIds as $classId) {
                $adjacentHeavy = $this->countAdjacentHeavySlots($classHeavySlots, $classId, $day, $startNo, $endNo);
                if ($adjacentHeavy >= $maxHeavySequence) {
                    $score += (int) ($preferences['penalti_mapel_berat_berurutan'] ?? 22) * ($adjacentHeavy - $maxHeavySequence + 1);
                }
            }
        }

        if ($roomId !== null) {
            $score -= 2;
        }

        return $score;
    }

    private function chooseRoomForTask(array $task, array $rooms, array $occupancy, string $day, int $startNo, int $endNo): ?int
    {
        if (empty($rooms) || empty($task['productive'])) {
            return null;
        }

        $preferred = array_values(array_filter($rooms, static fn (array $room): bool => in_array((string) ($room['jenis'] ?? ''), ['lab', 'bengkel'], true)));
        $candidates = !empty($preferred) ? $preferred : $rooms;

        foreach ($candidates as $room) {
            $roomId = (int) ($room['id'] ?? 0);
            if ($roomId <= 0) {
                continue;
            }

            $free = true;
            for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
                if (isset($occupancy['room'][$roomId][$day][$lessonNo])) {
                    $free = false;
                    break;
                }
            }

            if ($free) {
                return $roomId;
            }
        }

        return null;
    }

    private function countAdjacentHeavySlots(array $classHeavySlots, int $classId, string $day, int $startNo, int $endNo): int
    {
        $count = 0;

        for ($lessonNo = $startNo - 1; $lessonNo >= 1; $lessonNo--) {
            if (!isset($classHeavySlots[$classId][$day][$lessonNo])) {
                break;
            }
            $count++;
        }

        for ($lessonNo = $endNo + 1; $lessonNo <= 14; $lessonNo++) {
            if (!isset($classHeavySlots[$classId][$day][$lessonNo])) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function reserveItem(array $item, array &$occupancy, array &$teacherLoad, array &$classLoad, array &$classSubjectsByDay, array &$classHeavySlots): void
    {
        $day = (string) ($item['hari'] ?? '');
        $startNo = (int) ($item['jam_ke_mulai'] ?? 0);
        $endNo = (int) ($item['jam_ke_selesai'] ?? 0);
        if ($day === '' || $startNo <= 0 || $endNo < $startNo) {
            return;
        }

        $teacherId = (int) $item['guru_id'];
        $classId = (int) $item['kelas_id'];
        $roomId = isset($item['ruangan_id']) ? (int) $item['ruangan_id'] : 0;
        $hours = max(1, (int) ($item['jumlah_jam'] ?? ($endNo - $startNo + 1)));
        $isHeavy = $this->isProductiveSubject($item);

        for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
            $occupancy['teacher'][$teacherId][$day][$lessonNo] = true;
            $occupancy['class'][$classId][$day][$lessonNo] = true;
            if ($roomId > 0) {
                $occupancy['room'][$roomId][$day][$lessonNo] = true;
            }
            if ($isHeavy) {
                $classHeavySlots[$classId][$day][$lessonNo] = true;
            }
        }

        $teacherLoad[$teacherId]['weekly'] = ($teacherLoad[$teacherId]['weekly'] ?? 0) + $hours;
        $teacherLoad[$teacherId]['daily'][$day] = ($teacherLoad[$teacherId]['daily'][$day] ?? 0) + $hours;
        $classLoad[$classId][$day] = ($classLoad[$classId][$day] ?? 0) + $hours;
        $classSubjectsByDay[$classId][$day][(int) $item['guru_mata_pelajaran_id']] = true;
    }

    /**
     * @param array<string, mixed> $task
     * @param array<string, mixed> $slot
     */
    private function reserveTaskPlacement(array $task, array $slot, array &$occupancy, array &$teacherLoad, array &$classLoad, array &$classSubjectsByDay, array &$classHeavySlots): void
    {
        $day = (string) ($slot['day'] ?? '');
        $startNo = (int) ($slot['start_no'] ?? 0);
        $endNo = (int) ($slot['end_no'] ?? 0);
        if ($day === '' || $startNo <= 0 || $endNo < $startNo) {
            return;
        }

        $teacherId = (int) ($task['guru_id'] ?? 0);
        $classIds = $this->taskClassIds($task);
        $roomId = isset($slot['room_id']) ? (int) $slot['room_id'] : 0;
        $hours = max(1, (int) ($task['block_hours'] ?? ($endNo - $startNo + 1)));
        $isHeavy = $this->isProductiveSubject($task);

        for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
            $occupancy['teacher'][$teacherId][$day][$lessonNo] = true;
            foreach ($classIds as $classId) {
                $occupancy['class'][$classId][$day][$lessonNo] = true;
                if ($isHeavy) {
                    $classHeavySlots[$classId][$day][$lessonNo] = true;
                }
            }
            if ($roomId > 0) {
                $occupancy['room'][$roomId][$day][$lessonNo] = true;
            }
        }

        $teacherLoad[$teacherId]['weekly'] = ($teacherLoad[$teacherId]['weekly'] ?? 0) + $hours;
        $teacherLoad[$teacherId]['daily'][$day] = ($teacherLoad[$teacherId]['daily'][$day] ?? 0) + $hours;
        foreach ($classIds as $classId) {
            $classLoad[$classId][$day] = ($classLoad[$classId][$day] ?? 0) + $hours;
            $classSubjectsByDay[$classId][$day][(int) $task['guru_mata_pelajaran_id']] = true;
        }
    }

    /**
     * @param array<string, mixed> $task
     * @return array<int>
     */
    private function taskClassIds(array $task): array
    {
        $classIds = $task['parallel_class_ids'] ?? [];
        if (!is_array($classIds) || empty($classIds)) {
            $classIds = [(int) ($task['kelas_id'] ?? 0)];
        }

        return array_values(array_unique(array_filter(array_map('intval', $classIds))));
    }

    /**
     * @return array<string, array>
     */
    private function emptyOccupancy(): array
    {
        return [
            'teacher' => [],
            'class' => [],
            'room' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, array<int, bool>>>
     */
    private function buildUnavailableMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $teacherId = (int) ($row['guru_id'] ?? 0);
            $day = (string) ($row['hari'] ?? '');
            $lessonNo = (int) ($row['jam_ke'] ?? 0);
            if ($teacherId <= 0 || $day === '' || $lessonNo <= 0) {
                continue;
            }
            $map[$teacherId][$day][$lessonNo] = true;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $activities
     * @return array<string, array<int, string>>
     */
    private function buildActivityMap(array $activities): array
    {
        $map = [];
        foreach ($activities as $activity) {
            $day = (string) ($activity['hari'] ?? '');
            $startNo = (int) ($activity['jam_ke_mulai'] ?? 0);
            $endNo = (int) ($activity['jam_ke_selesai'] ?? 0);
            $name = (string) ($activity['nama'] ?? 'Kegiatan tetap');
            if ($day === '' || $startNo <= 0 || $endNo < $startNo) {
                continue;
            }
            for ($lessonNo = $startNo; $lessonNo <= $endNo; $lessonNo++) {
                $map[$day][$lessonNo] = $name;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeLockedItem(array $source, int $draftId, int $schoolYearId, int $semester, array $periods): ?array
    {
        $day = (string) ($source['hari'] ?? '');
        $startNo = (int) ($source['jam_ke_mulai'] ?? 0);
        $endNo = (int) ($source['jam_ke_selesai'] ?? 0);
        $hours = max(1, (int) ($source['jumlah_jam'] ?? 1));

        if ($day === '') {
            return null;
        }

        if ($startNo <= 0 && !empty($source['waktu_mulai']) && isset($periods[$day])) {
            $startTime = substr((string) $source['waktu_mulai'], 0, 5);
            foreach ($periods[$day] as $lessonNo => $period) {
                if (substr((string) ($period['waktu_mulai'] ?? ''), 0, 5) === $startTime) {
                    $startNo = (int) $lessonNo;
                    break;
                }
            }
        }

        if ($startNo <= 0) {
            return null;
        }

        if ($endNo < $startNo) {
            $endNo = $startNo + $hours - 1;
        }

        $startTime = $source['waktu_mulai'] ?? ($periods[$day][$startNo]['waktu_mulai'] ?? null);
        $endTime = $source['waktu_selesai'] ?? ($periods[$day][$endNo]['waktu_selesai'] ?? null);

        if ($startTime === null || $endTime === null) {
            return null;
        }

        return [
            'draft_id' => $draftId,
            'tahun_ajaran_id' => $schoolYearId,
            'semester' => $semester,
            'guru_mata_pelajaran_id' => (int) $source['guru_mata_pelajaran_id'],
            'guru_id' => (int) $source['guru_id'],
            'kelas_id' => (int) $source['kelas_id'],
            'ruangan_id' => isset($source['ruangan_id']) && $source['ruangan_id'] !== null ? (int) $source['ruangan_id'] : null,
            'hari' => $day,
            'jam_ke_mulai' => $startNo,
            'jam_ke_selesai' => $endNo,
            'waktu_mulai' => $startTime,
            'waktu_selesai' => $endTime,
            'jumlah_jam' => $hours,
            'parallel_group_id' => !empty($source['parallel_group_id']) ? (int) $source['parallel_group_id'] : null,
            'status' => 'fixed',
            'is_locked' => 1,
            'catatan' => 'Dipertahankan dari jadwal terkunci.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'mata_pelajaran_nama' => $source['mata_pelajaran_nama'] ?? null,
            'mata_pelajaran_jenis' => $source['mata_pelajaran_jenis'] ?? null,
        ];
    }

    private function defaultTargetHours(array $assignment): int
    {
        $subjectName = strtolower((string) ($assignment['mata_pelajaran_nama'] ?? ''));
        $type = strtoupper((string) ($assignment['mata_pelajaran_jenis'] ?? ''));

        if (str_contains($subjectName, 'produktif') || str_contains($subjectName, 'praktik') || in_array($type, ['C2', 'C3'], true)) {
            return 4;
        }

        if ($type === 'C1') {
            return 3;
        }

        return 2;
    }

    private function isProductiveSubject(array $assignment): bool
    {
        $subjectName = strtolower((string) ($assignment['mata_pelajaran_nama'] ?? ''));
        $type = strtoupper((string) ($assignment['mata_pelajaran_jenis'] ?? ''));

        return in_array($type, ['C2', 'C3'], true)
            || str_contains($subjectName, 'produktif')
            || str_contains($subjectName, 'praktik')
            || str_contains($subjectName, 'lab');
    }

    private function assignmentClassKey(int $assignmentId, int $classId): string
    {
        return $assignmentId . ':' . $classId;
    }

    private function classLabel(array $row): string
    {
        $label = trim('Kelas ' . (string) ($row['kelas_tingkat'] ?? '-') . ' ' . (string) ($row['kelas_nama'] ?? '-'));
        if (!empty($row['jurusan_nama'])) {
            $label .= ' (' . $row['jurusan_nama'] . ')';
        }

        return $label;
    }

    private function isAllowedParallelCollision(array $existing, array $current): bool
    {
        $existingGroupId = (int) ($existing['parallel_group_id'] ?? 0);
        $currentGroupId = (int) ($current['parallel_group_id'] ?? 0);
        if ($existingGroupId <= 0 || $existingGroupId !== $currentGroupId) {
            return false;
        }

        return (int) ($existing['guru_id'] ?? 0) === (int) ($current['guru_id'] ?? 0)
            && (int) ($existing['guru_mata_pelajaran_id'] ?? 0) === (int) ($current['guru_mata_pelajaran_id'] ?? 0)
            && (string) ($existing['hari'] ?? '') === (string) ($current['hari'] ?? '')
            && (int) ($existing['jam_ke_mulai'] ?? 0) === (int) ($current['jam_ke_mulai'] ?? 0)
            && (int) ($existing['jam_ke_selesai'] ?? 0) === (int) ($current['jam_ke_selesai'] ?? 0);
    }

    private function teacherLoadKey(array $item): string
    {
        $parallelGroupId = (int) ($item['parallel_group_id'] ?? 0);
        if ($parallelGroupId > 0) {
            return implode(':', [
                'parallel',
                $parallelGroupId,
                (int) ($item['guru_mata_pelajaran_id'] ?? 0),
                (string) ($item['hari'] ?? ''),
                (int) ($item['jam_ke_mulai'] ?? 0),
                (int) ($item['jam_ke_selesai'] ?? 0),
            ]);
        }

        return 'item:' . (int) ($item['id'] ?? 0);
    }

    private function describeItem(array $item): string
    {
        $subject = trim((string) ($item['mata_pelajaran_kode'] ?? '') . ' ' . (string) ($item['mata_pelajaran_nama'] ?? 'Mapel'));
        $teacher = trim((string) ($item['guru_nama'] ?? ('Guru #' . (int) ($item['guru_id'] ?? 0))));
        $class = $this->classLabel($item);

        return trim($subject . ' - ' . $teacher . ' - ' . $class);
    }
}
