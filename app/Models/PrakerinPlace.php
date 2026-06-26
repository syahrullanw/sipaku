<?php

namespace App\Models;

use Core\Model;
use PDO;

class PrakerinPlace extends Model
{
    protected static ?string $table = 'tempat_prakerin';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        $statement = static::connection()->query(
            'SELECT tp.*, g.nama AS pembina_nama
            FROM tempat_prakerin tp
            LEFT JOIN guru g ON g.id = tp.pembina_guru_id
            ORDER BY tp.nama ASC'
        );

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::allOrdered() as $row) {
            $options[$row['id']] = $row['nama'];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function supervisedByTeacher(int $teacherId, int $schoolYearId): array
    {
        if ($teacherId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    tp.*,
    COUNT(DISTINCT pp.siswa_id) AS total_siswa
FROM tempat_prakerin tp
LEFT JOIN penempatan_prakerin pp
    ON pp.tempat_prakerin_id = tp.id
    AND pp.tahun_ajaran_id = :school_year_id
WHERE tp.pembina_guru_id = :teacher_id
GROUP BY tp.id
ORDER BY tp.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function teacherHasActivePlacements(int $teacherId, int $schoolYearId): bool
    {
        if ($teacherId <= 0 || $schoolYearId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            <<<SQL
SELECT 1
FROM tempat_prakerin tp
JOIN penempatan_prakerin pp
    ON pp.tempat_prakerin_id = tp.id
WHERE tp.pembina_guru_id = :teacher_id
  AND pp.tahun_ajaran_id = :school_year_id
LIMIT 1
SQL
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return false;
        }

        return $statement->fetchColumn() !== false;
    }
}
