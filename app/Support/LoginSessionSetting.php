<?php

namespace App\Support;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function json_decode;
use function json_encode;
use function storage_path;

class LoginSessionSetting
{
    private const DEFAULT_MINUTES = 30;
    private const MIN_MINUTES = 5;
    private const MAX_MINUTES = 480;
    private const STORAGE_FILE = 'settings/login-session.json';

    private static ?int $cachedMinutes = null;

    public static function defaultMinutes(): int
    {
        return self::DEFAULT_MINUTES;
    }

    public static function minMinutes(): int
    {
        return self::MIN_MINUTES;
    }

    public static function maxMinutes(): int
    {
        return self::MAX_MINUTES;
    }

    public static function normalizeMinutes(int $minutes): int
    {
        if ($minutes < self::MIN_MINUTES) {
            return self::MIN_MINUTES;
        }

        if ($minutes > self::MAX_MINUTES) {
            return self::MAX_MINUTES;
        }

        return $minutes;
    }

    public static function getMinutes(): int
    {
        if (self::$cachedMinutes !== null) {
            return self::$cachedMinutes;
        }

        $minutes = self::DEFAULT_MINUTES;
        $path = self::path();

        if (is_file($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['minutes'])) {
                    $minutes = (int) $data['minutes'];
                }
            }
        }

        $minutes = self::normalizeMinutes($minutes);
        self::$cachedMinutes = $minutes;

        return $minutes;
    }

    public static function getSeconds(): int
    {
        return self::getMinutes() * 60;
    }

    public static function saveMinutes(int $minutes): int
    {
        $minutes = self::normalizeMinutes($minutes);
        $path = self::path();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan pengaturan sesi.');
        }

        $payload = [
            'minutes' => $minutes,
            'updated_at' => date('c'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan pengaturan sesi login.');
        }

        self::$cachedMinutes = $minutes;

        return $minutes;
    }

    private static function path(): string
    {
        return storage_path(self::STORAGE_FILE);
    }
}
