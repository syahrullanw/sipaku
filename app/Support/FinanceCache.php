<?php

namespace App\Support;

class FinanceCache
{
    protected static function path(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

        return storage_path('cache/finance_' . $safeKey . '.cache.php');
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $path = static::path($key);

        if (file_exists($path)) {
            $payload = include $path;

            if (is_array($payload) && isset($payload['expires_at'], $payload['value'])) {
                if ($payload['expires_at'] >= time()) {
                    return $payload['value'];
                }
            }
        }

        $value = $callback();

        $data = [
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ];

        file_put_contents($path, '<?php return ' . var_export($data, true) . ';');

        return $value;
    }

    public static function forget(string $key): void
    {
        $path = static::path($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
