<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Services\FinanceCleanupService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class CleanFinanceController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $definitions = FinanceCleanupService::datasets();

        $service = new FinanceCleanupService();
        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $counts = $service->countAll($activeYearId);

        $report = Session::getFlash('clean_finance_report');

        return $this->render('admin/maintenance/clean-finance', [
            'title' => 'Clean Data Keuangan',
            'pageTitle' => 'Clean Data Keuangan',
            'activeMenu' => 'clean-data-finance',
            'activeYear' => $activeYear,
            'datasetDefinitions' => $definitions,
            'datasetCounts' => $counts,
            'report' => is_array($report) ? $report : null,
        ], 'admin');
    }

    public function clean(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/clean-data/keuangan')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif. Aktifkan tahun ajaran terlebih dahulu sebelum membersihkan data keuangan.');

            return $this->redirect('admin/clean-data/keuangan');
        }

        $definitions = FinanceCleanupService::datasets();
        $availableKeys = array_keys($definitions);

        $selected = $request->input('datasets', []);
        $selected = array_map('strval', (array) $selected);
        $selected = array_values(array_unique(array_intersect($selected, $availableKeys)));

        $billingTargets = $this->parseBillingTargets((string) $request->input('target_billings', ''));

        if (empty($selected) && empty($billingTargets)) {
            Session::flash('warning', 'Pilih minimal satu jenis data keuangan atau masukkan tagihan yang ingin dibatalkan.');

            return $this->redirect('admin/clean-data/keuangan');
        }

        $confirmation = strtoupper(trim((string) $request->input('confirmation', '')));
        if ($confirmation !== 'BERSIHKAN') {
            Session::flash('warning', 'Ketik "BERSIHKAN" pada kolom konfirmasi untuk melanjutkan pembersihan data.');

            return $this->redirect('admin/clean-data/keuangan');
        }

        $service = new FinanceCleanupService();
        $before = $service->countAll($activeYearId);

        try {
            $result = $service->clean($activeYearId, $selected, $billingTargets);
            $after = $service->countAll($activeYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membersihkan data keuangan: ' . $exception->getMessage());

            return $this->redirect('admin/clean-data/keuangan');
        }

        $summary = [];

        foreach ($selected as $key) {
            $summary[$key] = [
                'label' => $definitions[$key]['label'] ?? $key,
                'before' => $before[$key] ?? 0,
                'deleted' => $result[$key]['deleted'] ?? 0,
                'remaining' => $after[$key] ?? 0,
            ];
        }

        $cancelSummary = is_array($result['billing_cancellations'] ?? null) ? $result['billing_cancellations'] : [];
        $totalDeleted = array_sum(array_map(static fn (array $item): int => (int) ($item['deleted'] ?? 0), $result));

        if ($totalDeleted > 0) {
            Session::flash('success', 'Data keuangan terpilih berhasil dibersihkan.');
        } else {
            Session::flash('warning', 'Tidak ada baris data keuangan yang dihapus. Periksa kembali pilihan Anda.');
        }

        Session::flash('clean_finance_report', [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'cancel_summary' => $cancelSummary,
        ]);

        return $this->redirect('admin/clean-data/keuangan');
    }

    /**
     * @param string $input
     * @return array<int, string>
     */
    private function parseBillingTargets(string $input): array
    {
        $raw = preg_split('/[,\s;]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $value) {
            $target = trim((string) $value);
            if ($target === '') {
                continue;
            }

            if (!in_array($target, $normalized, true)) {
                $normalized[] = $target;
            }
        }

        return $normalized;
    }
}
