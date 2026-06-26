<?php

namespace App\Support;

use DateTimeInterface;

class LetterNumber
{
    public static function format(string $typeCode, int $sequence, string $unitCode, DateTimeInterface $date): string
    {
        $sequence = max(1, $sequence);
        $typeCode = strtoupper(trim($typeCode));
        $unitCode = static::normalizeUnitCode($unitCode);

        $sequenceFormatted = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $monthRoman = static::romanMonth((int) $date->format('n'));
        $year = $date->format('Y');

        return sprintf('%s.%s/%s/%s/%s', $typeCode, $sequenceFormatted, $unitCode, $monthRoman, $year);
    }

    public static function romanMonth(int $month): string
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $map[$month] ?? 'I';
    }

    public static function normalizeUnitCode(string $unitCode): string
    {
        $unitCode = trim($unitCode);
        $unitCode = preg_replace('/\s+/', ' ', $unitCode) ?? $unitCode;

        return strtoupper(str_replace(' ', '-', $unitCode));
    }
}

