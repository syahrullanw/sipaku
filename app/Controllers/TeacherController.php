<?php

namespace App\Controllers;

use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Models\UnexpectedExpense;
use App\Models\User;
use App\Services\Import\MasterDataImporter;
use App\Services\TeacherExporter;
use App\Services\TeacherProfileForm;
use App\Support\DemoMode;
use App\Traits\HandlesImportUpload;
use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use PDO;

class TeacherController extends Controller
{
    use HandlesImportUpload;

    protected ?string $layout = 'admin';
    private const GTK_TENDIK_LABEL = 'Tenaga Kependidikan';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $teachers = Teacher::allOrdered();
        $demoModeEnabled = DemoMode::isEnabled();
        $editId = $demoModeEnabled ? 0 : (int) $request->query('edit', 0);
        if ($demoModeEnabled && $request->query('edit') !== null) {
            Session::flash('warning', 'Mode demo aktif. Form edit guru dinonaktifkan.');
        }
        $editing = $editId > 0 ? Teacher::find($editId) : null;
        $defaultSchool = SchoolProfile::first();
        $formOptions = TeacherProfileForm::options();
        $unexpectedExpenses = $editing !== null ? UnexpectedExpense::historyForTeacher((int) ($editing['id'] ?? 0), 10) : [];

        return $this->render('master/teachers/index', [
            'title' => 'Guru',
            'pageTitle' => 'Master Guru',
            'activeMenu' => 'teachers',
            'teachers' => DemoMode::maskTeachers($teachers),
            'editingTeacher' => $editing,
            'genderOptions' => $formOptions['genders'],
            'religionOptions' => $formOptions['religions'],
            'maritalStatusOptions' => $formOptions['maritalStatuses'],
            'gtkTypeOptions' => $formOptions['gtkTypes'],
            'employmentStatusOptions' => $formOptions['employmentStatuses'],
            'educationOptions' => $formOptions['educationLevels'],
            'studyStatusOptions' => $formOptions['studyStatuses'],
            'defaultSchoolName' => is_array($defaultSchool) ? ($defaultSchool['nama'] ?? '') : '',
            'unexpectedExpenseHistory' => $unexpectedExpenses,
            'demoModeEnabled' => $demoModeEnabled,
        ], 'admin');
    }

    public function profile(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($id <= 0) {
            Session::flash('error', 'Data guru tidak valid.');

            return $this->redirect('master/guru');
        }

        $teacher = Teacher::find($id);
        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect('master/guru');
        }

        $demoModeEnabled = DemoMode::isEnabled();

        return $this->render('master/teachers/profile', array_merge([
            'title' => 'Profil Guru',
            'pageTitle' => 'Profil Guru',
            'activeMenu' => 'teachers',
            'returnUrl' => 'master/guru',
            'returnLabel' => 'Daftar Guru',
            'editUrl' => 'master/guru?edit=' . urlencode((string) $id),
            'editLabel' => 'Edit Data',
            'showEditAction' => true,
        ], $this->teacherProfileViewData($id, $teacher, $demoModeEnabled)), 'admin');
    }

    public function selfProfile(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();
        if (!is_array($user) || (string) ($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu profil guru hanya dapat diakses oleh guru.');

            return $this->redirect('dashboard');
        }

        $teacherId = (int) $user['teacher_id'];
        $teacher = Teacher::find($teacherId);
        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect('dashboard');
        }

        $demoModeEnabled = DemoMode::isEnabled();

        return $this->render('master/teachers/profile', array_merge([
            'title' => 'Profil Saya',
            'pageTitle' => 'Profil Guru Saya',
            'activeMenu' => 'teacher-profile',
            'returnUrl' => 'dashboard',
            'returnLabel' => 'Dashboard',
            'editUrl' => 'profile',
            'editLabel' => 'Edit Profil',
            'showEditAction' => true,
        ], $this->teacherProfileViewData($teacherId, $teacher, $demoModeEnabled)), 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');
            Session::flashInput($request->all());

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        $payload = TeacherProfileForm::validate($request);

        if ($payload === null) {
            return $this->redirect('master/guru');
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['status'] = 'aktif';

        try {
            Teacher::create($payload);
            $teacherId = (int) Database::connection()->lastInsertId();
            $teacher = $teacherId > 0 ? Teacher::find($teacherId) : null;

            $message = 'Guru berhasil ditambahkan.';
            if ($teacher !== null) {
                $account = $this->ensureTeacherAccount($teacher);
                if ($account !== null) {
                    $message .= sprintf(
                        ' Akun login otomatis dibuat dengan username %s dan password sementara %s. Silakan informasikan kepada guru untuk segera mengganti password.',
                        $account['username'],
                        $account['password']
                    );
                }
            }

            Session::flash('success', $message);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menambahkan guru: ' . $exception->getMessage());
        }

        return $this->redirect('master/guru');
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');
            Session::flashInput($request->all());

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        $files = $request->files();
        $upload = is_array($files) ? ($files['import_file'] ?? null) : null;

        if (!is_array($upload)) {
            Session::flash('error', 'File import tidak ditemukan.');

            return $this->redirect('master/guru');
        }

        $errorMessage = null;
        $path = $this->moveImportFile($upload, $errorMessage);

        if ($path === null) {
            Session::flash('error', $errorMessage ?? 'File import tidak valid.');

            return $this->redirect('master/guru');
        }

        try {
            $importer = new MasterDataImporter();
            $result = $importer->importTeachers($path);

            $summary = sprintf(
                'Import guru selesai. %d baris diproses: %d baru, %d diperbarui, %d dilewati.',
                $result['processed'],
                $result['inserted'],
                $result['updated'],
                $result['skipped']
            );

            if ($result['accounts_created'] > 0) {
                $summary .= sprintf(' %d akun guru baru dibuat dengan password sementara "guru123".', $result['accounts_created']);
            }

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

        return $this->redirect('master/guru');
    }

    public function export(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $scope = strtolower((string) $request->input('scope', 'all'));
        $status = strtolower((string) $request->input('status', 'all'));
        $format = strtolower((string) $request->input('format', 'pdf'));

        $allowedScopes = ['all', 'teachers', 'staff'];
        $allowedStatuses = ['all', 'aktif', 'nonaktif'];
        $allowedFormats = ['pdf', 'excel'];

        if (!in_array($scope, $allowedScopes, true)) {
            $scope = 'all';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (!in_array($format, $allowedFormats, true)) {
            $format = 'pdf';
        }

        $teachers = $this->fetchExportTeachers($scope, $status);
        $options = TeacherProfileForm::options();
        if (DemoMode::isEnabled()) {
            $teachers = DemoMode::maskTeachers($teachers);
        }

        $scopeLabels = [
            'all' => 'Guru dan Tenaga Kependidikan',
            'teachers' => 'Guru saja',
            'staff' => 'Tenaga Kependidikan',
        ];
        $statusLabels = [
            'all' => 'Semua Status',
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];

        $scopeLabel = $scopeLabels[$scope] ?? $scopeLabels['all'];
        $statusLabel = $statusLabels[$status] ?? $statusLabels['all'];

        if ($format === 'excel') {
            $content = TeacherExporter::toExcel($teachers, $options);
            $filenameExtension = 'xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $content = TeacherExporter::toPdf($teachers, $scopeLabel, $statusLabel, $options);
            $filenameExtension = 'pdf';
            $contentType = 'application/pdf';
        }

        $scopeSlugs = [
            'teachers' => 'guru',
            'staff' => 'tendik',
        ];
        $statusSlugs = [
            'aktif' => 'aktif',
            'nonaktif' => 'nonaktif',
        ];

        $filenameParts = ['daftar-guru'];
        if (isset($scopeSlugs[$scope])) {
            $filenameParts[] = $scopeSlugs[$scope];
        }
        if (isset($statusSlugs[$status])) {
            $filenameParts[] = $statusSlugs[$status];
        }
        $filenameParts[] = date('Ymd-His');

        $filename = implode('-', array_filter($filenameParts)) . '.' . $filenameExtension;

        return Response::make($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');
            Session::flashInput($request->all());

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        $payload = TeacherProfileForm::validate($request, $id);

        if ($payload === null) {
            return $this->redirect('master/guru');
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');

        try {
            Teacher::updateById($id, $payload);
            $this->syncTeacherUser($id, $payload);
            Session::flash('success', 'Guru berhasil diperbarui.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui guru: ' . $exception->getMessage());
        }

        return $this->redirect('master/guru');
    }

    public function toggleStatus(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        $teacher = Teacher::find($id);
        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');
            return $this->redirect('master/guru');
        }

        $targetStatus = trim((string) $request->input('status', ''));
        if (!in_array($targetStatus, ['aktif', 'nonaktif'], true)) {
            Session::flash('error', 'Status guru tidak valid.');
            return $this->redirect('master/guru');
        }

        if (($teacher['status'] ?? 'aktif') === $targetStatus) {
            Session::flash('success', 'Status guru sudah sesuai.');
            return $this->redirect('master/guru');
        }

        try {
            Teacher::updateById($id, [
                'status' => $targetStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash('success', $targetStatus === 'aktif' ? 'Guru diaktifkan.' : 'Guru dinonaktifkan.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal memperbarui status guru: ' . $exception->getMessage());
        }

        return $this->redirect('master/guru');
    }

    public function destroy(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        try {
            Teacher::deleteById($id);
            $this->deleteTeacherUser($id);
            Session::flash('success', 'Guru dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus guru: ' . $exception->getMessage());
        }

        return $this->redirect('master/guru');
    }

    public function resetPassword(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if (DemoMode::isEnabled()) {
            Session::flash('warning', 'Mode demo aktif. Perubahan data guru dinonaktifkan.');

            return $this->redirect('master/guru');
        }

        if ($response = $this->guardCsrf($request, 'master/guru')) {
            return $response;
        }

        $teacher = Teacher::find($id);
        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect('master/guru');
        }

        $user = User::findByTeacherId($id);
        $passwordPlain = $this->generateTemporaryPassword();

        try {
            if ($user === null) {
                $account = $this->ensureTeacherAccount($teacher);
                if ($account === null) {
                    Session::flash('error', 'Gagal membuat akun guru secara otomatis.');
                } else {
                    Session::flash('success', sprintf(
                        'Akun guru dibuat ulang dengan username %s dan password sementara %s.',
                        $account['username'],
                        $account['password']
                    ));
                }

                return $this->redirect('master/guru');
            }

            User::updateById((int) $user['id'], [
                'password' => password_hash($passwordPlain, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash('success', sprintf(
                'Password guru berhasil direset. Username: %s · Password sementara: %s',
                $user['username'] ?? '-',
                $passwordPlain
            ));
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mereset password guru: ' . $exception->getMessage());
        }

        return $this->redirect('master/guru');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExportTeachers(string $scope, string $status): array
    {
        $sql = <<<SQL
SELECT g.*, u.username
FROM guru g
LEFT JOIN users u ON u.teacher_id = g.id
WHERE 1=1
SQL;

        $params = [];

        if ($status !== 'all') {
            $sql .= ' AND g.status = :status';
            $params[':status'] = $status;
        }

        if ($scope === 'teachers') {
            $sql .= " AND (g.jenis_gtk IS NULL OR TRIM(g.jenis_gtk) = '' OR TRIM(g.jenis_gtk) <> :staff)";
            $params[':staff'] = self::GTK_TENDIK_LABEL;
        } elseif ($scope === 'staff') {
            $sql .= ' AND TRIM(g.jenis_gtk) = :staff';
            $params[':staff'] = self::GTK_TENDIK_LABEL;
        }

        $sql .= ' ORDER BY g.status ASC, g.nama ASC';

        $statement = Database::connection()->prepare($sql);
        if ($statement === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<string, mixed> $teacher
     *
     * @return array<string, mixed>
     */
    private function teacherProfileViewData(int $teacherId, array $teacher, bool $demoModeEnabled): array
    {
        $displayTeacher = $teacher;
        if ($demoModeEnabled) {
            $maskedTeachers = DemoMode::maskTeachers([$teacher]);
            $displayTeacher = $maskedTeachers[0] ?? $teacher;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;
        $activeYearStart = $activeYear !== null ? trim((string) ($activeYear['tanggal_mulai'] ?? '')) : '';
        $assignments = SubjectTeacher::byTeacher($teacherId);
        $pastAssignments = array_values(array_filter(
            $assignments,
            static function (array $assignment) use ($activeYearId, $activeYearStart): bool {
                if ($activeYearId === null || $activeYearId <= 0) {
                    return true;
                }

                $assignmentYearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
                if ($assignmentYearId === $activeYearId) {
                    return false;
                }

                $assignmentStart = trim((string) ($assignment['mata_pelajaran_tahun_ajaran_mulai'] ?? ''));
                if ($activeYearStart !== '' && $assignmentStart !== '') {
                    return strcmp($assignmentStart, $activeYearStart) < 0;
                }

                return true;
            }
        ));
        $teacherAccount = User::findByTeacherId($teacherId);
        if ($demoModeEnabled && is_array($teacherAccount)) {
            $teacherAccount['username'] = DemoMode::maskIdentifier($teacherAccount['username'] ?? null);
        }
        $formOptions = TeacherProfileForm::options();

        return [
            'teacher' => $displayTeacher,
            'teacherAccount' => $teacherAccount,
            'teachingHistoryGroups' => $this->groupTeachingHistory($pastAssignments),
            'teachingHistorySummary' => $this->summarizeTeachingHistory($pastAssignments),
            'activeSchoolYear' => $activeYear,
            'genderOptions' => $formOptions['genders'],
            'religionOptions' => $formOptions['religions'],
            'maritalStatusOptions' => $formOptions['maritalStatuses'],
            'gtkTypeOptions' => $formOptions['gtkTypes'],
            'employmentStatusOptions' => $formOptions['employmentStatuses'],
            'educationOptions' => $formOptions['educationLevels'],
            'studyStatusOptions' => $formOptions['studyStatuses'],
            'demoModeEnabled' => $demoModeEnabled,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @return array<int, array<string, mixed>>
     */
    private function groupTeachingHistory(array $assignments): array
    {
        $groups = [];

        foreach ($assignments as $assignment) {
            $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
            $yearName = trim((string) ($assignment['mata_pelajaran_tahun_ajaran_nama'] ?? 'Tahun Ajaran'));
            $semester = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 1);
            $key = $yearId > 0 ? 'year-' . $yearId : strtolower($yearName . '-' . $semester);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'school_year_id' => $yearId,
                    'school_year_name' => $yearName !== '' ? $yearName : 'Tahun Ajaran',
                    'semester' => $semester,
                    'semester_label' => $this->semesterLabel($semester),
                    'assignments' => [],
                ];
            }

            $groups[$key]['assignments'][] = $assignment;
        }

        return array_values($groups);
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     * @return array{assignments: int, subjects: int, classes: int, semesters: int}
     */
    private function summarizeTeachingHistory(array $assignments): array
    {
        $subjects = [];
        $classes = [];
        $semesters = [];

        foreach ($assignments as $assignment) {
            $subjectId = (int) ($assignment['mata_pelajaran_id'] ?? 0);
            if ($subjectId > 0) {
                $subjects[$subjectId] = true;
            } else {
                $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? ''));
                if ($subjectName !== '') {
                    $subjects[strtolower($subjectName)] = true;
                }
            }

            $yearId = (int) ($assignment['mata_pelajaran_tahun_ajaran_id'] ?? 0);
            $semester = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 1);
            $semesters[$yearId > 0 ? $yearId : ('semester-' . $semester . '-' . count($semesters))] = true;

            foreach (($assignment['classes'] ?? []) as $classroom) {
                if (!is_array($classroom)) {
                    continue;
                }

                $classId = (int) ($classroom['id'] ?? 0);
                if ($classId > 0) {
                    $classes[$classId] = true;
                    continue;
                }

                $className = trim((string) ($classroom['nama'] ?? ''));
                if ($className !== '') {
                    $classes[strtolower($className)] = true;
                }
            }
        }

        return [
            'assignments' => count($assignments),
            'subjects' => count($subjects),
            'classes' => count($classes),
            'semesters' => count($semesters),
        ];
    }

    private function semesterLabel(int $semester): string
    {
        return $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
    }

    /**
     * @param array<string, mixed> $teacher
     *
     * @return array{username: string, password: string}|null
     */
    private function ensureTeacherAccount(array $teacher): ?array
    {
        $teacherId = (int) ($teacher['id'] ?? 0);
        if ($teacherId === 0) {
            return null;
        }

        $existing = User::findByTeacherId($teacherId);
        if ($existing !== null) {
            return null;
        }

        $username = $this->generateUsernameFromName((string) ($teacher['nama'] ?? 'guru'));
        $passwordPlain = $this->generateTemporaryPassword();
        $email = trim((string) ($teacher['email'] ?? ''));

        if ($email !== '' && User::exists(['email' => $email])) {
            $email = '';
        }

        $now = date('Y-m-d H:i:s');

        try {
            User::create([
                'name' => (string) ($teacher['nama'] ?? 'Guru'),
                'username' => $username,
                'password' => password_hash($passwordPlain, PASSWORD_BCRYPT),
                'email' => $email !== '' ? $email : null,
                'role' => 'guru',
                'teacher_id' => $teacherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            // Jika pembuatan akun gagal, biarkan proses guru tetap berhasil.
            Session::flash('error', 'Akun guru tidak dapat dibuat otomatis: ' . $exception->getMessage());
            return null;
        }

        return [
            'username' => $username,
            'password' => $passwordPlain,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function syncTeacherUser(int $teacherId, array $payload): void
    {
        $user = User::findByTeacherId($teacherId);
        if ($user === null) {
            return;
        }

        $updates = [
            'name' => (string) ($payload['nama'] ?? $user['name']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            $updates['email'] = null;
        } elseif (!User::exists(['email' => $email], (int) $user['id'])) {
            $updates['email'] = $email;
        }

        User::updateById((int) $user['id'], $updates);
    }

    private function deleteTeacherUser(int $teacherId): void
    {
        $user = User::findByTeacherId($teacherId);

        if ($user !== null) {
            User::deleteById((int) $user['id']);
        }
    }

    private function generateUsernameFromName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'guru';
        }

        $parts = preg_split('/\s+/', $name);
        $firstWord = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($parts[0] ?? $name)));

        if ($firstWord === '') {
            $firstWord = 'guru';
        }

        $base = substr($firstWord, 0, 6);
        if ($base === '') {
            $base = 'guru';
        }

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'a');
        }

        $username = $base;
        $suffix = 1;

        while (User::exists(['username' => $username])) {
            $suffixString = (string) $suffix;
            $maxBaseLength = max(1, 50 - strlen($suffixString));
            $username = substr($base, 0, $maxBaseLength) . $suffixString;
            $suffix++;
        }

        return strtolower($username);
    }

    private function generateTemporaryPassword(): string
    {
        return 'guru123';
    }

}
