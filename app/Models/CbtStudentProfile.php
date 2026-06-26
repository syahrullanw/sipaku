<?php

namespace App\Models;

use Core\Model;
use PDO;

class CbtStudentProfile extends Model
{
    protected static ?string $table = 'cbt_student_profiles';

    /**
     * @param array<int, int> $studentIds
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mapByStudentIds(array $studentIds): array
    {
        $filtered = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $studentIds), static fn (int $id) => $id > 0)));

        if (empty($filtered)) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($filtered as $index => $studentId) {
            $placeholder = ':student_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $studentId;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE siswa_id IN (%s)',
            static::table(),
            implode(', ', $placeholders),
        );

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
        if ($rows === false) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $studentId = isset($row['siswa_id']) ? (int) $row['siswa_id'] : 0;
            if ($studentId <= 0) {
                continue;
            }
            $map[$studentId] = $row;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveForStudent(int $studentId, array $data): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        $username = self::normalize($data['username'] ?? null);
        $password = self::normalize($data['password'] ?? null);
        $examRoom = self::normalize($data['exam_room'] ?? null);
        $examSession = self::normalize($data['exam_session'] ?? null);

        $hasData = $username !== null || $password !== null || $examRoom !== null || $examSession !== null;

        if (!$hasData) {
            return self::deleteByStudentId($studentId);
        }

        $sql = <<<SQL
INSERT INTO cbt_student_profiles (siswa_id, username, password, exam_room, exam_session, created_at, updated_at)
VALUES (:student_id, :username, :password, :exam_room, :exam_session, :now, :now)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    exam_room = VALUES(exam_room),
    exam_session = VALUES(exam_session),
    updated_at = VALUES(updated_at)
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':username', $username);
        $statement->bindValue(':password', $password);
        $statement->bindValue(':exam_room', $examRoom);
        $statement->bindValue(':exam_session', $examSession);
        $statement->bindValue(':now', $now);

        return $statement->execute();
    }

    public static function deleteByStudentId(int $studentId): bool
    {
        if ($studentId <= 0) {
            return true;
        }

        $statement = static::connection()->prepare('DELETE FROM cbt_student_profiles WHERE siswa_id = :student_id');
        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        return $statement->execute();
    }

    private static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : substr($trimmed, 0, 100);
        }

        if (is_numeric($value)) {
            return substr((string) $value, 0, 100);
        }

        return null;
    }
}
