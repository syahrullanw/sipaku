<?php

namespace App\Services;

use App\Models\SchoolYear;
use Core\Database;
use PDO;

class ManagedFileStorage
{
    private const PUBLIC_ROOT = 'uploads/arsip';
    private const STORAGE_ROOT = 'arsip';

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $options
     */
    public static function storeUploadedPublic(array $file, string $category, string $subcategory, string $prefix, string $extension, array $options = []): ?string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $directory = self::publicDirectory($category, $subcategory, $options['school_year'] ?? null);
        $absoluteDirectory = public_path($directory);
        if (!self::ensureDirectory($absoluteDirectory)) {
            return null;
        }

        $filename = self::uniqueFilename($absoluteDirectory, $prefix, $extension);
        $absolute = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $absolute)) {
            return null;
        }

        $relative = $directory . '/' . $filename;
        self::record($relative, 'public', $category, $subcategory, $file['name'] ?? $filename, $options);

        if (!empty($options['existing_path']) && is_string($options['existing_path'])) {
            self::deletePublic($options['existing_path']);
        }

        return $relative;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function storePublicContents(string $contents, string $category, string $subcategory, string $prefix, string $extension, array $options = []): ?string
    {
        $directory = self::publicDirectory($category, $subcategory, $options['school_year'] ?? null);
        $absoluteDirectory = public_path($directory);
        if (!self::ensureDirectory($absoluteDirectory)) {
            return null;
        }

        $filename = self::uniqueFilename($absoluteDirectory, $prefix, $extension);
        $absolute = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($absolute, $contents) === false) {
            return null;
        }

        $relative = $directory . '/' . $filename;
        self::record($relative, 'public', $category, $subcategory, $options['original_name'] ?? $filename, $options);

        if (!empty($options['existing_path']) && is_string($options['existing_path'])) {
            self::deletePublic($options['existing_path']);
        }

        return $relative;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $options
     */
    public static function storeUploadedStorage(array $file, string $category, string $subcategory, string $prefix, string $extension, array $options = []): ?array
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $directory = self::storageDirectory($category, $subcategory, $options['school_year'] ?? null);
        $absoluteDirectory = storage_path($directory);
        if (!self::ensureDirectory($absoluteDirectory)) {
            return null;
        }

        $filename = self::uniqueFilename($absoluteDirectory, $prefix, $extension);
        $absolute = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $absolute)) {
            return null;
        }

        $relative = $directory . '/' . $filename;
        self::record($relative, 'storage', $category, $subcategory, $file['name'] ?? $filename, $options);

        if (!empty($options['existing_path']) && is_string($options['existing_path'])) {
            self::deleteStorage($options['existing_path']);
        }

        return ['relative' => $relative, 'absolute' => $absolute];
    }

    public static function deletePublic(?string $path): void
    {
        self::deleteAt(public_path((string) $path), (string) $path, 'public');
    }

    public static function deleteStorage(?string $path): void
    {
        self::deleteAt(storage_path((string) $path), (string) $path, 'storage');
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public static function list(array $filters = []): array
    {
        self::ensureTable();
        self::syncExistingFiles();

        $connection = Database::connection();
        $where = [];
        $params = [];

        foreach (['school_period', 'category', 'subcategory', 'disk', 'file_type'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $where[] = $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(original_name LIKE :keyword OR stored_name LIKE :keyword OR relative_path LIKE :keyword OR category LIKE :keyword OR subcategory LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = 'SELECT * FROM file_manager_items ' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT 500';
        $statement = $connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();
        $items = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary = $connection->query('SELECT COUNT(*) AS total_files, COALESCE(SUM(size_bytes),0) AS total_size FROM file_manager_items');
        $totals = $summary !== false ? ($summary->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        return ['items' => $items, 'totals' => $totals];
    }

    public static function find(int $id): ?array
    {
        self::ensureTable();
        $statement = Database::connection()->prepare('SELECT * FROM file_manager_items WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function absolutePath(array $item): ?string
    {
        $path = ltrim((string) ($item['relative_path'] ?? ''), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return ($item['disk'] ?? '') === 'storage' ? storage_path($path) : public_path($path);
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     */
    public static function periodFolder(?array $schoolYear = null): string
    {
        $year = $schoolYear ?? SchoolYear::active();
        $name = trim((string) ($year['nama'] ?? 'tanpa-tahun-ajaran'));
        $semester = (int) ($year['semester_aktif'] ?? 1);
        $semesterLabel = $semester === 2 ? 'genap' : 'ganjil';

        return self::slug($name . '-' . $semesterLabel);
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     */
    private static function publicDirectory(string $category, string $subcategory, ?array $schoolYear = null): string
    {
        return self::PUBLIC_ROOT . '/' . self::periodFolder($schoolYear) . '/' . self::slug($category) . '/' . self::slug($subcategory);
    }

    /**
     * @param array<string, mixed>|null $schoolYear
     */
    private static function storageDirectory(string $category, string $subcategory, ?array $schoolYear = null): string
    {
        return self::STORAGE_ROOT . '/' . self::periodFolder($schoolYear) . '/' . self::slug($category) . '/' . self::slug($subcategory);
    }

    private static function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0775, true) || is_dir($directory);
    }

    private static function uniqueFilename(string $directory, string $prefix, string $extension): string
    {
        $safePrefix = self::slug($prefix);
        $safeExtension = strtolower(preg_replace('/[^a-z0-9]+/i', '', $extension) ?? '');
        $safeExtension = $safeExtension !== '' ? $safeExtension : 'dat';

        do {
            try {
                $random = bin2hex(random_bytes(5));
            } catch (\Throwable) {
                $random = uniqid('', true);
            }
            $filename = sprintf('%s-%s-%s.%s', $safePrefix, date('YmdHis'), $random, $safeExtension);
        } while (is_file($directory . DIRECTORY_SEPARATOR . $filename));

        return $filename;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function record(string $relativePath, string $disk, string $category, string $subcategory, mixed $originalName, array $options = []): void
    {
        try {
            self::ensureTable();
            $absolute = $disk === 'storage' ? storage_path($relativePath) : public_path($relativePath);
            $schoolYear = $options['school_year'] ?? null;
            $schoolPeriod = isset($options['school_period']) && is_scalar($options['school_period'])
                ? trim((string) $options['school_period'])
                : '';
            $user = auth();

            $statement = Database::connection()->prepare(
                'INSERT INTO file_manager_items
                (school_period, school_year_id, semester, category, subcategory, disk, relative_path, stored_name, original_name, file_type, mime_type, size_bytes, uploaded_by_user_id, related_type, related_id, created_at, updated_at)
                VALUES
                (:school_period, :school_year_id, :semester, :category, :subcategory, :disk, :relative_path, :stored_name, :original_name, :file_type, :mime_type, :size_bytes, :uploaded_by_user_id, :related_type, :related_id, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE
                    school_period = VALUES(school_period),
                    category = VALUES(category),
                    subcategory = VALUES(subcategory),
                    file_type = VALUES(file_type),
                    size_bytes = VALUES(size_bytes),
                    mime_type = VALUES(mime_type),
                    updated_at = VALUES(updated_at)'
            );

            $statement->execute([
                ':school_period' => $schoolPeriod !== '' ? $schoolPeriod : self::periodFolder(is_array($schoolYear) ? $schoolYear : null),
                ':school_year_id' => is_array($schoolYear) ? ($schoolYear['id'] ?? null) : null,
                ':semester' => is_array($schoolYear) ? ($schoolYear['semester_aktif'] ?? null) : null,
                ':category' => self::slug($category),
                ':subcategory' => self::slug($subcategory),
                ':disk' => $disk,
                ':relative_path' => $relativePath,
                ':stored_name' => basename($relativePath),
                ':original_name' => is_scalar($originalName) ? (string) $originalName : basename($relativePath),
                ':file_type' => self::fileType($relativePath),
                ':mime_type' => is_file($absolute) ? (mime_content_type($absolute) ?: null) : null,
                ':size_bytes' => is_file($absolute) ? filesize($absolute) : 0,
                ':uploaded_by_user_id' => is_array($user) ? ($user['id'] ?? null) : null,
                ':related_type' => $options['related_type'] ?? null,
                ':related_id' => $options['related_id'] ?? null,
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // File storage must not fail just because the optional index cannot be written.
        }
    }

    private static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS file_manager_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                school_period VARCHAR(100) NOT NULL,
                school_year_id INT UNSIGNED NULL,
                semester TINYINT UNSIGNED NULL,
                category VARCHAR(100) NOT NULL,
                subcategory VARCHAR(100) NOT NULL,
                disk VARCHAR(20) NOT NULL DEFAULT 'public',
                relative_path VARCHAR(500) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NULL,
                file_type VARCHAR(50) NOT NULL DEFAULT 'other',
                mime_type VARCHAR(150) NULL,
                size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_by_user_id INT UNSIGNED NULL,
                related_type VARCHAR(100) NULL,
                related_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_file_manager_relative_path (relative_path),
                KEY idx_file_manager_period_category (school_period, category, subcategory),
                KEY idx_file_manager_type (file_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $done = true;
    }

    private static function syncExistingFiles(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $roots = [
            ['disk' => 'public', 'base' => public_path('uploads'), 'relative' => 'uploads'],
            ['disk' => 'storage', 'base' => storage_path('keuangan'), 'relative' => 'keuangan'],
            ['disk' => 'storage', 'base' => storage_path(self::STORAGE_ROOT), 'relative' => self::STORAGE_ROOT],
        ];

        foreach ($roots as $root) {
            if (!is_dir($root['base'])) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root['base'], \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                if (str_starts_with($file->getBasename(), '.')) {
                    continue;
                }

                $absolute = $file->getPathname();
                $relative = $root['relative'] . '/' . ltrim(str_replace('\\', '/', substr($absolute, strlen($root['base']))), '/');
                $mapped = self::inferCategory($relative);
                self::record($relative, $root['disk'], $mapped['category'], $mapped['subcategory'], basename($relative), [
                    'school_period' => self::inferPeriod($relative),
                ]);
            }
        }

        $done = true;
    }

    /**
     * @return array{category: string, subcategory: string}
     */
    private static function inferCategory(string $relative): array
    {
        $path = strtolower($relative);
        if (str_contains($path, 'siswa/data-fisik')) {
            return ['category' => 'data-siswa', 'subcategory' => 'dokumen-fisik'];
        }
        if (str_contains($path, 'siswa')) {
            return ['category' => 'data-siswa', 'subcategory' => 'foto-siswa'];
        }
        if (str_contains($path, 'surat') || str_contains($path, 'kop-surat')) {
            return ['category' => 'persuratan', 'subcategory' => 'arsip-surat'];
        }
        if (str_contains($path, 'keuangan')) {
            return ['category' => 'keuangan', 'subcategory' => 'lampiran'];
        }
        if (str_contains($path, 'sekolah')) {
            return ['category' => 'profil-sekolah', 'subcategory' => 'asset'];
        }

        return ['category' => 'dokumen-lainnya', 'subcategory' => self::fileType($relative)];
    }

    private static function inferPeriod(string $relative): string
    {
        $path = trim(str_replace('\\', '/', $relative), '/');
        $parts = explode('/', $path);

        if (($parts[0] ?? '') === 'uploads' && ($parts[1] ?? '') === 'arsip' && !empty($parts[2])) {
            return self::slug($parts[2]);
        }

        if (($parts[0] ?? '') === self::STORAGE_ROOT && !empty($parts[1])) {
            return self::slug($parts[1]);
        }

        return 'legacy';
    }

    private static function deleteAt(string $absolute, string $relative, string $disk): void
    {
        if ($relative !== '' && is_file($absolute)) {
            @unlink($absolute);
        }

        try {
            self::ensureTable();
            $statement = Database::connection()->prepare('DELETE FROM file_manager_items WHERE relative_path = :path AND disk = :disk');
            $statement->execute([':path' => ltrim($relative, '/'), ':disk' => $disk]);
        } catch (\Throwable) {
        }
    }

    private static function fileType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => 'image',
            'pdf' => 'pdf',
            'xls', 'xlsx', 'csv' => 'excel',
            'doc', 'docx' => 'word',
            'zip', 'rar', '7z' => 'archive',
            default => 'other',
        };
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'lainnya';
    }
}
