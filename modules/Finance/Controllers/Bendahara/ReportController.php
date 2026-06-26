<?php

namespace Modules\Finance\Controllers\Bendahara;

use App\Services\Finance\CashflowReportService;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardBendahara()) {
            return $response;
        }
        $yearInput = trim((string) $request->input('year', ''));
        $selectedYear = preg_match('/^\d{4}$/', $yearInput) === 1 ? (int) $yearInput : null;

        $report = CashflowReportService::yearlyRecap($selectedYear);

        return $this->render('finance/bendahara/reports/index', [
            'title' => 'Rekap Keuangan',
            'pageTitle' => 'Rekap Arus Kas Bulanan',
            'activeMenu' => 'finance-bendahara-reports',
            'availableYears' => $report['available_years'],
            'selectedYear' => $report['selected_year'],
            'annualSummary' => $report['annual_summary'],
            'monthlyReports' => $report['monthly_reports'],
            'yearlySource' => $report['yearly_source'],
        ], 'admin');
    }
}
