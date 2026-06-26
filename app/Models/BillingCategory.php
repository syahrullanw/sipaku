<?php

namespace App\Models;

use Core\Model;
use PDO;

class BillingCategory extends Model
{
    protected static ?string $table = 'kategori_tagihan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function active(): array
    {
        $sql = <<<SQL
SELECT *
FROM kategori_tagihan
WHERE status = 'aktif'
ORDER BY COALESCE(urutan, 999), nama ASC
SQL;

        $statement = static::connection()->query($sql);

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function findByCode(string $code): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM kategori_tagihan WHERE kode = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function ordered(): array
    {
        $statement = static::connection()->query(
            'SELECT * FROM kategori_tagihan ORDER BY COALESCE(urutan, 999), nama ASC'
        );

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
