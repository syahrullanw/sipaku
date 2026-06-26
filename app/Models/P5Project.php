<?php

namespace App\Models;

use Core\Model;
use PDO;

class P5Project extends Model
{
    protected static ?string $table = 'p5_projek';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT p5.*, g.nama AS guru_pembimbing_nama FROM p5_projek p5 LEFT JOIN guru g ON g.id = p5.guru_pembimbing_id WHERE p5.kelas_id = :class ORDER BY p5.tanggal_mulai DESC, p5.id DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
