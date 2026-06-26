<?php

namespace App\Services\Finance;

use Core\Database;
use DateTimeImmutable;
use RuntimeException;

class BillingCashService
{
    public static function initialize(int $billingId): void
    {
        if ($billingId <= 0) {
            throw new RuntimeException('ID tagihan tidak valid.');
        }

        $connection = Database::connection();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'INSERT INTO tagihan_kas (tagihan_id, saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at)
             VALUES (:billing_id, 0, 0, 0, :now, :now)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal menyiapkan inisialisasi kas tagihan.');
        }

        $statement->bindValue(':billing_id', $billingId, \PDO::PARAM_INT);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }

    public static function increase(int $billingId, float $amount): void
    {
        if ($billingId <= 0) {
            throw new RuntimeException('ID tagihan tidak valid.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0.0) {
            return;
        }

        $connection = Database::connection();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'INSERT INTO tagihan_kas (tagihan_id, saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at)
             VALUES (:billing_id, :amount, 0, :amount, :now, :now)
             ON DUPLICATE KEY UPDATE
                saldo_masuk = saldo_masuk + VALUES(saldo_masuk),
                saldo_akhir = saldo_akhir + VALUES(saldo_akhir),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal memperbarui kas tagihan.');
        }

        $statement->bindValue(':billing_id', $billingId, \PDO::PARAM_INT);
        $statement->bindValue(':amount', $amount);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }

    public static function decrease(int $billingId, float $amount): void
    {
        if ($billingId <= 0) {
            throw new RuntimeException('ID tagihan tidak valid.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0.0) {
            return;
        }

        $connection = Database::connection();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $statement = $connection->prepare(
            'UPDATE tagihan_kas
             SET saldo_keluar = saldo_keluar + :amount,
                 saldo_akhir = saldo_akhir - :amount,
                 updated_at = :now
             WHERE tagihan_id = :billing_id
             LIMIT 1'
        );

        if ($statement === false) {
            throw new RuntimeException('Gagal mengurangi kas tagihan.');
        }

        $statement->bindValue(':billing_id', $billingId, \PDO::PARAM_INT);
        $statement->bindValue(':amount', $amount);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }
}
