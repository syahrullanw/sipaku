<?php

namespace App\Models;

use Core\Model;
use PDO;

class IncomingLetter extends Model
{
    protected static ?string $table = 'surat_masuk';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForYear(int $schoolYearId): array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM surat_masuk WHERE tahun_ajaran_id = :year_id ORDER BY tanggal_diterima DESC, nomor_agenda DESC'
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

    public static function maxAgenda(int $schoolYearId): int
    {
        $statement = static::connection()->prepare(
            'SELECT MAX(nomor_agenda) AS max_agenda FROM surat_masuk WHERE tahun_ajaran_id = :year_id'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        $value = $result['max_agenda'] ?? null;

        return $value === null ? 0 : (int) $value;
    }
}

