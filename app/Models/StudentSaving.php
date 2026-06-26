<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentSaving extends Model
{
    protected static ?string $table = 'tabungan_siswa';

    public static function findByStudentAndYear(int $studentId, int $schoolYearId): ?array
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM tabungan_siswa WHERE siswa_id = :student AND tahun_ajaran_id = :year LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeByYear(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            "SELECT * FROM tabungan_siswa WHERE tahun_ajaran_id = :year AND status = 'aktif'"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
