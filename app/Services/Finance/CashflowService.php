<?php

namespace App\Services\Finance;

use App\Models\Cashflow;
use App\Support\FinanceCache;
use Core\Database;
use PDO;
use RuntimeException;

class CashflowService
{
    /**
     * @param array{
     *     reference_id?: int|null,
     *     reference_code?: string|null,
     *     description?: string|null,
     *     recorded_at?: string|null,
     *     user_id?: int|null,
     *     school_year_id?: int|null,
     * } $options
     */
    public static function record(string $type, string $source, float $amount, array $options = []): bool
    {
        if (!in_array($type, ['masuk', 'keluar'], true)) {
            throw new RuntimeException('Tipe arus kas tidak valid.');
        }

        if (!in_array($source, ['tagihan', 'tabungan', 'kasbon', 'kegiatan', 'honor', 'penyesuaian', 'kas_umum', 'tak_terduga', 'pembelian'], true)) {
            throw new RuntimeException('Sumber arus kas tidak dikenal.');
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal arus kas harus lebih dari nol.');
        }

        $connection = Database::connection();

        $shouldManageTransaction = !$connection->inTransaction();

        try {
            if ($shouldManageTransaction) {
                $connection->beginTransaction();
            }

            $previousBalance = self::getLatestBalanceForUpdate($connection);

            $newBalance = $type === 'masuk'
                ? $previousBalance + $amount
                : $previousBalance - $amount;

            $code = TransactionCodeGenerator::generate('CF', static fn (string $candidate): bool => Cashflow::exists(['kode_transaksi' => $candidate]));

            $timestamp = $options['recorded_at'] ?? date('Y-m-d H:i:s');

            $statement = $connection->prepare(
                'INSERT INTO arus_kas (
                    kode_transaksi, tipe, sumber, referensi_id, referensi_kode, tanggal,
                    nominal, saldo_setelah, keterangan, dicatat_oleh, created_at, updated_at
                ) VALUES (
                    :code, :type, :source, :reference_id, :reference_code, :recorded_at,
                    :amount, :balance, :description, :user_id, :created_at, :updated_at
                )'
            );

            if ($statement === false) {
                throw new RuntimeException('Gagal mempersiapkan penyimpanan arus kas.');
            }

            $statement->bindValue(':code', $code);
            $statement->bindValue(':type', $type);
            $statement->bindValue(':source', $source);
            $statement->bindValue(':reference_id', $options['reference_id'] ?? null, PDO::PARAM_INT);
            $statement->bindValue(':reference_code', $options['reference_code'] ?? null);
            $statement->bindValue(':recorded_at', $timestamp);
            $statement->bindValue(':amount', $amount);
            $statement->bindValue(':balance', $newBalance);
            $statement->bindValue(':description', $options['description'] ?? null);
            $statement->bindValue(':user_id', $options['user_id'] ?? null, PDO::PARAM_INT);
            $statement->bindValue(':created_at', $timestamp);
            $statement->bindValue(':updated_at', $timestamp);

            if (!$statement->execute()) {
                throw new RuntimeException('Gagal menyimpan data arus kas.');
            }

            if (!empty($options['school_year_id'])) {
                $updateYear = $connection->prepare(
                    'UPDATE tahun_ajaran SET saldo_kas_akhir = :saldo WHERE id = :id LIMIT 1'
                );

                if ($updateYear === false) {
                    throw new RuntimeException('Gagal mempersiapkan pembaruan saldo kas tahun ajaran.');
                }

                $updateYear->bindValue(':saldo', $newBalance);
                $updateYear->bindValue(':id', $options['school_year_id'], PDO::PARAM_INT);
                $updateYear->execute();
            }

            $yearId = (int) ($options['school_year_id'] ?? 0);
            FinanceCache::forget('bendahara_dashboard_stats_' . ($yearId ?: 0));
            FinanceCache::forget('bendahara_dashboard_stats_0');
            FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
            FinanceCache::forget('kepsek_dashboard_revenue_' . date('Y_m'));

            if ($shouldManageTransaction) {
                return $connection->commit();
            }

            return true;
        } catch (\Throwable $exception) {
            if ($shouldManageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private static function getLatestBalanceForUpdate(PDO $connection): float
    {
        $statement = $connection->query(
            'SELECT saldo_setelah FROM arus_kas ORDER BY tanggal DESC, id DESC LIMIT 1 FOR UPDATE'
        );

        if ($statement === false) {
            return 0.0;
        }

        $value = $statement->fetchColumn();

        return $value === false ? 0.0 : (float) $value;
    }
}
