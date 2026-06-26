<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Services\Finance\BillingCashService;
use App\Services\Finance\BillingService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\PaymentDetailService;
use App\Services\Finance\PaymentSlipExporter;
use App\Support\FinanceCache;
use App\Support\FinancePaymentSlipToken;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $connection = Database::connection();

        $pending = $connection->query(
            "SELECT p.*, ti.siswa_id, s.nama AS siswa_nama, s.status AS siswa_status, s.status_dapodik AS siswa_status_dapodik, t.judul AS tagihan_judul
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN siswa s ON s.id = ti.siswa_id
             WHERE p.status = 'menunggu_verifikasi'
             ORDER BY p.tanggal_bayar ASC"
        );

        $recent = $connection->query(
            "SELECT p.*, ti.siswa_id, s.nama AS siswa_nama, s.status AS siswa_status, s.status_dapodik AS siswa_status_dapodik, t.judul AS tagihan_judul
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN siswa s ON s.id = ti.siswa_id
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 20"
        );

        return $this->render('finance/bendahara/payments/index', [
            'title' => 'Verifikasi Pembayaran',
            'pageTitle' => 'Pembayaran Siswa',
            'activeMenu' => 'finance-bendahara-payments',
            'pendingPayments' => $pending !== false ? ($pending->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [],
            'recentPayments' => $recent !== false ? ($recent->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [],
        ], 'admin');
    }

    public function approve(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/pembayaran')) {
            return $response;
        }

        $connection = Database::connection();
        $paymentStatement = $connection->prepare(
            "SELECT p.*, ti.tagihan_id, t.tahun_ajaran_id
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             WHERE p.id = :id
             LIMIT 1"
        );

        if ($paymentStatement === false) {
            Session::flash('error', 'Gagal memuat data pembayaran.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $paymentStatement->bindValue(':id', $id, \PDO::PARAM_INT);
        $paymentStatement->execute();
        $payment = $paymentStatement->fetch(\PDO::FETCH_ASSOC);

        if ($payment === false) {
            Session::flash('error', 'Pembayaran tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        if (($payment['status'] ?? '') !== 'menunggu_verifikasi') {
            Session::flash('info', 'Pembayaran sudah diproses sebelumnya.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $user = $this->user();
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;
        $now = date('Y-m-d H:i:s');

        try {
            $connection->beginTransaction();

            $updated = $connection->prepare(
                "UPDATE pembayaran
                 SET status = 'disetujui',
                     diverifikasi_oleh = :user_id,
                     diverifikasi_pada = :verified_at,
                     updated_at = :updated_at
                 WHERE id = :id
                 LIMIT 1"
            );

            if ($updated === false) {
                throw new \RuntimeException('Gagal memperbarui status pembayaran.');
            }

            $updated->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $updated->bindValue(':verified_at', $now);
            $updated->bindValue(':updated_at', $now);
            $updated->bindValue(':id', $id, \PDO::PARAM_INT);

            if (!$updated->execute()) {
                throw new \RuntimeException('Gagal memperbarui status pembayaran.');
            }

            BillingService::synchronizeItemBalance((int) ($payment['tagihan_item_id'] ?? 0));
            BillingService::tryClosingBilling((int) ($payment['tagihan_id'] ?? 0));

            BillingCashService::increase(
                (int) ($payment['tagihan_id'] ?? 0),
                (float) ($payment['nominal'] ?? 0)
            );

            CashflowService::record('masuk', 'tagihan', (float) ($payment['nominal'] ?? 0), [
                'reference_id' => $id,
                'reference_code' => $payment['kode_transaksi'] ?? null,
                'description' => 'Pembayaran tagihan #' . ($payment['kode_transaksi'] ?? $id),
                'user_id' => $userId,
                'school_year_id' => (int) ($payment['tahun_ajaran_id'] ?? 0) ?: null,
            ]);

            \Core\Log::channel('finance')->info('Pembayaran disetujui', [
                'payment_id' => $id,
                'user_id' => $userId,
                'nominal' => (float) ($payment['nominal'] ?? 0),
            ]);

            $yearId = (int) ($payment['tahun_ajaran_id'] ?? 0);
            FinanceCache::forget('bendahara_dashboard_stats_' . ($yearId ?: 0));

            $connection->commit();

            Session::flash('success', 'Pembayaran berhasil disetujui.');
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal menyetujui pembayaran: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/bendahara/pembayaran');
    }

    public function reject(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/bendahara/pembayaran')) {
            return $response;
        }

        $user = $this->user();
        $connection = Database::connection();
        $payment = $connection->prepare(
            'SELECT p.status, t.tahun_ajaran_id
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             WHERE p.id = :id
             LIMIT 1'
        );

        if ($payment === false) {
            Session::flash('error', 'Gagal memuat data pembayaran.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $payment->bindValue(':id', $id, \PDO::PARAM_INT);
        $payment->execute();
        $current = $payment->fetch(\PDO::FETCH_ASSOC);

        if ($current === false) {
            Session::flash('error', 'Pembayaran tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        if (($current['status'] ?? '') !== 'menunggu_verifikasi') {
            Session::flash('info', 'Pembayaran sudah diproses sebelumnya.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $note = trim((string) $request->input('alasan', ''));

        $updated = $connection->prepare(
            "UPDATE pembayaran
             SET status = 'ditolak',
                 catatan = :catatan,
                 updated_at = :updated_at
             WHERE id = :id
             LIMIT 1"
        );

        if ($updated === false) {
            Session::flash('error', 'Gagal menolak pembayaran.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $updated->bindValue(':catatan', $note === '' ? null : $note);
        $updated->bindValue(':updated_at', date('Y-m-d H:i:s'));
        $updated->bindValue(':id', $id, \PDO::PARAM_INT);

        if ($updated->execute()) {
            \Core\Log::channel('finance')->warning('Pembayaran ditolak', [
                'payment_id' => $id,
                'user_id' => $user !== null ? (int) ($user['id'] ?? 0) : null,
                'note' => $note,
            ]);
            $yearId = (int) ($current['tahun_ajaran_id'] ?? 0);
            FinanceCache::forget('bendahara_dashboard_stats_' . ($yearId ?: 0));
            Session::flash('success', 'Pembayaran berhasil ditolak.');
        } else {
            Session::flash('error', 'Gagal menolak pembayaran.');
        }

        return $this->redirect('keuangan/bendahara/pembayaran');
    }

    public function slip(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara('keuangan/bendahara/pembayaran')) {
            return $response;
        }

        $payment = $this->fetchPaymentDetail($id);

        if ($payment === null) {
            Session::flash('error', 'Pembayaran tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        return $this->render('finance/bendahara/payments/slip', [
            'title' => 'Bukti Pembayaran',
            'payment' => $payment,
            'paperSize' => 'a4',
            'shareableUrl' => FinancePaymentSlipToken::buildPublicUrl($payment),
        ], 'print');
    }

    public function slipPdf(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara('keuangan/bendahara/pembayaran')) {
            return $response;
        }

        $payment = $this->fetchPaymentDetail($id);

        if ($payment === null) {
            Session::flash('error', 'Pembayaran tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        try {
            $pdfBinary = PaymentSlipExporter::generate($payment);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat PDF bukti pembayaran: ' . $exception->getMessage());

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $code = (string) ($payment['kode_transaksi'] ?? ('PAY-' . $id));
        $safeName = str_replace(['"', "'"], '', $code !== '' ? $code : ('PAY-' . $id));
        $filename = 'Slip-' . $safeName . '.pdf';

        return Response::make($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBinary),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function attachment(Request $request, int $id): Response
    {
        if ($response = $this->guardBendahara('keuangan/bendahara/pembayaran')) {
            return $response;
        }

        $payment = $this->fetchPaymentDetail($id);

        if ($payment === null || empty($payment['bukti_path'])) {
            Session::flash('error', 'Lampiran bukti pembayaran tidak ditemukan.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $absolutePath = $this->resolveReceiptAbsolutePath((string) $payment['bukti_path']);

        if ($absolutePath === null || !is_file($absolutePath)) {
            Session::flash('error', 'File lampiran tidak tersedia.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $mime = $this->detectReceiptMime($absolutePath);
        $content = file_get_contents($absolutePath);

        if ($content === false) {
            Session::flash('error', 'Gagal membaca lampiran bukti pembayaran.');

            return $this->redirect('keuangan/bendahara/pembayaran');
        }

        $filename = basename($absolutePath);
        $safeFilename = str_replace('"', '', $filename);
        $length = filesize($absolutePath);

        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $length !== false ? (string) $length : (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchPaymentDetail(int $id): ?array
    {
        return PaymentDetailService::findById($id);
    }

    protected function resolveReceiptAbsolutePath(string $relativePath): ?string
    {
        $normalized = trim(str_replace('\\', '/', $relativePath));

        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        return storage_path($normalized);
    }

    protected function detectReceiptMime(string $path): string
    {
        $mime = mime_content_type($path);

        if (!is_string($mime) || $mime === '') {
            return 'application/octet-stream';
        }

        return $mime;
    }
}
