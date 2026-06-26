<?php

namespace App\Models;

use Core\Model;
use PDO;

class GradeUploadBatchAudit extends Model
{
    protected static ?string $table = 'batch_upload_nilai_audit';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byBatch(int $batchId): array
    {
        if ($batchId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM batch_upload_nilai_audit WHERE batch_upload_nilai_id = :batch_id ORDER BY id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':batch_id', $batchId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}