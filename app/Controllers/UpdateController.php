<?php

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Session;
use PDO;

class UpdateController extends Controller
{
    protected ?string $layout = 'admin';

    private string $projectRoot;

    private string $backupDir;

    private string $updateDir;

    private const MAX_UPLOAD_SIZE = 52428800; // 50 MB

    private const ALLOWED_MIME = ['application/zip', 'application/x-zip-compressed'];

    public function __construct(\Core\Application $app)
    {
        parent::__construct($app);
        $this->projectRoot = realpath(__DIR__ . '/../..') ?: '';
        $this->backupDir = $this->projectRoot . '/storage/backups/updates';
        $this->updateDir = $this->projectRoot . '/update';
    }

    public function index(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        $currentVersion = (string) config('app.version', '0.0.0');
        $history = $this->getUpdateHistory();
        $report = Session::getFlash('update_report');

        return $this->render('admin/update/index', [
            'title' => 'Update Aplikasi',
            'pageTitle' => 'Update Aplikasi',
            'activeMenu' => 'app-update',
            'currentVersion' => $currentVersion,
            'history' => $history,
            'report' => is_array($report) ? $report : null,
            'maxUploadSize' => self::MAX_UPLOAD_SIZE,
        ], 'admin');
    }

    public function upload(Request $request): Response
    {
        if ($response = $this->ensureAuthenticated()) {
            return $response;
        }

        if ($response = $this->ensureAdmin()) {
            return $response;
        }

        if ($response = $this->guardCsrf($request, 'admin/update')) {
            return $response;
        }

        $upload = $request->file('update_zip');
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'old_version' => (string) config('app.version', '0.0.0'),
            'new_version' => null,
            'files_extracted' => [],
            'errors' => [],
            'backup_path' => null,
            'sql_backup_path' => null,
            'migration' => null,
        ];

