<?php

namespace App\Services\Finance;

use App\Support\FinanceCache;
use Core\Log;

class ReminderService
{
    /**
     * Jalankan reminder tagihan jatuh tempo dalam rentang hari yang diatur di config.
     * Saat ini log ke channel finance sebagai placeholder.
     */
    public static function dispatchBillingReminders(): void
    {
        $days = config('finance.tagihan_reminder_days', [7, 3, 1]);

        sort($days);

        foreach ($days as $day) {
            $cacheKey = 'billing_reminder_' . $day . '_' . date('Y-m-d');

            // Hindari pengiriman berulang dalam satu hari.
            $pending = FinanceCache::remember($cacheKey, 3600, static function () use ($day) {
                $targetDate = date('Y-m-d', strtotime("+{$day} days"));
                $connection = \Core\Database::connection();

                $statement = $connection->prepare(
                    "SELECT ti.id, ti.siswa_id, ti.sisa_nominal, t.judul, t.tanggal_jatuh_tempo
                     FROM tagihan_item ti
                     JOIN tagihan t ON t.id = ti.tagihan_id
                     WHERE ti.status IN ('menunggu_pembayaran', 'menunggu_verifikasi', 'cicilan_berjalan')
                       AND t.tanggal_jatuh_tempo = :due"
                );

                if ($statement === false) {
                    return [];
                }

                $statement->bindValue(':due', $targetDate);
                $statement->execute();

                $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

                return $rows === false ? [] : $rows;
            });

            if (!empty($pending)) {
                Log::channel('finance')->info('Reminder tagihan jatuh tempo', [
                    'days_before_due' => $day,
                    'items' => array_map(static fn (array $item) => $item['id'] ?? null, $pending),
                ]);
            }
        }
    }

    /**
     * Reminder cicilan kasbon yang akan jatuh tempo.
     */
    public static function dispatchLoanInstallmentReminders(): void
    {
        $cacheKey = 'loan_installment_reminder_' . date('Y-m-d');

        $upcoming = FinanceCache::remember($cacheKey, 3600, static function () {
            $targetDate = date('Y-m-d', strtotime('+3 days'));
            $connection = \Core\Database::connection();

            $statement = $connection->prepare(
                "SELECT * FROM kasbon_cicilan
                 WHERE status IN ('menunggu','tertunggak')
                   AND jatuh_tempo = :due"
            );

            if ($statement === false) {
                return [];
            }

            $statement->bindValue(':due', $targetDate);
            $statement->execute();

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return $rows === false ? [] : $rows;
        });

        if (!empty($upcoming)) {
            Log::channel('finance')->info('Reminder cicilan kasbon', [
                'items' => array_map(static fn (array $item) => $item['id'] ?? null, $upcoming),
            ]);
        }
    }

    /**
     * Placeholder dispatch honor reminder (misal slip siap diunduh).
     */
    public static function dispatchHonorReminders(): void
    {
        Log::channel('finance')->info('Reminder honor guru (placeholder)');
    }
}
