<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentAchievement extends Model
{
    protected static ?string $table = 'prestasi_siswa';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['pa.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'pa.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $sql = <<<SQL
SELECT
    pa.*,
    s.nama AS siswa_nama,
    s.nisn AS siswa_nisn,
    g.nama AS guru_nama,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama
FROM prestasi_siswa pa
LEFT JOIN siswa s ON s.id = pa.siswa_id
LEFT JOIN guru g ON g.id = pa.guru_id
LEFT JOIN tahun_ajaran ta ON ta.id = pa.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = pa.kelas_id
WHERE %s
ORDER BY s.nama ASC, pa.created_at DESC, pa.id DESC
SQL;

        $statement = static::connection()->prepare(sprintf($sql, implode(' AND ', $conditions)));

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}

