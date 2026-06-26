<?php

namespace App\Support;

use App\Models\SchoolYear;
use Core\Session;

class SchoolYearContext
{
    private const SESSION_KEY = 'active_school_year_id';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $cached = null;

    public static function id(): ?int
    {
        $year = static::resolve();

        return $year !== null ? (int) ($year['id'] ?? 0) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolve(): ?array
    {
        if (static::$cached !== null) {
            return static::$cached;
        }

        $stored = Session::get(self::SESSION_KEY);

        if (is_numeric($stored)) {
            $identifier = (int) $stored;
            if ($identifier > 0) {
                $existing = SchoolYear::find($identifier);
                if ($existing !== null) {
                    static::$cached = $existing;

                    return $existing;
                }
            }
        }

        $year = SchoolYear::active();

        if ($year === null) {
            $ordered = SchoolYear::allOrdered();
            $year = $ordered[0] ?? null;
        }

        if ($year !== null && isset($year['id'])) {
            Session::set(self::SESSION_KEY, (int) $year['id']);
            static::$cached = $year;

            return $year;
        }

        Session::forget(self::SESSION_KEY);
        static::$cached = null;

        return null;
    }

    public static function set(int $schoolYearId): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        $year = SchoolYear::find($schoolYearId);

        if ($year === null) {
            return false;
        }

        Session::set(self::SESSION_KEY, (int) $year['id']);
        static::$cached = $year;

        return true;
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        static::$cached = null;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return SchoolYear::options();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return SchoolYear::allOrdered();
    }
}

