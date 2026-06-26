<?php

namespace App\Services\Finance;

use App\Models\Billing;
use App\Models\BillingItem;
use App\Support\FinanceCache;
use Core\Database;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

class RecurringBillingService
{
    public static function initialNextSchedule(
        string $type,
        ?int $weeklyDay = null,
        ?int $monthlyDate = null,
        ?DateTimeInterface $clock = null
    ): ?string {
        if (!in_array($type, ['mingguan', 'bulanan'], true)) {
            return null;
        }

        $weeklyDay = static::normalizeWeeklyDay($weeklyDay);
        $monthlyDate = static::normalizeMonthlyDate($monthlyDate);

        if (($type === 'mingguan' && $weeklyDay === null) || ($type === 'bulanan' && $monthlyDate === null)) {
            return null;
        }

        $reference = $clock !== null ? DateTimeImmutable::createFromInterface($clock) : new DateTimeImmutable();
        $reference = $reference->setTime(0, 0);

        $next = static::nextScheduleDate($type, $reference, $weeklyDay, $monthlyDate, true);

        return $next !== null ? $next->format('Y-m-d') : null;
    }

    public static function generateDue(?DateTimeInterface $today = null): int
    {
        $nowDate = $today !== null ? DateTimeImmutable::createFromInterface($today) : new DateTimeImmutable();
        $nowDate = $nowDate->setTime(0, 0);

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT * FROM tagihan
             WHERE status = 'aktif'
               AND rutin_tipe IN ('mingguan','bulanan')
               AND rutin_jadwal_berikutnya IS NOT NULL
               AND rutin_jadwal_berikutnya <= :today"
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':today', $nowDate->format('Y-m-d'));
        $statement->execute();

        $billings = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($billings === false) {
            return 0;
        }

        $processed = 0;
        foreach ($billings as $billing) {
            $processed += static::processBilling($billing, $nowDate);
        }

