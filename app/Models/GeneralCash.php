<?php

namespace App\Models;

use Core\Model;
use PDO;

class GeneralCash extends Model
{
    protected static ?string $table = 'kas_umum';

    public static function findByYear(int $schoolYearId): ?array
    {
        if ($schoolYearId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM kas_umum WHERE tahun_ajaran_id = :year LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
