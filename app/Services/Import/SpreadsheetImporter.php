<?php

namespace App\Services\Import;

use RuntimeException;

class SpreadsheetImporter
{
    /**
     * @return array<int, array<int|string, mixed>>
     */
    public static function readRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            self::loadXlsxLibrary();
            $xlsx = \Shuchkin\SimpleXLSX::parse($path);

            if ($xlsx === false) {
                $error = \Shuchkin\SimpleXLSX::parseError();
                throw new RuntimeException($error !== null && $error !== '' ? $error : 'File XLSX tidak dapat dibaca.');
            }

            return $xlsx->rows();
        }

        if ($extension === 'xls') {
            self::loadXlsLibrary();
            $xls = \Shuchkin\SimpleXLS::parse($path);

            if ($xls === false) {
                $error = \Shuchkin\SimpleXLS::parseError();
                throw new RuntimeException($error !== null && $error !== '' ? $error : 'File XLS tidak dapat dibaca.');
            }

            return $xls->rows();
        }

        throw new RuntimeException('Format file tidak didukung. Gunakan file XLS atau XLSX.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function readAssociative(string $path): array
    {
        $rows = self::readRows($path);

        if (empty($rows)) {
            return [];
        }

        $headerRow = array_shift($rows);
        if (!is_array($headerRow)) {
            throw new RuntimeException('Baris header tidak valid.');
        }

        $headers = self::normalizeHeaders($headerRow);
        $results = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            if (self::isRowEmpty($row)) {
                continue;
            }

            $record = [];

            foreach ($headers as $columnIndex => $columnName) {
                $value = $row[$columnIndex] ?? null;
                if (is_string($value)) {
                    $record[$columnName] = trim($value);
                } elseif ($value === null) {
                    $record[$columnName] = null;
                } else {
                    $record[$columnName] = $value;
                }
            }

            $results[] = $record;
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public static function readHeaderNames(string $path): array
    {
        $rows = self::readRows($path);

        if (empty($rows)) {
            return [];
        }

        $headerRow = $rows[0];
        if (!is_array($headerRow)) {
            throw new RuntimeException('Baris header tidak valid.');
        }

        return array_values(self::normalizeHeaders($headerRow));
    }

    private static function loadXlsxLibrary(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        require_once __DIR__ . '/../../Libraries/Spreadsheet/SimpleXLSX.php';
        $loaded = true;
    }

    private static function loadXlsLibrary(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        require_once __DIR__ . '/../../Libraries/Spreadsheet/SimpleXLS.php';
        $loaded = true;
    }

    /**
     * @param array<int|string, mixed> $row
     *
     * @return array<int, string>
     */
    private static function normalizeHeaders(array $row): array
    {
        $headers = [];
        $seen = [];

        foreach ($row as $index => $value) {
            $normalized = self::normalizeHeader(is_scalar($value) ? (string) $value : '');
            if ($normalized === '') {
                $normalized = 'kolom_' . $index;
            }

            if (isset($seen[$normalized])) {
                $suffix = $seen[$normalized] + 1;
                $seen[$normalized] = $suffix;
                $normalized .= '_' . $suffix;
            } else {
                $seen[$normalized] = 1;
            }

            $headers[(int) $index] = $normalized;
        }

        return $headers;
    }

    private static function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);

        return trim((string) $normalized, '_');
    }

    /**
     * @param array<int|string, mixed> $row
     */
    private static function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            if (is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