        return $processed;
    }

    /**
     * @param array<string, mixed> $billing
     */
    protected static function processBilling(array $billing, DateTimeImmutable $today): int
    {
        $billingId = (int) ($billing['id'] ?? 0);
        if ($billingId <= 0) {
            return 0;
        }

        $type = (string) ($billing['rutin_tipe'] ?? 'tidak');
        if (!in_array($type, ['mingguan', 'bulanan'], true)) {
            return 0;
        }

        $weeklyDay = isset($billing['rutin_hari_mingguan']) ? static::normalizeWeeklyDay((int) $billing['rutin_hari_mingguan']) : null;
        $monthlyDate = isset($billing['rutin_tanggal_bulanan']) ? static::normalizeMonthlyDate((int) $billing['rutin_tanggal_bulanan']) : null;

        if (($type === 'mingguan' && $weeklyDay === null) || ($type === 'bulanan' && $monthlyDate === null)) {
            return 0;
        }

        $scheduleRaw = $billing['rutin_jadwal_berikutnya'] ?? null;
        if (!is_string($scheduleRaw) || $scheduleRaw === '') {
            return 0;
        }

        $schedule = DateTimeImmutable::createFromFormat('Y-m-d', $scheduleRaw);
        if ($schedule === false) {
            return 0;
        }

        $schedule = $schedule->setTime(0, 0);

        $occurrences = 0;
        $lastGenerated = null;
        $nextSchedule = $schedule;

        while ($nextSchedule !== null && $nextSchedule <= $today) {
            $occurrences++;
            $lastGenerated = $nextSchedule;
            $nextSchedule = static::nextScheduleDate($type, $nextSchedule, $weeklyDay, $monthlyDate, false);
        }

        if ($occurrences === 0 || $lastGenerated === null) {
            return 0;
        }

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();
        $totalIncrement = 0.0;

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $items = BillingItem::byBilling($billingId);
            $now = date('Y-m-d H:i:s');

            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                $periodAmount = (float) ($item['nominal_periode'] ?? 0.0);
                if ($periodAmount <= 0.0) {
                    $periodAmount = (float) ($item['nominal'] ?? 0.0);
                }

                if ($periodAmount <= 0.0) {
                    continue;
                }

                $increment = round($periodAmount * $occurrences, 2);
                if ($increment <= 0.0) {
                    continue;
                }

                $newNominal = round((float) ($item['nominal'] ?? 0.0) + $increment, 2);
                $newRemaining = round((float) ($item['sisa_nominal'] ?? 0.0) + $increment, 2);
                $currentStatus = (string) ($item['status'] ?? 'menunggu_pembayaran');
                if ($newRemaining <= 0.0) {
                    $newStatus = 'lunas';
                } elseif (in_array($currentStatus, ['lunas', 'gagal'], true)) {
                    $newStatus = 'menunggu_pembayaran';
                } else {
                    $newStatus = $currentStatus;
                }

                BillingItem::updateById($itemId, [
                    'nominal' => $newNominal,
                    'sisa_nominal' => $newRemaining,
                    'status' => $newStatus,
                    'updated_at' => $now,
                ]);

                $totalIncrement += $increment;
            }

            $updates = [
                'rutin_jadwal_berikutnya' => $nextSchedule !== null ? $nextSchedule->format('Y-m-d') : null,
                'rutin_terakhir_generate' => $lastGenerated->format('Y-m-d'),
                'updated_at' => $now,
            ];

            if ($totalIncrement > 0.0) {
                $updates['nominal_total'] = round((float) ($billing['nominal_total'] ?? 0.0) + $totalIncrement, 2);
            }

            Billing::updateById($billingId, $updates);

            if ($manageTransaction) {
                $connection->commit();
            }

            if ($totalIncrement > 0.0) {
                $yearId = (int) ($billing['tahun_ajaran_id'] ?? 0);
                FinanceCache::forget('bendahara_dashboard_stats_' . $yearId);
                FinanceCache::forget('bendahara_dashboard_stats_0');
            }

            return $occurrences;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    protected static function nextScheduleDate(
        string $type,
        DateTimeInterface $from,
        ?int $weeklyDay,
        ?int $monthlyDate,
        bool $includeSameDay
    ): ?DateTimeImmutable {
        $base = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);

        if ($type === 'mingguan') {
            $targetDay = static::normalizeWeeklyDay($weeklyDay) ?? 1;
            $currentDay = (int) $base->format('N'); // 1 = Monday
            $difference = ($targetDay - $currentDay + 7) % 7;

            if ($difference === 0) {
                return $includeSameDay ? $base : $base->modify('+7 days');
            }

            return $base->modify('+' . $difference . ' days');
        }

        if ($type === 'bulanan') {
            $targetDate = static::normalizeMonthlyDate($monthlyDate) ?? 1;

            if ($includeSameDay) {
                $candidate = static::resolveMonthlyDate($base, $targetDate);
                if ($candidate >= $base) {
                    return $candidate;
                }
            }

            $nextMonthBase = $base->modify('first day of next month');

            return static::resolveMonthlyDate($nextMonthBase, $targetDate);
        }

        return null;
    }

    protected static function normalizeWeeklyDay(?int $day): ?int
    {
        if ($day === null) {
            return null;
        }

        if ($day < 1 || $day > 7) {
            return null;
        }

        return $day;
    }

    protected static function normalizeMonthlyDate(?int $date): ?int
    {
        if ($date === null) {
            return null;
        }

        if ($date < 1 || $date > 31) {
            return null;
        }

        return $date;
    }

    protected static function resolveMonthlyDate(DateTimeImmutable $base, int $targetDay): DateTimeImmutable
    {
        $daysInMonth = (int) $base->format('t');
        $day = min($targetDay, $daysInMonth);

        return $base->setDate(
            (int) $base->format('Y'),
            (int) $base->format('m'),
            $day
        );
    }
}
