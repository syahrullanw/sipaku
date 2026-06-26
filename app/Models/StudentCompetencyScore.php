<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentCompetencyScore extends Model
{
    protected static ?string $table = 'penilaian_kd_siswa';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignmentAndType(int $assignmentId, string $type, ?array $studentIds = null): array
    {
        if ($assignmentId <= 0) {
            return [];
        }

        $connection = static::connection();
        $sql = <<<SQL
SELECT pks.*, mkd.jenis, mkd.kode
FROM penilaian_kd_siswa pks
JOIN mata_pelajaran_kd mkd ON mkd.id = pks.kd_id
WHERE pks.guru_mata_pelajaran_id = :assignment
  AND mkd.jenis = :type
%s
ORDER BY mkd.kode ASC
SQL;

        $filter = '';
        if (is_array($studentIds) && !empty($studentIds)) {
            $placeholders = [];
            foreach ($studentIds as $index => $studentId) {
                $placeholder = ':student_' . $index;
                $placeholders[] = $placeholder;
            }
            $filter = 'AND pks.siswa_id IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = $connection->prepare(sprintf($sql, $filter));

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':type', $type, PDO::PARAM_STR);

        if (is_array($studentIds) && !empty($studentIds)) {
            foreach ($studentIds as $index => $studentId) {
                $statement->bindValue(':student_' . $index, $studentId, PDO::PARAM_INT);
            }
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function upsert(int $assignmentId, int $kdId, int $studentId, ?float $score, ?string $description): bool
    {
        if ($assignmentId <= 0 || $kdId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $sql = <<<SQL
INSERT INTO penilaian_kd_siswa (guru_mata_pelajaran_id, kd_id, siswa_id, nilai, deskripsi, created_at, updated_at)
VALUES (:assignment, :kd, :student, :nilai, :deskripsi, :created_at, :updated_at)
ON DUPLICATE KEY UPDATE
    nilai = VALUES(nilai),
    deskripsi = VALUES(deskripsi),
    updated_at = VALUES(updated_at)
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':kd', $kdId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);

        if ($score === null) {
            $statement->bindValue(':nilai', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':nilai', $score);
        }

        if ($description === null) {
            $statement->bindValue(':deskripsi', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':deskripsi', $description, PDO::PARAM_STR);
        }

        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }

    public static function deleteByAssignment(int $assignmentId, int $kdId): bool
    {
        if ($assignmentId <= 0 || $kdId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            'DELETE FROM penilaian_kd_siswa WHERE guru_mata_pelajaran_id = :assignment AND kd_id = :kd'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':kd', $kdId, PDO::PARAM_INT);

        return $statement->execute();
    }
}