        try {
            if ($upload === null || !is_array($upload)) {
                throw new \RuntimeException('Tidak ada file yang diunggah.');
            }

            if (($upload['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
                throw new \RuntimeException('Gagal mengunggah file: ' . $this->uploadErrorText($upload['error']));
            }

            $tmpPath = (string) ($upload['tmp_name'] ?? '');

            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new \RuntimeException('File yang diunggah tidak valid.');
            }

            $originalName = (string) ($upload['name'] ?? 'update.zip');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if ($extension !== 'zip') {
                throw new \RuntimeException('Format file tidak didukung. Gunakan file ZIP.');
            }

            $fileSize = filesize($tmpPath);

            if ($fileSize === false || $fileSize <= 0) {
                throw new \RuntimeException('File ZIP kosong.');
            }

            if ($fileSize > self::MAX_UPLOAD_SIZE) {
                throw new \RuntimeException('Ukuran file melebihi batas maksimal (50 MB).');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : '';
            finfo_close($finfo);

            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                throw new \RuntimeException('File yang diunggah bukan ZIP yang valid.');
            }

            $zip = new \ZipArchive();
            $zipOpened = false;
            $openResult = $zip->open($tmpPath);

            if ($openResult !== true) {
                $zip = null;
                throw new \RuntimeException('Tidak dapat membuka file ZIP (kode: ' . $openResult . ').');
            }

            $zipOpened = true;

            $tempDir = sys_get_temp_dir() . '/sipaku-update-' . uniqid();

            if (!@mkdir($tempDir, 0755, true)) {
                $zip->close();
                $zip = null;
                throw new \RuntimeException('Gagal membuat direktori temporary.');
            }

            if (!$zip->extractTo($tempDir)) {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('Gagal mengekstrak file ZIP ke direktori temporary.');
            }

            $sourceDir = $this->findUpdateRoot($tempDir);

            if ($sourceDir === null) {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('File ZIP tidak valid: tidak ditemukan file VERSION.');
            }

            $versionInZip = trim((string) file_get_contents($sourceDir . '/VERSION'));

            if ($versionInZip === '') {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('File VERSION dalam ZIP tidak terbaca.');
            }

            if (!preg_match('/^\d+\.\d+\.\d+$/', $versionInZip)) {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('Format VERSION dalam ZIP tidak valid. Gunakan format x.x.x (contoh: 1.2.0).');
            }

            $report['new_version'] = $versionInZip;

            $backupId = 'pre-' . $report['old_version'] . '-' . date('Ymd-His');
            $backupPath = $this->backupDir . '/' . $backupId;
            $report['backup_path'] = $backupPath;

            if (!is_dir($this->backupDir)) {
                if (!@mkdir($this->backupDir, 0755, true)) {
                    $this->rmDirRecursive($tempDir);
                    $zip->close();
                    $zip = null;
                    throw new \RuntimeException('Gagal membuat direktori backup.');
                }
            }

            if (!@mkdir($backupPath, 0755, true)) {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('Gagal membuat direktori backup untuk update ini.');
            }

            $migrationSource = $sourceDir . '/database/migration.sql';
            $hasMigration = is_file($migrationSource);

            if ($hasMigration && !$this->checkDatabaseConnection()) {
                $this->rmDirRecursive($tempDir);
                $zip->close();
                $zip = null;
                throw new \RuntimeException('Terdapat file migration.sql dalam paket, tetapi koneksi database tidak tersedia.');
            }

            $extractedCount = 0;
            $skipCount = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $fileInfo) {
                $realPath = $fileInfo->getRealPath();

                if ($realPath === false) {
                    $skipCount++;
                    continue;
                }

                $relativePath = substr($realPath, strlen($sourceDir) + 1);
                $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

                if ($relativePath === '') {
                    continue;
                }

                $cleanName = $this->sanitizePath($relativePath);

                if ($cleanName === null) {
                    $skipCount++;
                    continue;
                }

                $targetPath = $this->projectRoot . '/' . $cleanName;
                $targetDir = dirname($targetPath);

                if ($fileInfo->isDir()) {
                    if (!is_dir($targetPath) && !@mkdir($targetPath, 0755, true)) {
                        $report['errors'][] = 'Gagal membuat direktori: ' . $cleanName;
                    }
                    continue;
                }

                if (!is_dir($targetDir)) {
                    if (!@mkdir($targetDir, 0755, true)) {
                        $report['errors'][] = 'Gagal membuat direktori: ' . $cleanName;
                        continue;
                    }
                }

                if (file_exists($targetPath)) {
                    $backupTargetDir = $backupPath . '/' . dirname($cleanName);

                    if (!is_dir($backupTargetDir)) {
                        @mkdir($backupTargetDir, 0755, true);
                    }

                    $backupTarget = $backupPath . '/' . $cleanName;

                    if (!@copy($targetPath, $backupTarget)) {
                        $report['errors'][] = 'Gagal mem-backup file: ' . $cleanName;
                        $this->rmDirRecursive($tempDir);
                        $zip->close();
                        $zip = null;
                        throw new \RuntimeException('Gagal mem-backup file: ' . $cleanName . '. Proses dibatalkan.');
                    }
                }

                if (!@copy($realPath, $targetPath)) {
                    $report['errors'][] = 'Gagal menulis file: ' . $cleanName;
                    continue;
                }

                $extractedCount++;
                $report['files_extracted'][] = $cleanName;
            }

            $this->rmDirRecursive($tempDir);
            $zip->close();

            if ($extractedCount === 0 && $skipCount > 0) {
                $report['errors'][] = 'Tidak ada file yang diekstrak. Semua entry dilewati karena tidak valid.';
            }

            $report['total_extracted'] = $extractedCount;
            $report['total_skipped'] = $skipCount;

            $versionFilePath = $this->projectRoot . '/VERSION';

            if (@file_put_contents($versionFilePath, $versionInZip . "\n") !== false) {
                \Core\Application::getInstance()?->config()->set('app.version', $versionInZip);
            } else {
                $report['errors'][] = 'Gagal menulis file VERSION. Versi mungkin tidak tersimpan permanen.';
            }

            // Execute database migration if migration.sql exists in the extracted update
            $migrationPath = $this->projectRoot . '/database/migration.sql';

            if (is_file($migrationPath)) {
                $migrationResult = $this->runMigration($migrationPath, $backupPath);
                $report['migration'] = $migrationResult;

                if (empty($migrationResult['errors'])) {
                    $report['messages'][] = 'Migration database berhasil (' . $migrationResult['executed'] . ' pernyataan).';
                } else {
                    $report['errors'] = array_merge(
                        $report['errors'],
                        array_map(static fn(string $e) => 'Migration: ' . $e, $migrationResult['errors'])
                    );
                }
            }

            if (function_exists('opcache_reset')) {
                opcache_reset();
            } elseif (function_exists('opcache_invalidate')) {
                foreach ($report['files_extracted'] as $extractedFile) {
                    $fullPath = $this->projectRoot . '/' . $extractedFile;
                    if (is_file($fullPath)) {
                        opcache_invalidate($fullPath, true);
                    }
                }
            }

            $hasErrors = !empty($report['errors']);

            Session::flash($hasErrors ? 'warning' : 'success', $hasErrors
                ? 'Update selesai dengan beberapa kendala. Periksa laporan di bawah.'
                : 'Update aplikasi berhasil: v' . $report['old_version'] . ' -> v' . $versionInZip);

            Session::flash('update_report', $report);
        } catch (\Throwable $exception) {
            if (isset($zip) && $zip instanceof \ZipArchive && isset($zipOpened) && $zipOpened) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }

