<?php

namespace Modules\Finance\Controllers\Guru;

use App\Models\AccountabilityReport;
use App\Models\ActivityFund;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Finance\ActivityFundService;
use App\Services\ManagedFileStorage;
use App\Services\WhatsappGatewayService;
use App\Support\SchoolYearContext;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;
use RuntimeException;

class ActivityFundController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak terhubung dengan data guru.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYearContext::resolve();
        $schoolYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        $headmasterInfo = $this->resolveHeadmasterInfo();

        $submissionErrors = [];

        if ($schoolYearId === null || $schoolYearId <= 0) {
            $submissionErrors[] = 'Tahun ajaran aktif belum ditetapkan.';
        }

        if ($headmasterInfo['user_id'] === null) {
            $submissionErrors[] = 'Belum ada kepala sekolah yang ditetapkan pada tahun ajaran aktif.';
        }

        $activities = $this->fetchActivities($teacherId);
        $activityIds = array_map(static fn (array $activity): int => (int) ($activity['id'] ?? 0), $activities);
        $reports = AccountabilityReport::mapByEntity('dana_kegiatan', $activityIds);

        return $this->render('finance/guru/activities/index', [
            'title' => 'Pengajuan Dana Kegiatan',
            'pageTitle' => 'Pengajuan Dana Kegiatan',
            'activeMenu' => 'finance-guru-activities',
            'activities' => $activities,
            'canSubmit' => empty($submissionErrors),
            'submissionErrors' => $submissionErrors,
            'headmasterName' => $headmasterInfo['teacher_name'],
            'maxAttachmentSizeKb' => (int) config('finance.max_receipt_size_kb', 2048),
            'allowedAttachmentMimes' => (array) config('finance.allowed_receipt_mimetypes', []),
            'reports' => $reports,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $redirectUrl = 'keuangan/guru/dana-kegiatan';

        if ($response = $this->guardCsrfOrRedirect($request, $redirectUrl)) {
            return $response;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak terhubung dengan data guru.');

            return $this->redirect('dashboard');
        }

        $activeYear = SchoolYearContext::resolve();
        $schoolYearId = $activeYear !== null ? (int) ($activeYear['id'] ?? 0) : null;

        if ($schoolYearId === null || $schoolYearId <= 0) {
            Session::flashInput($request->all());
            Session::flash('error', 'Tahun ajaran aktif belum ditetapkan.');

            return $this->redirect($redirectUrl);
        }

        $headmasterInfo = $this->resolveHeadmasterInfo();

        if ($headmasterInfo['user_id'] === null) {
            Session::flashInput($request->all());
            Session::flash('error', 'Belum ada kepala sekolah yang ditetapkan pada tahun ajaran aktif.');

            return $this->redirect($redirectUrl);
        }

        $teacher = Teacher::find($teacherId);

        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        $category = trim((string) ($request->input('kategori') ?? ''));
        $title = trim((string) ($request->input('judul') ?? ''));
        $description = trim((string) ($request->input('deskripsi') ?? ''));
        $rawEstimate = (string) ($request->input('estimasi') ?? '');
        $estimate = $this->normalizeAmount($rawEstimate);

        if ($category === '') {
            Session::flashInput($request->all());
            Session::flash('error', 'Kategori kegiatan harus diisi.');

            return $this->redirect($redirectUrl);
        }

        if ($title === '') {
            Session::flashInput($request->all());
            Session::flash('error', 'Judul kegiatan harus diisi.');

            return $this->redirect($redirectUrl);
        }

        if ($estimate <= 0) {
            Session::flashInput($request->all());
            Session::flash('error', 'Estimasi biaya harus lebih dari nol.');

            return $this->redirect($redirectUrl);
        }

        $files = $request->files();

        try {
            $upload = $this->handleAttachmentUpload($files['lampiran'] ?? null);
        } catch (RuntimeException $exception) {
            Session::flashInput($request->all());
            Session::flash('error', $exception->getMessage());

            return $this->redirect($redirectUrl);
        }

        $attachmentPath = $upload['relative'] ?? null;
        $attachmentAbsolute = $upload['absolute'] ?? null;

        try {
            $activityId = ActivityFundService::createRequest([
                'guru_id' => $teacherId,
                'tahun_ajaran_id' => $schoolYearId,
                'kategori' => $category,
                'judul' => $title,
                'deskripsi' => $description === '' ? null : $description,
                'estimasi_biaya' => $estimate,
                'status' => 'diajukan',
                'tanggal_pengajuan' => date('Y-m-d H:i:s'),
                'lampiran_path' => $attachmentPath,
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->notifyHeadmasterActivitySubmission($activityId, $teacher, $headmasterInfo);

            Session::flash('success', 'Pengajuan dana kegiatan berhasil dikirim. Menunggu verifikasi bendahara sebelum diajukan ke kepala sekolah.');
        } catch (\Throwable $exception) {
            if ($attachmentAbsolute !== null && is_file($attachmentAbsolute)) {
                @unlink($attachmentAbsolute);
            }

            Session::flashInput($request->all());
            Session::flash('error', 'Gagal mengirim pengajuan dana kegiatan: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @param array<string, mixed> $teacher
     * @param array<string, mixed> $headmasterInfo
     */
    private function notifyHeadmasterActivitySubmission(int $activityId, array $teacher, array $headmasterInfo): void
    {
        $phoneRaw = trim((string) ($headmasterInfo['phone'] ?? ''));

        if ($phoneRaw === '') {
            \Core\Log::channel('finance')->info('Nomor kepala sekolah kosong, notifikasi dana kegiatan tidak dikirim.', [
                'activity_id' => $activityId,
                'headmaster_id' => $headmasterInfo['teacher_id'] ?? null,
            ]);

            return;
        }

        $activity = ActivityFund::find($activityId);

        if ($activity === null) {
            \Core\Log::channel('finance')->warning('Data pengajuan dana kegiatan tidak ditemukan saat mengirim notifikasi kepala sekolah.', [
                'activity_id' => $activityId,
            ]);

            return;
        }

        $teacherName = trim((string) ($teacher['nama'] ?? ''));
        if ($teacherName === '') {
            $teacherName = 'Guru';
        }

        $headmasterName = trim((string) ($headmasterInfo['teacher_name'] ?? ''));
        if ($headmasterName === '') {
            $headmasterName = 'Kepala Sekolah';
        }

        $title = trim((string) ($activity['judul'] ?? ''));
        $estimate = $this->formatCurrency((float) ($activity['estimasi_biaya'] ?? 0));
        $code = trim((string) ($activity['kode'] ?? ''));
        $codeLabel = $code !== '' ? $code : 'Pengajuan #' . $activityId;

        $summaryLine = $teacherName . ' mengajukan dana kegiatan';
        if ($title !== '') {
            $summaryLine .= ' "' . $title . '"';
        }
        $summaryLine .= ' dengan estimasi ' . $estimate . '.';

        $messageLines = [
            "Assalamu'alaikum Bapak/Ibu {$headmasterName},",
            '',
            $summaryLine,
            'Kode pengajuan: ' . $codeLabel . '.',
            '',
            'Pengajuan akan diverifikasi bendahara sebelum menunggu persetujuan Anda.',
            'Pantau portal kepala sekolah: ' . absolute_url('keuangan/kepala-sekolah/approval') . '.',
            '',
            'Terima kasih.',
        ];

        $template = implode("\n", $messageLines);

        try {
            $result = WhatsappGatewayService::sendDetailed([
                'phone' => $phoneRaw,
                'template' => $template,
            ]);

            $context = [
                'activity_id' => $activityId,
                'phone' => $phoneRaw,
                'payload' => $result['payload'],
            ];

            if ($result['success']) {
                if ($result['queued']) {
                    $context['queued'] = true;
                    $context['duplicate'] = $result['duplicate'];
                    \Core\Log::channel('finance')->info('Notifikasi pengajuan dana kegiatan dimasukkan ke antrian WhatsApp.', $context);
                } else {
                    \Core\Log::channel('finance')->info('Notifikasi pengajuan dana kegiatan dikirim ke kepala sekolah.', $context);
                }

                return;
            }

            $context['error'] = $result['error'];
            $context['status'] = $result['status'];
            $context['queued'] = $result['queued'];
            $context['duplicate'] = $result['duplicate'];

            \Core\Log::channel('finance')->warning('Gagal mengirim notifikasi pengajuan dana kegiatan ke kepala sekolah.', $context);
        } catch (\Throwable $exception) {
            \Core\Log::channel('finance')->error('Kesalahan saat mengirim notifikasi pengajuan dana kegiatan ke kepala sekolah.', [
                'activity_id' => $activityId,
                'phone' => $phoneRaw,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActivities(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT * FROM dana_kegiatan WHERE guru_id = :teacher ORDER BY created_at DESC, id DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array{user_id: int|null, teacher_id: int|null, teacher_name: string|null, phone: string|null}
     */
    private function resolveHeadmasterInfo(): array
    {
        $activeYear = SchoolYearContext::resolve();

        if ($activeYear === null) {
            return ['user_id' => null, 'teacher_name' => null];
        }

        $headmasterTeacherId = (int) ($activeYear['kepala_sekolah_id'] ?? 0);

        if ($headmasterTeacherId <= 0) {
            return ['user_id' => null, 'teacher_name' => null];
        }

        $headmasterUser = User::findByTeacherId($headmasterTeacherId);
        $headmaster = Teacher::find($headmasterTeacherId);
        $headmasterPhone = $headmaster !== null ? trim((string) ($headmaster['telepon'] ?? '')) : '';

        return [
            'user_id' => $headmasterUser !== null ? (int) ($headmasterUser['id'] ?? 0) : null,
            'teacher_id' => $headmasterTeacherId > 0 ? $headmasterTeacherId : null,
            'teacher_name' => $headmaster !== null ? (string) ($headmaster['nama'] ?? null) : null,
            'phone' => $headmasterPhone !== '' ? $headmasterPhone : null,
        ];
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

    /**
     * @param array<string, mixed>|null $file
     * @return array{relative: string, absolute: string}|null
     */
    private function handleAttachmentUpload(?array $file): ?array
    {
        $noFileError = \UPLOAD_ERR_NO_FILE;
        $error = $file['error'] ?? $noFileError;

        if ($file === null || $error === $noFileError) {
            return null;
        }

        if ($error !== \UPLOAD_ERR_OK) {
            throw new RuntimeException('Gagal mengunggah lampiran. Silakan coba lagi.');
        }

        $tmpName = $file['tmp_name'] ?? '';

        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Lampiran yang diunggah tidak valid.');
        }

        $size = filesize($tmpName);
        $maxSizeKb = (int) config('finance.max_receipt_size_kb', 2048);

        if ($size !== false && $size > $maxSizeKb * 1024) {
            throw new RuntimeException('Lampiran melebihi batas ukuran ' . $maxSizeKb . ' KB.');
        }

        $allowedMimes = (array) config('finance.allowed_receipt_mimetypes', []);
        $mime = mime_content_type($tmpName);

        if (!is_string($mime) || $mime === '') {
            throw new RuntimeException('Tidak dapat menentukan tipe lampiran yang diunggah.');
        }

        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Lampiran harus berformat PDF atau gambar (JPG/PNG).');
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
                default => 'dat',
            };
        }

        $stored = ManagedFileStorage::storeUploadedStorage($file, 'keuangan', 'dana-kegiatan', 'act', $extension);

        if ($stored === null) {
            throw new RuntimeException('Gagal menyimpan lampiran pengajuan.');
        }

        return [
            'relative' => $stored['relative'],
            'absolute' => $stored['absolute'],
        ];
    }
}
