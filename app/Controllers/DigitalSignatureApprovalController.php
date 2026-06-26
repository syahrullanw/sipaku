<?php

namespace App\Controllers;

use App\Models\DigitalDocumentSignature;
use App\Models\OutgoingLetter;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Services\GraduationCertificateService;
use App\Services\OutgoingLetterPdfService;
use App\Support\DigitalDocumentTypes;
use App\Services\TeacherAssignmentLetterService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use function asset;

class DigitalSignatureApprovalController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        $statusFilter = strtolower((string) $request->query('status', 'pending'));
        $allowedStatuses = ['pending', 'approved', 'revoked', 'all'];

        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $statusQuery = $statusFilter === 'all' ? null : $statusFilter;

        $allRecords = DigitalDocumentSignature::listForYear((int) $activeYear['id']);
        $letterTypes = DigitalDocumentTypes::letterTypes();

        $allRecords = array_values(array_filter(
            $allRecords,
            static function (array $record) use ($letterTypes): bool {
                $type = (string) ($record['document_type'] ?? '');

                return !in_array($type, $letterTypes, true)
                    && $type !== GraduationCertificateService::DOCUMENT_TYPE;
            }
        ));

        if ($statusQuery === null) {
            $records = $allRecords;
        } else {
            $records = array_values(array_filter(
                $allRecords,
                static function (array $record) use ($statusQuery): bool {
                    return ($record['status'] ?? null) === $statusQuery;
                }
            ));
        }

        foreach ($records as &$record) {
            $payload = [];
            if (isset($record['payload']) && is_string($record['payload'])) {
                $decoded = json_decode($record['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $record['payload_data'] = $payload;
        }
        unset($record);

        $classSummaries = [];

        foreach ($allRecords as $record) {
            $classId = isset($record['class_id']) ? (int) $record['class_id'] : 0;

            if ($classId <= 0) {
                continue;
            }

            if (!isset($classSummaries[$classId])) {
                $classSummaries[$classId] = [
                    'class_id' => $classId,
                    'class_name' => (string) ($record['class_name'] ?? '-'),
                    'class_level' => (string) ($record['class_level'] ?? ''),
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'revoked' => 0,
                ];
            }

            $classSummaries[$classId]['total']++;

            $status = (string) ($record['status'] ?? 'pending');
            if ($status === 'approved') {
                $classSummaries[$classId]['approved']++;
            } elseif ($status === 'revoked') {
                $classSummaries[$classId]['revoked']++;
            } else {
                $classSummaries[$classId]['pending']++;
            }
        }

        $classSummaries = array_values($classSummaries);

        $headmasterName = $this->resolveHeadmasterName($activeYear);

        return $this->render('digital-signatures/index', [
            'title' => 'Persetujuan TTD Digital',
            'pageTitle' => 'Persetujuan TTD Digital',
            'activeMenu' => 'digital-signatures',
            'headmasterName' => $headmasterName,
            'activeYear' => $activeYear,
            'records' => $records,
            'statusFilter' => $statusFilter,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
            'classSummaries' => $classSummaries,
        ]);
    }

    public function skl(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        $statusFilter = strtolower((string) $request->query('status', 'pending'));
        $allowedStatuses = ['pending', 'approved', 'revoked', 'all'];

        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $statusQuery = $statusFilter === 'all' ? null : $statusFilter;

        $records = DigitalDocumentSignature::listForYear((int) $activeYear['id']);

        $records = array_values(array_filter(
            $records,
            static function (array $record): bool {
                return (string) ($record['document_type'] ?? '') === GraduationCertificateService::DOCUMENT_TYPE;
            }
        ));

        $statusSummary = [
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
        ];

        foreach ($records as &$record) {
            $payload = [];
            if (isset($record['payload']) && is_string($record['payload'])) {
                $decoded = json_decode($record['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $record['payload_data'] = $payload;

            $status = (string) ($record['status'] ?? 'pending');
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            }
        }
        unset($record);

        if ($statusQuery !== null) {
            $records = array_values(array_filter(
                $records,
                static function (array $record) use ($statusQuery): bool {
                    return (string) ($record['status'] ?? '') === $statusQuery;
                }
            ));
        }

        $classSummaries = [];

        foreach ($records as $record) {
            $classId = isset($record['class_id']) ? (int) $record['class_id'] : 0;

            if ($classId <= 0) {
                continue;
            }

            if (!isset($classSummaries[$classId])) {
                $classSummaries[$classId] = [
                    'class_id' => $classId,
                    'class_name' => (string) ($record['class_name'] ?? '-'),
                    'class_level' => (string) ($record['class_level'] ?? ''),
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'revoked' => 0,
                ];
            }

            $classSummaries[$classId]['total']++;

            $status = (string) ($record['status'] ?? 'pending');
            if ($status === 'approved') {
                $classSummaries[$classId]['approved']++;
            } elseif ($status === 'revoked') {
                $classSummaries[$classId]['revoked']++;
            } else {
                $classSummaries[$classId]['pending']++;
            }
        }

        $classSummaries = array_values($classSummaries);

        $headmasterName = $this->resolveHeadmasterName($activeYear);

        return $this->render('digital-signatures/skl', [
            'title' => 'Persetujuan SKL',
            'pageTitle' => 'Persetujuan SKL',
            'activeMenu' => 'graduation-approvals',
            'headmasterName' => $headmasterName,
            'activeYear' => $activeYear,
            'records' => $records,
            'statusFilter' => $statusFilter,
            'statusSummary' => $statusSummary,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
            'classSummaries' => $classSummaries,
        ]);
    }

    public function transkrip(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        $statusFilter = strtolower((string) $request->query('status', 'pending'));
        $allowedStatuses = ['pending', 'approved', 'revoked', 'all'];

        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $statusQuery = $statusFilter === 'all' ? null : $statusFilter;

        $allRecords = DigitalDocumentSignature::listForYear((int) $activeYear['id']);

        $allRecords = array_values(array_filter(
            $allRecords,
            static function (array $record): bool {
                return (string) ($record['document_type'] ?? '') === 'student_transcript';
            }
        ));

        $statusSummary = [
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
        ];

        foreach ($allRecords as &$record) {
            $payload = [];
            if (isset($record['payload']) && is_string($record['payload'])) {
                $decoded = json_decode($record['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $record['payload_data'] = $payload;

            $status = (string) ($record['status'] ?? 'pending');
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            }
        }
        unset($record);

        if ($statusQuery === null) {
            $records = $allRecords;
        } else {
            $records = array_values(array_filter(
                $allRecords,
                static function (array $record) use ($statusQuery): bool {
                    return ($record['status'] ?? null) === $statusQuery;
                }
            ));
        }

        $classSummaries = [];

        foreach ($allRecords as $record) {
            $classId = isset($record['class_id']) ? (int) $record['class_id'] : 0;

            if ($classId <= 0) {
                continue;
            }

            if (!isset($classSummaries[$classId])) {
                $classSummaries[$classId] = [
                    'class_id' => $classId,
                    'class_name' => (string) ($record['class_name'] ?? '-'),
                    'class_level' => (string) ($record['class_level'] ?? ''),
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'revoked' => 0,
                ];
            }

            $classSummaries[$classId]['total']++;

            $status = (string) ($record['status'] ?? 'pending');
            if ($status === 'approved') {
                $classSummaries[$classId]['approved']++;
            } elseif ($status === 'revoked') {
                $classSummaries[$classId]['revoked']++;
            } else {
                $classSummaries[$classId]['pending']++;
            }
        }

        $classSummaries = array_values($classSummaries);

        $headmasterName = $this->resolveHeadmasterName($activeYear);

        return $this->render('digital-signatures/transkrip', [
            'title' => 'Persetujuan Transkrip Nilai',
            'pageTitle' => 'Persetujuan Transkrip Nilai',
            'activeMenu' => 'digital-signatures-transkrip',
            'headmasterName' => $headmasterName,
            'activeYear' => $activeYear,
            'records' => $records,
            'allRecords' => $allRecords,
            'statusFilter' => $statusFilter,
            'statusSummary' => $statusSummary,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
            'classSummaries' => $classSummaries,
        ]);
    }

    public function letters(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        $statusFilter = strtolower((string) $request->query('status', 'pending'));
        $allowedStatuses = ['pending', 'approved', 'revoked', 'all'];

        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $letterTypes = DigitalDocumentTypes::letterTypes();

        $records = DigitalDocumentSignature::listForYear((int) $activeYear['id']);

        $letterRecords = array_values(array_filter(
            $records,
            static function (array $record) use ($letterTypes): bool {
                $type = (string) ($record['document_type'] ?? '');

                return in_array($type, $letterTypes, true);
            }
        ));

        $statusSummary = [
            'pending' => 0,
            'approved' => 0,
            'revoked' => 0,
        ];

        foreach ($letterRecords as $record) {
            $status = (string) ($record['status'] ?? 'pending');
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            }
        }

        $filteredRecords = $letterRecords;

        if ($statusFilter !== 'all') {
            $filteredRecords = array_values(array_filter(
                $filteredRecords,
                static function (array $record) use ($statusFilter): bool {
                    return (string) ($record['status'] ?? '') === $statusFilter;
                }
            ));
        }

        foreach ($filteredRecords as &$record) {
            $payload = [];

            if (isset($record['payload']) && is_string($record['payload'])) {
                $decoded = json_decode($record['payload'], true);

                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $record['payload_data'] = $payload;
            $record['pdf_meta'] = null;

            if ((string) ($record['document_type'] ?? '') === 'outgoing_letter') {
                $documentKey = (string) ($record['document_key'] ?? '');
                $letterId = str_starts_with($documentKey, 'letter:') ? (int) substr($documentKey, strlen('letter:')) : 0;

                if ($letterId > 0) {
                    $letter = OutgoingLetter::find($letterId);

                    if ($letter !== null) {
                        $pdfPath = (string) ($letter['pdf_path'] ?? '');
                        $signedPath = (string) ($letter['pdf_signed_path'] ?? '');
                        $options = OutgoingLetterPdfService::decodeSignatureOptions($letter['pdf_signature_options'] ?? null);

                        $record['pdf_meta'] = [
                            'path' => $pdfPath !== '' ? $pdfPath : null,
                            'signed_path' => $signedPath !== '' ? $signedPath : null,
                            'url' => $pdfPath !== '' ? asset($pdfPath) : null,
                            'signed_url' => $signedPath !== '' ? asset($signedPath) : null,
                            'options' => $options,
                        ];
                    }
                }
            }

            if ($record['pdf_meta'] === null && isset($payload['pdf']) && is_array($payload['pdf'])) {
                $record['pdf_meta'] = $payload['pdf'];
            }

            $letterPayload = isset($payload['letter']) && is_array($payload['letter']) ? $payload['letter'] : [];

            $record['letter'] = [
                'number' => $letterPayload['number'] ?? null,
                'subject' => $letterPayload['subject'] ?? null,
                'place' => $letterPayload['place'] ?? null,
                'sign_date' => $letterPayload['sign_date'] ?? null,
                'sign_date_formatted' => TeacherAssignmentLetterService::formatDate($letterPayload['sign_date'] ?? null),
                'effective_start' => $letterPayload['effective_start'] ?? null,
                'effective_start_formatted' => TeacherAssignmentLetterService::formatDate($letterPayload['effective_start'] ?? null),
                'effective_end' => $letterPayload['effective_end'] ?? null,
                'effective_end_formatted' => TeacherAssignmentLetterService::formatDate($letterPayload['effective_end'] ?? null),
            ];

            $record['requested_at_formatted'] = TeacherAssignmentLetterService::formatDateTime($record['created_at'] ?? $record['updated_at'] ?? null);
            $record['approved_at_formatted'] = TeacherAssignmentLetterService::formatDateTime($record['approved_at'] ?? null);
            $record['updated_at_formatted'] = TeacherAssignmentLetterService::formatDateTime($record['updated_at'] ?? null);

            $token = (string) ($record['signature_token'] ?? '');
            $record['verification_url'] = $token !== '' && (string) ($record['status'] ?? '') === 'approved'
                ? absolute_url('persuratan/validasi/' . $token)
                : null;
        }

        unset($record);

        $headmasterName = $this->resolveHeadmasterName($activeYear);

        return $this->render('digital-signatures/letters', [
            'title' => 'Persetujuan Persuratan',
            'pageTitle' => 'Persetujuan Persuratan',
            'activeMenu' => 'digital-signatures-letters',
            'headmasterName' => $headmasterName,
            'activeYear' => $activeYear,
            'records' => $filteredRecords,
            'statusFilter' => $statusFilter,
            'digitalSignatureEnabled' => (int) ($activeYear['digital_signature_enabled'] ?? 0) === 1,
            'statusSummary' => $statusSummary,
        ]);
    }

    public function approve(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'kepala-sekolah/ttd-digital');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan oleh admin.');

            return $this->redirect($redirectPath);
        }

        $record = DigitalDocumentSignature::findWithRelations($id);

        if ($record === null || (int) ($record['tahun_ajaran_id'] ?? 0) !== (int) ($activeYear['id'] ?? 0)) {
            Session::flash('error', 'Dokumen tidak ditemukan atau tidak termasuk tahun ajaran aktif.');

            return $this->redirect($redirectPath);
        }

        if (($record['status'] ?? 'pending') !== 'pending') {
            Session::flash('error', 'Dokumen ini sudah diproses sebelumnya.');

            return $this->redirect($redirectPath);
        }

        if ((string) ($record['document_type'] ?? '') === GraduationCertificateService::DOCUMENT_TYPE) {
            $eligibility = GraduationCertificateService::evaluateStudentEligibility(
                (int) ($record['student_id'] ?? 0),
                (int) ($record['tahun_ajaran_id'] ?? 0),
                $record
            );

            if (!($eligibility['can_approve_signature'] ?? false)) {
                Session::flash('error', 'SKL belum dapat disetujui: ' . $this->firstBlockingMessage($eligibility));

                return $this->redirect($redirectPath);
            }
        }

        $note = trim((string) $request->input('approval_note', ''));

        $token = $this->generateUniqueToken();
        $approverId = (int) (auth()['id'] ?? 0);

        $updates = [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $approverId > 0 ? $approverId : null,
            'signature_token' => $token,
            'approval_note' => $note !== '' ? $note : null,
        ];

        DigitalDocumentSignature::updateById($id, $updates);

        $signedPdfPath = null;
        $requiresPdfSignature = false;

        if ((string) ($record['document_type'] ?? '') === 'outgoing_letter') {
            $documentKey = (string) ($record['document_key'] ?? '');
            $letterId = str_starts_with($documentKey, 'letter:') ? (int) substr($documentKey, strlen('letter:')) : 0;

            if ($letterId > 0) {
                $letter = OutgoingLetter::find($letterId);

                if ($letter !== null) {
                    $pdfPath = (string) ($letter['pdf_path'] ?? '');
                    $pdfOptions = OutgoingLetterPdfService::decodeSignatureOptions($letter['pdf_signature_options'] ?? null);
                    $requiresPdfSignature = $pdfPath !== '' || $pdfOptions !== null;
                    $verificationUrl = absolute_url('persuratan/validasi/' . $token);
                    $signedPdfPath = OutgoingLetterPdfService::applySignature(
                        $letter,
                        $verificationUrl,
                        isset($letter['pdf_signed_path']) ? (string) $letter['pdf_signed_path'] : null
                    );

                    if ($signedPdfPath !== null) {
                        OutgoingLetter::updateById($letterId, [
                            'pdf_signed_path' => $signedPdfPath,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        }

        Session::flash('success', 'Dokumen berhasil disetujui.');

        if ((string) ($record['document_type'] ?? '') === 'outgoing_letter' && $signedPdfPath === null && $requiresPdfSignature) {
            Session::flash('warning', 'Dokumen disetujui, namun PDF belum dapat disematkan QR. Periksa kembali berkas PDF yang diunggah.');
        }

        return $this->redirect($redirectPath);
    }

    public function reset(Request $request, int $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'kepala-sekolah/ttd-digital');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        $record = DigitalDocumentSignature::findWithRelations($id);

        if ($record === null || (int) ($record['tahun_ajaran_id'] ?? 0) !== (int) ($activeYear['id'] ?? 0)) {
            Session::flash('error', 'Dokumen tidak ditemukan atau tidak termasuk tahun ajaran aktif.');

            return $this->redirect($redirectPath);
        }

        if (($record['status'] ?? '') !== 'approved') {
            Session::flash('error', 'Dokumen belum disetujui atau sudah berada pada status pending.');

            return $this->redirect($redirectPath);
        }

        DigitalDocumentSignature::updateById($id, [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'signature_token' => null,
            'approval_note' => null,
        ]);

        if ((string) ($record['document_type'] ?? '') === 'outgoing_letter') {
            $documentKey = (string) ($record['document_key'] ?? '');
            $letterId = str_starts_with($documentKey, 'letter:') ? (int) substr($documentKey, strlen('letter:')) : 0;

            if ($letterId > 0) {
                $letter = OutgoingLetter::find($letterId);

                if ($letter !== null) {
                    $signedPath = (string) ($letter['pdf_signed_path'] ?? '');

                    if ($signedPath !== '') {
                        $absolute = public_path($signedPath);

                        if (is_file($absolute)) {
                            @unlink($absolute);
                        }
                    }

                    OutgoingLetter::updateById($letterId, [
                        'pdf_signed_path' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        Session::flash('success', 'Persetujuan ditarik dan dokumen kembali menunggu persetujuan.');

        return $this->redirect($redirectPath);
    }

    public function approveClass(Request $request, int $classId): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $redirectPath = $this->resolveRedirectPath($request, 'kepala-sekolah/ttd-digital');

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $activeYear = SchoolYear::active();

        if ($activeYear === null) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        if ($response = $this->authorizeHeadmaster($activeYear)) {
            return $response;
        }

        if ((int) ($activeYear['digital_signature_enabled'] ?? 0) !== 1) {
            Session::flash('error', 'TTD digital belum diaktifkan oleh admin.');

            return $this->redirect($redirectPath);
        }

        $classId = max(0, $classId);

        if ($classId <= 0) {
            Session::flash('error', 'Kelas tidak valid.');

            return $this->redirect($redirectPath);
        }

        $documentType = trim((string) $request->input('document_type', 'report_card'));
        if ($documentType === '') {
            $documentType = 'report_card';
        }

        $pendingIds = DigitalDocumentSignature::pendingIdsByClass(
            (int) ($activeYear['id'] ?? 0),
            $classId,
            $documentType
        );

        if (empty($pendingIds)) {
            Session::flash('error', 'Tidak ada dokumen menunggu persetujuan untuk kelas ini.');

            return $this->redirect($redirectPath);
        }

        $approverId = (int) (auth()['id'] ?? 0);
        $approvedCount = 0;
        $skippedCount = 0;

        foreach ($pendingIds as $id) {
            $record = DigitalDocumentSignature::findWithRelations($id);
            if ($record === null) {
                $skippedCount++;
                continue;
            }

            if ((string) ($record['document_type'] ?? '') === GraduationCertificateService::DOCUMENT_TYPE) {
                $eligibility = GraduationCertificateService::evaluateStudentEligibility(
                    (int) ($record['student_id'] ?? 0),
                    (int) ($record['tahun_ajaran_id'] ?? 0),
                    $record
                );

                if (!($eligibility['can_approve_signature'] ?? false)) {
                    $skippedCount++;
                    continue;
                }
            }

            $token = $this->generateUniqueToken();

            DigitalDocumentSignature::updateById($id, [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $approverId > 0 ? $approverId : null,
                'signature_token' => $token,
                'approval_note' => null,
            ]);

            $approvedCount++;
        }

        if ($approvedCount === 0) {
            Session::flash('error', 'Tidak ada dokumen yang dapat disetujui. Pastikan syarat SKL sudah terpenuhi.');

            return $this->redirect($redirectPath);
        }

        $message = sprintf('Berhasil menyetujui %d dokumen TTD digital pada kelas ini.', $approvedCount);
        if ($skippedCount > 0) {
            $message .= sprintf(' %d dokumen dilewati karena syarat belum terpenuhi.', $skippedCount);
        }

        Session::flash('success', $message);

        return $this->redirect($redirectPath);
    }

    /**
     * @param array<string, mixed> $activeYear
     */
    private function authorizeHeadmaster(array $activeYear): ?Response
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
            $headmasterId = isset($activeYear['kepala_sekolah_id']) ? (int) $activeYear['kepala_sekolah_id'] : 0;

            if ($teacherId > 0 && $teacherId === $headmasterId) {
                return null;
            }
        }

        Session::flash('error', 'Fitur ini hanya dapat diakses oleh kepala sekolah.');

        return $this->redirect('dashboard');
    }

    /**
     * @param array<string, mixed> $activeYear
     */
    private function resolveHeadmasterName(array $activeYear): string
    {
        $headmasterId = isset($activeYear['kepala_sekolah_id']) ? (int) $activeYear['kepala_sekolah_id'] : 0;

        if ($headmasterId <= 0) {
            return '';
        }

        $teacher = Teacher::find($headmasterId);

        if ($teacher === null) {
            return '';
        }

        return (string) ($teacher['nama'] ?? '');
    }

    private function resolveRedirectPath(Request $request, string $default): string
    {
        $candidate = trim((string) $request->input('redirect_to', ''));

        if ($candidate === '') {
            return $default;
        }

        if (!preg_match('#^[a-z0-9\-/]+(\?[a-z0-9_\-=&%]+)?$#i', $candidate)) {
            return $default;
        }

        return $candidate;
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24));
            $exists = DigitalDocumentSignature::findByToken($token);
        } while ($exists !== null);

        return $token;
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

        return 'syarat kelulusan belum terpenuhi.';
    }
}
