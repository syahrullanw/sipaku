<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherLoan extends Model
{
    protected static ?string $table = 'kasbon_guru';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function findActiveByTeacher(int $teacherId): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            "SELECT * FROM kasbon_guru WHERE guru_id = :teacher AND status NOT IN ('lunas','ditolak') ORDER BY created_at DESC"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function countPendingForTeacher(int $teacherId, ?int $schoolYearId = null): int
    {
        if ($teacherId <= 0) {
            return 0;
        }

        $statuses = ['diajukan', 'diverifikasi_bendahara', 'menunggu_acc'];
        $statusPlaceholders = [];

        foreach ($statuses as $index => $status) {
            $statusPlaceholders[] = ':status_' . $index;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM kasbon_guru WHERE guru_id = :teacher AND status IN (%s)',
            implode(', ', $statusPlaceholders)
        );

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $sql .= ' AND tahun_ajaran_id = :school_year';
        }

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':teacher', $teacherId, PDO::PARAM_INT);

        foreach ($statuses as $index => $status) {
            $statement->bindValue(':status_' . $index, $status, PDO::PARAM_STR);
        }

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $statement->bindValue(':school_year', $schoolYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    public static function findByCode(string $code): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM kasbon_guru WHERE kode = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
