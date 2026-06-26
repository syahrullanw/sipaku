<?php

namespace App\Services\Finance;

use App\Models\TeacherLoan;
use App\Models\TeacherLoanInstallment;
use App\Support\FinanceCache;
use Core\Database;
use RuntimeException;

class LoanService
{
    /**
     * @param array<string, mixed> $loanData
     * @param array<int, array<string, mixed>> $installments
     */
    public static function createLoan(array $loanData, array $installments = []): int
    {
        $teacherId = (int) ($loanData['guru_id'] ?? 0);
        $schoolYearId = (int) ($loanData['tahun_ajaran_id'] ?? 0);
        $amount = (float) ($loanData['nominal_diminta'] ?? 0);

        if ($teacherId <= 0 || $schoolYearId <= 0 || $amount <= 0) {
            throw new RuntimeException('Data kasbon tidak valid.');
        }

        $connection = Database::connection();
        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $now = date('Y-m-d H:i:s');
            $record = array_merge([
                'kode' => TransactionCodeGenerator::generate('LOAN', static fn (string $candidate): bool => TeacherLoan::exists(['kode' => $candidate])),
                'tanggal_pengajuan' => $loanData['tanggal_pengajuan'] ?? date('Y-m-d'),
                'nominal_diminta' => $amount,
                'saldo_terhutang' => $loanData['saldo_terhutang'] ?? $amount,
                'status' => $loanData['status'] ?? 'diajukan',
                'created_at' => $loanData['created_at'] ?? $now,
                'updated_at' => $loanData['updated_at'] ?? $now,
            ], $loanData);

            $loanId = TeacherLoan::createAndReturnId($record);

            if ($loanId === null) {
                throw new RuntimeException('Gagal menyimpan data kasbon.');
            }

            foreach ($installments as $item) {
                $dueDate = $item['jatuh_tempo'] ?? null;
                $nominal = (float) ($item['nominal'] ?? 0);

                if ($dueDate === null || $nominal <= 0) {
                    throw new RuntimeException('Data cicilan kasbon tidak valid.');
                }

                $payload = [
                    'kasbon_id' => $loanId,
                    'jatuh_tempo' => $dueDate,
                    'nominal' => $nominal,
                    'nominal_terbayar' => $item['nominal_terbayar'] ?? 0.0,
                    'status' => $item['status'] ?? 'menunggu',
                    'created_at' => $item['created_at'] ?? $now,
                    'updated_at' => $item['updated_at'] ?? $now,
                    'dicatat_oleh' => $item['dicatat_oleh'] ?? null,
                ];

                if (!TeacherLoanInstallment::create($payload)) {
                    throw new RuntimeException('Gagal menyimpan cicilan kasbon.');
                }
            }

            FinanceCache::forget('kepsek_dashboard_loan_' . $schoolYearId);
            FinanceCache::forget('bendahara_dashboard_stats_' . $schoolYearId);

            if ($manageTransaction) {
                $connection->commit();
            }

            return $loanId;
        } catch (\Throwable $exception) {
            if ($manageTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function recordInstallment(int $installmentId, float $amount, array $options = []): void
    {
        if ($installmentId <= 0) {
            throw new RuntimeException('ID cicilan tidak valid.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Nominal cicilan harus lebih dari nol.');
        }

        $connection = Database::connection();

        $installmentStatement = $connection->prepare(
            'SELECT * FROM kasbon_cicilan WHERE id = :id LIMIT 1'
        );
        $loanStatement = $connection->prepare(
            'SELECT id, saldo_terhutang, tahun_ajaran_id FROM kasbon_guru WHERE id = :id LIMIT 1'
        );

        if ($installmentStatement === false || $loanStatement === false) {
            throw new RuntimeException('Gagal mempersiapkan pembacaan data kasbon.');
        }

        $installmentStatement->bindValue(':id', $installmentId, PDO::PARAM_INT);
        $installmentStatement->execute();

        $installment = $installmentStatement->fetch(PDO::FETCH_ASSOC);

        if ($installment === false) {
            throw new RuntimeException('Cicilan kasbon tidak ditemukan.');
        }

        $loanId = (int) ($installment['kasbon_id'] ?? 0);
        $loanStatement->bindValue(':id', $loanId, PDO::PARAM_INT);
        $loanStatement->execute();
        $loan = $loanStatement->fetch(PDO::FETCH_ASSOC);

        if ($loan === false) {
            throw new RuntimeException('Kasbon guru tidak ditemukan.');
        }

        $remainingInstallment = max(0.0, (float) ($installment['nominal'] ?? 0) - (float) ($installment['nominal_terbayar'] ?? 0));
        if ($amount > $remainingInstallment) {
            throw new RuntimeException('Nominal pembayaran melebihi jumlah cicilan tersisa.');
        }

        $manageTransaction = !$connection->inTransaction();

        try {
            if ($manageTransaction) {
                $connection->beginTransaction();
            }

            $newPaid = (float) ($installment['nominal_terbayar'] ?? 0) + $amount;
            $installmentStatus = $newPaid >= (float) ($installment['nominal'] ?? 0)
                ? 'terbayar'
                : 'menunggu';

            TeacherLoanInstallment::updateById($installmentId, [
                'nominal_terbayar' => $newPaid,
                'status' => $installmentStatus,
                'tanggal_bayar_terakhir' => $options['tanggal_bayar'] ?? date('Y-m-d H:i:s'),
                'dicatat_oleh' => $options['dicatat_oleh'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $newOutstanding = max(0.0, (float) ($loan['saldo_terhutang'] ?? 0) - $amount);

            $loanStatus = $newOutstanding <= 0.0 ? 'lunas' : 'disetujui';

            TeacherLoan::updateById($loanId, [
                'saldo_terhutang' => $newOutstanding,
                'status' => $loanStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $options['dicatat_oleh'] ?? null,
            ]);

            FinanceCache::forget('kepsek_dashboard_loan_' . ((int) ($loan['tahun_ajaran_id'] ?? 0)));
            FinanceCache::forget('bendahara_dashboard_stats_' . ((int) ($loan['tahun_ajaran_id'] ?? 0)));

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
}
