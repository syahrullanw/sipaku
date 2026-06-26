<?php

namespace App\Models;

use Core\Model;
use PDO;

class BillingItem extends Model
{
    protected static ?string $table = 'tagihan_item';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forStudent(int $studentId, ?string $status = null): array
    {
        if ($studentId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM tagihan_item WHERE siswa_id = :student';
        if ($status !== null) {
            $sql .= ' AND status = :status';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        if ($status !== null) {
            $statement->bindValue(':status', $status);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byBilling(int $billingId): array
    {
        if ($billingId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM tagihan_item WHERE tagihan_id = :billing ORDER BY siswa_id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':billing', $billingId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
