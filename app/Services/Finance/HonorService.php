<?php

namespace App\Services\Finance;

use App\Models\TeacherHonor;
use RuntimeException;

class HonorService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function createHonor(array $data): int
    {
        $teacherId = (int) ($data['guru_id'] ?? 0);
        $schoolYearId = (int) ($data['tahun_ajaran_id'] ?? 0);
        $period = (string) ($data['periode'] ?? '');
        $amount = (float) ($data['nominal_diterima'] ?? 0);

        if ($teacherId <= 0 || $schoolYearId <= 0 || $period === '' || $amount <= 0) {
            throw new RuntimeException('Data honor guru tidak valid.');
        }

        $now = date('Y-m-d H:i:s');

        $record = array_merge([
            'status' => $data['status'] ?? 'draft',
            'created_at' => $data['created_at'] ?? $now,
            'updated_at' => $data['updated_at'] ?? $now,
        ], $data);

        $id = TeacherHonor::createAndReturnId($record);

        if ($id === null) {
            throw new RuntimeException('Gagal menyimpan honor guru.');
        }

        return $id;
    }

    public static function markAsPaid(int $honorId, ?int $userId = null): void
    {
        if ($honorId <= 0) {
            throw new RuntimeException('ID honor tidak valid.');
        }

        TeacherHonor::updateById($honorId, [
            'status' => 'terbayar',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ]);
    }
}
