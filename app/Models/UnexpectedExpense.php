<?php

namespace App\Models;

use Core\Model;
use PDO;

class UnexpectedExpense extends Model
{
    protected static ?string $table = 'pengeluaran_tak_terduga';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $schoolYearId, int $limit = 20, ?int $teacherId = null): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        $sql = <<<'SQL'
SELECT pet.*, g.nama AS guru_nama, s.nama AS siswa_nama, s.status AS siswa_status
FROM pengeluaran_tak_terduga pet
LEFT JOIN guru g ON g.id = pet.guru_id
LEFT JOIN siswa s ON s.id = pet.siswa_id
WHERE pet.tahun_ajaran_id = :year
SQL;

        if ($teacherId !== null && $teacherId > 0) {
            $sql .= ' AND pet.guru_id = :teacher';
        }

        $sql .= ' ORDER BY pet.tanggal DESC, pet.id DESC LIMIT :limit';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);

        if ($teacherId !== null && $teacherId > 0) {
            $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function historyForTeacher(int $teacherId, int $limit = 10): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        $sql = <<<'SQL'
SELECT pet.*, ta.nama AS tahun_ajaran_nama, s.nama AS siswa_nama
FROM pengeluaran_tak_terduga pet
LEFT JOIN tahun_ajaran ta ON ta.id = pet.tahun_ajaran_id
LEFT JOIN siswa s ON s.id = pet.siswa_id
WHERE pet.guru_id = :teacher
ORDER BY pet.tanggal DESC, pet.id DESC
LIMIT :limit
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function historyForStudent(int $studentId, int $limit = 10): array
    {
        if ($studentId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 100));

        $sql = <<<'SQL'
SELECT pet.*, ta.nama AS tahun_ajaran_nama, g.nama AS guru_nama
FROM pengeluaran_tak_terduga pet
LEFT JOIN tahun_ajaran ta ON ta.id = pet.tahun_ajaran_id
LEFT JOIN guru g ON g.id = pet.guru_id
WHERE pet.siswa_id = :student
ORDER BY pet.tanggal DESC, pet.id DESC
LIMIT :limit
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
