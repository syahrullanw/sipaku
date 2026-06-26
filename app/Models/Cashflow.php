<?php

namespace App\Models;

use Core\Model;
use PDO;

class Cashflow extends Model
{
    protected static ?string $table = 'arus_kas';

    public static function latestBalance(): float
    {
        $statement = static::connection()->query(
            'SELECT saldo_setelah FROM arus_kas ORDER BY tanggal DESC, id DESC LIMIT 1'
        );

        if ($statement === false) {
            return 0.0;
        }

        $value = $statement->fetchColumn();

        return $value === false ? 0.0 : (float) $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function between(string $startDate, string $endDate): array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM arus_kas WHERE tanggal BETWEEN :start AND :end ORDER BY tanggal ASC, id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':start', $startDate);
        $statement->bindValue(':end', $endDate);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
