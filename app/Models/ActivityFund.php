<?php

namespace App\Models;

use Core\Model;
use PDO;

class ActivityFund extends Model
{
    protected static ?string $table = 'dana_kegiatan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pendingApprovals(): array
    {
        $sql = <<<SQL
SELECT *
FROM dana_kegiatan
WHERE status IN ('diajukan','diverifikasi_bendahara','menunggu_acc')
ORDER BY tanggal_pengajuan ASC
SQL;

        $statement = static::connection()->query($sql);

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
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
            'SELECT COUNT(*) FROM dana_kegiatan WHERE guru_id = :teacher AND status IN (%s)',
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
}
