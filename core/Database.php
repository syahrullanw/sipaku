<?php

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    /**
     * @var array<string, PDO>
     */
    protected static array $connections = [];

    public static function connection(?string $name = null): PDO
    {
        $app = Application::getInstance();

        if ($app === null) {
            throw new RuntimeException('Application instance is not available.');
        }

        $config = $app->config()->get('database', []);
        $name = $name ?? ($config['default'] ?? 'mysql');

        if (!isset($config['connections'][$name])) {
            throw new RuntimeException("Database connection [{$name}] is not configured.");
        }

        if (!isset(static::$connections[$name])) {
            static::$connections[$name] = static::createConnection($config['connections'][$name]);
        }

        return static::$connections[$name];
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function createConnection(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => static::createMySqlConnection($config),
            default => throw new RuntimeException("Driver [{$driver}] is not supported yet."),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function createMySqlConnection(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [];

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset,
        );

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
