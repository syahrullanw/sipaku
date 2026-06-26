<?php

namespace Core;

class Session
{
    private const FLASH_KEY = '_flash';
    private const OLD_INPUT_KEY = '_old_input';

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);

        if (isset($_SESSION[self::FLASH_KEY]) && empty($_SESSION[self::FLASH_KEY])) {
            unset($_SESSION[self::FLASH_KEY]);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function flashInput(array $input): void
    {
        $_SESSION[self::FLASH_KEY][self::OLD_INPUT_KEY] = $input;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        static $cached;

        if ($cached === null) {
            $cached = $_SESSION[self::FLASH_KEY][self::OLD_INPUT_KEY] ?? [];
            unset($_SESSION[self::FLASH_KEY][self::OLD_INPUT_KEY]);

            if (isset($_SESSION[self::FLASH_KEY]) && empty($_SESSION[self::FLASH_KEY])) {
                unset($_SESSION[self::FLASH_KEY]);
            }
        }

        return $cached[$key] ?? $default;
    }
}
