<?php

namespace Core;

use App\Support\MaintenanceMode;
use App\Support\UserModuleRules;
use App\Services\UserActivityLogger;

class Application
{
    protected static ?Application $instance = null;

    protected string $basePath;

    protected Config $config;

    protected Router $router;

    protected ModuleManager $modules;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        static::$instance = $this;

        $this->config = new Config($this->path('app/Config'));
        $this->router = new Router($this);
        $this->modules = new ModuleManager($this);
    }

    public static function getInstance(): ?self
    {
        return static::$instance;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function path(string $path = ''): string
    {
        return rtrim($this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''), DIRECTORY_SEPARATOR);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function modules(): ModuleManager
    {
        return $this->modules;
    }

    public function bootstrap(): void
    {
        $timezone = $this->config->get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);

        $this->modules->register();
    }

    public function loadRoutesFrom(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $router = $this->router;
        $app = $this;

        require $path;
    }

    public function routes(callable $callback): void
    {
        $callback($this->router);
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            if (!MaintenanceMode::allowsCurrentRequest($request)) {
                $response = MaintenanceMode::response($request);
            } else {
                $response = UserModuleRules::guardCurrentRequest($request)
                    ?? $this->router->dispatch($request);
            }
        } catch (\Throwable $exception) {
            UserActivityLogger::log($request, null, $this->router->getLastResolvedAction(), $exception);

            throw $exception;
        }

        UserActivityLogger::log($request, $response, $this->router->getLastResolvedAction());
        $response->send();
    }
}
