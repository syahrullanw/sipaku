<?php

namespace App\Models;

use Core\Model;
use PDO;

class Payment extends Model
{
    protected static ?string $table = 'pembayaran';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pendingVerification(): array
    {
        $sql = <<<SQL
SELECT
    p.*,
    ti.siswa_id,
    s.nama AS siswa_nama,
    s.status AS siswa_status,
    s.status_dapodik AS siswa_status_dapodik,
    t.judul AS tagihan_judul
FROM pembayaran p
JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
JOIN tagihan t ON t.id = ti.tagihan_id
JOIN siswa s ON s.id = ti.siswa_id
WHERE p.status = 'menunggu_verifikasi'
ORDER BY p.tanggal_bayar ASC
SQL;

        $statement = static::connection()->query($sql);

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function findByCode(string $code): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM pembayaran WHERE kode_transaksi = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
