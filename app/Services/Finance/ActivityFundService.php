<?php

namespace App\Services\Finance;

use App\Models\ActivityFund;
use App\Models\ActivityFundRealization;
use RuntimeException;

class ActivityFundService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function createRequest(array $data): int
    {
        $teacherId = (int) ($data['guru_id'] ?? 0);
        $schoolYearId = (int) ($data['tahun_ajaran_id'] ?? 0);
        $estimate = (float) ($data['estimasi_biaya'] ?? 0);

        if ($teacherId <= 0 || $schoolYearId <= 0 || $estimate <= 0) {
            throw new RuntimeException('Data pengajuan dana kegiatan tidak valid.');
        }

        $now = date('Y-m-d H:i:s');

        $record = array_merge([
            'kode' => TransactionCodeGenerator::generate('ACT', static fn (string $candidate): bool => ActivityFund::exists(['kode' => $candidate])),
            'status' => $data['status'] ?? 'diajukan',
            'tanggal_pengajuan' => $data['tanggal_pengajuan'] ?? $now,
            'created_at' => $data['created_at'] ?? $now,
            'updated_at' => $data['updated_at'] ?? $now,
        ], $data);

        $id = ActivityFund::createAndReturnId($record);

        if ($id === null) {
            throw new RuntimeException('Gagal menyimpan pengajuan dana kegiatan.');
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function recordRealization(array $data): int
    {
        $activityId = (int) ($data['dana_kegiatan_id'] ?? 0);
        $amount = (float) ($data['nominal'] ?? 0);

        if ($activityId <= 0 || $amount <= 0) {
            throw new RuntimeException('Data realisasi dana kegiatan tidak valid.');
        }

        $activity = ActivityFund::find($activityId);

        if ($activity === null) {
            throw new RuntimeException('Pengajuan dana kegiatan tidak ditemukan.');
        }

        $now = date('Y-m-d H:i:s');

        $record = array_merge([
            'kode_transaksi' => $data['kode_transaksi'] ?? TransactionCodeGenerator::generate('ACTR', static fn (string $candidate): bool => ActivityFundRealization::exists(['kode_transaksi' => $candidate])),
            'tanggal' => $data['tanggal'] ?? $now,
            'created_at' => $data['created_at'] ?? $now,
            'updated_at' => $data['updated_at'] ?? $now,
        ], $data);

        $id = ActivityFundRealization::createAndReturnId($record);

        if ($id === null) {
            throw new RuntimeException('Gagal menyimpan realisasi dana kegiatan.');
        }

        return $id;
    }
}
