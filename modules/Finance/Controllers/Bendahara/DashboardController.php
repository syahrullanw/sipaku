<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Models\ActivityFund;
use App\Models\Cashflow;
use App\Models\Payment;
use App\Models\TeacherHonor;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }

        $schoolYearId = $this->activeSchoolYearId();
        $connection = Database::connection();

        $stats = FinanceCache::remember('bendahara_dashboard_stats_' . ($schoolYearId ?? 0), 300, static function () use ($connection, $schoolYearId): array {
            $result = [
                'total_billings' => 0,
                'outstanding_amount' => 0.0,
                'active_savings' => 0.0,
                'cash_balance' => Cashflow::latestBalance(),
                'active_billing_cash' => 0.0,
                'billing_cashes' => [],
            ];

            if ($schoolYearId === null) {
                return $result;
            }

            $totalBillings = $connection->prepare('SELECT COUNT(*) FROM tagihan WHERE tahun_ajaran_id = :year');
            $outstanding = $connection->prepare(
                'SELECT COALESCE(SUM(ti.sisa_nominal), 0)
                 FROM tagihan_item ti
                 JOIN tagihan t ON t.id = ti.tagihan_id
                 WHERE t.tahun_ajaran_id = :year AND ti.status <> \'lunas\''
            );
            $totalSavings = $connection->prepare(
                'SELECT COALESCE(SUM(saldo_terakhir), 0)
                 FROM tabungan_siswa
                 WHERE tahun_ajaran_id = :year AND status = \'aktif\''
            );
            $activeBillingCash = $connection->prepare(
                'SELECT COALESCE(SUM(tk.saldo_akhir), 0)
                 FROM tagihan_kas tk
                 JOIN tagihan t ON t.id = tk.tagihan_id
                 WHERE t.tahun_ajaran_id = :year AND t.status = \'aktif\''
            );
            $billingCashBreakdown = $connection->prepare(
                'SELECT
                    t.id,
                    t.judul,
                    (tk.saldo_akhir - tk.saldo_masuk + tk.saldo_keluar) AS saldo_awal,
                    tk.saldo_akhir,
                    tk.saldo_masuk,
                    tk.saldo_keluar,
                    tk.updated_at
                 FROM tagihan_kas tk
                 JOIN tagihan t ON t.id = tk.tagihan_id
                 WHERE t.tahun_ajaran_id = :year AND t.status = \'aktif\'
                 ORDER BY t.judul ASC'
            );

            if ($totalBillings === false || $outstanding === false || $totalSavings === false || $activeBillingCash === false || $billingCashBreakdown === false) {
                return $result;
            }

            $totalBillings->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $totalBillings->execute();
            $result['total_billings'] = (int) ($totalBillings->fetchColumn() ?: 0);

            $outstanding->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $outstanding->execute();
            $result['outstanding_amount'] = (float) ($outstanding->fetchColumn() ?: 0.0);

            $totalSavings->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $totalSavings->execute();
            $result['active_savings'] = (float) ($totalSavings->fetchColumn() ?: 0.0);

            $activeBillingCash->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $activeBillingCash->execute();
            $result['active_billing_cash'] = (float) ($activeBillingCash->fetchColumn() ?: 0.0);

            $billingCashBreakdown->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $billingCashBreakdown->execute();
            $rows = $billingCashBreakdown->fetchAll(\PDO::FETCH_ASSOC);
            $result['billing_cashes'] = $rows === false ? [] : $rows;

            $trendStart = (new \DateTimeImmutable('today'))->sub(new \DateInterval('P6D'));
            $trendStmt = $connection->prepare(
                'SELECT DATE(p.tanggal_bayar) AS day, COALESCE(SUM(p.nominal), 0) AS total
                 FROM pembayaran p
                 JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
                 JOIN tagihan t ON t.id = ti.tagihan_id
                 WHERE p.status = :status
                   AND p.tanggal_bayar >= :start
                   AND t.tahun_ajaran_id = :year
                 GROUP BY DATE(p.tanggal_bayar)
                 ORDER BY DATE(p.tanggal_bayar) ASC'
            );

            $dailyTotals = [];
            if ($trendStmt !== false) {
                $trendStmt->bindValue(':status', 'disetujui');
                $trendStmt->bindValue(':start', $trendStart->format('Y-m-d'), \PDO::PARAM_STR);
                $trendStmt->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
                $trendStmt->execute();
                $trendRows = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);
                if ($trendRows !== false) {
                    foreach ($trendRows as $row) {
                        $dayKey = (string) ($row['day'] ?? '');
                        $dailyTotals[$dayKey] = (float) ($row['total'] ?? 0.0);
                    }
                }
            }

            $labels = [];
            $values = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $trendStart->add(new \DateInterval('P' . $i . 'D'));
                $dayKey = $day->format('Y-m-d');
                $labels[] = $day->format('D, d M');
                $values[] = $dailyTotals[$dayKey] ?? 0.0;
            }

            $result['weekly_payment_labels'] = $labels;
            $result['weekly_payment_values'] = $values;

            return $result;
        });

        if (!isset($stats['billing_cashes']) || !is_array($stats['billing_cashes'])) {
            $stats['billing_cashes'] = [];
        }

        $stats['billing_cashes'] = $this->filterActiveBillingCashes($connection, $stats['billing_cashes']);
        $stats['active_billing_cash'] = array_reduce(
            $stats['billing_cashes'],
            static function (float $carry, array $cash): float {
                return $carry + (float) ($cash['saldo_akhir'] ?? 0.0);
            },
            0.0
        );

        $pendingPayments = array_slice(Payment::pendingVerification(), 0, 5);
        $stats['pending_payments'] = count($pendingPayments);

        $pendingLoansStatement = $connection->query(
            "SELECT * FROM kasbon_guru WHERE status IN ('diajukan','diverifikasi_bendahara','menunggu_acc') ORDER BY created_at ASC LIMIT 5"
        );
        $pendingLoans = $pendingLoansStatement !== false
            ? $pendingLoansStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []
            : [];

        $pendingActivities = array_slice(ActivityFund::pendingApprovals(), 0, 5);
        $pendingHonors = array_slice(TeacherHonor::pendingApproval(), 0, 5);

        $recentCashflowStatement = $connection->query(
            'SELECT * FROM arus_kas ORDER BY tanggal DESC, id DESC LIMIT 10'
        );
        $recentCashflow = $recentCashflowStatement !== false
            ? $recentCashflowStatement->fetchAll(\PDO::FETCH_ASSOC) ?: []
            : [];

        $pendingApprovalsStatement = $connection->prepare(
            'SELECT COUNT(*) FROM keuangan_approval WHERE status = :status'
        );
        $pendingApprovals = 0;
        if ($pendingApprovalsStatement !== false) {
            $pendingApprovalsStatement->bindValue(':status', 'menunggu');
            $pendingApprovalsStatement->execute();
            $pendingApprovals = (int) ($pendingApprovalsStatement->fetchColumn() ?: 0);
        }
        $stats['pending_approvals'] = $pendingApprovals;

        return $this->render('finance/bendahara/dashboard', [
            'title' => 'Dashboard Bendahara',
            'pageTitle' => 'Dashboard Keuangan',
            'activeMenu' => 'finance-bendahara-dashboard',
            'stats' => $stats,
            'pendingPayments' => $pendingPayments,
            'pendingLoans' => $pendingLoans,
            'pendingActivities' => $pendingActivities,
            'pendingHonors' => $pendingHonors,
            'recentCashflow' => $recentCashflow,
            'pendingApprovals' => $pendingApprovals,
        ], 'admin');
    }

    /**
     * Pastikan hanya kas dari tagihan dan kategori yang benar-benar masih aktif yang tampil.
     *
     * @param array<int, array<string, mixed>> $billingCashes
     * @return array<int, array<string, mixed>>
     */
    private function filterActiveBillingCashes(\PDO $connection, array $billingCashes): array
    {
        if (empty($billingCashes)) {
            return [];
        }

        $billingIds = [];
        foreach ($billingCashes as $cash) {
            $id = (int) ($cash['id'] ?? 0);
            if ($id > 0) {
                $billingIds[$id] = $id;
            }
        }

        if (empty($billingIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($billingIds), '?'));
        $statusQuery = <<<SQL
SELECT t.id, t.status AS tagihan_status, kt.status AS kategori_status
FROM tagihan t
JOIN kategori_tagihan kt ON kt.id = t.kategori_id
WHERE t.id IN ($placeholders)
SQL;

        $statusStatement = $connection->prepare($statusQuery);

        if ($statusStatement === false) {
            return array_values($billingCashes);
        }

        $position = 1;
        foreach ($billingIds as $id) {
            $statusStatement->bindValue($position, $id, \PDO::PARAM_INT);
            $position++;
        }

        if (!$statusStatement->execute()) {
            return array_values($billingCashes);
        }

        $statusRows = $statusStatement->fetchAll(\PDO::FETCH_ASSOC);

        if ($statusRows === false) {
            return array_values($billingCashes);
        }

        $statusMap = [];
        foreach ($statusRows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId <= 0) {
                continue;
            }

            $statusMap[$rowId] = [
                'tagihan_status' => (string) ($row['tagihan_status'] ?? ''),
                'kategori_status' => (string) ($row['kategori_status'] ?? ''),
            ];
        }

        $filtered = array_filter(
            $billingCashes,
            static function (array $cash) use ($statusMap): bool {
                $cashId = (int) ($cash['id'] ?? 0);
                if ($cashId <= 0) {
                    return false;
                }

                $status = $statusMap[$cashId] ?? null;
                if ($status === null) {
                    return false;
                }

                if (($status['tagihan_status'] ?? '') !== 'aktif') {
                    return false;
                }

                if (($status['kategori_status'] ?? 'aktif') !== 'aktif') {
                    return false;
                }

                return true;
            }
        );

        return array_values($filtered);
    }
}
