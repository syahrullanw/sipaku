<?php

namespace Core;

class Config
{
    /**
     * @var array<string, mixed>
     */
    protected array $items = [];

    public function __construct(string $configPath)
    {
        $this->loadFromPath($configPath);
    }

    public function loadFromPath(string $configPath): void
    {
        $files = glob(rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');

        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }

        $this->loadVersionFile($configPath);
    }

    protected function loadVersionFile(string $configPath): void
    {
        $versionFile = dirname($configPath, 2) . DIRECTORY_SEPARATOR . 'VERSION';

        if (!is_file($versionFile)) {
            return;
        }

        $version = trim((string) file_get_contents($versionFile));

        if ($version === '') {
            return;
        }

        $this->set('app.version', $version);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $data = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $data = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }

            $data = &$data[$segment];
        }

        $data = $value;
    }
}
