<?php

namespace App\Support;

final class SchoolYearDocumentSettings
{
    private const FIELDS = [
        'skl_nomor_surat',
        'skl_tanggal_rapat_pleno',
        'skl_titimangsa',
        'transkrip_nomor_prefix',
    ];

    /**
     * @param array<string, mixed>|null $schoolProfile
     * @param array<string, mixed>|null $schoolYear
     * @return array<string, mixed>
     */
    public static function merge(?array $schoolProfile, ?array $schoolYear): array
    {
        $merged = $schoolProfile ?? [];

        if ($schoolYear === null) {
            return $merged;
        }

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $schoolYear)) {
                $merged[$field] = $schoolYear[$field];
            }
        }

        return $merged;
    }
}
