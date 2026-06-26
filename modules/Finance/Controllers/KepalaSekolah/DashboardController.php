<?php

namespace Modules\Finance\Controllers\KepalaSekolah;

use App\Models\Cashflow;
use App\Support\FinanceCache;
use Core\Database;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardHeadmaster()) {
            return $response;
        }

        $connection = Database::connection();
        $schoolYearId = $this->activeSchoolYearId();

        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth = date('Y-m-t 23:59:59');

        $monthKey = date('Y_m');
        $summary = FinanceCache::remember('kepsek_dashboard_summary_' . $monthKey, 300, static function () use ($connection, $startOfMonth, $endOfMonth): array {
            $result = [
                'cash_balance' => Cashflow::latestBalance(),
                'monthly_income' => 0.0,
                'monthly_expense' => 0.0,
                'pending_approvals' => 0,
            ];

            $incomeStatement = $connection->prepare(
                'SELECT COALESCE(SUM(nominal), 0) FROM arus_kas WHERE tipe = \'masuk\' AND tanggal BETWEEN :start AND :end'
            );
            $expenseStatement = $connection->prepare(
                'SELECT COALESCE(SUM(nominal), 0) FROM arus_kas WHERE tipe = \'keluar\' AND tanggal BETWEEN :start AND :end'
            );
            $pendingApprovalsStatement = $connection->prepare(
                'SELECT COUNT(*) FROM keuangan_approval WHERE status = :status'
            );

            foreach ([$incomeStatement, $expenseStatement] as $statement) {
                if ($statement !== false) {
                    $statement->bindValue(':start', $startOfMonth);
                    $statement->bindValue(':end', $endOfMonth);
                    $statement->execute();
                }
            }

            if ($incomeStatement !== false) {
                $result['monthly_income'] = (float) ($incomeStatement->fetchColumn() ?: 0.0);
            }
            if ($expenseStatement !== false) {
                $result['monthly_expense'] = (float) ($expenseStatement->fetchColumn() ?: 0.0);
            }
            if ($pendingApprovalsStatement !== false) {
                $pendingApprovalsStatement->bindValue(':status', 'menunggu');
                $pendingApprovalsStatement->execute();
                $result['pending_approvals'] = (int) ($pendingApprovalsStatement->fetchColumn() ?: 0);
            }

            return $result;
        });

        $topRevenue = FinanceCache::remember('kepsek_dashboard_revenue_' . $monthKey, 300, static function () use ($connection, $startOfMonth, $endOfMonth): array {
            $statement = $connection->prepare(
                'SELECT kt.nama AS kategori, COALESCE(SUM(p.nominal), 0) AS total
                 FROM pembayaran p
                 JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
                 JOIN tagihan t ON t.id = ti.tagihan_id
                 JOIN kategori_tagihan kt ON kt.id = t.kategori_id
                 WHERE p.status = \'disetujui\'
                 AND p.tanggal_bayar BETWEEN :start AND :end
                 GROUP BY kt.nama
                 ORDER BY total DESC
                 LIMIT 5'
            );

            if ($statement === false) {
                return [];
            }

            $statement->bindValue(':start', $startOfMonth);
            $statement->bindValue(':end', $endOfMonth);
            $statement->execute();

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return $rows === false ? [] : $rows;
        });

        $loanSummary = FinanceCache::remember('kepsek_dashboard_loan_' . ($schoolYearId ?? 0), 300, static function () use ($connection, $schoolYearId): array {
            if ($schoolYearId === null) {
                return [];
            }

            $statement = $connection->prepare(
                "SELECT status, COUNT(*) AS total
                 FROM kasbon_guru
                 WHERE tahun_ajaran_id = :year
                 GROUP BY status"
            );

            if ($statement === false) {
                return [];
            }

            $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return $rows === false ? [] : $rows;
        });

        return $this->render('finance/kepsek/dashboard', [
            'title' => 'Keuangan Sekolah',
            'pageTitle' => 'Ringkasan Kepala Sekolah',
            'activeMenu' => 'finance-kepsek-dashboard',
            'summary' => $summary,
            'topRevenue' => $topRevenue,
            'loanSummary' => $loanSummary,
        ], 'admin');
    }
}
