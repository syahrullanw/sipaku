<?php

namespace App\Services\Finance;

use App\Models\Billing;
use App\Models\GeneralCash;
use App\Models\GeneralCashTransaction;
use App\Models\GeneralCash as GeneralCashModel;
use App\Models\BillingCash;
use App\Models\SupplyPurchase;
use App\Support\FinanceCache;
use Core\Database;
use RuntimeException;

class GeneralCashService
{
    public static function ensure(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            throw new RuntimeException('Tahun ajaran tidak valid.');
        }

        $record = GeneralCash::findByYear($schoolYearId);

        if ($record !== null) {
            return $record;
        }

        $now = date('Y-m-d H:i:s');

        $created = GeneralCashModel::createAndReturnId([
            'tahun_ajaran_id' => $schoolYearId,
            'saldo' => 0.0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($created === null) {
            throw new RuntimeException('Gagal inisialisasi kas utama.');
        }

        return [
            'id' => $created,
            'tahun_ajaran_id' => $schoolYearId,
            'saldo' => 0.0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public static function balance(int $schoolYearId): float
    {
        if ($schoolYearId <= 0) {
            return 0.0;
        }

        $record = GeneralCash::findByYear($schoolYearId);

        return $record !== null ? (float) ($record['saldo'] ?? 0.0) : 0.0;
    }

    public static function addExternal(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal tambahan kas harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Tambahan dana kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        if ($manageTransaction) {
            $connection->beginTransaction();
        }

        try {
            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $statement = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo + :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($statement === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $statement->bindValue(':amount', $amount);
            $statement->bindValue(':now', $now);
            $statement->bindValue(':id', $generalId);
            $statement->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'masuk',
                'sumber_tipe' => 'eksternal',
                'sumber_id' => null,
                'tujuan_tipe' => 'kas_umum',
                'tujuan_id' => $generalId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            CashflowService::record('masuk', 'kas_umum', $amount, [
                'reference_id' => $generalId,
                'description' => $description === '' ? 'Top up kas utama' : $description,
                'user_id' => $userId,
                'recorded_at' => $recordedAt,
                'school_year_id' => $schoolYearId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function transferFromBilling(int $schoolYearId, int $billingId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($billingId <= 0) {
            throw new RuntimeException('Tagihan tidak ditemukan.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal transfer harus lebih dari nol.');
        }

        $billing = Billing::find($billingId);

        if ($billing === null) {
            throw new RuntimeException('Data tagihan tidak tersedia.');
        }

        if ((int) ($billing['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            throw new RuntimeException('Tagihan tidak termasuk dalam tahun ajaran aktif.');
        }

        $billingCash = BillingCash::findByBillingId($billingId);
        if ($billingCash === null) {
            BillingCashService::initialize($billingId);
            $billingCash = BillingCash::findByBillingId($billingId);
        }

        $available = $billingCash !== null ? (float) ($billingCash['saldo_akhir'] ?? 0.0) : 0.0;
        if ($available < $amount) {
            throw new RuntimeException('Saldo kas tagihan tidak mencukupi untuk dipindahkan.');
        }

        $description = trim((string) ($options['description'] ?? 'Transfer dari kas tagihan ke kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            BillingCashService::decrease($billingId, $amount);

            $now = date('Y-m-d H:i:s');
            $statement = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo + :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($statement === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $statement->bindValue(':amount', $amount);
            $statement->bindValue(':now', $now);
            $statement->bindValue(':id', $generalId);
            $statement->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'masuk',
                'sumber_tipe' => 'tagihan',
                'sumber_id' => $billingId,
                'tujuan_tipe' => 'kas_umum',
                'tujuan_id' => $generalId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function transferToBilling(int $schoolYearId, int $billingId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($billingId <= 0) {
            throw new RuntimeException('Tagihan tidak ditemukan.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengembalian harus lebih dari nol.');
        }

        $billing = Billing::find($billingId);

        if ($billing === null) {
            throw new RuntimeException('Data tagihan tidak tersedia.');
        }

        if ((int) ($billing['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            throw new RuntimeException('Tagihan tidak termasuk dalam tahun ajaran aktif.');
        }

        $description = trim((string) ($options['description'] ?? 'Pengembalian kas ke tagihan'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $update = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo - :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            BillingCashService::increase($billingId, $amount);

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'tagihan',
                'tujuan_id' => $billingId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function transferFromPurchase(int $schoolYearId, int $purchaseId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($purchaseId <= 0) {
            throw new RuntimeException('Pembelian tidak ditemukan.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal transfer harus lebih dari nol.');
        }

        $purchase = SupplyPurchase::find($purchaseId);

        if ($purchase === null) {
            throw new RuntimeException('Data pembelian tidak tersedia.');
        }

        if ((int) ($purchase['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            throw new RuntimeException('Pembelian tidak termasuk dalam tahun ajaran aktif.');
        }

        $description = trim((string) ($options['description'] ?? 'Transfer dari kas pembelian ke kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $cashRow = self::findPurchaseCash($purchaseId);
            if ($cashRow === null) {
                PurchaseCashService::initialize($purchaseId);
                $cashRow = self::findPurchaseCash($purchaseId);
            }

            $available = $cashRow !== null ? (float) ($cashRow['saldo_akhir'] ?? 0.0) : 0.0;
            if ($available < $amount) {
                throw new RuntimeException('Saldo kas pembelian tidak mencukupi untuk dipindahkan.');
            }

            PurchaseCashService::decrease($purchaseId, $amount);

            $now = date('Y-m-d H:i:s');
            $statement = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo + :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($statement === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $statement->bindValue(':amount', $amount);
            $statement->bindValue(':now', $now);
            $statement->bindValue(':id', $generalId);
            $statement->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'masuk',
                'sumber_tipe' => 'pembelian',
                'sumber_id' => $purchaseId,
                'tujuan_tipe' => 'kas_umum',
                'tujuan_id' => $generalId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function transferToPurchase(int $schoolYearId, int $purchaseId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($purchaseId <= 0) {
            throw new RuntimeException('Pembelian tidak ditemukan.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal transfer harus lebih dari nol.');
        }

        $purchase = SupplyPurchase::find($purchaseId);

        if ($purchase === null) {
            throw new RuntimeException('Data pembelian tidak tersedia.');
        }

        if ((int) ($purchase['tahun_ajaran_id'] ?? 0) !== $schoolYearId) {
            throw new RuntimeException('Pembelian tidak termasuk dalam tahun ajaran aktif.');
        }

        $description = trim((string) ($options['description'] ?? 'Pengembalian kas ke pembelian'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $update = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo - :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            PurchaseCashService::increase($purchaseId, $amount);

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'pembelian',
                'tujuan_id' => $purchaseId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function withdrawForLoan(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pencairan harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pencairan kasbon dari kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $loanId = $options['loan_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);
            $now = date('Y-m-d H:i:s');

            $update = $connection->prepare(
                'UPDATE kas_umum
                 SET saldo = saldo - :amount,
                     updated_at = :now
                 WHERE id = :id
                 LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'kasbon',
                'tujuan_id' => $loanId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function withdrawForActivity(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pencairan harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pencairan dana kegiatan dari kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $activityId = $options['activity_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);
            $now = date('Y-m-d H:i:s');

            $update = $connection->prepare(
                'UPDATE kas_umum
                 SET saldo = saldo - :amount,
                     updated_at = :now
                 WHERE id = :id
                 LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'kegiatan',
                'tujuan_id' => $activityId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function withdrawForUnexpectedExpense(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengeluaran harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pengeluaran tak terduga dari kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $expenseId = $options['expense_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);
            $now = date('Y-m-d H:i:s');

            $update = $connection->prepare(
                'UPDATE kas_umum
                 SET saldo = saldo - :amount,
                     updated_at = :now
                 WHERE id = :id
                 LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'tak_terduga',
                'tujuan_id' => $expenseId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function withdrawForPurchase(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pembelian harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pembelian perlengkapan dari kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $billingId = $options['billing_id'] ?? null;
        $purchaseId = $options['purchase_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);
            $now = date('Y-m-d H:i:s');

            $update = $connection->prepare(
                'UPDATE kas_umum
                 SET saldo = saldo - :amount,
                     updated_at = :now
                 WHERE id = :id
                 LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            $targetType = 'tagihan';
            $targetId = $billingId;
            if ($purchaseId !== null) {
                $preferredType = static::resolveKasUmumTarget('pembelian');
                if ($preferredType === 'pembelian') {
                    $targetType = 'pembelian';
                    $targetId = $purchaseId;
                }
            }

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => $targetType,
                'tujuan_id' => $targetId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function withdrawForTeacherSalary(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Total gaji bersih harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pencairan gaji guru dari kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;
        $recordId = $options['record_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);
            $now = date('Y-m-d H:i:s');

            $update = $connection->prepare(
                'UPDATE kas_umum
                 SET saldo = saldo - :amount,
                     updated_at = :now
                 WHERE id = :id
                 LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'honor',
                'tujuan_id' => $recordId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function borrowFromSavings(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pinjaman harus lebih dari nol.');
        }

        $description = trim((string) ($options['description'] ?? 'Pinjam dana tabungan ke kas utama'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $available = SavingsPoolService::availableToBorrow($schoolYearId);
            if ($amount > $available) {
                throw new RuntimeException('Nominal pinjaman melebihi saldo tabungan yang tersedia.');
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $update = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo + :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'masuk',
                'sumber_tipe' => 'tabungan',
                'sumber_id' => null,
                'tujuan_tipe' => 'kas_umum',
                'tujuan_id' => $generalId,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            SavingsPoolService::recordBorrow($schoolYearId, $amount, [
                'description' => $description,
                'recorded_at' => $recordedAt,
                'user_id' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function returnToSavings(int $schoolYearId, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengembalian harus lebih dari nol.');
        }

        $outstanding = SavingsPoolService::outstanding($schoolYearId);
        if ($amount > $outstanding) {
            throw new RuntimeException('Nominal pengembalian melebihi total pinjaman tabungan.');
        }

        $description = trim((string) ($options['description'] ?? 'Pengembalian dana tabungan'));
        $recordedAt = $options['recorded_at'] ?? date('Y-m-d H:i:s');
        $userId = $options['user_id'] ?? null;

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $generalCash = static::ensure($schoolYearId);
            $generalId = (int) ($generalCash['id'] ?? 0);

            $now = date('Y-m-d H:i:s');
            $update = $connection->prepare(
                'UPDATE kas_umum SET saldo = saldo - :amount, updated_at = :now WHERE id = :id LIMIT 1'
            );

            if ($update === false) {
                throw new RuntimeException('Gagal memperbarui saldo kas utama.');
            }

            $update->bindValue(':amount', $amount);
            $update->bindValue(':now', $now);
            $update->bindValue(':id', $generalId);
            $update->execute();

            static::recordTransaction($schoolYearId, [
                'tipe' => 'keluar',
                'sumber_tipe' => 'kas_umum',
                'sumber_id' => $generalId,
                'tujuan_tipe' => 'tabungan',
                'tujuan_id' => null,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'keterangan' => $description,
                'dicatat_oleh' => $userId,
            ]);

            SavingsPoolService::recordReturn($schoolYearId, $amount, [
                'description' => $description,
                'recorded_at' => $recordedAt,
                'user_id' => $userId,
            ]);

            static::forgetDashboards($schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findPurchaseCash(int $purchaseId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM pembelian_kas WHERE pembelian_id = :id LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $purchaseId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected static function recordTransaction(int $schoolYearId, array $data): void
    {
        $code = TransactionCodeGenerator::generate('GCF', static function (string $candidate): bool {
            return GeneralCashTransaction::exists(['kode_transaksi' => $candidate]);
        });

        $timestamp = $data['tanggal'] ?? date('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');

        GeneralCashTransaction::create([
            'tahun_ajaran_id' => $schoolYearId,
            'kode_transaksi' => $code,
            'tipe' => $data['tipe'],
            'sumber_tipe' => $data['sumber_tipe'],
            'sumber_id' => $data['sumber_id'] ?? null,
            'tujuan_tipe' => $data['tujuan_tipe'],
            'tujuan_id' => $data['tujuan_id'] ?? null,
            'nominal' => $data['nominal'],
            'tanggal' => $timestamp,
            'keterangan' => $data['keterangan'] ?? null,
            'dicatat_oleh' => $data['dicatat_oleh'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected static function forgetDashboards(int $schoolYearId): void
    {
        FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);
        FinanceCache::forget('bendahara_dashboard_stats_0');
        FinanceCache::forget('kepsek_dashboard_summary_' . date('Y_m'));
    }

    private static array $kasUmumTargetCache = [];

    private static function resolveKasUmumTarget(string $preferred): string
    {
        if (isset(self::$kasUmumTargetCache[$preferred])) {
            return self::$kasUmumTargetCache[$preferred];
        }

        try {
            $statement = Database::connection()->query("SHOW COLUMNS FROM kas_umum_transaksi LIKE 'tujuan_tipe'");
            if ($statement !== false) {
                $definition = $statement->fetch(\PDO::FETCH_ASSOC);
                if ($definition !== false) {
                    $type = (string) ($definition['Type'] ?? '');
                    if (str_contains($type, "'" . $preferred . "'")) {
                        self::$kasUmumTargetCache[$preferred] = $preferred;

                        return $preferred;
                    }
                }
            }
        } catch (\Throwable $exception) {
            // ignore and fallback
        }

        self::$kasUmumTargetCache[$preferred] = 'tagihan';

        return 'tagihan';
    }
}
