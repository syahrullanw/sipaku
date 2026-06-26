<?php

namespace Modules\Finance\Controllers\Siswa;

use App\Models\StudentSaving;
use App\Services\Finance\CashflowService;
use App\Services\Finance\PaymentService;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class PaymentController extends Controller
{
    public function payFromSavings(Request $request, int $itemId): Response
    {
        if ($response = $this->guardRole('siswa')) {
            return $response;
        }

        if ($response = $this->guardCsrfOrRedirect($request, 'keuangan/siswa')) {
            return $response;
        }

        $itemId = max(0, (int) $itemId);
        if ($itemId <= 0) {
            Session::flash('error', 'Tagihan tidak ditemukan.');

            return $this->redirect('keuangan/siswa');
        }

        $user = $this->user();
        $studentId = $user !== null ? (int) ($user['student_id'] ?? 0) : 0;
        $userId = $user !== null ? (int) ($user['id'] ?? 0) : null;

        if ($studentId <= 0) {
            Session::flash('error', 'Akun siswa tidak terhubung dengan data siswa.');

            return $this->redirect('dashboard');
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'SELECT ti.*, t.kode AS tagihan_kode, t.judul AS tagihan_judul, t.tahun_ajaran_id
             FROM tagihan_item ti
             JOIN tagihan t ON t.id = ti.tagihan_id
             WHERE ti.id = :id AND ti.siswa_id = :student
             LIMIT 1'
        );

        if ($statement === false) {
            Session::flash('error', 'Gagal memuat data tagihan.');

            return $this->redirect('keuangan/siswa');
        }

        $statement->bindValue(':id', $itemId, \PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, \PDO::PARAM_INT);
        $statement->execute();
        $item = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($item === false) {
            Session::flash('error', 'Tagihan tidak ditemukan atau tidak terdaftar untuk akun Anda.');

            return $this->redirect('keuangan/siswa');
        }

        $remaining = (float) ($item['sisa_nominal'] ?? 0.0);
        if ($remaining <= 0.0) {
            Session::flash('info', 'Tagihan ini sudah lunas.');

            return $this->redirect('keuangan/siswa');
        }

        $itemStatus = (string) ($item['status'] ?? '');
        if ($itemStatus === 'menunggu_verifikasi') {
            Session::flash('info', 'Masih ada pembayaran untuk tagihan ini yang menunggu verifikasi. Mohon tunggu konfirmasi bendahara.');

            return $this->redirect('keuangan/siswa');
        }

        $schoolYearId = (int) ($item['tahun_ajaran_id'] ?? 0);
        if ($schoolYearId <= 0) {
            Session::flash('error', 'Tagihan tidak terkait dengan tahun ajaran aktif.');

            return $this->redirect('keuangan/siswa');
        }

        $savingRecord = StudentSaving::findByStudentAndYear($studentId, $schoolYearId);
        if ($savingRecord === null || ($savingRecord['status'] ?? 'nonaktif') !== 'aktif') {
            Session::flash('error', 'Anda tidak memiliki tabungan aktif untuk tahun ajaran ini.');

            return $this->redirect('keuangan/siswa');
        }

        $availableSavings = (float) ($savingRecord['saldo_terakhir'] ?? 0.0);
        if ($availableSavings + 0.0001 < $remaining) {
            Session::flash('error', 'Saldo tabungan Anda tidak mencukupi untuk membayar tagihan ini.');

            return $this->redirect('keuangan/siswa');
        }

        $now = date('Y-m-d H:i:s');
        $shouldManageTransaction = !$connection->inTransaction();
        if ($shouldManageTransaction) {
            $connection->beginTransaction();
        }

        try {
            $paymentId = PaymentService::record([
                'tagihan_item_id' => $itemId,
                'metode' => 'tabungan',
                'nominal' => $remaining,
                'status' => 'disetujui',
                'tanggal_bayar' => $now,
                'diverifikasi_oleh' => $userId,
                'diverifikasi_pada' => $now,
                'created_by' => $userId,
                'updated_by' => $userId,
                'catatan' => 'Pembayaran via tabungan oleh siswa',
            ]);

            $paymentCodeStatement = $connection->prepare('SELECT kode_transaksi FROM pembayaran WHERE id = :id LIMIT 1');
            $paymentCode = null;
            if ($paymentCodeStatement !== false) {
                $paymentCodeStatement->bindValue(':id', $paymentId, \PDO::PARAM_INT);
                $paymentCodeStatement->execute();
                $codeValue = $paymentCodeStatement->fetchColumn();
                if ($codeValue !== false) {
                    $paymentCode = (string) $codeValue;
                }
            }

            CashflowService::record('masuk', 'tagihan', $remaining, [
                'reference_id' => $paymentId,
                'reference_code' => $paymentCode,
                'description' => sprintf(
                    'Pembayaran tagihan %s oleh siswa (Saldo Tabungan)',
                    $item['tagihan_kode'] ?? ('#' . ($item['tagihan_id'] ?? ''))
                ),
                'user_id' => $userId,
                'recorded_at' => $now,
                'school_year_id' => $schoolYearId ?: null,
            ]);

            if ($shouldManageTransaction && $connection->inTransaction()) {
                $connection->commit();
            }

            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_0');
            FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
            FinanceCache::forget('kepsek_dashboard_revenue_' . date('Y_m'));

            Session::flash('success', 'Pembayaran tagihan berhasil diproses menggunakan saldo tabungan.');
        } catch (\Throwable $exception) {
            if ($shouldManageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            Session::flash('error', 'Gagal memproses pembayaran: ' . $exception->getMessage());
        }

        return $this->redirect('keuangan/siswa');
    }
}
