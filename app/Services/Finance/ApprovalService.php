<?php

namespace App\Services\Finance;

use App\Models\FinanceApproval;
use RuntimeException;

class ApprovalService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function request(array $data): int
    {
        $entityType = $data['entity_type'] ?? null;
        $entityId = (int) ($data['entity_id'] ?? 0);
        $approverId = (int) ($data['approver_id'] ?? 0);

        if ($entityType === null || $entityId <= 0 || $approverId <= 0) {
            throw new RuntimeException('Data approval tidak valid.');
        }

        $existing = FinanceApproval::findPending($entityType, $entityId);
        if ($existing !== null) {
            return (int) ($existing['id'] ?? 0);
        }

        $now = date('Y-m-d H:i:s');
        $record = array_merge([
            'status' => 'menunggu',
            'tanggal' => $data['tanggal'] ?? $now,
            'created_at' => $data['created_at'] ?? $now,
            'updated_at' => $data['updated_at'] ?? $now,
        ], $data);

        $id = FinanceApproval::createAndReturnId($record);

        if ($id === null) {
            throw new RuntimeException('Gagal menyimpan permintaan approval.');
        }

        return $id;
    }

    public static function resolve(int $approvalId, string $status, ?string $note = null): void
    {
        if ($approvalId <= 0) {
            throw new RuntimeException('ID approval tidak valid.');
        }

        if (!in_array($status, ['disetujui', 'ditolak'], true)) {
            throw new RuntimeException('Status approval tidak dikenal.');
        }

        FinanceApproval::updateById($approvalId, [
            'status' => $status,
            'catatan' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
