<?php

namespace App\Services;

class AssessmentEvaluator
{
    private const KURMER_LEVELS = ['BB', 'MB', 'BSH', 'SB'];

    public static function normalizeScore(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < 0 || $number > 100) {
            return null;
        }

        return round($number, 2);
    }

    public static function normalizeScoreOrZero(mixed $value): ?float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 0.0;
            }
            $value = $trimmed;
        }

        return self::normalizeScore($value);
    }

    public static function determinePredicate(float $score, bool $kkmEnabled, ?float $kkmValue): string
    {
        $score = round($score, 2);
        $kkm = $kkmEnabled ? ($kkmValue ?? null) : null;

        if ($kkm !== null && $kkm > 0) {
            if ($score < $kkm) {
                return 'Perlu Bimbingan';
            }
        }

        if ($score < 70) {
            return 'Perlu Bimbingan';
        }

        if ($score <= 80) {
            return 'Cukup';
        }

        if ($score <= 90) {
            return 'Baik';
        }

        return 'Sangat Baik';
    }

    public static function normalizeKurmerCapaian(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = strtoupper(trim((string) $value));

        return in_array($string, self::KURMER_LEVELS, true) ? $string : null;
    }
}
