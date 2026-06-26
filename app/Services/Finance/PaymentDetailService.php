<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Core\Database;
use PDO;

class PaymentDetailService
{
    /**
     * Ambil detail pembayaran lengkap beserta relasi siswa/tagihan.
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            "SELECT
                p.*,
                ti.siswa_id,
                s.nama AS siswa_nama,
                s.nipd AS siswa_nis,
                s.status AS siswa_status,
                s.status_dapodik AS siswa_status_dapodik,
                t.judul AS tagihan_judul,
                t.kode AS tagihan_kode,
                ti.tagihan_id,
                ti.nominal AS tagihan_total,
                ti.sisa_nominal,
                kt.nama AS kategori_nama,
                u.name AS diverifikasi_oleh_nama
             FROM pembayaran p
             JOIN tagihan_item ti ON ti.id = p.tagihan_item_id
             JOIN tagihan t ON t.id = ti.tagihan_id
             JOIN siswa s ON s.id = ti.siswa_id
             JOIN kategori_tagihan kt ON kt.id = t.kategori_id
             LEFT JOIN users u ON u.id = p.diverifikasi_oleh
             WHERE p.id = :id
             LIMIT 1"
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $paymentId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
