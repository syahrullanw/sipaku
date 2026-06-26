<?php

namespace App\Services;

use Core\Database;
use InvalidArgumentException;
use PDO;

class PpdbCleanupService
{
    private const SCOPE_YEAR = 'year';
    private const SCOPE_GLOBAL = 'global';

    /**
     * @var array<string, array<string, string>>
     */
    private const DATASETS = [
        'registrants' => [
            'label' => 'Data Pendaftar PPDB',
            'description' => 'Menghapus data pendaftar, status seleksi, pengumuman, daftar ulang, dan keterkaitan ke data siswa.',
            'scope' => self::SCOPE_YEAR,
        ],
        'payments' => [
            'label' => 'Riwayat Pembayaran PPDB',
            'description' => 'Menghapus histori pembayaran biaya PPDB dari seluruh pendaftar.',
            'scope' => self::SCOPE_YEAR,
        ],
        'responsibles' => [
            'label' => 'Penanggung Jawab Periode',
            'description' => 'Menghapus daftar penanggung jawab pada setiap periode PPDB.',
            'scope' => self::SCOPE_YEAR,
        ],
        'periods' => [
            'label' => 'Periode & Pengaturan PPDB',
            'description' => 'Menghapus periode PPDB beserta konfigurasi tahapan, token akses, dan pengaturan jadwal.',
            'scope' => self::SCOPE_YEAR,
        ],
    ];

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::connection();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function datasets(): array
    {
        return self::DATASETS;
    }

    /**
     * @return array<string, int>
     */
    public function countAll(int $targetYearId): array
    {
        $counts = [];

        foreach (self::DATASETS as $key => $definition) {
            $scope = $definition['scope'] ?? self::SCOPE_YEAR;

            if ($scope === self::SCOPE_GLOBAL) {
                $counts[$key] = $this->countDataset($key, null);
            } else {
                $counts[$key] = $targetYearId > 0 ? $this->countDataset($key, $targetYearId) : 0;
            }
        }

        return $counts;
    }

    /**
     * @param array<int, string> $datasets
     * @return array<string, array<string, int>>
     */
    public function clean(int $targetYearId, array $datasets): array
    {
        if ($targetYearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran target tidak valid.');
        }

        $definitions = self::DATASETS;
        $normalized = [];

        foreach ($datasets as $dataset) {
            $dataset = (string) $dataset;

            if ($dataset === '' || !isset($definitions[$dataset])) {
                continue;
            }

            if (!in_array($dataset, $normalized, true)) {
                $normalized[] = $dataset;
            }
        }

        if (empty($normalized)) {
            return [];
        }

        $report = [];

        $this->connection->beginTransaction();

        try {
            foreach ($normalized as $dataset) {
                $scope = $definitions[$dataset]['scope'] ?? self::SCOPE_YEAR;
                $yearArgument = $scope === self::SCOPE_GLOBAL ? null : $targetYearId;

                $deleted = $this->cleanDataset($dataset, $yearArgument);
                $report[$dataset] = [
                    'deleted' => $deleted,
                ];
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $report;
    }

    private function countDataset(string $dataset, ?int $yearId): int
    {
        $statement = null;

        switch ($dataset) {
            case 'registrants':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM ppdb_pendaftar r
                     JOIN ppdb_periode p ON p.id = r.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'payments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM ppdb_pembayaran pay
                     JOIN ppdb_pendaftar r ON r.id = pay.pendaftar_id
                     JOIN ppdb_periode p ON p.id = r.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'responsibles':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM ppdb_periode_penanggung_jawab pr
                     JOIN ppdb_periode p ON p.id = pr.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'periods':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM ppdb_periode WHERE tahun_ajaran_target_id = :year'
                );
                break;
        }

        if ($statement === null) {
            return 0;
        }

        if ($yearId !== null) {
            $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    private function cleanDataset(string $dataset, ?int $yearId): int
    {
        $statement = null;

        switch ($dataset) {
            case 'registrants':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE r FROM ppdb_pendaftar r
                     JOIN ppdb_periode p ON p.id = r.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'payments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE pay FROM ppdb_pembayaran pay
                     JOIN ppdb_pendaftar r ON r.id = pay.pendaftar_id
                     JOIN ppdb_periode p ON p.id = r.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'responsibles':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE pr FROM ppdb_periode_penanggung_jawab pr
                     JOIN ppdb_periode p ON p.id = pr.periode_id
                     WHERE p.tahun_ajaran_target_id = :year'
                );
                break;

            case 'periods':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM ppdb_periode WHERE tahun_ajaran_target_id = :year'
                );
                break;
        }

        if ($statement === null) {
            return 0;
        }

        if ($yearId !== null) {
            $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return 0;
        }

        return $statement->rowCount();
    }
}

