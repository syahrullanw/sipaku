<?php

namespace App\Models;

use Core\Model;
use PDO;

class CocurricularSummary extends Model
{
    protected static ?string $table = 'kokurikuler_ringkasan';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    public static function byActivity(int $activityId, array $studentIds): array
    {
        if ($activityId <= 0 || empty($studentIds)) {
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
            'SELECT * FROM kokurikuler_ringkasan WHERE kegiatan_id = :activity AND siswa_id IN (%s)',
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);

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

    public static function upsert(int $activityId, int $studentId, ?string $deskripsi, ?string $tindakLanjut): bool
    {
        if ($activityId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $statement = static::connection()->prepare(
            'INSERT INTO kokurikuler_ringkasan (kegiatan_id, siswa_id, deskripsi_umum, tindak_lanjut, created_at, updated_at)
             VALUES (:activity, :student, :deskripsi, :tindak_lanjut, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                deskripsi_umum = VALUES(deskripsi_umum),
                tindak_lanjut = VALUES(tindak_lanjut),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':deskripsi', $deskripsi !== null && trim($deskripsi) !== '' ? trim($deskripsi) : null, $deskripsi !== null && trim($deskripsi) !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $statement->bindValue(':tindak_lanjut', $tindakLanjut !== null && trim($tindakLanjut) !== '' ? trim($tindakLanjut) : null, $tindakLanjut !== null && trim($tindakLanjut) !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }
}
