<?php

namespace App\Models;

use Core\Model;
use PDO;

class User extends Model
{
    protected static ?string $table = 'users';

    public static function findByUsername(string $username): ?array
    {
        return static::findForLogin($username);
    }

    public static function findForLogin(string $identifier): ?array
    {
        $sql = <<<SQL
SELECT
    u.*,
    g.status AS teacher_status,
    s.status AS student_status,
    s.status_dapodik AS student_dapodik_status,
    s.kelas_id AS student_class_id,
    s.tahun_ajaran_id AS student_school_year_id,
    k.id AS student_joined_class_id,
    s.nipd AS student_nis,
    s.nisn AS student_nisn
FROM users u
LEFT JOIN guru g ON g.id = u.teacher_id
LEFT JOIN siswa s ON s.id = u.student_id
LEFT JOIN kelas k ON k.id = s.kelas_id
WHERE u.username = :identifier
   OR LOWER(u.username) = :identifier_lower
   OR u.email = :identifier
   OR (u.email IS NOT NULL AND u.email <> '' AND LOWER(u.email) = :identifier_lower)
   OR (g.nip IS NOT NULL AND g.nip <> '' AND g.nip = :identifier)
   OR (s.nipd IS NOT NULL AND s.nipd <> '' AND s.nipd = :identifier)
   OR (s.nisn IS NOT NULL AND s.nisn <> '' AND s.nisn = :identifier)
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);
        $statement->bindValue(':identifier', $identifier);
        $statement->bindValue(':identifier_lower', strtolower($identifier));
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function findByTeacherId(int $teacherId): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM users WHERE teacher_id = :teacher_id LIMIT 1'
        );
        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function findByStudentId(int $studentId): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM users WHERE student_id = :student_id LIMIT 1'
        );
        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(array $criteria = []): array
    {
        $sql = <<<SQL
SELECT
    u.id,
    u.name,
    u.username,
    u.email,
    u.role,
    u.created_at,
    u.updated_at,
    u.teacher_id,
    g.nip,
    g.nama AS guru_nama,
    g.telepon AS guru_telepon
FROM users u
LEFT JOIN guru g ON g.id = u.teacher_id
SQL;
        $bindings = [];
        $where = [];

        if (!empty($criteria['keyword'])) {
            $where[] = '(u.name LIKE :keyword OR u.username LIKE :keyword OR u.email LIKE :keyword OR g.nip LIKE :keyword OR g.nama LIKE :keyword)';
            $bindings[':keyword'] = '%' . $criteria['keyword'] . '%';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY u.created_at DESC';

        $statement = static::connection()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
