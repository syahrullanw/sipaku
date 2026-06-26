<?php

namespace App\Models;

use Core\Model;
use PDO;

class CocurricularActivityElement extends Model
{
    protected static ?string $table = 'kokurikuler_kegiatan_elemen';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byActivity(int $activityId): array
    {
        if ($activityId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    ke.*,
    el.kode AS elemen_kode,
    el.nama AS elemen_nama,
    el.fase AS elemen_fase,
    el.deskripsi AS elemen_deskripsi,
    dim.nama AS dimensi_nama,
    dim.kode AS dimensi_kode
FROM kokurikuler_kegiatan_elemen ke
LEFT JOIN p5_elemen el ON el.id = ke.elemen_id
LEFT JOIN p5_dimensi dim ON dim.id = el.dimensi_id
WHERE ke.kegiatan_id = :activity
ORDER BY ke.id ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
