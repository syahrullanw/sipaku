<?php

namespace App\Controllers;

use App\Services\ActivityLogCleanupService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class CleanActivityLogController extends Controller
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

        $service = new ActivityLogCleanupService();
        $definitions = ActivityLogCleanupService::datasets();
        $counts = $service->countAll();
        $report = Session::getFlash('clean_activity_log_report');

        return $this->render('admin/maintenance/clean-logs', [
            'title' => 'Clean Log Pengguna',
            'pageTitle' => 'Clean Log Aktivitas Pengguna',
            'activeMenu' => 'clean-data-logs',
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

        if ($response = $this->guardCsrf($request, 'admin/clean-data/log')) {
            return $response;
        }

        $definitions = ActivityLogCleanupService::datasets();
        $availableKeys = array_keys($definitions);

        $selected = array_values(array_unique(array_intersect(
            array_map('strval', (array) $request->input('datasets', [])),
            $availableKeys
        )));

        if (empty($selected)) {
            Session::flash('warning', 'Pilih minimal satu jenis log yang ingin dibersihkan.');

            return $this->redirect('admin/clean-data/log');
        }

        $confirmation = strtoupper(trim((string) $request->input('confirmation', '')));
        if ($confirmation !== 'BERSIHKAN') {
            Session::flash('warning', 'Ketik "BERSIHKAN" untuk mengonfirmasi penghapusan log.');

            return $this->redirect('admin/clean-data/log');
        }

        $service = new ActivityLogCleanupService();
        $before = $service->countAll();

        try {
            $result = $service->clean($selected);
            $after = $service->countAll();
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membersihkan log pengguna: ' . $exception->getMessage());

            return $this->redirect('admin/clean-data/log');
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

        $totalDeleted = array_sum(array_map(static fn ($item) => (int) ($item['deleted'] ?? 0), $result));

        if ($totalDeleted > 0) {
            Session::flash('success', 'Log terpilih berhasil dibersihkan.');
        } else {
            Session::flash('info', 'Tidak ada log yang dihapus untuk pilihan tersebut.');
        }

        Session::flash('clean_activity_log_report', [
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => $summary,
        ]);

        return $this->redirect('admin/clean-data/log');
    }
}

