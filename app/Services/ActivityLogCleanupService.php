<?php

namespace App\Services;

use Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

class ActivityLogCleanupService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const DATASETS = [
        'older_30_days' => [
            'label' => 'Log lebih lama dari 30 hari',
            'description' => 'Menghapus catatan aktivitas yang dibuat lebih dari 30 hari yang lalu.',
            'type' => 'age',
            'days' => 30,
        ],
        'older_90_days' => [
            'label' => 'Log lebih lama dari 90 hari',
            'description' => 'Menghapus catatan aktivitas yang dibuat lebih dari 90 hari yang lalu.',
            'type' => 'age',
            'days' => 90,
        ],
        'older_180_days' => [
            'label' => 'Log lebih lama dari 180 hari',
            'description' => 'Menghapus catatan aktivitas yang dibuat lebih dari 180 hari yang lalu.',
            'type' => 'age',
            'days' => 180,
        ],
        'error_logs' => [
            'label' => 'Log Error (4xx / 5xx)',
            'description' => 'Menghapus log yang memiliki status 4xx/5xx atau menyimpan pesan error.',
            'type' => 'error',
        ],
        'all_logs' => [
            'label' => 'Seluruh Log Aktivitas',
            'description' => 'Menghapus seluruh catatan log aktivitas pengguna tanpa kecuali.',
            'type' => 'all',
        ],
    ];

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::connection();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function datasets(): array
    {
        return self::DATASETS;
    }

    /**
     * @return array<string, int>
     */
    public function countAll(): array
    {
        $counts = [];

        foreach (self::DATASETS as $key => $definition) {
            $counts[$key] = $this->countDataset($definition);
        }

        return $counts;
    }

    /**
     * @param array<int, string> $datasets
     *
     * @return array<string, array<string, int>>
     */
    public function clean(array $datasets): array
    {
        if (empty($datasets)) {
            throw new InvalidArgumentException('Tidak ada dataset yang dipilih.');
        }

        $definitions = self::DATASETS;
        $targets = [];

        foreach ($datasets as $dataset) {
            $dataset = (string) $dataset;

            if ($dataset === '' || !isset($definitions[$dataset])) {
                continue;
            }

            if (!in_array($dataset, $targets, true)) {
                $targets[] = $dataset;
            }
        }

        if (empty($targets)) {
            return [];
        }

        $report = [];

        $this->connection->beginTransaction();

        try {
            foreach ($targets as $key) {
                $definition = $definitions[$key];
                $deleted = $this->cleanDataset($definition);
                $report[$key] = ['deleted' => $deleted];
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function countDataset(array $definition): int
    {
        $type = $definition['type'] ?? 'age';

        return match ($type) {
            'all' => $this->countAllLogs(),
            'error' => $this->countErrorLogs(),
            default => $this->countOlderThanDays((int) ($definition['days'] ?? 0)),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function cleanDataset(array $definition): int
    {
        $type = $definition['type'] ?? 'age';

        return match ($type) {
            'all' => $this->deleteAllLogs(),
            'error' => $this->deleteErrorLogs(),
            default => $this->deleteOlderThanDays((int) ($definition['days'] ?? 0)),
        };
    }

    private function countAllLogs(): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM user_activity_logs');

        return $statement !== false ? (int) $statement->fetchColumn() : 0;
    }

    private function countErrorLogs(): int
    {
        $sql = "SELECT COUNT(*) FROM user_activity_logs WHERE (status_code >= 400 AND status_code IS NOT NULL) OR (error_message IS NOT NULL AND error_message <> '')";
        $statement = $this->connection->query($sql);

        return $statement !== false ? (int) $statement->fetchColumn() : 0;
    }

    private function countOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $threshold = $this->thresholdDate($days);

        $statement = $this->connection->prepare('SELECT COUNT(*) FROM user_activity_logs WHERE created_at < :threshold');

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':threshold', $threshold);

        return $statement->execute() ? (int) $statement->fetchColumn() : 0;
    }

    private function deleteAllLogs(): int
    {
        $statement = $this->connection->prepare('DELETE FROM user_activity_logs');

        if ($statement === false) {
            return 0;
        }

        $statement->execute();

        return (int) $statement->rowCount();
    }

    private function deleteErrorLogs(): int
    {
        $sql = "DELETE FROM user_activity_logs WHERE (status_code >= 400 AND status_code IS NOT NULL) OR (error_message IS NOT NULL AND error_message <> '')";
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $statement->execute();

        return (int) $statement->rowCount();
    }

    private function deleteOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $threshold = $this->thresholdDate($days);

        $statement = $this->connection->prepare('DELETE FROM user_activity_logs WHERE created_at < :threshold');

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':threshold', $threshold);
        $statement->execute();

        return (int) $statement->rowCount();
    }

    private function thresholdDate(int $days): string
    {
        $now = new DateTimeImmutable('now');

        return $now->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
    }
}

