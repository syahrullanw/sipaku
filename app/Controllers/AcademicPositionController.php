<?php

namespace App\Controllers;

use App\Models\AcademicPosition;
use App\Models\Major;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentAcademicPosition;
use App\Models\Teacher;
use App\Models\TeacherAcademicPosition;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class AcademicPositionController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        AcademicPosition::ensureSystemPositions();

        $positions = AcademicPosition::allOrdered();
        $yearOptions = SchoolYear::options();
        $allYears = SchoolYear::allOrdered();
        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);

        if ($selectedYearId <= 0) {
            $activeYear = SchoolYear::active();
            if ($activeYear !== null) {
                $selectedYearId = (int) ($activeYear['id'] ?? 0);
            }
        }

        if ($selectedYearId <= 0 && !empty($allYears)) {
            $selectedYearId = (int) ($allYears[0]['id'] ?? 0);
        }

        if ($selectedYearId > 0 && !isset($yearOptions[$selectedYearId])) {
            $selectedYearId = (int) array_key_first($yearOptions) ?: 0;
        }

        $selectedYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;
        $teacherOptions = Teacher::options(false);
        $studentOptions = Student::options(null, $selectedYearId > 0 ? $selectedYearId : null);
        $majorOptions = Major::options();
        $teacherAssignments = $selectedYearId > 0 ? TeacherAcademicPosition::byYearGroupedByPosition($selectedYearId) : [];
        $studentAssignments = $selectedYearId > 0 ? StudentAcademicPosition::byYearGroupedByPosition($selectedYearId) : [];

        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? AcademicPosition::find($editId) : null;

        return $this->render('master/academic-positions/index', [
            'title' => 'Jabatan Akademik',
            'pageTitle' => 'Master Jabatan Akademik',
            'activeMenu' => 'academic-positions',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'selectedYear' => $selectedYear,
            'teacherOptions' => $teacherOptions,
            'studentOptions' => $studentOptions,
            'majorOptions' => $majorOptions,
            'teacherAssignments' => $teacherAssignments,
            'studentAssignments' => $studentAssignments,
            'positions' => $positions,
            'editingPosition' => $editing,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jabatan-akademik')) {
            return $response;
        }

        AcademicPosition::ensureSystemPositions();

        $contextYearId = (int) $request->input('context_year_id', 0);
        $assignedTeacherId = (int) $request->input('guru_id', 0);
        $assignedStudentId = (int) $request->input('siswa_id', 0);
        $assignedMajorId = (int) $request->input('jurusan_id', 0);
        $allInput = $request->all();
        $hasTeacherField = array_key_exists('guru_id', $allInput);
        $hasStudentField = array_key_exists('siswa_id', $allInput);
        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/jabatan-akademik');
        }

        $payload['level'] = AcademicPosition::nextLevel();
        $payload['assigns_user_role'] = null;
        $payload['is_system'] = 0;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $redirectUrl = 'master/jabatan-akademik';
        if ($contextYearId > 0) {
            $redirectUrl .= '?tahun_ajaran_id=' . $contextYearId;
        }

        try {
            $positionId = AcademicPosition::createAndReturnId($payload);

            if ($positionId === null) {
                throw new \RuntimeException('Gagal menyimpan data jabatan.');
            }

            $assignmentSuccess = true;

            if ($contextYearId > 0) {
                if (
                    $payload['kategori'] === 'guru'
                    && $hasTeacherField
                    && $assignedTeacherId > 0
                ) {
                    if ((int) ($payload['requires_major'] ?? 0) === 1) {
                        if ($assignedMajorId > 0 && Major::find($assignedMajorId) !== null) {
                            $assignmentSuccess = TeacherAcademicPosition::replaceAssignment($contextYearId, $positionId, $assignedTeacherId, $assignedMajorId);
                        } else {
                            $assignmentSuccess = false;
                        }
                    } else {
                        $assignmentSuccess = TeacherAcademicPosition::replaceAssignment($contextYearId, $positionId, $assignedTeacherId);
                    }
                } elseif (
                    $payload['kategori'] === 'siswa'
                    && $hasStudentField
                    && $assignedStudentId > 0
                ) {
                    $assignmentSuccess = StudentAcademicPosition::replaceAssignment($contextYearId, $positionId, $assignedStudentId);
                }
            }

            Session::flash('success', 'Jabatan akademik berhasil ditambahkan.');

            if (!$assignmentSuccess) {
                Session::flash('warning', 'Jabatan berhasil disimpan namun penetapan awal tidak dapat dilakukan.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan jabatan akademik: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jabatan-akademik')) {
            return $response;
        }

        AcademicPosition::ensureSystemPositions();

        $contextYearId = (int) $request->input('context_year_id', 0);
        $assignedTeacherId = (int) $request->input('guru_id', 0);
        $assignedStudentId = (int) $request->input('siswa_id', 0);
        $assignedMajorId = (int) $request->input('jurusan_id', 0);
        $allInput = $request->all();
        $hasTeacherField = array_key_exists('guru_id', $allInput);
        $hasStudentField = array_key_exists('siswa_id', $allInput);
        $payload = $this->validate($request, false, $id);

        $redirectUrl = 'master/jabatan-akademik';
        if ($contextYearId > 0) {
            $redirectUrl .= '?tahun_ajaran_id=' . $contextYearId;
        }

        if ($payload === null) {
            return $this->redirect('master/jabatan-akademik?edit=' . urlencode((string) $id));
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        $existing = AcademicPosition::find($id);

        if ($existing === null) {
            Session::flash('error', 'Jabatan akademik tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $existingCategory = (string) ($existing['kategori'] ?? 'guru');
        $categoryChanged = $payload['kategori'] !== $existingCategory;

        if (AcademicPosition::isSystem($id)) {
            $payload['kategori'] = $existingCategory;
            $payload['requires_major'] = (int) ($existing['requires_major'] ?? 0);
            $categoryChanged = false;
        }

        try {
            AcademicPosition::updateById($id, $payload);

            $assignmentHandled = true;

            if ($categoryChanged) {
                TeacherAcademicPosition::clearAllAssignments($id);
                StudentAcademicPosition::clearAllAssignments($id);
            }

            if ($contextYearId > 0) {
                if ($payload['kategori'] === 'guru' && $hasTeacherField) {
                    if ($assignedTeacherId > 0) {
                        if ((int) ($payload['requires_major'] ?? 0) === 1) {
                            if ($assignedMajorId > 0 && Major::find($assignedMajorId) !== null) {
                                $assignmentHandled = TeacherAcademicPosition::replaceAssignment($contextYearId, $id, $assignedTeacherId, $assignedMajorId);
                            } else {
                                $assignmentHandled = false;
                            }
                        } else {
                            $assignmentHandled = TeacherAcademicPosition::replaceAssignment($contextYearId, $id, $assignedTeacherId);
                        }
                    } else {
                        $assignmentHandled = TeacherAcademicPosition::clearAssignments($contextYearId, $id);
                    }
                } elseif ($payload['kategori'] === 'siswa' && $hasStudentField) {
                    if ($assignedStudentId > 0) {
                        $assignmentHandled = StudentAcademicPosition::replaceAssignment($contextYearId, $id, $assignedStudentId);
                    } else {
                        $assignmentHandled = StudentAcademicPosition::clearAssignments($contextYearId, $id);
                    }
                }
            }

            Session::flash('success', 'Jabatan akademik berhasil diperbarui.');

            if ($contextYearId > 0 && !$assignmentHandled) {
                Session::flash('warning', 'Perubahan tersimpan namun penetapan tidak dapat diperbarui.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui jabatan akademik: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    public function assignTeacher(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jabatan-akademik')) {
            return $response;
        }

        $yearId = (int) $request->input('tahun_ajaran_id', 0);
        $teacherId = (int) $request->input('guru_id', 0);
        $studentId = (int) $request->input('siswa_id', 0);
        $majorId = (int) $request->input('jurusan_id', 0);
        $action = (string) $request->input('action', 'save');

        $redirectUrl = 'master/jabatan-akademik';
        if ($yearId > 0) {
            $redirectUrl .= '?tahun_ajaran_id=' . $yearId;
        }

        $position = AcademicPosition::find($id);
        if ($position === null) {
            Session::flash('error', 'Jabatan akademik tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ($yearId <= 0) {
            Session::flash('error', 'Tahun ajaran wajib dipilih sebelum menetapkan jabatan.');

            return $this->redirect($redirectUrl);
        }

        $category = (string) ($position['kategori'] ?? 'guru');
        $requiresMajor = (int) ($position['requires_major'] ?? 0) === 1;

        if ($category === 'siswa') {
            if ($action === 'clear') {
                if (StudentAcademicPosition::clearAssignments($yearId, $id)) {
                    Session::flash('success', 'Penetapan siswa pada jabatan akademik telah dikosongkan.');
                } else {
                    Session::flash('error', 'Gagal mengosongkan penetapan siswa.');
                }

                return $this->redirect($redirectUrl);
            }

            if ($studentId <= 0) {
                Session::flash('error', 'Siswa wajib dipilih untuk menetapkan jabatan akademik.');

                return $this->redirect($redirectUrl);
            }

            $student = Student::find($studentId);
            if ($student === null) {
                Session::flash('error', 'Siswa yang dipilih tidak ditemukan.');

                return $this->redirect($redirectUrl);
            }

            if (!Student::hasActiveStatus($student)) {
                Session::flash('error', 'Siswa nonaktif tidak dapat ditetapkan pada jabatan akademik.');

                return $this->redirect($redirectUrl);
            }

            if (StudentAcademicPosition::replaceAssignment($yearId, $id, $studentId)) {
                Session::flash('success', sprintf(
                    'Siswa %s telah ditetapkan sebagai %s pada tahun ajaran terpilih.',
                    $student['nama'] ?? 'tanpa nama',
                    $position['nama'] ?? 'jabatan akademik'
                ));
            } else {
                Session::flash('error', 'Gagal menetapkan siswa pada jabatan akademik.');
            }

            return $this->redirect($redirectUrl);
        }

        if ($action === 'clear') {
            if ($requiresMajor && $majorId <= 0) {
                Session::flash('error', 'Pilih jurusan yang ingin dikosongkan.');

                return $this->redirect($redirectUrl);
            }

            if (TeacherAcademicPosition::clearAssignments($yearId, $id, $requiresMajor ? $majorId : null)) {
                Session::flash('success', 'Penetapan guru pada jabatan akademik telah dikosongkan.');
            } else {
                Session::flash('error', 'Gagal mengosongkan penetapan guru.');
            }

            return $this->redirect($redirectUrl);
        }

        if ($requiresMajor && $majorId <= 0) {
            Session::flash('error', 'Pilih jurusan sebelum menetapkan jabatan ini.');

            return $this->redirect($redirectUrl);
        }

        if ($requiresMajor && Major::find($majorId) === null) {
            Session::flash('error', 'Jurusan yang dipilih tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ($teacherId <= 0) {
            Session::flash('error', 'Guru wajib dipilih untuk menetapkan jabatan akademik.');

            return $this->redirect($redirectUrl);
        }

        $teacher = Teacher::find($teacherId);
        if ($teacher === null) {
            Session::flash('error', 'Guru yang dipilih tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if (TeacherAcademicPosition::replaceAssignment($yearId, $id, $teacherId, $requiresMajor ? $majorId : null)) {
            Session::flash('success', sprintf(
                'Guru %s telah ditetapkan sebagai %s pada tahun ajaran terpilih.',
                $teacher['nama'] ?? 'tanpa nama',
                $position['nama'] ?? 'jabatan akademik'
            ));
        } else {
            Session::flash('error', 'Gagal menetapkan guru pada jabatan akademik.');
        }

        return $this->redirect($redirectUrl);
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/jabatan-akademik')) {
            return $response;
        }

        if (AcademicPosition::isSystem($id)) {
            Session::flash('error', 'Jabatan default tidak dapat dihapus.');

            return $this->redirect('master/jabatan-akademik');
        }

        try {
            AcademicPosition::deleteById($id);
            Session::flash('success', 'Jabatan akademik dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus jabatan akademik: ' . $exception->getMessage());
        }

        return $this->redirect('master/jabatan-akademik');
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $kategori = strtolower(trim((string) $request->input('kategori', 'guru')));
        $data = [
            'nama' => trim((string) $request->input('nama', '')),
            'deskripsi' => trim((string) $request->input('deskripsi', '')),
            'kategori' => $kategori,
        ];

        if ($data['nama'] === '') {
            Session::flash('error', 'Nama jabatan akademik wajib diisi.');
            Session::flashInput($request->all());

            return null;
        }

        if (!in_array($data['kategori'], ['guru', 'siswa'], true)) {
            Session::flash('error', 'Kategori jabatan tidak valid.');
            Session::flashInput($request->all());

            return null;
        }

        if (AcademicPosition::exists(['nama' => $data['nama'], 'kategori' => $data['kategori']], $ignoreId)) {
            Session::flash('error', 'Nama jabatan akademik sudah terdaftar.');
            Session::flashInput($request->all());

            return null;
        }

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        $requiresMajorRaw = (string) $request->input('requires_major', '0');
        $requiresMajor = in_array($requiresMajorRaw, ['1', 'true', 'on'], true) ? 1 : 0;

        if ($data['kategori'] !== 'guru') {
            $requiresMajor = 0;
        }

        $data['requires_major'] = $requiresMajor;

        return $data;
    }
}
