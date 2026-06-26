<?php

namespace Modules\Finance\Controllers\Siswa;

use App\Models\AccountabilityReport;
use App\Models\UnexpectedExpense;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use Modules\Finance\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardRole('siswa')) {
            return $response;
        }

        $user = $this->user();
        $studentId = $user !== null ? (int) ($user['student_id'] ?? 0) : 0;

        if ($studentId <= 0) {
            Session::flash('error', 'Akun siswa tidak terhubung dengan data siswa.');

            return $this->redirect('dashboard');
        }

        $connection = Database::connection();
        $hasPurchaseTable = $this->tableExists($connection, 'pembelian_perlengkapan');
        $hasPurchasePaymentTable = $hasPurchaseTable && $this->tableExists($connection, 'pembelian_pembayaran');

        $activeBillsStatement = $connection->prepare(
            "SELECT ti.*, t.judul, t.tanggal_jatuh_tempo, t.rutin_tipe, t.rutin_jadwal_berikutnya,
                    t.rutin_terakhir_generate, t.rutin_hari_mingguan, t.rutin_tanggal_bulanan, kt.nama AS kategori_nama
             FROM tagihan_item ti
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN kategori_tagihan kt ON kt.id = t.kategori_id
             WHERE ti.siswa_id = :student
               AND ti.status <> 'lunas'
               AND ti.status <> 'dibatalkan'
               AND t.status <> 'dibatalkan'
             ORDER BY COALESCE(t.tanggal_jatuh_tempo, t.rutin_jadwal_berikutnya) ASC, ti.id ASC"
        );

        $historyBillsStatement = $connection->prepare(
            "SELECT ti.*, t.judul, t.tanggal_jatuh_tempo, t.rutin_tipe, t.rutin_jadwal_berikutnya,
                    t.rutin_terakhir_generate, t.rutin_hari_mingguan, t.rutin_tanggal_bulanan, kt.nama AS kategori_nama
             FROM tagihan_item ti
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN kategori_tagihan kt ON kt.id = t.kategori_id
             WHERE ti.siswa_id = :student
               AND ti.status <> 'dibatalkan'
               AND t.status <> 'dibatalkan'
             ORDER BY ti.updated_at DESC, ti.id DESC
             LIMIT 20"
        );

        $paymentsStatement = $connection->prepare(
            "SELECT p.*, t.judul, kt.nama AS kategori_nama
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN kategori_tagihan kt ON kt.id = t.kategori_id
             WHERE ti.siswa_id = :student
             ORDER BY p.tanggal_bayar DESC"
        );

        $activePurchasesStatement = null;
        $purchaseHistoryStatement = null;
        if ($hasPurchaseTable) {
            $activePurchasesStatement = $connection->prepare(
                "SELECT * FROM pembelian_perlengkapan
                 WHERE siswa_id = :student AND status <> 'lunas'
                 ORDER BY created_at DESC"
            );

            $purchaseHistoryStatement = $connection->prepare(
                "SELECT * FROM pembelian_perlengkapan
                 WHERE siswa_id = :student AND status <> 'dibatalkan'
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 20"
            );
        }

        $purchasePaymentsStatement = null;
        if ($hasPurchasePaymentTable) {
            $purchasePaymentsStatement = $connection->prepare(
                "SELECT pay.*, p.item_label, p.kode AS purchase_kode
                 FROM pembelian_pembayaran pay
                 JOIN pembelian_perlengkapan p ON p.id = pay.pembelian_id
                 WHERE p.siswa_id = :student
                 ORDER BY pay.tanggal_bayar DESC
                 LIMIT 20"
            );
        }

        foreach ([
            $activeBillsStatement,
            $historyBillsStatement,
            $paymentsStatement,
            $activePurchasesStatement,
            $purchaseHistoryStatement,
            $purchasePaymentsStatement,
        ] as $statement) {
            if ($statement instanceof \PDOStatement) {
                $statement->bindValue(':student', $studentId, \PDO::PARAM_INT);
                $statement->execute();
            }
        }

        $activeBills = $activeBillsStatement instanceof \PDOStatement ? ($activeBillsStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        $billHistory = $historyBillsStatement instanceof \PDOStatement ? ($historyBillsStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        $payments = $paymentsStatement instanceof \PDOStatement ? ($paymentsStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        $activePurchasesRaw = $activePurchasesStatement instanceof \PDOStatement ? ($activePurchasesStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        $purchaseHistoryRaw = $purchaseHistoryStatement instanceof \PDOStatement ? ($purchaseHistoryStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        $purchasePaymentsRaw = $purchasePaymentsStatement instanceof \PDOStatement ? ($purchasePaymentsStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

        $activePurchases = array_map(fn (array $purchase): array => $this->transformPurchaseForBill($purchase), $activePurchasesRaw);
        $purchaseHistory = array_map(fn (array $purchase): array => $this->transformPurchaseForBill($purchase), $purchaseHistoryRaw);
        $purchasePayments = array_map(fn (array $payment): array => $this->transformPurchasePayment($payment), $purchasePaymentsRaw);

        $activeBills = array_merge($activeBills, $activePurchases);
        usort($activeBills, static function (array $a, array $b): int {
            $aTime = isset($a['tanggal_jatuh_tempo']) ? strtotime((string) $a['tanggal_jatuh_tempo']) : null;
            $bTime = isset($b['tanggal_jatuh_tempo']) ? strtotime((string) $b['tanggal_jatuh_tempo']) : null;
            $aTime = $aTime !== false ? $aTime : (isset($a['created_at']) ? strtotime((string) $a['created_at']) : 0);
            $bTime = $bTime !== false ? $bTime : (isset($b['created_at']) ? strtotime((string) $b['created_at']) : 0);

            return $aTime <=> $bTime;
        });

        $billHistory = array_merge($billHistory, $purchaseHistory);
        usort($billHistory, static function (array $a, array $b): int {
            $aTime = isset($a['updated_at']) ? strtotime((string) $a['updated_at']) : 0;
            $bTime = isset($b['updated_at']) ? strtotime((string) $b['updated_at']) : 0;

            return $bTime <=> $aTime;
        });

        $payments = array_merge($payments, $purchasePayments);
        usort($payments, static function (array $a, array $b): int {
            $aTime = isset($a['tanggal_bayar']) ? strtotime((string) $a['tanggal_bayar']) : 0;
            $bTime = isset($b['tanggal_bayar']) ? strtotime((string) $b['tanggal_bayar']) : 0;

            return $bTime <=> $aTime;
        });

        $purchaseOutstanding = array_reduce($activePurchases, static function (float $carry, array $purchase): float {
            return $carry + (float) ($purchase['sisa_nominal'] ?? 0.0);
        }, 0.0);

        $schoolYearId = $this->activeSchoolYearId();
        $savingStatement = null;
        $savingTransactionsStatement = null;

        if ($schoolYearId !== null) {
            $savingStatement = $connection->prepare(
                "SELECT * FROM tabungan_siswa WHERE siswa_id = :student AND tahun_ajaran_id = :year LIMIT 1"
            );
            $savingStatement->bindValue(':student', $studentId, \PDO::PARAM_INT);
            $savingStatement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $savingStatement->execute();

            $savingTransactionsStatement = $connection->prepare(
                "SELECT * FROM tabungan_transaksi
                 WHERE tabungan_id = (
                     SELECT id FROM tabungan_siswa WHERE siswa_id = :student AND tahun_ajaran_id = :year LIMIT 1
                 )
                 ORDER BY tanggal DESC, id DESC
                 LIMIT 20"
            );
            if ($savingTransactionsStatement !== false) {
                $savingTransactionsStatement->bindValue(':student', $studentId, \PDO::PARAM_INT);
                $savingTransactionsStatement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
                $savingTransactionsStatement->execute();
            }
        }

        $savingAccount = null;
        if ($savingStatement !== null) {
            $record = $savingStatement->fetch(\PDO::FETCH_ASSOC);
            $savingAccount = $record === false ? null : $record;
        }
        $savingTransactions = $savingTransactionsStatement !== null
            ? ($savingTransactionsStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [])
            : [];

        $outstandingAmount = 0.0;
        foreach ($activeBills as $bill) {
            $outstandingAmount += (float) ($bill['sisa_nominal'] ?? 0.0);
        }

        $savingBalance = (float) ($savingAccount['saldo_terakhir'] ?? 0.0);

        $unexpectedExpenses = UnexpectedExpense::historyForStudent($studentId, 10);
        $unexpectedIds = array_map(static fn (array $expense): int => (int) ($expense['id'] ?? 0), $unexpectedExpenses);
        $unexpectedReports = AccountabilityReport::mapByEntity('pengeluaran_tak_terduga', $unexpectedIds);

        return $this->render('finance/siswa/dashboard', [
            'title' => 'Keuangan Siswa',
            'pageTitle' => 'Ringkasan Keuangan',
            'activeMenu' => 'finance-siswa-dashboard',
            'activeBills' => $activeBills,
            'billHistory' => $billHistory,
            'payments' => $payments,
            'savingAccount' => $savingAccount,
            'savingTransactions' => $savingTransactions,
            'outstandingAmount' => $outstandingAmount,
            'savingBalance' => $savingBalance,
            'unexpectedExpenses' => $unexpectedExpenses,
            'unexpectedReports' => $unexpectedReports,
            'activePurchases' => $activePurchases,
            'purchaseHistory' => $purchaseHistory,
            'purchasePayments' => $purchasePayments,
            'purchaseOutstanding' => $purchaseOutstanding,
        ], 'admin');
    }

    /**
     * @param array<string, mixed> $purchase
     * @return array<string, mixed>
     */
    private function transformPurchaseForBill(array $purchase): array
    {
        $total = (float) ($purchase['nominal'] ?? 0.0);
        $paid = (float) ($purchase['nominal_terbayar'] ?? 0.0);
        $remaining = (float) ($purchase['sisa_nominal'] ?? ($total - $paid));

        return array_merge($purchase, [
            'judul' => $purchase['item_label'] ?? 'Pembelian Perlengkapan',
            'kategori_nama' => 'Pembelian Perlengkapan',
            'nominal' => $total,
            'sisa_nominal' => max(0.0, $remaining),
            'status' => $purchase['status'] ?? 'menunggu_pembayaran',
            'tanggal_jatuh_tempo' => $purchase['tanggal_jatuh_tempo'] ?? $purchase['created_at'] ?? null,
            'is_purchase' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    private function transformPurchasePayment(array $payment): array
    {
        return [
            'id' => (int) ($payment['id'] ?? 0),
            'judul' => $payment['item_label'] ?? 'Pembelian Perlengkapan',
            'kategori_nama' => 'Pembelian Perlengkapan',
            'kode_transaksi' => $payment['kode_transaksi'] ?? null,
            'metode' => $payment['metode'] ?? 'cash',
            'nominal' => (float) ($payment['nominal'] ?? 0.0),
            'tanggal_bayar' => $payment['tanggal_bayar'] ?? null,
            'is_purchase' => true,
        ];
    }

    private function tableExists(\PDO $connection, string $table): bool
    {
        try {
            $statement = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
            );

            if ($statement === false) {
                return false;
            }

            $statement->bindValue(':table', $table, \PDO::PARAM_STR);
            $statement->execute();

            return (bool) $statement->fetchColumn();
        } catch (\PDOException $exception) {
            return false;
        }
    }
}
