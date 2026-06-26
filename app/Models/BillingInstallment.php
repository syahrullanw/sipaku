<?php

namespace App\Models;

use Core\Model;
use PDO;

class BillingInstallment extends Model
{
    protected static ?string $table = 'tagihan_cicilan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pendingForItem(int $itemId): array
    {
        if ($itemId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            "SELECT * FROM tagihan_cicilan WHERE tagihan_item_id = :item AND status IN ('menunggu','tertunggak') ORDER BY jatuh_tempo ASC"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':item', $itemId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
