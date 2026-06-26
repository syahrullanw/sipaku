<?php

namespace App\Models;

use Core\Model;
use PDO;

class GeneralCashTransaction extends Model
{
    protected static ?string $table = 'kas_umum_transaksi';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latestForYear(int $schoolYearId, int $limit = 20): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $limit = max(1, $limit);

        $statement = static::connection()->prepare(
            'SELECT * FROM kas_umum_transaksi
             WHERE tahun_ajaran_id = :year
             ORDER BY tanggal DESC, id DESC
             LIMIT :limit'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
