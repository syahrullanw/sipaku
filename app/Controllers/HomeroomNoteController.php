<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\HomeroomNote;
use App\Models\SchoolYear;
use App\Models\Student;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class HomeroomNoteController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/notes/index', array_merge([
            'title' => 'Catatan Wali Kelas',
            'pageTitle' => 'Catatan Wali Kelas',
            'activeMenu' => 'homeroom-notes',
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

        if (empty($classes)) {
            Session::flash('error', 'Anda belum terdaftar sebagai wali kelas pada kelas manapun.');

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
            $records = HomeroomNote::byClass($selectedClassId, $schoolYearFilter);
        }

        return $this->renderView([
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'records' => $records,
            'oldNotes' => old('catatan', []),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/catatan')) {
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
            Session::flashInput($request->all());

            return $this->redirect('walikelas/catatan');
        }

        $classes = Classroom::homeroomClassesForTeacher($teacherId);

        $selectedClass = null;

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $selectedClass = $class;
                break;
            }
        }

        if ($selectedClass === null) {
            Session::flash('error', 'Anda tidak memiliki akses ke kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/catatan');
        }

        $schoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($schoolYearId <= 0) {
            Session::flash('error', 'Tahun ajaran pada kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/catatan?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $schoolYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas terpilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/catatan?kelas_id=' . urlencode((string) $classId));
        }

        $notes = $request->input('catatan', []);

        if (!is_array($notes)) {
            $notes = [];
        }

        $payloads = [];

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $payloads[$studentId] = $notes[$studentId] ?? '';
        }

        try {
            HomeroomNote::saveMany($classId, $schoolYearId, $teacherId, $payloads);
            Session::flash('success', 'Catatan wali kelas berhasil disimpan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Terjadi kesalahan saat menyimpan catatan wali kelas: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('walikelas/catatan?kelas_id=' . urlencode((string) $classId));
    }
}
