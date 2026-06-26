<?php

namespace Modules\Ppdb\Controllers\Admin;

use App\Models\PpdbPeriod;
use App\Models\PpdbRegistrant;
use Modules\Ppdb\Controllers\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->ensureAdminAccess()) {
            return $response;
        }

        $periodOptions = PpdbPeriod::options();
        $periodId = (int) $request->query('periode_id', 0);
        $finalStatus = (string) $request->query('status_final', '');

        if ($periodId <= 0 && !empty($periodOptions)) {
            $periodId = array_key_first($periodOptions);
        }

        $summary = [];
        $registrants = [];
        $period = null;

        if ($periodId > 0) {
            $period = PpdbPeriod::find($periodId);
            if ($period === null) {
                Session::flash('error', 'Periode PPDB tidak ditemukan.');

                return $this->redirect('ppdb/admin/laporan');
            }

            $summary = PpdbRegistrant::summaryForPeriod($periodId);
            $registrants = PpdbRegistrant::forPeriodWithFilters($periodId, $finalStatus !== '' ? $finalStatus : null);
        }

        return $this->render('ppdb/admin/reports/index', [
            'title' => 'Laporan PPDB',
            'pageTitle' => 'Laporan Akhir PPDB',
            'activeMenu' => 'ppdb-report',
            'periodOptions' => $periodOptions,
            'selectedPeriodId' => $periodId,
            'period' => $period,
            'summary' => $summary,
            'registrants' => $registrants,
            'finalStatusOptions' => PpdbRegistrant::statusFinalOptions(),
            'selectedFinalStatus' => $finalStatus,
        ], 'admin');
    }
}
