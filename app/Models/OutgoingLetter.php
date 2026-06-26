<?php

namespace App\Models;

use Core\Model;
use PDO;

class OutgoingLetter extends Model
{
    protected static ?string $table = 'surat_keluar';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForYear(int $schoolYearId): array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM surat_keluar WHERE tahun_ajaran_id = :year_id ORDER BY tanggal_surat DESC, nomor_urut DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function maxSequence(int $schoolYearId): int
    {
        $statement = static::connection()->prepare(
            'SELECT MAX(nomor_urut) AS max_sequence FROM surat_keluar WHERE tahun_ajaran_id = :year_id'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        $value = $result['max_sequence'] ?? null;

        return $value === null ? 0 : (int) $value;
    }

    public static function existsNumber(string $number, ?int $ignoreId = null): bool
    {
        return static::exists(['nomor_surat' => $number], $ignoreId);
    }
}

