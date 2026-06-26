<?php

namespace App\Models;

use Core\Model;
use PDO;

class CocurricularActivity extends Model
{
    protected static ?string $table = 'kokurikuler_kegiatan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null, ?int $semester = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['kegiatan.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'kegiatan.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        if ($semester !== null && $semester > 0) {
            $conditions[] = 'kegiatan.semester = :semester';
            $params[':semester'] = $semester;
        }

        $sql = <<<SQL
SELECT
    kegiatan.*,
    g.nama AS guru_koordinator_nama
FROM kokurikuler_kegiatan kegiatan
LEFT JOIN guru g ON g.id = kegiatan.guru_koordinator_id
WHERE %s
ORDER BY kegiatan.created_at DESC, kegiatan.id DESC
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

    public static function findWithRelations(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    kegiatan.*,
    g.nama AS guru_koordinator_nama,
    k.tahun_ajaran_id,
    k.kurikulum
FROM kokurikuler_kegiatan kegiatan
JOIN kelas k ON k.id = kegiatan.kelas_id
LEFT JOIN guru g ON g.id = kegiatan.guru_koordinator_id
WHERE kegiatan.id = :id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function countByClassSemester(int $classId, int $schoolYearId, int $semester): int
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $semester <= 0) {
            return 0;
        }

        $statement = static::connection()->prepare(
            'SELECT COUNT(*) AS total FROM kokurikuler_kegiatan WHERE kelas_id = :class_id AND tahun_ajaran_id = :year_id AND semester = :semester'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semester, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? 0 : (int) ($result['total'] ?? 0);
    }
}
