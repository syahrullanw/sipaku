<?php

namespace Core;

use DateTimeImmutable;

class Log
{
    public static function channel(string $name = 'default'): Logger
    {
        return LoggerManager::instance()->channel($name);
    }

    public static function info(string $message, array $context = []): void
    {
        static::channel()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::channel()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::channel()->error($message, $context);
    }
}

class Logger
{
    protected string $name;
    protected string $path;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function write(string $level, string $message, array $context = []): void
    {
        $timestamp = new DateTimeImmutable();
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            $timestamp->format('Y-m-d H:i:s'),
            strtoupper($level),
            $this->name,
            $message,
            empty($context) ? '' : json_encode($context, JSON_THROW_ON_ERROR)
        );

        file_put_contents($this->path, $line, FILE_APPEND);
    }
}

class LoggerManager
{
    protected static ?self $instance = null;

    /**
     * @var array<string, Logger>
     */
    protected array $channels = [];

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    public function channel(string $name): Logger
    {
        if (!isset($this->channels[$name])) {
            $path = storage_path('logs/' . $name . '.log');
            $directory = dirname($path);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $this->channels[$name] = new Logger($name, $path);
        }

        return $this->channels[$name];
    }
}
