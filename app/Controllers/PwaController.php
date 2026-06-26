<?php

namespace App\Controllers;

use Core\Controller;
use Core\Response;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        $branding = app_branding();
        $configuredName = (string) config('app.name', 'Aplikasi Sekolah');
        $appName = trim((string) ($branding['name'] ?? $configuredName));
        if ($appName === '') {
            $appName = $configuredName !== '' ? $configuredName : 'Aplikasi Sekolah';
        }

        $customIcon = isset($branding['icon']) && $branding['icon'] !== ''
            ? (string) $branding['icon']
            : null;

        $manifest = [
            'name' => $appName,
            'short_name' => $this->resolveShortName($appName),
            'description' => 'Sistem Informasi Akademik yang dapat diakses langsung dari layar beranda Anda.',
            'start_url' => base_url('/'),
            'scope' => base_url('/'),
            'display' => 'standalone',
            'background_color' => '#0f172a',
            'theme_color' => '#4f46e5',
            'orientation' => 'portrait-primary',
            'icons' => $this->buildIcons($customIcon),
            'id' => base_url('/'),
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return Response::make(
            $json === false ? '{}' : $json,
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildIcons(?string $customIcon): array
    {
        $icons = [];

        if ($customIcon !== null) {
            foreach (['192x192', '512x512'] as $size) {
                $icons[] = $this->makeIconEntry($customIcon, $size);
            }

            return $icons;
        }

        $icons[] = $this->makeIconEntry('icons/icon-192.png', '192x192');
        $icons[] = $this->makeIconEntry('icons/icon-512.png', '512x512');

        return $icons;
    }

    private function resolveShortName(string $name): string
    {
        $limit = 30;
        if (function_exists('mb_substr')) {
            $short = trim((string) mb_substr($name, 0, $limit));
        } else {
            $short = trim(substr($name, 0, $limit));
        }

        if ($short === '') {
            return $name;
        }

        return $short;
    }

    /**
     * @return array<string, string>
     */
    private function makeIconEntry(string $path, string $size): array
    {
        return [
            'src' => absolute_url($path),
            'sizes' => $size,
            'type' => $this->guessMimeType($path),
            'purpose' => 'any maskable',
        ];
    }

    private function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
