<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentPlacementHistory;
use App\Services\Import\MasterDataImporter;
use App\Services\StudentAcademicHistoryService;
use App\Services\StudentExporter;
use App\Services\StudentAccountManager;
use App\Support\AcademicRoleGate;
use App\Support\StudentImportTemplate;
use App\Support\StudentDocumentFields;
use App\Support\StudentNipdGenerator;
use App\Traits\HandlesImportUpload;
use App\Traits\ManagesStudentFileAccess;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;

class StudentController extends Controller
{
    use HandlesImportUpload, ManagesStudentFileAccess;

    protected ?string $layout = 'admin';

    public function selfSummary(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            Session::flash('error', 'Menu profil siswa hanya tersedia untuk siswa.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $user['student_id'];
        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('dashboard');
        }

        $documentFields = StudentDocumentFields::all();
        $documentStatuses = [];
        foreach ($documentFields as $key => $definition) {
            $path = trim((string) ($student[$definition['column']] ?? ''));
            $documentStatuses[$key] = [
                'label' => $definition['label'],
                'input' => $definition['input'],
                'path' => $path,
                'is_complete' => $path !== '',
            ];
        }

        return $this->render('student/profile/show', [
            'title' => 'Profil Saya',
            'pageTitle' => 'Profil Saya',
            'activeMenu' => 'student-self-profile',
            'student' => $student,
            'documentFields' => $documentFields,
            'documentStatuses' => $documentStatuses,
            'academicHistory' => StudentAcademicHistoryService::collect([$student]),
        ], 'admin');
    }

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $canManage = $role === 'admin';
        $canEditStudents = $canManage;
        $teacherId = $user !== null && !empty($user['teacher_id']) ? (int) $user['teacher_id'] : null;
        $activeYear = SchoolYear::active();
        $regularNipdPreview = null;
        if ($activeYear !== null) {
            try {
                $regularNipdPreview = StudentNipdGenerator::previewNext($activeYear, StudentNipdGenerator::TYPE_REGULAR);
            } catch (\Throwable) {
                $regularNipdPreview = null;
            }
        }

        $students = [];
        $editing = null;
        $selectedYearLabel = null;
        $homeroomClasses = [];
        $showStudentForm = false;

        if ($canManage) {
            $editId = (int) $request->query('edit', 0);
            if ($editId > 0) {
                return $this->redirect('master/siswa/' . $editId . '/edit');
            }

            $students = Student::allWithRelations();

            foreach ($students as $studentRecord) {
                if (is_array($studentRecord)) {
                    StudentAccountManager::sync($studentRecord, false);
                }
            }

            if (!$showStudentForm) {
                $requestedFormMode = strtolower(trim((string) $request->query('form', '')));
                $showStudentForm = $requestedFormMode === 'create';
            }
        } elseif ($role === 'guru' && $teacherId !== null) {
            $canEditStudents = true;
            $activeYearId = (int) ($activeYear['id'] ?? 0);

            $homeroomClasses = Classroom::homeroomClassesForTeacher(
                $teacherId,
                $activeYearId > 0 ? $activeYearId : null
            );

            if (empty($homeroomClasses)) {
                $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId);
            }

            if (empty($homeroomClasses)) {
                Session::flash('error', 'Anda tidak memiliki kelas wali aktif.');

                return $this->redirect('dashboard');
            }

            $editId = (int) $request->query('edit', 0);
            if ($editId > 0) {
                return $this->redirect('master/siswa/' . $editId . '/edit');
            }

            $classIds = array_map(
                static fn (array $classroom) => (int) ($classroom['id'] ?? 0),
                $homeroomClasses
            );
            $classIds = array_values(array_filter($classIds, static fn (int $id) => $id > 0));

            $students = Student::allWithRelations($classIds);

