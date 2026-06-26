<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class StudentPrakerinPlacement extends Model
{
    protected static ?string $table = 'penempatan_prakerin';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $conditions = ['pp.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'pp.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $sql = sprintf(
            'SELECT pp.*, tp.nama AS tempat_nama FROM %s pp LEFT JOIN tempat_prakerin tp ON tp.id = pp.tempat_prakerin_id WHERE %s',
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

        $placements = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $row['tempat_prakerin_id'] = isset($row['tempat_prakerin_id']) ? (int) $row['tempat_prakerin_id'] : null;
            $row['kelas_id'] = isset($row['kelas_id']) ? (int) $row['kelas_id'] : null;
            $row['tahun_ajaran_id'] = isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null;
            $row['guru_id'] = isset($row['guru_id']) ? (int) $row['guru_id'] : null;

            $placements[$studentId] = $row;
        }

        return $placements;
    }

    /**
     * @param array<int, int> $placements
     */
    public static function saveMany(int $classId, int $schoolYearId, int $teacherId, array $placements): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0 || empty($placements)) {
            return;
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                <<<SQL
INSERT INTO penempatan_prakerin (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    tempat_prakerin_id,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :tempat_prakerin_id,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    kelas_id = VALUES(kelas_id),
    guru_id = VALUES(guru_id),
    tempat_prakerin_id = VALUES(tempat_prakerin_id),
    updated_at = VALUES(updated_at)
SQL
            );

            if ($statement === false) {
                throw new \RuntimeException('Gagal menyiapkan penyimpanan penempatan prakerin.');
            }

            $now = date('Y-m-d H:i:s');

            $studentIds = [];

            foreach ($placements as $studentId => $placeId) {
                $studentId = (int) $studentId;
                $placeId = (int) $placeId;

                if ($studentId <= 0 || $placeId <= 0) {
                    continue;
                }

                if (!Student::isActiveId($studentId)) {
                    continue;
                }

                $studentIds[] = $studentId;

                $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                $statement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                $statement->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                $statement->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                $statement->bindValue(':tempat_prakerin_id', $placeId, PDO::PARAM_INT);
                $statement->bindValue(':created_at', $now);
                $statement->bindValue(':updated_at', $now);

                $statement->execute();
                $statement->closeCursor();
            }

            if (!empty($studentIds)) {
                $studentIds = array_values(array_unique(array_filter($studentIds, static fn (int $id): bool => $id > 0)));

                $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));

                $deleteSql = <<<SQL
DELETE FROM penempatan_prakerin
WHERE kelas_id = ?
  AND tahun_ajaran_id = ?
  AND siswa_id NOT IN ($placeholders)
  AND siswa_id IN (SELECT id FROM siswa WHERE status = 'aktif')
SQL;

                $deleteStatement = $connection->prepare($deleteSql);

                if ($deleteStatement !== false) {
                    $deleteStatement->bindValue(1, $classId, PDO::PARAM_INT);
                    $deleteStatement->bindValue(2, $schoolYearId, PDO::PARAM_INT);

                    foreach ($studentIds as $index => $studentId) {
                        $deleteStatement->bindValue($index + 3, $studentId, PDO::PARAM_INT);
                    }

                    $deleteStatement->execute();
                    $deleteStatement->closeCursor();
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function studentsByPlace(int $placeId, int $schoolYearId): array
    {
        if ($placeId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT
    pp.siswa_id,
    pp.kelas_id,
    s.nama AS siswa_nama,
    s.nisn,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat
FROM penempatan_prakerin pp
JOIN siswa s ON s.id = pp.siswa_id
JOIN kelas k ON k.id = pp.kelas_id
WHERE pp.tempat_prakerin_id = :place_id
  AND pp.tahun_ajaran_id = :school_year_id
ORDER BY s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
