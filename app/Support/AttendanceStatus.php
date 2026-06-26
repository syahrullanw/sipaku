<?php

namespace App\Support;

final class AttendanceStatus
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'hadir' => 'Hadir',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'bolos' => 'Bolos',
        'alpa' => 'Tanpa Keterangan',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::LABELS;
    }

    public static function isValid(string $status): bool
    {
        $normalized = strtolower(trim($status));

        return array_key_exists($normalized, self::LABELS);
    }

    public static function normalize(string $status): string
    {
        $normalized = strtolower(trim($status));

        return array_key_exists($normalized, self::LABELS) ? $normalized : 'hadir';
    }
}
