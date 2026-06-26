<?php

namespace App\Models;

use Core\Model;
use PDO;

class HomeroomPrakerinConfirmation extends Model
{
    protected static ?string $table = 'homeroom_prakerin_confirmations';

    /**
     * @param array<int> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function mapByClassIds(array $classIds, int $teacherId): array
    {
        $filteredIds = array_values(array_filter(array_unique(array_map(
            static fn ($id) => (int) $id,
            $classIds
        )), static fn (int $id) => $id > 0));

        if ($filteredIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($filteredIds as $index => $classId) {
            $placeholders[] = ':class_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE kelas_id IN (%s) AND guru_id = :teacher',
            static::table(),
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($filteredIds as $index => $classId) {
            $statement->bindValue(':class_' . $index, $classId, PDO::PARAM_INT);
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $classId = (int) ($row['kelas_id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $map[$classId] = $row;
        }

        return $map;
    }

    public static function upsertForTeacher(int $teacherId, int $classId, bool $required): bool
    {
        if ($teacherId <= 0 || $classId <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $statement = static::connection()->prepare(
            'INSERT INTO ' . static::table() . ' (guru_id, kelas_id, prakerin_required, created_at, updated_at)
            VALUES (:teacher_id, :class_id, :required, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE prakerin_required = VALUES(prakerin_required), updated_at = VALUES(updated_at)'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':required', $required ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }
}
