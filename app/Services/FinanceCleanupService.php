<?php

namespace App\Services;

use Core\Database;
use InvalidArgumentException;
use PDO;

class FinanceCleanupService
{
    private const SCOPE_YEAR = 'year';
    private const SCOPE_GLOBAL = 'global';

    /**
     * @var array<string, array<string, string>>
     */
    private const DATASETS = [
        'billing_categories' => [
            'label' => 'Kategori Tagihan',
            'description' => 'Menghapus seluruh kategori tagihan yang tersedia (berlaku untuk semua tahun).',
            'scope' => self::SCOPE_GLOBAL,
        ],
        'billings' => [
            'label' => 'Tagihan & Rincian Siswa',
            'description' => 'Menghapus tagihan, rincian siswa, cicilan, serta saldo kas tagihan pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'payments' => [
            'label' => 'Riwayat Pembayaran Tagihan',
            'description' => 'Menghapus transaksi pembayaran siswa yang tercatat pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'student_savings' => [
            'label' => 'Tabungan Siswa & Transaksi',
            'description' => 'Menghapus rekening tabungan siswa dan seluruh transaksi setoran/penarikan pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'general_cash' => [
            'label' => 'Kas Utama & Transaksi',
            'description' => 'Menghapus transaksi kas utama dan mereset saldo kas utama pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'cashflows' => [
            'label' => 'Riwayat Arus Kas',
            'description' => 'Menghapus seluruh histori arus kas dan saldo kas akhir yang tercatat (berlaku untuk semua tahun).',
            'scope' => self::SCOPE_GLOBAL,
        ],
        'loans' => [
            'label' => 'Kasbon Guru',
            'description' => 'Menghapus data kasbon guru beserta cicilannya pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'unexpected_expenses' => [
            'label' => 'Pengeluaran Tak Terduga',
            'description' => 'Menghapus pengeluaran tak terduga yang dicatat pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'activity_funds' => [
            'label' => 'Dana Kegiatan & Realisasi',
            'description' => 'Menghapus pengajuan dana kegiatan dan realisasi penggunaannya pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'teacher_salary_settings' => [
            'label' => 'Pengaturan Honor Guru',
            'description' => 'Menghapus konfigurasi komponen honor guru pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'teacher_salary_records' => [
            'label' => 'Rekap Honor & Slip Guru',
            'description' => 'Menghapus rekap honor guru berikut komponen perhitungan pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'teacher_honor' => [
            'label' => 'Pengajuan Honor Guru',
            'description' => 'Menghapus pengajuan honor guru yang diajukan pada tahun ajaran aktif.',
            'scope' => self::SCOPE_YEAR,
        ],
        'finance_approvals' => [
            'label' => 'Persetujuan Keuangan',
            'description' => 'Menghapus seluruh riwayat persetujuan keuangan (berlaku untuk semua tahun).',
            'scope' => self::SCOPE_GLOBAL,
        ],
    'savings_pool_adjustments' => [
        'label' => 'Penyesuaian Dana Tabungan Kolektif',
        'description' => 'Menghapus catatan pinjam/kembalikan dana tabungan kolektif pada tahun ajaran aktif.',
        'scope' => self::SCOPE_YEAR,
        ],
    ];

    private const BILLING_STATUS_CANCELED = 'dibatalkan';
    private const ITEM_STATUS_CANCELED = 'dibatalkan';

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
    public function countAll(int $yearId): array
    {
        $counts = [];

        foreach (self::DATASETS as $key => $definition) {
            $scope = $definition['scope'] ?? self::SCOPE_YEAR;

            if ($scope === self::SCOPE_GLOBAL) {
                $counts[$key] = $this->countDataset($key, null);
            } else {
                $counts[$key] = $yearId > 0 ? $this->countDataset($key, $yearId) : 0;
            }
        }

        return $counts;
    }

