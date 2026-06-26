<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAttendance;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomAttendanceController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/attendance/index', array_merge([
            'title' => 'Input Presensi Siswa',
            'pageTitle' => 'Input Presensi Siswa',
            'activeMenu' => 'homeroom-attendance',
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

        $classes = $activeYear !== null
            ? Classroom::homeroomClassesForTeacher($teacherId, (int) ($activeYear['id'] ?? 0))
            : [];

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

        $students = [];
        $records = [];
        $schoolYearId = $selectedClass !== null ? (int) ($selectedClass['tahun_ajaran_id'] ?? 0) : null;
        $schoolYearFilter = ($schoolYearId !== null && $schoolYearId > 0) ? $schoolYearId : null;

        if ($selectedClass !== null) {
            $students = Student::byClass($selectedClassId, $schoolYearFilter);
            $records = StudentAttendance::byClass($selectedClassId, $schoolYearFilter);
        }

        return $this->renderView([
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'records' => $records,
            'oldSick' => old('sakit', []),
            'oldPermit' => old('izin', []),
            'oldTruant' => old('bolos', []),
            'oldAbsent' => old('alpa', []),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/presensi')) {
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

            return $this->redirect('walikelas/presensi');
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

            return $this->redirect('walikelas/presensi');
        }

        $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada kelas tidak valid.');

            return $this->redirect('walikelas/presensi?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');

            return $this->redirect('walikelas/presensi?kelas_id=' . urlencode((string) $classId));
        }

        $sickInputs = $request->input('sakit', []);
        $permitInputs = $request->input('izin', []);
        $truantInputs = $request->input('bolos', []);
        $absentInputs = $request->input('alpa', []);

        if (!is_array($sickInputs)) {
            $sickInputs = [];
        }
        if (!is_array($permitInputs)) {
            $permitInputs = [];
        }
        if (!is_array($truantInputs)) {
            $truantInputs = [];
        }
        if (!is_array($absentInputs)) {
            $absentInputs = [];
        }

        $payloads = [];
        $errors = [];
        $maxDays = 366;

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $name = $student['nama'] ?? 'siswa';

            $values = [
                'sakit' => $sickInputs[$studentId] ?? 0,
                'izin' => $permitInputs[$studentId] ?? 0,
                'bolos' => $truantInputs[$studentId] ?? 0,
                'alpa' => $absentInputs[$studentId] ?? 0,
            ];

            $normalized = [];

            foreach ($values as $key => $rawValue) {
                if ($rawValue === null || $rawValue === '') {
                    $normalized[$key] = 0;
                    continue;
                }

                if (!is_numeric($rawValue)) {
                    $errors[] = sprintf('Nilai %s untuk %s harus berupa angka.', ucfirst($key), $name);
                    continue;
                }

                $intValue = (int) $rawValue;

                if ($intValue < 0 || $intValue > $maxDays) {
                    $errors[] = sprintf('Nilai %s untuk %s harus di antara 0 hingga %d.', ucfirst($key), $name, $maxDays);
                    continue;
                }

                $normalized[$key] = $intValue;
            }

            if (count($normalized) !== 4) {
                continue;
            }

            $payloads[$studentId] = $normalized;
        }

        if (!empty($errors)) {
            Session::flashInput($request->all());
            Session::flash('error', implode(' ', $errors));

            return $this->redirect('walikelas/presensi?kelas_id=' . urlencode((string) $classId));
        }

        try {
            StudentAttendance::saveMany($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Data presensi berhasil disimpan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan data presensi: ' . $exception->getMessage());
        }

        return $this->redirect('walikelas/presensi?kelas_id=' . urlencode((string) $classId));
    }
}
