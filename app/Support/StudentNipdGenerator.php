<?php

namespace App\Support;

use Core\Database;
use RuntimeException;

class StudentNipdGenerator
{
    public const TYPE_REGULAR = 'regular';
    public const TYPE_TRANSFER = 'transfer';

    private const TYPE_CODES = [
        self::TYPE_REGULAR => '1',
        self::TYPE_TRANSFER => '2',
    ];

    /**
     * @param array<string, mixed> $schoolYear
     */
    public static function generateNext(array $schoolYear, string $type): string
    {
        $seriesPrefix = self::seriesPrefix($schoolYear, $type);
        $sequence = self::lastSequence($seriesPrefix) + 1;

        return $seriesPrefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $schoolYear
     */
    public static function previewNext(array $schoolYear, string $type): string
    {
        return self::generateNext($schoolYear, $type);
    }

    /**
     * @param array<string, mixed> $schoolYear
     */
    public static function academicYearPrefix(array $schoolYear): string
    {
        foreach (['kode', 'nama'] as $field) {
            $prefix = self::prefixFromText((string) ($schoolYear[$field] ?? ''));
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $start = self::yearFromDate((string) ($schoolYear['tanggal_mulai'] ?? ''));
        $end = self::yearFromDate((string) ($schoolYear['tanggal_selesai'] ?? ''));

        if ($start !== null && $end !== null) {
            return substr((string) $start, -2) . substr((string) $end, -2);
        }

        throw new RuntimeException('Format tahun ajaran tidak dapat dipakai untuk membuat NIPD.');
    }

    /**
     * @param array<string, mixed> $schoolYear
     */
    private static function seriesPrefix(array $schoolYear, string $type): string
    {
        $typeCode = self::TYPE_CODES[$type] ?? null;
        if ($typeCode === null) {
            throw new RuntimeException('Tipe siswa untuk NIPD tidak valid.');
        }

        return self::academicYearPrefix($schoolYear) . $typeCode;
    }

    private static function prefixFromText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/(?<!\d)(20\d{2})\s*[\/\-.]\s*(20\d{2})(?!\d)/', $value, $matches) === 1) {
            return substr($matches[1], -2) . substr($matches[2], -2);
        }

        if (preg_match('/(?<!\d)(\d{2})\s*[\/\-.]\s*(\d{2})(?!\d)/', $value, $matches) === 1) {
            return $matches[1] . $matches[2];
        }

        if (preg_match('/(?<!\d)(\d{4})(?!\d)/', $value, $matches) === 1 && !str_starts_with($matches[1], '20')) {
            return $matches[1];
        }

        return '';
    }

    private static function yearFromDate(string $value): ?int
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return (int) date('Y', $timestamp);
    }

    private static function lastSequence(string $seriesPrefix): int
    {
        $statement = Database::connection()->prepare('SELECT nipd FROM siswa WHERE nipd LIKE :pattern');
        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':pattern', $seriesPrefix . '%');
        if (!$statement->execute()) {
            return 0;
        }

        $rows = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
        if ($rows === false) {
            return 0;
        }

        $max = 0;
        $pattern = '/^' . preg_quote($seriesPrefix, '/') . '(\d+)$/';

        foreach ($rows as $row) {
            if (preg_match($pattern, (string) $row, $matches) !== 1) {
                continue;
            }

            $max = max($max, (int) $matches[1]);
        }

        return $max;
    }
}
