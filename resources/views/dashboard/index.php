<?php
    $homeroomProgressData = isset($homeroomProgress) && is_array($homeroomProgress) ? $homeroomProgress : null;
    $progressCategories = $homeroomProgressData['categories'] ?? [];
    $overallProgress = is_array($homeroomProgressData) ? ($homeroomProgressData['overall'] ?? null) : null;
    $overallPercentageValue = isset($overallProgress['percentage']) ? (float) $overallProgress['percentage'] : null;
    $overallPercentageValue = $overallPercentageValue !== null ? max(0.0, min(100.0, $overallPercentageValue)) : null;
    $overallGaugeDegrees = $overallPercentageValue !== null ? $overallPercentageValue * 3.6 : 0.0;
    $overallGaugeStyle = $overallPercentageValue !== null
        ? sprintf('background: conic-gradient(#4f46e5 %sdeg, rgba(79, 70, 229, 0.12) 0deg);', number_format($overallGaugeDegrees, 2, '.', ''))
        : 'background: conic-gradient(#c7d2fe 0deg, rgba(148, 163, 184, 0.16) 0deg);';
    $progressActiveYear = $homeroomProgressData['activeYear'] ?? null;
    $progressStudentCount = isset($homeroomProgressData['studentCount']) ? (int) $homeroomProgressData['studentCount'] : 0;
    $progressClassCount = isset($homeroomProgressData['classesCount']) ? (int) $homeroomProgressData['classesCount'] : 0;

    $classSummariesData = isset($classSummaries) && is_array($classSummaries) ? $classSummaries : [];
    $activeSchoolYearData = isset($activeSchoolYear) && is_array($activeSchoolYear) ? $activeSchoolYear : null;
    $unassignedSummaryData = [
        'total' => isset($unassignedSummary['total']) ? (int) $unassignedSummary['total'] : 0,
        'male' => isset($unassignedSummary['male']) ? (int) $unassignedSummary['male'] : 0,
        'female' => isset($unassignedSummary['female']) ? (int) $unassignedSummary['female'] : 0,
    ];

    $totalClassStudents = 0;
    $totalClassMale = 0;
    $totalClassFemale = 0;

    foreach ($classSummariesData as $summaryRow) {
        if (!is_array($summaryRow)) {
            continue;
        }
        $totalClassStudents += (int) ($summaryRow['total_students'] ?? 0);
        $totalClassMale += (int) ($summaryRow['total_male'] ?? 0);
        $totalClassFemale += (int) ($summaryRow['total_female'] ?? 0);
    }

    $summarySubtitle = 'Seluruh kelas';
    if ($activeSchoolYearData !== null && !empty($activeSchoolYearData['nama'])) {
        $summarySubtitle = sprintf('Tahun ajaran %s', $activeSchoolYearData['nama']);
    }

    $teacherScheduleData = isset($teacherSchedule) && is_array($teacherSchedule) ? $teacherSchedule : [];
    $teacherScheduleNotifications = [];
    $pwaNotificationIconUrl = absolute_url(app_icon_asset('icons/icon-192.png'));
    $isStudentUser = isset($isStudent) ? (bool) $isStudent : false;
    $isTeacherUser = isset($isTeacher) ? (bool) $isTeacher : false;
    $teacherMetricsData = isset($teacherMetrics) && is_array($teacherMetrics) ? $teacherMetrics : null;
    $studentSubjectsData = isset($studentSubjects) && is_array($studentSubjects) ? $studentSubjects : [];
    $studentAttendanceSummaryRaw = isset($studentAttendanceSummary) && is_array($studentAttendanceSummary) ? $studentAttendanceSummary : [];
    $studentAttendanceSummaryData = [
        'hadir' => (int) ($studentAttendanceSummaryRaw['hadir'] ?? 0),
        'izin' => (int) ($studentAttendanceSummaryRaw['izin'] ?? 0),
        'sakit' => (int) ($studentAttendanceSummaryRaw['sakit'] ?? 0),
        'bolos' => (int) ($studentAttendanceSummaryRaw['bolos'] ?? 0),
        'alpa' => (int) ($studentAttendanceSummaryRaw['alpa'] ?? 0),
    ];
    $studentWeekRangeData = isset($studentWeekRange) && is_array($studentWeekRange) ? $studentWeekRange : null;
    $studentInfoData = isset($studentInfo) && is_array($studentInfo) ? $studentInfo : null;
    $studentClassLabel = null;
    if ($studentInfoData !== null) {
        $classGrade = trim((string) ($studentInfoData['kelas_tingkat'] ?? ''));
        $className = trim((string) ($studentInfoData['kelas_nama'] ?? ''));
        if ($classGrade !== '' || $className !== '') {
            $studentClassLabel = trim(sprintf('Kelas %s %s', $classGrade, $className));
        }
        if (!empty($studentInfoData['jurusan_nama'] ?? '')) {
            $studentClassLabel = trim(($studentClassLabel ?? 'Kelas') . ' (' . $studentInfoData['jurusan_nama'] . ')');
        }
    }
    $studentSummaryTotal = array_sum($studentAttendanceSummaryData);
    $studentScoreSummaryData = isset($studentScoreSummary) && is_array($studentScoreSummary) ? $studentScoreSummary : null;
    $studentScoreOverallAverageValue = $studentScoreSummaryData !== null && $studentScoreSummaryData['overall_average'] !== null
        ? (float) $studentScoreSummaryData['overall_average']
        : null;
    $studentScoreKnowledgeAverageValue = $studentScoreSummaryData !== null && $studentScoreSummaryData['knowledge_average'] !== null
        ? (float) $studentScoreSummaryData['knowledge_average']
        : null;
    $studentScoreSkillAverageValue = $studentScoreSummaryData !== null && $studentScoreSummaryData['skill_average'] !== null
        ? (float) $studentScoreSummaryData['skill_average']
        : null;
    $studentScoreSubjectsTotal = $studentScoreSummaryData !== null ? (int) ($studentScoreSummaryData['total_subjects'] ?? 0) : 0;
    $studentScoreSubjectsCompleted = $studentScoreSummaryData !== null ? (int) ($studentScoreSummaryData['completed_subjects'] ?? 0) : 0;
    $studentScoreSubjectsFull = $studentScoreSummaryData !== null ? (int) ($studentScoreSummaryData['subjects_with_full_scores'] ?? 0) : 0;
    $studentScoreSubjectsPending = $studentScoreSummaryData !== null ? (int) ($studentScoreSummaryData['pending_subjects'] ?? max(0, $studentScoreSubjectsTotal - $studentScoreSubjectsCompleted)) : max(0, $studentScoreSubjectsTotal - $studentScoreSubjectsCompleted);
    $studentScoreLastUpdatedRaw = $studentScoreSummaryData !== null ? (string) ($studentScoreSummaryData['last_updated_at'] ?? '') : '';
    $studentScoreLastUpdatedLabel = $studentScoreLastUpdatedRaw !== '' && strtotime($studentScoreLastUpdatedRaw) !== false
        ? date('d M Y H:i', strtotime($studentScoreLastUpdatedRaw))
        : null;
    $studentCurriculumRaw = isset($studentCurriculum) ? (string) $studentCurriculum : 'k13';
    $studentCurriculumCode = strtolower($studentCurriculumRaw) === 'kurmer' ? 'kurmer' : 'k13';
    $studentIsKurmer = $studentCurriculumCode === 'kurmer';
    $studentCurriculumLabel = $studentIsKurmer ? 'Kurikulum Merdeka' : 'Kurikulum 2013';
    $studentCurriculumBadge = $studentIsKurmer
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-100'
        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-100';
    $kurmerLevelLabels = [
        'BB' => 'Belum Berkembang',
        'MB' => 'Mulai Berkembang',
        'BSH' => 'Berkembang Sesuai Harapan',
        'SB' => 'Sangat Berkembang',
    ];
    $formatScore = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (!is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    };
    $studentScoredSubjects = $studentIsKurmer
        ? max($studentScoreSubjectsFull, $studentScoreSubjectsCompleted)
        : $studentScoreSubjectsCompleted;
    $studentPendingSubjects = max(0, $studentScoreSubjectsTotal - $studentScoredSubjects);
    $isAdminUser = isset($isAdmin) ? (bool) $isAdmin : false;
    $greetingLabelValue = isset($greetingLabel) ? (string) $greetingLabel : 'Selamat datang';
    $greetingMessageValue = isset($greetingMessage) ? (string) $greetingMessage : '';
    $userDisplayNameValue = isset($userDisplayName) ? (string) $userDisplayName : 'Pengguna';
    $userRoleLabelsListRaw = isset($userRoleLabels) && is_array($userRoleLabels) ? $userRoleLabels : [];
    $userRoleLabelsList = array_values(array_filter(array_map(
        static fn ($label) => trim((string) $label),
        $userRoleLabelsListRaw
    ), static fn ($label) => $label !== ''));
    $prakerinSupervisionListRaw = isset($prakerinSupervisions) && is_array($prakerinSupervisions) ? $prakerinSupervisions : [];
    $extracurricularMentorshipListRaw = isset($extracurricularMentorships) && is_array($extracurricularMentorships) ? $extracurricularMentorships : [];
    $prakerinSupervisionNames = array_values(array_filter(array_map(
        static fn ($item) => isset($item['nama']) ? trim((string) $item['nama']) : '',
        $prakerinSupervisionListRaw
    ), static fn ($name) => $name !== ''));
    $extracurricularMentorshipNames = array_values(array_filter(array_map(
        static fn ($item) => isset($item['nama']) ? trim((string) $item['nama']) : '',
        $extracurricularMentorshipListRaw
    ), static fn ($name) => $name !== ''));
    $scheduleDayOrder = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
    $scheduleDayLabels = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];
    $teacherScheduleByDay = [];

    foreach ($teacherScheduleData as $scheduleRow) {
        if (!is_array($scheduleRow)) {
            continue;
        }

        $dayKey = (string) ($scheduleRow['hari'] ?? '');
        if ($dayKey === '') {
            $dayKey = 'lainnya';
            if (!isset($scheduleDayLabels[$dayKey])) {
                $scheduleDayLabels[$dayKey] = 'Lainnya';
                $scheduleDayOrder[] = $dayKey;
            }
        } elseif (!isset($scheduleDayLabels[$dayKey])) {
            $scheduleDayLabels[$dayKey] = ucfirst($dayKey);
            $scheduleDayOrder[] = $dayKey;
        }

        $startRaw = $scheduleRow['waktu_mulai'] ?? null;
        $endRaw = $scheduleRow['waktu_selesai'] ?? null;
        $startFormatted = ($startRaw !== null && $startRaw !== '' && strtotime((string) $startRaw) !== false)
            ? date('H:i', strtotime((string) $startRaw))
            : '—';
        $endFormatted = ($endRaw !== null && $endRaw !== '' && strtotime((string) $endRaw) !== false)
            ? date('H:i', strtotime((string) $endRaw))
            : '—';

        $classLabel = sprintf('Kelas %s %s', $scheduleRow['kelas_tingkat'] ?? '-', $scheduleRow['kelas_nama'] ?? '-');
        if (!empty($scheduleRow['jurusan_nama'])) {
            $classLabel .= sprintf(' (%s)', $scheduleRow['jurusan_nama']);
        }

        $subjectCode = trim((string) ($scheduleRow['mata_pelajaran_kode'] ?? ''));
        $subjectName = trim((string) ($scheduleRow['mata_pelajaran_nama'] ?? ''));
        $subjectLabel = $subjectCode !== ''
            ? trim(sprintf('%s - %s', $subjectCode, $subjectName !== '' ? $subjectName : '-'))
            : ($subjectName !== '' ? $subjectName : '-');

        $teacherScheduleByDay[$dayKey] ??= [
            'label' => $scheduleDayLabels[$dayKey] ?? ucfirst($dayKey),
            'items' => [],
        ];

        $teacherScheduleByDay[$dayKey]['items'][] = [
            'subject' => $subjectLabel,
            'class' => $classLabel,
            'time' => sprintf('%s - %s', $startFormatted, $endFormatted),
            'hours' => isset($scheduleRow['jumlah_jam']) ? (int) $scheduleRow['jumlah_jam'] : null,
        ];

        if ($startRaw !== null && $startRaw !== '') {
            $scheduleId = isset($scheduleRow['id']) ? (int) $scheduleRow['id'] : 0;
            $notificationKey = $scheduleId > 0
                ? 'schedule-' . $scheduleId
                : 'schedule-' . md5($dayKey . '|' . $startRaw . '|' . $subjectLabel . '|' . $classLabel);

            $teacherScheduleNotifications[] = [
                'key' => $notificationKey,
                'day' => $dayKey,
                'start' => (string) $scheduleRow['waktu_mulai'],
                'subject' => $subjectLabel,
                'class' => $classLabel,
            ];
        }
    }

    $hasTeacherSchedule = !empty($teacherScheduleByDay);
    $pendingActionsData = isset($pendingActions) && is_array($pendingActions) ? $pendingActions : [];
    $pendingActionsList = array_values(array_filter(array_map(
        static function ($action) {
            if (!is_array($action)) {
                return null;
            }

            $count = isset($action['count']) ? (int) $action['count'] : 0;
            if ($count <= 0) {
                return null;
            }

            $label = (string) ($action['label'] ?? 'Tugas belum selesai');
            $description = (string) ($action['description'] ?? '');
            $roleLabel = (string) ($action['role'] ?? '');
            $url = (string) ($action['url'] ?? '#');
            $key = (string) ($action['key'] ?? '');

            if ($key === '') {
                $key = 'action-' . md5($label . $count . $url);
            }

            return [
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'role' => $roleLabel,
                'count' => $count,
                'url' => $url !== '' ? $url : '#',
            ];
        },
        $pendingActionsData
    ), static fn ($action) => $action !== null));
