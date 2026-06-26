<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentGraduationStatus;
use App\Models\Teacher;
use App\Services\GraduationCertificateService;
use App\Support\AcademicRoleGate;
use App\Support\SchoolYearDocumentSettings;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class GraduationCertificateController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureWakaAccess()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $classes = array_values(array_filter(
            Classroom::byYear($activeYearId),
            static fn (array $class): bool => (int) ($class['tingkat'] ?? 0) === 12
        ));

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
        $subjects = [];
        $selectedSubjectIds = [];
        $signatureRecords = [];
        $signatureSummary = [
            'total' => 0,
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];
        $graduationStatuses = [];

        if ($selectedClass !== null) {
            $students = Student::byClass($selectedClassId, $activeYearId);
            $subjects = GraduationCertificateService::availableSubjectsForClass($selectedClassId, $activeYearId);
            $selectedSubjectIds = $this->resolveSubjectSelection($request, $subjects);
            $signatureRecords = DigitalDocumentSignature::mapByClass(
                $activeYearId,
                $selectedClassId,
                GraduationCertificateService::DOCUMENT_TYPE
            );
            $signatureSummary = $this->summarizeSignatures($students, $signatureRecords);
            $graduationStatuses = StudentGraduationStatus::byClass($selectedClassId, $activeYearId);
        }

        return $this->render('graduation/certificates/index', [
            'title' => 'Surat Keterangan Lulus',
            'pageTitle' => 'Surat Keterangan Lulus',
            'activeMenu' => 'graduation-certificates',
            'activeYear' => $activeYear,
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'subjects' => $subjects,
            'selectedSubjectIds' => $selectedSubjectIds,
            'signatureRecords' => $signatureRecords,
            'signatureSummary' => $signatureSummary,
            'graduationStatuses' => $graduationStatuses,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'akademik/skl')) {
            return $response;
        }

        if ($response = $this->ensureWakaAccess()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan oleh admin.');

            return $this->redirect('akademik/skl');
        }

        $classId = (int) $request->input('kelas_id', 0);

        if ($classId <= 0) {
            Session::flash('error', 'Pilih kelas terlebih dahulu.');
            Session::flashInput($request->all());

            return $this->redirect('akademik/skl');
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tingkat'] ?? 0) !== 12 || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Kelas tidak valid atau tidak termasuk tahun ajaran aktif.');
            Session::flashInput($request->all());

            return $this->redirect('akademik/skl');
        }

        $availableSubjects = GraduationCertificateService::availableSubjectsForClass($classId, $activeYearId);
        $allowedSubjectIds = array_map(
            static fn (array $subject): int => (int) ($subject['assignment_id'] ?? 0),
            $availableSubjects
        );
        $selectedSubjectIds = $allowedSubjectIds;

        if (empty($selectedSubjectIds)) {
            Session::flash('error', 'Tidak ada mata pelajaran dengan nilai untuk kelas ini.');
            Session::flashInput($request->all());

            return $this->redirect('akademik/skl?kelas_id=' . urlencode((string) $classId));
        }

        $students = Student::byClass($classId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas ini.');
            Session::flashInput($request->all());

            return $this->redirect('akademik/skl?kelas_id=' . urlencode((string) $classId));
        }

        $graduationStatuses = StudentGraduationStatus::byClass($classId, $activeYearId);
        $schoolYearName = (string) ($activeYear['nama'] ?? '');

        $processed = 0;
        $created = 0;
        $pending = 0;
        $approved = 0;
        $revoked = 0;
        $skipped = 0;
        $userId = (int) (auth()['id'] ?? 0);

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $eligibility = GraduationCertificateService::evaluateStudentEligibility($studentId, $activeYearId);

            if (!($eligibility['can_request_signature'] ?? false)) {
                $skipped++;
                continue;
            }

            $payload = GraduationCertificateService::buildPayloadFromEvaluation($eligibility);
            $subjects = isset($payload['subjects']) && is_array($payload['subjects']) ? $payload['subjects'] : [];

            if (!empty($selectedSubjectIds)) {
                $selectedSubjectMap = array_fill_keys($selectedSubjectIds, true);
                $subjects = array_values(array_filter(
                    $subjects,
                    static fn (array $subject): bool => isset($selectedSubjectMap[(int) ($subject['assignment_id'] ?? 0)])
                ));
                $payload['subjects'] = $subjects;
                $payload['average'] = GraduationCertificateService::averageScore($subjects);
            }

            if (empty($subjects)) {
                $skipped++;
                continue;
            }

            $documentKey = GraduationCertificateService::documentKey($studentId, $activeYearId);
            $documentTitle = GraduationCertificateService::documentTitle($payload['student']['name'], $schoolYearName);
            $existing = DigitalDocumentSignature::findByDocument(
                $activeYearId,
                GraduationCertificateService::DOCUMENT_TYPE,
                $documentKey
            );

            $record = DigitalDocumentSignature::ensure(
                $activeYearId,
                GraduationCertificateService::DOCUMENT_TYPE,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId,
                $userId > 0 ? $userId : null
            );

            if ($record === null) {
                $skipped++;
                continue;
            }

            $processed++;

            if ($existing === null) {
                $created++;
            }

            $status = (string) ($record['status'] ?? 'pending');

            if ($status === 'approved') {
                $approved++;
            } elseif ($status === 'revoked') {
                $revoked++;
            } else {
                $pending++;
            }
        }

        $messages = [];
        $messages[] = sprintf('%d siswa diproses.', $processed);

        if ($created > 0) {
            $messages[] = sprintf('%d pengajuan SKL baru dibuat.', $created);
        }

        if ($pending > 0) {
            $messages[] = sprintf('%d siswa menunggu persetujuan.', $pending);
        }

        if ($approved > 0) {
            $messages[] = sprintf('%d siswa sudah disetujui.', $approved);
        }

        if ($revoked > 0) {
            $messages[] = sprintf('%d siswa memiliki status dicabut.', $revoked);
        }

        if ($skipped > 0) {
            $messages[] = sprintf('%d siswa dilewati karena syarat nilai atau status lulus belum terpenuhi.', $skipped);
        }

        if ($processed === 0) {
            Session::flash('error', 'Tidak ada SKL yang diajukan. Pastikan syarat nilai pengajuan terpenuhi dan status siswa sudah lulus.');
        } else {
            Session::flash('success', implode(' ', $messages));
        }

        return $this->redirect('akademik/skl?kelas_id=' . urlencode((string) $classId));
    }

    public function storeHomeroom(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/status-lulus')) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'guru' || empty($user['teacher_id'])) {
            Session::flash('error', 'Menu ini hanya dapat diakses oleh wali kelas.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('walikelas/status-lulus');
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan oleh admin.');

            return $this->redirect('walikelas/status-lulus');
        }

        $teacherId = (int) $user['teacher_id'];
        $classId = (int) $request->input('kelas_id', 0);
        $redirectUrl = 'walikelas/status-lulus' . ($classId > 0 ? '?kelas_id=' . urlencode((string) $classId) : '');

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect('walikelas/status-lulus');
        }

        $class = Classroom::findWithRelations($classId);

        if (
            $class === null
            || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId
            || (int) ($class['tingkat'] ?? 0) !== 12
            || (int) ($class['wali_kelas_id'] ?? 0) !== $teacherId
        ) {
            Session::flash('error', 'Anda tidak memiliki akses mengajukan SKL untuk kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $students = Student::byClass($classId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Belum ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $processed = 0;
        $created = 0;
        $pending = 0;
        $approved = 0;
        $revoked = 0;
        $skipped = 0;
        $userId = (int) ($user['id'] ?? 0);
        $schoolYearName = (string) ($activeYear['nama'] ?? '');

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $eligibility = GraduationCertificateService::evaluateStudentEligibility($studentId, $activeYearId);

            if (!($eligibility['can_request_signature'] ?? false)) {
                $skipped++;
                continue;
            }

            $payload = GraduationCertificateService::buildPayloadFromEvaluation($eligibility);
            $subjects = isset($payload['subjects']) && is_array($payload['subjects']) ? $payload['subjects'] : [];

            if (empty($subjects)) {
                $skipped++;
                continue;
            }

            $documentKey = GraduationCertificateService::documentKey($studentId, $activeYearId);
            $documentTitle = GraduationCertificateService::documentTitle($payload['student']['name'], $schoolYearName);
            $existing = DigitalDocumentSignature::findByDocument(
                $activeYearId,
                GraduationCertificateService::DOCUMENT_TYPE,
                $documentKey
            );

            $record = DigitalDocumentSignature::ensure(
                $activeYearId,
                GraduationCertificateService::DOCUMENT_TYPE,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId,
                $userId > 0 ? $userId : null
            );

            if ($record === null) {
                $skipped++;
                continue;
            }

            $processed++;

            if ($existing === null) {
                $created++;
            }

            $status = (string) ($record['status'] ?? 'pending');
            if ($status === 'approved') {
                $approved++;
            } elseif ($status === 'revoked') {
                $revoked++;
            } else {
                $pending++;
            }
        }

        if ($processed === 0) {
            Session::flash('error', 'Tidak ada SKL yang diajukan. Pastikan syarat nilai pengajuan terpenuhi dan status siswa sudah lulus.');

            return $this->redirect($redirectUrl);
        }

        $messages = [sprintf('%d siswa diproses.', $processed)];
        if ($created > 0) {
            $messages[] = sprintf('%d pengajuan SKL baru dibuat.', $created);
        }
        if ($pending > 0) {
            $messages[] = sprintf('%d siswa menunggu persetujuan kepala sekolah.', $pending);
        }
        if ($approved > 0) {
            $messages[] = sprintf('%d siswa sudah disetujui.', $approved);
        }
        if ($revoked > 0) {
            $messages[] = sprintf('%d siswa berstatus dicabut.', $revoked);
        }
        if ($skipped > 0) {
            $messages[] = sprintf('%d siswa dilewati karena syarat belum terpenuhi.', $skipped);
        }

        Session::flash('success', implode(' ', $messages));

        return $this->redirect($redirectUrl);
    }

    public function student(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $user = auth();

        if (($user['role'] ?? '') !== 'siswa' || empty($user['student_id'])) {
            return $this->redirect('dashboard');
        }

        $studentId = (int) $user['student_id'];
        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            return $this->redirect('dashboard');
        }

        $classLevel = (int) ($student['kelas_tingkat'] ?? 0);

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $targetYearId = $activeYearId > 0 ? $activeYearId : (int) ($student['tahun_ajaran_id'] ?? 0);
        if ($activeYear === null && $targetYearId > 0) {
            $activeYear = SchoolYear::find($targetYearId);
        }
        $documentKey = GraduationCertificateService::documentKey($studentId, $targetYearId);
        $signatureRecord = null;

        if ($targetYearId > 0) {
            $signatureRecord = DigitalDocumentSignature::findByDocument(
                $targetYearId,
                GraduationCertificateService::DOCUMENT_TYPE,
                $documentKey
            );
        }

        $payload = [];

        if ($signatureRecord !== null && isset($signatureRecord['payload']) && is_string($signatureRecord['payload'])) {
            $decoded = json_decode($signatureRecord['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if ($signatureRecord !== null) {
            $livePayload = GraduationCertificateService::livePayloadForRecord($signatureRecord);
            if (!empty($livePayload)) {
                $payload = $livePayload;
            }
        }

        $verificationUrl = null;
        if ($signatureRecord !== null && ($signatureRecord['status'] ?? '') === 'approved') {
            $token = (string) ($signatureRecord['signature_token'] ?? '');
            if ($token !== '') {
                $verificationUrl = absolute_url('dokumen/validasi/' . $token);
            }
        }

        $eligibility = GraduationCertificateService::evaluateStudentEligibility(
            $studentId,
            $targetYearId > 0 ? $targetYearId : null,
            $signatureRecord
        );

        return $this->render('student/graduation/index', [
            'title' => 'Informasi Kelulusan',
            'pageTitle' => 'Informasi Kelulusan',
            'activeMenu' => 'student-graduation',
            'student' => $student,
            'classLevel' => $classLevel,
            'signatureRecord' => $signatureRecord,
            'payload' => $payload,
            'verificationUrl' => $verificationUrl,
            'activeYear' => $activeYear,
            'eligibility' => $eligibility,
        ]);
    }

    public function print(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $record = DigitalDocumentSignature::findWithRelations($id);

        if ($record === null || ($record['document_type'] ?? '') !== GraduationCertificateService::DOCUMENT_TYPE) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak SKL',
                'message' => 'Dokumen tidak ditemukan.',
            ], 'print');
        }

        if (($record['status'] ?? '') !== 'approved') {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak SKL',
                'message' => 'SKL belum disetujui oleh kepala sekolah.',
            ], 'print');
        }

        if ($response = $this->authorizeView($record)) {
            return $response;
        }

        $eligibility = GraduationCertificateService::evaluateStudentEligibility(
            (int) ($record['student_id'] ?? 0),
            (int) ($record['tahun_ajaran_id'] ?? 0),
            $record
        );

        if (!($eligibility['can_print'] ?? false)) {
            return $this->render('reports/sections/error', [
                'title' => 'Cetak SKL',
                'message' => $this->firstBlockingMessage($eligibility),
            ], 'print');
        }

        $payload = [];
        if (isset($record['payload']) && is_string($record['payload'])) {
            $decoded = json_decode($record['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $livePayload = GraduationCertificateService::livePayloadForRecord($record);
        if (!empty($livePayload)) {
            $payload = $livePayload;
        }

        $schoolYear = SchoolYear::find((int) ($record['tahun_ajaran_id'] ?? 0));
        $schoolProfile = SchoolYearDocumentSettings::merge(SchoolProfile::first(), $schoolYear);
        $headmaster = null;
        $currentStudent = Student::findWithRelations((int) ($record['student_id'] ?? 0));

        if (is_array($schoolYear)) {
            $headmasterId = (int) ($schoolYear['kepala_sekolah_id'] ?? 0);
            if ($headmasterId > 0) {
                $headmaster = Teacher::find($headmasterId);
            }
        }

        $verificationUrl = null;
        $token = (string) ($record['signature_token'] ?? '');

        if ($token !== '') {
            $verificationUrl = absolute_url('dokumen/validasi/' . $token);
        }

        $paperSize = strtolower((string) $request->query('paper', 'f4'));
        if (!in_array($paperSize, ['f4', 'a4'], true)) {
            $paperSize = 'f4';
        }

        return $this->render('graduation/certificates/print', [
            'title' => 'Cetak SKL',
            'record' => $record,
            'payload' => $payload,
            'currentStudent' => $currentStudent,
            'schoolYear' => $schoolYear,
            'schoolProfile' => $schoolProfile,
            'headmaster' => $headmaster,
            'verificationUrl' => $verificationUrl,
            'paperSize' => $paperSize,
        ], 'print');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function authorizeView(array $record): ?Response
    {
        $user = auth();

        if ($user === null) {
            return $this->redirect('login');
        }

        $role = (string) ($user['role'] ?? '');

        if (in_array($role, ['admin', 'staff', 'kepala_sekolah'], true)) {
            return null;
        }

        if ($role === 'guru' && AcademicRoleGate::isWakaKurikulum($user)) {
            return null;
        }

        if ($role === 'guru') {
            $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;
            $activeYear = SchoolYear::active();
            $headmasterId = isset($activeYear['kepala_sekolah_id']) ? (int) $activeYear['kepala_sekolah_id'] : 0;
            if ($teacherId > 0 && $teacherId === $headmasterId) {
                return null;
            }

            if ($teacherId > 0) {
                $classId = (int) ($record['class_id'] ?? 0);
                $schoolYearId = (int) ($record['tahun_ajaran_id'] ?? 0);
                $homeroomClasses = Classroom::homeroomClassesForTeacher($teacherId, $schoolYearId > 0 ? $schoolYearId : null);
                foreach ($homeroomClasses as $homeroomClass) {
                    if ((int) ($homeroomClass['id'] ?? 0) === $classId) {
                        return null;
                    }
                }
            }
        }

        if ($role === 'siswa') {
            $studentId = isset($user['student_id']) ? (int) $user['student_id'] : 0;
            $recordStudentId = isset($record['student_id']) ? (int) $record['student_id'] : 0;
            if ($studentId > 0 && $studentId === $recordStudentId) {
                return null;
            }
        }

        return $this->render('reports/sections/error', [
            'title' => 'Cetak SKL',
            'message' => 'Anda tidak memiliki akses untuk melihat SKL ini.',
        ], 'print');
    }

    /**
     * @param array<int, array<string, mixed>> $subjects
     *
     * @return array<int>
     */
    private function resolveSubjectSelection(Request $request, array $subjects): array
    {
        $availableIds = array_values(array_filter(array_map(
            static fn (array $subject): int => (int) ($subject['assignment_id'] ?? 0),
            $subjects
        ), static fn (int $id): bool => $id > 0));

        $selected = $this->normalizeSelectedSubjectIds($request->input('subject_ids', []));

        $selected = array_values(array_intersect($selected, $availableIds));

        if (empty($selected)) {
            return $availableIds;
        }

        return $selected;
    }

    /**
     * @param mixed $input
     *
     * @return array<int>
     */
    private function normalizeSelectedSubjectIds(mixed $input): array
    {
        if (!is_array($input)) {
            $input = [$input];
        }

        $ids = array_map(static fn ($value): int => (int) $value, $input);

        $ids = array_values(array_filter(array_unique($ids), static fn (int $id): bool => $id > 0));

        return $ids;
    }

    /**
     * @param array<int, array<string, mixed>> $students
     * @param array<int, array<string, mixed>> $signatures
     *
     * @return array<string, int>
     */
    private function summarizeSignatures(array $students, array $signatures): array
    {
        $summary = [
            'total' => count($students),
            'requested' => 0,
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
            'not_requested' => 0,
        ];

        foreach ($signatures as $record) {
            $status = (string) ($record['status'] ?? 'pending');
            $summary['requested']++;

            if ($status === 'approved') {
                $summary['approved']++;
            } elseif ($status === 'revoked') {
                $summary['revoked']++;
            } else {
                $summary['pending']++;
            }
        }

        $summary['not_requested'] = max(0, $summary['total'] - $summary['requested']);

        return $summary;
    }

    private function ensureWakaAccess(): ?Response
    {
        $user = auth();

        if ($user === null) {
            return $this->redirect('login');
        }

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        $role = (string) ($user['role'] ?? '');

        if ($role === 'admin' || $role === 'staff') {
            return null;
        }

        if (AcademicRoleGate::isWakaKurikulum($user)) {
            return null;
        }

        Session::flash('error', 'Menu ini hanya dapat diakses oleh Waka Kurikulum.');

        return $this->redirect('dashboard');
    }

    /**
     * @param array<string, mixed> $eligibility
     */
    private function firstBlockingMessage(array $eligibility): string
    {
        foreach (($eligibility['context_issues'] ?? []) as $issue) {
            $issue = trim((string) $issue);
            if ($issue !== '') {
                return $issue;
            }
        }

        foreach (($eligibility['criteria'] ?? []) as $criterion) {
            if (!is_array($criterion) || (bool) ($criterion['passed'] ?? false)) {
                continue;
            }

            $message = trim((string) ($criterion['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return 'SKL belum dapat dicetak karena syarat kelulusan belum terpenuhi.';
    }
}
