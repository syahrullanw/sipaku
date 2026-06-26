<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentCardController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $isAdmin = $role === 'admin';
        $isHomeroom = $role === 'guru' && $teacherId > 0;

        if (!$isAdmin && !$isHomeroom) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh admin atau wali kelas.');

            return $this->redirect('dashboard');
        }

        $context = $this->buildSelectionContext($request, $role, $teacherId);

        return $this->render('student-cards/index', array_merge([
            'title' => 'Cetak Kartu Pelajar',
            'pageTitle' => 'Cetak Kartu Pelajar',
            'activeMenu' => 'student-cards',
        ], $context));
    }

    public function print(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth() ?? [];
        $role = (string) ($user['role'] ?? '');
        $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

        $isAdmin = $role === 'admin';
        $isHomeroom = $role === 'guru' && $teacherId > 0;

        if (!$isAdmin && !$isHomeroom) {
            Session::flash('error', 'Anda tidak memiliki akses ke fitur ini.');

            return $this->redirect('dashboard');
        }

        $mode = (string) $request->query('mode', 'single');
        $accessible = $this->resolveAccessibleClasses($role, $teacherId);
        $classes = $accessible['classes'];
        $allowedClassIds = $accessible['ids'];

        $school = SchoolProfile::first();
        $groups = [];
        $title = 'Kartu Pelajar';
        $heading = 'Kartu Pelajar';
        $subheading = '';

        if ($mode === 'all') {
            if (empty($classes)) {
                Session::flash('error', 'Tidak ada kelas yang dapat dicetak.');

                return $this->redirect('kartu-pelajar');
            }

            foreach ($classes as $class) {
                $classId = (int) ($class['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $students = $this->fetchStudentsForClass(
                    $classId,
                    isset($class['tahun_ajaran_id']) ? (int) $class['tahun_ajaran_id'] : null
                );

                if (empty($students)) {
                    continue;
                }

                $groups[] = [
                    'label' => $this->formatClassLabel($class),
                    'cards' => $this->buildCards($students),
                ];
            }

            if (empty($groups)) {
                Session::flash('error', 'Tidak ada siswa pada kelas yang tersedia.');

                return $this->redirect('kartu-pelajar');
            }

            $title = 'Kartu Pelajar - Semua Kelas';
            $subheading = 'Semua kelas yang tersedia';
        } elseif ($mode === 'class') {
            $classId = (int) $request->query('kelas_id', 0);

            if ($classId <= 0 || !in_array($classId, $allowedClassIds, true)) {
                Session::flash('error', 'Kelas tidak ditemukan atau tidak dapat diakses.');

                return $this->redirect('kartu-pelajar');
            }

            $class = $this->findClassById($classes, $classId);

            if ($class === null) {
                Session::flash('error', 'Kelas tidak ditemukan atau tidak dapat diakses.');

                return $this->redirect('kartu-pelajar');
            }

            $students = $this->fetchStudentsForClass(
                $classId,
                isset($class['tahun_ajaran_id']) ? (int) $class['tahun_ajaran_id'] : null
            );

            if (empty($students)) {
                Session::flash('error', 'Belum ada siswa pada kelas terpilih.');

                return $this->redirect('kartu-pelajar?kelas_id=' . urlencode((string) $classId));
            }

            $groups[] = [
                'label' => $this->formatClassLabel($class),
                'cards' => $this->buildCards($students),
            ];

            $title = 'Kartu Pelajar - ' . $this->formatClassLabel($class);
            $subheading = 'Kelas ' . $this->formatClassLabel($class);
        } else {
            $studentId = (int) $request->query('siswa_id', 0);

            if ($studentId <= 0) {
                Session::flash('error', 'Data siswa tidak valid.');

                return $this->redirect('kartu-pelajar');
            }

            $student = Student::findWithRelations($studentId);

            if ($student === null) {
                Session::flash('error', 'Data siswa tidak ditemukan.');

                return $this->redirect('kartu-pelajar');
            }

            $studentClassId = isset($student['kelas_id']) ? (int) $student['kelas_id'] : 0;

            if (!$isAdmin && ($studentClassId <= 0 || !in_array($studentClassId, $allowedClassIds, true))) {
                Session::flash('error', 'Anda tidak memiliki akses ke siswa tersebut.');

                return $this->redirect('kartu-pelajar');
            }

            $groups[] = [
                'label' => null,
                'cards' => [$this->buildCard($student)],
            ];

            $title = 'Kartu Pelajar - ' . ($student['nama'] ?? '-');
            $subheading = $student['nama'] ?? '';
        }

        return $this->render('student-cards/print', [
            'title' => $title,
            'school' => $school,
            'groups' => $groups,
            'printHeading' => $heading,
            'printSubheading' => $subheading,
        ], 'print');
    }

    public function verify(Request $request): Response
    {
        $studentId = (int) $request->query('siswa', 0);
        $code = (string) $request->query('kode', '');

        $student = null;
        $isValid = false;

        if ($studentId > 0 && $code !== '') {
            $student = Student::findWithRelations($studentId);

            if ($student !== null) {
                $expected = $this->generateValidationCode($student);
                $isValid = hash_equals($expected, $code);
            }
        }

        if (strtolower((string) $request->query('format', '')) === 'json') {
            $payload = [
                'status' => $isValid ? 'valid' : 'invalid',
                'student' => $isValid ? [
                    'id' => $student['id'] ?? null,
                    'nama' => $student['nama'] ?? null,
                    'nipd' => $student['nipd'] ?? null,
                    'kelas' => $student['kelas_nama'] ?? null,
                ] : null,
            ];

            return $this->json($payload, $isValid ? 200 : 404);
        }

        $school = SchoolProfile::first();

        return $this->render('student-cards/verify', [
            'title' => 'Verifikasi Kartu Pelajar',
            'student' => $isValid ? $student : null,
            'isValid' => $isValid,
            'code' => $code,
            'school' => $school,
        ], 'app');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSelectionContext(Request $request, string $role, int $teacherId): array
    {
        $accessible = $this->resolveAccessibleClasses($role, $teacherId);
        $classes = $accessible['classes'];
        $classIds = $accessible['ids'];

        $selectedClassId = (int) $request->query('kelas_id', 0);

        if ($selectedClassId > 0 && !in_array($selectedClassId, $classIds, true)) {
            $selectedClassId = 0;
        }

        $selectedClass = null;

        if (!empty($classes)) {
            if ($selectedClassId === 0) {
                $selectedClass = $classes[0];
                $selectedClassId = (int) ($selectedClass['id'] ?? 0);
            } else {
                $selectedClass = $this->findClassById($classes, $selectedClassId);

                if ($selectedClass === null) {
                    $selectedClass = $classes[0];
                    $selectedClassId = (int) ($selectedClass['id'] ?? 0);
                }
            }
        }

        $students = [];
        $selectedStudentId = (int) $request->query('siswa_id', 0);
        $selectedStudent = null;

        if ($selectedClass !== null) {
            $students = $this->fetchStudentsForClass(
                $selectedClassId,
                isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : null
            );

            if (!empty($students)) {
                $studentIds = array_values(array_filter(array_map(static function ($row) {
                    return isset($row['id']) ? (int) $row['id'] : 0;
                }, $students), static fn (int $id) => $id > 0));

                if (!in_array($selectedStudentId, $studentIds, true)) {
                    $selectedStudentId = $studentIds[0] ?? 0;
                }

                if ($selectedStudentId > 0) {
                    $selectedStudent = Student::findWithRelations($selectedStudentId);
                }
            } else {
                $selectedStudentId = 0;
            }
        } else {
            $selectedClassId = 0;
            $selectedStudentId = 0;
        }

        $validationCode = $selectedStudent !== null ? $this->generateValidationCode($selectedStudent) : null;
        $validationUrl = null;

        if ($selectedStudent !== null && $validationCode !== null) {
            $validationUrl = $this->buildValidationUrl((int) $selectedStudent['id'], $validationCode);
        }

        $singlePrintUrl = null;
        $classPrintUrl = null;
        $allPrintUrl = null;

        if ($selectedStudentId > 0) {
            $singlePrintUrl = base_url('kartu-pelajar/cetak?' . http_build_query([
                'kelas_id' => $selectedClassId,
                'siswa_id' => $selectedStudentId,
            ]));
        }

        if ($selectedClassId > 0) {
            $classPrintUrl = base_url('kartu-pelajar/cetak?' . http_build_query([
                'mode' => 'class',
                'kelas_id' => $selectedClassId,
            ]));
        }

        if (!empty($classes)) {
            $allPrintUrl = base_url('kartu-pelajar/cetak?' . http_build_query([
                'mode' => 'all',
            ]));
        }

        return [
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassId' => $selectedClassId,
            'students' => $students,
            'selectedStudentId' => $selectedStudentId,
            'selectedStudent' => $selectedStudent,
            'validationCode' => $validationCode,
            'validationUrl' => $validationUrl,
            'qrValue' => $validationUrl,
            'singlePrintUrl' => $singlePrintUrl,
            'classPrintUrl' => $classPrintUrl,
            'allPrintUrl' => $allPrintUrl,
        ];
    }

    /**
     * @return array{classes: array<int, array<string, mixed>>, ids: array<int>}
     */
    private function resolveAccessibleClasses(string $role, int $teacherId): array
    {
        if ($role === 'admin') {
            $classes = Classroom::allWithRelations();
        } elseif ($role === 'guru' && $teacherId > 0) {
            $classes = $this->resolveHomeroomClasses($teacherId);
        } else {
            $classes = [];
        }

        $ids = array_values(array_filter(array_map(static function ($class) {
            return isset($class['id']) ? (int) $class['id'] : 0;
        }, $classes), static fn (int $id) => $id > 0));

        return [
            'classes' => $classes,
            'ids' => $ids,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveHomeroomClasses(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $classes = [];
        $activeYear = SchoolYear::active();

        if ($activeYear !== null) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0));
        }

        if (empty($classes)) {
            $classes = Classroom::homeroomClassesForTeacher($teacherId);
        }

        return $classes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchStudentsForClass(int $classId, ?int $schoolYearId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $filterYear = ($schoolYearId ?? 0) > 0 ? $schoolYearId : null;

        $students = Student::byClass($classId, $filterYear);

        if (empty($students) && $filterYear !== null) {
            $students = Student::byClass($classId, null);
        }

        return $students;
    }

    /**
     * @param array<int, array<string, mixed>> $students
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCards(array $students): array
    {
        $cards = [];

        foreach ($students as $student) {
            $cards[] = $this->buildCard($student);
        }

        return $cards;
    }

    /**
     * @param array<string, mixed> $student
     *
     * @return array<string, mixed>
     */
    private function buildCard(array $student): array
    {
        $studentId = isset($student['id']) ? (int) $student['id'] : 0;
        $validationCode = $this->generateValidationCode($student);
        $validationUrl = $studentId > 0 ? $this->buildValidationUrl($studentId, $validationCode) : '';

        return [
            'student' => $student,
            'validationCode' => $validationCode,
            'validationUrl' => $validationUrl,
            'qrValue' => $validationUrl,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     */
    private function findClassById(array $classes, int $classId): ?array
    {
        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                return $class;
            }
        }

        return null;
    }

    private function formatClassLabel(?array $class): string
    {
        if ($class === null) {
            return '-';
        }

        $level = trim((string) ($class['tingkat'] ?? ''));
        $name = trim((string) ($class['nama'] ?? ''));
        $year = trim((string) ($class['tahun_ajaran_nama'] ?? ''));

        $label = trim(($level !== '' ? $level . ' ' : '') . $name);

        if ($label === '') {
            $label = $name !== '' ? $name : '-';
        }

        if ($year !== '') {
            $label .= ' (' . $year . ')';
        }

        return trim($label);
    }

    private function generateValidationCode(array $student): string
    {
        $id = isset($student['id']) ? (int) $student['id'] : 0;
        $nipd = trim((string) ($student['nipd'] ?? ''));
        $birthDate = trim((string) ($student['tanggal_lahir'] ?? ''));
        $secret = config('app.url', 'siakad-smk');

        $payload = $id . '|' . $nipd . '|' . $birthDate . '|' . $secret;

        return hash('sha256', $payload);
    }

    private function buildValidationUrl(int $studentId, string $code): string
    {
        $path = 'kartu-pelajar/verifikasi?siswa=' . urlencode((string) $studentId) . '&kode=' . urlencode($code);

        return absolute_url($path);
    }
}
