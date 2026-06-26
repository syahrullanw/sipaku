<?php

namespace App\Support;

class Changelog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function releases(): array
    {
        $directory = base_path('docs/changelog');
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $releases = [];

        foreach ($files as $file) {
            $version = basename($file, '.md');
            if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
                continue;
            }

            $release = self::parseReleaseFile($file, $version);
            if ($release !== null) {
                $releases[] = $release;
            }
        }

        usort(
            $releases,
            static fn (array $a, array $b): int => version_compare((string) $b['version'], (string) $a['version'])
        );

        return $releases;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latest(): ?array
    {
        $releases = self::releases();

        return $releases[0] ?? null;
    }

    /**
     * @return array{version:string,title:string,status:string,sections:array<string, array<int, string>>}|null
     */
    private static function parseReleaseFile(string $file, string $version): ?array
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $lines = preg_split('/\R/', $content);
        if ($lines === false) {
            return null;
        }

        $title = 'SIPAKU ' . $version;
        $status = '';
        $sections = [];
        $currentSection = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '# ')) {
                $title = trim(substr($trimmed, 2));
                continue;
            }

            if (str_starts_with($trimmed, 'Status:')) {
                $status = trim(substr($trimmed, strlen('Status:')));
                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                $currentSection = trim(substr($trimmed, 3));
                if ($currentSection !== '' && !isset($sections[$currentSection])) {
                    $sections[$currentSection] = [];
                }
                continue;
            }

            if ($currentSection !== null && str_starts_with($trimmed, '- ')) {
                $sections[$currentSection][] = trim(substr($trimmed, 2));
            }
        }

        return [
            'version' => $version,
            'title' => $title,
            'status' => $status,
            'sections' => $sections,
        ];
    }
}
