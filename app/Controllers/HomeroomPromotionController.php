<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentPromotionStatus;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomPromotionController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/promotions/index', array_merge([
            'title' => 'Status Naik Kelas',
            'pageTitle' => 'Status Naik Kelas',
            'activeMenu' => 'homeroom-promotions',
        ], $context));
    }

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);

        if ($activeSemester !== 2) {
            Session::flash('error', 'Status naik kelas hanya tersedia pada semester genap.');

            return $this->redirect('walikelas/catatan');
        }

        $teacherId = (int) $user['teacher_id'];

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $classes = array_values(array_filter($classes, static function ($class) {
            $level = (int) ($class['tingkat'] ?? 0);

            return in_array($level, [10, 11], true);
        }));

        if (empty($classes)) {
            Session::flash('error', 'Tidak ada kelas tingkat 10 atau 11 yang Anda ampu.');

            return $this->redirect('dashboard');
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            $selectedClass = $classes[0];
            $selectedClassId = (int) ($selectedClass['id'] ?? 0);
        }

        $students = [];
        $records = [];
        $schoolYearId = $selectedClass !== null ? (int) ($selectedClass['tahun_ajaran_id'] ?? 0) : null;
        $schoolYearFilter = ($schoolYearId !== null && $schoolYearId > 0) ? $schoolYearId : null;

        if ($selectedClass !== null) {
            $students = Student::byClass($selectedClassId, $schoolYearFilter);
            $records = StudentPromotionStatus::byClass($selectedClassId, $schoolYearFilter);
        }

        return $this->renderView([
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'records' => $records,
            'oldStatuses' => old('status', []),
            'oldNotes' => old('catatan', []),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/status-naik-kelas')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeSemester = (int) ($activeYear['semester_aktif'] ?? 0);

        if ($activeSemester !== 2) {
            Session::flash('error', 'Status naik kelas hanya dapat disimpan pada semester genap.');

            return $this->redirect('walikelas/catatan');
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-naik-kelas');
        }

        $classes = Classroom::homeroomClassesForTeacher($teacherId);
        $allowedClasses = array_values(array_filter($classes, static function ($class) use ($classId) {
            $level = (int) ($class['tingkat'] ?? 0);

            return in_array($level, [10, 11], true);
        }));

        $selectedClass = null;

        foreach ($allowedClasses as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            Session::flash('error', 'Anda tidak memiliki akses ke kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-naik-kelas');
        }

        $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Tahun ajaran pada kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-naik-kelas?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/status-naik-kelas?kelas_id=' . urlencode((string) $classId));
        }

        $statuses = $request->input('status', []);
        $notes = $request->input('catatan', []);

        if (!is_array($statuses)) {
            $statuses = [];
        }

        if (!is_array($notes)) {
            $notes = [];
        }

        $payloads = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $payloads[$studentId] = [
                'status' => $statuses[$studentId] ?? null,
                'catatan' => $notes[$studentId] ?? null,
            ];
        }

        try {
            StudentPromotionStatus::saveMany($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Status naik kelas berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Terjadi kesalahan saat menyimpan status naik kelas: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('walikelas/status-naik-kelas?kelas_id=' . urlencode((string) $classId));
    }
}
