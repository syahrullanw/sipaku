<?php

namespace App\Models;

use Core\Model;
use PDO;

class ActivityFundRealization extends Model
{
    protected static ?string $table = 'dana_kegiatan_realisasi';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forActivity(int $activityId): array
    {
        if ($activityId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM dana_kegiatan_realisasi WHERE dana_kegiatan_id = :activity ORDER BY tanggal ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
