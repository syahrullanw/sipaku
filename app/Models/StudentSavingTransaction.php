<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentSavingTransaction extends Model
{
    protected static ?string $table = 'tabungan_transaksi';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function historyForSaving(int $savingId): array
    {
        if ($savingId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM tabungan_transaksi WHERE tabungan_id = :id ORDER BY tanggal DESC, id DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':id', $savingId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