?>
<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-12">
        <div class="rounded-2xl border border-indigo-100 bg-gradient-to-r from-white via-indigo-50 to-indigo-100 p-6 shadow-sm dark:from-slate-900 dark:via-slate-900/80 dark:to-slate-900/60">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300"><?= htmlspecialchars($greetingLabelValue, ENT_QUOTES, 'UTF-8') ?></p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($userDisplayNameValue, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($greetingMessageValue !== ''): ?>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300"><?= htmlspecialchars($greetingMessageValue, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
</div>

<?php if (!empty($teacherScheduleNotifications)): ?>
    <script>
        (function () {
            if (!("Notification" in window) || !("serviceWorker" in navigator)) {
                return;
            }

            const scheduleItems = <?= json_encode($teacherScheduleNotifications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const notificationIcon = <?= json_encode($pwaNotificationIconUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            if (!Array.isArray(scheduleItems) || scheduleItems.length === 0) {
                return;
            }

            const DAY_MAP = { minggu: 0, senin: 1, selasa: 2, rabu: 3, kamis: 4, jumat: 5, sabtu: 6 };
            const OFFSET_MINUTES = 10;
            const OFFSET_MS = OFFSET_MINUTES * 60 * 1000;
            const STORAGE_KEY = "siakad:pwa:schedule-notifications";

            const sanitizedSchedules = scheduleItems
                .map((item) => ({
                    key: item.key,
                    day: (item.day || "").toLowerCase(),
                    start: (item.start || "").trim(),
                    subject: item.subject || "Jadwal Pelajaran",
                    className: item.class || "",
                }))
                .filter((item) => item.start !== "" && DAY_MAP[item.day] !== undefined);

            if (sanitizedSchedules.length === 0) {
                return;
            }

            let sentRegistry = {};

            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    sentRegistry = JSON.parse(stored) || {};
                }
            } catch (error) {
                sentRegistry = {};
            }

            const persistRegistry = () => {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(sentRegistry));
                } catch (error) {
                    // Ignore persistence issues (quota/private mode).
                }
            };

            const pruneRegistry = () => {
                const nowTs = Date.now();
                const threshold = nowTs - 14 * 24 * 60 * 60 * 1000;
                let changed = false;

                Object.entries(sentRegistry).forEach(([key, timestamp]) => {
                    if (typeof timestamp === "number" && timestamp < threshold) {
                        delete sentRegistry[key];
                        changed = true;
                    }
                });

                if (changed) {
                    persistRegistry();
                }
            };

            const computeNextOccurrence = (item) => {
                const [hourStr, minuteStr] = item.start.split(":");
                const hour = parseInt(hourStr, 10);
                const minute = parseInt(minuteStr, 10);

                if (Number.isNaN(hour) || Number.isNaN(minute)) {
                    return null;
                }

                const now = new Date();
                const currentDay = now.getDay();
                const targetDay = DAY_MAP[item.day];
                let delta = (targetDay - currentDay + 7) % 7;

                const startDate = new Date(now);
                startDate.setHours(hour, minute, 0, 0);

                if (delta !== 0) {
                    startDate.setDate(startDate.getDate() + delta);
                }

                if (delta === 0 && startDate <= now) {
                    startDate.setDate(startDate.getDate() + 7);
                }

                return startDate;
            };

            const formatTime = (date) => date.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });

            let permissionRequest = null;

            const ensurePermission = () => {
                if (Notification.permission === "granted") {
                    return Promise.resolve(true);
                }

                if (Notification.permission === "denied") {
                    return Promise.resolve(false);
                }

                if (permissionRequest) {
                    return permissionRequest;
                }

                permissionRequest = Notification.requestPermission()
                    .then((result) => result === "granted")
                    .catch(() => false);

                return permissionRequest;
            };

            const sendNotification = (registration, item, startDate, registryKey) => {
                const title = `${item.subject} akan dimulai`;
                const bodyParts = [];

                if (item.className) {
                    bodyParts.push(item.className);
                }

                bodyParts.push(`Mulai pukul ${formatTime(startDate)}`);

                const options = {
                    body: bodyParts.join(" • "),
                    icon: notificationIcon,
                    badge: notificationIcon,
                    tag: registryKey,
                    data: {
                        scheduleKey: item.key,
                        startTime: startDate.toISOString(),
                    },
                };

                const markAsSent = () => {
                    sentRegistry[registryKey] = startDate.getTime();
                    persistRegistry();
                };

                if (registration) {
                    registration.showNotification(title, options).then(markAsSent).catch(() => {
                        new Notification(title, options);
                        markAsSent();
                    });
                } else {
                    new Notification(title, options);
                    markAsSent();
                }
            };

            const evaluateSchedules = () => {
                pruneRegistry();

                const now = new Date();
                const candidates = sanitizedSchedules
                    .map((item) => {
                        const startDate = computeNextOccurrence(item);
                        if (!startDate) {
                            return null;
                        }

                        const notifyWindowStart = startDate.getTime() - OFFSET_MS;
                        if (now.getTime() < notifyWindowStart || now.getTime() >= startDate.getTime()) {
                            return null;
                        }

                        const registryKey = `${item.key}:${startDate.getTime()}`;
                        if (sentRegistry[registryKey]) {
                            return null;
                        }

                        return { item, startDate, registryKey };
                    })
                    .filter(Boolean);

            if (candidates.length === 0) {
                return;
            }

                ensurePermission().then((granted) => {
                    if (!granted) {
                        return;
                    }

                    navigator.serviceWorker.getRegistration().then((registration) => {
                        candidates.forEach(({ item, startDate, registryKey }) => {
                            sendNotification(registration, item, startDate, registryKey);
                        });
                    });
                });
            };

            evaluateSchedules();
            setInterval(evaluateSchedules, 60000);
        })();
    </script>
