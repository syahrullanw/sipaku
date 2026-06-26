<?php

namespace App\Controllers;

use App\Services\BackupRestoreService;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;

class BackupRestoreController extends Controller
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

        $service = new BackupRestoreService();
        $backups = $service->listBackups();
        $report = Session::getFlash('backup_restore_report');

        return $this->render('admin/maintenance/backup-restore', [
            'title' => 'Backup & Restore Data',
            'pageTitle' => 'Backup & Restore Data',
            'activeMenu' => 'data-backup-restore',
            'backups' => $backups,
            'storagePath' => $service->storagePath(),
            'report' => is_array($report) ? $report : null,
        ], 'admin');
    }

    public function create(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/backup-restore')) {
            return $response;
        }

        $service = new BackupRestoreService();

        try {
            $result = $service->createBackup();

            Session::flash('success', 'Backup penuh database dan asset berhasil dibuat.');
            Session::flash('backup_restore_report', [
                'type' => 'backup',
                'timestamp' => date('Y-m-d H:i:s'),
                'details' => $result,
            ]);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal membuat backup: ' . $exception->getMessage());
        }

        return $this->redirect('admin/backup-restore');
    }

    public function download(Request $request, string $filename): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $service = new BackupRestoreService();

        try {
            $path = $service->resolveBackupPath($filename);
            $downloadName = basename($path);
            $extension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
            $contentType = $extension === 'zip'
                ? 'application/zip'
                : 'application/sql; charset=utf-8';
            $size = filesize($path);

            return Response::file($path, 200, [
                'Content-Type' => $contentType,
                'Content-Length' => $size !== false ? (string) $size : '0',
                'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $exception) {
            Session::flash('error', $exception->getMessage());

            return $this->redirect('admin/backup-restore');
        }
    }

    public function restore(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/backup-restore')) {
            return $response;
        }

        $service = new BackupRestoreService();
        $files = $request->files();
        $upload = is_array($files) ? ($files['backup_file'] ?? null) : null;
        $selectedBackup = (string) $request->input('existing_backup', '');

        $result = null;

        try {
            if ($upload !== null && is_array($upload) && ($upload['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_NO_FILE) {
                $result = $this->restoreFromUpload($service, $upload);
            } elseif ($selectedBackup !== '') {
                $path = $service->resolveBackupPath($selectedBackup);
                $result = $service->restoreFromFile($path);
            } else {
                Session::flash('warning', 'Pilih file backup atau unggah berkas SQL/ZIP terlebih dahulu.');

                return $this->redirect('admin/backup-restore');
            }

            $hasErrors = !empty($result['errors'] ?? []);

            Session::flash($hasErrors ? 'warning' : 'success', $hasErrors
                ? 'Proses restore selesai dengan beberapa kendala. Periksa detail laporan di bawah.'
                : 'Data berhasil direstore.');

            Session::flash('backup_restore_report', [
                'type' => 'restore',
                'timestamp' => date('Y-m-d H:i:s'),
                'details' => $result,
            ]);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Gagal melakukan restore: ' . $exception->getMessage());
        }

        return $this->redirect('admin/backup-restore');
    }

    /**
     * @param array<string, mixed> $upload
     * @return array<string, mixed>
     */
    private function restoreFromUpload(BackupRestoreService $service, array $upload): array
    {
        $error = $upload['error'] ?? \UPLOAD_ERR_NO_FILE;

        if ($error !== \UPLOAD_ERR_OK) {
            throw new \RuntimeException('Gagal mengunggah file backup. Silakan coba lagi.');
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('File backup tidak valid.');
        }

        $originalName = (string) ($upload['name'] ?? 'upload.sql');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['sql', 'zip'], true)) {
            throw new \RuntimeException('Format file tidak didukung. Gunakan file dengan ekstensi .sql atau .zip.');
        }

        if ($extension === 'zip') {
            return $service->restoreFromFile($tmpName, $originalName);
        }

        $sql = file_get_contents($tmpName);

        if ($sql === false || $sql === '') {
            throw new \RuntimeException('File backup kosong atau tidak dapat dibaca.');
        }

        return $service->restoreFromSql($sql, $originalName);
    }
}
