<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentSaving;
use Core\Database;
use PDO;
use RuntimeException;

class PaymentService
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function record(array $payload): int
    {
        $itemId = (int) ($payload['tagihan_item_id'] ?? 0);
        $amount = (float) ($payload['nominal'] ?? 0);
        $method = $payload['metode'] ?? 'tunai';

        if ($itemId <= 0 || $amount <= 0) {
            throw new RuntimeException('Data pembayaran tidak valid.');
        }

        if (!in_array($method, ['tunai', 'transfer', 'tabungan'], true)) {
            throw new RuntimeException('Metode pembayaran tidak dikenal.');
        }

        $connection = Database::connection();

        $itemStatement = $connection->prepare(
            'SELECT ti.id, ti.tagihan_id, ti.sisa_nominal, ti.siswa_id, t.kode AS tagihan_kode, t.tahun_ajaran_id, s.status AS siswa_status
             FROM tagihan_item ti
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN siswa s ON s.id = ti.siswa_id
             WHERE ti.id = :id
             LIMIT 1'
        );

        if ($itemStatement === false) {
            throw new RuntimeException('Gagal mempersiapkan pembacaan item tagihan.');
        }

        $itemStatement->bindValue(':id', $itemId, PDO::PARAM_INT);
        $itemStatement->execute();
        $item = $itemStatement->fetch(PDO::FETCH_ASSOC);

        if ($item === false) {
            throw new RuntimeException('Item tagihan tidak ditemukan.');
        }

        if (!Student::hasActiveStatus(['siswa_status' => $item['siswa_status'] ?? null])) {
            throw new RuntimeException('Pembayaran tidak dapat diproses karena status siswa nonaktif.');
        }

        $remaining = (float) ($item['sisa_nominal'] ?? 0);

        if ($amount > $remaining + 0.0001) {
            throw new RuntimeException('Nominal pembayaran melebihi sisa tagihan.');
        }

        $status = $payload['status'] ?? 'menunggu_verifikasi';
        $isSavingsMethod = $method === 'tabungan';

        if ($isSavingsMethod && $status !== 'disetujui') {
            throw new RuntimeException('Pembayaran melalui tabungan harus langsung disetujui.');
        }

        $now = date('Y-m-d H:i:s');
        $paymentTimestamp = $payload['tanggal_bayar'] ?? $now;
        $code = $payload['kode_transaksi'] ?? TransactionCodeGenerator::generate('PAY', static fn (string $candidate): bool => Payment::exists(['kode_transaksi' => $candidate]));

        $record = [
            'tagihan_item_id' => $itemId,
            'kode_transaksi' => $code,
            'tanggal_bayar' => $paymentTimestamp,
            'metode' => $method,
            'nominal' => $amount,
            'sisa_setelah' => max(0.0, $remaining - $amount),
            'status' => $status,
            'bukti_path' => $payload['bukti_path'] ?? null,
            'catatan' => $payload['catatan'] ?? null,
            'diverifikasi_oleh' => $payload['diverifikasi_oleh'] ?? null,
            'diverifikasi_pada' => $payload['diverifikasi_pada'] ?? null,
            'tabungan_transaksi_id' => null,
            'created_at' => $payload['created_at'] ?? $now,
            'updated_at' => $payload['updated_at'] ?? $now,
            'created_by' => $payload['created_by'] ?? null,
            'updated_by' => $payload['updated_by'] ?? null,
        ];

        $studentId = (int) ($item['siswa_id'] ?? 0);
        $schoolYearId = (int) ($item['tahun_ajaran_id'] ?? 0);
        $billingCode = (string) ($item['tagihan_kode'] ?? '');
        $userIdForLog = $record['updated_by'] ?? $record['created_by'] ?? null;

        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            if ($isSavingsMethod) {
                $record['tabungan_transaksi_id'] = static::debitSavings(
                    $studentId,
                    $schoolYearId,
                    $amount,
                    $paymentTimestamp,
                    $record['catatan'] ?? ('Pembayaran tagihan ' . ($billingCode !== '' ? $billingCode : ('#' . ($item['tagihan_id'] ?? '')))),
                    $userIdForLog,
                    $billingCode,
                    (int) ($item['tagihan_id'] ?? 0)
                );
            }

            $insertStatement = $connection->prepare(
                'INSERT INTO pembayaran (
                    tagihan_item_id, kode_transaksi, tanggal_bayar, metode, nominal, sisa_setelah,
                    status, bukti_path, catatan, diverifikasi_oleh, diverifikasi_pada,
                    tabungan_transaksi_id, created_at, updated_at, created_by, updated_by
                ) VALUES (
                    :item, :code, :date, :method, :amount, :sisa,
                    :status, :bukti, :catatan, :verified_by, :verified_at,
                    :saving_tx, :created_at, :updated_at, :created_by, :updated_by
                )'
            );

            if ($insertStatement === false) {
                throw new RuntimeException('Gagal mempersiapkan penyimpanan pembayaran.');
            }

            $insertStatement->bindValue(':item', $record['tagihan_item_id'], PDO::PARAM_INT);
            $insertStatement->bindValue(':code', $record['kode_transaksi']);
            $insertStatement->bindValue(':date', $record['tanggal_bayar']);
            $insertStatement->bindValue(':method', $record['metode']);
            $insertStatement->bindValue(':amount', $record['nominal']);
            $insertStatement->bindValue(':sisa', $record['sisa_setelah']);
            $insertStatement->bindValue(':status', $record['status']);
            $insertStatement->bindValue(':bukti', $record['bukti_path']);
            $insertStatement->bindValue(':catatan', $record['catatan']);
            $insertStatement->bindValue(':verified_by', $record['diverifikasi_oleh'], PDO::PARAM_INT);
            $insertStatement->bindValue(':verified_at', $record['diverifikasi_pada']);
            if ($record['tabungan_transaksi_id'] !== null) {
                $insertStatement->bindValue(':saving_tx', $record['tabungan_transaksi_id'], PDO::PARAM_INT);
            } else {
                $insertStatement->bindValue(':saving_tx', null, PDO::PARAM_NULL);
            }
            $insertStatement->bindValue(':created_at', $record['created_at']);
            $insertStatement->bindValue(':updated_at', $record['updated_at']);
            $insertStatement->bindValue(':created_by', $record['created_by'], PDO::PARAM_INT);
            $insertStatement->bindValue(':updated_by', $record['updated_by'], PDO::PARAM_INT);

            if (!$insertStatement->execute()) {
                throw new RuntimeException('Gagal menyimpan pembayaran.');
            }

            $paymentId = (int) $connection->lastInsertId();

            BillingService::synchronizeItemBalance($itemId);
            BillingService::tryClosingBilling((int) ($item['tagihan_id'] ?? 0));

            if ($status === 'disetujui') {
                BillingCashService::increase((int) ($item['tagihan_id'] ?? 0), $amount);
            }

            if ($manageTransaction) {
                $connection->commit();
            }

            return $paymentId;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    protected static function debitSavings(
        int $studentId,
        int $schoolYearId,
        float $amount,
        string $timestamp,
        ?string $note,
        ?int $userId,
        string $billingCode,
        int $billingId
    ): int {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            throw new RuntimeException('Data tabungan siswa tidak tersedia.');
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
            throw new RuntimeException('Saldo tabungan tidak mencukupi untuk pembayaran tagihan.');
        }

        $description = $note ?? ('Pembayaran tagihan ' . ($billingCode !== '' ? $billingCode : ('#' . $billingId)));

        $transactionId = SavingsService::recordTransaction($accountId, 'tarik', $amount, [
            'tanggal' => $timestamp,
            'dicatat_oleh' => $userId,
            'catatan' => $description,
        ]);

        CashflowService::record('keluar', 'tabungan', $amount, [
            'reference_id' => $transactionId,
            'description' => 'Transfer tabungan ke tagihan ' . ($billingCode !== '' ? $billingCode : ('#' . $billingId)),
            'user_id' => $userId,
            'recorded_at' => $timestamp,
            'school_year_id' => $schoolYearId ?: null,
        ]);

        return $transactionId;
    }
}
