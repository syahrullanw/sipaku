<?php

namespace App\Controllers;

use App\Models\DigitalDocumentSignature;
use App\Models\Classroom;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterAttachment;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\SubjectTeacher;
use App\Models\Teacher;
use App\Models\User;
use App\Services\OutgoingLetterPdfService;
use App\Services\ManagedFileStorage;
use App\Services\TeacherAssignmentLetterService;
use App\Support\AcademicRoleGate;
use App\Support\DigitalDocumentTypes;
use App\Support\LetterCatalog;
use App\Support\LetterNumber;
use App\Support\LetterTemplateParser;
use App\Support\SchoolYearContext;
use Core\Controller;
use Core\Csrf;
use Core\Database;
use Core\Log;
use Core\Request;
use Core\Response;
use Core\Session;
use DateTimeImmutable;
use function asset;
use function base_url;

class TataUsahaController extends Controller
{
    private const MAX_OUTGOING_ATTACHMENTS = 5;

    protected ?string $layout = 'admin';

    public function letters(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $statusFilter = strtolower((string) $request->query('status', 'pending'));
        $allowedStatuses = ['pending', 'approved', 'revoked', 'all'];

        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'pending';
        }

        $yearOptions = SchoolYearContext::options();
        $year = null;

        if ($selectedYearId > 0) {
            $year = SchoolYear::find($selectedYearId);
        }

        if ($year === null) {
            $year = SchoolYearContext::resolve();
        }

        if ($year === null || !isset($year['id'])) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        $resolvedYearId = (int) $year['id'];
        $selectedYearId = $resolvedYearId;

        $records = DigitalDocumentSignature::listForYear($resolvedYearId);
        $letterTypes = DigitalDocumentTypes::letterTypes();

        $letterRecords = array_values(array_filter(
            $records,
            static function (array $record) use ($letterTypes): bool {
                $type = (string) ($record['document_type'] ?? '');

                return in_array($type, $letterTypes, true);
            }
        ));

