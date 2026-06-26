<?php

namespace App\Models;

use Core\Model;
use PDO;

class SupplyPurchase extends Model
{
    protected static ?string $table = 'pembelian_perlengkapan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forSchoolYear(int $yearId, ?int $classId = null, string $keyword = '', int $limit = 80): array
    {
        if ($yearId <= 0) {
            return [];
        }

        $connection = static::connection();
        $conditions = ['p.tahun_ajaran_id = :year'];
        $params = [':year' => $yearId];

        if ($classId !== null && $classId > 0) {
            $conditions[] = 's.kelas_id = :class';
            $params[':class'] = $classId;
        }

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $conditions[] = '(s.nama LIKE :keyword OR s.nipd LIKE :keyword OR s.nisn LIKE :keyword OR t.judul LIKE :keyword OR p.item_label LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = <<<SQL
SELECT
    p.*,
    s.nama AS siswa_nama,
    s.status AS siswa_status,
    s.status_dapodik AS siswa_status_dapodik,
    s.nipd,
    s.nisn,
    k.tingkat,
    k.nama AS kelas_nama,
    t.kode AS tagihan_kode,
    t.judul AS tagihan_judul,
    ti.id AS tagihan_item_id,
    COALESCE(ti.sisa_nominal, p.sisa_nominal) AS sisa_nominal,
    COALESCE(ti.status, p.status) AS tagihan_status
FROM pembelian_perlengkapan p
JOIN siswa s ON s.id = p.siswa_id
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN tagihan t ON t.id = p.tagihan_id
LEFT JOIN tagihan_item ti ON ti.tagihan_id = t.id AND ti.siswa_id = p.siswa_id
%s
ORDER BY p.created_at DESC
LIMIT :limit
SQL;

        $statement = $connection->prepare(sprintf($sql, $whereClause));

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $type = PDO::PARAM_STR;
            if ($placeholder === ':year' || $placeholder === ':class') {
                $type = PDO::PARAM_INT;
            }
            $statement->bindValue($placeholder, $value, $type);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
