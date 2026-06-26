<?php

namespace App\Models;

use Core\Model;
use PDO;

class P5ProjectElement extends Model
{
    protected static ?string $table = 'p5_projek_elemen';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byProject(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT pel.*, el.kode AS elemen_kode, el.nama AS elemen_nama FROM p5_projek_elemen pel LEFT JOIN p5_elemen el ON el.id = pel.elemen_id WHERE pel.projek_id = :project ORDER BY pel.urutan IS NULL, pel.urutan ASC, pel.id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':project', $projectId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
