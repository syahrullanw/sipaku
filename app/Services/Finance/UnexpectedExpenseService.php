<?php

namespace App\Services\Finance;

use App\Models\UnexpectedExpense;
use Core\Database;
use RuntimeException;

class UnexpectedExpenseService
{
    /**
     * @param array{
     *     tipe_pemohon: string,
     *     guru_id?: int|null,
     *     pemohon_nama: string,
     *     deskripsi?: string|null,
     *     nominal: float,
     *     tanggal?: string|null,
     *     dicatat_oleh?: int|null,
     * } $data
     */
    public static function create(int $schoolYearId, array $data): int
    {
        if ($schoolYearId <= 0) {
            throw new RuntimeException('Tahun ajaran aktif tidak valid.');
        }

        $amount = round((float) ($data['nominal'] ?? 0), 2);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengeluaran harus lebih dari nol.');
        }

        $requesterType = (string) ($data['tipe_pemohon'] ?? '');
        if (!in_array($requesterType, ['guru', 'siswa', 'lainnya'], true)) {
            throw new RuntimeException('Tipe pemohon pengeluaran tidak valid.');
        }

        $teacherId = $requesterType === 'guru' ? (int) ($data['guru_id'] ?? 0) : null;
        $studentId = $requesterType === 'siswa' ? (int) ($data['siswa_id'] ?? 0) : null;

        if ($requesterType === 'guru' && ($teacherId === null || $teacherId <= 0)) {
            throw new RuntimeException('Pilih guru pemohon pengeluaran.');
        }

        if ($requesterType === 'siswa' && ($studentId === null || $studentId <= 0)) {
            throw new RuntimeException('Pilih siswa pemohon pengeluaran.');
        }

        $requesterName = trim((string) ($data['pemohon_nama'] ?? ''));

        if ($requesterName === '') {
            throw new RuntimeException('Nama pemohon pengeluaran harus diisi.');
        }

        $recordedAt = $data['tanggal'] ?? date('Y-m-d H:i:s');
        $recordedBy = $data['dicatat_oleh'] ?? null;
        $description = trim((string) ($data['deskripsi'] ?? ''));

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $now = date('Y-m-d H:i:s');
            $code = TransactionCodeGenerator::generate('UEX', static fn (string $candidate): bool => UnexpectedExpense::exists(['kode_transaksi' => $candidate]));

            $expenseId = UnexpectedExpense::createAndReturnId([
                'tahun_ajaran_id' => $schoolYearId,
                'kode_transaksi' => $code,
                'tipe_pemohon' => $requesterType,
                'guru_id' => $teacherId,
                'siswa_id' => $studentId,
                'pemohon_nama' => $requesterName,
                'deskripsi' => $description === '' ? null : $description,
                'nominal' => $amount,
                'tanggal' => $recordedAt,
                'dicatat_oleh' => $recordedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($expenseId === null) {
                throw new RuntimeException('Gagal menyimpan data pengeluaran tak terduga.');
            }

        $transactionDescription = $description !== ''
            ? $description
            : sprintf('Pengeluaran tak terduga oleh %s', $requesterName);

            GeneralCashService::withdrawForUnexpectedExpense($schoolYearId, $amount, [
                'description' => $transactionDescription,
                'recorded_at' => $recordedAt,
                'user_id' => $recordedBy,
                'expense_id' => $expenseId,
            ]);

            CashflowService::record('keluar', 'tak_terduga', $amount, [
                'reference_id' => $expenseId,
                'reference_code' => $code,
                'description' => $transactionDescription,
                'recorded_at' => $recordedAt,
                'user_id' => $recordedBy,
                'school_year_id' => $schoolYearId,
            ]);

            if ($manageTransaction) {
                $connection->commit();
            }

            return $expenseId;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