            if ($activeYear !== null) {
                $selectedYearLabel = sprintf(
                    '%s - %s',
                    $activeYear['nama'],
                    (int) ($activeYear['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
                );
            }

        } else {
            Session::flash('error', 'Anda tidak memiliki hak akses ke data siswa.');

            return $this->redirect('dashboard');
        }

        $documentFieldDefinitions = StudentDocumentFields::all();
        $studentTablePerPage = (int) $request->query('per_page', 20);
        if ($studentTablePerPage <= 0) {
            $studentTablePerPage = 20;
        }
        if ($studentTablePerPage > 100) {
            $studentTablePerPage = 100;
        }
        $studentTablePage = (int) $request->query('page', 1);
        if ($studentTablePage <= 0) {
            $studentTablePage = 1;
        }

        $studentTableTotal = count($students);
        $studentTablePages = max(1, (int) ceil($studentTableTotal / $studentTablePerPage));
        if ($studentTablePage > $studentTablePages) {
            $studentTablePage = $studentTablePages;
        }

        $studentTableOffset = ($studentTablePage - 1) * $studentTablePerPage;
        $studentTableRows = array_slice($students, $studentTableOffset, $studentTablePerPage);

        $documentTablePerPage = (int) $request->query('doc_per_page', 10);
        if ($documentTablePerPage <= 0) {
            $documentTablePerPage = 10;
        }
        if ($documentTablePerPage > 100) {
            $documentTablePerPage = 100;
        }
        $documentTablePage = (int) $request->query('doc_page', 1);
        if ($documentTablePage <= 0) {
            $documentTablePage = 1;
        }

        $documentTableTotal = count($students);
        $documentTablePages = max(1, (int) ceil($documentTableTotal / $documentTablePerPage));
        if ($documentTablePage > $documentTablePages) {
            $documentTablePage = $documentTablePages;
        }

        $documentTableOffset = ($documentTablePage - 1) * $documentTablePerPage;
        $documentTableSlice = array_slice($students, $documentTableOffset, $documentTablePerPage);
        $documentTableRows = [];

        foreach ($documentTableSlice as $studentRecord) {
            if (!is_array($studentRecord)) {
                continue;
            }

            $statuses = [];
            foreach ($documentFieldDefinitions as $key => $definition) {
                $column = $definition['column'];
                $path = trim((string) ($studentRecord[$column] ?? ''));
                $statuses[$key] = [
                    'label' => $definition['label'],
                    'path' => $path,
                    'is_complete' => $path !== '',
                ];
            }

            $documentTableRows[] = [
                'student' => $studentRecord,
                'statuses' => $statuses,
            ];
        }

        return $this->render('master/students/index', [
            'title' => 'Siswa',
            'pageTitle' => 'Master Siswa',
            'activeMenu' => $canManage ? 'students' : 'homeroom-students',
            'students' => $students,
            'editingStudent' => $editing,
            'selectedYearLabel' => $selectedYearLabel,
            'canManageStudents' => $canManage,
            'canEditStudents' => $canEditStudents,
            'homeroomClasses' => $homeroomClasses,
            'canUploadPhotos' => $canManage || $role === 'guru',
            'canUploadDocuments' => $canManage || $role === 'guru',
            'documentFieldDefinitions' => $documentFieldDefinitions,
            'studentTableRows' => $studentTableRows,
            'studentTablePage' => $studentTablePage,
            'studentTablePages' => $studentTablePages,
            'studentTablePerPage' => $studentTablePerPage,
            'studentTableTotal' => $studentTableTotal,
            'documentTableRows' => $documentTableRows,
            'documentTablePage' => $documentTablePage,
            'documentTablePages' => $documentTablePages,
            'documentTablePerPage' => $documentTablePerPage,
            'documentTableTotal' => $documentTableTotal,
            'showStudentForm' => $showStudentForm,
            'regularNipdPreview' => $regularNipdPreview,
        ], 'admin');
    }

    public function selfProfile(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            Session::flash('error', 'Menu data diri hanya tersedia untuk siswa.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $user['student_id'];
        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('dashboard');
        }

        return $this->render('master/students/index', [
            'title' => 'Data Diri Saya',
            'pageTitle' => 'Data Diri Saya',
            'activeMenu' => 'student-profile',
            'students' => [$student],
            'editingStudent' => $student,
            'selectedYearLabel' => null,
            'canManageStudents' => false,
            'canEditStudents' => true,
            'homeroomClasses' => [],
            'canUploadPhotos' => false,
            'canUploadDocuments' => false,
            'documentFieldDefinitions' => StudentDocumentFields::all(),
            'documentTableRows' => [],
            'documentTablePage' => 1,
            'documentTablePages' => 1,
            'documentTablePerPage' => 10,
            'documentTableTotal' => 0,
            'showStudentForm' => true,
            'showStudentListing' => false,
            'showStudentStatusSection' => false,
            'showStudentPhotoUpload' => true,
            'studentPhotoUploadAction' => base_url('siswa/data-diri/foto'),
            'studentFormAction' => base_url('siswa/data-diri'),
            'studentFormCancelUrl' => base_url('dashboard'),
            'studentFormNotice' => 'Perbarui data sesuai dokumen resmi. Penempatan kelas tetap dikelola oleh sekolah.',
        ], 'admin');
    }

