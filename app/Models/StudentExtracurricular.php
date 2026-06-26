<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class StudentExtracurricular extends Model
{
    protected static ?string $table = 'siswa_ekstrakurikuler';

    /**
     * @return array<int, array<int>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['se.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'se.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $sql = sprintf(
            'SELECT se.siswa_id, se.ekstrakurikuler_id FROM %s se WHERE %s',
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

        $assignments = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            $activityId = (int) ($row['ekstrakurikuler_id'] ?? 0);

            if ($studentId <= 0 || $activityId <= 0) {
                continue;
            }

            if (!isset($assignments[$studentId])) {
                $assignments[$studentId] = [];
            }

            $assignments[$studentId][] = $activityId;
        }

        foreach ($assignments as $studentId => $activityIds) {
            $assignments[$studentId] = array_values(array_unique($activityIds));
        }

        return $assignments;
    }

    /**
     * @param array<int, array<int>> $assignments
     */
    public static function saveAssignments(int $classId, int $schoolYearId, int $teacherId, array $assignments): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0) {
            return;
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $insertSql = <<<SQL
INSERT INTO siswa_ekstrakurikuler (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    ekstrakurikuler_id,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :ekstrakurikuler_id,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    kelas_id = VALUES(kelas_id),
    guru_id = VALUES(guru_id),
    updated_at = VALUES(updated_at)
SQL;

            $insertStatement = $connection->prepare($insertSql);

            if ($insertStatement === false) {
                throw new \RuntimeException('Gagal menyiapkan query penyimpanan ekskul siswa.');
            }

            $now = date('Y-m-d H:i:s');

            $normalizedAssignments = [];

            foreach ($assignments as $studentId => $activityIds) {
                $studentId = (int) $studentId;
                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::isActiveId($studentId)) {
                    continue;
                }

                $activityIds = array_values(array_unique(array_filter(
                    array_map(
                        static fn ($value): int => (int) $value,
                        is_array($activityIds) ? $activityIds : []
                    ),
                    static fn (int $id): bool => $id > 0
                )));

                $normalizedAssignments[$studentId] = $activityIds;

                foreach ($activityIds as $activityId) {
                    $insertStatement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':ekstrakurikuler_id', $activityId, PDO::PARAM_INT);
                    $insertStatement->bindValue(':created_at', $now);
                    $insertStatement->bindValue(':updated_at', $now);

                    $insertStatement->execute();
                    $insertStatement->closeCursor();
                }
            }

            if (!empty($normalizedAssignments)) {
                foreach ($normalizedAssignments as $studentId => $activityIds) {
                    if (empty($activityIds)) {
                        $deleteSql = <<<SQL
DELETE FROM siswa_ekstrakurikuler
WHERE kelas_id = :kelas_id
  AND tahun_ajaran_id = :tahun_ajaran_id
  AND siswa_id = :siswa_id
SQL;

                        $deleteStatement = $connection->prepare($deleteSql);
                        if ($deleteStatement !== false) {
                            $deleteStatement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                            $deleteStatement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                            $deleteStatement->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                            $deleteStatement->execute();
                            $deleteStatement->closeCursor();
                        }
                        continue;
                    }

                    $placeholders = implode(', ', array_fill(0, count($activityIds), '?'));
                    $deleteSql = <<<SQL
DELETE FROM siswa_ekstrakurikuler
WHERE kelas_id = ?
  AND tahun_ajaran_id = ?
  AND siswa_id = ?
  AND ekstrakurikuler_id NOT IN ($placeholders)
SQL;

                    $deleteStatement = $connection->prepare($deleteSql);
                    if ($deleteStatement !== false) {
                        $deleteStatement->bindValue(1, $classId, PDO::PARAM_INT);
                        $deleteStatement->bindValue(2, $schoolYearId, PDO::PARAM_INT);
                        $deleteStatement->bindValue(3, $studentId, PDO::PARAM_INT);

                        foreach ($activityIds as $index => $activityId) {
                            $deleteStatement->bindValue($index + 4, $activityId, PDO::PARAM_INT);
                        }

                        $deleteStatement->execute();
                        $deleteStatement->closeCursor();
                    }
                }

                $studentIds = array_keys($normalizedAssignments);
                $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));

                if (!empty($studentIds)) {
                    $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
                    $deleteExtraSql = <<<SQL
DELETE FROM siswa_ekstrakurikuler
WHERE kelas_id = ?
  AND tahun_ajaran_id = ?
  AND siswa_id NOT IN ($placeholders)
  AND siswa_id IN (SELECT id FROM siswa WHERE status = 'aktif')
SQL;
                    $deleteExtraStatement = $connection->prepare($deleteExtraSql);
                    if ($deleteExtraStatement !== false) {
                        $deleteExtraStatement->bindValue(1, $classId, PDO::PARAM_INT);
                        $deleteExtraStatement->bindValue(2, $schoolYearId, PDO::PARAM_INT);

                        foreach ($studentIds as $index => $studentId) {
                            $deleteExtraStatement->bindValue($index + 3, $studentId, PDO::PARAM_INT);
                        }

                        $deleteExtraStatement->execute();
                        $deleteExtraStatement->closeCursor();
                    }
                }
            } else {
                $deleteAllStatement = $connection->prepare(
                    "DELETE FROM siswa_ekstrakurikuler WHERE kelas_id = :kelas_id AND tahun_ajaran_id = :tahun_ajaran_id AND siswa_id IN (SELECT id FROM siswa WHERE status = 'aktif')"
                );
                if ($deleteAllStatement !== false) {
                    $deleteAllStatement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                    $deleteAllStatement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                    $deleteAllStatement->execute();
                    $deleteAllStatement->closeCursor();
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function detailedByClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['se.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'se.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $sql = <<<SQL
SELECT
    se.siswa_id,
    se.ekstrakurikuler_id,
    se.nilai_keaktifan,
    se.nilai_kemampuan_teknis,
    se.nilai_kehadiran,
    se.nilai_akhir,
    se.predikat,
    se.deskripsi,
    e.nama AS ekstrakurikuler_nama
FROM siswa_ekstrakurikuler se
JOIN ekstrakurikuler e ON e.id = se.ekstrakurikuler_id
WHERE %s
ORDER BY e.nama ASC
SQL;

        $statement = static::connection()->prepare(sprintf($sql, implode(' AND ', $conditions)));

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

        $details = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            $activityId = (int) ($row['ekstrakurikuler_id'] ?? 0);

            if ($studentId <= 0 || $activityId <= 0) {
                continue;
            }

            if (!isset($details[$studentId])) {
                $details[$studentId] = [];
            }

            $details[$studentId][$activityId] = $row;
        }

        return $details;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function studentsByActivity(int $activityId, int $schoolYearId): array
    {
        if ($activityId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    se.siswa_id,
    se.kelas_id,
    se.nilai_keaktifan,
    se.nilai_kemampuan_teknis,
    se.nilai_kehadiran,
    se.nilai_akhir,
    se.predikat,
    se.deskripsi,
    s.nama AS siswa_nama,
    s.nisn AS siswa_nisn,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat
FROM siswa_ekstrakurikuler se
JOIN siswa s ON s.id = se.siswa_id
JOIN kelas k ON k.id = se.kelas_id
WHERE se.ekstrakurikuler_id = :activity_id
  AND se.tahun_ajaran_id = :school_year_id
ORDER BY s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':activity_id', $activityId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $scores
     */
    public static function saveScores(int $activityId, int $schoolYearId, int $teacherId, array $scores): void
    {
        if ($activityId <= 0 || $schoolYearId <= 0 || $teacherId <= 0) {
            return;
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $updateSql = <<<SQL
UPDATE siswa_ekstrakurikuler
SET
    nilai_keaktifan = :nilai_keaktifan,
    nilai_kemampuan_teknis = :nilai_kemampuan_teknis,
    nilai_kehadiran = :nilai_kehadiran,
    nilai_akhir = :nilai_akhir,
    predikat = :predikat,
    deskripsi = :deskripsi,
    updated_at = :updated_at
WHERE ekstrakurikuler_id = :activity_id
  AND tahun_ajaran_id = :school_year_id
  AND siswa_id = :student_id
SQL;

            $updateStatement = $connection->prepare($updateSql);

            if ($updateStatement === false) {
                throw new \RuntimeException('Gagal menyiapkan query pembaruan nilai ekskul.');
            }

            $now = date('Y-m-d H:i:s');

            foreach ($scores as $studentId => $payload) {
                $studentId = (int) $studentId;

                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::isActiveId($studentId)) {
                    continue;
                }

                $updateStatement->bindValue(':nilai_keaktifan', $payload['nilai_keaktifan'], PDO::PARAM_STR);
                $updateStatement->bindValue(':nilai_kemampuan_teknis', $payload['nilai_kemampuan_teknis'], PDO::PARAM_STR);
                $updateStatement->bindValue(':nilai_kehadiran', $payload['nilai_kehadiran'], PDO::PARAM_STR);
                $updateStatement->bindValue(':nilai_akhir', $payload['nilai_akhir'], PDO::PARAM_STR);
                $updateStatement->bindValue(':predikat', $payload['predikat'], PDO::PARAM_STR);
                $updateStatement->bindValue(':deskripsi', $payload['deskripsi'], PDO::PARAM_STR);
                $updateStatement->bindValue(':updated_at', $now);
                $updateStatement->bindValue(':activity_id', $activityId, PDO::PARAM_INT);
                $updateStatement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
                $updateStatement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

                $updateStatement->execute();
                $updateStatement->closeCursor();
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
