<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class StudentPromotionStatus extends Model
{
    protected static ?string $table = 'status_naik_kelas';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['kelas_id = :kelas_id'];
        $params = [':kelas_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'tahun_ajaran_id = :tahun_ajaran_id';
            $params[':tahun_ajaran_id'] = $schoolYearId;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            static::table(),
            implode(' AND ', $conditions)
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

        $records = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $records[$studentId] = [
                'status' => $row['status'] ?? null,
                'catatan' => $row['catatan'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function promotedStudentsForYear(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    snk.siswa_id,
    snk.kelas_id,
    k.jurusan_id,
    k.tingkat,
    k.nama
FROM status_naik_kelas snk
JOIN kelas k ON k.id = snk.kelas_id
WHERE snk.tahun_ajaran_id = :school_year_id
  AND snk.status = 'naik'
  AND k.tahun_ajaran_id = :school_year_id
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     */
    public static function saveMany(int $classId, int $schoolYearId, int $teacherId, array $payloads): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0) {
            return;
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $insert = $connection->prepare(
                <<<SQL
INSERT INTO status_naik_kelas (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    status,
    catatan,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :status,
    :catatan,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    guru_id = VALUES(guru_id),
    status = VALUES(status),
    catatan = VALUES(catatan),
    updated_at = VALUES(updated_at)
SQL
            );

            if ($insert === false) {
                throw new \RuntimeException('Gagal menyiapkan pernyataan status naik kelas.');
            }

            $delete = $connection->prepare(
                <<<SQL
DELETE FROM status_naik_kelas
WHERE tahun_ajaran_id = :tahun_ajaran_id
  AND kelas_id = :kelas_id
  AND siswa_id = :siswa_id
SQL
            );

            if ($delete === false) {
                throw new \RuntimeException('Gagal menyiapkan penghapusan status naik kelas.');
            }

            $now = date('Y-m-d H:i:s');

            foreach ($payloads as $studentId => $data) {
                $studentId = (int) $studentId;
                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::isActiveId($studentId)) {
                    continue;
                }

                $status = $data['status'] ?? null;
                if (!in_array($status, ['naik', 'tinggal'], true)) {
                    $delete->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                    $delete->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                    $delete->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                    $delete->execute();
                    $delete->closeCursor();
                    continue;
                }

                $note = $data['catatan'] ?? null;
                if (is_string($note)) {
                    $note = trim($note);
                } else {
                    $note = null;
                }

                $insert->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                $insert->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                $insert->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                $insert->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                $insert->bindValue(':status', $status);
                if ($note !== null && $note !== '') {
                    $insert->bindValue(':catatan', $note, PDO::PARAM_STR);
                } else {
                    $insert->bindValue(':catatan', null, PDO::PARAM_NULL);
                }
                $insert->bindValue(':created_at', $now);
                $insert->bindValue(':updated_at', $now);

                $insert->execute();
                $insert->closeCursor();
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
