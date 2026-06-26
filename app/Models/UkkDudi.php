<?php

namespace App\Models;

use Core\Model;
use PDO;

class UkkDudi extends Model
{
    protected static ?string $table = 'ukk_dudi';

    /**
     * @param array<int, int> $majorIds
     * @return array<int, array<string, mixed>>
     */
    public static function byYearAndMajors(int $schoolYearId, array $majorIds): array
    {
        if ($schoolYearId <= 0 || empty($majorIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($majorIds), '?'));
        $sql = <<<SQL
SELECT d.*, j.nama AS jurusan_nama, ta.nama AS tahun_ajaran_nama
FROM ukk_dudi d
LEFT JOIN jurusan j ON j.id = d.jurusan_id
LEFT JOIN tahun_ajaran ta ON ta.id = d.tahun_ajaran_id
WHERE d.tahun_ajaran_id = ? AND d.jurusan_id IN ({$placeholders})
ORDER BY j.nama ASC, d.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $bindIndex = 1;
        $statement->bindValue($bindIndex++, $schoolYearId, PDO::PARAM_INT);
        foreach ($majorIds as $majorId) {
            $statement->bindValue($bindIndex++, (int) $majorId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int, int> $majorIds
     */
    public static function findForMajor(int $id, int $schoolYearId, array $majorIds): ?array
    {
        if ($id <= 0 || $schoolYearId <= 0 || empty($majorIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($majorIds), '?'));
        $sql = <<<SQL
SELECT d.*, j.nama AS jurusan_nama
FROM ukk_dudi d
LEFT JOIN jurusan j ON j.id = d.jurusan_id
WHERE d.id = ? AND d.tahun_ajaran_id = ? AND d.jurusan_id IN ({$placeholders})
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $bindIndex = 1;
        $statement->bindValue($bindIndex++, $id, PDO::PARAM_INT);
        $statement->bindValue($bindIndex++, $schoolYearId, PDO::PARAM_INT);

        foreach ($majorIds as $majorId) {
            $statement->bindValue($bindIndex++, (int) $majorId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
