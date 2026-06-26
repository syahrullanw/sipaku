<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentKurmerSubjectSummary extends Model
{
    protected static ?string $table = 'penilaian_kurmer_mapel_siswa';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignmentAndClass(int $assignmentId, int $classId, array $studentIds): array
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
            'SELECT * FROM penilaian_kurmer_mapel_siswa WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class AND siswa_id IN (%s)',
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

            if ($studentId <= 0) {
                continue;
            }

            $map[$studentId] = $row;
        }

        return $map;
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

        $assignmentIds = array_values(array_filter(array_unique(array_map(static fn ($id): int => (int) $id, $assignmentIds)), static fn (int $id): bool => $id > 0));

        if (empty($assignmentIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($assignmentIds as $index => $assignmentId) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM penilaian_kurmer_mapel_siswa WHERE siswa_id = :student AND guru_mata_pelajaran_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);

        foreach ($assignmentIds as $index => $assignmentId) {
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
            $assignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }
            $map[$assignmentId] = $row;
        }

        return $map;
    }

    public static function upsert(
        int $assignmentId,
        int $classId,
        int $studentId,
        ?string $capaianAkhir,
        ?string $deskripsi,
        ?string $tindakLanjut,
        ?float $nilaiOpsional,
        ?array $sumberTp = null
    ): bool {
        if ($assignmentId <= 0 || $classId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $statement = static::connection()->prepare(
            'INSERT INTO penilaian_kurmer_mapel_siswa (guru_mata_pelajaran_id, kelas_id, siswa_id, capaian_akhir_enum, deskripsi_umum, tindak_lanjut, nilai_opsional, sumber_tp, created_at, updated_at)
             VALUES (:assignment, :class, :student, :capaian, :deskripsi, :tindak_lanjut, :nilai_opsional, :sumber_tp, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                capaian_akhir_enum = VALUES(capaian_akhir_enum),
                deskripsi_umum = VALUES(deskripsi_umum),
                tindak_lanjut = VALUES(tindak_lanjut),
                nilai_opsional = VALUES(nilai_opsional),
                sumber_tp = VALUES(sumber_tp),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':capaian', $capaianAkhir);

        $statement->bindValue(':deskripsi', $deskripsi !== null && trim($deskripsi) !== '' ? $deskripsi : null, $deskripsi !== null && trim($deskripsi) !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $statement->bindValue(':tindak_lanjut', $tindakLanjut !== null && trim($tindakLanjut) !== '' ? $tindakLanjut : null, $tindakLanjut !== null && trim($tindakLanjut) !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        if ($nilaiOpsional === null) {
            $statement->bindValue(':nilai_opsional', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':nilai_opsional', $nilaiOpsional);
        }

        if ($sumberTp === null) {
            $statement->bindValue(':sumber_tp', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':sumber_tp', json_encode($sumberTp));
        }

        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }
}
