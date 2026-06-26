<?php

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefixes = [
        'App\\' => $basePath . '/app/',
        'Core\\' => $basePath . '/core/',
        'Modules\\' => $basePath . '/modules/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});
