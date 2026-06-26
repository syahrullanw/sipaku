<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class StudentAttendance extends Model
{
    protected static ?string $table = 'presensi_siswa';

    /**
     * @return array<int, array<string, int>>
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
                'sakit' => isset($row['sakit']) ? (int) $row['sakit'] : 0,
                'izin' => isset($row['izin']) ? (int) $row['izin'] : 0,
                'bolos' => isset($row['bolos']) ? (int) $row['bolos'] : 0,
                'alpa' => isset($row['alpa']) ? (int) $row['alpa'] : 0,
            ];
        }

        return $records;
    }

    /**
     * @param array<int, array<string, int>> $payloads
     */
    public static function saveMany(int $classId, int $schoolYearId, int $teacherId, array $payloads): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0 || empty($payloads)) {
            return;
        }

        $connection = static::connection();

        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                <<<SQL
INSERT INTO presensi_siswa (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    sakit,
    izin,
    bolos,
    alpa,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :sakit,
    :izin,
    :bolos,
    :alpa,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    guru_id = VALUES(guru_id),
    sakit = VALUES(sakit),
    izin = VALUES(izin),
    bolos = VALUES(bolos),
    alpa = VALUES(alpa),
    updated_at = VALUES(updated_at)
SQL
            );

            if ($statement === false) {
                throw new \RuntimeException('Gagal menyiapkan pernyataan presensi.');
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

                $sick = isset($data['sakit']) ? max(0, (int) $data['sakit']) : 0;
                $permit = isset($data['izin']) ? max(0, (int) $data['izin']) : 0;
                $truant = isset($data['bolos']) ? max(0, (int) $data['bolos']) : 0;
                $absent = isset($data['alpa']) ? max(0, (int) $data['alpa']) : 0;

                $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                $statement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                $statement->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                $statement->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                $statement->bindValue(':sakit', $sick, PDO::PARAM_INT);
                $statement->bindValue(':izin', $permit, PDO::PARAM_INT);
                $statement->bindValue(':bolos', $truant, PDO::PARAM_INT);
                $statement->bindValue(':alpa', $absent, PDO::PARAM_INT);
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
}
