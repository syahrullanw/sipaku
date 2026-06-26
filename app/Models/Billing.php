<?php

namespace App\Models;

use Core\Model;
use PDO;

class Billing extends Model
{
    protected static ?string $table = 'tagihan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forSchoolYear(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM tagihan WHERE tahun_ajaran_id = :year ORDER BY created_at DESC, id DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function findByCode(string $code): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM tagihan WHERE kode = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
