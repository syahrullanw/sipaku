<?php

namespace App\Services\Finance;

use App\Models\StudentSaving;
use App\Models\StudentSavingTransaction;
use App\Models\Student;
use Core\Database;
use PDO;
use RuntimeException;

class SavingsService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public static function ensureAccount(int $studentId, int $schoolYearId, array $attributes = []): int
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            throw new RuntimeException('Data tabungan siswa tidak valid.');
        }

        if (!Student::isActiveId($studentId)) {
            throw new RuntimeException('Tabungan tidak dapat diproses karena status siswa nonaktif.');
        }

        $existing = StudentSaving::findByStudentAndYear($studentId, $schoolYearId);

        if ($existing !== null) {
            return (int) ($existing['id'] ?? 0);
        }

        $now = date('Y-m-d H:i:s');
        $record = array_merge([
            'siswa_id' => $studentId,
            'tahun_ajaran_id' => $schoolYearId,
            'saldo_terakhir' => 0.0,
            'status' => 'aktif',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $attributes['created_by'] ?? null,
            'updated_by' => $attributes['updated_by'] ?? null,
        ], $attributes);

        $accountId = StudentSaving::createAndReturnId($record);

        if ($accountId === null) {
            throw new RuntimeException('Gagal membuat tabungan siswa.');
        }

        return $accountId;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function recordTransaction(int $savingId, string $type, float $amount, array $options = []): int
    {
        if ($savingId <= 0) {
            throw new RuntimeException('ID tabungan tidak valid.');
        }

        if (!in_array($type, ['setor', 'tarik'], true)) {
            throw new RuntimeException('Jenis transaksi tabungan tidak dikenal.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Nominal transaksi tabungan harus lebih dari nol.');
        }

        $connection = Database::connection();

        $statement = $connection->prepare('SELECT * FROM tabungan_siswa WHERE id = :id LIMIT 1');

        if ($statement === false) {
            throw new RuntimeException('Gagal memuat data tabungan siswa.');
        }

        $statement->bindValue(':id', $savingId, PDO::PARAM_INT);
        $statement->execute();
        $saving = $statement->fetch(PDO::FETCH_ASSOC);

        if ($saving === false) {
            throw new RuntimeException('Tabungan siswa tidak ditemukan.');
        }

        if (!Student::isActiveId((int) ($saving['siswa_id'] ?? 0))) {
            throw new RuntimeException('Transaksi tabungan tidak dapat diproses karena status siswa nonaktif.');
        }

        $currentBalance = (float) ($saving['saldo_terakhir'] ?? 0);

        if ($type === 'tarik' && $amount > $currentBalance) {
            throw new RuntimeException('Saldo tabungan tidak mencukupi untuk penarikan.');
        }

        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $newBalance = $type === 'setor'
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            $code = $options['kode_transaksi'] ?? TransactionCodeGenerator::generate('SAV', static fn (string $candidate): bool => StudentSavingTransaction::exists(['kode_transaksi' => $candidate]));
            $timestamp = $options['tanggal'] ?? date('Y-m-d H:i:s');

            $insert = $connection->prepare(
                'INSERT INTO tabungan_transaksi (
                    tabungan_id, kode_transaksi, jenis, tanggal, nominal, saldo_setelah,
                    bukti_path, catatan, dicatat_oleh, created_at, updated_at
                ) VALUES (
                    :tabungan, :kode, :jenis, :tanggal, :nominal, :saldo,
                    :bukti, :catatan, :user_id, :created_at, :updated_at
                )'
            );

            if ($insert === false) {
                throw new RuntimeException('Gagal mencatat transaksi tabungan.');
            }

            $insert->bindValue(':tabungan', $savingId, PDO::PARAM_INT);
            $insert->bindValue(':kode', $code);
            $insert->bindValue(':jenis', $type);
            $insert->bindValue(':tanggal', $timestamp);
            $insert->bindValue(':nominal', $amount);
            $insert->bindValue(':saldo', $newBalance);
            $insert->bindValue(':bukti', $options['bukti_path'] ?? null);
            $insert->bindValue(':catatan', $options['catatan'] ?? null);
            $insert->bindValue(':user_id', $options['dicatat_oleh'] ?? null, PDO::PARAM_INT);
            $insert->bindValue(':created_at', $timestamp);
            $insert->bindValue(':updated_at', $timestamp);

            if (!$insert->execute()) {
                throw new RuntimeException('Gagal menyimpan transaksi tabungan.');
            }

            $transactionId = (int) $connection->lastInsertId();

            StudentSaving::updateById($savingId, [
                'saldo_terakhir' => $newBalance,
                'updated_at' => $timestamp,
                'updated_by' => $options['dicatat_oleh'] ?? null,
            ]);

            if ($manageTransaction) {
                $connection->commit();
            }

            return $transactionId;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
