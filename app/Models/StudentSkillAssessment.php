<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentSkillAssessment extends Model
{
    protected static ?string $table = 'penilaian_keterampilan_siswa';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignment(int $assignmentId, ?array $studentIds = null): array
    {
        if ($assignmentId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM penilaian_keterampilan_siswa WHERE guru_mata_pelajaran_id = :assignment';

        if (is_array($studentIds) && !empty($studentIds)) {
            $placeholders = [];
            foreach ($studentIds as $index => $studentId) {
                $placeholders[] = ':student_' . $index;
            }
            $sql .= ' AND siswa_id IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

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

    /**
     * @param array<int> $assignmentIds
     * @return array<int, int>
     */
    public static function countByAssignments(array $assignmentIds): array
    {
        $filteredIds = array_values(array_filter(array_unique(array_map(
            static fn ($id) => (int) $id,
            $assignmentIds
        )), static fn (int $id) => $id > 0));

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($filteredIds as $index => $assignmentId) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT guru_mata_pelajaran_id, COUNT(*) AS total FROM penilaian_keterampilan_siswa WHERE guru_mata_pelajaran_id IN (%s) GROUP BY guru_mata_pelajaran_id',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($filteredIds as $index => $assignmentId) {
            $statement->bindValue(':assignment_' . $index, $assignmentId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $counts = [];

        foreach ($rows as $row) {
            $assignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $counts[$assignmentId] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param array<int> $assignmentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignmentsForStudent(array $assignmentIds, int $studentId): array
    {
        $studentId = (int) $studentId;

        if ($studentId <= 0) {
            return [];
        }

        $filteredIds = array_values(array_filter(array_unique(array_map(
            static fn ($id) => (int) $id,
            $assignmentIds
        )), static fn (int $id) => $id > 0));

        if (empty($filteredIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($filteredIds as $index => $assignmentId) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM penilaian_keterampilan_siswa WHERE siswa_id = :student AND guru_mata_pelajaran_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);

        foreach ($filteredIds as $index => $assignmentId) {
            $statement->bindValue(':assignment_' . $index, $assignmentId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            $assignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);

            if ($assignmentId <= 0) {
                continue;
            }

            $results[$assignmentId] = $row;
        }

        return $results;
    }

    public static function upsert(
        int $assignmentId,
        int $studentId,
        ?float $nilaiAkhir,
        ?string $predikat,
        ?string $deskripsi
    ): bool {
        if ($assignmentId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $sql = <<<SQL
INSERT INTO penilaian_keterampilan_siswa (guru_mata_pelajaran_id, siswa_id, nilai_akhir, predikat, deskripsi, created_at, updated_at)
VALUES (:assignment, :student, :nilai_akhir, :predikat, :deskripsi, :created_at, :updated_at)
ON DUPLICATE KEY UPDATE
    nilai_akhir = VALUES(nilai_akhir),
    predikat = VALUES(predikat),
    deskripsi = VALUES(deskripsi),
    updated_at = VALUES(updated_at)
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);

        if ($nilaiAkhir === null) {
            $statement->bindValue(':nilai_akhir', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':nilai_akhir', $nilaiAkhir);
        }

        if ($predikat === null) {
            $statement->bindValue(':predikat', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':predikat', $predikat);
        }

        if ($deskripsi === null) {
            $statement->bindValue(':deskripsi', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':deskripsi', $deskripsi);
        }

        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }

    /**
     * @param array<int> $assignmentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function mapByClass(int $classId, array $assignmentIds): array
    {
        if ($classId <= 0) {
            return [];
        }

        $filteredAssignments = array_values(array_filter(array_unique(array_map(
            static fn ($id) => (int) $id,
            $assignmentIds
        )), static fn (int $id) => $id > 0));

        if (empty($filteredAssignments)) {
            return [];
        }

        $placeholders = [];
        foreach ($filteredAssignments as $index => $assignmentId) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT skill.* FROM penilaian_keterampilan_siswa skill JOIN siswa s ON s.id = skill.siswa_id WHERE s.kelas_id = :class_id AND skill.guru_mata_pelajaran_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);

        foreach ($filteredAssignments as $index => $assignmentId) {
            $statement->bindValue(':assignment_' . $index, $assignmentId, PDO::PARAM_INT);
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
            $assignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);

            if ($studentId <= 0 || $assignmentId <= 0) {
                continue;
            }

            $map[$studentId][$assignmentId] = $row;
        }

        return $map;
    }
}
