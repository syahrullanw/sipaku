<?php

namespace Modules\Finance\Controllers\KepalaSekolah;

use App\Services\Finance\CashflowReportService;
use Core\Request;
use Core\Response;
use Modules\Finance\Controllers\Controller;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        if ($response = $this->guardHeadmaster()) {
            return $response;
        }

        $yearInput = trim((string) $request->input('year', ''));
        $selectedYear = preg_match('/^\d{4}$/', $yearInput) === 1 ? (int) $yearInput : null;

        $report = CashflowReportService::yearlyRecap($selectedYear);

        return $this->render('finance/kepsek/reports/index', [
            'title' => 'Rekap Keuangan Sekolah',
            'pageTitle' => 'Rekap Arus Kas Sekolah',
            'activeMenu' => 'finance-kepsek-reports',
            'availableYears' => $report['available_years'],
            'selectedYear' => $report['selected_year'],
            'annualSummary' => $report['annual_summary'],
            'monthlyReports' => $report['monthly_reports'],
            'yearlySource' => $report['yearly_source'],
        ], 'admin');
    }
}
