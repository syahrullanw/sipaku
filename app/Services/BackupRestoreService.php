<?php

namespace App\Services;

use Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

class BackupRestoreService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? storage_path('backups');
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        if (!is_dir($this->storagePath)) {
            return [];
        }

        $files = scandir($this->storagePath);
        if ($files === false) {
            return [];
        }

        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $this->storagePath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ['sql', 'zip'], true)) {
                continue;
            }

            $size = filesize($path);
            $modified = filemtime($path);

            $backups[] = [
                'filename' => $file,
                'path' => $path,
                'size' => $size === false ? 0 : $size,
                'modified' => $modified === false ? 0 : $modified,
                'type' => $extension === 'zip' ? 'full' : 'database',
                'extension' => $extension,
            ];
        }

        usort($backups, static fn (array $a, array $b): int => ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0));

        return $backups;
    }

    /**
     * @return array<string, mixed>
     */
    public function createBackup(): array
    {
        $this->ensureStorageDirectory();

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP tidak tersedia di server PHP.');
        }

        $connection = Database::connection();
        $connection->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);

        $config = config('database.connections.' . config('database.default', 'mysql'), []);
        $databaseName = (string) ($config['database'] ?? $this->currentDatabaseName($connection));
        $host = (string) ($config['host'] ?? 'localhost');
        $timestamp = date('Ymd-His');

        $sql = $this->buildDatabaseDump($connection, $host, $databaseName);
        $sqlStats = $this->lastSqlStats;
        $filename = sprintf('backup-full-%s.zip', $timestamp);
        $path = $this->storagePath . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat file backup ZIP.');
        }

        $zip->addFromString('database.sql', $sql);

        $assetDirectories = $this->assetDirectories();
        $assetStats = [];

        foreach ($assetDirectories as $directory) {
            $stats = $this->addDirectoryToZip($zip, $directory['source'], $directory['zip_prefix']);
            $assetStats[] = [
                'label' => $directory['label'],
                'zip_prefix' => $directory['zip_prefix'],
                'files' => $stats['files'],
                'directories' => $stats['directories'],
            ];
        }

        $manifest = [
            'format' => 'siakad-full-backup',
            'app_name' => (string) config('app.name', 'SIAKAD SMK'),
            'app_version' => (string) config('app.version', '1.0.5'),
            'created_at' => date('c'),
            'database' => [
                'host' => $host,
                'name' => $databaseName,
                'tables' => (int) ($sqlStats['tables'] ?? 0),
                'rows' => (int) ($sqlStats['rows'] ?? 0),
            ],
            'assets' => $assetStats,
        ];

        $zip->addFromString(
            'manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $zip->close();

        $size = filesize($path);

        return [
            'filename' => $filename,
            'path' => $path,
            'type' => 'full',
            'tables' => (int) ($sqlStats['tables'] ?? 0),
            'rows' => (int) ($sqlStats['rows'] ?? 0),
            'size' => $size === false ? 0 : $size,
            'assets' => $assetStats,
            'app_version' => (string) config('app.version', '1.0.5'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFromFile(string $path, ?string $sourceName = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'zip') {
            return $this->restoreFromZip($path, $sourceName ?? basename($path));
        }

        $sql = file_get_contents($path);

        if ($sql === false || $sql === '') {
            throw new RuntimeException('File backup kosong atau tidak dapat dibaca.');
        }

        return $this->restoreFromSql($sql, $sourceName ?? basename($path));
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreFromSql(string $sql, ?string $sourceName = null): array
    {
        $normalized = $this->stripSqlComments($sql);
        $statements = $this->splitSqlStatements($normalized);

        if (empty($statements)) {
            throw new RuntimeException('File backup tidak mengandung pernyataan SQL yang valid.');
        }

        $connection = Database::connection();

        $executed = 0;
        $errors = [];

        try {
            $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if ($trimmed === '') {
                    continue;
                }

                try {
                    $connection->exec($trimmed);
                    $executed++;
                } catch (\Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        } finally {
            try {
                $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Throwable) {
                // ignore reset failure
            }
        }

        return [
            'source' => $sourceName ?? 'upload',
            'type' => 'database',
            'statements' => count($statements),
            'executed' => $executed,
            'errors' => $errors,
            'assets_restored' => [],
        ];
    }

    public function resolveBackupPath(string $filename): string
    {
        $safe = $this->sanitizeFilename($filename);

        if ($safe === '') {
            throw new RuntimeException('Nama file backup tidak valid.');
        }

        $path = $this->storagePath . DIRECTORY_SEPARATOR . $safe;

        if (!is_file($path)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        return $path;
    }

    /**
     * @var array{tables:int,rows:int}
     */
    private array $lastSqlStats = ['tables' => 0, 'rows' => 0];

    private function ensureStorageDirectory(): void
    {
        if (is_dir($this->storagePath)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->storagePath, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Tidak dapat membuat direktori penyimpanan backup.');
        }
    }

    /**
     * @return array<int, array{label:string,source:string,zip_prefix:string,restore_to:string}>
     */
    private function assetDirectories(): array
    {
        return [
            [
                'label' => 'Public Uploads',
                'source' => public_path('uploads'),
                'zip_prefix' => 'public/uploads',
                'restore_to' => public_path('uploads'),
            ],
            [
                'label' => 'Storage Arsip',
                'source' => storage_path('arsip'),
                'zip_prefix' => 'storage/arsip',
                'restore_to' => storage_path('arsip'),
            ],
            [
                'label' => 'Storage Keuangan',
                'source' => storage_path('keuangan'),
                'zip_prefix' => 'storage/keuangan',
                'restore_to' => storage_path('keuangan'),
            ],
        ];
    }

    private function buildDatabaseDump(PDO $connection, string $host, string $databaseName): string
    {
        $header = [
            '-- Backup Database',
            '-- Host: ' . $host,
            '-- Database: ' . $databaseName,
            '-- Dibuat pada: ' . date('Y-m-d H:i:s'),
            '-- App Version: ' . (string) config('app.version', '1.0.5'),
            'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";',
            'SET AUTOCOMMIT = 0;',
            'START TRANSACTION;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            '',
        ];

        [$tables, $rowCount, $body] = $this->dumpTablesToString($connection);

        $footer = [
            '',
            'SET FOREIGN_KEY_CHECKS = 1;',
            'COMMIT;',
            'SET AUTOCOMMIT = 1;',
            '',
        ];

        $this->lastSqlStats = [
            'tables' => count($tables),
            'rows' => $rowCount,
        ];

        return implode(PHP_EOL, $header) . PHP_EOL . $body . implode(PHP_EOL, $footer);
    }

    /**
     * @return array{0: array<int, string>, 1: int, 2: string}
     */
    private function dumpTablesToString(PDO $connection): array
    {
        $tables = $this->tableList($connection);
        $totalRows = 0;
        $output = '';

        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table);

            $output .= PHP_EOL . sprintf('-- Struktur tabel %s', $quotedTable) . PHP_EOL;
            $output .= 'DROP TABLE IF EXISTS ' . $quotedTable . ';' . PHP_EOL;

            $createStatement = $this->tableCreateStatement($connection, $table);
            $output .= $createStatement . ';' . PHP_EOL;

            $output .= PHP_EOL . sprintf('-- Data untuk tabel %s', $quotedTable) . PHP_EOL;

            $rowCount = 0;
            $query = $connection->query('SELECT * FROM ' . $quotedTable);

            if ($query !== false) {
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $rowCount++;
                    $totalRows++;

                    $columns = array_keys($row);
                    $columnList = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
                    $values = implode(', ', array_map(fn ($value) => $this->quoteValue($connection, $value), array_values($row)));

                    $output .= sprintf('INSERT INTO %s (%s) VALUES (%s);', $quotedTable, $columnList, $values) . PHP_EOL;
                }
            }

            if ($rowCount === 0) {
                $output .= '-- (tidak ada data)' . PHP_EOL;
            }
        }

        return [$tables, $totalRows, $output];
    }

    /**
     * @return array{files:int,directories:int}
     */
    private function addDirectoryToZip(ZipArchive $zip, string $source, string $zipPrefix): array
    {
        $files = 0;
        $directories = 0;

        if (!is_dir($source)) {
            $zip->addEmptyDir($zipPrefix);

            return ['files' => 0, 'directories' => 1];
        }

        $zip->addEmptyDir($zipPrefix);
        $directories++;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = ltrim(substr($absolutePath, strlen($source)), DIRECTORY_SEPARATOR);
            $zipPath = $zipPrefix . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($zipPath);
                $directories++;
                continue;
            }

            $zip->addFile($absolutePath, $zipPath);
            $files++;
        }

        return ['files' => $files, 'directories' => $directories];
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreFromZip(string $path, ?string $sourceName = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP tidak tersedia di server PHP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File backup ZIP tidak dapat dibuka.');
        }

        $extractDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'siakad-restore-' . bin2hex(random_bytes(6));
        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            $zip->close();
            throw new RuntimeException('Tidak dapat menyiapkan direktori sementara restore.');
        }

        try {
            if (!$zip->extractTo($extractDir)) {
                throw new RuntimeException('Gagal mengekstrak backup ZIP.');
            }
        } finally {
            $zip->close();
        }

        try {
            $sqlPath = $extractDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (!is_file($sqlPath)) {
                throw new RuntimeException('Backup ZIP tidak memiliki database.sql.');
            }

            $sql = file_get_contents($sqlPath);
            if ($sql === false || $sql === '') {
                throw new RuntimeException('database.sql kosong atau tidak dapat dibaca.');
            }

            $manifestPath = $extractDir . DIRECTORY_SEPARATOR . 'manifest.json';
            $manifest = null;
            if (is_file($manifestPath)) {
                $decoded = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($decoded)) {
                    $manifest = $decoded;
                }
            }

            $dbResult = $this->restoreFromSql($sql, $sourceName ?? basename($path));
            $assetResults = $this->restoreAssetsFromExtractedBackup($extractDir);

            return [
                'source' => $sourceName ?? basename($path),
                'type' => 'full',
                'statements' => (int) ($dbResult['statements'] ?? 0),
                'executed' => (int) ($dbResult['executed'] ?? 0),
                'errors' => $dbResult['errors'] ?? [],
                'assets_restored' => $assetResults,
                'manifest' => $manifest,
            ];
        } finally {
            $this->deleteDirectory($extractDir);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function restoreAssetsFromExtractedBackup(string $extractDir): array
    {
        $results = [];

        foreach ($this->assetDirectories() as $directory) {
            $source = $extractDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory['zip_prefix']);
            $target = $directory['restore_to'];

            if (!is_dir($source)) {
                $results[] = [
                    'label' => $directory['label'],
                    'status' => 'skipped',
                    'files' => 0,
                ];
                continue;
            }

            $this->deleteDirectory($target);
            if (!mkdir($target, 0775, true) && !is_dir($target)) {
                throw new RuntimeException('Tidak dapat membuat direktori restore: ' . $target);
            }

            $copiedFiles = $this->copyDirectoryContents($source, $target);
            $results[] = [
                'label' => $directory['label'],
                'status' => 'restored',
                'files' => $copiedFiles,
            ];
        }

        return $results;
    }

    private function copyDirectoryContents(string $source, string $target): int
    {
        $count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = ltrim(substr($item->getPathname(), strlen($source)), DIRECTORY_SEPARATOR);
            $destination = $target . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                if (!mkdir($destination, 0775, true) && !is_dir($destination)) {
                    throw new RuntimeException('Gagal membuat direktori restore: ' . $destination);
                }
                continue;
            }

            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('Gagal membuat direktori restore: ' . $parent);
            }

            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('Gagal menyalin asset restore: ' . $destination);
            }

            $count++;
        }

        return $count;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    /**
     * @return array<int, string>
     */
    private function tableList(PDO $connection): array
    {
        $tables = [];
        $result = $connection->query('SHOW FULL TABLES');

        if ($result === false) {
            throw new RuntimeException('Tidak dapat mengambil daftar tabel.');
        }

        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            if (!isset($row[0], $row[1])) {
                continue;
            }

            if (strtoupper((string) $row[1]) !== 'BASE TABLE') {
                continue;
            }

            $tables[] = (string) $row[0];
        }

        return $tables;
    }

    private function tableCreateStatement(PDO $connection, string $table): string
    {
        $escaped = $this->quoteIdentifier($table);
        $query = $connection->query('SHOW CREATE TABLE ' . $escaped);

        if ($query === false) {
            throw new RuntimeException(sprintf('Tidak dapat mengambil struktur tabel %s.', $table));
        }

        $row = $query->fetch(PDO::FETCH_NUM);

        if (!isset($row[1])) {
            throw new RuntimeException(sprintf('Struktur tabel %s tidak ditemukan.', $table));
        }

        return (string) $row[1];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue(PDO $connection, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $quoted = $connection->quote((string) $value);

        if ($quoted === false) {
            return "'" . addslashes((string) $value) . "'";
        }

        return $quoted;
    }

    private function sanitizeFilename(string $filename): string
    {
        $basename = basename($filename);

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $basename)) {
            return '';
        }

        return $basename;
    }

    private function currentDatabaseName(PDO $connection): string
    {
        $result = $connection->query('SELECT DATABASE()');
        $name = $result !== false ? $result->fetchColumn() : false;

        return $name !== false ? (string) $name : '';
    }

    private function stripSqlComments(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $cleaned = [];
        $inBlockComment = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($inBlockComment) {
                if (str_contains($trimmed, '*/')) {
                    $inBlockComment = false;
                }
                continue;
            }

            if (str_starts_with($trimmed, '/*')) {
                if (!str_contains($trimmed, '*/')) {
                    $inBlockComment = true;
                }
                continue;
            }

            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $cleaned[] = $line;
        }

        return implode(PHP_EOL, $cleaned);
    }

    /**
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $escape = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buffer .= $char;
                continue;
            }

            if ($char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buffer .= $char;
                continue;
            }

            if ($char === ';' && !$inSingle && !$inDouble) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