            if (isset($tempDir) && is_dir($tempDir)) {
                $this->rmDirRecursive($tempDir);
            }

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            $report['errors'][] = $exception->getMessage();
            Session::flash('error', 'Gagal melakukan update: ' . $exception->getMessage());
            Session::flash('update_report', $report);
        }

        return $this->redirect('admin/update');
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            Database::connection();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function runMigration(string $migrationPath, string $backupPath): array
    {
        $result = [
            'executed' => 0,
            'errors' => [],
            'sql_backup_path' => null,
        ];

        $sqlContent = file_get_contents($migrationPath);

        if ($sqlContent === false || trim($sqlContent) === '') {
            $result['errors'][] = 'File migration.sql kosong.';
            return $result;
        }

        $dbBackupPath = $backupPath . '/pre-migration-database.sql';

        try {
            $this->exportDatabaseToSql($dbBackupPath);
            $result['sql_backup_path'] = $dbBackupPath;
        } catch (\Throwable $e) {
            $result['errors'][] = 'Gagal membuat backup database: ' . $e->getMessage();
            return $result;
        }

        $statements = $this->splitSqlStatements($sqlContent);

        if (empty($statements)) {
            $result['errors'][] = 'Tidak ada pernyataan SQL yang ditemukan di migration.sql.';
            return $result;
        }

        $pdo = Database::connection();

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } catch (\Throwable) {
        }

        foreach ($statements as $index => $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            try {
                $pdo->exec($statement);
                $result['executed']++;
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf(
                    'Pernyataan #%d: %s',
                    $index + 1,
                    $e->getMessage()
                );
            }
        }

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable) {
        }

        return $result;
    }

    private function exportDatabaseToSql(string $outputPath): void
    {
        $pdo = Database::connection();
        $config = config('database.connections.' . config('database.default', 'mysql'), []);
        $databaseName = (string) ($config['database'] ?? $this->currentDatabaseName($pdo));

        $tables = $this->fetchAllTables($pdo, $databaseName);

        $lines = [];
        $lines[] = '-- Backup Database sebelum migration';
        $lines[] = '-- Database: ' . $databaseName;
        $lines[] = '-- Dibuat: ' . date('Y-m-d H:i:s');
        $lines[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $lines[] = 'SET UNIQUE_CHECKS = 0;';
        $lines[] = '';

        foreach ($tables as $table) {
            $quoted = '`' . str_replace('`', '``', $table) . '`';

            $lines[] = '';
            $lines[] = '-- Table structure for ' . $quoted;
            $lines[] = 'DROP TABLE IF EXISTS ' . $quoted . ';';

            $createStmt = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_ASSOC);

            if ($createStmt !== false && isset($createStmt['Create Table'])) {
                $lines[] = $createStmt['Create Table'] . ';';
            }

            $lines[] = '';
            $lines[] = '-- Data for ' . $quoted;

            $rows = $pdo->query('SELECT * FROM ' . $quoted);

            if ($rows !== false) {
                $insertValues = [];

                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $values = [];

                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote((string) $value);
                        }
                    }

                    $insertValues[] = '(' . implode(', ', $values) . ')';
                }

                if (!empty($insertValues)) {
                    $columns = array_map(static fn(string $col) => '`' . str_replace('`', '``', $col) . '`', array_keys($row));
                    $lines[] = 'INSERT INTO ' . $quoted . ' (' . implode(', ', $columns) . ') VALUES';
                    $lines[] = implode(',' . PHP_EOL, $insertValues) . ';';
                }
            }
        }

        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $lines[] = 'SET UNIQUE_CHECKS = 1;';
        $lines[] = '';

        file_put_contents($outputPath, implode(PHP_EOL, $lines));
    }

    private function fetchAllTables(PDO $pdo, string $databaseName): array
    {
        $stmt = $pdo->query('SHOW TABLES');

        if ($stmt === false) {
            return [];
        }

        $tables = [];

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $table = (string) ($row[0] ?? '');

            if ($table !== '') {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    private function currentDatabaseName(PDO $pdo): string
    {
        $stmt = $pdo->query('SELECT DATABASE()');

        if ($stmt === false) {
            return 'unknown';
        }

        $row = $stmt->fetch(PDO::FETCH_NUM);

        return (string) ($row[0] ?? 'unknown');
    }

    private function splitSqlStatements(string $sql): array
    {
        $lines = explode("\n", $sql);
        $statements = [];
        $current = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $current .= $line . "\n";

            if (str_ends_with(trim($current), ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }

        $remaining = trim($current);

        if ($remaining !== '') {
            $statements[] = $remaining;
        }

        return $statements;
    }

    private function findUpdateRoot(string $dir): ?string
    {
        if (is_file($dir . '/VERSION')) {
            return $dir;
        }

        $items = scandir($dir);

        if ($items === false) {
            return null;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $subDir = $dir . '/' . $item;

            if (!is_dir($subDir)) {
                continue;
            }

            if (is_file($subDir . '/VERSION')) {
                return $subDir;
            }
        }

        return null;
    }

    private function rmDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }

    private function sanitizePath(string $path): ?string
    {
        $parts = explode('/', $path);
        $clean = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                return null;
            }

            $clean[] = $part;
        }

        return implode('/', $clean);
    }

    private function uploadErrorText(int $code): string
    {
        return match ($code) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal.',
            \UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian.',
            \UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan.',
            \UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            \UPLOAD_ERR_EXTENSION => 'Ekstensi PHP menghalangi upload file.',
            default => 'Kesalahan tidak dikenal (kode: ' . $code . ').',
        };
    }

    private function getUpdateHistory(): array
    {
        if (!is_dir($this->updateDir)) {
            return [];
        }

        $items = scandir($this->updateDir, SCANDIR_SORT_DESCENDING);
        $history = [];

        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.DS_Store') {
                continue;
            }

            $fullPath = $this->updateDir . '/' . $item;

            if (!is_dir($fullPath)) {
                continue;
            }

            $versionFile = $fullPath . '/VERSION';

            if (is_file($versionFile)) {
                $version = trim((string) file_get_contents($versionFile));
            } else {
                $version = $item;
            }

            $history[] = [
                'path' => $item,
                'version' => $version,
                'modified' => filemtime($fullPath),
            ];
        }

        return $history;
    }
}
