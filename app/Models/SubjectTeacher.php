<?php

namespace App\Models;

use Core\Model;
use PDO;

class SubjectTeacher extends Model
{
    protected static ?string $table = 'guru_mata_pelajaran';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithRelations(?int $schoolYearId = null): array
    {
        $connection = static::connection();
        $codes = [];

        foreach (Subject::GROUPS as $group) {
            $codes[] = $connection->quote($group['code']);
        }

        $orderClause = implode(', ', $codes);

        $baseSql = <<<SQL
SELECT gmp.*, mp.kode AS mata_pelajaran_kode, mp.nama AS mata_pelajaran_nama, mp.jenis AS mata_pelajaran_jenis,
       mp.jurusan_id AS mata_pelajaran_jurusan_id, j.nama AS mata_pelajaran_jurusan_nama,
       mp.tahun_ajaran_id AS mata_pelajaran_tahun_ajaran_id, ta.nama AS mata_pelajaran_tahun_ajaran_nama,
       ta.semester_aktif AS mata_pelajaran_tahun_ajaran_semester,
       ta.tanggal_mulai AS mata_pelajaran_tahun_ajaran_mulai,
       ta.status AS mata_pelajaran_tahun_ajaran_status,
       mp.deskripsi AS mata_pelajaran_deskripsi,
       g.nama AS guru_nama, g.nip AS guru_nip
FROM guru_mata_pelajaran gmp
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = mp.jurusan_id
JOIN guru g ON g.id = gmp.guru_id
%s
ORDER BY FIELD(mp.jenis, {$orderClause}), ta.tanggal_mulai DESC, mp.nama ASC, g.nama ASC
SQL;

        $where = '';
        if ($schoolYearId !== null) {
            $where = 'WHERE mp.tahun_ajaran_id = :school_year_id';
        }

        $sql = sprintf($baseSql, $where);
        $statement = $connection->prepare($sql);
        if ($statement === false) {
            return [];
        }

        if ($schoolYearId !== null) {
            $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        if (!empty($rows)) {
            $assignmentIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $rows
            ), static fn (int $id): bool => $id > 0));

