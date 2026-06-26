<?php

namespace App\Models;

use Core\Model;
use PDO;

class SavingsPoolAdjustment extends Model
{
    protected static ?string $table = 'tabungan_pool_adjustments';

    public static function outstanding(int $schoolYearId): float
    {
        if ($schoolYearId <= 0) {
            return 0.0;
        }

        $statement = static::connection()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN tipe = 'pinjam' THEN nominal ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN tipe = 'kembalikan' THEN nominal ELSE 0 END), 0)
             AS saldo
             FROM tabungan_pool_adjustments
             WHERE tahun_ajaran_id = :year"
        );

        if ($statement === false) {
            return 0.0;
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();
        $value = $statement->fetchColumn();

        return $value === false ? 0.0 : (float) $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latest(int $schoolYearId, int $limit = 20): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $limit = max(1, $limit);

        $statement = static::connection()->prepare(
            'SELECT * FROM tabungan_pool_adjustments
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
