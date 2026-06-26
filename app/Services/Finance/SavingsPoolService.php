<?php

namespace App\Services\Finance;

use App\Models\SavingsPoolAdjustment;
use Core\Database;
use RuntimeException;

class SavingsPoolService
{
    public static function outstanding(int $schoolYearId): float
    {
        return SavingsPoolAdjustment::outstanding($schoolYearId);
    }

    public static function availableToBorrow(int $schoolYearId): float
    {
        if ($schoolYearId <= 0) {
            return 0.0;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT COALESCE(SUM(saldo_terakhir), 0)
             FROM tabungan_siswa
             WHERE tahun_ajaran_id = :year
               AND status = 'aktif'"
        );

        if ($statement === false) {
            return 0.0;
        }

        $statement->bindValue(':year', $schoolYearId, \PDO::PARAM_INT);
        if (!$statement->execute()) {
            return 0.0;
        }

        $totalBalance = $statement->fetchColumn();
        $total = $totalBalance === false ? 0.0 : (float) $totalBalance;
        $available = $total - static::outstanding($schoolYearId);

        return $available > 0 ? $available : 0.0;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function recordBorrow(int $schoolYearId, float $amount, array $options = []): void
    {
        static::record($schoolYearId, $amount, 'pinjam', $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function recordReturn(int $schoolYearId, float $amount, array $options = []): void
    {
        static::record($schoolYearId, $amount, 'kembalikan', $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function record(int $schoolYearId, float $amount, string $type, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($schoolYearId <= 0) {
            throw new RuntimeException('Tahun ajaran tidak valid.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal harus lebih dari nol.');
        }

        if (!in_array($type, ['pinjam', 'kembalikan'], true)) {
            throw new RuntimeException('Tipe penyesuaian tabungan tidak dikenal.');
        }

        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $description = $options['description'] ?? null;

        $now = date('Y-m-d H:i:s');

        SavingsPoolAdjustment::create([
            'tahun_ajaran_id' => $schoolYearId,
            'tipe' => $type,
            'nominal' => $amount,
            'tanggal' => $recordedAt,
            'keterangan' => $description,
            'dicatat_oleh' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
