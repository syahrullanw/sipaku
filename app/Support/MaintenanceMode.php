<?php

namespace App\Support;

use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function storage_path;
use function trim;
use function view;

class MaintenanceMode
{
    private const STORAGE_FILE = 'settings/maintenance-mode.json';

    private static ?bool $cachedEnabled = null;

    public static function isEnabled(): bool
    {
        if (self::$cachedEnabled !== null) {
            return self::$cachedEnabled;
        }

        $enabled = false;
        $path = self::path();

        if (is_file($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && array_key_exists('enabled', $data)) {
                    $enabled = (bool) $data['enabled'];
                }
            }
        }

        self::$cachedEnabled = $enabled;

        return $enabled;
    }

    public static function setEnabled(bool $enabled): void
    {
        $path = self::path();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan maintenance mode.');
        }

        $payload = [
            'enabled' => $enabled,
            'updated_at' => date('c'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan status maintenance mode.');
        }

        self::$cachedEnabled = $enabled;
    }

    public static function allowsCurrentRequest(Request $request): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        $user = Auth::user();
        if (is_array($user) && ($user['role'] ?? '') === 'admin') {
            return true;
        }

        $path = $request->getPath();
        $method = $request->getMethod();

        if ($path === '/login' && ($method === 'GET' || $method === 'POST')) {
            return true;
        }

        if ($path === '/logout' && $method === 'POST') {
            return true;
        }

        return $path === '/maintenance';
    }

    public static function response(Request $request): Response
    {
        if (Auth::check()) {
            Session::flash('warning', 'Maintenance mode sedang aktif. Silakan masuk menggunakan akun admin.');
        }

        $response = view('maintenance/index', [
            'title' => 'Maintenance Mode',
        ], 'auth');

        return Response::make($response->getContent(), 503, [
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public static function rejectNonAdminLogin(): void
    {
        Auth::logout();
        Session::flash('error', 'Maintenance mode sedang aktif. Hanya admin yang dapat masuk.');
    }

    private static function path(): string
    {
        return storage_path(self::STORAGE_FILE);
    }
}
