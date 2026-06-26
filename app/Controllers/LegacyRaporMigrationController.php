<?php

namespace App\Controllers;

use App\Services\Migration\LegacyRaporMigrationService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class LegacyRaporMigrationController extends Controller
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

        $service = new LegacyRaporMigrationService();

        $defaultPath = $service->defaultSqlPath();
        $sqlExists = $service->sqlFileExists($defaultPath);
        $legacyTables = $service->listLegacyTables();
        $legacyCounts = $service->legacyTableCounts();
        $datasetLabels = LegacyRaporMigrationService::DATASET_LABELS;
        $importReport = Session::getFlash('legacy_migration_import');
        $migrationReport = Session::getFlash('legacy_migration_report');

        return $this->render('admin/legacy-migration/index', [
            'title' => 'Migrasi Data Raport Legacy',
            'pageTitle' => 'Migrasi Raport Legacy',
            'activeMenu' => 'legacy-migration',
            'defaultSqlPath' => $defaultPath,
            'sqlExists' => $sqlExists,
            'legacyTables' => $legacyTables,
            'legacyCounts' => $legacyCounts,
            'datasetLabels' => $datasetLabels,
            'importReport' => $importReport,
            'migrationReport' => $migrationReport,
        ], 'admin');
    }

    public function import(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/migrasi-rapor')) {
            return $response;
        }

        $service = new LegacyRaporMigrationService();

        $pathInput = $request->input('sql_path');
        $resolvedPath = $this->resolveSqlPath(is_string($pathInput) ? $pathInput : null);
        $force = $this->booleanInput($request->input('force'));

        try {
            $result = $service->importFromSql($resolvedPath, $force);

            $report = [
                'path' => $resolvedPath ?? $service->defaultSqlPath(),
                'force' => $force,
                'timestamp' => date('Y-m-d H:i:s'),
                'result' => $result,
            ];

            Session::flash('legacy_migration_import', $report);

            $hasErrors = !empty($result['errors']);
            Session::flash($hasErrors ? 'warning' : 'success', $hasErrors
                ? 'Import selesai dengan beberapa kendala. Periksa detail laporan di bawah.'
                : 'File SQL legacy berhasil diimport.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal mengimpor data legacy: ' . $exception->getMessage());
        }

        return $this->redirect('admin/migrasi-rapor');
    }

    public function migrate(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/migrasi-rapor')) {
            return $response;
        }

        $datasets = $request->input('datasets', []);
        $selectedDatasets = array_values(array_filter(array_map('strval', (array) $datasets)));

        if (empty($selectedDatasets)) {
            Session::flash('warning', 'Pilih minimal satu dataset legacy yang ingin dimigrasikan.');

            return $this->redirect('admin/migrasi-rapor');
        }

        $dryRun = $this->booleanInput($request->input('dry_run'));

        $service = new LegacyRaporMigrationService();

        try {
            $result = $service->migrate($selectedDatasets, $dryRun);

            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'dry_run' => $dryRun,
                'selected' => $selectedDatasets,
                'datasets' => $result,
            ];

            Session::flash('legacy_migration_report', $report);

            $statuses = array_map(static fn (array $item): string => (string) ($item['status'] ?? ''), $result);
            $hasError = in_array('error', $statuses, true);
            $hasSuccess = in_array('success', $statuses, true);

            if ($hasError) {
                Session::flash('error', 'Migrasi selesai dengan beberapa error. Periksa detail laporan di bawah.');
            } elseif ($hasSuccess) {
                Session::flash('success', $dryRun
                    ? 'Simulasi migrasi legacy selesai. Tidak ada perubahan data.'
                    : 'Migrasi data legacy berhasil dijalankan.');
            } else {
                Session::flash('warning', 'Tidak ada dataset legacy yang diproses. Periksa laporan di bawah.');
            }
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menjalankan migrasi legacy: ' . $exception->getMessage());
            Session::flash('legacy_migration_report', [
                'timestamp' => date('Y-m-d H:i:s'),
                'dry_run' => $dryRun,
                'selected' => $selectedDatasets,
                'datasets' => [],
            ]);
        }

        return $this->redirect('admin/migrasi-rapor');
    }

    public function drop(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/migrasi-rapor')) {
            return $response;
        }

        $service = new LegacyRaporMigrationService();

        try {
            $service->dropLegacyTables();
            Session::flash('success', 'Seluruh tabel legacy berhasil dihapus.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal menghapus tabel legacy: ' . $exception->getMessage());
        }

        return $this->redirect('admin/migrasi-rapor');
    }

    private function booleanInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'on', 'yes', 'ya'], true);
        }

        return false;
    }

    private function resolveSqlPath(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, DIRECTORY_SEPARATOR)) {
            return $trimmed;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmed) === 1) {
            return $trimmed;
        }

        return base_path($trimmed);
    }
}
