<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class PrakerinAssessment extends Model
{
    protected static ?string $table = 'penilaian_prakerin';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byPlace(int $placeId, int $schoolYearId): array
    {
        if ($placeId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM penilaian_prakerin WHERE tempat_prakerin_id = :place_id AND tahun_ajaran_id = :school_year_id'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':place_id', $placeId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $results[$studentId] = $row;
        }

        return $results;
    }

    /**
     * @param array<int, array<string, float>> $scores
     */
    public static function saveMany(int $placeId, int $schoolYearId, int $teacherId, array $scores): void
    {
        if ($placeId <= 0 || $schoolYearId <= 0 || $teacherId <= 0 || empty($scores)) {
            return;
        }

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                <<<SQL
INSERT INTO penilaian_prakerin (
    tahun_ajaran_id,
    tempat_prakerin_id,
    siswa_id,
    guru_id,
    nilai_keaktifan,
    nilai_jurnal,
    nilai_laporan,
    nilai_akhir,
    predikat,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :tempat_prakerin_id,
    :siswa_id,
    :guru_id,
    :nilai_keaktifan,
    :nilai_jurnal,
    :nilai_laporan,
    :nilai_akhir,
    :predikat,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    nilai_keaktifan = VALUES(nilai_keaktifan),
    nilai_jurnal = VALUES(nilai_jurnal),
    nilai_laporan = VALUES(nilai_laporan),
    nilai_akhir = VALUES(nilai_akhir),
    predikat = VALUES(predikat),
    guru_id = VALUES(guru_id),
    updated_at = VALUES(updated_at)
SQL
            );

            if ($statement === false) {
                throw new \RuntimeException('Gagal menyiapkan penyimpanan penilaian prakerin.');
            }

            $now = date('Y-m-d H:i:s');

            foreach ($scores as $studentId => $score) {
                $studentId = (int) $studentId;
                if ($studentId <= 0) {
                    continue;
                }

                if (!Student::isActiveId($studentId)) {
                    continue;
                }

                $keaktifan = (float) ($score['nilai_keaktifan'] ?? 0);
                $jurnal = (float) ($score['nilai_jurnal'] ?? 0);
                $laporan = (float) ($score['nilai_laporan'] ?? 0);
                $finalScore = (float) ($score['nilai_akhir'] ?? 0);
                $grade = (string) ($score['predikat'] ?? 'Kurang');

                $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                $statement->bindValue(':tempat_prakerin_id', $placeId, PDO::PARAM_INT);
                $statement->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                $statement->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                $statement->bindValue(':nilai_keaktifan', $keaktifan);
                $statement->bindValue(':nilai_jurnal', $jurnal);
                $statement->bindValue(':nilai_laporan', $laporan);
                $statement->bindValue(':nilai_akhir', $finalScore);
                $statement->bindValue(':predikat', $grade);
                $statement->bindValue(':created_at', $now);
                $statement->bindValue(':updated_at', $now);

                $statement->execute();
                $statement->closeCursor();
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<string, mixed>>
     */
    public static function byStudents(array $studentIds, int $schoolYearId): array
    {
        $studentIds = array_values(array_unique(array_filter($studentIds, static fn ($id) => (int) $id > 0)));

        if (empty($studentIds) || $schoolYearId <= 0) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $sql = <<<SQL
SELECT * FROM penilaian_prakerin
WHERE tahun_ajaran_id = ?
  AND siswa_id IN ($placeholders)
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(1, $schoolYearId, PDO::PARAM_INT);

        foreach ($studentIds as $index => $studentId) {
            $statement->bindValue($index + 2, $studentId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $results = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $results[$studentId] = $row;
        }

        return $results;
    }
}
