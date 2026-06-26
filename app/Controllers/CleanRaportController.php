<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Services\RaportCleanupService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class CleanRaportController extends Controller
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
        $definitions = RaportCleanupService::datasets();
        $service = new RaportCleanupService();

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $counts = $activeYearId > 0 ? $service->countAll($activeYearId) : [];

        foreach ($definitions as $key => $_) {
            if (!isset($counts[$key])) {
                $counts[$key] = 0;
            }
        }

        $report = Session::getFlash('clean_raport_report');

        return $this->render('admin/maintenance/clean-raport', [
            'title' => 'Clean Data Rapor',
            'pageTitle' => 'Clean Data Rapor',
            'activeMenu' => 'clean-data-report',
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

        if ($response = $this->guardCsrf($request, 'admin/clean-data/raport')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        if ($activeYear === null || $activeYearId <= 0) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif. Aktifkan tahun ajaran terlebih dahulu sebelum membersihkan data raport.');

            return $this->redirect('admin/clean-data/raport');
        }

        $definitions = RaportCleanupService::datasets();
        $availableKeys = array_keys($definitions);

        $selected = $request->input('datasets', []);
        $selected = array_map('strval', (array) $selected);
        $selected = array_values(array_unique(array_intersect($selected, $availableKeys)));

        if (empty($selected)) {
            Session::flash('warning', 'Pilih minimal satu jenis data yang ingin dibersihkan.');

            return $this->redirect('admin/clean-data/raport');
        }

        $confirmation = strtoupper(trim((string) $request->input('confirmation', '')));

        if ($confirmation !== 'BERSIHKAN') {
            Session::flash('warning', 'Ketik "BERSIHKAN" pada kolom konfirmasi untuk melanjutkan pembersihan data.');

            return $this->redirect('admin/clean-data/raport');
        }

        $service = new RaportCleanupService();

        try {
            $before = $service->countAll($activeYearId);
            $result = $service->clean($activeYearId, $selected);
            $after = $service->countAll($activeYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membersihkan data raport: ' . $exception->getMessage());

            return $this->redirect('admin/clean-data/raport');
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

        $totalDeleted = array_sum(array_map(static fn (array $item): int => (int) ($item['deleted'] ?? 0), $result));

        if ($totalDeleted > 0) {
            Session::flash('success', 'Data raport terpilih berhasil dibersihkan.');
        } else {
            Session::flash('warning', 'Tidak ada baris data yang dihapus. Periksa kembali data raport pada tahun ajaran aktif.');
        }

        Session::flash('clean_raport_report', [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $summary,
        ]);

        return $this->redirect('admin/clean-data/raport');
    }
}
