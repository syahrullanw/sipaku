<?php

namespace Modules\Finance\Controllers\Guru;

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
        if ($response = $this->guardRole('guru')) {
            return $response;
        }

        $user = $this->user();
        $teacherId = $user !== null ? (int) ($user['teacher_id'] ?? 0) : 0;

        if ($teacherId <= 0) {
            Session::flash('error', 'Akun guru tidak terhubung dengan data guru.');

            return $this->redirect('dashboard');
        }

        $connection = Database::connection();

        $loansStatement = $connection->prepare(
            "SELECT * FROM kasbon_guru WHERE guru_id = :teacher ORDER BY created_at DESC LIMIT 10"
        );
        $loansStatement->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
        $loansStatement->execute();
        $loans = $loansStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $loanIds = array_map(static fn (array $loan) => (int) ($loan['id'] ?? 0), $loans);
        $installments = [];
        if (!empty($loanIds)) {
            $placeholders = implode(',', array_fill(0, count($loanIds), '?'));
            $installmentStatement = $connection->prepare(
                "SELECT * FROM kasbon_cicilan WHERE kasbon_id IN ({$placeholders}) ORDER BY jatuh_tempo ASC"
            );
            if ($installmentStatement !== false) {
                foreach ($loanIds as $index => $loanId) {
                    $installmentStatement->bindValue($index + 1, $loanId, \PDO::PARAM_INT);
                }
                $installmentStatement->execute();
                $installments = $installmentStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        }

        $activitiesStatement = $connection->prepare(
            "SELECT * FROM dana_kegiatan WHERE guru_id = :teacher ORDER BY created_at DESC LIMIT 10"
        );
        $activitiesStatement->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
        $activitiesStatement->execute();
        $activities = $activitiesStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $activityIds = array_map(static fn (array $activity): int => (int) ($activity['id'] ?? 0), $activities);
        $activityReports = AccountabilityReport::mapByEntity('dana_kegiatan', $activityIds);

        $unexpectedExpenses = UnexpectedExpense::historyForTeacher($teacherId, 10);
        $unexpectedIds = array_map(static fn (array $expense): int => (int) ($expense['id'] ?? 0), $unexpectedExpenses);
        $unexpectedReports = AccountabilityReport::mapByEntity('pengeluaran_tak_terduga', $unexpectedIds);

        $honorStatement = $connection->prepare(
            "SELECT * FROM honor_guru WHERE guru_id = :teacher ORDER BY tahun_ajaran_id DESC, periode DESC LIMIT 12"
        );
        $honorStatement->bindValue(':teacher', $teacherId, \PDO::PARAM_INT);
        $honorStatement->execute();
        $honors = $honorStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $this->render('finance/guru/dashboard', [
            'title' => 'Keuangan Guru',
            'pageTitle' => 'Ringkasan Keuangan',
            'activeMenu' => 'finance-guru-dashboard',
            'loans' => $loans,
            'installments' => $installments,
            'activities' => $activities,
            'activityReports' => $activityReports,
            'unexpectedExpenses' => $unexpectedExpenses,
            'unexpectedReports' => $unexpectedReports,
            'honors' => $honors,
        ], 'admin');
    }
}
