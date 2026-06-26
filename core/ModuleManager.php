<?php

namespace Core;

use RuntimeException;

class ModuleManager
{
    protected Application $app;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $modules = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->discover();
    }

    public function register(): void
    {
        foreach ($this->modules as $module) {
            $manifest = $module['manifest'];

            $routeFile = $manifest['routes']['web'] ?? null;

            if ($routeFile) {
                $path = $module['path'] . DIRECTORY_SEPARATOR . ltrim($routeFile, DIRECTORY_SEPARATOR);
                $this->app->loadRoutesFrom($path);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    protected function discover(): void
    {
        $modulesPath = $this->app->path('modules');

        if (!is_dir($modulesPath)) {
            return;
        }

        $directories = array_filter(scandir($modulesPath) ?: [], static function ($item) {
            return $item !== '.' && $item !== '..';
        });

        foreach ($directories as $directory) {
            $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $directory;

            if (!is_dir($modulePath)) {
                continue;
            }

            $manifestPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';

            if (!file_exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (!is_array($manifest)) {
                throw new RuntimeException("Invalid module manifest for [{$directory}].");
            }

            $name = $manifest['name'] ?? $directory;

            $this->modules[$name] = [
                'path' => $modulePath,
                'manifest' => $manifest,
            ];
        }
    }
}
