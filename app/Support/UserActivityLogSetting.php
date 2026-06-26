<?php

namespace App\Support;

class UserActivityLogSetting
{
    private const DEFAULT_LIMIT = 5000;
    private const MIN_LIMIT = 100;
    private const MAX_LIMIT = 50000;
    private const STORAGE_FILE = 'settings/user-activity-log.json';

    private static ?int $cachedLimit = null;

    public static function defaultLimit(): int
    {
        return self::DEFAULT_LIMIT;
    }

    public static function minLimit(): int
    {
        return self::MIN_LIMIT;
    }

    public static function maxLimit(): int
    {
        return self::MAX_LIMIT;
    }

    public static function normalizeLimit(int $limit): int
    {
        $limit = max(self::MIN_LIMIT, $limit);

        return min(self::MAX_LIMIT, $limit);
    }

    public static function getLimit(): int
    {
        if (self::$cachedLimit !== null) {
            return self::$cachedLimit;
        }

        $limit = self::DEFAULT_LIMIT;
        $path = self::path();

        if (is_file($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['max_logs'])) {
                    $limit = (int) $data['max_logs'];
                }
            }
        }

        $limit = self::normalizeLimit($limit);
        self::$cachedLimit = $limit;

        return $limit;
    }

    public static function saveLimit(int $limit): int
    {
        $limit = self::normalizeLimit($limit);
        $path = self::path();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan pengaturan log.');
        }

        $payload = [
            'max_logs' => $limit,
            'updated_at' => date('c'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan pengaturan log pengguna.');
        }

        self::$cachedLimit = $limit;

        return $limit;
    }

    private static function path(): string
    {
        return storage_path(self::STORAGE_FILE);
    }
}