<?php endif; ?>
                <?php if (!empty($userRoleLabelsList) || !empty($prakerinSupervisionNames) || !empty($extracurricularMentorshipNames)): ?>
                    <div class="rounded-2xl border border-indigo-200/60 bg-white/80 px-4 py-3 text-sm text-slate-600 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-800/80 dark:text-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Peran Aktif</p>
                        <?php if (!empty($userRoleLabelsList)): ?>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <?php foreach ($userRoleLabelsList as $label): ?>
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">
                                        <i class="ri-user-star-line mr-1 text-sm"></i>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($prakerinSupervisionNames) || !empty($extracurricularMentorshipNames)): ?>
                            <div class="mt-3 space-y-2 text-xs text-slate-500 dark:text-slate-300">
                                <?php if (!empty($prakerinSupervisionNames)): ?>
                                    <p>
                                        <span class="font-semibold text-slate-600 dark:text-slate-200">Tempat Prakerin:</span>
                                        <?= htmlspecialchars(implode(', ', $prakerinSupervisionNames), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($extracurricularMentorshipNames)): ?>
                                    <p>
                                        <span class="font-semibold text-slate-600 dark:text-slate-200">Ekskul Binaan:</span>
                                        <?= htmlspecialchars(implode(', ', $extracurricularMentorshipNames), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-12">
        <?php if (!empty($pendingActionsList)): ?>
            <div class="rounded-2xl border border-rose-100 bg-gradient-to-r from-rose-50 via-white to-orange-50 p-5 shadow-sm dark:border-rose-900/40 dark:from-slate-900 dark:via-slate-900/80 dark:to-slate-900/60">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-500">Akses Cepat</p>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-gray-100">Tindakan yang belum selesai</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-300">Langsung lompat ke tugas yang masih menunggu aksi Anda.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-semibold text-rose-600 shadow-sm dark:bg-slate-800/80 dark:text-rose-200">
                        <i class="ri-time-line text-sm"></i>
                        <?= number_format(count($pendingActionsList)) ?> jenis tugas
                    </span>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($pendingActionsList as $action): ?>
                        <?php
                            $countLabel = number_format((int) ($action['count'] ?? 0));
                            $actionLabel = (string) ($action['label'] ?? 'Tugas');
                            $actionDescription = (string) ($action['description'] ?? '');
                            $actionRole = (string) ($action['role'] ?? '');
                            $actionUrl = (string) ($action['url'] ?? '#');
                            $actionKey = (string) ($action['key'] ?? '');
                        ?>
                        <div class="space-y-3">
                            <a href="<?= htmlspecialchars($actionUrl !== '' ? $actionUrl : '#', ENT_QUOTES, 'UTF-8') ?>" class="group flex flex-col rounded-xl border border-rose-100 bg-white/80 p-4 shadow-sm ring-1 ring-transparent transition hover:-translate-y-0.5 hover:border-rose-200 hover:ring-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-300 dark:border-rose-900/40 dark:bg-slate-900/60">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-rose-500">Belum selesai</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600 dark:bg-rose-500/10 dark:text-rose-200">
                                        <i class="ri-notification-badge-line text-sm"></i>
                                        <?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8') ?> item
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-gray-100"><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($actionDescription !== ''): ?>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300"><?= htmlspecialchars($actionDescription, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($actionRole !== ''): ?>
                                    <span class="mt-3 inline-flex w-fit items-center gap-1 rounded-full bg-rose-50 px-3 py-1 text-[11px] font-semibold text-rose-600 dark:bg-rose-500/10 dark:text-rose-200">
                                        <i class="ri-user-star-line text-sm"></i>
                                        <?= htmlspecialchars($actionRole, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                                <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-200">
                                    <span>Kerjakan sekarang</span>
                                    <i class="ri-arrow-right-up-line text-sm transition group-hover:translate-x-0.5"></i>
                                </span>
                            </a>
                            <?php if ($actionKey === 'homeroom-prakerin' && !empty($homeroomPrakerinConfirmationClasses)): ?>
                                <div class="rounded-2xl border border-slate-200 bg-white/90 p-4 text-sm text-slate-600 shadow-sm dark:border-slate-700/40 dark:bg-slate-900/60 dark:text-slate-300">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Pilihan konfirmasi</p>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-gray-100">Apakah prakerin dilaksanakan di kelas berikut?</p>
                                    <div class="mt-3 space-y-3">
                                        <?php foreach ($homeroomPrakerinConfirmationClasses as $classConfirmation): ?>
                                            <?php
                                                $classId = (int) ($classConfirmation['id'] ?? 0);
                                                $classLabel = (string) ($classConfirmation['label'] ?? 'Kelas');
                                                $requiredStatus = $classConfirmation['required'];
                                                $statusLabel = 'Belum dikonfirmasi';
                                                if ($requiredStatus === true) {
                                                    $statusLabel = 'Dikonfirmasi dijalankan';
                                                } elseif ($requiredStatus === false) {
                                                    $statusLabel = 'Tidak dilaksanakan';
                                                }
                                            ?>
                                            <form action="<?= htmlspecialchars(base_url('walikelas/prakerin/konfirmasi'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-3 dark:border-slate-700/50 dark:bg-slate-900/60">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Kelas <?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                                        <p class="text-xs text-slate-500 dark:text-slate-400">Status: <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button
                                                            type="submit"
                                                            name="prakerin_required"
                                                            value="1"
                                                            class="inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $requiredStatus === true ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700/50 dark:bg-slate-800 dark:text-slate-200' ?>"
                                                        >
                                                            Ya
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            name="prakerin_required"
                                                            value="0"
                                                            class="inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-semibold transition <?= $requiredStatus === false ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-700/50 dark:bg-slate-800 dark:text-slate-200' ?>"
                                                        >
                                                            Tidak
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-rose-100 bg-gradient-to-r from-rose-50 via-white to-orange-50 p-5 shadow-sm dark:border-rose-900/40 dark:from-slate-900 dark:via-slate-900/80 dark:to-slate-900/60">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-rose-500">Akses Cepat</p>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-gray-100">Tindakan yang belum selesai</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-300">Langsung lompat ke tugas yang masih menunggu aksi Anda.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-800/80 dark:text-slate-300">
                        <i class="ri-calendar-check-line text-sm"></i>
                        Semua tugas selesai
                    </span>
                </div>
                <div class="mt-4">
                    <div class="rounded-xl border border-slate-200 bg-white/80 p-6 text-center text-sm text-slate-500 shadow-sm dark:border-slate-700/40 dark:bg-slate-900/60 dark:text-slate-300">
                        <p class="text-sm font-semibold text-slate-900 dark:text-gray-100">Yey, semua tugas sudah beres <span aria-hidden="true">🎉</span></p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Kalau nanti ada tugas baru, pengingat ini akan kembali lagi.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$isStudentUser): ?>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 lg:col-span-12">
            <?php if ($isTeacherUser && $teacherMetricsData !== null): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Total Jam Mengajar</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format((int) ($teacherMetricsData['total_hours'] ?? 0)) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Jumlah Kelas Diajar</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format((int) ($teacherMetricsData['class_count'] ?? 0)) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Mata Pelajaran Diampu</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format((int) ($teacherMetricsData['subject_count'] ?? 0)) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Pengajuan Belum di ACC</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format((int) ($teacherMetricsData['pending_submissions'] ?? 0)) ?></p>
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Total Siswa</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format($metrics['students'] ?? 0) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Total Guru</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format($metrics['teachers'] ?? 0) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Total Kelas</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format($metrics['classes'] ?? 0) ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Tahun Ajaran</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900"><?= number_format($metrics['years'] ?? 0) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($isStudentUser): ?>
        <div class="lg:col-span-12">
            <?php include resource_path('views/student/dashboard/attendance-link.php'); ?>
        </div>
        <div class="grid gap-4 lg:grid-cols-12 lg:col-span-12">
            <div class="lg:col-span-4">
                <div class="h-full rounded-2xl border border-emerald-100 bg-emerald-50 p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-emerald-800">Rekap Kehadiran Minggu Ini</h2>
                            <?php if ($studentWeekRangeData !== null): ?>
                                <?php
                                    $weekStartLabel = htmlspecialchars(date('d M', strtotime((string) $studentWeekRangeData['start'])), ENT_QUOTES, 'UTF-8');
                                    $weekEndLabel = htmlspecialchars(date('d M Y', strtotime((string) $studentWeekRangeData['end'])), ENT_QUOTES, 'UTF-8');
                                ?>
                                <p class="text-xs text-emerald-600">
                                    <?= $weekStartLabel ?> &mdash; <?= $weekEndLabel ?>
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-emerald-600">Minggu berjalan</p>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex items-center justify-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-600">
                            <i class="ri-user-smile-line mr-1"></i>
                            <?= number_format($studentSummaryTotal) ?> sesi
                        </span>
                    </div>
                    <?php if ($studentSummaryTotal === 0): ?>
                        <p class="mt-4 rounded-xl border border-dashed border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700">
                            Belum ada presensi tercatat pada minggu ini.
                        </p>
                    <?php else: ?>
                        <dl class="mt-5 grid gap-3">
                            <?php foreach ($studentAttendanceSummaryData as $statusKey => $value): ?>
                                <?php
                                    $labelMap = [
                                        'hadir' => 'Hadir',
                                        'izin' => 'Izin',
                                        'sakit' => 'Sakit',
                                        'bolos' => 'Bolos',
                                        'alpa' => 'Tanpa Keterangan',
                                    ];
                                    $label = $labelMap[$statusKey] ?? ucfirst($statusKey);
                                    $badgeClass = match ($statusKey) {
                                        'hadir' => 'border-emerald-200 text-emerald-700',
                                        'izin' => 'border-amber-200 text-amber-700',
                                        'sakit' => 'border-sky-200 text-sky-700',
                                        default => 'border-slate-200 text-slate-600',
                                    };
                                ?>
                                <div class="rounded-xl border <?= $badgeClass ?> bg-white px-4 py-3 text-sm">
                                    <dt class="text-xs font-semibold uppercase tracking-wide"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                    <dd class="mt-1 text-lg font-semibold"><?= number_format((int) $value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lg:col-span-8">
                <div class="h-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-800">Mata Pelajaran Aktif</h2>
                            <?php if ($studentClassLabel !== null): ?>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars($studentClassLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                            <i class="ri-book-2-line text-sm"></i>
                            <?= number_format(count($studentSubjectsData)) ?> mapel
                        </span>
                    </div>
                    <?php if (empty($studentSubjectsData)): ?>
                        <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                            Data mata pelajaran belum tersedia. Silakan hubungi wali kelas atau admin.
                        </p>
                    <?php else: ?>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <?php foreach ($studentSubjectsData as $subject): ?>
                                <?php
                                    if (!is_array($subject)) {
                                        continue;
                                    }
                                    $subjectName = (string) ($subject['name'] ?? 'Mata Pelajaran');
                                    $subjectCode = (string) ($subject['code'] ?? '');
                                    $teacherName = (string) ($subject['teacher'] ?? '');
                                    $subjectCurriculum = strtolower((string) ($subject['curriculum'] ?? 'k13')) === 'kurmer' ? 'kurmer' : 'k13';
                                    $isSubjectKurmer = $subjectCurriculum === 'kurmer' || $studentIsKurmer;
                                    $finalScoreValue = isset($subject['final_score']) && $subject['final_score'] !== null
                                        ? (float) $subject['final_score']
                                        : null;
                                    $knowledgeScoreValue = isset($subject['knowledge_score']) && $subject['knowledge_score'] !== null
                                        ? (float) $subject['knowledge_score']
                                        : null;
                                    $skillScoreValue = isset($subject['skill_score']) && $subject['skill_score'] !== null
                                        ? (float) $subject['skill_score']
                                        : null;
                                    $finalScoreLabel = $finalScoreValue !== null ? number_format($finalScoreValue, 2, ',', '.') : 'Belum dinilai';
                                    $knowledgeScoreLabel = $knowledgeScoreValue !== null ? number_format($knowledgeScoreValue, 2, ',', '.') : '—';
                                    $skillScoreLabel = $skillScoreValue !== null ? number_format($skillScoreValue, 2, ',', '.') : '—';
                                    $knowledgePredicate = isset($subject['knowledge_predicate']) ? trim((string) $subject['knowledge_predicate']) : '';
                                    $skillPredicate = isset($subject['skill_predicate']) ? trim((string) $subject['skill_predicate']) : '';
                                    $finalBadgeClass = $finalScoreValue !== null
                                        ? 'bg-indigo-100 text-indigo-700'
                                        : 'bg-slate-200 text-slate-600';
                                    $kurmerSummary = isset($subject['kurmer_summary']) && is_array($subject['kurmer_summary']) ? $subject['kurmer_summary'] : [];
                                    $kurmerCapaianCode = strtoupper(trim((string) ($kurmerSummary['capaian_akhir_enum'] ?? $kurmerSummary['capaian'] ?? '')));
                                    $kurmerCapaianLabel = $kurmerCapaianCode !== '' ? ($kurmerLevelLabels[$kurmerCapaianCode] ?? $kurmerCapaianCode) : null;
                                    $kurmerDescription = trim((string) ($kurmerSummary['deskripsi_umum'] ?? $kurmerSummary['description'] ?? ''));
                                    $kurmerTindakLanjut = trim((string) ($kurmerSummary['tindak_lanjut'] ?? ''));
                                    $kurmerOptionalScore = $kurmerSummary['nilai_opsional'] ?? $kurmerSummary['score'] ?? $finalScoreValue;
                                    $kurmerLevelBadge = match ($kurmerCapaianCode) {
                                        'SB' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-100',
                                        'BSH' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-100',
                                        'MB' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-100',
                                        'BB' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-100',
                                        default => 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
                                    };
                                    $kurmerTpSummary = '';
                                    $tpSourcesRaw = $kurmerSummary['sumber_tp'] ?? $kurmerSummary['tp_sources'] ?? [];
                                    if (is_string($tpSourcesRaw)) {
                                        $decodedTp = json_decode($tpSourcesRaw, true);
                                        $tpSourcesRaw = is_array($decodedTp) ? $decodedTp : [];
                                    }
                                    $tpSources = array_values(array_filter($tpSourcesRaw, static fn ($item) => is_array($item)));
                                    if (!empty($tpSources)) {
                                        $tpParts = [];
                                        $used = 0;
                                        foreach (array_slice($tpSources, 0, 2) as $tp) {
                                            $used++;
                                            $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? ''));
                                            $tpDesc = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));
                                            $label = $code !== '' ? $code : 'TP';
                                            $tpParts[] = $tpDesc !== '' ? ($label !== '' ? $label . ' - ' . $tpDesc : $tpDesc) : $label;
                                        }
                                        $remaining = count($tpSources) - $used;
                                        if ($remaining > 0) {
                                            $tpParts[] = $remaining . ' TP lain';
                                        }
                                        $tpParts = array_values(array_filter($tpParts, static fn ($item) => $item !== ''));
                                        $kurmerTpSummary = implode('; ', $tpParts);
                                    }
                                ?>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                                <?= htmlspecialchars($subjectCode !== '' ? $subjectCode : 'MAPEL', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <h3 class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?>
                                            </h3>
                                            <?php if ($teacherName !== ''): ?>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">
                                                    Pengampu: <?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isSubjectKurmer): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold <?= htmlspecialchars($kurmerLevelBadge, ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="ri-seedling-line"></i>
                                                <?= htmlspecialchars($kurmerCapaianCode !== '' ? $kurmerCapaianCode : 'BB–SB', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($kurmerCapaianLabel !== null && $kurmerCapaianLabel !== $kurmerCapaianCode): ?>
                                                    <span class="text-[11px] font-normal">(<?= htmlspecialchars($kurmerCapaianLabel, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold <?= $finalBadgeClass ?>">
                                                <i class="ri-medal-line"></i>
                                                <?= htmlspecialchars($finalScoreLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isSubjectKurmer): ?>
                                        <div class="mt-3 text-sm text-slate-700 dark:text-slate-100">
                                            <?php if ($kurmerDescription !== '' || $kurmerTindakLanjut !== '' || $kurmerTpSummary !== ''): ?>
                                                <?php if ($kurmerDescription !== ''): ?>
                                                    <p><?= nl2br(htmlspecialchars($kurmerDescription, ENT_QUOTES, 'UTF-8')) ?></p>
                                                <?php endif; ?>
                                                <?php if ($kurmerTindakLanjut !== ''): ?>
                                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-200">Tindak lanjut: <?= nl2br(htmlspecialchars($kurmerTindakLanjut, ENT_QUOTES, 'UTF-8')) ?></p>
                                                <?php endif; ?>
                                                <?php if ($kurmerTpSummary !== ''): ?>
                                                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">TP: <?= htmlspecialchars($kurmerTpSummary, ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-xs text-slate-400 dark:text-slate-500">Belum ada narasi capaian.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-indigo-100 bg-white px-3 py-1 text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100">
                                                <i class="ri-pencil-ruler-2-line text-sm"></i>
                                                Nilai opsional: <?= htmlspecialchars($formatScore($kurmerOptionalScore), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-100 bg-white px-3 py-1 text-emerald-600 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                                                <i class="ri-brain-line text-sm"></i>
                                                <span><?= htmlspecialchars($knowledgeScoreLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if ($knowledgePredicate !== ''): ?>
                                                    <span class="font-semibold">(<?= htmlspecialchars($knowledgePredicate, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full border border-sky-100 bg-white px-3 py-1 text-sky-600 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                                                <i class="ri-rocket-line text-sm"></i>
                                                <span><?= htmlspecialchars($skillScoreLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if ($skillPredicate !== ''): ?>
                                                    <span class="font-semibold">(<?= htmlspecialchars($skillPredicate, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($hasTeacherSchedule): ?>
        <div class="lg:col-span-12">
            <div class="rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800">Jadwal Mengajar Anda</h2>
                        <?php if ($activeSchoolYearData !== null && !empty($activeSchoolYearData['nama'])): ?>
                            <p class="text-xs text-slate-400">
                                Tahun ajaran <?= htmlspecialchars((string) $activeSchoolYearData['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400">Daftar jadwal mengajar terurut per hari dan jam.</p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
                        <i class="ri-calendar-check-line text-sm"></i>
                        <?= htmlspecialchars((string) array_reduce($teacherScheduleByDay, static function (int $carry, array $dayData): int {
                            return $carry + count($dayData['items'] ?? []);
                        }, 0), ENT_QUOTES, 'UTF-8') ?> sesi
                    </span>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($scheduleDayOrder as $dayKey): ?>
                        <?php if (empty($teacherScheduleByDay[$dayKey]['items'])) { continue; } ?>
                        <?php
                            $dayData = $teacherScheduleByDay[$dayKey];
                            $dayLabel = (string) ($dayData['label'] ?? ucfirst($dayKey));
                            $dayItems = is_array($dayData['items'] ?? null) ? $dayData['items'] : [];
                        ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                    <?= htmlspecialchars((string) count($dayItems), ENT_QUOTES, 'UTF-8') ?> sesi
                                </span>
                            </div>
                            <ul class="mt-3 space-y-3">
                                <?php foreach ($dayItems as $item): ?>
                                    <li class="rounded-lg bg-white px-3 py-2 shadow-sm">
                                        <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($item['subject'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($item['class'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="mt-2 flex items-center justify-between text-[11px] font-semibold text-slate-500">
                                            <span class="inline-flex items-center gap-1">
                                                <i class="ri-time-line text-sm"></i>
                                                <?= htmlspecialchars($item['time'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if (!empty($item['hours'])): ?>
                                                <span class="inline-flex items-center gap-1">
                                                    <i class="ri-book-2-line text-sm"></i>
                                                    <?= htmlspecialchars((string) $item['hours'], ENT_QUOTES, 'UTF-8') ?> JP
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($isAdminUser && (!empty($classSummariesData) || $unassignedSummaryData['total'] > 0)): ?>
        <div class="lg:col-span-12">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800">Ringkasan Penempatan Siswa</h2>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($summarySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                            <i class="ri-team-line text-sm text-slate-500"></i>
                            <?= number_format($totalClassStudents, 0, ',', '.') ?> siswa terdaftar
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-blue-600">
                            <i class="ri-men-line text-sm"></i>
                            <?= number_format($totalClassMale, 0, ',', '.') ?> L
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-3 py-1 text-rose-600">
                            <i class="ri-women-line text-sm"></i>
                            <?= number_format($totalClassFemale, 0, ',', '.') ?> P
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-amber-600">
                            <i class="ri-user-question-line text-sm"></i>
                            <?= number_format($unassignedSummaryData['total'], 0, ',', '.') ?> belum ditempatkan
                        </span>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-semibold">Kelas</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Laki-laki</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold">Perempuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($classSummariesData)): ?>
                                <?php foreach ($classSummariesData as $summaryRow): ?>
                                    <?php if (!is_array($summaryRow)) { continue; } ?>
                                    <?php
                                        $className = (string) ($summaryRow['class_name'] ?? '-');
                                        $majorName = (string) ($summaryRow['major_name'] ?? '');
                                        $totalStudentsRow = (int) ($summaryRow['total_students'] ?? 0);
                                        $totalMaleRow = (int) ($summaryRow['total_male'] ?? 0);
                                        $totalFemaleRow = (int) ($summaryRow['total_female'] ?? 0);
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if ($majorName !== ''): ?>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars($majorName, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-slate-700"><?= number_format($totalStudentsRow, 0, ',', '.') ?></td>
                                        <td class="px-4 py-3 text-right text-blue-600"><?= number_format($totalMaleRow, 0, ',', '.') ?></td>
                                        <td class="px-4 py-3 text-right text-rose-600"><?= number_format($totalFemaleRow, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada data penempatan kelas yang tersedia.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-slate-50 text-sm font-semibold text-slate-700">
                            <tr>
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right"><?= number_format($totalClassStudents, 0, ',', '.') ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($totalClassMale, 0, ',', '.') ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($totalClassFemale, 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if ($unassignedSummaryData['total'] > 0): ?>
                    <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                        <p class="font-semibold">Catatan Penempatan</p>
                        <p class="mt-1">
                            Ada <?= number_format($unassignedSummaryData['total'], 0, ',', '.') ?> siswa belum ditempatkan ke kelas
                            (<?= number_format($unassignedSummaryData['male'], 0, ',', '.') ?> L · <?= number_format($unassignedSummaryData['female'], 0, ',', '.') ?> P).
                            Periksa menu <a href="<?= htmlspecialchars(base_url('master/siswa/penempatan'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-amber-700 underline underline-offset-2">Penempatan Siswa</a>
                            untuk melengkapi data.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($homeroomProgressData !== null): ?>
        <div class="grid gap-6 lg:col-span-12 xl:grid-cols-12">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-6 shadow-sm xl:col-span-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-indigo-900">Progres Wali Kelas</p>
                        <?php if (is_array($progressActiveYear) && !empty($progressActiveYear['nama'])): ?>
                            <p class="text-xs text-indigo-600">
                                Semester aktif: <?= htmlspecialchars((string) $progressActiveYear['nama'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-indigo-600">Rangkuman progres untuk kelas yang Anda ampu.</p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-white/60 px-3 py-1 text-xs font-semibold text-indigo-700 shadow-sm">
                        Wali Kelas
                    </span>
                </div>
                <div class="mt-6 flex flex-col items-center justify-center space-y-4">
                    <div class="relative flex h-40 w-40 items-center justify-center">
                        <div class="absolute inset-0 rounded-full border-8 border-white/40"></div>
                        <div class="absolute inset-0 rounded-full" style="<?= htmlspecialchars($overallGaugeStyle, ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="absolute inset-4 rounded-full bg-white shadow-lg"></div>
                        <div class="relative text-center">
                            <div class="text-3xl font-semibold text-indigo-700">
                                <?= $overallPercentageValue !== null ? htmlspecialchars(number_format($overallPercentageValue, 1, ',', '.')) . '%' : '—' ?>
                            </div>
                            <p class="text-xs text-indigo-500">Rata-rata progres</p>
                        </div>
                    </div>
                    <?php if ($progressClassCount > 0): ?>
                        <p class="text-xs text-indigo-700">
                            Mengelola <?= number_format($progressClassCount, 0, ',', '.') ?> kelas · <?= number_format($progressStudentCount, 0, ',', '.') ?> siswa
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-indigo-700">Belum ada kelas yang Anda ampu.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:col-span-8">
                <?php foreach ($progressCategories as $category): ?>
                    <?php if (!is_array($category)) { continue; } ?>
                    <?php
                        $categoryPercentage = isset($category['percentage']) ? (float) $category['percentage'] : null;
                        $categoryPercentage = $categoryPercentage !== null ? max(0.0, min(100.0, $categoryPercentage)) : null;
                        $progressWidth = $categoryPercentage !== null
                            ? number_format($categoryPercentage, 2, '.', '') . '%'
                            : '0%';
                        $completedCount = (int) ($category['completed'] ?? 0);
                        $totalCount = (int) ($category['total'] ?? 0);
                        $pendingCount = (int) ($category['pending'] ?? max(0, $totalCount - $completedCount));
                        $unitLabel = (string) ($category['unit'] ?? 'item');
                        $categoryLabel = (string) ($category['label'] ?? 'Kategori');
                        $categoryDescription = (string) ($category['description'] ?? '');

                        if ($pendingCount < 0) {
                            $pendingCount = 0;
                        }

                        $progressTone = 'bg-emerald-500';
                        if ($categoryPercentage === null) {
                            $progressTone = 'bg-slate-300';
                        } elseif ($categoryPercentage < 40) {
                            $progressTone = 'bg-rose-500';
                        } elseif ($categoryPercentage < 70) {
                            $progressTone = 'bg-amber-500';
                        }
                    ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if ($categoryDescription !== ''): ?>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($categoryDescription, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                <?= $categoryPercentage !== null ? htmlspecialchars(number_format($categoryPercentage, 1, ',', '.')) . '%' : '—' ?>
                            </span>
                        </div>
                        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full <?= $progressTone ?>"
                                style="width: <?= htmlspecialchars($progressWidth, ENT_QUOTES, 'UTF-8') ?>"
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                <?= $categoryPercentage !== null ? 'aria-valuenow="' . htmlspecialchars(number_format($categoryPercentage, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                            ></div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                            <?php if ($totalCount > 0): ?>
                                <span><?= number_format($completedCount, 0, ',', '.') ?> dari <?= number_format($totalCount, 0, ',', '.') ?> <?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($pendingCount > 0): ?>
                                    <span><?= number_format($pendingCount, 0, ',', '.') ?> belum terisi</span>
                                <?php else: ?>
                                    <span class="font-medium text-emerald-600">Sudah lengkap</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span>Belum ada target data</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="lg:col-span-7 space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Tahun Ajaran</h2>
                <a href="<?= htmlspecialchars(base_url('master/tahun-ajaran'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Kelola</a>
            </div>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach (array_slice($schoolYears, 0, 5) as $year): ?>
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($year['nama'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-400">
                                <?= htmlspecialchars($year['tanggal_mulai'], ENT_QUOTES, 'UTF-8') ?> -
                                <?= htmlspecialchars($year['tanggal_selesai'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= ($year['status'] ?? '') === 'aktif' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                            <?= htmlspecialchars(strtoupper($year['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($schoolYears)): ?>
                    <li class="py-6 text-center text-sm text-slate-400">Belum ada data tahun ajaran.</li>
                <?php endif; ?>
            </ul>
        </div>
        </div>

    <?php if ($isStudentUser): ?>
        <div class="lg:col-span-5 space-y-4">
            <div id="ringkasan-nilai" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-800 dark:text-gray-100">Ringkasan Nilai</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold <?= htmlspecialchars($studentCurriculumBadge, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="ri-book-3-line text-sm"></i>
                            <?= htmlspecialchars($studentCurriculumLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
                            <i class="ri-award-line text-sm"></i>
                            <?= number_format($studentScoredSubjects) ?> / <?= number_format($studentScoreSubjectsTotal) ?> mapel
                        </span>
                    </div>
                </div>
                <?php if ($studentScoreSummaryData === null): ?>
                    <p class="mt-4 text-sm text-slate-500 dark:text-slate-300">
                        Belum ada data nilai yang tercatat untuk tahun ajaran ini.
                    </p>
                <?php else: ?>
                    <?php if ($studentIsKurmer): ?>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Mapel Dinilai</p>
                                <p class="mt-2 text-xl font-semibold text-emerald-900 dark:text-emerald-100">
                                    <?= number_format($studentScoredSubjects) ?> / <?= number_format($studentScoreSubjectsTotal) ?>
                                </p>
                                <p class="text-[11px] text-emerald-700 dark:text-emerald-200/80">Capaian BB/MB/BSH/SB per mapel.</p>
                            </div>
                            <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm shadow-sm dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-500 dark:text-sky-200">Nilai Opsional</p>
                                <p class="mt-2 text-xl font-semibold text-sky-900 dark:text-sky-100">
                                    <?= htmlspecialchars($formatScore($studentScoreOverallAverageValue), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-sky-600 dark:text-sky-200/80">Rata-rata angka bila guru mengisi nilai.</p>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Status Penilaian</p>
                                <p class="mt-2 text-xl font-semibold text-amber-900 dark:text-amber-100">
                                    <?= number_format($studentPendingSubjects) ?> menunggu
                                </p>
                                <p class="text-[11px] text-amber-700 dark:text-amber-200/80"><?= number_format($studentScoreSubjectsFull) ?> mapel sudah punya capaian.</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Aktivitas Terakhir</p>
                                <p class="mt-2 text-xl font-semibold text-slate-700 dark:text-slate-100">
                                    <?= htmlspecialchars($studentScoreLastUpdatedLabel !== null ? $studentScoreLastUpdatedLabel : 'Belum ada', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Lihat per mapel untuk narasi terbaru.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-200">Rata-rata Nilai</p>
                                <p class="mt-2 text-xl font-semibold text-indigo-900 dark:text-indigo-100">
                                    <?= htmlspecialchars($studentScoreOverallAverageValue !== null ? number_format($studentScoreOverallAverageValue, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-indigo-600 dark:text-indigo-200/80">Gabungan pengetahuan & keterampilan</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Progres Penilaian</p>
                                <p class="mt-2 text-xl font-semibold text-slate-700 dark:text-slate-100">
                                    <?= number_format($studentScoreSubjectsFull) ?> lengkap
                                </p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-300">
                                    <?= number_format(max(0, $studentScoreSubjectsPending)) ?> menunggu penilaian
                                </p>
                            </div>
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500 dark:text-emerald-200">Rata-rata Pengetahuan</p>
                                <p class="mt-2 text-xl font-semibold text-emerald-900 dark:text-emerald-100">
                                    <?= htmlspecialchars($studentScoreKnowledgeAverageValue !== null ? number_format($studentScoreKnowledgeAverageValue, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-200/80">Penilaian teori</p>
                            </div>
                            <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm shadow-sm dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-500 dark:text-sky-200">Rata-rata Keterampilan</p>
                                <p class="mt-2 text-xl font-semibold text-sky-900 dark:text-sky-100">
                                    <?= htmlspecialchars($studentScoreSkillAverageValue !== null ? number_format($studentScoreSkillAverageValue, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="text-[11px] text-sky-600 dark:text-sky-200/80">Penilaian praktik</p>
                            </div>
                        </div>
                        <?php if ($studentScoreLastUpdatedLabel !== null): ?>
                            <p class="mt-4 text-[11px] text-slate-400 dark:text-slate-500">
                                Pembaruan terakhir <?= htmlspecialchars($studentScoreLastUpdatedLabel, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>

    <?php if ($isAdminUser): ?>
        <div class="lg:col-span-5 space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-800">Siswa Terbaru</h2>
                    <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Lihat semua</a>
                </div>
                <ul class="mt-4 space-y-4">
                    <?php foreach ($latestStudents as $student): ?>
                        <li class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">
                                    <?= htmlspecialchars($student['nama'], ENT_QUOTES, 'UTF-8') ?>
                                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                </p>
                                <p class="text-xs text-slate-400">
                                    <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?> ·
                                    <?= htmlspecialchars($student['kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <span class="text-xs font-medium text-indigo-500">
                                <?= htmlspecialchars($student['tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($latestStudents)): ?>
                        <li class="py-6 text-center text-sm text-slate-400">Belum ada data siswa terbaru.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>
