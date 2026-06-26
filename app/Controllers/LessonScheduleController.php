<?php

namespace App\Controllers;

use App\Models\LessonSchedule;
use App\Models\SchoolYear;
use App\Models\SubjectTeacher;
use App\Services\PeriodicDataCopyService;
use App\Support\AcademicRoleGate;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class LessonScheduleController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $yearOptions = SchoolYear::options();
        $allYears = SchoolYear::allOrdered();
        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $editId = (int) $request->query('edit', 0);
        $editingSchedule = null;

        if ($editId > 0) {
            $editingSchedule = LessonSchedule::findWithRelations($editId);
            if ($editingSchedule !== null) {
                $selectedYearId = (int) ($editingSchedule['tahun_ajaran_id'] ?? 0);
            }
        }

        if ($selectedYearId === 0 && !empty($allYears)) {
            foreach ($allYears as $year) {
                if (($year['status'] ?? 'nonaktif') === 'aktif') {
                    $selectedYearId = (int) $year['id'];
                    break;
                }
            }

            if ($selectedYearId === 0) {
                $selectedYearId = (int) ($allYears[0]['id'] ?? 0);
            }
        }

        $schedules = LessonSchedule::listWithRelations($selectedYearId > 0 ? $selectedYearId : null);
        $assignments = SubjectTeacher::allWithRelations($selectedYearId > 0 ? $selectedYearId : null);
        $copyScheduleSourceYear = null;
        $copyScheduleSourceCount = 0;

        if ($selectedYearId > 0) {
            $copyService = new PeriodicDataCopyService();

            foreach ($allYears as $year) {
                $candidateId = (int) ($year['id'] ?? 0);
                if ($candidateId <= 0 || $candidateId === $selectedYearId) {
                    continue;
                }

                $candidateCounts = $copyService->countForYear($candidateId);
                $candidateScheduleCount = (int) ($candidateCounts['lesson_schedules'] ?? 0);
                if ($candidateScheduleCount <= 0) {
                    continue;
                }

                $copyScheduleSourceYear = $year;
                $copyScheduleSourceCount = $candidateScheduleCount;
                break;
            }
        }

        $assignmentOptions = [];
        $assignmentClassMap = [];

        foreach ($assignments as $assignment) {
            $assignmentId = (int) ($assignment['id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $subjectCode = (string) ($assignment['mata_pelajaran_kode'] ?? '');
            $subjectName = (string) ($assignment['mata_pelajaran_nama'] ?? '');
            $teacherName = (string) ($assignment['guru_nama'] ?? '');

            $label = trim(sprintf('%s - %s • %s', $subjectCode, $subjectName, $teacherName));

            $assignmentOptions[$assignmentId] = $label;

            $assignmentClasses = $assignment['classes'] ?? [];
            $assignmentClassMap[$assignmentId] = [];

            foreach ($assignmentClasses as $class) {
                $classId = (int) ($class['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $classLabel = sprintf('Kelas %s %s', $class['tingkat'] ?? '-', $class['nama'] ?? '-');
                if (!empty($class['jurusan_nama'])) {
                    $classLabel .= sprintf(' (%s)', $class['jurusan_nama']);
                }

                $assignmentClassMap[$assignmentId][$classId] = $classLabel;
            }
        }

        $dayOptions = LessonSchedule::dayOptions();
        $disableForm = empty($assignmentOptions) || $selectedYearId <= 0;

        return $this->render('academic/lesson-schedules/index', [
            'title' => 'Jadwal Pelajaran',
            'pageTitle' => 'Jadwal Pelajaran',
            'activeMenu' => 'lesson-schedules',
            'schedules' => $schedules,
            'assignmentOptions' => $assignmentOptions,
            'assignmentClassMap' => $assignmentClassMap,
            'dayOptions' => $dayOptions,
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'editingSchedule' => $editingSchedule,
            'disableForm' => $disableForm,
            'copyScheduleSourceYear' => $copyScheduleSourceYear,
            'copyScheduleSourceCount' => $copyScheduleSourceCount,
            'canCopyLessonSchedules' => $selectedYearId > 0 && empty($schedules) && $copyScheduleSourceYear !== null,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal')) {
            return $response;
        }

        $filterYearId = (int) $request->input('filter_tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/jadwal' . ($filterYearId > 0 ? '?tahun_ajaran_id=' . $filterYearId : '');

        $validated = $this->validatePayload($request);

        if ($validated === null) {
            return $this->redirect($redirectUrl);
        }

        $now = date('Y-m-d H:i:s');

        LessonSchedule::create([
            'tahun_ajaran_id' => $validated['tahun_ajaran_id'],
            'guru_mata_pelajaran_id' => $validated['guru_mata_pelajaran_id'],
            'kelas_id' => $validated['kelas_id'],
            'hari' => $validated['hari'],
            'waktu_mulai' => $validated['waktu_mulai'],
            'waktu_selesai' => $validated['waktu_selesai'],
            'jumlah_jam' => $validated['jumlah_jam'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Session::flash('success', 'Jadwal pelajaran berhasil ditambahkan.');

        return $this->redirect($redirectUrl);
    }

    public function copyFromSource(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal')) {
            return $response;
        }

        $targetYearId = (int) $request->input('tahun_ajaran_id', 0);
        $sourceYearId = (int) $request->input('tahun_ajaran_sumber_id', 0);
        $redirectUrl = 'akademik/jadwal' . ($targetYearId > 0 ? '?tahun_ajaran_id=' . $targetYearId : '');

        if ($targetYearId <= 0 || $sourceYearId <= 0 || $targetYearId === $sourceYearId) {
            Session::flash('error', 'Tahun ajaran sumber atau tujuan tidak valid.');
            return $this->redirect($redirectUrl);
        }

        if (SchoolYear::find($targetYearId) === null || SchoolYear::find($sourceYearId) === null) {
            Session::flash('error', 'Data tahun ajaran tidak ditemukan.');
            return $this->redirect($redirectUrl);
        }

        $service = new PeriodicDataCopyService();
        $targetCounts = $service->countForYear($targetYearId);

        if ((int) ($targetCounts['lesson_schedules'] ?? 0) > 0) {
            Session::flash('error', 'Jadwal pelajaran sudah tersedia pada tahun ajaran ini. Tombol salin dinonaktifkan.');
            return $this->redirect($redirectUrl);
        }

        try {
            $report = $service->copyLessonSchedulesOnly($sourceYearId, $targetYearId);
            $copied = (int) ($report['copied'] ?? 0);
            $skipped = (int) ($report['skipped'] ?? 0);

            if ($copied > 0) {
                Session::flash('success', sprintf('Jadwal pelajaran berhasil disalin: %d entri disalin, %d entri dilewati.', $copied, $skipped));
            } else {
                Session::flash('error', 'Tidak ada jadwal yang berhasil disalin. Pastikan kelas, mata pelajaran, dan guru pengampu tahun tujuan sudah tersedia.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyalin jadwal pelajaran: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $schedule = LessonSchedule::find($id);

        $filterYearId = (int) $request->input('filter_tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/jadwal' . ($filterYearId > 0 ? '?tahun_ajaran_id=' . $filterYearId : '');

        if ($schedule === null) {
            Session::flash('error', 'Jadwal pelajaran tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->guardCsrf($request, 'akademik/jadwal')) {
            return $response;
        }

        $validated = $this->validatePayload($request, $id);

        if ($validated === null) {
            return $this->redirect($redirectUrl);
        }

        LessonSchedule::updateById($id, [
            'tahun_ajaran_id' => $validated['tahun_ajaran_id'],
            'guru_mata_pelajaran_id' => $validated['guru_mata_pelajaran_id'],
            'kelas_id' => $validated['kelas_id'],
            'hari' => $validated['hari'],
            'waktu_mulai' => $validated['waktu_mulai'],
            'waktu_selesai' => $validated['waktu_selesai'],
            'jumlah_jam' => $validated['jumlah_jam'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Jadwal pelajaran berhasil diperbarui.');

        return $this->redirect($redirectUrl);
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $filterYearId = (int) $request->input('filter_tahun_ajaran_id', 0);
        $redirectUrl = 'akademik/jadwal' . ($filterYearId > 0 ? '?tahun_ajaran_id=' . $filterYearId : '');

        if ($response = $this->guardCsrf($request, 'akademik/jadwal')) {
            return $response;
        }

        $schedule = LessonSchedule::find($id);

        if ($schedule === null) {
            Session::flash('error', 'Jadwal pelajaran tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        LessonSchedule::deleteById($id);

        Session::flash('success', 'Jadwal pelajaran berhasil dihapus.');

        return $this->redirect($redirectUrl);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatePayload(Request $request, ?int $currentId = null): ?array
    {
        $assignmentId = (int) $request->input('guru_mata_pelajaran_id', 0);
        $classId = (int) $request->input('kelas_id', 0);
        $day = (string) $request->input('hari', '');
        $startInput = (string) $request->input('waktu_mulai', '');
        $endInput = (string) $request->input('waktu_selesai', '');
        $hours = (int) $request->input('jumlah_jam', 0);

        if ($assignmentId <= 0 || $classId <= 0 || $day === '' || $startInput === '' || $endInput === '' || $hours <= 0) {
            Session::flash('error', 'Semua kolom wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        $assignment = SubjectTeacher::findWithRelations($assignmentId);

        if ($assignment === null) {
            Session::flash('error', 'Guru pengampu tidak ditemukan.');
            Session::flashInput($request->all());

            return null;
        }

        $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);

        if ($yearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada guru pengampu tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $assignedClasses = $assignment['classes'] ?? [];
        $allowedClassIds = array_map(
            static fn ($class) => (int) ($class['id'] ?? 0),
            $assignedClasses
        );

        if (!in_array($classId, $allowedClassIds, true)) {
            Session::flash('error', 'Kelas yang dipilih tidak sesuai dengan guru pengampu.');
            Session::flashInput($request->all());

            return null;
        }

        $dayOptions = LessonSchedule::dayOptions();
        if (!array_key_exists($day, $dayOptions)) {
            Session::flash('error', 'Hari jadwal tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        $startTime = \DateTimeImmutable::createFromFormat('H:i', $startInput) ?: \DateTimeImmutable::createFromFormat('H:i:s', $startInput);
        $endTime = \DateTimeImmutable::createFromFormat('H:i', $endInput) ?: \DateTimeImmutable::createFromFormat('H:i:s', $endInput);

        if ($startTime === false || $endTime === false) {
            Session::flash('error', 'Format waktu tidak valid. Gunakan format HH:MM.');
            Session::flashInput($request->all());

            return null;
        }

        if ($endTime <= $startTime) {
            Session::flash('error', 'Waktu selesai harus lebih besar dari waktu mulai.');
            Session::flashInput($request->all());

            return null;
        }

        if ($hours > 10) {
            Session::flash('error', 'Jumlah jam pelajaran tidak boleh lebih dari 10 jam.');
            Session::flashInput($request->all());

            return null;
        }

        $data = [
            'tahun_ajaran_id' => $yearId,
            'guru_mata_pelajaran_id' => $assignmentId,
            'kelas_id' => $classId,
            'hari' => $day,
            'waktu_mulai' => $startTime->format('H:i:s'),
            'waktu_selesai' => $endTime->format('H:i:s'),
            'jumlah_jam' => $hours,
        ];

        return $data;
    }

    private function ensureTataUsahaAccess(): ?Response
    {
        $user = auth();

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (
            AcademicRoleGate::isTataUsaha($user)
            || AcademicRoleGate::isWakaKurikulum($user)
        ) {
            return null;
        }

        return $this->ensureRole(['admin', 'staff']);
    }
}
