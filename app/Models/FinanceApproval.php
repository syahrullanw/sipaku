<?php

namespace App\Models;

use Core\Model;
use PDO;

class FinanceApproval extends Model
{
    protected static ?string $table = 'keuangan_approval';

    public static function findPending(string $entityType, int $entityId): ?array
    {
        if ($entityId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM keuangan_approval WHERE entity_type = :type AND entity_id = :entity AND status = :status LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':type', $entityType);
        $statement->bindValue(':entity', $entityId, PDO::PARAM_INT);
        $statement->bindValue(':status', 'menunggu');
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forApprover(int $approverId, ?string $status = null): array
    {
        if ($approverId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM keuangan_approval WHERE approver_id = :approver';
        if ($status !== null) {
            $sql .= ' AND status = :status';
        }
        $sql .= ' ORDER BY tanggal DESC, id DESC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':approver', $approverId, PDO::PARAM_INT);
        if ($status !== null) {
            $statement->bindValue(':status', $status);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
