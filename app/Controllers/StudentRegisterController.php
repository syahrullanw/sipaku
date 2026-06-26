<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\StudentAcademicHistoryService;
use App\Services\StudentRegisterPdfExporter;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentRegisterController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $isAdmin = $role === 'admin';
        $isHomeroom = $role === 'guru' && $teacherId > 0 && Classroom::teacherHasHomeroom($teacherId);

        if (!$isAdmin && !$isHomeroom) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh admin atau wali kelas.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();

        $classRecords = $isAdmin
            ? Classroom::allWithRelations()
            : Classroom::homeroomClassesForTeacher($teacherId, isset($activeYear['id']) ? (int) $activeYear['id'] : null);

        if (!$isAdmin && empty($classRecords)) {
            $classRecords = Classroom::homeroomClassesForTeacher($teacherId);
        }

        if (!$isAdmin && empty($classRecords)) {
            Session::flash('error', 'Anda tidak memiliki kelas wali aktif.');

            return $this->redirect('dashboard');
        }

        $classOptions = [];
        $classMap = [];

        foreach ($classRecords as $class) {
            $classId = (int) ($class['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $labelParts = [];
            $level = trim((string) ($class['tingkat'] ?? ''));
            $name = trim((string) ($class['nama'] ?? ''));
            $yearName = trim((string) ($class['tahun_ajaran_nama'] ?? ''));
            $majorName = trim((string) ($class['jurusan_nama'] ?? ''));

            if ($level !== '') {
                $labelParts[] = $level;
            }
            if ($name !== '') {
                $labelParts[] = $name;
            }
            if ($majorName !== '') {
                $labelParts[] = '(' . $majorName . ')';
            }

            $label = trim(implode(' ', $labelParts));
            if ($label === '') {
                $label = 'Kelas #' . $classId;
            }

            if ($yearName !== '') {
                $label .= sprintf(' · %s', $yearName);
            }

            $classOptions[$classId] = $label;
            $classMap[$classId] = $class;
        }

        $classIds = array_keys($classOptions);
        sort($classIds);

        $selectedClassId = (int) $request->query('kelas_id', 0);
        if (!$isAdmin && $selectedClassId > 0 && !in_array($selectedClassId, $classIds, true)) {
            $selectedClassId = 0;
        }

        $statusFilter = (string) $request->query('status', 'aktif');
        if ($statusFilter === '') {
            $statusFilter = 'aktif';
        }

        $keyword = trim((string) $request->query('q', ''));
        $selectedStudentId = (int) $request->query('siswa_id', 0);

        $classFilter = null;
        if ($isAdmin) {
            if ($selectedClassId > 0) {
                $classFilter = [$selectedClassId];
            }
        } else {
            $classFilter = $selectedClassId > 0 ? [$selectedClassId] : $classIds;
        }

        $statusParam = $statusFilter === 'all' ? null : $statusFilter;

        $students = Student::allWithRelations($classFilter, $statusParam, $keyword === '' ? null : $keyword);

        $selectedStudent = null;
        if ($selectedStudentId > 0) {
            foreach ($students as $record) {
                if ((int) ($record['id'] ?? 0) === $selectedStudentId) {
                    $selectedStudent = $record;
                    break;
                }
            }
        }
        if ($selectedStudent === null && !empty($students) && $selectedStudentId > 0) {
            $selectedStudentId = 0;
        }

        $selectedClass = $selectedClassId > 0 && isset($classMap[$selectedClassId])
            ? $classMap[$selectedClassId]
            : null;

        $statusOptions = [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
            'all' => 'Semua Status',
        ];

        $defaultClassLabel = $isAdmin ? 'Semua Kelas' : 'Semua Kelas Saya';

        $academicHistory = [
            'promotions' => [],
            'graduations' => [],
            'achievements' => [],
            'extracurriculars' => [],
            'attendance' => [],
            'attitudes' => [],
            'notes' => [],
            'prakerin' => [],
            'subjects' => [],
        ];
        if ($selectedStudent !== null) {
            $academicHistory = StudentAcademicHistoryService::collect([$selectedStudent]);
        }

        return $this->render('student-register/index', [
            'title' => 'Buku Induk Siswa',
            'pageTitle' => 'Buku Induk Siswa',
            'activeMenu' => $isAdmin ? 'student-register' : 'homeroom-student-register',
            'students' => $students,
            'classOptions' => $classOptions,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'statusFilter' => $statusFilter,
            'statusOptions' => $statusOptions,
            'keyword' => $keyword,
            'isAdmin' => $isAdmin,
            'defaultClassOptionLabel' => $defaultClassLabel,
            'totalStudents' => count($students),
            'classCount' => count($classIds),
            'selectedStudent' => $selectedStudent,
            'selectedStudentId' => $selectedStudentId,
            'academicHistory' => $academicHistory,
        ]);
    }

    public function print(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;
        $isAdmin = $role === 'admin';
        $isHomeroom = $role === 'guru' && $teacherId > 0 && Classroom::teacherHasHomeroom($teacherId);

        if (!$isAdmin && !$isHomeroom) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh admin atau wali kelas.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $request->query('siswa_id', 0);

        if ($studentId <= 0) {
            Session::flash('error', 'Siswa tidak ditemukan.');

            return $this->redirect('buku-induk');
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak tersedia.');

            return $this->redirect('buku-induk');
        }

        if (!$isAdmin) {
            $classId = isset($student['kelas_id']) ? (int) $student['kelas_id'] : 0;

            if ($classId <= 0) {
                Session::flash('error', 'Siswa tidak terdaftar pada kelas manapun.');

                return $this->redirect('buku-induk');
            }

            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);

            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId > 0 ? $activeYearId : null);

            if (empty($classes)) {
                $classes = Classroom::homeroomClassesForTeacher($teacherId);
            }

            $allowedClassIds = array_values(array_filter(array_map(static function ($class) {
                return (int) ($class['id'] ?? 0);
            }, $classes), static fn (int $id) => $id > 0));

            if (!in_array($classId, $allowedClassIds, true)) {
                Session::flash('error', 'Anda tidak memiliki akses ke data siswa ini.');

                return $this->redirect('buku-induk');
            }
        }

        $history = StudentAcademicHistoryService::collect([$student]);

        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        $autoPrint = $request->query('download', '') !== '';

        $schoolProfile = \App\Models\SchoolProfile::first();

        return $this->render('student-register/print', [
            'title' => 'Cetak Buku Induk - ' . ($student['nama'] ?? 'Siswa'),
            'student' => $student,
            'academicHistory' => $history,
            'paperSize' => $paperSize,
            'autoPrint' => $autoPrint,
            'schoolProfile' => $schoolProfile,
        ], 'print');
    }

    public function export(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;
        $isAdmin = $role === 'admin';
        $isHomeroom = $role === 'guru' && $teacherId > 0 && Classroom::teacherHasHomeroom($teacherId);

        if (!$isAdmin && !$isHomeroom) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh admin atau wali kelas.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $request->query('siswa_id', 0);
        if ($studentId <= 0) {
            Session::flash('error', 'Siswa tidak ditemukan.');

            return $this->redirect('buku-induk');
        }

        $student = Student::findWithRelations($studentId);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak tersedia.');

            return $this->redirect('buku-induk');
        }

        if (!$isAdmin) {
            $classId = isset($student['kelas_id']) ? (int) $student['kelas_id'] : 0;
            if ($classId <= 0) {
                Session::flash('error', 'Siswa tidak terdaftar pada kelas manapun.');

                return $this->redirect('buku-induk');
            }

            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);
            $classes = Classroom::homeroomClassesForTeacher($teacherId, $activeYearId > 0 ? $activeYearId : null);
            if (empty($classes)) {
                $classes = Classroom::homeroomClassesForTeacher($teacherId);
            }

            $allowedClassIds = array_values(array_filter(
                array_map(static fn (array $class): int => (int) ($class['id'] ?? 0), $classes),
                static fn (int $id): bool => $id > 0
            ));

            if (!in_array($classId, $allowedClassIds, true)) {
                Session::flash('error', 'Anda tidak memiliki akses ke data siswa ini.');

                return $this->redirect('buku-induk');
            }
        }

        $history = StudentAcademicHistoryService::collect([$student]);
        $schoolProfile = \App\Models\SchoolProfile::first();
        $binary = StudentRegisterPdfExporter::make($student, $history, $schoolProfile);

        $safeName = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) ($student['nama'] ?? 'siswa')) ?: 'siswa');
        $filename = sprintf('buku-induk-%s-%s.pdf', trim($safeName, '-'), date('YmdHis'));

        return Response::make(
            $binary,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($binary),
            ]
        );
    }
}
