<?php

namespace App\Models;

use Core\Model;
use PDO;
use Throwable;

class AttitudeScore extends Model
{
    protected static ?string $table = 'penilaian_sikap';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClassAndType(int $classId, string $type): array
    {
        if ($classId <= 0 || !array_key_exists($type, Attitude::TYPES)) {
            return [];
        }

        $statement = static::connection()->prepare(
            <<<SQL
SELECT
    ps.*,
    ds1.nama AS data_sikap_selalu_1_nama,
    ds1.deskripsi AS data_sikap_selalu_1_deskripsi,
    ds1.kode AS data_sikap_selalu_1_kode,
    ds2.nama AS data_sikap_selalu_2_nama,
    ds2.deskripsi AS data_sikap_selalu_2_deskripsi,
    ds2.kode AS data_sikap_selalu_2_kode,
    ds3.nama AS data_sikap_meningkat_nama,
    ds3.deskripsi AS data_sikap_meningkat_deskripsi,
    ds3.kode AS data_sikap_meningkat_kode
FROM penilaian_sikap ps
LEFT JOIN data_sikap ds1 ON ds1.id = ps.data_sikap_selalu_1_id
LEFT JOIN data_sikap ds2 ON ds2.id = ps.data_sikap_selalu_2_id
LEFT JOIN data_sikap ds3 ON ds3.id = ps.data_sikap_meningkat_id
WHERE ps.kelas_id = :kelas_id
  AND ps.jenis = :jenis
SQL
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':jenis', $type);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $scores = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $row['data_sikap_selalu_1_id'] = isset($row['data_sikap_selalu_1_id']) ? (int) $row['data_sikap_selalu_1_id'] : null;
            $row['data_sikap_selalu_2_id'] = isset($row['data_sikap_selalu_2_id']) ? (int) $row['data_sikap_selalu_2_id'] : null;
            $row['data_sikap_meningkat_id'] = isset($row['data_sikap_meningkat_id']) ? (int) $row['data_sikap_meningkat_id'] : null;

            if ($row['data_sikap_selalu_1_id'] === 0) {
                $row['data_sikap_selalu_1_id'] = null;
            }
            if ($row['data_sikap_selalu_2_id'] === 0) {
                $row['data_sikap_selalu_2_id'] = null;
            }
            if ($row['data_sikap_meningkat_id'] === 0) {
                $row['data_sikap_meningkat_id'] = null;
            }

            $scores[$studentId] = $row;
        }

        return $scores;
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     */
    public static function saveMany(int $classId, int $schoolYearId, int $teacherId, string $type, array $payloads): void
    {
        if ($classId <= 0 || $schoolYearId <= 0 || $teacherId <= 0) {
            return;
        }

        if (!array_key_exists($type, Attitude::TYPES)) {
            return;
        }

        if (empty($payloads)) {
            return;
        }

        $connection = static::connection();

        $connection->beginTransaction();

        try {
            $insert = $connection->prepare(
                <<<SQL
INSERT INTO penilaian_sikap (
    tahun_ajaran_id,
    kelas_id,
    siswa_id,
    guru_id,
    jenis,
    data_sikap_selalu_1_id,
    data_sikap_selalu_2_id,
    data_sikap_meningkat_id,
    catatan,
    created_at,
    updated_at
) VALUES (
    :tahun_ajaran_id,
    :kelas_id,
    :siswa_id,
    :guru_id,
    :jenis,
    :data_sikap_selalu_1_id,
    :data_sikap_selalu_2_id,
    :data_sikap_meningkat_id,
    :catatan,
    :created_at,
    :updated_at
)
ON DUPLICATE KEY UPDATE
    data_sikap_selalu_1_id = VALUES(data_sikap_selalu_1_id),
    data_sikap_selalu_2_id = VALUES(data_sikap_selalu_2_id),
    data_sikap_meningkat_id = VALUES(data_sikap_meningkat_id),
    catatan = VALUES(catatan),
    guru_id = VALUES(guru_id),
    updated_at = VALUES(updated_at)
SQL
            );

            $delete = $connection->prepare(
                <<<SQL
DELETE FROM penilaian_sikap
WHERE tahun_ajaran_id = :tahun_ajaran_id
  AND kelas_id = :kelas_id
  AND siswa_id = :siswa_id
  AND jenis = :jenis
SQL
            );

            if ($insert === false || $delete === false) {
                throw new \RuntimeException('Gagal menyiapkan pernyataan penilaian sikap.');
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

                $always = isset($data['selalu']) && is_array($data['selalu']) ? $data['selalu'] : [];
                $always = array_values(array_filter(array_map(static fn ($value) => (int) $value, $always), static fn ($value) => $value > 0));
                $always = array_values(array_unique($always));

                $alwaysFirst = $always[0] ?? null;
                $alwaysSecond = $always[1] ?? null;

                $improving = isset($data['meningkat']) ? (int) $data['meningkat'] : null;
                if ($improving !== null && $improving <= 0) {
                    $improving = null;
                }

                $note = isset($data['catatan']) ? trim((string) $data['catatan']) : '';
                $note = $note === '' ? null : $note;

                if ($alwaysFirst === null && $alwaysSecond === null && $improving === null && $note === null) {
                    if ($delete !== false) {
                        $delete->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                        $delete->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                        $delete->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                        $delete->bindValue(':jenis', $type);
                        $delete->execute();
                        $delete->closeCursor();
                    }
                    continue;
                }

                if ($insert === false) {
                    continue;
                }

                $insert->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
                $insert->bindValue(':kelas_id', $classId, PDO::PARAM_INT);
                $insert->bindValue(':siswa_id', $studentId, PDO::PARAM_INT);
                $insert->bindValue(':guru_id', $teacherId, PDO::PARAM_INT);
                $insert->bindValue(':jenis', $type);

                if ($alwaysFirst === null) {
                    $insert->bindValue(':data_sikap_selalu_1_id', null, PDO::PARAM_NULL);
                } else {
                    $insert->bindValue(':data_sikap_selalu_1_id', $alwaysFirst, PDO::PARAM_INT);
                }

                if ($alwaysSecond === null) {
                    $insert->bindValue(':data_sikap_selalu_2_id', null, PDO::PARAM_NULL);
                } else {
                    $insert->bindValue(':data_sikap_selalu_2_id', $alwaysSecond, PDO::PARAM_INT);
                }

                if ($improving === null) {
                    $insert->bindValue(':data_sikap_meningkat_id', null, PDO::PARAM_NULL);
                } else {
                    $insert->bindValue(':data_sikap_meningkat_id', $improving, PDO::PARAM_INT);
                }

                if ($note === null) {
                    $insert->bindValue(':catatan', null, PDO::PARAM_NULL);
                } else {
                    $insert->bindValue(':catatan', $note);
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
