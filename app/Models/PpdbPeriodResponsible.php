<?php

namespace App\Models;

use Core\Model;
use PDO;

class PpdbPeriodResponsible extends Model
{
    protected static ?string $table = 'ppdb_periode_penanggung_jawab';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forPeriod(int $periodId): array
    {
        $sql = <<<SQL
SELECT r.*, g.nama AS guru_nama, g.nip, g.email
FROM ppdb_periode_penanggung_jawab r
JOIN guru g ON g.id = r.guru_id
WHERE r.periode_id = :periode_id
ORDER BY FIELD(r.peran, 'ketua', 'sekretaris', 'anggota'), g.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);
        $statement->execute();
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $records === false ? [] : $records;
    }

    public static function deleteByPeriod(int $periodId): bool
    {
        $statement = static::connection()->prepare(
            'DELETE FROM ppdb_periode_penanggung_jawab WHERE periode_id = :periode_id'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':periode_id', $periodId, PDO::PARAM_INT);

        return $statement->execute();
    }

    public static function assign(int $periodId, int $teacherId, string $role = 'anggota'): bool
    {
        return static::create([
            'periode_id' => $periodId,
            'guru_id' => $teacherId,
            'peran' => $role,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function teacherHasAssignment(int $teacherId, ?int $periodId = null, bool $onlyActive = true): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        $clauses = [
            'r.guru_id = :teacher_id',
        ];
        $params = [
            ':teacher_id' => $teacherId,
        ];

        if ($periodId !== null) {
            $clauses[] = 'r.periode_id = :periode_id';
            $params[':periode_id'] = $periodId;
        }

        if ($onlyActive) {
            $clauses[] = "p.status = 'aktif'";
        }

        $where = implode(' AND ', $clauses);

        $sql = <<<SQL
SELECT COUNT(*) AS aggregate
FROM ppdb_periode_penanggung_jawab r
JOIN ppdb_periode p ON p.id = r.periode_id
WHERE {$where}
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();
        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function teacherActiveAssignments(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT p.*, r.peran
FROM ppdb_periode_penanggung_jawab r
JOIN ppdb_periode p ON p.id = r.periode_id
WHERE r.guru_id = :teacher_id
  AND p.status = 'aktif'
ORDER BY p.updated_at DESC, p.id DESC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $statement->execute();
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $records === false ? [] : $records;
    }
}
