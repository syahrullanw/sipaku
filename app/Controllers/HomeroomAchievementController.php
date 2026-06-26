<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAchievement;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Throwable;

class HomeroomAchievementController extends Controller
{
    protected ?string $layout = 'admin';

    /**
     * @param array<string, mixed> $context
     */
    protected function renderView(array $context = []): Response
    {
        return $this->render('homeroom/achievements/index', array_merge([
            'title' => 'Prestasi Siswa',
            'pageTitle' => 'Prestasi Siswa',
            'activeMenu' => 'homeroom-achievements',
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

        $students = [];
        $achievements = [];
        $isActiveMismatch = false;
        $classSchoolYearId = null;

        if ($selectedClass !== null) {
            $classSchoolYearId = isset($selectedClass['tahun_ajaran_id']) ? (int) $selectedClass['tahun_ajaran_id'] : null;

            if ($activeYearId !== null && $classSchoolYearId !== $activeYearId) {
                $isActiveMismatch = true;
            }

            $students = Student::byClass($selectedClassId, $classSchoolYearId);
            $achievements = StudentAchievement::byClass($selectedClassId, $classSchoolYearId);
        }

        return $this->renderView([
            'activeYear' => $activeYear,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'achievements' => $achievements,
            'isActiveMismatch' => $isActiveMismatch,
            'oldStudentId' => old('siswa_id', 0),
            'oldType' => old('jenis', ''),
            'oldNotes' => old('keterangan', ''),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/prestasi')) {
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

            return $this->redirect('walikelas/prestasi');
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada semester aktif. Hubungi admin untuk mengatur tahun ajaran.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYearId <= 0) {
            Session::flash('error', 'Data semester aktif tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi');
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
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi');
        }

        $classSchoolYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? 0);

        if ($classSchoolYearId !== $activeYearId) {
            Session::flash('error', 'Pencatatan prestasi hanya dapat dilakukan pada semester aktif.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        if ($classSchoolYearId <= 0) {
            Session::flash('error', 'Data tahun ajaran pada kelas tidak valid.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        $studentId = (int) $request->input('siswa_id', 0);
        $type = trim((string) $request->input('jenis', ''));
        $notes = trim((string) $request->input('keterangan', ''));

        if ($studentId <= 0) {
            Session::flash('error', 'Siswa harus dipilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        $student = Student::find($studentId);

        if ($student === null || (int) ($student['kelas_id'] ?? 0) !== $classId || (int) ($student['tahun_ajaran_id'] ?? 0) !== $classSchoolYearId) {
            Session::flash('error', 'Data siswa tidak sesuai dengan kelas yang dipilih.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        if (!Student::hasActiveStatus($student)) {
            Session::flash('error', 'Prestasi tidak dapat ditambahkan karena status siswa nonaktif.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        if ($type === '') {
            Session::flash('error', 'Jenis prestasi wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
        }

        $payload = [
            'tahun_ajaran_id' => $classSchoolYearId,
            'kelas_id' => $classId,
            'siswa_id' => $studentId,
            'guru_id' => $teacherId,
            'jenis' => $type,
            'keterangan' => $notes === '' ? null : $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            StudentAchievement::create($payload);
            Session::flash('success', 'Prestasi siswa berhasil ditambahkan.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan prestasi siswa: ' . $exception->getMessage());
            Session::flashInput($request->all());
        }

        return $this->redirect('walikelas/prestasi?kelas_id=' . urlencode((string) $classId));
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/prestasi')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];

        $achievement = StudentAchievement::find($id);

        if ($achievement === null) {
            Session::flash('error', 'Data prestasi tidak ditemukan.');

            return $this->redirect('walikelas/prestasi');
        }

        $classId = (int) ($achievement['kelas_id'] ?? 0);
        $schoolYearId = (int) ($achievement['tahun_ajaran_id'] ?? 0);

        $availableClasses = Classroom::homeroomClassesForTeacher($teacherId);

        $hasAccess = false;
        foreach ($availableClasses as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                $hasAccess = true;
                break;
            }
        }

        $redirectUrl = 'walikelas/prestasi' . ($classId > 0 ? '?kelas_id=' . urlencode((string) $classId) : '');

        if (!$hasAccess) {
            Session::flash('error', 'Anda tidak berhak menghapus data prestasi ini.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        if ($activeYearId !== null && $activeYearId > 0 && $schoolYearId !== $activeYearId) {
            Session::flash('error', 'Penghapusan prestasi hanya diperbolehkan pada semester aktif.');

            return $this->redirect($redirectUrl);
        }

        try {
            StudentAchievement::deleteById($id);
            Session::flash('success', 'Prestasi siswa berhasil dihapus.');
        } catch (Throwable $exception) {
            Session::flash('error', 'Gagal menghapus prestasi siswa: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }
}
