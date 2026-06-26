<?php

namespace Core;

use InvalidArgumentException;

class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $viewPath = static::resolvePath('views', $view);

        if (!file_exists($viewPath)) {
            throw new InvalidArgumentException("View [{$view}] not found.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $viewPath;
        $content = ob_get_clean() ?: '';

        if ($layout === null) {
            return $content;
        }

        $layoutPath = static::resolvePath('layouts', $layout);

        if (!file_exists($layoutPath)) {
            throw new InvalidArgumentException("Layout [{$layout}] not found.");
        }

        $slot = $content;
        $title ??= config('app.name', 'Aplikasi Sekolah');

        ob_start();
        include $layoutPath;

        return ob_get_clean() ?: '';
    }

    protected static function resolvePath(string $base, string $name): string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';

        return resource_path($base . DIRECTORY_SEPARATOR . $relative);
    }
}