        $letterRecords = array_values(array_filter(
            $letterRecords,
            static function (array $record): bool {
                if (($record['document_type'] ?? '') !== 'outgoing_letter') {
                    return true;
                }

                $documentKey = (string) ($record['document_key'] ?? '');

                if (!str_starts_with($documentKey, 'letter:')) {
                    return true;
                }

                $letterId = (int) substr($documentKey, strlen('letter:'));

                if ($letterId <= 0) {
                    return false;
                }

                $letter = OutgoingLetter::find($letterId);

                return $letter !== null;
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

        if ($statusFilter !== 'all') {
            $letterRecords = array_values(array_filter(
                $letterRecords,
                static function (array $record) use ($statusFilter): bool {
                    return (string) ($record['status'] ?? '') === $statusFilter;
                }
            ));
        }

        $letters = array_map(function (array $record): array {
            $payload = [];

            if (isset($record['payload']) && is_string($record['payload'])) {
                $decoded = json_decode($record['payload'], true);

                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $letterPayload = [];

            if (isset($payload['letter']) && is_array($payload['letter'])) {
                $letterPayload = $payload['letter'];
            }

            $record['payload_data'] = $payload;
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

            $record['requested_at_label'] = $this->formatDateTime($record['created_at'] ?? ($record['updated_at'] ?? null));
            $record['approved_at_label'] = $this->formatDateTime($record['approved_at'] ?? null);
            $record['updated_at_label'] = $this->formatDateTime($record['updated_at'] ?? null);

            $token = (string) ($record['signature_token'] ?? '');
            $record['verification_url'] = $token !== '' && (string) ($record['status'] ?? '') === 'approved'
                ? absolute_url('persuratan/validasi/' . $token)
                : null;

            return $record;
        }, $letterRecords);

        $digitalSignatureEnabled = (int) ($year['digital_signature_enabled'] ?? 0) === 1;

        $outgoingLetterRows = OutgoingLetter::listForYear($resolvedYearId);
        $outgoingLetters = array_map(
            fn (array $letter): array => $this->formatOutgoingLetterRecord($letter, $year, $digitalSignatureEnabled),
            $outgoingLetterRows
        );

        $incomingLetterRows = IncomingLetter::listForYear($resolvedYearId);
        $incomingLetters = array_map(fn (array $letter): array => $this->formatIncomingLetterRecord($letter), $incomingLetterRows);

        $editLetterId = (int) $request->query('edit_surat', 0);
        $editOutgoingLetter = null;
        $editAttachmentBodies = [];

        if ($editLetterId > 0) {
            $editLetter = OutgoingLetter::find($editLetterId);

            if ($editLetter === null || (int) ($editLetter['tahun_ajaran_id'] ?? 0) !== $resolvedYearId) {
                Session::flash('error', 'Surat yang ingin diedit tidak ditemukan.');

                return $this->redirect($this->buildLettersRedirectUrl($resolvedYearId, null, '#surat-keluar'));
            }

            $editOutgoingLetter = $this->formatOutgoingLetterRecord($editLetter, $year, $digitalSignatureEnabled, true);
            $attachmentRecords = is_array($editOutgoingLetter['lampiran_records'] ?? null)
                ? $editOutgoingLetter['lampiran_records']
                : [];

            foreach ($attachmentRecords as $attachment) {
                $editAttachmentBodies[] = (string) ($attachment['body_html'] ?? '');
            }
        }

        $letterTypeOptions = [];

        foreach (LetterCatalog::outgoingTypes() as $key => $entry) {
            $letterTypeOptions[$key] = [
                'value' => $key,
                'code' => $entry['code'] ?? '',
                'label' => LetterCatalog::displayLabel($entry),
            ];
        }

        $schoolProfile = SchoolProfile::first();
        $defaultUnitCode = '';
        $letterheadPath = null;
        $letterheadUrl = null;
        $headmasterOption = null;

        if ($schoolProfile !== null) {
            $defaultUnitCode = LetterNumber::normalizeUnitCode((string) ($schoolProfile['nama'] ?? ''));
            $letterheadPath = (string) ($schoolProfile['kop_surat'] ?? '');

            if ($letterheadPath !== '') {
                $letterheadUrl = asset($letterheadPath);
            }
        }

        $headmasterId = isset($year['kepala_sekolah_id']) ? (int) $year['kepala_sekolah_id'] : 0;

        if ($headmasterId > 0) {
            $headmaster = Teacher::find($headmasterId);

            if ($headmaster !== null) {
                $headmasterOption = [
                    'name' => (string) ($headmaster['nama'] ?? ''),
                    'nip' => (string) ($headmaster['nip'] ?? ''),
                ];
            }
        }

        $nextOutgoingSequence = OutgoingLetter::maxSequence($resolvedYearId) + 1;
        $nextIncomingAgenda = IncomingLetter::maxAgenda($resolvedYearId) + 1;

        return $this->render('tata-usaha/letters/index', [
            'title' => 'Persuratan',
            'pageTitle' => 'Persuratan',
            'activeMenu' => 'letters',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'currentYear' => $year,
            'statusFilter' => $statusFilter,
            'letters' => $letters,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'statusSummary' => $statusSummary,
            'outgoingLetters' => $outgoingLetters,
            'incomingLetters' => $incomingLetters,
            'outgoingLetterTypes' => $letterTypeOptions,
            'commonLetterTemplates' => LetterCatalog::commonTemplates(),
            'defaultUnitCode' => $defaultUnitCode,
            'nextOutgoingSequence' => $nextOutgoingSequence,
            'nextIncomingAgenda' => $nextIncomingAgenda,
            'letterheadPath' => $letterheadPath,
            'letterheadUrl' => $letterheadUrl,
            'schoolProfileExists' => $schoolProfile !== null,
            'headmasterOption' => $headmasterOption,
            'maxOutgoingAttachments' => self::MAX_OUTGOING_ATTACHMENTS,
            'editOutgoingLetter' => $editOutgoingLetter,
            'editAttachmentBodies' => $editAttachmentBodies,
        ], 'admin');
    }

    public function updateLetterhead(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/persuratan')) {
            return $response;
        }

        $profile = SchoolProfile::first();

        if ($profile === null || !isset($profile['id'])) {
            Session::flash('error', 'Profil sekolah belum diset oleh admin. Silakan hubungi admin terlebih dahulu.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
        }

        $profileId = (int) $profile['id'];
        $existingPath = (string) ($profile['kop_surat'] ?? '');

        if ((string) $request->input('remove', '') === '1') {
            if ($existingPath !== '') {
                $this->deleteLetterheadFile($existingPath);
            }

            $updated = SchoolProfile::updateById($profileId, [
                'kop_surat' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Session::flash($updated ? 'success' : 'error', $updated ? 'Kop surat berhasil dihapus.' : 'Gagal menghapus kop surat.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
        }

        $files = $request->files();
        $file = $files['kop_surat'] ?? null;

        if ($file === null) {
            Session::flash('error', 'Pilih berkas JPG untuk diunggah.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
        }

        $stored = $this->storeLetterheadFile($file, $existingPath);

        if ($stored === null) {
            Session::flash('error', 'Format kop surat harus JPG.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
        }

        if ($stored === false) {
            Session::flash('error', 'Gagal mengunggah kop surat. Coba lagi.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
        }

        $updated = SchoolProfile::updateById($profileId, [
            'kop_surat' => $stored,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash($updated ? 'success' : 'error', $updated ? 'Kop surat berhasil diperbarui.' : 'Gagal menyimpan kop surat.');

        return $this->redirect($this->buildLettersRedirectUrl(null, null, '#pengaturan-kop'));
    }

    public function storeOutgoingLetter(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/persuratan')) {
            return $response;
        }

        $files = $request->files();

        $editingId = (int) $request->input('outgoing_letter_id', 0);
        $existingLetter = null;

        if ($editingId > 0) {
            $existingLetter = OutgoingLetter::find($editingId);

            if ($existingLetter === null) {
                Session::flash('error', 'Surat tidak ditemukan untuk diperbarui.');
                Session::flashInput($request->all());

                return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
            }
        }

        $yearId = $existingLetter !== null
            ? (int) ($existingLetter['tahun_ajaran_id'] ?? 0)
            : (int) $request->input('tahun_ajaran_id', 0);

        if ($yearId <= 0) {
            $yearId = (int) (SchoolYearContext::id() ?? 0);
        }

        $redirectParams = $editingId > 0 ? ['edit_surat' => $editingId] : [];

        $year = $yearId > 0 ? SchoolYear::find($yearId) : null;

        if ($year === null || !isset($year['id'])) {
            Session::flash('error', 'Tahun ajaran tidak valid untuk menyimpan surat.');
            Session::flashInput($request->all());

            return $this->redirect($this->buildLettersRedirectUrl($yearId > 0 ? $yearId : null, null, '#surat-keluar', $redirectParams));
        }

        $redirectUrl = $this->buildLettersRedirectUrl($yearId, null, '#surat-keluar', $redirectParams);

        $digitalSignatureEnabled = (int) ($year['digital_signature_enabled'] ?? 0) === 1;
        $schoolProfile = SchoolProfile::first();

        $typeInput = $existingLetter !== null
            ? (string) ($existingLetter['kode_jenis'] ?? '')
            : (string) $request->input('jenis_surat', '');

        if ($typeInput === '') {
            $typeInput = (string) $request->input('jenis_surat', '');
        }
        $letterType = LetterCatalog::find($typeInput);

        if ($letterType === null) {
            $letterType = LetterCatalog::findByCode($typeInput);
        }

        if ($letterType === null) {
            if ($existingLetter !== null) {
                $letterType = [
                    'code' => (string) ($existingLetter['kode_jenis'] ?? ''),
                    'name' => (string) ($existingLetter['jenis'] ?? ''),
                ];
            } else {
                Session::flash('error', 'Jenis surat tidak dikenal.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }
        }

        $subject = trim((string) $request->input('perihal', ''));

        if ($subject === '') {
            Session::flash('error', 'Perihal wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $unitInput = trim((string) $request->input('unit_kode', ''));

        if ($unitInput === '' && $schoolProfile !== null) {
            $unitInput = (string) ($schoolProfile['nama'] ?? '');
        }

        $unitCode = $unitInput !== '' ? LetterNumber::normalizeUnitCode($unitInput) : '';

        if ($unitCode === '') {
            $unitCode = 'SEKOLAH';
        }

        $recipient = trim((string) $request->input('tujuan', ''));
        $attachmentRaw = (string) $request->input('lampiran', '');
        $attachmentLabel = $this->normalizeAttachmentInput($attachmentRaw);
        $attachmentCount = $this->parseAttachmentCount($attachmentRaw);
        $attachmentBodiesInput = $request->input('lampiran_teks', []);
        $preparedAttachments = $this->prepareAttachmentBodies($attachmentBodiesInput, $attachmentCount);
        $tembusan = (string) $request->input('tembusan', '');
        $body = (string) $request->input('isi', '');
        $note = trim((string) $request->input('catatan', ''));
        $signer = trim((string) $request->input('tanda_tangan', ''));

        $letterDateInput = (string) $request->input('tanggal_surat', date('Y-m-d'));
        $recordedDateInput = (string) $request->input('tanggal_dicatat', $letterDateInput);

        $letterDateNormalized = $this->normalizeDate($letterDateInput, date('Y-m-d'));
        $recordedDateNormalized = $this->normalizeDate($recordedDateInput, $letterDateNormalized !== '' ? $letterDateNormalized : date('Y-m-d'));

        try {
            $letterDateObject = new DateTimeImmutable($letterDateNormalized !== '' ? $letterDateNormalized : date('Y-m-d'));
        } catch (\Exception) {
            $letterDateObject = new DateTimeImmutable();
            $letterDateNormalized = $letterDateObject->format('Y-m-d');
        }

        try {
            $recordedDateObject = new DateTimeImmutable($recordedDateNormalized !== '' ? $recordedDateNormalized : $letterDateNormalized);
        } catch (\Exception) {
            $recordedDateObject = $letterDateObject;
            $recordedDateNormalized = $recordedDateObject->format('Y-m-d');
        }

        $userId = (int) (auth()['id'] ?? 0);
        $userId = $userId > 0 ? $userId : null;
        $now = date('Y-m-d H:i:s');
        $requestSignature = $digitalSignatureEnabled
            && (string) $request->input('ajukan_ttd', '1') === '1';
        $headmasterSigner = $this->isHeadmasterSigner($signer, $year);
        $existingPdfPath = $existingLetter !== null ? (string) ($existingLetter['pdf_path'] ?? '') : '';
        $existingSignedPath = $existingLetter !== null ? (string) ($existingLetter['pdf_signed_path'] ?? '') : '';

        $pdfMode = (string) $request->input('mode_pdf', '0') === '1';
        $pdfFile = is_array($files) ? ($files['surat_pdf'] ?? null) : null;
        $pdfPath = null;
        $pdfSignatureOptions = null;
        $signatureCity = $this->trimString($request->input('signature_city', ''));
        $signatureTitimangsa = $this->trimString($request->input('signature_titimangsa', ''));
        $signatureHeadmaster = $this->trimString($request->input('signature_headmaster_name', ''));
        $useLetterhead = (string) $request->input('use_letterhead', '0') === '1';
        $letterheadPath = null;
        $signatureMetaTitle = $this->trimString($request->input('signature_meta_title', ''));
        $signatureMetaNote = $this->trimString($request->input('signature_meta_note', ''));
        $schoolProfile = SchoolProfile::first();
        if ($useLetterhead && $schoolProfile !== null) {
            $candidate = (string) ($schoolProfile['kop_surat'] ?? '');
            if ($candidate !== '') {
                $letterheadPath = $candidate;
            }
        }

        if ($pdfMode) {
            $pdfInputOptions = [
                'page' => $request->input('pdf_signature_page', 1),
                'x_percent' => $request->input('pdf_signature_x', 70),
                'y_percent' => $request->input('pdf_signature_y', 70),
                'width_percent' => $request->input('pdf_signature_width', 20),
                'city' => $signatureCity,
                'titimangsa' => $signatureTitimangsa,
                'headmaster_name' => $signatureHeadmaster,
                'signature_mode' => 'metadata',
                'signature_meta_title' => $signatureMetaTitle,
                'signature_meta_note' => $signatureMetaNote,
                'use_letterhead' => $useLetterhead,
                'letterhead_path' => $letterheadPath,
                'school_name' => $this->trimString($request->input('signature_school_name', (string) ($schoolProfile['nama'] ?? ''))),
            ];
            $pdfRequired = $digitalSignatureEnabled && $headmasterSigner && $requestSignature;
            $pdfUploaded = is_array($pdfFile) && (int) ($pdfFile['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_NO_FILE;

            if ($pdfUploaded) {
                $storedPdf = OutgoingLetterPdfService::storeUploadedPdf(
                    $pdfFile,
                    $existingPdfPath !== '' ? $existingPdfPath : null
                );

                if ($storedPdf === null) {
                    Session::flash('error', 'File TTD digital harus berformat PDF.');
                    Session::flashInput($request->all());

                    return $this->redirect($redirectUrl);
                }

                if ($storedPdf === false) {
                    Session::flash('error', 'Gagal mengunggah PDF surat. Coba lagi.');
                    Session::flashInput($request->all());

                    return $this->redirect($redirectUrl);
                }

                $pdfPath = $storedPdf;
                $pdfSignatureOptions = OutgoingLetterPdfService::normalizeSignatureOptions($pdfInputOptions);
            } elseif ($existingPdfPath !== '') {
                $pdfPath = $existingPdfPath;
            } elseif ($pdfRequired) {
                Session::flash('error', 'Unggah file PDF surat terlebih dahulu sebelum mengajukan TTD digital.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }

            if ($pdfSignatureOptions === null && $pdfRequired) {
                $pdfSignatureOptions = OutgoingLetterPdfService::normalizeSignatureOptions($pdfInputOptions);
            }
        }

        if ($existingLetter !== null) {
            $updates = [
                'unit_kode' => $unitCode,
                'tujuan' => $recipient !== '' ? $recipient : null,
                'perihal' => $subject,
                'lampiran' => $attachmentLabel,
                'tembusan' => $this->normalizeLineInput($tembusan),
                'tanggal_surat' => $letterDateNormalized,
                'tanggal_dicatat' => $recordedDateNormalized,
                'isi' => $this->normalizeBodyInput($body),
                'catatan' => $note !== '' ? $note : null,
                'tanda_tangan' => $signer !== '' ? $signer : null,
                'diperbarui_oleh' => $userId,
                'updated_at' => $now,
            ];

            if ($pdfMode) {
                $updates['pdf_path'] = $pdfPath;
                $updates['pdf_signature_options'] = $pdfSignatureOptions !== null
                    ? json_encode($pdfSignatureOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null;
                $updates['pdf_signed_path'] = null;
            }

            $updated = OutgoingLetter::updateById($editingId, $updates);

            if (!$updated) {
                Session::flash('error', 'Gagal memperbarui surat keluar. Coba lagi.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }

            try {
                OutgoingLetterAttachment::replaceForLetter($editingId, $preparedAttachments);
            } catch (\Throwable $exception) {
                Session::flash('error', 'Surat keluar gagal diperbarui karena lampiran tidak dapat diproses. Silakan coba lagi.');
                Session::flashInput($request->all());

                return $this->redirect($redirectUrl);
            }

            if ($pdfMode && $existingSignedPath !== '') {
                $this->deleteLetterPdfFile($existingSignedPath);
            }

            $updatedLetter = OutgoingLetter::find($editingId);
            $signatureRecord = null;
            $digitalSignatureRecord = null;

            if ($digitalSignatureEnabled && $headmasterSigner) {
                $signatureRecord = DigitalDocumentSignature::findByDocument(
                    (int) $year['id'],
                    'outgoing_letter',
                    'letter:' . $editingId
                );
                $signatureStatusBefore = (string) ($signatureRecord['status'] ?? '');
                $shouldEnsureSignature = $requestSignature || $signatureRecord !== null;

                if ($shouldEnsureSignature && $updatedLetter !== null) {
                    $signaturePayload = $this->buildOutgoingLetterSignaturePayload(
                        $updatedLetter,
                        $letterType,
                        $schoolProfile
                    );

                    $digitalSignatureRecord = DigitalDocumentSignature::ensure(
                        (int) $year['id'],
                        'outgoing_letter',
                        'letter:' . $editingId,
                        $subject !== '' ? $subject : ($updatedLetter['jenis'] ?? 'Surat Keluar'),
                        $signaturePayload,
                        null,
                        null,
                        $userId
                    );
                }
            }

            Session::flash('success', 'Surat keluar berhasil diperbarui.');

            if ($digitalSignatureEnabled && $headmasterSigner && $requestSignature && $digitalSignatureRecord === null) {
                Session::flash('warning', 'Surat diperbarui, namun pengajuan TTD digital kepala sekolah gagal. Silakan ajukan ulang melalui menu TTD digital.');
            }

            if (
                isset($signatureStatusBefore)
                && $signatureStatusBefore === 'approved'
                && $digitalSignatureRecord !== null
                && (string) ($digitalSignatureRecord['status'] ?? '') === 'pending'
            ) {
                Session::flash('info', 'Surat direvisi. Persetujuan kepala sekolah perlu diajukan ulang.');
            }

            return $this->redirect($redirectUrl);
        }

        $createdId = null;
        $letterNumber = '';
        $attempt = 0;
        $lastInsertError = null;
        $usedSchemaFallback = false;

        do {
            $sequence = OutgoingLetter::maxSequence($yearId) + 1;
            $letterNumber = LetterNumber::format((string) ($letterType['code'] ?? ''), $sequence, $unitCode, $letterDateObject);

            $attributes = [
                'tahun_ajaran_id' => $yearId,
                'kode_jenis' => $letterType['code'],
                'jenis' => LetterCatalog::displayLabel($letterType),
                'nomor_urut' => $sequence,
                'nomor_surat' => $letterNumber,
                'unit_kode' => $unitCode,
                'tujuan' => $recipient !== '' ? $recipient : null,
                'perihal' => $subject,
                'lampiran' => $attachmentLabel,
                'tembusan' => $this->normalizeLineInput($tembusan),
                'tanggal_surat' => $letterDateNormalized,
                'tanggal_dicatat' => $recordedDateNormalized,
                'isi' => $this->normalizeBodyInput($body),
                'pdf_path' => $pdfPath,
                'pdf_signature_options' => $pdfSignatureOptions !== null ? json_encode($pdfSignatureOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'pdf_signed_path' => null,
                'catatan' => $note !== '' ? $note : null,
                'tanda_tangan' => $signer !== '' ? $signer : null,
                'dibuat_oleh' => $userId,
                'diperbarui_oleh' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            try {
                $createdId = OutgoingLetter::createAndReturnId($attributes);
            } catch (\Throwable $exception) {
                $lastInsertError = $exception;
                $createdId = $this->retryOutgoingLetterInsert($attributes, $exception, $usedSchemaFallback);
            }

            $attempt++;
        } while ($createdId === null && $attempt < 2);

        if ($createdId === null) {
            $errorMessage = $lastInsertError !== null
                ? $this->buildOutgoingLetterInsertErrorMessage($lastInsertError)
                : 'Gagal menyimpan surat keluar. Coba lagi.';
            Session::flash('error', $errorMessage);
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        try {
            OutgoingLetterAttachment::replaceForLetter($createdId, $preparedAttachments);
        } catch (\Throwable $exception) {
            OutgoingLetter::deleteById($createdId);
            Session::flash('error', 'Surat keluar gagal disimpan karena lampiran tidak dapat diproses. Silakan coba lagi.');
            Session::flashInput($request->all());

            return $this->redirect($redirectUrl);
        }

        $letterRecord = OutgoingLetter::find($createdId);
        $digitalSignatureRecord = null;

        if ($digitalSignatureEnabled && $headmasterSigner && $requestSignature && $letterRecord !== null) {
            $signaturePayload = $this->buildOutgoingLetterSignaturePayload(
                $letterRecord,
                $letterType,
                $schoolProfile
            );

            $digitalSignatureRecord = DigitalDocumentSignature::ensure(
                (int) $year['id'],
                'outgoing_letter',
                'letter:' . $createdId,
                $subject !== '' ? $subject : ($letterRecord['jenis'] ?? 'Surat Keluar'),
                $signaturePayload,
                null,
                null,
                $userId
            );
        }

        $successMessage = sprintf('Surat keluar berhasil dibuat dengan nomor %s.', $letterNumber);

        if ($digitalSignatureEnabled && $headmasterSigner && $requestSignature) {
            if ($digitalSignatureRecord !== null) {
                $successMessage .= ' Permintaan TTD digital kepala sekolah telah diajukan.';
            } else {
                Session::flash('warning', 'Surat keluar berhasil dibuat, namun pengajuan TTD digital kepala sekolah gagal. Silakan ajukan ulang melalui menu TTD digital.');
            }
        } elseif ($digitalSignatureEnabled && $headmasterSigner && !$requestSignature) {
            $successMessage .= ' (TTD digital kepala sekolah tidak diajukan).';
        }

        Session::flash('success', $successMessage);
        if ($usedSchemaFallback) {
            Session::flash('warning', 'Surat keluar berhasil disimpan, tetapi database belum mengikuti skema terbaru. Jalankan migrasi database agar fitur PDF/QR berfungsi penuh.');
        }

        return $this->redirect($redirectUrl);
    }

    public function parseOutgoingLetterTemplate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if (!Csrf::validate((string) $request->input('_token', ''))) {
            return $this->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau telah kedaluwarsa. Muat ulang halaman, lalu coba lagi.',
            ], 419);
        }

        $files = $request->files();
        $upload = is_array($files) ? ($files['template_surat'] ?? null) : null;

        if (!is_array($upload)) {
            return $this->json([
                'success' => false,
                'message' => 'File template surat tidak ditemukan.',
            ], 422);
        }

        $errorCode = (int) ($upload['error'] ?? \UPLOAD_ERR_NO_FILE);

        if ($errorCode === \UPLOAD_ERR_NO_FILE) {
            return $this->json([
                'success' => false,
                'message' => 'Pilih file template surat terlebih dahulu.',
            ], 422);
        }

        if ($errorCode !== \UPLOAD_ERR_OK) {
            return $this->json([
                'success' => false,
                'message' => 'Gagal mengunggah template surat.',
            ], 422);
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return $this->json([
                'success' => false,
                'message' => 'Template surat tidak valid.',
            ], 422);
        }

        $size = (int) ($upload['size'] ?? 0);

        try {
            LetterTemplateParser::assertAllowedSize($size);
            $fields = LetterTemplateParser::parseFile($tmpName, (string) ($upload['name'] ?? ''));
        } catch (\RuntimeException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses template surat.',
            ], 500);
        }

        return $this->json([
            'success' => true,
            'message' => 'Template surat berhasil dianalisis.',
            'data' => [
                'tujuan' => $fields['tujuan'] ?? null,
                'perihal' => $fields['perihal'] ?? null,
                'tembusan' => $fields['tembusan'] ?? null,
                'isi' => $fields['isi'] ?? null,
            ],
        ]);
    }

    public function showOutgoingLetter(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letter = OutgoingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letterYear = SchoolYear::find((int) ($letter['tahun_ajaran_id'] ?? 0));
        $letterDigitalEnabled = $letterYear !== null && (int) ($letterYear['digital_signature_enabled'] ?? 0) === 1;
        $letter = $this->formatOutgoingLetterRecord($letter, $letterYear, $letterDigitalEnabled, true);
        $schoolProfile = SchoolProfile::first();

        return $this->render('tata-usaha/letters/outgoing-show', [
            'title' => 'Detail Surat Keluar',
            'pageTitle' => 'Detail Surat Keluar',
            'activeMenu' => 'letters',
            'letter' => $letter,
            'schoolProfile' => $schoolProfile,
        ], 'admin');
    }

    public function printOutgoingLetter(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letter = OutgoingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letterYear = SchoolYear::find((int) ($letter['tahun_ajaran_id'] ?? 0));
        $letterDigitalEnabled = $letterYear !== null && (int) ($letterYear['digital_signature_enabled'] ?? 0) === 1;
        $letter = $this->formatOutgoingLetterRecord($letter, $letterYear, $letterDigitalEnabled, true);
        $schoolProfile = SchoolProfile::first();

        return $this->render('tata-usaha/letters/outgoing-print', [
            'title' => 'Cetak Surat Keluar',
            'letter' => $letter,
            'schoolProfile' => $schoolProfile,
        ], 'print');
    }

    public function createOutgoingLetterPdf(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $yearOptions = SchoolYearContext::options();
        $year = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;

        if ($year === null) {
            $year = SchoolYearContext::resolve();
        }

        if ($year === null || !isset($year['id'])) {
            Session::flash('error', 'Belum ada tahun ajaran aktif.');

            return $this->redirect('dashboard');
        }

        $resolvedYearId = (int) $year['id'];
        $selectedYearId = $resolvedYearId;
        $digitalSignatureEnabled = (int) ($year['digital_signature_enabled'] ?? 0) === 1;
        $schoolProfile = SchoolProfile::first();
        $defaultUnitCode = '';
        $letterheadPath = null;
        $letterheadUrl = null;

        if ($schoolProfile !== null) {
            $defaultUnitCode = LetterNumber::normalizeUnitCode((string) ($schoolProfile['nama'] ?? ''));
            $letterheadPath = (string) ($schoolProfile['kop_surat'] ?? '');

            if ($letterheadPath !== '') {
                $letterheadUrl = asset($letterheadPath);
            }
        }

        $headmasterId = isset($year['kepala_sekolah_id']) ? (int) $year['kepala_sekolah_id'] : 0;
        $headmasterOption = null;

        if ($headmasterId > 0) {
            $headmaster = Teacher::find($headmasterId);

            if ($headmaster !== null) {
                $headmasterOption = [
                    'name' => (string) ($headmaster['nama'] ?? ''),
                    'nip' => (string) ($headmaster['nip'] ?? ''),
                ];
            }
        }

        $letterTypeOptions = [];

        foreach (LetterCatalog::outgoingTypes() as $key => $entry) {
            $letterTypeOptions[$key] = [
                'value' => $key,
                'code' => $entry['code'] ?? '',
                'label' => LetterCatalog::displayLabel($entry),
            ];
        }

        $nextOutgoingSequence = OutgoingLetter::maxSequence($resolvedYearId) + 1;
        $nextOutgoingSequenceLabel = str_pad((string) $nextOutgoingSequence, 3, '0', STR_PAD_LEFT);
        $todayDate = date('Y-m-d');
        $defaultPdfPage = max(1, (int) old('pdf_signature_page', 1));
        $defaultPdfX = (float) old('pdf_signature_x', 70);
        $defaultPdfY = (float) old('pdf_signature_y', 65);
        $defaultPdfWidth = (float) old('pdf_signature_width', 20);
        $signerDefault = 'Kepala Sekolah';
        $defaultCity = '';

        if ($schoolProfile !== null) {
            $cityCandidates = [
                $schoolProfile['kota'] ?? null,
                $schoolProfile['kabupaten'] ?? null,
                $schoolProfile['kota_kabupaten'] ?? null,
            ];

            foreach ($cityCandidates as $candidate) {
                $candidate = is_string($candidate) ? trim($candidate) : '';

                if ($candidate !== '') {
                    $defaultCity = $candidate;
                    break;
                }
            }
        }

        return $this->render('tata-usaha/letters/create-pdf', [
            'title' => 'Surat Keluar (PDF)',
            'pageTitle' => 'Pencatatan Surat Keluar PDF',
            'activeMenu' => 'letters',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'defaultUnitCode' => $defaultUnitCode,
            'letterheadUrl' => $letterheadUrl,
            'letterTypeOptions' => $letterTypeOptions,
            'commonLetterTemplates' => LetterCatalog::commonTemplates(),
            'nextOutgoingSequenceLabel' => $nextOutgoingSequenceLabel,
            'todayDate' => $todayDate,
            'defaultPdfPage' => $defaultPdfPage,
            'defaultPdfX' => $defaultPdfX,
            'defaultPdfY' => $defaultPdfY,
            'defaultPdfWidth' => $defaultPdfWidth,
            'headmasterOption' => $headmasterOption,
            'signerDefault' => $signerDefault,
            'defaultCity' => $defaultCity,
        ], 'admin');
    }

    public function previewOutgoingLetterSignature(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letter = OutgoingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $year = SchoolYear::find((int) ($letter['tahun_ajaran_id'] ?? 0));
        $digitalSignatureEnabled = $year !== null && (int) ($year['digital_signature_enabled'] ?? 0) === 1;
        $letter = $this->formatOutgoingLetterRecord($letter, $year, $digitalSignatureEnabled, true);
        $pdfMeta = is_array($letter['pdf'] ?? null) ? $letter['pdf'] : [];
        $pdfUrl = isset($pdfMeta['url']) ? (string) $pdfMeta['url'] : null;
        $schoolProfile = SchoolProfile::first();
        $defaultCity = '';

        if ($schoolProfile !== null) {
            $cityCandidates = [
                $schoolProfile['kota'] ?? null,
                $schoolProfile['kabupaten'] ?? null,
                $schoolProfile['kota_kabupaten'] ?? null,
            ];

            foreach ($cityCandidates as $candidate) {
                $candidate = is_string($candidate) ? trim($candidate) : '';

                if ($candidate !== '') {
                    $defaultCity = $candidate;
                    break;
                }
            }
        }

        $headmasterOption = null;

        if ($year !== null) {
            $headmasterId = isset($year['kepala_sekolah_id']) ? (int) $year['kepala_sekolah_id'] : 0;

            if ($headmasterId > 0) {
                $headmaster = Teacher::find($headmasterId);

                if ($headmaster !== null) {
                    $headmasterOption = [
                        'name' => (string) ($headmaster['nama'] ?? ''),
                        'nip' => (string) ($headmaster['nip'] ?? ''),
                    ];
                }
            }
        }

        return $this->render('tata-usaha/letters/preview-signature', [
            'title' => 'Preview TTD Digital',
            'pageTitle' => 'Atur Posisi QR TTD Digital',
            'activeMenu' => 'letters',
            'letter' => $letter,
            'digitalSignatureEnabled' => $digitalSignatureEnabled,
            'existingPdfUrl' => $pdfUrl,
            'schoolProfile' => $schoolProfile,
            'headmasterOption' => $headmasterOption,
            'defaultCity' => $defaultCity,
        ], 'admin');
    }

    public function updateOutgoingLetterSignature(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $redirectPath = 'tata-usaha/persuratan/surat-keluar/' . $id . '/preview-ttd';

        if ($response = $this->guardCsrf($request, $redirectPath)) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($redirectPath);
        }

        $letter = OutgoingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($redirectPath);
        }

        $year = SchoolYear::find((int) ($letter['tahun_ajaran_id'] ?? 0));
        $digitalSignatureEnabled = $year !== null && (int) ($year['digital_signature_enabled'] ?? 0) === 1;

        if (!$digitalSignatureEnabled) {
            Session::flash('error', 'TTD digital belum diaktifkan oleh admin.');

            return $this->redirect($redirectPath);
        }

        $files = $request->files();
        $pdfFile = is_array($files) ? ($files['surat_pdf'] ?? null) : null;
        $existingPdfPath = (string) ($letter['pdf_path'] ?? '');
        $existingSignedPath = (string) ($letter['pdf_signed_path'] ?? '');
        $signatureCity = $this->trimString($request->input('signature_city', ''));
        $signatureTitimangsa = $this->trimString($request->input('signature_titimangsa', ''));
        $signatureHeadmaster = $this->trimString($request->input('signature_headmaster_name', ''));
        $useLetterhead = (string) $request->input('use_letterhead', '0') === '1';
        $signatureMetaTitle = $this->trimString($request->input('signature_meta_title', ''));
        $signatureMetaNote = $this->trimString($request->input('signature_meta_note', ''));
        $letterheadPath = null;
        $schoolProfile = SchoolProfile::first();
        if ($useLetterhead && $schoolProfile !== null) {
            $candidate = (string) ($schoolProfile['kop_surat'] ?? '');
            if ($candidate !== '') {
                $letterheadPath = $candidate;
            }
        }

        $pdfSignatureOptions = OutgoingLetterPdfService::normalizeSignatureOptions([
            'page' => $request->input('pdf_signature_page', 1),
            'x_percent' => $request->input('pdf_signature_x', 70),
            'y_percent' => $request->input('pdf_signature_y', 65),
            'width_percent' => $request->input('pdf_signature_width', 20),
            'city' => $signatureCity,
            'titimangsa' => $signatureTitimangsa,
            'headmaster_name' => $signatureHeadmaster,
            'signature_mode' => 'metadata',
            'signature_meta_title' => $signatureMetaTitle,
            'signature_meta_note' => $signatureMetaNote,
            'use_letterhead' => $useLetterhead,
            'letterhead_path' => $letterheadPath,
            'school_name' => $this->trimString($request->input('signature_school_name', (string) ($schoolProfile['nama'] ?? ''))),
        ]);

        $pdfPath = $existingPdfPath;

        $uploaded = is_array($pdfFile) && (int) ($pdfFile['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_NO_FILE;

        if ($uploaded) {
            $stored = OutgoingLetterPdfService::storeUploadedPdf($pdfFile, $existingPdfPath !== '' ? $existingPdfPath : null);

            if ($stored === null) {
                Session::flash('error', 'File harus PDF.');

                return $this->redirect($redirectPath);
            }

            if ($stored === false) {
                Session::flash('error', 'Gagal mengunggah PDF. Coba lagi.');

                return $this->redirect($redirectPath);
            }

            $pdfPath = $stored;
        } elseif ($existingPdfPath === '') {
            Session::flash('error', 'Unggah PDF surat terlebih dahulu.');

            return $this->redirect($redirectPath);
        }

        $updates = [
            'pdf_path' => $pdfPath,
            'pdf_signature_options' => json_encode($pdfSignatureOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pdf_signed_path' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        OutgoingLetter::updateById($letterId, $updates);

        if ($existingSignedPath !== '') {
            $this->deleteLetterPdfFile($existingSignedPath);
        }

        $signatureRecord = DigitalDocumentSignature::findByDocument(
            (int) ($letter['tahun_ajaran_id'] ?? 0),
            'outgoing_letter',
            'letter:' . $letterId
        );

        if ($signatureRecord !== null && (string) ($signatureRecord['status'] ?? '') === 'approved') {
            DigitalDocumentSignature::updateById($signatureRecord['id'], [
                'status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
                'signature_token' => null,
                'approval_note' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Session::flash('success', 'PDF dan posisi QR berhasil diperbarui. Ajukan/lanjutkan persetujuan kepala sekolah.');

        return $this->redirect($redirectPath);
    }

    public function destroyOutgoingLetter(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/persuratan')) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $letter = OutgoingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-keluar'));
        }

        $yearId = (int) ($letter['tahun_ajaran_id'] ?? 0);
        $number = (string) ($letter['nomor_surat'] ?? '');
        $pdfPath = (string) ($letter['pdf_path'] ?? '');
        $pdfSignedPath = (string) ($letter['pdf_signed_path'] ?? '');

        $digitalDocumentKey = 'letter:' . $letterId;

        try {
            $deleted = OutgoingLetter::deleteById($letterId);
        } catch (\Throwable) {
            $deleted = false;
        }

        if ($deleted) {
            try {
                DigitalDocumentSignature::revokeForDocument(
                    $yearId,
                    'outgoing_letter',
                    $digitalDocumentKey,
                    'Surat dihapus dari sistem',
                    (int) (auth()['id'] ?? 0) ?: null
                );
            } catch (\Throwable) {
                // ignore revocation failure
            }
        }

        if ($deleted) {
            Session::flash('success', $number !== '' ? sprintf('Surat %s berhasil dihapus.', $number) : 'Surat berhasil dihapus.');
            $this->deleteLetterPdfFile($pdfPath);
            $this->deleteLetterPdfFile($pdfSignedPath);
        } else {
            Session::flash('error', 'Gagal menghapus surat keluar.');
        }

        return $this->redirect($this->buildLettersRedirectUrl($yearId > 0 ? $yearId : null, null, '#surat-keluar'));
    }

    public function storeIncomingLetter(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/persuratan')) {
            return $response;
        }

        $yearId = (int) $request->input('tahun_ajaran_id', 0);

        if ($yearId <= 0) {
            $yearId = (int) (SchoolYearContext::id() ?? 0);
        }

        $year = $yearId > 0 ? SchoolYear::find($yearId) : null;

        if ($year === null || !isset($year['id'])) {
            Session::flash('error', 'Tahun ajaran tidak valid untuk mencatat surat masuk.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-masuk'));
        }

        $incomingNumber = trim((string) $request->input('nomor_surat', ''));
        $origin = trim((string) $request->input('asal_surat', ''));
        $subject = trim((string) $request->input('perihal', ''));

        if ($incomingNumber === '' || $origin === '' || $subject === '') {
            Session::flash('error', 'Nomor surat, asal surat, dan perihal wajib diisi.');

            return $this->redirect($this->buildLettersRedirectUrl($yearId, null, '#surat-masuk'));
        }

        $recipient = trim((string) $request->input('penerima', ''));
        $attachment = $this->normalizeAttachmentInput((string) $request->input('lampiran', ''));
        $note = trim((string) $request->input('catatan', ''));

        $letterDateInput = (string) $request->input('tanggal_surat', '');
        $receivedDateInput = (string) $request->input('tanggal_diterima', date('Y-m-d'));

        $letterDateNormalized = $letterDateInput !== '' ? $this->normalizeDate($letterDateInput, null) : null;
        $receivedDateNormalized = $this->normalizeDate($receivedDateInput, date('Y-m-d'));

        if ($receivedDateNormalized === '') {
            $receivedDateNormalized = date('Y-m-d');
        }

        $agendaNumber = IncomingLetter::maxAgenda($yearId) + 1;
        $userId = (int) (auth()['id'] ?? 0);
        $userId = $userId > 0 ? $userId : null;
        $now = date('Y-m-d H:i:s');

        $attributes = [
            'tahun_ajaran_id' => $yearId,
            'nomor_agenda' => $agendaNumber,
            'nomor_surat' => $incomingNumber,
            'asal_surat' => $origin,
            'penerima' => $recipient !== '' ? $recipient : null,
            'perihal' => $subject,
            'lampiran' => $attachment,
            'tanggal_surat' => $letterDateNormalized,
            'tanggal_diterima' => $receivedDateNormalized,
            'catatan' => $note !== '' ? $note : null,
            'diterima_oleh' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $created = IncomingLetter::create($attributes);
        } catch (\Throwable) {
            $created = false;
        }

        if (!$created) {
            Session::flash('error', 'Gagal mencatat surat masuk.');

            return $this->redirect($this->buildLettersRedirectUrl($yearId, null, '#surat-masuk'));
        }

        Session::flash('success', sprintf('Surat masuk berhasil dicatat dengan nomor agenda %s.', str_pad((string) $agendaNumber, 3, '0', STR_PAD_LEFT)));

        return $this->redirect($this->buildLettersRedirectUrl($yearId, null, '#surat-masuk'));
    }

    public function destroyIncomingLetter(Request $request, string $id): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/persuratan')) {
            return $response;
        }

        $letterId = (int) $id;

        if ($letterId <= 0) {
            Session::flash('error', 'Data surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-masuk'));
        }

        $letter = IncomingLetter::find($letterId);

        if ($letter === null) {
            Session::flash('error', 'Data surat tidak ditemukan.');

            return $this->redirect($this->buildLettersRedirectUrl(null, null, '#surat-masuk'));
        }

        $yearId = (int) ($letter['tahun_ajaran_id'] ?? 0);
        $agenda = (int) ($letter['nomor_agenda'] ?? 0);

        try {
            $deleted = IncomingLetter::deleteById($letterId);
        } catch (\Throwable) {
            $deleted = false;
        }

        if ($deleted) {
            $agendaLabel = $agenda > 0 ? str_pad((string) $agenda, 3, '0', STR_PAD_LEFT) : '';
            Session::flash('success', $agendaLabel !== '' ? sprintf('Surat masuk dengan nomor agenda %s dihapus.', $agendaLabel) : 'Surat masuk berhasil dihapus.');
        } else {
            Session::flash('error', 'Gagal menghapus surat masuk.');
        }

        return $this->redirect($this->buildLettersRedirectUrl($yearId > 0 ? $yearId : null, null, '#surat-masuk'));
    }

    public function assignmentLetters(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $selectedTeacherId = (int) $request->query('guru_id', 0);

        $context = TeacherAssignmentLetterService::build(
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );

        $resolvedYearId = isset($context['yearId']) ? (int) $context['yearId'] : 0;

        if ($selectedYearId <= 0 && $resolvedYearId > 0) {
            $selectedYearId = $resolvedYearId;
        }

        $yearOptions = SchoolYearContext::options();
        $teacherOptions = [];

        foreach ($context['teachers'] as $teacher) {
            $teacherId = (int) ($teacher['id'] ?? 0);

            if ($teacherId <= 0) {
                continue;
            }

            $teacherOptions[$teacherId] = (string) ($teacher['name'] ?? 'Guru');
        }

        if ($selectedTeacherId > 0 && !isset($teacherOptions[$selectedTeacherId])) {
            $selectedTeacher = Teacher::find($selectedTeacherId);

            if ($selectedTeacher !== null) {
                $teacherOptions[$selectedTeacherId] = (string) ($selectedTeacher['nama'] ?? 'Guru');
            }
        }

        asort($teacherOptions);

        $defaults = TeacherAssignmentLetterService::defaultLetterConfig(
            $context['schoolYear'],
            $context['schoolProfile']
        );

        $letter = $this->resolveLetterPayload($request, $defaults);
        $signature = TeacherAssignmentLetterService::makeSignatureContext(
            $context,
            $letter,
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );
        $letter['signature_qr'] = $signature['status'] === 'approved'
            ? $this->makeSignatureQrPayload(
                $letter,
                $context['headmaster'],
                $context['schoolProfile'],
                $signature['verification_url'] ?? null
            )
            : '';

        $printParams = [
            'tahun_ajaran_id' => $selectedYearId,
            'guru_id' => $selectedTeacherId,
            'nomor_sk' => $letter['number'],
            'perihal' => $letter['subject'],
            'tempat' => $letter['place'],
            'tanggal_sk' => $letter['sign_date'],
            'berlaku_mulai' => $letter['effective_start'],
            'berlaku_sampai' => $letter['effective_end'],
            'menimbang' => implode("\n", $letter['menimbang']),
            'mengingat' => implode("\n", $letter['mengingat']),
            'menetapkan' => implode("\n", $letter['menetapkan']),
            'tembusan' => implode("\n", $letter['tembusan']),
        ];

        return $this->render('tata-usaha/assignment-letters/index', [
            'title' => 'SK Penugasan Guru',
            'pageTitle' => 'SK Penugasan Guru',
            'activeMenu' => 'assignment-letters',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'teacherOptions' => $teacherOptions,
            'selectedTeacherId' => $selectedTeacherId,
            'teachers' => $context['teachers'],
            'metrics' => $context['metrics'],
            'issues' => $context['issues'],
            'period' => $context['period'],
            'schoolYear' => $context['schoolYear'],
            'schoolProfile' => $context['schoolProfile'],
            'headmaster' => $context['headmaster'],
            'letter' => $letter,
            'printParams' => $printParams,
            'positionSummary' => $context['positionSummary'] ?? [],
            'signature' => $signature,
        ], 'admin');
    }

    public function assignmentLettersPrint(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $selectedTeacherId = (int) $request->query('guru_id', 0);

        $context = TeacherAssignmentLetterService::build(
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );

        if (empty($context['teachers'])) {
            Session::flash('error', 'Belum ada data penugasan guru yang dapat dicetak.');
            $query = $this->buildQueryString($request);

            $redirectUrl = 'tata-usaha/sk-penugasan';
            if ($query !== '') {
                $redirectUrl .= '?' . $query;
            }

            return $this->redirect($redirectUrl);
        }

        $defaults = TeacherAssignmentLetterService::defaultLetterConfig(
            $context['schoolYear'],
            $context['schoolProfile']
        );

        $letter = $this->resolveLetterPayload($request, $defaults);
        $signature = TeacherAssignmentLetterService::makeSignatureContext(
            $context,
            $letter,
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );
        $letter['signature_qr'] = $signature['status'] === 'approved'
            ? $this->makeSignatureQrPayload(
                $letter,
                $context['headmaster'],
                $context['schoolProfile'],
                $signature['verification_url'] ?? null
            )
            : '';

        return $this->render('tata-usaha/assignment-letters/print', [
            'title' => 'SK Penugasan Guru',
            'letter' => $letter,
            'teachers' => $context['teachers'],
            'schoolYear' => $context['schoolYear'],
            'period' => $context['period'],
            'schoolProfile' => $context['schoolProfile'],
            'headmaster' => $context['headmaster'],
            'metrics' => $context['metrics'],
            'positionSummary' => $context['positionSummary'] ?? [],
            'signature' => $signature,
        ], 'print');
    }

    public function manualAttendance(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $context = $this->buildManualAttendanceContext($request);
        $sheetPrintUrl = null;
        $coverPrintUrl = null;

        if (!empty($context['printGroups'])) {
            $printParams = $this->buildManualAttendancePrintParams($context);

            $sheetPrintUrl = base_url('tata-usaha/presensi-manual/cetak?' . http_build_query($printParams));
            $coverPrintUrl = base_url('tata-usaha/presensi-manual/sampul?' . http_build_query($printParams));
        }

        return $this->render('tata-usaha/manual-attendance/index', array_merge($context, [
            'title' => 'Cetak Absensi Manual',
            'pageTitle' => 'Cetak Absensi Manual',
            'activeMenu' => 'manual-attendance',
            'sheetPrintUrl' => $sheetPrintUrl,
            'coverPrintUrl' => $coverPrintUrl,
        ]), 'admin');
    }

    public function manualAttendancePrint(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $context = $this->buildManualAttendanceContext($request, false);

        if (empty($context['printGroups'])) {
            Session::flash('error', 'Data presensi tidak ditemukan untuk parameter yang dipilih.');

            return $this->redirect('tata-usaha/presensi-manual');
        }

        return $this->render('tata-usaha/manual-attendance/print', array_merge($context, [
            'title' => 'Lembar Absensi Manual',
            'paperSize' => 'f4',
            'schoolProfile' => SchoolProfile::first(),
        ]), 'print');
    }

    public function manualAttendanceCover(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        $context = $this->buildManualAttendanceContext($request, false);

        if (empty($context['printGroups'])) {
            Session::flash('error', 'Data presensi tidak ditemukan untuk parameter yang dipilih.');

            return $this->redirect('tata-usaha/presensi-manual');
        }

        return $this->render('tata-usaha/manual-attendance/cover', array_merge($context, [
            'title' => 'Sampul Presensi Manual',
            'paperSize' => 'f4',
            'schoolProfile' => SchoolProfile::first(),
        ]), 'print');
    }

    public function requestSignature(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureTataUsahaAccess()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'tata-usaha/sk-penugasan')) {
            return $response;
        }

        $selectedYearId = (int) $request->input('tahun_ajaran_id', 0);
        $selectedTeacherId = (int) $request->input('guru_id', 0);

        $context = TeacherAssignmentLetterService::build(
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );

        $defaults = TeacherAssignmentLetterService::defaultLetterConfig(
            $context['schoolYear'],
            $context['schoolProfile']
        );

        $letter = $this->resolveLetterPayload($request, $defaults);

        $signature = TeacherAssignmentLetterService::makeSignatureContext(
            $context,
            $letter,
            $selectedYearId > 0 ? $selectedYearId : null,
            $selectedTeacherId > 0 ? $selectedTeacherId : null
        );

        if (!($signature['available'] ?? false)) {
            Session::flash('error', $signature['reason'] ?? 'TTD digital belum tersedia untuk konfigurasi ini.');

            $query = $this->buildQueryString($request);
            $redirectUrl = 'tata-usaha/sk-penugasan';
            if ($query !== '') {
                $redirectUrl .= '?' . $query;
            }

            return $this->redirect($redirectUrl);
        }

        $requestedBy = (int) (auth()['id'] ?? 0);

        $record = DigitalDocumentSignature::ensure(
            (int) ($signature['year_id'] ?? 0),
            (string) ($signature['document_type'] ?? 'assignment_letter'),
            (string) ($signature['document_key'] ?? ''),
            (string) ($signature['document_title'] ?? 'SK Penugasan Guru'),
            (array) ($signature['payload'] ?? []),
            null,
            null,
            $requestedBy > 0 ? $requestedBy : null
        );

        if ($record === null) {
            Session::flash('error', 'Gagal mengajukan TTD digital. Coba lagi nanti.');
        } else {
            $status = (string) ($record['status'] ?? 'pending');

            if ($status === 'approved') {
                Session::flash('success', 'Data TTD digital berhasil diperbarui.');
            } elseif ($status === 'pending') {
                Session::flash('success', 'Permintaan TTD digital berhasil dikirim. Menunggu persetujuan kepala sekolah.');
            } elseif ($status === 'revoked') {
                Session::flash('warning', 'Dokumen berada pada status dicabut. Ajukan ulang setelah koordinasi dengan kepala sekolah.');
            } else {
                Session::flash('success', 'Data TTD digital diperbarui.');
            }
        }

        $query = $this->buildQueryString($request);
        $redirectUrl = 'tata-usaha/sk-penugasan';

        if ($query !== '') {
            $redirectUrl .= '?' . $query;
        }

        return $this->redirect($redirectUrl);
    }

    private function ensureTataUsahaAccess(): ?Response
    {
        $user = auth();

        if (\App\Support\UserModuleRules::allowsCurrentRequest($user, true)) {
            return null;
        }

        if (AcademicRoleGate::isTataUsaha($user)) {
            return null;
        }

        if (is_array($user) && ($user['role'] ?? '') === 'admin') {
            return null;
        }

        Session::flash('error', 'Anda tidak memiliki hak akses ke fitur ini.');

        return $this->redirect('dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManualAttendanceContext(Request $request, bool $fallbackToFirstClass = true): array
    {
        $yearOptions = SchoolYearContext::options();
        $selectedYearId = (int) $request->query('tahun_ajaran_id', 0);
        $schoolYear = null;

        if ($selectedYearId > 0) {
            $schoolYear = SchoolYear::find($selectedYearId);
        }

        if ($schoolYear === null) {
            $schoolYear = SchoolYearContext::resolve();
        }

        if ($schoolYear !== null && isset($schoolYear['id'])) {
            $selectedYearId = (int) $schoolYear['id'];
        } else {
            $selectedYearId = 0;
        }

        $attendanceType = $this->normalizeManualAttendanceType((string) $request->query('jenis', 'kelas'));
        $classes = $selectedYearId > 0 ? Classroom::allWithRelations($selectedYearId) : [];
        $levelOptions = $this->manualAttendanceLevelOptions($classes);
        $requestedClassIds = $this->normalizeManualAttendanceIdList($request->query('kelas_ids', []));
        $legacyClassId = (int) $request->query('kelas_id', 0);

        if (empty($requestedClassIds) && $legacyClassId > 0) {
            $requestedClassIds = [$legacyClassId];
        }

        $selectedLevel = trim((string) $request->query('tingkat', ''));

        if ($selectedLevel === '' && !empty($requestedClassIds)) {
            $firstRequestedClass = $this->findClassInList($classes, $requestedClassIds[0]);
            if ($firstRequestedClass !== null) {
                $selectedLevel = trim((string) ($firstRequestedClass['tingkat'] ?? ''));
            }
        }

        if ($selectedLevel === '' && !empty($levelOptions)) {
            $selectedLevel = (string) array_key_first($levelOptions);
        }

        $classesForSelectedLevel = $this->filterManualAttendanceClassesByLevel($classes, $selectedLevel);
        $selectedClassIds = [];
        $selectedClasses = [];
        $selectedClass = null;
        $selectedClassId = 0;
        $studentsByClass = [];
        $printGroups = [];
        $subjectAssignments = [];
        $selectedAssignmentId = (int) $request->query('pengampu_id', (int) $request->query('guru_mata_pelajaran_id', 0));
        $selectedSubjectAssignment = null;

        if ($attendanceType === 'kelas') {
            $levelClassIds = array_values(array_filter(array_map(
                static fn (array $class): int => (int) ($class['id'] ?? 0),
                $classesForSelectedLevel
            ), static fn (int $classId): bool => $classId > 0));

            $selectedClassIds = array_values(array_filter(
                array_values(array_unique($requestedClassIds)),
                static fn (int $classId): bool => in_array($classId, $levelClassIds, true)
            ));

            if (empty($selectedClassIds) && $fallbackToFirstClass && !empty($levelClassIds)) {
                $selectedClassIds = [$levelClassIds[0]];
            }

            foreach ($classesForSelectedLevel as $class) {
                $classId = (int) ($class['id'] ?? 0);
                if ($classId <= 0 || !in_array($classId, $selectedClassIds, true)) {
                    continue;
                }

                $selectedClasses[] = $class;
            }

            $selectedClass = $selectedClasses[0] ?? null;
            $selectedClassId = $selectedClass !== null ? (int) ($selectedClass['id'] ?? 0) : 0;

            $mergedStudents = [];
            $classSections = [];
            $classLabels = [];
            $classLabelsWithYear = [];

            foreach ($selectedClasses as $class) {
                $classId = (int) ($class['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $classYearId = (int) ($class['tahun_ajaran_id'] ?? $selectedYearId);
                $students = Student::byClass($classId, $classYearId > 0 ? $classYearId : $selectedYearId);
                $classLabel = $this->formatManualAttendanceClassLabel($class, false);
                $classShortLabel = $this->formatManualAttendanceClassShortLabel($class);
                $classLabelWithYear = $this->formatManualAttendanceClassLabel($class, true);
                $sectionStudents = [];

                foreach ($students as $student) {
                    $student['manual_attendance_class_id'] = $classId;
                    $student['manual_attendance_class_label'] = $classLabel;
                    $student['manual_attendance_class_short_label'] = $classShortLabel;
                    $sectionStudents[] = $student;
                    $mergedStudents[] = $student;
                }

                $studentsByClass[$classId] = $students;

                $classSections[] = [
                    'class' => $class,
                    'class_label' => $classLabel,
                    'class_short_label' => $classShortLabel,
                    'homeroom' => trim((string) ($class['wali_kelas_nama'] ?? '')),
                    'student_count' => count($students),
                    'students' => $sectionStudents,
                ];

                $classLabels[] = $classLabel;
                $classLabelsWithYear[] = $classLabelWithYear;
            }

            if (!empty($selectedClasses)) {
                $isMultiClass = count($selectedClasses) > 1;
                $mergedClassLabel = $isMultiClass
                    ? $this->formatManualAttendanceMergedClassLabel($selectedLevel, $classLabels)
                    : ($classLabels[0] ?? $this->formatManualAttendanceClassLabel($selectedClass, false));
                $mergedClassLabelWithYear = $isMultiClass
                    ? $this->formatManualAttendanceMergedClassLabelWithYear($mergedClassLabel, $schoolYear)
                    : ($classLabelsWithYear[0] ?? $this->formatManualAttendanceClassLabel($selectedClass, true));

                $printGroups[] = [
                    'attendance_type' => 'kelas',
                    'class' => $selectedClass,
                    'class_label' => $mergedClassLabel,
                    'class_label_with_year' => $mergedClassLabelWithYear,
                    'students' => $mergedStudents,
                    'class_sections' => $classSections,
                    'is_multi_class' => $isMultiClass,
                    'subject_assignment' => null,
                ];
            }
        } else {
            $selectedClassId = $legacyClassId > 0 ? $legacyClassId : ($requestedClassIds[0] ?? 0);
            $selectedClass = $this->findClassInList($classes, $selectedClassId);

            if ($selectedClass === null && $fallbackToFirstClass && !empty($classes)) {
                $selectedClass = $classes[0];
                $selectedClassId = (int) ($selectedClass['id'] ?? 0);
            }

            if ($selectedClass !== null) {
                $selectedClassId = (int) ($selectedClass['id'] ?? 0);
                $selectedLevel = trim((string) ($selectedClass['tingkat'] ?? $selectedLevel));
                $selectedClassIds = $selectedClassId > 0 ? [$selectedClassId] : [];
                $selectedClasses = [$selectedClass];
                $subjectAssignments = $this->manualAttendanceSubjectAssignments($selectedClassId, $selectedYearId);
                $selectedSubjectAssignment = $this->findManualAttendanceSubjectAssignment($subjectAssignments, $selectedAssignmentId);

                if ($selectedSubjectAssignment === null && $fallbackToFirstClass && !empty($subjectAssignments)) {
                    $selectedSubjectAssignment = $subjectAssignments[0];
                    $selectedAssignmentId = (int) ($selectedSubjectAssignment['id'] ?? 0);
                }

                $classYearId = (int) ($selectedClass['tahun_ajaran_id'] ?? $selectedYearId);
                $students = Student::byClass($selectedClassId, $classYearId > 0 ? $classYearId : $selectedYearId);
                $studentsByClass[$selectedClassId] = $students;

                if ($selectedSubjectAssignment !== null) {
                    $printGroups[] = [
                        'attendance_type' => 'mapel',
                        'class' => $selectedClass,
                        'class_label' => $this->formatManualAttendanceClassLabel($selectedClass, false),
                        'class_label_with_year' => $this->formatManualAttendanceClassLabel($selectedClass, true),
                        'students' => $students,
                        'subject_assignment' => $selectedSubjectAssignment,
                    ];
                }
            }
        }

        $students = [];
        if ($selectedClassId > 0 && isset($studentsByClass[$selectedClassId])) {
            $students = $studentsByClass[$selectedClassId];
        }

        $totalStudentCount = 0;
        foreach ($studentsByClass as $classStudents) {
            $totalStudentCount += count($classStudents);
        }

        return [
            'attendanceType' => $attendanceType,
            'attendanceTypeLabel' => $attendanceType === 'mapel' ? 'Presensi Mapel' : 'Presensi Kelas',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'schoolYear' => $schoolYear,
            'classes' => $classes,
            'levelOptions' => $levelOptions,
            'selectedLevel' => $selectedLevel,
            'classesForSelectedLevel' => $classesForSelectedLevel,
            'selectedClassIds' => $selectedClassIds,
            'selectedClassId' => $selectedClassId,
            'selectedClasses' => $selectedClasses,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'studentsByClass' => $studentsByClass,
            'totalStudentCount' => $totalStudentCount,
            'subjectAssignments' => $subjectAssignments,
            'selectedAssignmentId' => $selectedAssignmentId,
            'selectedSubjectAssignment' => $selectedSubjectAssignment,
            'printGroups' => $printGroups,
            'classLabel' => $this->formatManualAttendanceClassLabel($selectedClass, false),
            'classLabelWithYear' => $this->formatManualAttendanceClassLabel($selectedClass, true),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildManualAttendancePrintParams(array $context): array
    {
        $attendanceType = $this->normalizeManualAttendanceType((string) ($context['attendanceType'] ?? 'kelas'));
        $params = [
            'jenis' => $attendanceType,
            'tahun_ajaran_id' => (int) ($context['selectedYearId'] ?? 0),
        ];

        if ($attendanceType === 'mapel') {
            $params['kelas_id'] = (int) ($context['selectedClassId'] ?? 0);
            $params['pengampu_id'] = (int) ($context['selectedAssignmentId'] ?? 0);

            return $params;
        }

        $params['tingkat'] = (string) ($context['selectedLevel'] ?? '');
        $params['kelas_ids'] = array_values(array_filter(array_map(
            static fn ($id): int => (int) $id,
            is_array($context['selectedClassIds'] ?? null) ? $context['selectedClassIds'] : []
        ), static fn (int $id): bool => $id > 0));

        return $params;
    }

    private function normalizeManualAttendanceType(string $type): string
    {
        $type = strtolower(trim($type));

        return $type === 'mapel' ? 'mapel' : 'kelas';
    }

    /**
     * @return array<int>
     */
    private function normalizeManualAttendanceIdList(mixed $value): array
    {
        if (is_array($value)) {
            $rawValues = $value;
        } elseif (is_string($value) && str_contains($value, ',')) {
            $rawValues = explode(',', $value);
        } else {
            $rawValues = [$value];
        }

        $ids = array_map(static fn ($item): int => (int) $item, $rawValues);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     * @return array<string, string>
     */
    private function manualAttendanceLevelOptions(array $classes): array
    {
        $options = [];

        foreach ($classes as $class) {
            $level = trim((string) ($class['tingkat'] ?? ''));
            if ($level === '') {
                continue;
            }

            $options[$level] = 'Tingkat ' . $level;
        }

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     * @return array<int, array<string, mixed>>
     */
    private function filterManualAttendanceClassesByLevel(array $classes, string $level): array
    {
        if ($level === '') {
            return [];
        }

        return array_values(array_filter(
            $classes,
            static fn (array $class): bool => trim((string) ($class['tingkat'] ?? '')) === $level
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualAttendanceSubjectAssignments(int $classId, int $schoolYearId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $assignments = SubjectTeacher::allWithRelations($schoolYearId > 0 ? $schoolYearId : null);
        $filtered = [];

        foreach ($assignments as $assignment) {
            $assignmentClasses = is_array($assignment['classes'] ?? null) ? $assignment['classes'] : [];
            $hasClass = false;

            foreach ($assignmentClasses as $class) {
                if ((int) ($class['id'] ?? 0) === $classId) {
                    $hasClass = true;
                    break;
                }
            }

            if (!$hasClass) {
                continue;
            }

            $assignment['label'] = $this->formatManualAttendanceSubjectAssignmentLabel($assignment);
            $assignment['subject_label'] = $this->formatManualAttendanceSubjectLabel($assignment);
            $assignment['teacher_label'] = trim((string) ($assignment['guru_nama'] ?? 'Guru Pengampu'));
            $filtered[] = $assignment;
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $assignments
     */
    private function findManualAttendanceSubjectAssignment(array $assignments, int $assignmentId): ?array
    {
        if ($assignmentId <= 0) {
            return null;
        }

        foreach ($assignments as $assignment) {
            if ((int) ($assignment['id'] ?? 0) === $assignmentId) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function formatManualAttendanceSubjectAssignmentLabel(array $assignment): string
    {
        $subjectLabel = $this->formatManualAttendanceSubjectLabel($assignment);
        $teacherName = trim((string) ($assignment['guru_nama'] ?? ''));

        if ($teacherName !== '') {
            return $subjectLabel . ' | ' . $teacherName;
        }

        return $subjectLabel;
    }

    /**
     * @param array<string, mixed> $assignment
     */
    private function formatManualAttendanceSubjectLabel(array $assignment): string
    {
        $code = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
        $name = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'));

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $name !== '' ? $name : $code;
    }

    /**
     * @param array<int, array<string, mixed>> $classes
     */
    private function findClassInList(array $classes, int $classId): ?array
    {
        if ($classId <= 0) {
            return null;
        }

        foreach ($classes as $class) {
            if ((int) ($class['id'] ?? 0) === $classId) {
                return $class;
            }
        }

        return null;
    }

    private function formatManualAttendanceClassLabel(?array $class, bool $withYear = false): string
    {
        if ($class === null) {
            return '-';
        }

        $level = trim((string) ($class['tingkat'] ?? ''));
        $name = trim((string) ($class['nama'] ?? ''));
        $major = trim((string) ($class['jurusan_nama'] ?? ''));
        $year = trim((string) ($class['tahun_ajaran_nama'] ?? ''));
        $label = trim(($level !== '' ? $level . ' ' : '') . $name);

        if ($label === '') {
            $label = '-';
        }

        if ($major !== '') {
            $label .= ' - ' . $major;
        }

        if ($withYear && $year !== '') {
            $label .= ' (' . $year . ')';
        }

        return $label;
    }

    private function formatManualAttendanceClassShortLabel(?array $class): string
    {
        if ($class === null) {
            return '-';
        }

        $level = trim((string) ($class['tingkat'] ?? ''));
        $name = trim((string) ($class['nama'] ?? ''));
        $label = trim(($level !== '' ? $level . ' ' : '') . $name);

        return $label !== '' ? $label : $this->formatManualAttendanceClassLabel($class, false);
    }

    /**
     * @param array<int, string> $classLabels
     */
    private function formatManualAttendanceMergedClassLabel(string $level, array $classLabels): string
    {
        $classCount = count(array_filter($classLabels, static fn (string $label): bool => trim($label) !== ''));

        if ($classCount <= 1) {
            return $classLabels[0] ?? '-';
        }

        $level = trim($level);

        return ($level !== '' ? 'Tingkat ' . $level : 'Gabungan Kelas') . ' - ' . $classCount . ' rombel';
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     */
    private function formatManualAttendanceMergedClassLabelWithYear(string $classLabel, ?array $schoolYear): string
    {
        $yearName = trim((string) ($schoolYear['nama'] ?? ''));

        if ($yearName === '') {
            return $classLabel;
        }

        return $classLabel . ' (' . $yearName . ')';
    }

    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function resolveLetterPayload(Request $request, array $defaults): array
    {
        $number = trim((string) $request->query('nomor_sk', $defaults['number'] ?? ''));
        $subject = trim((string) $request->query('perihal', $defaults['subject'] ?? ''));
        $place = trim((string) $request->query('tempat', $defaults['place'] ?? ''));

        $signDateInput = (string) $request->query('tanggal_sk', $defaults['sign_date'] ?? date('Y-m-d'));
        $signDate = $this->normalizeDate($signDateInput, $defaults['sign_date'] ?? date('Y-m-d'));

        $effectiveStart = $this->normalizeDate(
            (string) $request->query('berlaku_mulai', $defaults['effective_start'] ?? ''),
            $defaults['effective_start'] ?? ''
        );

        $effectiveEnd = $this->normalizeDate(
            (string) $request->query('berlaku_sampai', $defaults['effective_end'] ?? ''),
            $defaults['effective_end'] ?? ''
        );

        [$menimbangList, $menimbangText] = $this->extractMultiline(
            $request->query('menimbang', null),
            $defaults['menimbang'] ?? []
        );

        [$mengingatList, $mengingatText] = $this->extractMultiline(
            $request->query('mengingat', null),
            $defaults['mengingat'] ?? []
        );

        [$menetapkanList, $menetapkanText] = $this->extractMultiline(
            $request->query('menetapkan', null),
            $defaults['menetapkan'] ?? []
        );

        [$tembusanList, $tembusanText] = $this->extractMultiline(
            $request->query('tembusan', null),
            $defaults['tembusan'] ?? [],
            false
        );

        return [
            'number' => $number,
            'subject' => $subject,
            'place' => $place,
            'sign_date' => $signDate,
            'sign_date_formatted' => TeacherAssignmentLetterService::formatDate($signDate),
            'effective_start' => $effectiveStart,
            'effective_start_formatted' => TeacherAssignmentLetterService::formatDate($effectiveStart),
            'effective_end' => $effectiveEnd,
            'effective_end_formatted' => TeacherAssignmentLetterService::formatDate($effectiveEnd),
            'menimbang' => $menimbangList,
            'menimbang_text' => $menimbangText,
            'mengingat' => $mengingatList,
            'mengingat_text' => $mengingatText,
            'menetapkan' => $menetapkanList,
            'menetapkan_text' => $menetapkanText,
            'tembusan' => $tembusanList,
            'tembusan_text' => $tembusanText,
        ];
    }

    /**
     * @param mixed $input
     * @param array<int, string> $fallback
     * @return array{0: array<int, string>, 1: string}
     */
    private function extractMultiline(mixed $input, array $fallback, bool $useFallbackWhenEmpty = true): array
    {
        if ($input === null) {
            $lines = $fallback;
            $text = implode("\n", $fallback);

            return [$lines, $text];
        }

        $text = trim((string) $input);
        $lines = $this->parseMultiline($text);

        if ($useFallbackWhenEmpty && empty($lines)) {
            $lines = $fallback;
            $text = implode("\n", $fallback);
        }

        return [$lines, $text];
    }

    /**
     * @return array<int, string>
     */
    private function parseMultiline(string $text): array
    {
        $rows = preg_split("/\r\n|\r|\n/", trim($text));

        if ($rows === false) {
            return [];
        }

        $rows = array_map(static function ($row): string {
            return trim((string) $row);
        }, $rows);

        $rows = array_values(array_filter($rows, static fn ($row): bool => $row !== ''));

        return $rows;
    }

    private function formatDateLabel(mixed $value, string $format = 'd/m/Y'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }

        return $date->format($format);
    }

    /**
     * @param array<string, mixed> $letter
     * @return array<string, mixed>
     */
    private function formatOutgoingLetterRecord(array $letter, ?array $year = null, ?bool $digitalSignatureEnabled = null, bool $includeAttachments = false): array
    {
        $type = LetterCatalog::findByCode((string) ($letter['kode_jenis'] ?? ''));

        $letter['jenis_label'] = $type !== null
            ? LetterCatalog::displayLabel($type)
            : (string) ($letter['jenis'] ?? '');
        $letter['sequence_label'] = str_pad((string) ($letter['nomor_urut'] ?? 0), 3, '0', STR_PAD_LEFT);
        $letter['tanggal_surat_formatted'] = $this->formatDateLabel($letter['tanggal_surat'] ?? null);
        $letter['tanggal_dicatat_formatted'] = $this->formatDateLabel($letter['tanggal_dicatat'] ?? null);
        $letter['created_at_formatted'] = $this->formatDateTime($letter['created_at'] ?? null);
        $letter['updated_at_formatted'] = $this->formatDateTime($letter['updated_at'] ?? null);
        $attachmentLabel = isset($letter['lampiran']) ? trim((string) $letter['lampiran']) : '';
        $letter['lampiran_label'] = $attachmentLabel;
        $letter['lampiran_total'] = $this->parseAttachmentCount($attachmentLabel);
        $tembusan = isset($letter['tembusan']) ? trim((string) $letter['tembusan']) : '';
        $letter['tembusan_lines'] = $tembusan !== '' ? $this->parseMultiline($tembusan) : [];
        $body = isset($letter['isi']) ? trim((string) $letter['isi']) : '';
        $sanitizedBody = $body !== '' ? $this->sanitizeLetterBodyHtml($body) : '';
        $letter['body_html'] = $sanitizedBody;
        $letter['body_text'] = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $sanitizedBody)));
        $letter['lampiran_records'] = [];

        if ($includeAttachments) {
            $letterId = isset($letter['id']) ? (int) $letter['id'] : 0;

            if ($letterId > 0) {
                $attachments = OutgoingLetterAttachment::listForLetter($letterId);
                $letter['lampiran_records'] = array_map(
                    static function (array $row): array {
                        return [
                            'number' => (int) ($row['nomor'] ?? 0),
                            'body_html' => (string) ($row['isi_html'] ?? ''),
                            'body_text' => (string) ($row['isi_text'] ?? ''),
                        ];
                    },
                    $attachments
                );
            }
        }
        $signature = $this->resolveOutgoingLetterSignature($letter);
        $letter['digital_signature'] = $signature;
        $letter['pdf'] = $this->buildOutgoingLetterPdfMeta($letter);

        $effectiveYear = $year;

        if ($effectiveYear === null) {
            $yearId = isset($letter['tahun_ajaran_id']) ? (int) $letter['tahun_ajaran_id'] : 0;

            if ($yearId > 0) {
                $effectiveYear = SchoolYear::find($yearId);
            }
        }

        $enabled = $digitalSignatureEnabled;

        if ($enabled === null && $effectiveYear !== null) {
            $enabled = (int) ($effectiveYear['digital_signature_enabled'] ?? 0) === 1;
        }

        $requiresHeadmasterDigital = false;

        if ($enabled === true && $effectiveYear !== null && $signature !== null) {
            $requiresHeadmasterDigital = $this->isHeadmasterSigner((string) ($letter['tanda_tangan'] ?? ''), $effectiveYear);
        }

        $letter['requires_headmaster_digital_signature'] = $requiresHeadmasterDigital;
        $letter['digital_signature_missing'] = $requiresHeadmasterDigital
            && ($signature === null || (string) ($letter['pdf_path'] ?? '') === '');

        return $letter;
    }

    /**
     * @param array<string, mixed> $letter
     * @return array<string, mixed>
     */
    private function formatIncomingLetterRecord(array $letter): array
    {
        $letter['tanggal_diterima_formatted'] = $this->formatDateLabel($letter['tanggal_diterima'] ?? null);
        $letter['tanggal_surat_formatted'] = $this->formatDateLabel($letter['tanggal_surat'] ?? null);
        $letter['created_at_formatted'] = $this->formatDateTime($letter['created_at'] ?? null);
        $letter['updated_at_formatted'] = $this->formatDateTime($letter['updated_at'] ?? null);

        return $letter;
    }

    private function normalizeAttachmentInput(string $value): ?string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 150);
        } else {
            $value = substr($value, 0, 150);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function parseAttachmentCount(string $value): int
    {
        $value = trim($value);

        if ($value === '' || !ctype_digit($value)) {
            return 0;
        }

        $count = (int) $value;

        if ($count <= 0) {
            return 0;
        }

        return min($count, self::MAX_OUTGOING_ATTACHMENTS);
    }

    /**
     * @param mixed $input
     * @return array<int, array<string, mixed>>
     */
    private function prepareAttachmentBodies(mixed $input, int $maxCount): array
    {
        if (!is_array($input) || $maxCount <= 0) {
            return [];
        }

        ksort($input);

        $records = [];
        $position = 1;

        foreach ($input as $value) {
            if ($position > $maxCount) {
                break;
            }

            $raw = is_string($value)
                ? $value
                : (is_scalar($value) ? (string) $value : '');

            $normalized = $this->normalizeBodyInput($raw);
            $sanitized = $normalized !== null
                ? $normalized
                : $this->sanitizeLetterBodyHtml('<p><br></p>');

            $plainText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $sanitized)));

            $records[] = [
                'number' => $position,
                'body_html' => $sanitized,
                'body_text' => $plainText,
            ];

            $position++;
        }

        $placeholderHtml = $this->sanitizeLetterBodyHtml('<p><br></p>');

        while ($position <= $maxCount) {
            $records[] = [
                'number' => $position,
                'body_html' => $placeholderHtml,
                'body_text' => '',
            ];

            $position++;
        }

        return $records;
    }

    private function normalizeLineInput(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = $this->parseMultiline($value);

        if (empty($lines)) {
            return null;
        }

        return implode("\n", $lines);
    }

    private function normalizeBodyInput(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (!str_contains($value, '<')) {
            $value = $this->convertPlainTextBodyToHtml($value);
        }

        $sanitized = $this->sanitizeLetterBodyHtml($value);

        return $sanitized === '' ? null : $sanitized;
    }

    private function sanitizeLetterBodyHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? '';

        if ($html === '') {
            return '';
        }

        $html = preg_replace('/\son[a-zA-Z]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\son[a-zA-Z]+=\'[^\']*\'/i', '', $html);
        $html = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace("/href\s*=\s*'\s*javascript:[^']*'/i", "href='#'", $html);

        return $html;
    }

    /**
     * @param array<int, string> $allowedTags
     * @param array<string, array<int, string>> $allowedAttributes
     */

    private function convertPlainTextBodyToHtml(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = str_replace('&nbsp;', ' ', $text);
        $lines = explode("\n", $text);
        $blocks = [];
        $listType = null;
        $listItems = [];

        $flushList = static function () use (&$blocks, &$listItems, &$listType): void {
            if ($listItems === []) {
                return;
            }

            $tag = $listType === 'ol' ? 'ol' : 'ul';
            $blocks[] = sprintf('<%1$s>%2$s</%1$s>', $tag, implode('', array_map(
                static fn (string $item): string => '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
                $listItems
            )));

            $listItems = [];
            $listType = null;
        };

        foreach ($lines as $line) {
            $normalizedLine = str_replace("\xc2\xa0", ' ', $line);
            $trimmed = trim($normalizedLine);

            if ($trimmed === '') {
                $flushList();

                continue;
            }

            if (preg_match('/^(?:-|\*|•)\s+(.+)$/u', $trimmed, $match)) {
                $content = trim((string) ($match[1] ?? ''));

                if ($content !== '') {
                    if ($listType !== 'ul') {
                        $flushList();
                        $listType = 'ul';
                    }

                    $listItems[] = $content;
                }

                continue;
            }

            if (preg_match('/^(?:[0-9]+|[a-z])[\.\)]\s+(.+)$/iu', $trimmed, $match)) {
                $content = trim((string) ($match[1] ?? ''));

                if ($content !== '') {
                    if ($listType !== 'ol') {
                        $flushList();
                        $listType = 'ol';
                    }

                    $listItems[] = $content;
                }

                continue;
            }

            $flushList();
            $blocks[] = '<p>' . htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $flushList();

        if ($blocks === []) {
            return '';
        }

        return implode("\n", $blocks);
    }

    /**
     * @param array<string, string|int> $extraParams
     */
    private function buildLettersRedirectUrl(?int $yearId = null, ?string $status = null, string $anchor = '', array $extraParams = []): string
    {
        $params = [];

        if ($yearId !== null && $yearId > 0) {
            $params['tahun_ajaran_id'] = $yearId;
        }

        if ($status !== null && $status !== '') {
            $params['status'] = $status;
        }

        foreach ($extraParams as $key => $value) {
            if ($key === '' || $value === '' || $value === null) {
                continue;
            }

            $params[$key] = $value;
        }

        $query = http_build_query($params);
        $url = 'tata-usaha/persuratan';

        if ($query !== '') {
            $url .= '?' . $query;
        }

        if ($anchor !== '') {
            $anchor = '#' . ltrim($anchor, '#');
            $url .= $anchor;
        }

        return $url;
    }

    private function isHeadmasterSigner(string $signer, array $year): bool
    {
        $normalized = strtolower(trim($signer));

        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'kepala sekolah')) {
            return true;
        }

        $headmasterId = isset($year['kepala_sekolah_id']) ? (int) $year['kepala_sekolah_id'] : 0;

        if ($headmasterId <= 0) {
            return false;
        }

        $headmaster = Teacher::find($headmasterId);

        if ($headmaster === null) {
            return false;
        }

        $headmasterName = strtolower(trim((string) ($headmaster['nama'] ?? '')));

        if ($headmasterName === '') {
            return false;
        }

        if ($normalized === $headmasterName) {
            return true;
        }

        return str_contains($normalized, $headmasterName);
    }

    /**
     * @param array<string, mixed> $letter
     * @param array<string, mixed>|null $letterType
     * @param array<string, mixed>|null $schoolProfile
     * @return array<string, mixed>
     */
    private function buildOutgoingLetterSignaturePayload(array $letter, ?array $letterType, ?array $schoolProfile): array
    {
        $tembusanRaw = isset($letter['tembusan']) ? (string) $letter['tembusan'] : '';
        $tembusanLines = $tembusanRaw !== '' ? $this->parseMultiline($tembusanRaw) : [];
        $typeLabel = $letterType !== null
            ? LetterCatalog::displayLabel($letterType)
            : (string) ($letter['jenis'] ?? '');

        return [
            'letter' => [
                'id' => $letter['id'] ?? null,
                'number' => $letter['nomor_surat'] ?? null,
                'type_code' => $letter['kode_jenis'] ?? null,
                'type_label' => $typeLabel,
                'subject' => $letter['perihal'] ?? null,
                'recipient' => $letter['tujuan'] ?? null,
                'unit_code' => $letter['unit_kode'] ?? null,
                'issued_at' => $letter['tanggal_surat'] ?? null,
                'recorded_at' => $letter['tanggal_dicatat'] ?? null,
                'signer' => $letter['tanda_tangan'] ?? null,
                'attachment' => $letter['lampiran'] ?? null,
                'tembusan' => $tembusanLines,
            ],
            'school' => [
                'name' => $schoolProfile['nama'] ?? null,
                'npsn' => $schoolProfile['npsn'] ?? null,
            ],
            'pdf' => $this->buildOutgoingLetterPdfMeta($letter),
        ];
    }

    /**
     * @param array<string, mixed> $letter
     * @return array<string, mixed>
     */
    private function buildOutgoingLetterPdfMeta(array $letter): array
    {
        $pdfPath = isset($letter['pdf_path']) ? (string) $letter['pdf_path'] : '';
        $signedPath = isset($letter['pdf_signed_path']) ? (string) $letter['pdf_signed_path'] : '';
        $options = OutgoingLetterPdfService::decodeSignatureOptions($letter['pdf_signature_options'] ?? null);

        return [
            'path' => $pdfPath !== '' ? $pdfPath : null,
            'url' => $pdfPath !== '' ? asset($pdfPath) : null,
            'signed_path' => $signedPath !== '' ? $signedPath : null,
            'signed_url' => $signedPath !== '' ? asset($signedPath) : null,
            'options' => $options,
        ];
    }

    /**
     * @param array<string, mixed> $letter
     * @return array<string, mixed>|null
     */
    private function resolveOutgoingLetterSignature(array $letter): ?array
    {
        $letterId = isset($letter['id']) ? (int) $letter['id'] : 0;
        $yearId = isset($letter['tahun_ajaran_id']) ? (int) $letter['tahun_ajaran_id'] : 0;

        if ($letterId <= 0 || $yearId <= 0) {
            return null;
        }

        $record = DigitalDocumentSignature::findByDocument($yearId, 'outgoing_letter', 'letter:' . $letterId);

        if ($record === null) {
            return null;
        }

        $status = (string) ($record['status'] ?? 'pending');

        $statusLabels = [
            'pending' => 'Menunggu TTD Kepala Sekolah',
            'approved' => 'TTD Disetujui Kepala Sekolah',
            'revoked' => 'TTD Dicabut',
        ];

        $statusClasses = [
            'pending' => 'border-amber-200 bg-amber-50 text-amber-600',
            'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-600',
            'revoked' => 'border-rose-200 bg-rose-50 text-rose-600',
        ];

        $token = (string) ($record['signature_token'] ?? '');
        $verificationUrl = $token !== '' && $status === 'approved'
            ? absolute_url('persuratan/validasi/' . $token)
            : null;
        $approverId = isset($record['approved_by']) ? (int) $record['approved_by'] : 0;
        $approverName = null;
        $approverRole = null;

        if ($approverId > 0) {
            $approver = User::find($approverId);

            if ($approver !== null) {
                $name = trim((string) ($approver['name'] ?? ''));

                if ($name === '' && isset($approver['username'])) {
                    $name = trim((string) $approver['username']);
                }

                $approverName = $name !== '' ? $name : null;

                $role = isset($approver['role']) ? trim((string) $approver['role']) : '';
                $approverRole = $role !== '' ? $role : null;
            }
        }

        $approvedAt = $record['approved_at'] ?? null;
        $approvedAtFormatted = $this->formatDateTime($approvedAt);
        $verificationToken = $token !== '' ? $token : null;

        return [
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? ucfirst($status),
            'status_class' => $statusClasses[$status] ?? 'border-slate-200 bg-slate-50 text-slate-500',
            'verification_url' => $verificationUrl,
            'requested_at' => $record['created_at'] ?? null,
            'approved_at' => $approvedAt,
            'approved_at_formatted' => $approvedAtFormatted,
            'approver_name' => $approverName,
            'approver_role' => $approverRole,
            'approver_id' => $approverId > 0 ? $approverId : null,
            'token' => $verificationToken,
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return string|false|null
     */
    private function storeLetterheadFile(?array $file, ?string $existingPath = null): string|false|null
    {
        $noFileError = \UPLOAD_ERR_NO_FILE;
        $fileError = $file['error'] ?? $noFileError;

        if ($file === null || $fileError === $noFileError) {
            return null;
        }

        if ($fileError !== \UPLOAD_ERR_OK) {
            return false;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return false;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg'], true)) {
            return null;
        }

        $imageInfo = @getimagesize($tmpName);

        if ($imageInfo === false || !in_array($imageInfo['mime'] ?? '', ['image/jpeg', 'image/pjpeg'], true)) {
            return null;
        }

        $stored = ManagedFileStorage::storeUploadedPublic($file, 'persuratan', 'kop-surat', 'kop-surat', 'jpg', [
            'existing_path' => $existingPath,
        ]);

        if ($stored === null) {
            return false;
        }

        return $stored;
    }

    private function deleteLetterheadFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        ManagedFileStorage::deletePublic($path);
    }

    private function deleteLetterPdfFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $absolute = public_path($path);

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (\Exception) {
            return $value;
        }

        return $date->format('d/m/Y H:i');
    }

    private function normalizeDate(string $value, ?string $fallback = null): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback !== null ? $fallback : '';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (\Exception) {
            return $fallback !== null ? $fallback : '';
        }

        return $date->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function retryOutgoingLetterInsert(array $attributes, \Throwable $exception, bool &$usedSchemaFallback): ?int
    {
        if (!$this->isUnknownColumnException($exception)) {
            $this->logOutgoingLetterInsertError($exception, $attributes);
            return null;
        }

        $columns = $this->resolveTableColumns('surat_keluar');
        if ($columns === []) {
            $this->logOutgoingLetterInsertError($exception, $attributes);
            return null;
        }

        $allowed = array_flip($columns);
        $filtered = array_intersect_key($attributes, $allowed);

        if ($filtered === [] || $filtered === $attributes) {
            $this->logOutgoingLetterInsertError($exception, $attributes);
            return null;
        }

        try {
            $createdId = OutgoingLetter::createAndReturnId($filtered);
        } catch (\Throwable $retryException) {
            $this->logOutgoingLetterInsertError($retryException, $filtered);
            return null;
        }

        if ($createdId !== null) {
            $usedSchemaFallback = true;
        }

        return $createdId;
    }

    private function buildOutgoingLetterInsertErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'base table or view not found') && str_contains($lowerMessage, 'surat_keluar')) {
            return 'Tabel surat keluar belum tersedia di database. Jalankan migrasi database atau import schema terbaru.';
        }

        if (str_contains($lowerMessage, 'unknown column')) {
            $column = $this->extractUnknownColumnName($message);
            $suffix = $column !== '' ? sprintf(' (kolom %s)', $column) : '';
            return 'Struktur tabel surat keluar belum sesuai dengan versi aplikasi. Jalankan migrasi database' . $suffix . '.';
        }

        if (str_contains($lowerMessage, 'duplicate entry')) {
            return 'Nomor surat sudah terpakai. Muat ulang halaman lalu coba simpan kembali.';
        }

        if ((bool) config('app.debug', false)) {
            return 'Gagal menyimpan surat keluar: ' . $message;
        }

        return 'Gagal menyimpan surat keluar. Coba lagi.';
    }

    private function extractUnknownColumnName(string $message): string
    {
        if (preg_match("/Unknown column '([^']+)'/i", $message, $matches) === 1) {
            return (string) $matches[1];
        }

        return '';
    }

    private function isUnknownColumnException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unknown column');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function logOutgoingLetterInsertError(\Throwable $exception, array $attributes): void
    {
        Log::channel('app')->error('Failed to insert outgoing letter.', [
            'error' => $exception->getMessage(),
            'year_id' => $attributes['tahun_ajaran_id'] ?? null,
            'letter_number' => $attributes['nomor_surat'] ?? null,
            'letter_type' => $attributes['kode_jenis'] ?? null,
            'sequence' => $attributes['nomor_urut'] ?? null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTableColumns(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $statement = Database::connection()->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        } catch (\Throwable $exception) {
            $cache[$table] = [];
            return [];
        }

        if ($statement === false) {
            $cache[$table] = [];
            return [];
        }

        $columns = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        $normalized = array_values(array_filter(array_map('strval', $columns)));
        $cache[$table] = $normalized;

        return $normalized;
    }

    private function buildQueryString(Request $request): string
    {
        $params = $request->all();
        unset($params['_token']);

        if (empty($params)) {
            return '';
        }

        return http_build_query($params);
    }

    /**
     * @param array<string, mixed>|null $headmaster
     * @param array<string, mixed>|null $schoolProfile
     */
    private function makeSignatureQrPayload(array $letter, ?array $headmaster, ?array $schoolProfile, ?string $verificationUrl = null): string
    {
        if ($verificationUrl !== null && $verificationUrl !== '') {
            return $verificationUrl;
        }

        $payload = [
            'document' => 'sk_penugasan_guru',
            'number' => $letter['number'] ?? null,
            'subject' => $letter['subject'] ?? null,
            'place' => $letter['place'] ?? null,
            'sign_date' => $letter['sign_date'] ?? null,
            'effective_start' => $letter['effective_start'] ?? null,
            'effective_end' => $letter['effective_end'] ?? null,
            'headmaster' => [
                'name' => $headmaster['name'] ?? null,
                'nip' => $headmaster['nip'] ?? null,
            ],
            'school' => [
                'name' => $schoolProfile['nama'] ?? null,
                'npsn' => $schoolProfile['npsn'] ?? null,
            ],
            'generated_at' => date('c'),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json !== false) {
            return $json;
        }

        $parts = array_filter([
            'SK Penugasan Guru',
            isset($letter['number']) && $letter['number'] !== '' ? 'Nomor: ' . $letter['number'] : null,
            isset($headmaster['name']) ? 'Kepala Sekolah: ' . $headmaster['name'] : null,
            isset($letter['sign_date']) && $letter['sign_date'] !== '' ? 'Tanggal: ' . $letter['sign_date'] : null,
        ]);

        return implode(' | ', $parts);
    }

    private function trimString(mixed $value, int $maxLength = 180): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $maxLength);
        } else {
            $text = substr($text, 0, $maxLength);
        }

        return trim($text);
    }
}
