<?php

namespace App\Models;

use Core\Model;
use PDO;

class SubjectCompetency extends Model
{
    protected static ?string $table = 'mata_pelajaran_kd';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byAssignment(int $assignmentId, string $type, ?int $classId = null): array
    {
        if ($assignmentId <= 0 || $classId === null || $classId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM mata_pelajaran_kd WHERE guru_mata_pelajaran_id = :assignment AND kelas_id = :class AND jenis = :type ORDER BY kode ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        $statement->bindValue(':type', $type, PDO::PARAM_STR);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function findForAssignment(int $id, int $assignmentId, ?int $classId = null): ?array
    {
        if ($id <= 0 || $assignmentId <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM mata_pelajaran_kd WHERE id = :id AND guru_mata_pelajaran_id = :assignment';
        if ($classId !== null && $classId > 0) {
            $sql .= ' AND kelas_id = :class';
        }
        $sql .= ' LIMIT 1';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        if ($classId !== null && $classId > 0) {
            $statement->bindValue(':class', $classId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function existsWithCode(int $assignmentId, int $classId, string $type, string $code, ?int $ignoreId = null): bool
    {
        if ($assignmentId <= 0 || $classId <= 0 || $code === '') {
            return false;
        }

        $wheres = [
            'guru_mata_pelajaran_id = :assignment',
            'kelas_id = :class',
            'jenis = :type',
            'kode = :code',
        ];

        $params = [
            ':assignment' => $assignmentId,
            ':class' => $classId,
            ':type' => $type,
            ':code' => $code,
        ];

        if ($ignoreId !== null) {
            $wheres[] = 'id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM mata_pelajaran_kd WHERE %s',
            implode(' AND ', $wheres)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($params as $placeholder => $value) {
            $typeParam = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($placeholder, $value, $typeParam);
        }

        if (!$statement->execute()) {
            return false;
        }

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }
}
