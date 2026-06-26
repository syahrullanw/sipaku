<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\Extracurricular;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentExtracurricular;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomExtracurricularController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/extracurriculars/index', array_merge([
            'title' => 'Ekskul Siswa',
            'pageTitle' => 'Pengelolaan Ekskul Siswa',
            'activeMenu' => 'homeroom-extracurriculars',
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

        $teacherId = (int) $user['teacher_id'];

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $classes = [];

        if ($activeYearId !== null && $activeYearId > 0) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId);
        }

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        $selectedClassId = (int) $request->query('kelas_id', 0);
        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $selectedClassId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null && !empty($classes)) {
            $selectedClass = $classes[0];
            $selectedClassId = (int) ($selectedClass['id'] ?? 0);
        }

        $activities = Extracurricular::allOrdered($activeYearId);
        $activityOptions = Extracurricular::options($activeYearId);

        $students = [];
        $assignments = [];
        $unassignedStudents = [];
        $isActiveMismatch = false;
        $classSchoolYearId = null;
        $scoreDetails = [];

        if ($selectedClass !== null) {
            $classSchoolYearId = isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : null;

            if ($activeYearId !== null && $classSchoolYearId !== $activeYearId) {
                $isActiveMismatch = true;
            }

            $students = Student::byClass($selectedClassId, $classSchoolYearId);

            if ($classSchoolYearId !== null && $classSchoolYearId > 0) {
                $assignments = StudentExtracurricular::byClass($selectedClassId, $classSchoolYearId);
                $scoreDetails = StudentExtracurricular::detailedByClass($selectedClassId, $classSchoolYearId);
            }

            foreach ($students as $student) {
                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $studentAssignments = $assignments[$studentId] ?? [];

                if (empty($studentAssignments)) {
                    $unassignedStudents[] = $student;
                }
            }
        }

        return $this->renderView([
            'activeYear' => $activeYear,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'activities' => $activities,
            'activityOptions' => $activityOptions,
            'students' => $students,
            'assignments' => $assignments,
            'oldAssignments' => old('assignments', []),
            'unassignedStudents' => $unassignedStudents,
            'isActiveMismatch' => $isActiveMismatch,
            'scoreDetails' => $scoreDetails,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/ekskul')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect('walikelas/ekskul');
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada semester aktif. Hubungi admin untuk mengatur tahun ajaran.');

            return $this->redirect('walikelas/ekskul');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');

            return $this->redirect('walikelas/ekskul');
        }

        $availableClasses = Classroom::homeroomClassesForTeacher($teacherId);

        $selectedClass = null;
        foreach ($availableClasses as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            Session::flash('error', 'Anda tidak terdaftar sebagai wali kelas untuk kelas terpilih.');

            return $this->redirect('walikelas/ekskul');
        }

        $schoolYearId = isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : 0;

        if ($schoolYearId !== $activeYearId) {
            Session::flash('error', 'Pengelolaan ekskul hanya dapat dilakukan pada semester aktif.');

            return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
        }

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada kelas tidak valid.');

            return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');

            return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
        }

        $activityOptions = Extracurricular::options($activeYearId);

        if (empty($activityOptions)) {
            Session::flash('error', 'Belum ada data ekstrakurikuler pada semester aktif. Hubungi admin untuk menambahkan daftar ekskul.');

            return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
        }

        $assignmentInputs = $request->input('assignments', []);

        if (!is_array($assignmentInputs)) {
            $assignmentInputs = [];
        }

        $payloads = [];
        $errors = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $rawAssignments = $assignmentInputs[$studentId] ?? [];

            if (!is_array($rawAssignments)) {
                $rawAssignments = $rawAssignments !== null ? [$rawAssignments] : [];
            }

            $activityIds = array_values(array_unique(array_filter(
                array_map(static fn ($value): int => (int) $value, $rawAssignments),
                static fn (int $id): bool => $id > 0 && array_key_exists($id, $activityOptions)
            )));

            if (empty($activityIds)) {
                $errors[] = sprintf('Pilih minimal satu ekskul untuk %s.', $student['nama'] ?? 'siswa');
                continue;
            }

            $payloads[$studentId] = $activityIds;
        }

        if (count($payloads) !== count($students)) {
            Session::flashInput($request->all());

            if (empty($errors)) {
                $errors[] = 'Semua siswa wajib memiliki minimal satu ekskul pada semester aktif.';
            }

            Session::flash('error', implode(' ', $errors));

            return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
        }

        try {
            StudentExtracurricular::saveAssignments($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Daftar ekskul siswa berhasil disimpan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan data ekskul siswa: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('walikelas/ekskul?kelas_id=' . urlencode((string) $classId));
    }
}
