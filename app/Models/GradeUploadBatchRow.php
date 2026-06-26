<?php

namespace App\Models;

use Core\Model;
use PDO;

class GradeUploadBatchRow extends Model
{
    protected static ?string $table = 'batch_upload_nilai_rows';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byBatch(int $batchId, ?bool $onlyInvalid = null): array
    {
        if ($batchId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM batch_upload_nilai_rows WHERE batch_upload_nilai_id = :batch_id';
        if ($onlyInvalid === true) {
            $sql .= ' AND is_valid = 0';
        } elseif ($onlyInvalid === false) {
            $sql .= ' AND is_valid = 1';
        }
        $sql .= ' ORDER BY row_no ASC, id ASC';

        $statement = static::connection()->prepare($sql);
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