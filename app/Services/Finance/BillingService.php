<?php

namespace App\Services\Finance;

use App\Models\Billing;
use App\Models\BillingCategory;
use App\Models\BillingItem;
use App\Services\Finance\BillingCashService;
use Core\Database;
use PDO;
use RuntimeException;

class BillingService
{
    /**
     * @param array<string, mixed> $billingData
     * @param array<int, array<string, mixed>> $items
     */
    public static function createBilling(array $billingData, array $items): int
    {
        if (empty($items)) {
            throw new RuntimeException('Minimal satu item tagihan harus disediakan.');
        }

        $categoryId = (int) ($billingData['kategori_id'] ?? 0);
        if ($categoryId <= 0 || BillingCategory::find($categoryId) === null) {
            throw new RuntimeException('Kategori tagihan tidak ditemukan.');
        }

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $now = date('Y-m-d H:i:s');
            $data = $billingData;
            $data['kode'] = $billingData['kode'] ?? TransactionCodeGenerator::generate('BIL', static fn (string $candidate): bool => Billing::exists(['kode' => $candidate]));
            $data['created_at'] = $billingData['created_at'] ?? $now;
            $data['updated_at'] = $billingData['updated_at'] ?? $now;
            $data['status'] = $billingData['status'] ?? 'draft';
            $allowedRecurringTypes = ['tidak', 'mingguan', 'bulanan'];
            $recurringType = (string) ($data['rutin_tipe'] ?? 'tidak');
            if (!in_array($recurringType, $allowedRecurringTypes, true)) {
                $recurringType = 'tidak';
            }
            $data['rutin_tipe'] = $recurringType;
            if ($recurringType === 'tidak') {
                $data['rutin_jadwal_berikutnya'] = null;
                $data['rutin_terakhir_generate'] = null;
                $data['rutin_hari_mingguan'] = null;
                $data['rutin_tanggal_bulanan'] = null;
            } else {
                $data['tanggal_jatuh_tempo'] = null;
                if ($recurringType === 'mingguan') {
                    $day = (int) ($data['rutin_hari_mingguan'] ?? 0);
                    if ($day < 1 || $day > 7) {
                        $day = 1;
                    }
                    $data['rutin_hari_mingguan'] = $day;
                    $data['rutin_tanggal_bulanan'] = null;
                } elseif ($recurringType === 'bulanan') {
                    $date = (int) ($data['rutin_tanggal_bulanan'] ?? 0);
                    if ($date < 1 || $date > 31) {
                        $date = 1;
                    }
                    $data['rutin_tanggal_bulanan'] = $date;
                    $data['rutin_hari_mingguan'] = null;
                } else {
                    $data['rutin_hari_mingguan'] = null;
                    $data['rutin_tanggal_bulanan'] = null;
                }
            }

            $billingId = Billing::createAndReturnId($data);

            if ($billingId === null) {
                throw new RuntimeException('Gagal menyimpan tagihan.');
            }

            foreach ($items as $item) {
                $studentId = (int) ($item['siswa_id'] ?? 0);
                $nominal = (float) ($item['nominal'] ?? 0);
                $periodAmount = (float) ($item['nominal_periode'] ?? 0);

                if ($studentId <= 0 || $nominal <= 0) {
                    throw new RuntimeException('Data item tagihan tidak valid.');
                }

                if ($periodAmount <= 0) {
                    $periodAmount = $nominal;
                }

                $insert = [
                    'tagihan_id' => $billingId,
                    'siswa_id' => $studentId,
                    'kelas_id' => $item['kelas_id'] ?? null,
                    'nominal' => $nominal,
                    'nominal_periode' => $periodAmount,
                    'sisa_nominal' => $nominal,
                    'status' => $item['status'] ?? 'menunggu_pembayaran',
                    'catatan' => $item['catatan'] ?? null,
                    'created_at' => $item['created_at'] ?? $now,
                    'updated_at' => $item['updated_at'] ?? $now,
                ];

                if (!BillingItem::create($insert)) {
                    throw new RuntimeException('Gagal menyimpan item tagihan.');
                }
            }

            BillingCashService::initialize($billingId);

            if ($manageTransaction) {
                $connection->commit();
            }

            return $billingId;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function synchronizeItemBalance(int $itemId): void
    {
        if ($itemId <= 0) {
            throw new RuntimeException('ID item tagihan tidak valid.');
        }

        $connection = Database::connection();

        $itemStatement = $connection->prepare('SELECT nominal FROM tagihan_item WHERE id = :id LIMIT 1');
        if ($itemStatement === false) {
            throw new RuntimeException('Gagal membaca data item tagihan.');
        }
        $itemStatement->bindValue(':id', $itemId, PDO::PARAM_INT);
        $itemStatement->execute();
        $item = $itemStatement->fetch(PDO::FETCH_ASSOC);

        if ($item === false) {
            throw new RuntimeException('Item tagihan tidak ditemukan.');
        }

        $nominal = (float) ($item['nominal'] ?? 0);

        $paidStatement = $connection->prepare(
            "SELECT COALESCE(SUM(nominal), 0) FROM pembayaran WHERE tagihan_item_id = :id AND status = 'disetujui'"
        );
        $pendingStatement = $connection->prepare(
            "SELECT COUNT(*) FROM pembayaran WHERE tagihan_item_id = :id AND status = 'menunggu_verifikasi'"
        );

        if ($paidStatement === false || $pendingStatement === false) {
            throw new RuntimeException('Gagal membaca riwayat pembayaran.');
        }

        $paidStatement->bindValue(':id', $itemId, PDO::PARAM_INT);
        $pendingStatement->bindValue(':id', $itemId, PDO::PARAM_INT);
        $paidStatement->execute();
        $pendingStatement->execute();

        $paid = $paidStatement->fetchColumn();
        $pending = $pendingStatement->fetchColumn();

        $paidAmount = $paid === false ? 0.0 : (float) $paid;
        $pendingCount = $pending === false ? 0 : (int) $pending;

        $remaining = max(0.0, round($nominal - $paidAmount, 2));

        $status = 'menunggu_pembayaran';
        if ($remaining <= 0.0) {
            $status = 'lunas';
        } elseif ($pendingCount > 0) {
            $status = 'menunggu_verifikasi';
        } elseif ($paidAmount > 0) {
            $status = 'cicilan_berjalan';
        }

        BillingItem::updateById($itemId, [
            'sisa_nominal' => $remaining,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function tryClosingBilling(int $billingId): void
    {
        if ($billingId <= 0) {
            return;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT COUNT(*) FROM tagihan_item WHERE tagihan_id = :id AND status <> 'lunas'"
        );

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':id', $billingId, PDO::PARAM_INT);
        $statement->execute();
        $remaining = $statement->fetchColumn();

        if ($remaining !== false && (int) $remaining === 0) {
            Billing::updateById($billingId, [
                'status' => 'ditutup',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