    /**
     * @param array<int, string> $datasets
     * @param array<int|string, string> $billingTargets
     * @return array<string, mixed>
     */
    public function clean(int $yearId, array $datasets, array $billingTargets = []): array
    {
        if ($yearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran tidak valid.');
        }

        $definitions = self::DATASETS;
        $normalized = [];
        $normalizedBillingTargets = $this->normalizeBillingTargets($billingTargets);

        foreach ($datasets as $dataset) {
            $dataset = (string) $dataset;
            if ($dataset === '' || !isset($definitions[$dataset])) {
                continue;
            }

            if (!in_array($dataset, $normalized, true)) {
                $normalized[] = $dataset;
            }
        }

        if (empty($normalized) && empty($normalizedBillingTargets)) {
            return [];
        }

        $report = [];

        $this->connection->beginTransaction();

        try {
            $cancellationReport = [];
            if (!empty($normalizedBillingTargets)) {
                $cancellationReport = $this->cancelBillings($yearId, $normalizedBillingTargets);
            }

            foreach ($normalized as $dataset) {
                $scope = $definitions[$dataset]['scope'] ?? self::SCOPE_YEAR;
                $yearArgument = $scope === self::SCOPE_GLOBAL ? null : $yearId;

                $deleted = $this->cleanDataset($dataset, $yearArgument);
                $report[$dataset] = [
                    'deleted' => $deleted,
                ];
            }

            $report['billing_cancellations'] = $cancellationReport;

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
            case 'billing_categories':
                $statement = $this->connection->query('SELECT COUNT(*) FROM kategori_tagihan');
                return $statement !== false ? (int) $statement->fetchColumn() : 0;

            case 'billings':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM tagihan WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'payments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM pembayaran p
                     JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
                     JOIN tagihan t ON t.id = ti.tagihan_id
                     WHERE t.tahun_ajaran_id = :year'
                );
                break;

            case 'student_savings':
                if ($yearId === null) {
                    return 0;
                }
                $accounts = $this->connection->prepare(
                    'SELECT COUNT(*) FROM tabungan_siswa WHERE tahun_ajaran_id = :year'
                );
                $transactions = $this->connection->prepare(
                    'SELECT COUNT(*) FROM tabungan_transaksi tt
                     JOIN tabungan_siswa ts ON ts.id = tt.tabungan_id
                     WHERE ts.tahun_ajaran_id = :year'
                );
                if ($accounts === false || $transactions === false) {
                    return 0;
                }
                $accounts->bindValue(':year', $yearId, PDO::PARAM_INT);
                $transactions->bindValue(':year', $yearId, PDO::PARAM_INT);
                $accounts->execute();
                $transactions->execute();
                return (int) ($accounts->fetchColumn() ?: 0) + (int) ($transactions->fetchColumn() ?: 0);

            case 'general_cash':
                if ($yearId === null) {
                    return 0;
                }
                $cash = $this->connection->prepare(
                    'SELECT COUNT(*) FROM kas_umum WHERE tahun_ajaran_id = :year'
                );
                $transactions = $this->connection->prepare(
                    'SELECT COUNT(*) FROM kas_umum_transaksi WHERE tahun_ajaran_id = :year'
                );
                if ($cash === false || $transactions === false) {
                    return 0;
                }
                $cash->bindValue(':year', $yearId, PDO::PARAM_INT);
                $transactions->bindValue(':year', $yearId, PDO::PARAM_INT);
                $cash->execute();
                $transactions->execute();
                return (int) ($cash->fetchColumn() ?: 0) + (int) ($transactions->fetchColumn() ?: 0);

            case 'cashflows':
                $statement = $this->connection->query('SELECT COUNT(*) FROM arus_kas');
                return $statement !== false ? (int) $statement->fetchColumn() : 0;

            case 'loans':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM kasbon_guru WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'unexpected_expenses':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM pengeluaran_tak_terduga WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'activity_funds':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM dana_kegiatan WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'teacher_salary_settings':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM teacher_salary_settings WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'teacher_salary_records':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM teacher_salary_records WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'teacher_honor':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM honor_guru WHERE tahun_ajaran_id = :year'
                );
                break;

            case 'finance_approvals':
                $statement = $this->connection->query('SELECT COUNT(*) FROM keuangan_approval');
                return $statement !== false ? (int) $statement->fetchColumn() : 0;

