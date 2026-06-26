<?php

namespace App\Models;

use Core\Model;
use PDO;

class P5StudentSummary extends Model
{
    protected static ?string $table = 'p5_penilaian_ringkasan';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byProject(int $projectId, array $studentIds): array
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0 || empty($studentIds)) {
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
            'SELECT * FROM p5_penilaian_ringkasan WHERE projek_id = :project AND siswa_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':project', $projectId, PDO::PARAM_INT);
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

    public static function upsert(
        int $projectId,
        int $studentId,
        ?string $capaian,
        ?string $deskripsi,
        ?string $tindakLanjut,
        ?float $nilaiOpsional
    ): bool {
        if ($projectId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $statement = static::connection()->prepare(
            'INSERT INTO p5_penilaian_ringkasan (projek_id, siswa_id, capaian_akhir_enum, deskripsi_umum, tindak_lanjut, nilai_opsional, created_at, updated_at)
             VALUES (:project, :student, :capaian, :deskripsi, :tindak, :nilai, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                capaian_akhir_enum = VALUES(capaian_akhir_enum),
                deskripsi_umum = VALUES(deskripsi_umum),
                tindak_lanjut = VALUES(tindak_lanjut),
                nilai_opsional = VALUES(nilai_opsional),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':project', $projectId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':capaian', $capaian);
        $statement->bindValue(':deskripsi', $deskripsi !== null && $deskripsi !== '' ? $deskripsi : null, $deskripsi !== null && $deskripsi !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $statement->bindValue(':tindak', $tindakLanjut !== null && $tindakLanjut !== '' ? $tindakLanjut : null, $tindakLanjut !== null && $tindakLanjut !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        if ($nilaiOpsional === null) {
            $statement->bindValue(':nilai', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':nilai', $nilaiOpsional);
        }
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }
}
