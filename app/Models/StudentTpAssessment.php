<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentTpAssessment extends Model
{
    protected static ?string $table = 'penilaian_tp_siswa';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function mapByAssignmentAndClass(int $assignmentId, int $classId, array $studentIds): array
    {
        if ($assignmentId <= 0 || $classId <= 0 || empty($studentIds)) {
            return [];
        }

        $studentIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $studentIds), static fn (int $id): bool => $id > 0));

        if (empty($studentIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($studentIds as $index => $id) {
            $placeholders[] = ':student_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM penilaian_tp_siswa WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class AND siswa_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);

        foreach ($studentIds as $index => $id) {
            $statement->bindValue(':student_' . $index, $id, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            $tpId = (int) ($row['tp_id'] ?? 0);

            if ($studentId <= 0 || $tpId <= 0) {
                continue;
            }

            $map[$studentId][$tpId] = $row;
        }

        return $map;
    }

    public static function upsert(
        int $assignmentId,
        int $classId,
        int $tpId,
        int $studentId,
        string $capaian,
        ?float $nilaiOpsional,
        ?string $catatan
    ): bool {
        if ($assignmentId <= 0 || $classId <= 0 || $tpId <= 0 || $studentId <= 0 || $capaian === '') {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $statement = static::connection()->prepare(
            'INSERT INTO penilaian_tp_siswa (guru_mata_pelajaran_id, kelas_id, tp_id, siswa_id, capaian_enum, nilai_opsional, catatan, created_at, updated_at)
             VALUES (:assignment, :class, :tp, :student, :capaian, :nilai_opsional, :catatan, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                capaian_enum = VALUES(capaian_enum),
                nilai_opsional = VALUES(nilai_opsional),
                catatan = VALUES(catatan),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        $statement->bindValue(':tp', $tpId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':capaian', $capaian);

        if ($nilaiOpsional === null) {
            $statement->bindValue(':nilai_opsional', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':nilai_opsional', $nilaiOpsional);
        }

        if ($catatan === null || trim($catatan) === '') {
            $statement->bindValue(':catatan', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':catatan', $catatan);
        }

        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }
}