            case 'savings_pool_adjustments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'SELECT COUNT(*) FROM tabungan_pool_adjustments WHERE tahun_ajaran_id = :year'
                );
                break;

            default:
                return 0;
        }

        if ($statement === null || $statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        $statement->execute();

        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function cleanDataset(string $dataset, ?int $yearId): int
    {
        $deleted = 0;
        $now = date('Y-m-d H:i:s');

        switch ($dataset) {
            case 'billing_categories':
                $statement = $this->connection->prepare('DELETE FROM kategori_tagihan');
                $statement?->execute();
                return $statement?->rowCount() ?? 0;

            case 'billings':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM tagihan WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'payments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE p FROM pembayaran p
                     JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
                     JOIN tagihan t ON t.id = ti.tagihan_id
                     WHERE t.tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'student_savings':
                if ($yearId === null) {
                    return 0;
                }
                $transactions = $this->connection->prepare(
                    'DELETE tt FROM tabungan_transaksi tt
                     JOIN tabungan_siswa ts ON ts.id = tt.tabungan_id
                     WHERE ts.tahun_ajaran_id = :year'
                );
                if ($transactions !== false) {
                    $transactions->bindValue(':year', $yearId, PDO::PARAM_INT);
                    $transactions->execute();
                    $deleted += $transactions->rowCount();
                }

                $accounts = $this->connection->prepare(
                    'DELETE FROM tabungan_siswa WHERE tahun_ajaran_id = :year'
                );
                if ($accounts !== false) {
                    $accounts->bindValue(':year', $yearId, PDO::PARAM_INT);
                    $accounts->execute();
                    $deleted += $accounts->rowCount();
                }

                return $deleted;

            case 'general_cash':
                if ($yearId === null) {
                    return 0;
                }

                $transactions = $this->connection->prepare(
                    'DELETE FROM kas_umum_transaksi WHERE tahun_ajaran_id = :year'
                );
                if ($transactions !== false) {
                    $transactions->bindValue(':year', $yearId, PDO::PARAM_INT);
                    $transactions->execute();
                    $deleted += $transactions->rowCount();
                }

                $cash = $this->connection->prepare(
                    'DELETE FROM kas_umum WHERE tahun_ajaran_id = :year'
                );
                if ($cash !== false) {
                    $cash->bindValue(':year', $yearId, PDO::PARAM_INT);
                    $cash->execute();
                    $deleted += $cash->rowCount();
                }

                $resetYear = $this->connection->prepare(
                    'UPDATE tahun_ajaran SET saldo_kas_awal = 0, saldo_kas_akhir = NULL, updated_at = :now WHERE id = :year LIMIT 1'
                );
                if ($resetYear !== false) {
                    $resetYear->bindValue(':now', $now);
                    $resetYear->bindValue(':year', $yearId, PDO::PARAM_INT);
                    $resetYear->execute();
                }

                return $deleted;

            case 'cashflows':
                $statement = $this->connection->prepare('DELETE FROM arus_kas');
                if ($statement !== false) {
                    $statement->execute();
                    $deleted += $statement->rowCount();
                }

                $reset = $this->connection->prepare(
                    'UPDATE tahun_ajaran SET saldo_kas_akhir = NULL, updated_at = :now'
                );
                if ($reset !== false) {
                    $reset->bindValue(':now', $now);
                    $reset->execute();
                }

                return $deleted;

            case 'loans':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM kasbon_guru WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'unexpected_expenses':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM pengeluaran_tak_terduga WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'activity_funds':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM dana_kegiatan WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'teacher_salary_settings':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM teacher_salary_settings WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'teacher_salary_records':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM teacher_salary_records WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'teacher_honor':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM honor_guru WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            case 'finance_approvals':
                $statement = $this->connection->prepare('DELETE FROM keuangan_approval');
                if ($statement === false) {
                    return 0;
                }
                $statement->execute();
                return $statement->rowCount();

            case 'savings_pool_adjustments':
                if ($yearId === null) {
                    return 0;
                }
                $statement = $this->connection->prepare(
                    'DELETE FROM tabungan_pool_adjustments WHERE tahun_ajaran_id = :year'
                );
                if ($statement === false) {
                    return 0;
                }
                $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
                $statement->execute();
                return $statement->rowCount();

            default:
                return 0;
        }
    }

    /**
     * @param array<int|string, string> $targets
     * @return array<int, string>
     */
    private function normalizeBillingTargets(array $targets): array
    {
        $normalized = [];

        foreach ($targets as $target) {
            $value = trim((string) $target);
            if ($value === '') {
                continue;
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $targets
     * @return array<int, array<string, mixed>>
     */
    private function cancelBillings(int $yearId, array $targets): array
    {
        if ($yearId <= 0) {
            return [];
        }

        $targets = $this->normalizeBillingTargets($targets);
        if (empty($targets)) {
            return [];
        }

        $conditions = [];
        $bindings = [];

        foreach ($targets as $index => $target) {
            $codeKey = ':billing_code_' . $index;
            $conditions[] = 't.kode = ' . $codeKey;
            $bindings[$codeKey] = $target;

            if (ctype_digit($target)) {
                $idKey = ':billing_id_' . $index;
                $conditions[] = 't.id = ' . $idKey;
                $bindings[$idKey] = (int) $target;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = 'SELECT t.id, t.kode, t.judul, t.status FROM tagihan t
                WHERE t.tahun_ajaran_id = :year AND (' . implode(' OR ', $conditions) . ')';
        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        foreach ($bindings as $placeholder => $value) {
            $parameterType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($placeholder, $value, $parameterType);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false || empty($rows)) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $itemUpdate = $this->connection->prepare(
            'UPDATE tagihan_item
             SET status = :status, sisa_nominal = 0, updated_at = :now
             WHERE tagihan_id = :billing_id AND status <> :status'
        );
        $installmentUpdate = $this->connection->prepare(
            'UPDATE tagihan_cicilan tc
             JOIN tagihan_item ti ON ti.id = tc.tagihan_item_id
             SET tc.status = :status, tc.updated_at = :now
             WHERE ti.tagihan_id = :billing_id AND tc.status <> :status'
        );
        $billingUpdate = $this->connection->prepare(
            'UPDATE tagihan SET status = :status, updated_at = :now WHERE id = :billing_id AND status <> :status'
        );
        $kasUpdate = $this->connection->prepare(
            'UPDATE tagihan_kas SET saldo_masuk = 0, saldo_keluar = 0, saldo_akhir = 0, updated_at = :now WHERE tagihan_id = :billing_id'
        );

        $cancellations = [];

        foreach ($rows as $row) {
            $billingId = (int) ($row['id'] ?? 0);
            if ($billingId <= 0) {
                continue;
            }

            $itemUpdated = 0;
            if ($itemUpdate !== false) {
                $itemUpdate->bindValue(':status', self::ITEM_STATUS_CANCELED);
                $itemUpdate->bindValue(':now', $now);
                $itemUpdate->bindValue(':billing_id', $billingId, PDO::PARAM_INT);
                $itemUpdate->execute();
                $itemUpdated = $itemUpdate->rowCount();
            }

            $installmentUpdated = 0;
            if ($installmentUpdate !== false) {
                $installmentUpdate->bindValue(':status', self::ITEM_STATUS_CANCELED);
                $installmentUpdate->bindValue(':now', $now);
                $installmentUpdate->bindValue(':billing_id', $billingId, PDO::PARAM_INT);
                $installmentUpdate->execute();
                $installmentUpdated = $installmentUpdate->rowCount();
            }

            $billingUpdated = 0;
            if ($billingUpdate !== false) {
                $billingUpdate->bindValue(':status', self::BILLING_STATUS_CANCELED);
                $billingUpdate->bindValue(':now', $now);
                $billingUpdate->bindValue(':billing_id', $billingId, PDO::PARAM_INT);
                $billingUpdate->execute();
                $billingUpdated = $billingUpdate->rowCount();
            }

            $kasUpdated = 0;
            if ($kasUpdate !== false) {
                $kasUpdate->bindValue(':now', $now);
                $kasUpdate->bindValue(':billing_id', $billingId, PDO::PARAM_INT);
                $kasUpdate->execute();
                $kasUpdated = $kasUpdate->rowCount();
            }

            $cancellations[] = [
                'billing_id' => $billingId,
                'billing_code' => (string) ($row['kode'] ?? ''),
                'billing_title' => (string) ($row['judul'] ?? ''),
                'items' => $itemUpdated,
                'installments' => $installmentUpdated,
                'kas_rows' => $kasUpdated,
                'status_changed' => $billingUpdated > 0,
                'previous_status' => (string) ($row['status'] ?? ''),
            ];
        }

        return $cancellations;
    }
}
