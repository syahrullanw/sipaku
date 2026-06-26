<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentAcademicPosition extends Model
{
    protected static ?string $table = 'siswa_jabatan_akademik';

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function byYearGroupedByPosition(int $yearId): array
    {
        if ($yearId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT sap.*, s.nama AS siswa_nama, s.nipd AS siswa_nipd, s.nisn AS siswa_nisn, s.status AS siswa_status, s.status_dapodik AS siswa_status_dapodik
FROM siswa_jabatan_akademik sap
LEFT JOIN siswa s ON s.id = sap.siswa_id
WHERE sap.tahun_ajaran_id = :year
ORDER BY sap.updated_at DESC, sap.created_at DESC, s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $positionId = (int) ($row['jabatan_akademik_id'] ?? 0);
            if ($positionId <= 0) {
                continue;
            }

            if (!isset($grouped[$positionId])) {
                $grouped[$positionId] = [];
            }

            $grouped[$positionId][] = $row;
        }

        return $grouped;
    }

    public static function replaceAssignment(int $yearId, int $positionId, int $studentId): bool
    {
        if ($yearId <= 0 || $positionId <= 0 || $studentId <= 0) {
            return false;
        }

        if (!Student::isActiveId($studentId)) {
            return false;
        }

        $connection = static::connection();

        try {
            $connection->beginTransaction();

            $delete = $connection->prepare(
                'DELETE FROM siswa_jabatan_akademik WHERE tahun_ajaran_id = :year AND jabatan_akademik_id = :position'
            );

            if ($delete === false) {
                $connection->rollBack();

                return false;
            }

            $delete->bindValue(':year', $yearId, PDO::PARAM_INT);
            $delete->bindValue(':position', $positionId, PDO::PARAM_INT);

            if (!$delete->execute()) {
                $connection->rollBack();

                return false;
            }

            $insert = $connection->prepare(
                'INSERT INTO siswa_jabatan_akademik (
                    tahun_ajaran_id, siswa_id, jabatan_akademik_id, created_at, updated_at
                ) VALUES (:year, :student, :position, :created, :updated)'
            );

            if ($insert === false) {
                $connection->rollBack();

                return false;
            }

            $timestamp = date('Y-m-d H:i:s');
            $insert->bindValue(':year', $yearId, PDO::PARAM_INT);
            $insert->bindValue(':position', $positionId, PDO::PARAM_INT);
            $insert->bindValue(':student', $studentId, PDO::PARAM_INT);
            $insert->bindValue(':created', $timestamp);
            $insert->bindValue(':updated', $timestamp);

            if (!$insert->execute()) {
                $connection->rollBack();

                return false;
            }

            $connection->commit();

            return true;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            return false;
        }
    }

    public static function clearAssignments(int $yearId, int $positionId): bool
    {
        if ($yearId <= 0 || $positionId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            'DELETE FROM siswa_jabatan_akademik WHERE tahun_ajaran_id = :year AND jabatan_akademik_id = :position'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);
        $statement->bindValue(':position', $positionId, PDO::PARAM_INT);

        return $statement->execute();
    }

    public static function clearAllAssignments(int $positionId): bool
    {
        if ($positionId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare(
            'DELETE FROM siswa_jabatan_akademik WHERE jabatan_akademik_id = :position'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':position', $positionId, PDO::PARAM_INT);

        return $statement->execute();
    }
}
