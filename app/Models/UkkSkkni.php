<?php

namespace App\Models;

use Core\Model;
use PDO;

class UkkSkkni extends Model
{
    protected static ?string $table = 'ukk_skkni';

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
SELECT s.*, j.nama AS jurusan_nama, ta.nama AS tahun_ajaran_nama, p.nama AS paket_nama
FROM ukk_skkni s
LEFT JOIN ukk_paket_ujian p ON p.id = s.paket_ujian_id
LEFT JOIN jurusan j ON j.id = s.jurusan_id
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
WHERE s.tahun_ajaran_id = ? AND s.jurusan_id IN ({$placeholders})
ORDER BY j.nama ASC, p.nama ASC, s.kode ASC
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

    public static function findForMajor(int $id, int $schoolYearId, array $majorIds): ?array
    {
        if ($id <= 0 || $schoolYearId <= 0 || empty($majorIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($majorIds), '?'));
        $sql = <<<SQL
SELECT s.*, j.nama AS jurusan_nama, p.nama AS paket_nama
FROM ukk_skkni s
LEFT JOIN ukk_paket_ujian p ON p.id = s.paket_ujian_id
LEFT JOIN jurusan j ON j.id = s.jurusan_id
WHERE s.id = ? AND s.tahun_ajaran_id = ? AND s.jurusan_id IN ({$placeholders})
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

    public static function countByPackage(int $packageId): int
    {
        if ($packageId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM ukk_skkni WHERE paket_ujian_id = :package';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':package', $packageId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byPackage(int $packageId): array
    {
        if ($packageId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM ukk_skkni WHERE paket_ujian_id = :package ORDER BY kode ASC';
        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':package', $packageId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
