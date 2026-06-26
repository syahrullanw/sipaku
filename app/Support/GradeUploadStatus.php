<?php

namespace App\Support;

final class GradeUploadStatus
{
    public const DRAFT = 'DRAFT';
    public const FINAL = 'FINAL';
    public const VALIDATING = 'VALIDATING';
    public const VALIDATED = 'VALIDATED';
    public const COMMITTED = 'COMMITTED';
    public const FAILED = 'FAILED';
    public const ROLLED_BACK = 'ROLLED_BACK';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::FINAL,
            self::VALIDATING,
            self::VALIDATED,
            self::COMMITTED,
            self::FAILED,
            self::ROLLED_BACK,
        ];
    }

    public static function isValid(string $status): bool
    {
        return in_array(strtoupper(trim($status)), self::all(), true);
    }

    public static function label(string $status): string
    {
        $normalized = strtoupper(trim($status));

        return match ($normalized) {
            self::DRAFT => 'Draft',
            self::FINAL, self::COMMITTED => 'Final',
            self::FAILED => 'Perlu diperbaiki',
            self::ROLLED_BACK => 'Diganti revisi',
            self::VALIDATING => 'Sedang diproses',
            self::VALIDATED => 'Siap disimpan',
            default => $normalized !== '' ? $normalized : '-',
        };
    }
}
