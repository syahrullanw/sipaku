<?php

namespace Modules\Finance\Controllers\Kaprodi;

use App\Models\Major;
use App\Models\PracticeProcurementRequest;
use App\Models\SchoolYear;
use App\Models\TeacherAcademicPosition;
use App\Services\ManagedFileStorage;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class PracticeProcurementController extends Controller
{
    private const LPJ_ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const LPJ_MAX_SIZE_BYTES = 5 * 1024 * 1024;

    public function index(Request $request): Response
    {
        if ($response = $this->guardKaprodi('dashboard')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        $activeYearId = $this->activeSchoolYearId();
        $activeYear = $activeYearId !== null ? SchoolYear::find($activeYearId) : null;

        $assignedMajorIds = $teacherId > 0
            ? TeacherAcademicPosition::teacherMajorIdsForRole($teacherId, 'kepala_prodi', $activeYearId)
            : [];

        $assignedMajors = [];
        foreach ($assignedMajorIds as $majorId) {
            $major = Major::find($majorId);
            if ($major !== null) {
                $assignedMajors[$majorId] = (string) ($major['nama'] ?? 'Jurusan');
            }
        }

        $requests = [];
        if ($activeYearId !== null && $teacherId > 0) {
            $requests = PracticeProcurementRequest::list([
                'teacher_id' => $teacherId,
                'year_id' => $activeYearId,
            ]);
        }

        $editId = (int) $request->query('edit', 0);
        $editing = null;
        if ($editId > 0) {
            $candidate = PracticeProcurementRequest::findDetailed($editId);
            if ($candidate !== null && (int) ($candidate['guru_id'] ?? 0) === $teacherId) {
                $editing = $candidate;
            }
        }

        return $this->render('finance/kaprodi/procurements/index', [
            'title' => 'Pengadaan Alat Praktik',
            'pageTitle' => 'Pengajuan Pengadaan Alat Praktik',
            'activeMenu' => 'finance-kaprodi-procurements',
            'activeYear' => $activeYear,
            'activeYearId' => $activeYearId,
            'assignedMajors' => $assignedMajors,
            'requests' => $requests,
            'editingRequest' => $editing,
            'statusLabels' => PracticeProcurementRequest::statusLabels(),
            'lpjAllowedMimes' => self::LPJ_ALLOWED_MIMES,
            'lpjMaxSizeKb' => (int) floor(self::LPJ_MAX_SIZE_BYTES / 1024),
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardKaprodi('keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        $activeYearId = $this->activeSchoolYearId();

        if ($teacherId <= 0 || $activeYearId === null) {
            Session::flash('error', 'Tahun ajaran aktif atau identitas guru tidak ditemukan.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        $allowedMajors = TeacherAcademicPosition::teacherMajorIdsForRole($teacherId, 'kepala_prodi', $activeYearId);

        if (empty($allowedMajors)) {
            Session::flash('error', 'Penetapan jurusan untuk kepala program studi belum tersedia.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        $requestId = (int) $request->input('request_id', 0);
        $jurusanId = (int) $request->input('jurusan_id', 0);
        $title = trim((string) $request->input('judul', ''));
        $purpose = trim((string) $request->input('tujuan', ''));
        $needs = trim((string) $request->input('rincian_kebutuhan', ''));
        $estimateRaw = (string) $request->input('estimasi', '0');
        $action = (string) $request->input('action', 'draft');

        if (!in_array($jurusanId, $allowedMajors, true)) {
            Session::flash('error', 'Anda tidak memiliki akses pada jurusan tersebut.');
            Session::flashInput($request->all());

            return $this->redirect($this->redirectUrlForRequest($requestId));
        }

        if ($title === '') {
            Session::flash('error', 'Judul pengadaan wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect($this->redirectUrlForRequest($requestId));
        }

        $estimate = max(0.0, $this->normalizeAmount($estimateRaw));
        if ($estimate <= 0.0) {
            Session::flash('error', 'Estimasi biaya harus lebih dari 0.');
            Session::flashInput($request->all());

            return $this->redirect($this->redirectUrlForRequest($requestId));
        }

        $now = date('Y-m-d H:i:s');
        $status = $action === 'submit'
            ? PracticeProcurementRequest::STATUS_SUBMITTED
            : PracticeProcurementRequest::STATUS_DRAFT;
        $submittedAt = $status === PracticeProcurementRequest::STATUS_SUBMITTED ? $now : null;

        if ($requestId > 0) {
            $existing = PracticeProcurementRequest::find($requestId);
            if ($existing === null || (int) ($existing['guru_id'] ?? 0) !== $teacherId) {
                Session::flash('error', 'Pengajuan tidak ditemukan.');

                return $this->redirect('keuangan/kaprodi/pengadaan');
            }

            $existingStatus = (string) ($existing['status'] ?? '');
            if (!in_array($existingStatus, [PracticeProcurementRequest::STATUS_DRAFT, PracticeProcurementRequest::STATUS_REJECTED], true)) {
                Session::flash('error', 'Pengajuan yang sudah dikirim tidak dapat diubah.');

                return $this->redirect('keuangan/kaprodi/pengadaan');
            }

            $payload = [
                'judul' => $title,
                'tujuan' => $purpose !== '' ? $purpose : null,
                'rincian_kebutuhan' => $needs !== '' ? $needs : null,
                'total_estimasi' => $estimate,
                'jurusan_id' => $jurusanId,
                'status' => $status,
                'submitted_at' => $submittedAt,
                'updated_at' => $now,
            ];

            if ($status === PracticeProcurementRequest::STATUS_DRAFT) {
                $payload['submitted_at'] = null;
            } else {
                $payload['reviewed_at'] = null;
                $payload['reviewed_by_user_id'] = null;
                $payload['review_note'] = null;
            }

            PracticeProcurementRequest::updateById($requestId, $payload);
            Session::flash('success', $status === PracticeProcurementRequest::STATUS_SUBMITTED
                ? 'Pengajuan berhasil dikirim ke kepala sekolah.'
                : 'Draft pengajuan berhasil disimpan.'
            );
        } else {
            PracticeProcurementRequest::create([
                'kode' => PracticeProcurementRequest::generateCode($activeYearId),
                'tahun_ajaran_id' => $activeYearId,
                'jurusan_id' => $jurusanId,
                'guru_id' => $teacherId,
                'judul' => $title,
                'tujuan' => $purpose !== '' ? $purpose : null,
                'rincian_kebutuhan' => $needs !== '' ? $needs : null,
                'total_estimasi' => $estimate,
                'status' => $status,
                'submitted_at' => $submittedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Session::flash('success', $status === PracticeProcurementRequest::STATUS_SUBMITTED
                ? 'Pengajuan berhasil dikirim.'
                : 'Draft pengajuan berhasil dibuat.'
            );
        }

        return $this->redirect('keuangan/kaprodi/pengadaan');
    }

    public function submit(Request $request, int $id): Response
    {
        if ($response = $this->guardKaprodi('keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);

        $existing = PracticeProcurementRequest::find($id);

        if ($existing === null || (int) ($existing['guru_id'] ?? 0) !== $teacherId) {
            Session::flash('error', 'Pengajuan tidak ditemukan.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        $status = (string) ($existing['status'] ?? '');
        if (!in_array($status, [PracticeProcurementRequest::STATUS_DRAFT, PracticeProcurementRequest::STATUS_REJECTED], true)) {
            Session::flash('error', 'Pengajuan ini sudah tidak dapat diajukan ulang.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        PracticeProcurementRequest::updateById($id, [
            'status' => PracticeProcurementRequest::STATUS_SUBMITTED,
            'submitted_at' => date('Y-m-d H:i:s'),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'review_note' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Pengajuan dikirim ke kepala sekolah.');

        return $this->redirect('keuangan/kaprodi/pengadaan');
    }

    public function report(Request $request, int $id): Response
    {
        if ($response = $this->guardKaprodi('keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/kaprodi/pengadaan')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = (int) ($user['teacher_id'] ?? 0);
        $record = PracticeProcurementRequest::find($id);

        if ($record === null || (int) ($record['guru_id'] ?? 0) !== $teacherId) {
            Session::flash('error', 'Pengajuan tidak ditemukan.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        if ((string) ($record['status'] ?? '') !== PracticeProcurementRequest::STATUS_FUNDED) {
            Session::flash('error', 'LPJ hanya dapat dikirim setelah pencairan bendahara.');

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        $description = trim((string) $request->input('lpj_deskripsi', ''));

        if ($description === '') {
            Session::flash('error', 'Deskripsi LPJ wajib diisi.');
            Session::flashInput($request->all());

            return $this->redirect('keuangan/kaprodi/pengadaan');
        }

        $attachment = $request->file('lpj_lampiran');
        $attachmentPath = null;
        if ($attachment !== null && $attachment['error'] !== UPLOAD_ERR_NO_FILE) {
            $attachmentPath = $this->storeLpjAttachment($attachment, (string) ($record['lpj_lampiran'] ?? ''));
            if ($attachmentPath === null) {
                Session::flashInput($request->all());

                return $this->redirect('keuangan/kaprodi/pengadaan');
            }
        }

        $now = date('Y-m-d H:i:s');

        $payload = [
            'status' => PracticeProcurementRequest::STATUS_REPORTED,
            'lpj_deskripsi' => $description,
            'lpj_submitted_at' => $now,
            'updated_at' => $now,
        ];

        if ($attachmentPath !== null) {
            $payload['lpj_lampiran'] = $attachmentPath;
        }

        PracticeProcurementRequest::updateById($id, $payload);

        Session::flash('success', 'LPJ berhasil dikirim. Terima kasih atas laporan pertanggungjawaban Anda.');

        return $this->redirect('keuangan/kaprodi/pengadaan');
    }

    private function redirectUrlForRequest(int $requestId): string
    {
        if ($requestId > 0) {
            return 'keuangan/kaprodi/pengadaan?edit=' . $requestId;
        }

        return 'keuangan/kaprodi/pengadaan';
    }

    private function normalizeAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d,\.]/', '', $raw);

        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif ($lastDot !== false && ($lastComma === false || $lastDot > $lastComma)) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(['.', ','], '', $clean);
        }

        return (float) $clean;
    }

    private function storeLpjAttachment(array $file, string $previousPath): ?string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $mime = '';
        if (isset($file['type']) && is_string($file['type'])) {
            $mime = $file['type'];
        }

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Session::flash('error', 'Lampiran LPJ tidak valid.');

            return null;
        }

        if ($size > self::LPJ_MAX_SIZE_BYTES) {
            Session::flash('error', 'Lampiran LPJ melebihi batas ' . (int) floor(self::LPJ_MAX_SIZE_BYTES / 1024 / 1024) . ' MB.');

            return null;
        }

        if (!in_array($mime, self::LPJ_ALLOWED_MIMES, true)) {
            Session::flash('error', 'Lampiran LPJ harus berupa PDF atau gambar (JPG/PNG).');

            return null;
        }

        $extension = pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION);
        $safeExtension = $extension !== '' ? strtolower($extension) : 'dat';
        $stored = ManagedFileStorage::storeUploadedStorage($file, 'keuangan', 'pengadaan-praktikum', 'lpj', $safeExtension, [
            'existing_path' => $previousPath !== '' ? $previousPath : null,
        ]);

        if ($stored === null) {
            Session::flash('error', 'Gagal menyimpan lampiran LPJ.');

            return null;
        }

        return $stored['relative'];
    }
}
