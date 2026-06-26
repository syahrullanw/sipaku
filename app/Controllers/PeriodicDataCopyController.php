<?php

namespace App\Controllers;

use App\Models\SchoolYear;
use App\Services\PeriodicDataCopyService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class PeriodicDataCopyController extends Controller
{
    protected ?string $layout = 'admin';

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        $activeYearId = (int) ($activeYear['id'] ?? 0);

        $allYears = SchoolYear::allOrdered();
        $availableSources = array_values(array_filter($allYears, static function (array $year) use ($activeYearId): bool {
            return (int) ($year['id'] ?? 0) !== $activeYearId;
        }));

        $selectedSourceId = (int) $request->query('tahun_ajaran_sumber', 0);

        $sourceYear = null;
        if ($selectedSourceId > 0) {
            foreach ($availableSources as $candidate) {
                if ((int) ($candidate['id'] ?? 0) === $selectedSourceId) {
                    $sourceYear = $candidate;
                    break;
                }
            }
        }

        if ($sourceYear === null && !empty($availableSources)) {
            $sourceYear = $availableSources[0];
            $selectedSourceId = (int) ($sourceYear['id'] ?? 0);
        }

        $service = new PeriodicDataCopyService();
        $datasetLabels = PeriodicDataCopyService::datasetLabels();

        $sourceCounts = $service->countForYear($selectedSourceId > 0 ? $selectedSourceId : null);
        $targetCounts = $service->countForYear($activeYearId > 0 ? $activeYearId : null);

        $copyReport = Session::getFlash('copy_report');

        return $this->render('admin/periodic-data/index', [
            'title' => 'Salin Data Periodik',
            'pageTitle' => 'Salin Data Periodik',
            'activeMenu' => 'periodic-copy',
            'activeYear' => $activeYear,
            'sourceYear' => $sourceYear,
            'sourceYearId' => $selectedSourceId,
            'availableSources' => $availableSources,
            'datasetLabels' => $datasetLabels,
            'sourceCounts' => $sourceCounts,
            'targetCounts' => $targetCounts,
            'copyReport' => $copyReport,
        ], 'admin');
    }

    public function store(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/salin-data-periodik')) {
            return $response;
        }

        $activeYear = SchoolYear::active();
        if ($activeYear === null) {
            Session::flash('error', 'Tidak ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.');
            return $this->redirect('admin/salin-data-periodik');
        }

        $activeYearId = (int) ($activeYear['id'] ?? 0);
        $sourceYearId = (int) $request->input('tahun_ajaran_sumber', 0);

        if ($sourceYearId <= 0) {
            Session::flash('error', 'Tahun ajaran sumber tidak valid.');
            return $this->redirect('admin/salin-data-periodik');
        }

        if ($sourceYearId === $activeYearId) {
            Session::flash('error', 'Tahun ajaran sumber tidak boleh sama dengan tahun ajaran aktif.');
            return $this->redirect('admin/salin-data-periodik');
        }

        $sourceYear = SchoolYear::find($sourceYearId);

        if ($sourceYear === null) {
            Session::flash('error', 'Data tahun ajaran sumber tidak ditemukan.');
            return $this->redirect('admin/salin-data-periodik');
        }

        $service = new PeriodicDataCopyService();
        $labels = PeriodicDataCopyService::datasetLabels();

        try {
            $report = $service->copy($sourceYearId, $activeYearId);

            $summaryParts = [];

            foreach ($report as $key => $stats) {
                $copied = (int) ($stats['copied'] ?? 0);
                if ($copied > 0) {
                    $summaryParts[] = sprintf('%s %d entri', $labels[$key] ?? ucfirst($key), $copied);
                }
            }

            $summaryText = empty($summaryParts)
                ? 'Tidak ada data baru yang disalin karena semua sudah tersedia pada semester aktif.'
                : 'Total salinan: ' . implode(', ', $summaryParts) . '.';

            Session::flash('success', 'Proses salin data periodik selesai. ' . $summaryText);
            Session::flash('copy_report', $report);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menyalin data periodik: ' . $exception->getMessage());
        }

        return $this->redirect('admin/salin-data-periodik?tahun_ajaran_sumber=' . $sourceYearId);
    }
}

