<?php

namespace Modules\Finance\Controllers\Guru;

use App\Models\Teacher;
use App\Models\TeacherLoan;
use App\Models\User;
use App\Services\Finance\ApprovalService;
use App\Services\Finance\LoanService;
use App\Services\WhatsappGatewayService;
use App\Support\SchoolYearContext;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class LoanController extends Controller
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
        $loans = $this->fetchLoans($teacherId);
        $activeLoans = TeacherLoan::findActiveByTeacher($teacherId);

        $submissionErrors = [];

        if ($schoolYearId === null || $schoolYearId <= 0) {
            $submissionErrors[] = 'Tahun ajaran aktif belum ditetapkan.';
        }

        if ($headmasterInfo['user_id'] === null) {
            $submissionErrors[] = 'Belum ada kepala sekolah yang ditetapkan pada tahun ajaran aktif.';
        }

        if (!empty($activeLoans)) {
            $submissionErrors[] = 'Masih terdapat kasbon yang belum diselesaikan atau menunggu persetujuan.';
        }

        return $this->render('finance/guru/loans/index', [
            'title' => 'Pengajuan Kasbon Guru',
            'pageTitle' => 'Pengajuan Kasbon',
            'activeMenu' => 'finance-guru-loans',
            'loans' => $loans,
            'canSubmit' => empty($submissionErrors),
            'submissionErrors' => $submissionErrors,
            'headmasterName' => $headmasterInfo['teacher_name'],
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $redirectUrl = 'keuangan/guru/kasbon';

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

        $existingLoans = TeacherLoan::findActiveByTeacher($teacherId);

        if (!empty($existingLoans)) {
            Session::flashInput($request->all());
            Session::flash('error', 'Pengajuan baru tidak dapat dibuat karena masih ada kasbon yang berjalan.');

            return $this->redirect($redirectUrl);
        }

        $rawAmount = (string) ($request->input('nominal') ?? '');
        $purpose = trim((string) ($request->input('tujuan') ?? ''));
        $tenor = (int) ($request->input('tenor') ?? 0);
        $notes = trim((string) ($request->input('catatan') ?? ''));

        $amount = $this->normalizeAmount($rawAmount);

        if ($amount <= 0) {
            Session::flashInput($request->all());
            Session::flash('error', 'Nominal pengajuan kasbon harus lebih dari nol.');

            return $this->redirect($redirectUrl);
        }

        if ($purpose === '') {
            Session::flashInput($request->all());
            Session::flash('error', 'Tuliskan tujuan penggunaan kasbon.');

            return $this->redirect($redirectUrl);
        }

        if ($tenor < 0) {
            $tenor = 0;
        }

        if ($tenor > 60) {
            $tenor = 60;
        }

        $teacher = Teacher::find($teacherId);

        if ($teacher === null) {
            Session::flash('error', 'Data guru tidak ditemukan.');

            return $this->redirect($redirectUrl);
        }

        try {
            $loanId = LoanService::createLoan([
                'guru_id' => $teacherId,
                'tahun_ajaran_id' => $schoolYearId,
                'tanggal_pengajuan' => date('Y-m-d'),
                'nominal_diminta' => $amount,
                'saldo_terhutang' => $amount,
                'tujuan' => $purpose,
                'tenor_bulan' => $tenor > 0 ? $tenor : null,
                'status' => 'menunggu_acc',
                'catatan' => $notes === '' ? null : $notes,
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            ApprovalService::request([
                'entity_type' => 'kasbon',
                'entity_id' => $loanId,
                'approver_id' => (int) $headmasterInfo['user_id'],
                'tanggal' => date('Y-m-d H:i:s'),
            ]);

            $this->notifyHeadmasterLoanSubmission($loanId, $teacher, $headmasterInfo);

            Session::flash('success', 'Pengajuan kasbon berhasil dikirim. Menunggu persetujuan kepala sekolah.');
        } catch (\Throwable $exception) {
            Session::flashInput($request->all());
            Session::flash('error', 'Gagal mengajukan kasbon: ' . $exception->getMessage());
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * @param array<string, mixed> $teacher
     * @param array<string, mixed> $headmasterInfo
     */
    private function notifyHeadmasterLoanSubmission(int $loanId, array $teacher, array $headmasterInfo): void
    {
        $phoneRaw = trim((string) ($headmasterInfo['phone'] ?? ''));

        if ($phoneRaw === '') {
            \Core\Log::channel('finance')->info('Nomor kepala sekolah kosong, notifikasi kasbon tidak dikirim.', [
                'loan_id' => $loanId,
                'headmaster_id' => $headmasterInfo['teacher_id'] ?? null,
            ]);

            return;
        }

        $loan = TeacherLoan::find($loanId);

        if ($loan === null) {
            \Core\Log::channel('finance')->warning('Data kasbon tidak ditemukan saat mengirim notifikasi kepala sekolah.', [
                'loan_id' => $loanId,
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

        $purpose = trim((string) ($loan['tujuan'] ?? ''));
        $amount = $this->formatCurrency((float) ($loan['nominal_diminta'] ?? 0));
        $code = (string) ($loan['kode'] ?? '');
        $codeLabel = $code !== '' ? 'Kasbon ' . $code : 'Kasbon #' . $loanId;

        $messageLines = [
            "Assalamu'alaikum Bapak/Ibu {$headmasterName},",
            '',
            "Ada pengajuan kasbon baru oleh {$teacherName} sebesar {$amount}.",
        ];

        if ($purpose !== '') {
            $messageLines[] = 'Tujuan: ' . $purpose . '.';
        }

        $messageLines[] = 'Kode pengajuan: ' . $codeLabel . '.';
        $messageLines[] = '';
        $messageLines[] = 'Silakan tinjau melalui portal kepala sekolah: ' . absolute_url('keuangan/kepala-sekolah/approval') . '.';
        $messageLines[] = '';
        $messageLines[] = 'Terima kasih.';

        $template = implode("\n", $messageLines);

        try {
            $result = WhatsappGatewayService::sendDetailed([
                'phone' => $phoneRaw,
                'template' => $template,
            ]);

            $context = [
                'loan_id' => $loanId,
                'phone' => $phoneRaw,
                'payload' => $result['payload'],
            ];

            if ($result['success']) {
                if ($result['queued']) {
                    $context['queued'] = true;
                    $context['duplicate'] = $result['duplicate'];
                    \Core\Log::channel('finance')->info('Notifikasi pengajuan kasbon dimasukkan ke antrian WhatsApp.', $context);
                } else {
                    \Core\Log::channel('finance')->info('Notifikasi pengajuan kasbon dikirim ke kepala sekolah.', $context);
                }

                return;
            }

            $context['error'] = $result['error'];
            $context['status'] = $result['status'];
            $context['queued'] = $result['queued'];
            $context['duplicate'] = $result['duplicate'];

            \Core\Log::channel('finance')->warning('Gagal mengirim notifikasi pengajuan kasbon ke kepala sekolah.', $context);
        } catch (\Throwable $exception) {
            \Core\Log::channel('finance')->error('Kesalahan saat mengirim notifikasi pengajuan kasbon ke kepala sekolah.', [
                'loan_id' => $loanId,
                'phone' => $phoneRaw,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLoans(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT * FROM kasbon_guru WHERE guru_id = :teacher ORDER BY created_at DESC, id DESC'
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
}
