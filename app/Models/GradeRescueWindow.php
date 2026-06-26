<?php

namespace App\Models;

use Core\Model;
use DateTimeImmutable;
use PDO;

class GradeRescueWindow extends Model
{
    protected static ?string $table = 'periode_rescue_nilai';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithSchoolYear(): array
    {
        $statement = static::connection()->prepare(
            'SELECT p.*, ta.nama AS tahun_ajaran_nama
             FROM periode_rescue_nilai p
             LEFT JOIN tahun_ajaran ta ON ta.id = p.tahun_ajaran_id
             ORDER BY p.created_at DESC, p.id DESC'
        );

        if ($statement === false || !$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function activeForContext(int $schoolYearId, string $semester, ?DateTimeImmutable $at = null): ?array
    {
        if ($schoolYearId <= 0) {
            return null;
        }

        $semesterNormalized = strtolower(trim($semester));
        if (!in_array($semesterNormalized, ['ganjil', 'genap'], true)) {
            return null;
        }

        $probeAt = ($at ?? new DateTimeImmutable())->format('Y-m-d H:i:s');
        $statement = static::connection()->prepare(
            'SELECT *
             FROM periode_rescue_nilai
             WHERE tahun_ajaran_id = :tahun_ajaran_id
               AND semester = :semester
               AND status = :status
               AND mulai_at <= :probe_at
               AND selesai_at >= :probe_at
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semesterNormalized);
        $statement->bindValue(':status', 'aktif');
        $statement->bindValue(':probe_at', $probeAt);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function hasOverlappingWindow(
        int $schoolYearId,
        string $semester,
        string $startAt,
        string $endAt,
        ?int $ignoreId = null
    ): bool {
        if ($schoolYearId <= 0) {
            return false;
        }

        $sql = 'SELECT COUNT(*)
                FROM periode_rescue_nilai
                WHERE tahun_ajaran_id = :tahun_ajaran_id
                  AND semester = :semester
                  AND mulai_at < :end_at
                  AND selesai_at > :start_at';

        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id <> :ignore_id';
        }

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':semester', $semester);
        $statement->bindValue(':start_at', $startAt);
        $statement->bindValue(':end_at', $endAt);

        if ($ignoreId !== null && $ignoreId > 0) {
            $statement->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return false;
        }

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }
}