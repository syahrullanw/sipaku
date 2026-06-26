<?php

namespace App\Models;

use Core\Model;
use PDO;

class P5StudentAssessment extends Model
{
    protected static ?string $table = 'p5_penilaian_siswa';

    /**
     * @param array<int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function mapByProject(int $projectId, array $studentIds): array
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
            'SELECT * FROM p5_penilaian_siswa WHERE projek_id = :project AND siswa_id IN (%s)',
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
            $elementId = (int) ($row['projek_elemen_id'] ?? 0);
            if ($studentId <= 0 || $elementId <= 0) {
                continue;
            }
            $map[$studentId][$elementId] = $row;
        }

        return $map;
    }
}
