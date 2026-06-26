<?php

namespace App\Models;

use Core\Model;
use PDO;

class BillingCash extends Model
{
    protected static ?string $table = 'tagihan_kas';

    /**
     * @return array<string, mixed>|null
     */
    public static function findByBillingId(int $billingId): ?array
    {
        if ($billingId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM tagihan_kas WHERE tagihan_id = :billing LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':billing', $billingId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
