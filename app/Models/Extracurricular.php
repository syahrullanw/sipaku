<?php

namespace App\Models;

use Core\Model;
use PDO;

class Extracurricular extends Model
{
    protected static ?string $table = 'ekstrakurikuler';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(?int $schoolYearId = null): array
    {
        if ($schoolYearId === null) {
            $activeYear = SchoolYear::active();
            $schoolYearId = $activeYear !== null ? (int) $activeYear['id'] : null;
        }

        if ($schoolYearId === null) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT e.*, g.nama AS pembina_nama, ta.nama AS tahun_ajaran_nama
            FROM ekstrakurikuler e
            JOIN tahun_ajaran ta ON ta.id = e.tahun_ajaran_id
            LEFT JOIN guru g ON g.id = e.pembina_guru_id
            WHERE e.tahun_ajaran_id = :tahun_ajaran_id
            ORDER BY e.nama ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function options(?int $schoolYearId = null): array
    {
        $options = [];

        foreach (static::allOrdered($schoolYearId) as $row) {
            $options[$row['id']] = $row['nama'];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byMentor(int $teacherId, int $schoolYearId): array
    {
        if ($teacherId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    e.*,
    COUNT(se.id) AS total_peserta
FROM ekstrakurikuler e
LEFT JOIN siswa_ekstrakurikuler se
    ON se.ekstrakurikuler_id = e.id
    AND se.tahun_ajaran_id = e.tahun_ajaran_id
WHERE e.pembina_guru_id = :teacher_id
  AND e.tahun_ajaran_id = :school_year_id
GROUP BY e.id
ORDER BY e.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function teacherHasMentorship(int $teacherId, ?int $schoolYearId = null): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        if ($schoolYearId === null) {
            $active = SchoolYear::active();
            $schoolYearId = $active !== null ? (int) ($active['id'] ?? 0) : 0;
        }

        if ($schoolYearId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            'SELECT COUNT(*) FROM ekstrakurikuler WHERE pembina_guru_id = :teacher_id AND tahun_ajaran_id = :school_year_id'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }
}
