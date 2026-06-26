<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherAcademicPosition extends Model
{
    protected static ?string $table = 'guru_jabatan_akademik';

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function byYearGroupedByPosition(int $yearId): array
    {
        if ($yearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    gja.*,
    g.nama AS guru_nama,
    g.nip AS guru_nip,
    g.email AS guru_email,
    g.telepon AS guru_telepon,
    j.nama AS jurusan_nama
FROM guru_jabatan_akademik gja
LEFT JOIN guru g ON g.id = gja.guru_id
LEFT JOIN jurusan j ON j.id = gja.jurusan_id
WHERE gja.tahun_ajaran_id = :year
ORDER BY j.nama ASC, gja.updated_at DESC, gja.created_at DESC, g.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $positionId = (int) ($row['jabatan_akademik_id'] ?? 0);
            if ($positionId <= 0) {
                continue;
            }

            if (!isset($grouped[$positionId])) {
                $grouped[$positionId] = [];
            }

            $grouped[$positionId][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forTeacher(int $teacherId, ?int $yearId = null): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $whereParts = ['gja.guru_id = :teacher'];
        $params = [':teacher' => $teacherId];

        if ($yearId !== null && $yearId > 0) {
            $whereParts[] = 'gja.tahun_ajaran_id = :year';
            $params[':year'] = $yearId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        $sql = <<<SQL
SELECT
    gja.*,
    ja.nama AS jabatan_nama,
    ja.level AS jabatan_level,
    j.nama AS jurusan_nama
FROM guru_jabatan_akademik gja
JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id
LEFT JOIN jurusan j ON j.id = gja.jurusan_id
{$whereClause}
ORDER BY ja.nama ASC, gja.created_at DESC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function teacherHasLevel(int $teacherId, int $level, ?int $yearId = null): bool
    {
        if ($teacherId <= 0 || $level < 0) {
            return false;
        }

        $clauses = [
            'gja.guru_id = :teacher',
            'COALESCE(ja.level, 0) = :level',
        ];
        $params = [
            ':teacher' => $teacherId,
            ':level' => $level,
        ];

        if ($yearId !== null && $yearId > 0) {
            $clauses[] = 'gja.tahun_ajaran_id = :year';
            $params[':year'] = $yearId;
        }

        $whereClause = implode(' AND ', $clauses);

        $sql = <<<SQL
SELECT COUNT(*)
FROM guru_jabatan_akademik gja
JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id
WHERE {$whereClause}
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return false;
        }

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    public static function teacherHasAssignedRole(int $teacherId, string $role, ?int $yearId = null, ?int $majorId = null): bool
    {
        if ($teacherId <= 0 || $role === '') {
            return false;
        }

        $clauses = [
            'gja.guru_id = :teacher',
            'ja.assigns_user_role = :role',
        ];
        $params = [
            ':teacher' => $teacherId,
            ':role' => $role,
        ];

        if ($yearId !== null && $yearId > 0) {
            $clauses[] = 'gja.tahun_ajaran_id = :year';
            $params[':year'] = $yearId;
        }

        if ($majorId !== null && $majorId > 0) {
            $clauses[] = 'gja.jurusan_id = :major';
            $params[':major'] = $majorId;
        }

        $sql = 'SELECT COUNT(*) FROM ' . static::$table .
            ' gja JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id WHERE ' .
            implode(' AND ', $clauses);

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($params as $placeholder => $value) {
            if ($placeholder === ':major') {
                $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
                continue;
            }

            $statement->bindValue($placeholder, $value);
        }

        if (!$statement->execute()) {
            return false;
        }

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    public static function clearAssignments(int $yearId, int $positionId, ?int $majorId = null): bool
    {
        if ($yearId <= 0 || $positionId <= 0) {
            return false;
        }

        $sql = 'DELETE FROM guru_jabatan_akademik WHERE tahun_ajaran_id = :year AND jabatan_akademik_id = :position';

        if ($majorId !== null && $majorId > 0) {
            $sql .= ' AND jurusan_id = :major';
        }

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        $statement->bindValue(':position', $positionId, PDO::PARAM_INT);

        if ($majorId !== null && $majorId > 0) {
            $statement->bindValue(':major', $majorId, PDO::PARAM_INT);
        }

        return $statement->execute();
    }

    public static function clearAllAssignments(int $positionId): bool
    {
        if ($positionId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            'DELETE FROM guru_jabatan_akademik WHERE jabatan_akademik_id = :position'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':position', $positionId, PDO::PARAM_INT);

        return $statement->execute();
    }

    public static function replaceAssignment(int $yearId, int $positionId, int $teacherId, ?int $majorId = null): bool
    {
        if ($yearId <= 0 || $positionId <= 0 || $teacherId <= 0) {
            return false;
        }

        $connection = static::connection();

        try {
            $connection->beginTransaction();

            $deleteSql = 'DELETE FROM guru_jabatan_akademik WHERE tahun_ajaran_id = :year AND jabatan_akademik_id = :position';

            if ($majorId !== null && $majorId > 0) {
                $deleteSql .= ' AND jurusan_id = :major';
            }

            $delete = $connection->prepare($deleteSql);

            if ($delete === false) {
                $connection->rollBack();

                return false;
            }

            $delete->bindValue(':year', $yearId, PDO::PARAM_INT);
            $delete->bindValue(':position', $positionId, PDO::PARAM_INT);

            if ($majorId !== null && $majorId > 0) {
                $delete->bindValue(':major', $majorId, PDO::PARAM_INT);
            }

            if (!$delete->execute()) {
                $connection->rollBack();

                return false;
            }

            $now = date('Y-m-d H:i:s');

            $insert = $connection->prepare(
                'INSERT INTO guru_jabatan_akademik (
                    tahun_ajaran_id, guru_id, jabatan_akademik_id, jurusan_id, tanggal_mulai, tanggal_selesai, catatan, created_at, updated_at
                ) VALUES (
                    :year, :teacher, :position, :major, NULL, NULL, NULL, :created_at, :updated_at
                )'
            );

            if ($insert === false) {
                $connection->rollBack();

                return false;
            }

            $insert->bindValue(':year', $yearId, PDO::PARAM_INT);
            $insert->bindValue(':teacher', $teacherId, PDO::PARAM_INT);
            $insert->bindValue(':position', $positionId, PDO::PARAM_INT);
            if ($majorId !== null && $majorId > 0) {
                $insert->bindValue(':major', $majorId, PDO::PARAM_INT);
            } else {
                $insert->bindValue(':major', null, PDO::PARAM_NULL);
            }
            $insert->bindValue(':created_at', $now);
            $insert->bindValue(':updated_at', $now);

            if (!$insert->execute()) {
                $connection->rollBack();

                return false;
            }

            return $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<int, int>
     */
    public static function teacherMajorIdsForRole(int $teacherId, string $role, ?int $yearId = null): array
    {
        if ($teacherId <= 0 || $role === '') {
            return [];
        }

        $sql = <<<SQL
SELECT DISTINCT gja.jurusan_id
FROM guru_jabatan_akademik gja
JOIN jabatan_akademik ja ON ja.id = gja.jabatan_akademik_id
WHERE gja.guru_id = :teacher
  AND ja.assigns_user_role = :role
  AND gja.jurusan_id IS NOT NULL
SQL;

        $params = [
            ':teacher' => $teacherId,
            ':role' => $role,
        ];

        if ($yearId !== null && $yearId > 0) {
            $sql .= ' AND gja.tahun_ajaran_id = :year';
            $params[':year'] = $yearId;
        }

        $connection = static::connection();
        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($placeholder, $value, $type);
        }

        if (!$statement->execute()) {
            return [];
        }

        $ids = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $majorId = (int) ($row['jurusan_id'] ?? 0);
            if ($majorId > 0) {
                $ids[] = $majorId;
            }
        }

        return $ids;
    }
}
