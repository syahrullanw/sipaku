<?php

namespace App\Services\Finance;

use App\Models\PurchasePayment;
use App\Models\Student;
use App\Models\StudentSaving;
use App\Models\SupplyPurchase;
use App\Support\FinanceCache;
use Core\Database;
use PDO;
use RuntimeException;

// Services
use App\Services\Finance\TransactionCodeGenerator;
use App\Services\Finance\PurchaseCashService;
use App\Services\Finance\GeneralCashService;
use App\Services\Finance\CashflowService;
use App\Services\Finance\SavingsService;

class PurchasePaymentService
{
    /**
     * @param array<string, mixed> $options
     * @return array{payment_id:int,payment:array<string,mixed>|null,purchase:array<string,mixed>|null,remaining:float}
     */
    public static function record(int $purchaseId, float $amount, string $method, array $options = []): array
    {
        if ($purchaseId <= 0 || $amount <= 0) {
            throw new RuntimeException('Data pembayaran pembelian tidak valid.');
        }

        if (!in_array($method, ['cash', 'tabungan', 'sekolah'], true)) {
            throw new RuntimeException('Metode pembayaran pembelian tidak dikenal.');
        }

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        if ($manageTransaction) {
            $connection->beginTransaction();
        }

        try {
            $purchase = self::findPurchaseForUpdate($connection, $purchaseId);

            if ($purchase === null) {
                throw new RuntimeException('Pembelian tidak ditemukan.');
            }

            $schoolYearId = (int) ($purchase['tahun_ajaran_id'] ?? 0);
            $studentId = (int) ($purchase['siswa_id'] ?? 0);
            $remaining = self::calculateOutstanding($purchase);

            if (!Student::isActiveId($studentId)) {
                throw new RuntimeException('Pembayaran pembelian tidak dapat diproses karena status siswa nonaktif.');
            }

            if ($amount > $remaining + 0.0001) {
                throw new RuntimeException('Nominal pembayaran melebihi sisa pembelian.');
            }

            $note = trim((string) ($options['note'] ?? ''));
            $timestamp = $options['paid_at'] ?? date('Y-m-d H:i:s');
            $userId = $options['user_id'] ?? null;
            $code = $options['transaction_code'] ?? TransactionCodeGenerator::generate('PPY', static fn (string $candidate): bool => PurchasePayment::exists(['kode_transaksi' => $candidate]));

            $savingsTransactionId = null;
            if ($method === 'tabungan') {
                $savingsTransactionId = self::debitSavings($studentId, $schoolYearId, $amount, $timestamp, $note, $userId, (string) ($purchase['kode'] ?? ''));
            } elseif ($method === 'sekolah') {
                GeneralCashService::withdrawForPurchase($schoolYearId, $amount, [
                    'description' => $note !== '' ? $note : ('Pembelian ' . ($purchase['item_label'] ?? 'perlengkapan')),
                    'recorded_at' => $timestamp,
                    'user_id' => $userId,
                    'purchase_id' => $purchaseId,
                ]);

                CashflowService::record('keluar', 'kas_umum', $amount, [
                    'reference_id' => $purchaseId,
                    'description' => $note !== '' ? $note : ('Pembelian ' . ($purchase['item_label'] ?? 'perlengkapan')),
                    'user_id' => $userId,
                    'school_year_id' => $schoolYearId ?: null,
                    'recorded_at' => $timestamp,
                ]);
            }

            $remainingAfter = max(0.0, $remaining - $amount);
            $newPaid = (float) ($purchase['nominal_terbayar'] ?? 0.0) + $amount;
            $status = self::determineStatus($newPaid, $remainingAfter);

            $paymentId = PurchasePayment::createAndReturnId([
                'pembelian_id' => $purchaseId,
                'kode_transaksi' => $code,
                'tanggal_bayar' => $timestamp,
                'metode' => $method,
                'nominal' => $amount,
                'sisa_setelah' => $remainingAfter,
                'catatan' => $note !== '' ? $note : null,
                'tabungan_transaksi_id' => $savingsTransactionId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($paymentId === null) {
                throw new RuntimeException('Gagal menyimpan pembayaran pembelian.');
            }

            SupplyPurchase::updateById($purchaseId, [
                'nominal_terbayar' => $newPaid,
                'sisa_nominal' => $remainingAfter,
                'status' => $status,
                'updated_at' => $timestamp,
                'updated_by' => $userId,
            ]);

            PurchaseCashService::increase($purchaseId, $amount);

            $cashflowSource = self::resolveCashflowSource('pembelian');
            CashflowService::record('masuk', $cashflowSource, $amount, [
                'reference_id' => $paymentId,
                'description' => $note !== '' ? $note : ('Pembayaran pembelian ' . ($purchase['item_label'] ?? 'perlengkapan')),
                'user_id' => $userId,
                'school_year_id' => $schoolYearId ?: null,
                'recorded_at' => $timestamp,
            ]);

            FinanceCache::forget('bendahara_dashboard_stats_' . ($schoolYearId ?: 0));
            FinanceCache::forget('bendahara_dashboard_stats_0');

            if ($manageTransaction) {
                $connection->commit();
            }

            return [
                'payment_id' => $paymentId,
                'payment' => PurchasePayment::find($paymentId),
                'purchase' => SupplyPurchase::find($purchaseId),
                'remaining' => $remainingAfter,
            ];
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
    private static function findPurchaseForUpdate(PDO $connection, int $purchaseId): ?array
    {
        $statement = $connection->prepare('SELECT * FROM pembelian_perlengkapan WHERE id = :id LIMIT 1 FOR UPDATE');

        if ($statement === false) {
            throw new RuntimeException('Gagal mempersiapkan pembacaan pembelian.');
        }

        $statement->bindValue(':id', $purchaseId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $purchase
     */
    private static function calculateOutstanding(array $purchase): float
    {
        $nominal = (float) ($purchase['nominal'] ?? 0.0);
        $paid = (float) ($purchase['nominal_terbayar'] ?? 0.0);
        $storedOutstanding = (float) ($purchase['sisa_nominal'] ?? 0.0);

        if ($storedOutstanding > 0) {
            return $storedOutstanding;
        }

        $remaining = $nominal - $paid;

        return $remaining > 0 ? $remaining : 0.0;
    }

    private static function determineStatus(float $paid, float $remainingAfter): string
    {
        if ($remainingAfter <= 0.0) {
            return 'lunas';
        }

        if ($paid > 0.0) {
            return 'cicilan_berjalan';
        }

        return 'menunggu_pembayaran';
    }

    private static function debitSavings(int $studentId, int $schoolYearId, float $amount, string $timestamp, string $note, ?int $userId, string $purchaseCode): int
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            throw new RuntimeException('Data siswa untuk tabungan tidak lengkap.');
        }

        $savingRecord = StudentSaving::findByStudentAndYear($studentId, $schoolYearId);

        if ($savingRecord === null) {
            throw new RuntimeException('Siswa tidak memiliki tabungan aktif.');
        }

        $accountId = (int) ($savingRecord['id'] ?? 0);
        if (($savingRecord['status'] ?? 'nonaktif') !== 'aktif') {
            throw new RuntimeException('Tabungan siswa tidak aktif.');
        }

        $availableBalance = (float) ($savingRecord['saldo_terakhir'] ?? 0);

        if ($amount > $availableBalance + 0.0001) {
            throw new RuntimeException('Saldo tabungan tidak mencukupi untuk pembayaran pembelian.');
        }

        $description = $note !== '' ? $note : ('Pembayaran pembelian ' . ($purchaseCode !== '' ? $purchaseCode : '#' . $studentId));

        $transactionId = SavingsService::recordTransaction($accountId, 'tarik', $amount, [
            'tanggal' => $timestamp,
            'dicatat_oleh' => $userId,
            'catatan' => $description,
        ]);

        CashflowService::record('keluar', 'tabungan', $amount, [
            'reference_id' => $transactionId,
            'description' => 'Transfer tabungan ke pembelian ' . ($purchaseCode !== '' ? $purchaseCode : '#' . $studentId),
            'user_id' => $userId,
            'recorded_at' => $timestamp,
            'school_year_id' => $schoolYearId ?: null,
        ]);

        return $transactionId;
    }

    private static array $cashflowSourceCache = [];

    private static function resolveCashflowSource(string $preferred): string
    {
        if (isset(self::$cashflowSourceCache[$preferred])) {
            return self::$cashflowSourceCache[$preferred];
        }

        try {
            $statement = Database::connection()->query("SHOW COLUMNS FROM arus_kas LIKE 'sumber'");
            if ($statement !== false) {
                $definition = $statement->fetch(PDO::FETCH_ASSOC);
                if ($definition !== false) {
                    $type = (string) ($definition['Type'] ?? '');
                    if (str_contains($type, "'" . $preferred . "'")) {
                        self::$cashflowSourceCache[$preferred] = $preferred;

                        return $preferred;
                    }
                }
            }
        } catch (\Throwable $exception) {
            // ignore and fallback
        }

        self::$cashflowSourceCache[$preferred] = 'tagihan';

        return 'tagihan';
    }
}
