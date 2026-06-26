<?php

namespace App\Controllers;

use App\Models\Classroom;
use App\Models\DigitalDocumentSignature;
use App\Models\SchoolYear;
use App\Models\Student;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class DigitalSignatureRequestController extends Controller
{
    protected ?string $layout = null;

    public function requestStudent(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'raport/ttd-digital')) {
            return $response;
        }

        $studentId = (int) $request->input('student_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $semester = (int) $request->input('semester', 0);
        $documentType = trim((string) $request->input('document_type', 'report_card'));

        $redirectUrl = $this->buildRedirectUrl($classId, $studentId, $semester);

        if ($studentId <= 0 || $semester <= 0) {
            Session::flash('error', 'Data siswa atau semester tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $studentClassId = isset($student['kelas_id']) ? (int) $student['kelas_id'] : 0;

        if ($studentClassId <= 0) {
            Session::flash('error', 'Siswa belum terhubung dengan kelas.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($studentClassId);

        if ($class === null) {
            Session::flash('error', 'Data kelas tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $classYearId = isset($class['tahun_ajaran_id']) ? (int) $class['tahun_ajaran_id'] : 0;

        if ($classYearId !== $activeYearId) {
            Session::flash('error', 'Pengajuan TTD hanya diperbolehkan untuk kelas pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($classId > 0 && $classId !== $studentClassId) {
            Session::flash('error', 'Siswa tidak terdaftar pada kelas yang dipilih.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $semester = $this->normalizeSemester($semester);
        $documentType = $documentType !== '' ? $documentType : 'report_card';
        $documentKey = $this->makeDocumentKey($documentType, $studentId, $semester);
        $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

        $payload = $this->buildPayload($documentType, $student, $class, $activeYear, $semester);
        $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, $semester);

        $userId = (int) (auth()['id'] ?? 0);
        $record = DigitalDocumentSignature::ensure(
            $activeYearId,
            $documentType,
            $documentKey,
            $documentTitle,
            $payload,
            $studentId,
            $studentClassId,
            $userId > 0 ? $userId : null,
        );

        if ($record === null) {
            Session::flash('error', 'Gagal mengajukan TTD digital. Coba ulangi beberapa saat lagi.');

            return $this->redirect($redirectUrl);
        }

        $status = (string) ($record['status'] ?? 'pending');

        $messageParts = [];

        if ($existing === null) {
            $messageParts[] = 'TTD digital berhasil diajukan.';
        } else {
            $messageParts[] = 'Permohonan TTD digital diperbarui.';
        }

        if ($status === 'pending') {
            $messageParts[] = 'Status saat ini: menunggu persetujuan kepala sekolah.';
        } elseif ($status === 'approved') {
            $messageParts[] = 'Status saat ini: telah disetujui.';
        } elseif ($status === 'revoked') {
            $messageParts[] = 'Status saat ini: dicabut, ajukan ulang setelah perbaikan.';
        }

        Session::flash('success', implode(' ', $messageParts));

        return $this->redirect($redirectUrl);
    }

    public function requestClass(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'raport/ttd-digital')) {
            return $response;
        }

        $classId = (int) $request->input('class_id', 0);
        $semester = (int) $request->input('semester', 0);
        $documentType = trim((string) $request->input('document_type', 'report_card'));

        $redirectUrl = $this->buildRedirectUrl($classId, null, $semester);

        if ($classId <= 0 || $semester <= 0) {
            Session::flash('error', 'Data kelas atau semester tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Data kelas tidak ditemukan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $semester = $this->normalizeSemester($semester);
        $documentType = $documentType !== '' ? $documentType : 'report_card';

        $students = Student::byClass($classId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $processed = 0;
        $created = 0;
        $pending = 0;
        $approved = 0;
        $revoked = 0;

        $userId = (int) (auth()['id'] ?? 0);

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $documentKey = $this->makeDocumentKey($documentType, $studentId, $semester);
            $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

            $payload = $this->buildPayload($documentType, $student, $class, $activeYear, $semester);
            $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, $semester);

            $record = DigitalDocumentSignature::ensure(
                $activeYearId,
                $documentType,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId,
                $userId > 0 ? $userId : null,
            );

            if ($record === null) {
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
            Session::flash('error', 'Tidak ada siswa yang dapat diajukan untuk TTD digital.');

            return $this->redirect($redirectUrl);
        }

        $messages = [];
        $messages[] = sprintf('%d siswa diproses.', $processed);

        if ($created > 0) {
            $messages[] = sprintf('%d siswa baru diajukan.', $created);
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

        Session::flash('success', implode(' ', $messages));

        return $this->redirect($redirectUrl);
    }

    public function requestTranscript(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/transkrip/ttd-digital/request')) {
            return $response;
        }

        $studentId = (int) $request->input('student_id', 0);
        $classId = (int) $request->input('class_id', 0);
        $scope = strtolower(trim((string) $request->input('scope', 'all')));
        $scope = in_array($scope, ['all', 'grade12'], true) ? $scope : 'all';

        $redirectParams = [];

        if ($classId > 0) {
            $redirectParams['kelas_id'] = $classId;
        }

        if ($studentId > 0) {
            $redirectParams['siswa_id'] = $studentId;
        }

        $redirectParams['scope'] = $scope;

        $redirectUrl = 'walikelas/transkrip' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

        if ($studentId <= 0 || $classId <= 0) {
            Session::flash('error', 'Data siswa atau kelas tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Data kelas tidak ditemukan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($student['kelas_id'] ?? 0) !== $classId) {
            Session::flash('error', 'Siswa tidak terdaftar pada kelas yang dipilih.');

            return $this->redirect($redirectUrl);
        }

        $documentType = 'student_transcript';
        $documentKey = $this->makeDocumentKey($documentType, $studentId, 0);
        $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

        $payload = $this->buildPayload($documentType, $student, $class, $activeYear, 0);
        $payload['scope'] = $scope === 'grade12' ? 'Transkrip nilai kelas 12' : 'Transkrip nilai tingkat 10-12';

        $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, 0);

        $userId = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::ensure(
            $activeYearId,
            $documentType,
            $documentKey,
            $documentTitle,
            $payload,
            $studentId,
            $classId,
            $userId > 0 ? $userId : null,
        );

        if ($record === null) {
            Session::flash('error', 'Gagal mengajukan TTD digital. Coba ulangi beberapa saat lagi.');

            return $this->redirect($redirectUrl);
        }

        $status = (string) ($record['status'] ?? 'pending');

        $messageParts = [];

        if ($existing === null) {
            $messageParts[] = 'TTD digital transkrip berhasil diajukan.';
        } else {
            $messageParts[] = 'Permohonan TTD digital transkrip diperbarui.';
        }

        if ($status === 'pending') {
            $messageParts[] = 'Status saat ini: menunggu persetujuan kepala sekolah.';
        } elseif ($status === 'approved') {
            $messageParts[] = 'Status saat ini: telah disetujui.';
        } elseif ($status === 'revoked') {
            $messageParts[] = 'Status saat ini: dicabut, ajukan ulang setelah perbaikan.';
        }

        Session::flash('success', implode(' ', $messageParts));

        return $this->redirect($redirectUrl);
    }

    public function requestTranscriptClass(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/transkrip/ttd-digital/request-class')) {
            return $response;
        }

        $classId = (int) $request->input('class_id', 0);

        $redirectParams = [];
        if ($classId > 0) {
            $redirectParams['kelas_id'] = $classId;
        }
        $redirectUrl = 'walikelas/transkrip' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

        if ($classId <= 0) {
            Session::flash('error', 'Data kelas tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Data kelas tidak ditemukan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $students = Student::byClass($classId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $processed = 0;
        $created = 0;
        $pending = 0;
        $approved = 0;
        $revoked = 0;

        $userId = (int) (auth()['id'] ?? 0);
        $documentType = 'student_transcript';

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $documentKey = sprintf('transcript:%d', $studentId);
            $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

            $payload = $this->buildPayload($documentType, $student, $class, $activeYear, 0);
            $payload['scope'] = 'Transkrip nilai kelas 12';
            $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, 0);

            $record = DigitalDocumentSignature::ensure(
                $activeYearId,
                $documentType,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId,
                $userId > 0 ? $userId : null,
            );

            if ($record === null) {
                continue;
            }

            $processed++;
            $status = (string) ($record['status'] ?? 'pending');

            if ($existing === null) {
                $created++;
            }

            if ($status === 'approved') {
                $approved++;
            } elseif ($status === 'revoked') {
                $revoked++;
            } else {
                $pending++;
            }
        }

        if ($processed === 0) {
            Session::flash('error', 'Tidak ada siswa yang dapat diajukan.');

            return $this->redirect($redirectUrl);
        }

        $messages = [];
        $messages[] = sprintf('%d siswa diproses.', $processed);

        if ($created > 0) {
            $messages[] = sprintf('%d siswa baru diajukan.', $created);
        }

        if ($pending > 0) {
            $messages[] = sprintf('%d siswa menunggu persetujuan.', $pending);
        }

        if ($approved > 0) {
            $messages[] = sprintf('%d siswa sudah disetujui.', $approved);
        }

        if ($revoked > 0) {
            $messages[] = sprintf('%d siswa berstatus dicabut.', $revoked);
        }

        Session::flash('success', implode(' ', $messages));

        return $this->redirect($redirectUrl);
    }

    public function requestP5(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/ttd-digital/request')) {
            return $response;
        }

        $studentId = (int) $request->input('student_id', 0);
        $classId = (int) $request->input('class_id', 0);

        $redirectParams = [];

        if ($classId > 0) {
            $redirectParams['kelas_id'] = $classId;
        }

        if ($studentId > 0) {
            $redirectParams['siswa_id'] = $studentId;
        }

        $redirectUrl = 'walikelas/p5/cetak' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

        if ($studentId <= 0 || $classId <= 0) {
            Session::flash('error', 'Data siswa atau kelas tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Data kelas tidak ditemukan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $student = Student::findWithRelations($studentId);

        if ($student === null) {
            Session::flash('error', 'Data siswa tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($student['kelas_id'] ?? 0) !== $classId) {
            Session::flash('error', 'Siswa tidak terdaftar pada kelas yang dipilih.');

            return $this->redirect($redirectUrl);
        }

        $documentType = 'p5_report';
        $documentKey = sprintf('%s:%d', $documentType, $studentId);
        $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

        $payload = $this->buildPayload($documentType, $student, $class, $activeYear, 0);
        $payload['scope'] = 'Rapor Projek Profil Pelajar Pancasila';

        $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, 0);

        $userId = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::ensure(
            $activeYearId,
            $documentType,
            $documentKey,
            $documentTitle,
            $payload,
            $studentId,
            $classId,
            $userId > 0 ? $userId : null,
        );

        if ($record === null) {
            Session::flash('error', 'Gagal mengajukan TTD digital. Coba ulangi beberapa saat lagi.');

            return $this->redirect($redirectUrl);
        }

        $status = (string) ($record['status'] ?? 'pending');

        $messageParts = [];

        if ($existing === null) {
            $messageParts[] = 'TTD digital rapor P5 berhasil diajukan.';
        } else {
            $messageParts[] = 'Permohonan TTD digital rapor P5 diperbarui.';
        }

        if ($status === 'pending') {
            $messageParts[] = 'Status saat ini: menunggu persetujuan kepala sekolah.';
        } elseif ($status === 'approved') {
            $messageParts[] = 'Status saat ini: telah disetujui.';
        } elseif ($status === 'revoked') {
            $messageParts[] = 'Status saat ini: dicabut, ajukan ulang setelah perbaikan.';
        }

        Session::flash('success', implode(' ', $messageParts));

        return $this->redirect($redirectUrl);
    }

    public function requestP5Class(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'walikelas/p5/ttd-digital/request-class')) {
            return $response;
        }

        $classId = (int) $request->input('class_id', 0);

        $redirectParams = [];
        if ($classId > 0) {
            $redirectParams['kelas_id'] = $classId;
        }
        $redirectUrl = 'walikelas/p5/cetak' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

        if ($classId <= 0) {
            Session::flash('error', 'Data kelas tidak valid.');

            return $this->redirect($redirectUrl);
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan untuk tahun ajaran ini.');

            return $this->redirect($redirectUrl);
        }

        $class = Classroom::findWithRelations($classId);

        if ($class === null || (int) ($class['tahun_ajaran_id'] ?? 0) !== $activeYearId) {
            Session::flash('error', 'Data kelas tidak ditemukan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        if ($response = $this->authorizeRequester($class)) {
            return $response;
        }

        $students = Student::byClass($classId, $activeYearId);

        if (empty($students)) {
            Session::flash('error', 'Tidak ada siswa pada kelas ini.');

            return $this->redirect($redirectUrl);
        }

        $processed = 0;
        $created = 0;
        $pending = 0;
        $approved = 0;
        $revoked = 0;

        $userId = (int) (auth()['id'] ?? 0);

        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $documentType = 'p5_report';
            $documentKey = sprintf('%s:%d', $documentType, $studentId);
            $existing = DigitalDocumentSignature::findByDocument($activeYearId, $documentType, $documentKey);

            $payload = $this->buildPayload($documentType, $student, $class, $activeYear, 0);
            $payload['scope'] = 'Rapor Projek Profil Pelajar Pancasila';
            $documentTitle = $this->makeDocumentTitle($documentType, $student, $activeYear, 0);

            $record = DigitalDocumentSignature::ensure(
                $activeYearId,
                $documentType,
                $documentKey,
                $documentTitle,
                $payload,
                $studentId,
                $classId,
                $userId > 0 ? $userId : null,
            );

            if ($record === null) {
                continue;
            }

            $processed++;
            $status = (string) ($record['status'] ?? 'pending');
            if ($existing === null) {
                $created++;
            }
            if ($status === 'approved') {
                $approved++;
            } elseif ($status === 'revoked') {
                $revoked++;
            } else {
                $pending++;
            }
        }

        if ($processed === 0) {
            Session::flash('error', 'Tidak ada siswa yang dapat diajukan.');

            return $this->redirect($redirectUrl);
        }

        $messages = [];
        $messages[] = sprintf('%d siswa diproses.', $processed);
        if ($created > 0) {
            $messages[] = sprintf('%d siswa baru diajukan.', $created);
        }
        if ($pending > 0) {
            $messages[] = sprintf('%d siswa menunggu persetujuan.', $pending);
        }
        if ($approved > 0) {
            $messages[] = sprintf('%d siswa sudah disetujui.', $approved);
        }
        if ($revoked > 0) {
            $messages[] = sprintf('%d siswa berstatus dicabut.', $revoked);
        }

        Session::flash('success', implode(' ', $messages));

        return $this->redirect($redirectUrl);
    }

    private function buildRedirectUrl(?int $classId, ?int $studentId, ?int $semester): string
    {
        $params = [];

        if ($classId !== null && $classId > 0) {
            $params['kelas_id'] = $classId;
        }

        if ($studentId !== null && $studentId > 0) {
            $params['siswa_id'] = $studentId;
        }

        if ($semester !== null && in_array((int) $semester, [1, 2], true)) {
            $params['semester'] = (int) $semester;
        }

        $query = !empty($params) ? '?' . http_build_query($params) : '';

        return 'raport/cetak' . $query;
    }

    private function normalizeSemester(int $semester): int
    {
        return in_array($semester, [1, 2], true) ? $semester : 1;
    }

    /**
     * @param array<string, mixed> $class
     */
    private function authorizeRequester(array $class): ?Response
    {
        $user = auth();

        if ($user === null) {
            return $this->redirect('login');
        }

        $role = (string) ($user['role'] ?? '');

        if ($role === 'admin') {
            return null;
        }

        if ($role === 'guru') {
            $teacherId = isset($user['teacher_id']) ? (int) $user['teacher_id'] : 0;

            if ($teacherId > 0 && $teacherId === (int) ($class['wali_kelas_id'] ?? 0)) {
                return null;
            }
        }

        Session::flash('error', 'Anda tidak memiliki hak untuk mengajukan TTD digital untuk kelas ini.');

        return $this->redirect('dashboard');
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed> $class
     * @param array<string, mixed> $schoolYear
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $documentType, array $student, array $class, array $schoolYear, int $semester): array
    {
        $payload = [
            'document_type' => $documentType,
            'school_year_id' => (int) ($schoolYear['id'] ?? 0),
            'school_year_name' => (string) ($schoolYear['nama'] ?? ''),
            'semester' => in_array($documentType, ['student_transcript', 'p5_report'], true) ? null : $semester,
            'student' => [
                'id' => (int) ($student['id'] ?? 0),
                'name' => (string) ($student['nama'] ?? ''),
                'nisn' => (string) ($student['nisn'] ?? ''),
                'nipd' => (string) ($student['nipd'] ?? ''),
            ],
            'class' => [
                'id' => (int) ($class['id'] ?? 0),
                'name' => (string) ($class['nama'] ?? ''),
                'level' => (string) ($class['tingkat'] ?? ''),
            ],
            'requested_at' => date('c'),
        ];

        return $payload;
    }

    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed> $schoolYear
     */
    private function makeDocumentTitle(string $documentType, array $student, array $schoolYear, int $semester): string
    {
        $studentName = (string) ($student['nama'] ?? 'Siswa');
        $schoolYearName = (string) ($schoolYear['nama'] ?? '');
        $periodSuffix = $schoolYearName !== '' ? ' (' . $schoolYearName . ')' : '';

        if ($documentType === 'p5_report') {
            return sprintf('Rapor P5%s - %s', $periodSuffix, $studentName);
        }

        if ($documentType === 'midterm_report') {
            return sprintf('Laporan Tengah Semester %d%s - %s', $semester, $periodSuffix, $studentName);
        }

        if ($documentType === 'student_transcript') {
            return sprintf('Transkrip Nilai%s - %s', $periodSuffix, $studentName);
        }

        return sprintf('Raport Semester %d%s - %s', $semester, $periodSuffix, $studentName);
    }

    private function makeDocumentKey(string $documentType, int $studentId, int $semester): string
    {
        switch ($documentType) {
            case 'midterm_report':
                return sprintf('midterm:%d:%d', $studentId, $semester);
            case 'student_transcript':
                return sprintf('transcript:%d', $studentId);
            default:
                return sprintf('raport:%d:%d', $studentId, $semester);
        }
    }

}
