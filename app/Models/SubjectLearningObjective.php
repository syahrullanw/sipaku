<?php

namespace App\Models;

use Core\Model;
use PDO;

class SubjectLearningObjective extends Model
{
    protected static ?string $table = 'mata_pelajaran_tp';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignment(int $assignmentId, int $classId): array
    {
        if ($assignmentId <= 0 || $classId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM mata_pelajaran_tp WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class ORDER BY urutan IS NULL, urutan ASC, kode_tp ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function existsWithCode(int $assignmentId, int $classId, string $code, ?int $ignoreId = null): bool
    {
        $assignmentId = (int) $assignmentId;
        $classId = (int) $classId;
        $code = strtoupper(trim($code));

        if ($assignmentId <= 0 || $classId <= 0 || $code === '') {
            return false;
        }

        $wheres = [
            'guru_mata_pelajaran_id = :assignment',
            'kelas_id = :class',
            'kode_tp = :code',
        ];

        if ($ignoreId !== null) {
            $wheres[] = 'id <> :ignore_id';
        }

        $sql = sprintf('SELECT COUNT(*) FROM mata_pelajaran_tp WHERE %s', implode(' AND ', $wheres));

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        $statement->bindValue(':code', $code, PDO::PARAM_STR);

        if ($ignoreId !== null) {
            $statement->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return false;
        }

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    public static function findForAssignment(int $id, int $assignmentId, int $classId): ?array
    {
        if ($id <= 0 || $assignmentId <= 0 || $classId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM mata_pelajaran_tp WHERE id = :id AND guru_mata_pelajaran_id = :assignment AND kelas_id = :class LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }
}