            if (!empty($assignmentIds)) {
                $classMap = SubjectTeacherClass::classesForAssignments($assignmentIds);

                foreach ($rows as $index => $row) {
                    $assignmentId = (int) ($row['id'] ?? 0);
                    $rows[$index]['classes'] = $classMap[$assignmentId] ?? [];
                }
            }
        }

        return $rows;
    }

    public static function findWithRelations(int $assignmentId): ?array
    {
        if ($assignmentId <= 0) {
            return null;
        }

        $connection = static::connection();
        $codes = [];

        foreach (Subject::GROUPS as $group) {
            $codes[] = $connection->quote($group['code']);
        }

        $orderClause = implode(', ', $codes);

        $sql = <<<SQL
SELECT gmp.*, mp.kode AS mata_pelajaran_kode, mp.nama AS mata_pelajaran_nama, mp.jenis AS mata_pelajaran_jenis,
       mp.jurusan_id AS mata_pelajaran_jurusan_id, j.nama AS mata_pelajaran_jurusan_nama,
       mp.tahun_ajaran_id AS mata_pelajaran_tahun_ajaran_id, ta.nama AS mata_pelajaran_tahun_ajaran_nama,
       ta.semester_aktif AS mata_pelajaran_tahun_ajaran_semester,
       ta.tanggal_mulai AS mata_pelajaran_tahun_ajaran_mulai,
       ta.status AS mata_pelajaran_tahun_ajaran_status,
       mp.deskripsi AS mata_pelajaran_deskripsi,
       g.nama AS guru_nama, g.nip AS guru_nip
FROM guru_mata_pelajaran gmp
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = mp.jurusan_id
JOIN guru g ON g.id = gmp.guru_id
WHERE gmp.id = :assignment
ORDER BY FIELD(mp.jenis, {$orderClause}), ta.tanggal_mulai DESC, mp.nama ASC, g.nama ASC
LIMIT 1
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $classes = SubjectTeacherClass::classesForAssignments([$assignmentId]);
        $record['classes'] = $classes[$assignmentId] ?? [];

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byTeacher(int $teacherId, ?int $schoolYearId = null): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $connection = static::connection();
        $codes = [];

        foreach (Subject::GROUPS as $group) {
            $codes[] = $connection->quote($group['code']);
        }

        $orderClause = implode(', ', $codes);

        $sql = <<<SQL
SELECT gmp.*, mp.kode AS mata_pelajaran_kode, mp.nama AS mata_pelajaran_nama, mp.jenis AS mata_pelajaran_jenis,
       mp.jurusan_id AS mata_pelajaran_jurusan_id, j.nama AS mata_pelajaran_jurusan_nama,
       mp.tahun_ajaran_id AS mata_pelajaran_tahun_ajaran_id, ta.nama AS mata_pelajaran_tahun_ajaran_nama,
       ta.semester_aktif AS mata_pelajaran_tahun_ajaran_semester,
       ta.tanggal_mulai AS mata_pelajaran_tahun_ajaran_mulai,
       ta.status AS mata_pelajaran_tahun_ajaran_status,
       mp.deskripsi AS mata_pelajaran_deskripsi,
       g.nama AS guru_nama, g.nip AS guru_nip
FROM guru_mata_pelajaran gmp
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = mp.jurusan_id
JOIN guru g ON g.id = gmp.guru_id
WHERE gmp.guru_id = :teacher
%s
ORDER BY FIELD(mp.jenis, {$orderClause}), ta.tanggal_mulai DESC, mp.nama ASC
SQL;

        $filter = '';
        if ($schoolYearId !== null) {
            $filter = 'AND mp.tahun_ajaran_id = :school_year_id';
        }

        $statement = $connection->prepare(sprintf($sql, $filter));

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);

        if ($schoolYearId !== null) {
            $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        if (!empty($rows)) {
            $assignmentIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $rows
            ), static fn (int $id): bool => $id > 0));

            if (!empty($assignmentIds)) {
                $classMap = SubjectTeacherClass::classesForAssignments($assignmentIds);

                foreach ($rows as $index => $row) {
                    $assignmentId = (int) ($row['id'] ?? 0);
                    $rows[$index]['classes'] = $classMap[$assignmentId] ?? [];
                }
            }
        }

        return $rows;
    }

    public static function findForTeacher(int $assignmentId, int $teacherId): ?array
    {
        if ($assignmentId <= 0 || $teacherId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT gmp.*, mp.kode AS mata_pelajaran_kode, mp.nama AS mata_pelajaran_nama, mp.jenis AS mata_pelajaran_jenis,
                    mp.jurusan_id AS mata_pelajaran_jurusan_id, mp.tahun_ajaran_id AS mata_pelajaran_tahun_ajaran_id,
                    ta.nama AS mata_pelajaran_tahun_ajaran_nama, ta.semester_aktif AS mata_pelajaran_tahun_ajaran_semester,
                    j.nama AS mata_pelajaran_jurusan_nama, mp.deskripsi AS mata_pelajaran_deskripsi
             FROM guru_mata_pelajaran gmp
             JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
             JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
             LEFT JOIN jurusan j ON j.id = mp.jurusan_id
             WHERE gmp.id = :assignment AND gmp.guru_id = :teacher
             LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);
        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $assignmentId = (int) ($record['id'] ?? 0);
        $record['classes'] = $assignmentId > 0 ? SubjectTeacherClass::classroomsForAssignment($assignmentId) : [];

        return $record;
    }

    public static function findBySubjectAndTeacher(int $subjectId, int $teacherId): ?array
    {
        if ($subjectId <= 0 || $teacherId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM guru_mata_pelajaran WHERE mata_pelajaran_id = :subject AND guru_id = :teacher LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':subject', $subjectId, PDO::PARAM_INT);
        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $assignmentId = (int) ($record['id'] ?? 0);
        $record['classes'] = $assignmentId > 0 ? SubjectTeacherClass::classroomsForAssignment($assignmentId) : [];

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bySchoolYearForClass(int $schoolYearId, ?int $majorId = null, ?int $classId = null): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $connection = static::connection();
        $codes = [];

        foreach (Subject::GROUPS as $group) {
            $codes[] = $connection->quote($group['code']);
        }

        $orderClause = implode(', ', $codes);

        $joinClause = '';
        $classFilter = '';

        if ($classId !== null && $classId > 0) {
            $joinClause = 'JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id';
            $classFilter = ' AND gmpk.kelas_id = :class_id';
        }

        $sql = <<<SQL
SELECT gmp.*,
       mp.kode AS mata_pelajaran_kode,
       mp.nama AS mata_pelajaran_nama,
       mp.jenis AS mata_pelajaran_jenis,
       mp.jurusan_id AS mata_pelajaran_jurusan_id,
       mp.deskripsi AS mata_pelajaran_deskripsi,
       ta.nama AS mata_pelajaran_tahun_ajaran_nama,
       ta.semester_aktif AS mata_pelajaran_tahun_ajaran_semester,
       j.nama AS mata_pelajaran_jurusan_nama,
       g.nama AS guru_nama,
       g.nip AS guru_nip
FROM guru_mata_pelajaran gmp
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = mp.jurusan_id
JOIN guru g ON g.id = gmp.guru_id
{$joinClause}
WHERE mp.tahun_ajaran_id = :school_year
SQL;

        if ($majorId !== null && $majorId > 0) {
            $sql .= ' AND (mp.jurusan_id IS NULL OR mp.jurusan_id = :major_id)';
        }

        $sql .= $classFilter;

        $sql .= sprintf(' ORDER BY FIELD(mp.jenis, %s), mp.nama ASC', $orderClause);

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':school_year', $schoolYearId, PDO::PARAM_INT);

        if ($majorId !== null && $majorId > 0) {
            $statement->bindValue(':major_id', $majorId, PDO::PARAM_INT);
        }

        if ($classId !== null && $classId > 0) {
            $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        if (!empty($rows)) {
            $assignmentIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $rows
            ), static fn (int $id): bool => $id > 0));

            if (!empty($assignmentIds)) {
                $classMap = SubjectTeacherClass::classesForAssignments($assignmentIds);

                foreach ($rows as $index => $row) {
                    $assignmentId = (int) ($row['id'] ?? 0);
                    $rows[$index]['classes'] = $classMap[$assignmentId] ?? [];
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int> $classIds
     */
    public static function createWithClasses(array $attributes, array $classIds): bool
    {
        $connection = static::connection();

        try {
            SubjectTeacherClass::ensureSchema($connection);
            $connection->beginTransaction();

            if (empty($attributes)) {
                throw new \RuntimeException('Attributes cannot be empty.');
            }

            $columns = array_keys($attributes);
            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                static::table(),
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $statement = $connection->prepare($sql);

            if ($statement === false) {
                throw new \RuntimeException('Failed to prepare insert statement for subject teacher.');
            }

            foreach ($attributes as $column => $value) {
                $statement->bindValue(':' . $column, $value);
            }

            if (!$statement->execute()) {
                throw new \RuntimeException('Failed to insert subject teacher assignment.');
            }

            $assignmentId = (int) $connection->lastInsertId();

            if ($assignmentId <= 0) {
                throw new \RuntimeException('Failed to obtain subject teacher assignment ID.');
            }

            SubjectTeacherClass::sync($assignmentId, $classIds, $connection);

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int> $classIds
     */
    public static function updateWithClasses(int $assignmentId, array $attributes, array $classIds): bool
    {
        if ($assignmentId <= 0) {
            return false;
        }

        $connection = static::connection();

        try {
            SubjectTeacherClass::ensureSchema($connection);
            $connection->beginTransaction();

            if (!empty($attributes)) {
                $columns = array_keys($attributes);
                $assignments = array_map(static fn (string $column): string => "{$column} = :{$column}", $columns);

                $sql = sprintf(
                    'UPDATE %s SET %s WHERE %s = :__id LIMIT 1',
                    static::table(),
                    implode(', ', $assignments),
                    static::$primaryKey,
                );

                $statement = $connection->prepare($sql);

                if ($statement === false) {
                    throw new \RuntimeException('Failed to prepare update statement for subject teacher.');
                }

                foreach ($attributes as $column => $value) {
                    $statement->bindValue(':' . $column, $value);
                }

                $statement->bindValue(':__id', $assignmentId, PDO::PARAM_INT);

                if (!$statement->execute()) {
                    throw new \RuntimeException('Failed to update subject teacher assignment.');
                }
            }

            SubjectTeacherClass::sync($assignmentId, $classIds, $connection);

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<int>
     */
    public static function assignedClassIds(int $assignmentId): array
    {
        return SubjectTeacherClass::classIds($assignmentId);
    }
}
