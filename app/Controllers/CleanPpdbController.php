<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Services\PpdbCleanupService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class CleanPpdbController extends Controller
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

        $yearOptions = SchoolYear::options();
        $selectedYearId = (int) $request->query('target_year', 0);

        if ($selectedYearId > 0 && !isset($yearOptions[$selectedYearId])) {
            $selectedYearId = 0;
        }

        if ($selectedYearId <= 0 && !empty($yearOptions)) {
            $selectedYearId = array_key_first($yearOptions);
        }

        $targetYear = $selectedYearId > 0 ? SchoolYear::find($selectedYearId) : null;

        $definitions = PpdbCleanupService::datasets();
        $service = new PpdbCleanupService();
        $counts = $service->countAll($selectedYearId);
        $report = Session::getFlash('clean_ppdb_report');

        return $this->render('admin/maintenance/clean-ppdb', [
            'title' => 'Clean Data PPDB',
            'pageTitle' => 'Clean Data PPDB',
            'activeMenu' => 'clean-data-ppdb',
            'yearOptions' => $yearOptions,
            'selectedYearId' => $selectedYearId,
            'targetYear' => $targetYear,
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

        if ($response = $this->guardCsrf($request, 'admin/clean-data/ppdb')) {
            return $response;
        }

        $yearOptions = SchoolYear::options();
        $targetYearId = (int) $request->input('target_year_id', 0);
        $targetYear = $targetYearId > 0 ? SchoolYear::find($targetYearId) : null;

        if ($targetYear === null) {
            Session::flash('error', 'Pilih tahun ajaran target yang valid sebelum membersihkan data PPDB.');

            return $this->redirect('admin/clean-data/ppdb');
        }

        $definitions = PpdbCleanupService::datasets();
        $availableKeys = array_keys($definitions);
        $selected = $request->input('datasets', []);
        $selected = array_map('strval', (array) $selected);
        $selected = array_values(array_unique(array_intersect($selected, $availableKeys)));

        if (empty($selected)) {
            Session::flash('warning', 'Pilih minimal satu jenis data PPDB yang ingin dibersihkan.');

            return $this->redirect('admin/clean-data/ppdb?target_year=' . $targetYearId);
        }

        $confirmation = strtoupper(trim((string) $request->input('confirmation', '')));

        if ($confirmation !== 'BERSIHKAN') {
            Session::flash('warning', 'Ketik "BERSIHKAN" pada kolom konfirmasi untuk melanjutkan pembersihan data PPDB.');

            return $this->redirect('admin/clean-data/ppdb?target_year=' . $targetYearId);
        }

        $service = new PpdbCleanupService();

        try {
            $before = $service->countAll($targetYearId);
            $result = $service->clean($targetYearId, $selected);
            $after = $service->countAll($targetYearId);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membersihkan data PPDB: ' . $exception->getMessage());

            return $this->redirect('admin/clean-data/ppdb?target_year=' . $targetYearId);
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

        $totalDeleted = array_sum(array_map(
            static fn (array $item): int => (int) ($item['deleted'] ?? 0),
            $result
        ));

        if ($totalDeleted > 0) {
            Session::flash('success', 'Data PPDB terpilih berhasil dibersihkan.');
        } else {
            Session::flash('warning', 'Tidak ada data PPDB yang dihapus. Periksa kembali pilihan Anda.');
        }

        Session::flash('clean_ppdb_report', [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'target_year' => [
                'id' => $targetYearId,
                'label' => $yearOptions[$targetYearId] ?? ($targetYear['nama'] ?? ''),
            ],
        ]);

        return $this->redirect('admin/clean-data/ppdb?target_year=' . $targetYearId);
    }
}
