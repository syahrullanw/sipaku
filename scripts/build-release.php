#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ZIP extension is not available in this PHP binary.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
$config = require $basePath . '/app/Config/app.php';
$version = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($config['version'] ?? date('Ymd-His')));
$timestamp = date('Ymd-His');
$releaseDir = $basePath . '/build/releases';
$releaseName = "sipaku-{$version}-{$timestamp}.zip";
$releasePath = $releaseDir . DIRECTORY_SEPARATOR . $releaseName;

$includePaths = [
    '.htaccess',
    'VERSION',
    'app',
    'bootstrap',
    'core',
    'database/README.md',
    'database/migrations',
    'database/seeders',
    'docs',
    'modules',
    'public',
    'resources',
    'routes',
    'scripts',
];

$excludePatterns = [
    '#(^|/)\.DS_Store$#',
    '#(^|/)Thumbs\.db$#',
    '#(^|/)node_modules(/|$)#',
    '#(^|/)vendor(/|$)#',
    '#(^|/)build(/|$)#',
    '#(^|/)storage(/|$)#',
    '#(^|/)update(/|$)#',
    '#(^|/)src(/|$)#',
    '#(^|/)public/uploads(/|$)#',
    '#(^|/)public/error_log$#',
    '#(^|/)app/Config/.*\.php$#',
    '#(^|/)app/Config/.*\.zip$#',
    '#(^|/)Archive\.zip$#',
    '#(^|/)package-lock\.json$#',
];

if (!is_dir($releaseDir) && !mkdir($releaseDir, 0775, true) && !is_dir($releaseDir)) {
    fwrite(STDERR, "Cannot create release directory: {$releaseDir}\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($releasePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create release archive: {$releasePath}\n");
    exit(1);
}

$addedFiles = 0;
$skippedFiles = 0;

foreach ($includePaths as $relativePath) {
    $absolutePath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

    if (!file_exists($absolutePath)) {
        $skippedFiles++;
        continue;
    }

    addPathToZip($zip, $basePath, $absolutePath, $excludePatterns, $addedFiles, $skippedFiles);
}

$manifest = [
    'name' => (string) ($config['name'] ?? 'SIPAKU'),
    'version' => $version,
    'created_at' => date('c'),
    'included_paths' => $includePaths,
    'excluded' => [
        'app/Config/*.php',
        'storage/',
        'public/uploads/',
        'update/',
        'build/',
        'node_modules/',
        'vendor/',
        'src/',
        'Archive.zip',
    ],
    'deploy_steps' => [
        'Enable maintenance mode on hosting.',
        'Create a full backup from /admin/backup-restore.',
        'Extract this archive over the existing application files.',
        'Do not replace hosting app/Config/*.php, storage/, or public/uploads/.',
        'Run new SQL files from database/migrations that are not applied yet.',
        'Delete storage/cache/*.cache.php if needed.',
        'Check login and main pages, then disable maintenance mode.',
    ],
];

$zip->addFromString(
    'RELEASE_MANIFEST.json',
    (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);
$zip->close();

$size = filesize($releasePath);
$sizeLabel = $size === false ? 'unknown size' : formatBytes($size);

fwrite(STDOUT, "Release created: {$releasePath}\n");
fwrite(STDOUT, "Version: {$version}\n");
fwrite(STDOUT, "Files added: {$addedFiles}\n");
fwrite(STDOUT, "Files skipped: {$skippedFiles}\n");
fwrite(STDOUT, "Size: {$sizeLabel}\n");

exit(0);

/**
 * @param list<string> $excludePatterns
 */
function addPathToZip(
    ZipArchive $zip,
    string $basePath,
    string $absolutePath,
    array $excludePatterns,
    int &$addedFiles,
    int &$skippedFiles
): void {
    $relativePath = normalizePath(substr($absolutePath, strlen($basePath) + 1));

    if (shouldExclude($relativePath, $excludePatterns)) {
        $skippedFiles++;
        return;
    }

    if (is_file($absolutePath)) {
        $zip->addFile($absolutePath, $relativePath);
        $addedFiles++;
        return;
    }

    if (!is_dir($absolutePath)) {
        $skippedFiles++;
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        $itemRelativePath = normalizePath(substr($itemPath, strlen($basePath) + 1));

        if (shouldExclude($itemRelativePath, $excludePatterns)) {
            $skippedFiles++;
            continue;
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($itemRelativePath);
            continue;
        }

        if ($item->isFile()) {
            $zip->addFile($itemPath, $itemRelativePath);
            $addedFiles++;
        }
    }
}

/**
 * @param list<string> $excludePatterns
 */
function shouldExclude(string $relativePath, array $excludePatterns): bool
{
    foreach ($excludePatterns as $pattern) {
        if (preg_match($pattern, $relativePath) === 1) {
            return true;
        }
    }

    return false;
}

function normalizePath(string $path): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return sprintf('%.2f %s', $value, $units[$unitIndex]);
}
