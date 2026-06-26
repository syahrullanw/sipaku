<?php

namespace App\Models;

use Core\Model;
use PDO;

class SubjectTeacherClass extends Model
{
    protected static ?string $table = 'guru_mata_pelajaran_kelas';
    private static bool $tableEnsured = false;

    public static function ensureSchema(?PDO $connection = null): void
    {
        static::ensureTableExists($connection);
    }

    /**
     * @return array<int>
     */
    public static function classIds(int $assignmentId): array
    {
        if ($assignmentId <= 0) {
            return [];
        }

        $connection = static::connection();
        static::ensureTableExists($connection);

        $statement = $connection->prepare(
            'SELECT kelas_id FROM guru_mata_pelajaran_kelas WHERE guru_mata_pelajaran_id = :assignment ORDER BY kelas_id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($rows === false) {
            return [];
        }

        return array_values(array_unique(array_map(static fn ($id) => (int) $id, $rows)));
    }

    /**
     * @param array<int> $classIds
     */
    public static function sync(int $assignmentId, array $classIds, ?PDO $connection = null): void
    {
        $assignmentId = (int) $assignmentId;
        if ($assignmentId <= 0) {
            return;
        }

        $connection ??= static::connection();
        static::ensureTableExists($connection);

        $classIds = array_values(array_unique(array_map(static fn ($value): int => (int) $value, $classIds)));
        $classIds = array_values(array_filter($classIds, static fn (int $id): bool => $id > 0));

        $existingStatement = $connection->prepare(
            'SELECT kelas_id FROM guru_mata_pelajaran_kelas WHERE guru_mata_pelajaran_id = :assignment'
        );

        if ($existingStatement === false) {
            return;
        }

        $existingStatement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

        if (!$existingStatement->execute()) {
            return;
        }

        $existingRows = $existingStatement->fetchAll(PDO::FETCH_COLUMN);
        $existingIds = $existingRows !== false
            ? array_values(array_unique(array_map(static fn ($value): int => (int) $value, $existingRows)))
            : [];

        $toDelete = array_diff($existingIds, $classIds);
        $toInsert = array_diff($classIds, $existingIds);

        if (!empty($toDelete)) {
            $placeholders = [];
            foreach (array_values($toDelete) as $index => $id) {
                $placeholders[] = ':del_' . $index;
            }

            $sql = sprintf(
                'DELETE FROM guru_mata_pelajaran_kelas WHERE guru_mata_pelajaran_id = :assignment AND kelas_id IN (%s)',
                implode(', ', $placeholders)
            );

            $deleteStatement = $connection->prepare($sql);

            if ($deleteStatement !== false) {
                $deleteStatement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

                foreach (array_values($toDelete) as $index => $id) {
                    $deleteStatement->bindValue(':del_' . $index, $id, PDO::PARAM_INT);
                }

                $deleteStatement->execute();
            }
        }

        if (!empty($toInsert)) {
            $insertStatement = $connection->prepare(
                'INSERT INTO guru_mata_pelajaran_kelas (guru_mata_pelajaran_id, kelas_id, created_at, updated_at)
                 VALUES (:assignment, :class_id, :created_at, :updated_at)'
            );

            if ($insertStatement !== false) {
                $now = date('Y-m-d H:i:s');

                foreach (array_values($toInsert) as $classId) {
                    $insertStatement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':class_id', $classId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':created_at', $now);
                    $insertStatement->bindValue(':updated_at', $now);
                    $insertStatement->execute();
                    $insertStatement->closeCursor();
                }
            }
        }

    }

    /**
     * @param array<int> $assignmentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function classesForAssignments(array $assignmentIds): array
    {
        $assignmentIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $assignmentIds)));
        $assignmentIds = array_values(array_filter($assignmentIds, static fn (int $id): bool => $id > 0));

        if (empty($assignmentIds)) {
            return [];
        }

        $connection = static::connection();
        static::ensureTableExists($connection);

        $placeholders = [];
        foreach ($assignmentIds as $index => $id) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT gmpk.guru_mata_pelajaran_id, k.id, k.tingkat, k.nama, k.jurusan_id, k.kurikulum, k.tahun_ajaran_id, j.nama AS jurusan_nama
             FROM guru_mata_pelajaran_kelas gmpk
             JOIN kelas k ON k.id = gmpk.kelas_id
             LEFT JOIN jurusan j ON j.id = k.jurusan_id
            WHERE gmpk.guru_mata_pelajaran_id IN (%s)
             ORDER BY k.tingkat ASC, k.nama ASC',
            implode(', ', $placeholders)
        );

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($assignmentIds as $index => $id) {
            $statement->bindValue(':assignment_' . $index, $id, PDO::PARAM_INT);
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

            $map[$assignmentId] ??= [];
            $map[$assignmentId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'tingkat' => $row['tingkat'] ?? null,
                'nama' => $row['nama'] ?? null,
                'jurusan_id' => isset($row['jurusan_id']) ? (int) $row['jurusan_id'] : null,
                'jurusan_nama' => $row['jurusan_nama'] ?? null,
                'kurikulum' => $row['kurikulum'] ?? null,
                'tahun_ajaran_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null,
            ];
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function classroomsForAssignment(int $assignmentId): array
    {
        $map = static::classesForAssignments([$assignmentId]);

        return $map[$assignmentId] ?? [];
    }

    /**
     * @return array<int>
     */
    public static function assignmentIdsForClass(int $classId): array
    {
        if ($classId <= 0) {
            return [];
        }

        $connection = static::connection();
        static::ensureTableExists($connection);

        $statement = $connection->prepare(
            'SELECT guru_mata_pelajaran_id FROM guru_mata_pelajaran_kelas WHERE kelas_id = :class'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class', $classId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($rows === false) {
            return [];
        }

        return array_values(array_unique(array_map(static fn ($value): int => (int) $value, $rows)));
    }

    private static function ensureTableExists(?PDO $connection = null): void
    {
        if (static::$tableEnsured) {
            return;
        }

        $connection ??= static::connection();

        if ($connection->inTransaction()) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS guru_mata_pelajaran_kelas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guru_mata_pelajaran_id INT UNSIGNED NOT NULL,
    kelas_id INT UNSIGNED NOT NULL,
    penilaian_mode ENUM('inherit','k13','kurmer') NOT NULL DEFAULT 'inherit',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_gmp_kelas_gmp FOREIGN KEY (guru_mata_pelajaran_id) REFERENCES guru_mata_pelajaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_gmp_kelas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_gmp_kelas (guru_mata_pelajaran_id, kelas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        try {
            $connection->exec($sql);
            static::$tableEnsured = true;
        } catch (\Throwable) {
            // ignore; operations will surface actual error if creation fails
        }
    }
}
