<?php

namespace App\Models;

use Core\Model;
use PDO;

class CocurricularAssessment extends Model
{
    protected static ?string $table = 'kokurikuler_penilaian';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function mapByActivity(int $activityId, array $studentIds): array
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
            'SELECT * FROM kokurikuler_penilaian WHERE kegiatan_id = :activity AND siswa_id IN (%s)',
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
            $elementId = (int) ($row['elemen_id'] ?? 0);

            if ($studentId <= 0 || $elementId <= 0) {
                continue;
            }

            $map[$studentId][$elementId] = $row;
        }

        return $map;
    }

    public static function upsert(int $activityId, int $elementId, int $studentId, string $capaian, ?string $catatan): bool
    {
        if ($activityId <= 0 || $elementId <= 0 || $studentId <= 0 || $capaian === '') {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $statement = static::connection()->prepare(
            'INSERT INTO kokurikuler_penilaian (kegiatan_id, elemen_id, siswa_id, capaian_enum, catatan, created_at, updated_at)
             VALUES (:activity, :element, :student, :capaian, :catatan, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                capaian_enum = VALUES(capaian_enum),
                catatan = VALUES(catatan),
                updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);
        $statement->bindValue(':element', $elementId, PDO::PARAM_INT);
        $statement->bindValue(':student', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':capaian', $capaian);

        if ($catatan === null || trim($catatan) === '') {
            $statement->bindValue(':catatan', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':catatan', trim($catatan));
        }

        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }

    public static function deleteByElement(int $activityId, int $elementId): void
    {
        if ($activityId <= 0 || $elementId <= 0) {
            return;
        }

        $statement = static::connection()->prepare(
            'DELETE FROM kokurikuler_penilaian WHERE kegiatan_id = :activity AND elemen_id = :element'
        );

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':activity', $activityId, PDO::PARAM_INT);
        $statement->bindValue(':element', $elementId, PDO::PARAM_INT);
        $statement->execute();
    }
}
