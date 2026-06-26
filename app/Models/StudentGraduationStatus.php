<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class StudentGraduationStatus extends Model
{
    protected static ?string $table = 'status_kelulusan_siswa';

    protected static bool $schemaEnsured = false;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        static::ensureSchema();

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
                'nomor_ijazah' => $row['nomor_ijazah'] ?? null,
                'jenis_kekhususan' => $row['jenis_kekhususan'] ?? null,
            ];
        }

        return $records;
    }

    public static function findForStudent(int $studentId, ?int $schoolYearId = null, ?int $classId = null): ?array
    {
        if ($studentId <= 0) {
            return null;
        }

        static::ensureSchema();

        $conditions = ['siswa_id = :siswa_id'];
        $params = [':siswa_id' => $studentId];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $conditions[] = 'tahun_ajaran_id = :tahun_ajaran_id';
            $params[':tahun_ajaran_id'] = $schoolYearId;
        }

        if ($classId !== null && $classId > 0) {
            $conditions[] = 'kelas_id = :kelas_id';
            $params[':kelas_id'] = $classId;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s ORDER BY updated_at DESC, id DESC LIMIT 1',
            static::table(),
            implode(' AND ', $conditions)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     */
    public static function saveMany(int $classId, int $schoolYearId, int $teacherId, array $payloads): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0) {
            return;
        }

        static::ensureSchema();

        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $insert = $connection->prepare(
                <<<SQL
INSERT INTO status_kelulusan_siswa (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    status,
    catatan,
    nomor_ijazah,
    jenis_kekhususan,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :status,
    :catatan,
    :nomor_ijazah,
    :jenis_kekhususan,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    guru_id = VALUES(guru_id),
    status = VALUES(status),
    catatan = VALUES(catatan),
    nomor_ijazah = VALUES(nomor_ijazah),
    jenis_kekhususan = VALUES(jenis_kekhususan),
    updated_at = VALUES(updated_at)
SQL
            );

            if ($insert === false) {
                throw new \RuntimeException('Gagal menyiapkan pernyataan status kelulusan.');
            }

            $delete = $connection->prepare(
                <<<SQL
DELETE FROM status_kelulusan_siswa
WHERE tahun_ajaran_id = :tahun_ajaran_id
  AND kelas_id = :kelas_id
  AND siswa_id = :siswa_id
SQL
            );

            if ($delete === false) {
                throw new \RuntimeException('Gagal menyiapkan penghapusan status kelulusan.');
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
                if (!in_array($status, ['lulus', 'tidak_lulus'], true)) {
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

                $diplomaNumber = $status === 'lulus' ? ($data['nomor_ijazah'] ?? null) : null;
                if (is_string($diplomaNumber)) {
                    $diplomaNumber = trim($diplomaNumber);
                } else {
                    $diplomaNumber = null;
                }

                $specializationType = $status === 'lulus' ? ($data['jenis_kekhususan'] ?? null) : null;
                if (is_string($specializationType)) {
                    $specializationType = trim($specializationType);
                } else {
                    $specializationType = null;
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
                if ($diplomaNumber !== null && $diplomaNumber !== '') {
                    $insert->bindValue(':nomor_ijazah', $diplomaNumber, PDO::PARAM_STR);
                } else {
                    $insert->bindValue(':nomor_ijazah', null, PDO::PARAM_NULL);
                }
                if ($specializationType !== null && $specializationType !== '') {
                    $insert->bindValue(':jenis_kekhususan', $specializationType, PDO::PARAM_STR);
                } else {
                    $insert->bindValue(':jenis_kekhususan', null, PDO::PARAM_NULL);
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

    private static function ensureSchema(): void
    {
        if (static::$schemaEnsured) {
            return;
        }

        $connection = static::connection();

        $columns = [
            'nomor_ijazah' => 'ALTER TABLE status_kelulusan_siswa ADD COLUMN nomor_ijazah VARCHAR(100) NULL AFTER catatan',
            'jenis_kekhususan' => 'ALTER TABLE status_kelulusan_siswa ADD COLUMN jenis_kekhususan VARCHAR(100) NULL AFTER nomor_ijazah',
        ];

        foreach ($columns as $column => $alterSql) {
            $statement = $connection->query('SHOW COLUMNS FROM status_kelulusan_siswa LIKE ' . $connection->quote($column));

            if ($statement !== false && $statement->fetch(PDO::FETCH_ASSOC) !== false) {
                continue;
            }

            $connection->exec($alterSql);
        }

        static::$schemaEnsured = true;
    }
}