    public function selfUpdate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'siswa/data-diri')) {
            return $response;
        }

        $user = auth();
        if (!is_array($user) || ($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            Session::flash('error', 'Menu data diri hanya tersedia untuk siswa.');

            return $this->redirect('dashboard');
        }

        $studentId = (int) $user['student_id'];
        $originalStudent = Student::find($studentId);

        if ($originalStudent === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('dashboard');
        }

        $payload = $this->validate($request, false, $studentId);

        if ($payload === null) {
            return $this->redirect('siswa/data-diri');
        }

        $payload['status'] = (string) ($originalStudent['status'] ?? 'aktif');
        $payload['status_dapodik'] = (string) ($originalStudent['status_dapodik'] ?? 'aktif');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            if (!Student::updateById($studentId, $payload)) {
                Session::flash('error', 'Gagal memperbarui data diri.');

                return $this->redirect('siswa/data-diri');
            }

            $updatedStudent = Student::find($studentId);
            if ($updatedStudent !== null) {
                $originalNisn = (string) ($originalStudent['nisn'] ?? '');
                $currentNisn = (string) ($updatedStudent['nisn'] ?? '');
                $originalNipd = (string) ($originalStudent['nipd'] ?? '');
                $currentNipd = (string) ($updatedStudent['nipd'] ?? '');
                $shouldResetPassword = ($currentNisn !== '' && $currentNisn !== $originalNisn)
                    || ($currentNipd !== '' && $currentNipd !== $originalNipd);
                StudentAccountManager::sync($updatedStudent, $shouldResetPassword);
            }

            Session::flash('success', 'Data diri berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui data diri: ' . $exception->getMessage());
        }

        return $this->redirect('siswa/data-diri');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa')) {
            return $response;
        }

        $payload = $this->validate($request);

        if ($payload === null) {
            return $this->redirect('master/siswa?form=create');
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif untuk membuat NIPD siswa.');
            Session::flashInput($request->all());

            return $this->redirect('master/siswa?form=create');
        }

        try {
            $payload['nipd'] = StudentNipdGenerator::generateNext($activeYear, StudentNipdGenerator::TYPE_REGULAR);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat NIPD siswa: ' . $exception->getMessage());
            Session::flashInput($request->all());

            return $this->redirect('master/siswa?form=create');
        }

        $payload['tahun_ajaran_id'] = $activeYearId;
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            if (!Student::create($payload)) {
                Session::flash('error', 'Gagal menambahkan siswa.');
                return $this->redirect('master/siswa?form=create');
            } else {
                $studentId = (int) Database::connection()->lastInsertId();
                if ($studentId > 0) {
                    StudentAccountManager::syncById($studentId, true);
                } else {
                    $createdStudent = Student::findByNisn((string) ($payload['nisn'] ?? ''))
                        ?? Student::findByNipd((string) ($payload['nipd'] ?? ''));
                    if ($createdStudent !== null) {
                        StudentAccountManager::sync($createdStudent, true);
                    }
                }

                Session::flash('success', 'Siswa berhasil ditambahkan.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan siswa: ' . $exception->getMessage());
            return $this->redirect('master/siswa?form=create');
        }

        return $this->redirect('master/siswa');
    }

    public function transferCreate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureStudentTransferAccess()) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $user = auth();
        $nipdPreview = null;
        if ($activeYear !== null) {
            try {
                $nipdPreview = StudentNipdGenerator::previewNext($activeYear, StudentNipdGenerator::TYPE_TRANSFER);
            } catch (\Throwable) {
                $nipdPreview = null;
            }
        }

        return $this->render('master/students/transfer', [
            'title' => 'Siswa Pindahan',
            'pageTitle' => 'Input Siswa Pindahan',
            'activeMenu' => 'student-transfers',
            'activeYear' => $activeYear,
            'classOptions' => $activeYearId > 0 ? Classroom::options($activeYearId) : [],
            'canManageStudentMaster' => is_array($user) && (string) ($user['role'] ?? '') === 'admin',
            'nipdPreview' => $nipdPreview,
        ], 'admin');
    }

    public function transferStore(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureStudentTransferAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa/pindahan')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif untuk penempatan siswa pindahan.');
            Session::flashInput($request->all());

            return $this->redirect('master/siswa/pindahan');
        }

        $classId = (int) $request->input('kelas_id', 0);
        $classroom = $classId > 0 ? Classroom::find($classId) : null;
        if ($classroom === null || (int) ($classroom['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Pilih kelas tujuan pada tahun ajaran aktif.');
            Session::flashInput($request->all());

            return $this->redirect('master/siswa/pindahan');
        }

        $originSchool = trim((string) $request->input('sekolah_asal', ''));
        if ($originSchool === '') {
            Session::flash('error', 'Sekolah asal wajib diisi untuk siswa pindahan.');
            Session::flashInput($request->all());

            return $this->redirect('master/siswa/pindahan');
        }

        $payload = $this->validate($request);
        if ($payload === null) {
            return $this->redirect('master/siswa/pindahan');
        }

        $now = date('Y-m-d H:i:s');
        $payload['sekolah_asal'] = $originSchool;
        try {
            $payload['nipd'] = StudentNipdGenerator::generateNext($activeYear, StudentNipdGenerator::TYPE_TRANSFER);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat NIPD siswa pindahan: ' . $exception->getMessage());
            Session::flashInput($request->all());

            return $this->redirect('master/siswa/pindahan');
        }

        $payload['kelas_id'] = $classId;
        $payload['tahun_ajaran_id'] = $activeYearId;
        $payload['status'] = 'aktif';
        $payload['status_dapodik'] = 'belum_masuk';
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        try {
            if (!Student::create($payload)) {
                Session::flash('error', 'Gagal menyimpan siswa pindahan.');
                Session::flashInput($request->all());

                return $this->redirect('master/siswa/pindahan');
            }

            $studentId = (int) Database::connection()->lastInsertId();
            if ($studentId <= 0) {
                $createdStudent = Student::findByNisn((string) ($payload['nisn'] ?? ''))
                    ?? Student::findByNipd((string) ($payload['nipd'] ?? ''));
                $studentId = (int) ($createdStudent['id'] ?? 0);
            }

            if ($studentId > 0) {
                StudentPlacementHistory::upsert($studentId, $classId, $activeYearId);
                StudentAccountManager::syncById($studentId, true);
            }

            Session::flash('success', 'Siswa pindahan berhasil disimpan dan ditempatkan ke kelas tujuan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyimpan siswa pindahan: ' . $exception->getMessage());
            Session::flashInput($request->all());

            return $this->redirect('master/siswa/pindahan');
        }

        return $this->redirect('master/siswa/pindahan/daftar');
    }

    public function transferList(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureStudentTransferAccess()) {
            return $response;
        }

        $keyword = trim((string) $request->query('q', ''));
        $user = auth();

        return $this->render('master/students/transfer-list', [
            'title' => 'Daftar Siswa Pindahan',
            'pageTitle' => 'Daftar Siswa Pindahan',
            'activeMenu' => 'student-transfers',
            'students' => Student::transferStudents($keyword === '' ? null : $keyword),
            'keyword' => $keyword,
            'canManageStudentMaster' => is_array($user) && (string) ($user['role'] ?? '') === 'admin',
            'canEditTransferStudents' => true,
        ], 'admin');
    }

    public function profile(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($id <= 0) {
            Session::flash('error', 'Data siswa tidak valid.');

            return $this->redirect('master/siswa');
        }

        $context = $this->resolveAccessibleStudentsForFileManagement('Anda tidak memiliki hak akses ke profil siswa.');
        if ($context === null) {
            return $this->redirect('master/siswa');
        }

        if (!isset($context['byId'][$id])) {
            Session::flash('error', 'Siswa tidak ditemukan atau berada di luar akses Anda.');

            return $this->redirect('master/siswa');
        }

        $student = Student::findWithRelations($id);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('master/siswa');
        }

        $documentFields = StudentDocumentFields::all();
        $documentStatuses = [];
        foreach ($documentFields as $key => $definition) {
            $path = trim((string) ($student[$definition['column']] ?? ''));
            $documentStatuses[$key] = [
                'label' => $definition['label'],
                'input' => $definition['input'],
                'path' => $path,
                'is_complete' => $path !== '',
            ];
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');

        return $this->render('master/students/profile', [
            'title' => 'Profil Siswa',
            'pageTitle' => 'Profil Siswa',
            'activeMenu' => $role === 'admin' ? 'students' : 'homeroom-students',
            'student' => $student,
            'documentFields' => $documentFields,
            'documentStatuses' => $documentStatuses,
            'canManageStudents' => $role === 'admin',
            'canEditStudents' => true,
            'canUploadPhotos' => true,
            'canUploadDocuments' => true,
            'returnTo' => 'master/siswa/' . $id . '/profil',
        ], 'admin');
    }

    private function ensureStudentTransferAccess(): ?Response
    {
        $user = auth();

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (is_array($user) && (string) ($user['role'] ?? '') === 'admin') {
            return null;
        }

        if (AcademicRoleGate::isTataUsaha($user)) {
            return null;
        }

        Session::flash('error', 'Anda tidak memiliki hak akses ke fitur siswa pindahan.');

        return $this->redirect('dashboard');
    }

    public function edit(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($id <= 0) {
            Session::flash('error', 'Data siswa tidak valid.');

            return $this->redirect('master/siswa');
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $teacherId = $user !== null && !empty($user['teacher_id']) ? (int) $user['teacher_id'] : null;

        $student = Student::findWithRelations($id);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('master/siswa');
        }

        $canManage = $role === 'admin';
        $canEditStudents = $canManage;
        $canEditTransferStudent = !$canManage
            && AcademicRoleGate::isTataUsaha($user)
            && trim((string) ($student['sekolah_asal'] ?? '')) !== '';
        $homeroomClasses = [];

        if (!$canManage && !$canEditTransferStudent) {
            if ($role !== 'guru' || $teacherId === null) {
                Session::flash('error', 'Anda tidak memiliki hak untuk mengubah data siswa.');

                return $this->redirect('master/siswa');
            }

            $activeYear = SchoolYear::active();
            $activeYearId = (int) ($activeYear['id'] ?? 0);
            $homeroomClasses = Classroom::homeroomClassesForTeacher(
                $teacherId,
                $activeYearId > 0 ? $activeYearId : null
            );

            if (empty($homeroomClasses)) {
                $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId);
            }

            $classIds = array_map(
                static fn (array $classroom) => (int) ($classroom['id'] ?? 0),
                $homeroomClasses
            );
            $classIds = array_values(array_filter($classIds, static fn (int $classId) => $classId > 0));
            $studentClassId = (int) ($student['kelas_id'] ?? 0);

            if ($studentClassId === 0 || empty($classIds) || !in_array($studentClassId, $classIds, true)) {
                Session::flash('error', 'Anda hanya dapat mengubah siswa di kelas yang Anda wali.');

                return $this->redirect('master/siswa');
            }

            $canEditStudents = true;
        } elseif ($canEditTransferStudent) {
            $canEditStudents = true;
        }

        return $this->render('master/students/index', [
            'title' => 'Edit Siswa',
            'pageTitle' => 'Edit Siswa',
            'activeMenu' => $canEditTransferStudent ? 'student-transfers' : ($canManage ? 'students' : 'homeroom-students'),
            'students' => [$student],
            'editingStudent' => $student,
            'selectedYearLabel' => null,
            'canManageStudents' => $canManage,
            'canEditStudents' => $canEditStudents,
            'homeroomClasses' => $homeroomClasses,
            'canUploadPhotos' => false,
            'canUploadDocuments' => false,
            'documentFieldDefinitions' => StudentDocumentFields::all(),
            'documentTableRows' => [],
            'documentTablePage' => 1,
            'documentTablePages' => 1,
            'documentTablePerPage' => 10,
            'documentTableTotal' => 0,
            'showStudentForm' => true,
            'showStudentListing' => false,
            'studentFormAction' => base_url('master/siswa/' . $id . '/update'),
            'studentFormCancelUrl' => $canEditTransferStudent ? base_url('master/siswa/pindahan/daftar') : base_url('master/siswa'),
            'studentFormNotice' => 'Edit data siswa dilakukan di halaman khusus agar fokus pada satu data.',
        ], 'admin');
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa')) {
            return $response;
        }

        if ($id <= 0) {
            Session::flash('error', 'Data siswa tidak valid.');

            return $this->redirect('master/siswa');
        }

        $user = auth();
        $role = (string) ($user['role'] ?? '');
        $teacherId = $user !== null && !empty($user['teacher_id']) ? (int) $user['teacher_id'] : null;
        $studentForAccess = Student::findWithRelations($id);

        if ($studentForAccess === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect('master/siswa');
        }

        $canUpdateTransferStudent = $role !== 'admin'
            && AcademicRoleGate::isTataUsaha($user)
            && trim((string) ($studentForAccess['sekolah_asal'] ?? '')) !== '';

        if ($role !== 'admin' && !$canUpdateTransferStudent) {
            if ($role !== 'guru' || $teacherId === null) {
                Session::flash('error', 'Anda tidak memiliki hak untuk mengubah data siswa.');

                return $this->redirect('master/siswa');
            }

            $studentClassId = (int) ($studentForAccess['kelas_id'] ?? 0);
            if ($studentClassId === 0) {
                Session::flash('error', 'Siswa belum ditempatkan pada kelas yang Anda wali.');

                return $this->redirect('master/siswa');
            }

            $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId);
            $classIds = array_map(
                static fn (array $classroom) => (int) ($classroom['id'] ?? 0),
                $homeroomClasses
            );
            $classIds = array_values(array_filter($classIds, static fn (int $classId) => $classId > 0));

            if (empty($classIds) || !in_array($studentClassId, $classIds, true)) {
                Session::flash('error', 'Anda hanya dapat mengubah siswa di kelas yang Anda wali.');

                return $this->redirect('master/siswa');
            }
        }

        $payload = $this->validate($request, false, $id);

        if ($payload === null) {
            return $this->redirect('master/siswa/' . $id . '/edit');
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $originalStudent = Student::find($id);

        try {
            if (!Student::updateById($id, $payload)) {
                Session::flash('error', 'Gagal memperbarui siswa.');
                return $this->redirect('master/siswa/' . $id . '/edit');
            } else {
                $updatedStudent = Student::find($id);
                if ($updatedStudent !== null) {
                    $originalNisn = (string) ($originalStudent['nisn'] ?? '');
                    $currentNisn = (string) ($updatedStudent['nisn'] ?? '');
                    $originalNipd = (string) ($originalStudent['nipd'] ?? '');
                    $currentNipd = (string) ($updatedStudent['nipd'] ?? '');
                    $shouldResetPassword = ($currentNisn !== '' && $currentNisn !== $originalNisn)
                        || ($currentNipd !== '' && $currentNipd !== $originalNipd);
                    StudentAccountManager::sync($updatedStudent, $shouldResetPassword);
                }

                Session::flash('success', 'Data siswa berhasil diperbarui.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui siswa: ' . $exception->getMessage());
            return $this->redirect('master/siswa/' . $id . '/edit');
        }

        return $this->redirect($canUpdateTransferStudent ? 'master/siswa/pindahan/daftar' : 'master/siswa');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa')) {
            return $response;
        }

        $student = Student::find($id);
        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');
            return $this->redirect('master/siswa');
        }

        try {
            if (!Student::deleteById($id)) {
                Session::flash('error', 'Gagal menghapus siswa.');
                return $this->redirect('master/siswa');
            }

            StudentAccountManager::delete($id);
            $this->removeStudentFiles($student);
            Session::flash('success', 'Siswa dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus siswa: ' . $exception->getMessage());
        }

        return $this->redirect('master/siswa');
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'master/siswa')) {
            return $response;
        }

        $files = $request->files();
        $upload = is_array($files) ? ($files['import_file'] ?? null) : null;

        if (!is_array($upload)) {
            Session::flash('error', 'File import tidak ditemukan.');

            return $this->redirect('master/siswa');
        }

        $errorMessage = null;
        $path = $this->moveImportFile($upload, $errorMessage);

        if ($path === null) {
            Session::flash('error', $errorMessage ?? 'File import tidak valid.');

            return $this->redirect('master/siswa');
        }

        try {
            $importer = new MasterDataImporter();
            $result = $importer->importStudents($path);

            $summary = sprintf(
                'Import siswa selesai. %d baris diproses: %d baru, %d diperbarui, %d dilewati.',
                $result['processed'],
                $result['inserted'],
                $result['updated'],
                $result['skipped']
            );

            Session::flash('success', $summary);

            if (!empty($result['errors'])) {
                $preview = array_slice($result['errors'], 0, 5);
                $warning = implode(' ', $preview);
                if (count($result['errors']) > 5) {
                    $warning .= sprintf(' Dan %d kesalahan lainnya.', count($result['errors']) - 5);
                }
                Session::flash('warning', $warning);
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memproses file import: ' . $exception->getMessage());
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->redirect('master/siswa');
    }

    public function downloadImportTemplate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $content = StudentImportTemplate::buildXlsx();
        if ($content === '') {
            Session::flash('error', 'Gagal membuat template import siswa.');

            return $this->redirect('master/siswa');
        }

        $filename = 'template-import-siswa-' . date('Ymd-His') . '.xlsx';

        return Response::make($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function export(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $status = strtolower(trim((string) $request->query('status', 'all')));
        $format = strtolower(trim((string) $request->query('format', 'pdf')));

        $allowedStatuses = ['all', 'aktif', 'nonaktif'];
        $allowedFormats = ['pdf', 'excel'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (!in_array($format, $allowedFormats, true)) {
            $format = 'pdf';
        }

        $students = Student::allWithRelations(null, $status === 'all' ? null : $status);

        $statusLabels = [
            'all' => 'Semua Status',
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];

        if ($format === 'excel') {
            $content = StudentExporter::toExcel($students);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $extension = 'xlsx';
        } else {
            $content = StudentExporter::toPdf($students, $statusLabels[$status] ?? $statusLabels['all']);
            $contentType = 'application/pdf';
            $extension = 'pdf';
        }

        $filenameParts = ['daftar-siswa'];
        if ($status !== 'all') {
            $filenameParts[] = $status;
        }
        $filenameParts[] = date('Ymd-His');
        $filename = implode('-', $filenameParts) . '.' . $extension;

        return Response::make($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param array<string, mixed> $student
     */
    private function removeStudentFiles(array $student): void
    {
        $paths = [
            $student['foto_path'] ?? null,
            $student['scan_ijazah_path'] ?? null,
            $student['scan_rapor_path'] ?? null,
            $student['scan_kartu_keluarga_path'] ?? null,
            $student['scan_akta_lahir_path'] ?? null,
            $student['scan_ktp_ayah_path'] ?? null,
            $student['scan_ktp_ibu_path'] ?? null,
        ];

        foreach ($paths as $path) {
            $this->deleteStoredFile($path);
        }
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $absolute = public_path($path);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function validate(Request $request, bool $isCreate = true, ?int $ignoreId = null): ?array
    {
        $fail = static function (string $message) use ($request): ?array {
            Session::flash('error', $message);
            Session::flashInput($request->all());

            return null;
        };

        $existingStudent = null;
        if (!$isCreate) {
            if ($ignoreId === null) {
                return $fail('Data siswa tidak valid.');
            }

            $existingStudent = Student::find($ignoreId);
            if ($existingStudent === null) {
                return $fail('Data siswa tidak ditemukan.');
            }
        }

        $data = [];

        $requiredFields = [
            'nama' => 'Nama',
            'jenis_kelamin' => 'Jenis kelamin',
            'nisn' => 'NISN',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'nik' => 'NIK',
            'ayah_nama' => 'Nama ayah',
            'ibu_nama' => 'Nama ibu',
        ];

        $missing = [];
        foreach ($requiredFields as $field => $label) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '') {
                $missing[] = $label;
            }
            $data[$field] = $value;
        }

        $data['nipd'] = $isCreate ? '' : trim((string) ($existingStudent['nipd'] ?? ''));
        if (!$isCreate && $data['nipd'] === '') {
            return $fail('NIPD siswa tidak valid.');
        }

        if (!empty($missing)) {
            return $fail('Field berikut wajib diisi: ' . implode(', ', $missing) . '.');
        }

        $data['jenis_kelamin'] = strtoupper($data['jenis_kelamin']);
        $allowedGender = ['L', 'P'];
        if (!in_array($data['jenis_kelamin'], $allowedGender, true)) {
            return $fail('Jenis kelamin tidak valid.');
        }

        if ($data['tanggal_lahir'] !== '') {
            $timestamp = strtotime($data['tanggal_lahir']);
            if ($timestamp === false) {
                return $fail('Tanggal lahir tidak valid.');
            }
            if ($timestamp > time()) {
                return $fail('Tanggal lahir tidak boleh di masa depan.');
            }
        }

        if (!preg_match('/^\d{10}$/', $data['nisn'])) {
            return $fail('NISN harus terdiri dari 10 digit angka.');
        }

        if (!preg_match('/^\d{16}$/', $data['nik'])) {
            return $fail('NIK harus terdiri dari 16 digit angka.');
        }

        $status = (string) $request->input('status', 'aktif');
        $statusDapodik = (string) $request->input('status_dapodik', 'aktif');
        $allowedStatus = ['aktif', 'nonaktif'];
        $allowedStatusDapodik = ['aktif', 'mutasi', 'pindah', 'residu', 'belum_masuk'];

        if (!in_array($status, $allowedStatus, true)) {
            return $fail('Status siswa tidak valid.');
        }

        if (!in_array($statusDapodik, $allowedStatusDapodik, true)) {
            return $fail('Status dapodik siswa tidak valid.');
        }

        if (Student::exists(['nisn' => $data['nisn']], $ignoreId)) {
            return $fail('NISN siswa sudah terdaftar.');
        }

        if ($data['nipd'] !== '' && Student::exists(['nipd' => $data['nipd']], $ignoreId)) {
            return $fail('NIPD siswa sudah terdaftar.');
        }

        if (Student::exists(['nik' => $data['nik']], $ignoreId)) {
            return $fail('NIK siswa sudah terdaftar.');
        }

        $optionalStrings = [
            'agama',
            'alamat',
            'rt',
            'rw',
            'dusun',
            'kelurahan',
            'kecamatan',
            'kode_pos',
            'jenis_tinggal',
            'alat_transportasi',
            'telepon',
            'hp',
            'email',
            'skhun',
            'nomor_kps',
            'ayah_jenjang_pendidikan',
            'ayah_pekerjaan',
            'ayah_penghasilan',
            'ayah_nik',
            'ibu_jenjang_pendidikan',
            'ibu_pekerjaan',
            'ibu_penghasilan',
            'ibu_nik',
            'wali_nama',
            'wali_jenjang_pendidikan',
            'wali_pekerjaan',
            'wali_penghasilan',
            'wali_nik',
            'rombel_saat_ini',
            'nomor_peserta_ujian',
            'nomor_seri_ijazah',
            'nomor_kip',
            'nama_di_kip',
            'nomor_kks',
            'nomor_registrasi_akta_lahir',
            'bank',
            'nomor_rekening_bank',
            'rekening_atas_nama',
            'alasan_layak_pip',
            'kebutuhan_khusus',
            'sekolah_asal',
            'nomor_kk',
        ];

        foreach ($optionalStrings as $field) {
            $value = $request->input($field, null);
            if ($value === null) {
                $data[$field] = null;
                continue;
            }
            $trimmed = trim((string) $value);
            $data[$field] = $trimmed === '' ? null : $trimmed;
        }

        $yearFields = [
            'ayah_tahun_lahir' => 'Tahun lahir ayah',
            'ibu_tahun_lahir' => 'Tahun lahir ibu',
            'wali_tahun_lahir' => 'Tahun lahir wali',
        ];

        foreach ($yearFields as $field => $label) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            if (!preg_match('/^\d{4}$/', $value)) {
                return $fail($label . ' harus terdiri dari 4 digit angka.');
            }
            $data[$field] = $value;
        }

        $booleanParser = static function (mixed $value): int {
            if ($value === null) {
                return 0;
            }
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }

            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['1', 'true', 'on', 'ya', 'y', 'yes'], true) ? 1 : 0;
        };

        $data['penerima_kps'] = $booleanParser($request->input('penerima_kps'));
        $data['penerima_kip'] = $booleanParser($request->input('penerima_kip'));
        $data['layak_pip'] = $booleanParser($request->input('layak_pip'));

        $integerFields = [
            'anak_ke' => 'Anak ke',
            'jumlah_saudara_kandung' => 'Jumlah saudara kandung',
        ];

        foreach ($integerFields as $field => $label) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            if (!preg_match('/^\d+$/', $value)) {
                return $fail($label . ' harus berupa angka.');
            }
            $numericValue = (int) $value;
            $data[$field] = $numericValue > 0 ? $numericValue : null;
        }

        $coordinateFields = [
            'lintang' => 'Lintang',
            'bujur' => 'Bujur',
        ];

        foreach ($coordinateFields as $field => $label) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            $normalized = str_replace(',', '.', $value);
            if (!is_numeric($normalized)) {
                return $fail($label . ' harus berupa angka.');
            }
            $data[$field] = $normalized;
        }

        $decimalFields = [
            'berat_badan' => 'Berat badan',
            'tinggi_badan' => 'Tinggi badan',
            'lingkar_kepala' => 'Lingkar kepala',
            'jarak_rumah_ke_sekolah_km' => 'Jarak rumah ke sekolah',
        ];

        foreach ($decimalFields as $field => $label) {
            $value = trim((string) $request->input($field, ''));
            if ($value === '') {
                $data[$field] = null;
                continue;
            }
            $normalized = str_replace(',', '.', $value);
            if (!is_numeric($normalized)) {
                return $fail($label . ' harus berupa angka.');
            }
            if ((float) $normalized < 0) {
                return $fail($label . ' tidak boleh bernilai negatif.');
            }
            $data[$field] = $normalized;
        }

        $data['status'] = $status;
        $data['status_dapodik'] = $statusDapodik;

        return $data;
    }
}
