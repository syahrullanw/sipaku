<?php

namespace App\Services\Finance;

use Core\Database;
use RuntimeException;

class PurchaseCashService
{
    public static function initialize(int $purchaseId): void
    {
        if ($purchaseId <= 0) {
            throw new RuntimeException('ID pembelian tidak valid untuk inisialisasi kas.');
        }

        $connection = Database::connection();
        $now = date('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'INSERT INTO pembelian_kas (pembelian_id, saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at)
             VALUES (:purchase, 0, 0, 0, :now, :now)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal menyiapkan kas pembelian.');
        }

        $statement->bindValue(':purchase', $purchaseId, \PDO::PARAM_INT);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }

    public static function increase(int $purchaseId, float $amount): void
    {
        if ($purchaseId <= 0) {
            throw new RuntimeException('ID pembelian tidak valid untuk kas.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $connection = Database::connection();
        $now = date('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'INSERT INTO pembelian_kas (pembelian_id, saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at)
             VALUES (:purchase, :amount, 0, :amount, :now, :now)
             ON DUPLICATE KEY UPDATE
                 saldo_masuk = saldo_masuk + VALUES(saldo_masuk),
                 saldo_akhir = saldo_akhir + VALUES(saldo_akhir),
                 updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal memperbarui kas pembelian.');
        }

        $statement->bindValue(':purchase', $purchaseId, \PDO::PARAM_INT);
        $statement->bindValue(':amount', $amount);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }

    public static function decrease(int $purchaseId, float $amount): void
    {
        if ($purchaseId <= 0) {
            throw new RuntimeException('ID pembelian tidak valid untuk kas.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            return;
        }

        $connection = Database::connection();
        $now = date('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'UPDATE pembelian_kas
             SET saldo_keluar = saldo_keluar + :amount,
                 saldo_akhir = saldo_akhir - :amount,
                 updated_at = :now
             WHERE pembelian_id = :purchase
             LIMIT 1'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal mengurangi kas pembelian.');
        }

        $statement->bindValue(':purchase', $purchaseId, \PDO::PARAM_INT);
        $statement->bindValue(':amount', $amount);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }
}
