<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherSalaryRecord extends Model
{
    protected static ?string $table = 'teacher_salary_records';

    public static function findByTeacherAndPeriod(int $teacherId, int $schoolYearId, string $period): ?array
    {
        if ($teacherId <= 0 || $schoolYearId <= 0 || $period === '') {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM teacher_salary_records WHERE guru_id = :teacher AND tahun_ajaran_id = :year AND periode = :period LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':period', $period);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByPeriod(int $schoolYearId, string $period): array
    {
        if ($schoolYearId <= 0 || $period === '') {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT tsr.*, g.nama AS guru_nama, g.nip AS guru_nip
             FROM teacher_salary_records tsr
             JOIN guru g ON g.id = tsr.guru_id
             WHERE tsr.tahun_ajaran_id = :year AND tsr.periode = :period
             ORDER BY g.nama ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':period', $period);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, string>
     */
    public static function periods(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT DISTINCT periode FROM teacher_salary_records WHERE tahun_ajaran_id = :year ORDER BY periode DESC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($rows === false) {
            return [];
        }

        return array_map(static fn ($value) => (string) $value, $rows);
    }
}
