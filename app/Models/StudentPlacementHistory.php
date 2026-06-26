<?php

namespace App\Models;

use Core\Model;
use PDO;

class StudentPlacementHistory extends Model
{
    protected static ?string $table = 'siswa_penempatan';

    public static function upsert(int $studentId, int $classId, int $schoolYearId): bool
    {
        if ($studentId <= 0 || $classId <= 0 || $schoolYearId <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $sql = <<<SQL
INSERT INTO siswa_penempatan (siswa_id, kelas_id, tahun_ajaran_id, created_at, updated_at)
VALUES (:student_id, :class_id, :school_year_id, :created_at, :updated_at)
ON DUPLICATE KEY UPDATE
    kelas_id = VALUES(kelas_id),
    updated_at = VALUES(updated_at)
SQL;

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);

        return $statement->execute();
    }

    /**
     * @param array<int> $studentIds
     */
    public static function upsertMany(array $studentIds, int $classId, int $schoolYearId): void
    {
        foreach ($studentIds as $studentId) {
            self::upsert((int) $studentId, $classId, $schoolYearId);
        }
    }

    public static function forStudentYear(int $studentId, int $schoolYearId): ?array
    {
        if ($studentId <= 0 || $schoolYearId <= 0) {
            return null;
        }

        $sql = <<<SQL
SELECT
    sp.*,
    k.tingkat AS kelas_tingkat,
    k.nama AS kelas_nama,
    k.jurusan_id,
    k.kurikulum,
    k.wali_kelas_id,
    j.nama AS jurusan_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester,
    g.nama AS wali_kelas_nama,
    g.nip AS wali_kelas_nip
FROM siswa_penempatan sp
JOIN kelas k ON k.id = sp.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN tahun_ajaran ta ON ta.id = sp.tahun_ajaran_id
LEFT JOIN guru g ON g.id = k.wali_kelas_id
WHERE sp.siswa_id = :student_id
  AND sp.tahun_ajaran_id = :school_year_id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row !== false) {
            return self::normalizePlacement($row);
        }

        return self::inferForStudentYear($studentId, $schoolYearId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function studentsByClassYear(int $classId, int $schoolYearId): array
    {
        if ($classId <= 0 || $schoolYearId <= 0) {
            return [];
        }

        $rows = self::studentsByClassYearFromHistory($classId, $schoolYearId);
        $known = [];
        foreach ($rows as $row) {
            $studentId = (int) ($row['id'] ?? 0);
            if ($studentId > 0) {
                $known[$studentId] = true;
            }
        }

        foreach (self::inferStudentsByClassYear($classId, $schoolYearId) as $row) {
            $studentId = (int) ($row['id'] ?? 0);
            if ($studentId <= 0 || isset($known[$studentId])) {
                continue;
            }

            $rows[] = $row;
            $known[$studentId] = true;
        }

        usort($rows, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nama'] ?? ''), (string) ($b['nama'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function studentsByClassYearFromHistory(int $classId, int $schoolYearId): array
    {
        $sql = <<<SQL
SELECT
    s.*,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    ta.nama AS tahun_ajaran_nama
FROM siswa_penempatan sp
JOIN siswa s ON s.id = sp.siswa_id
LEFT JOIN kelas k ON k.id = sp.kelas_id
LEFT JOIN tahun_ajaran ta ON ta.id = sp.tahun_ajaran_id
WHERE sp.kelas_id = :class_id
  AND sp.tahun_ajaran_id = :school_year_id
ORDER BY s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    private static function inferForStudentYear(int $studentId, int $schoolYearId): ?array
    {
        $sql = <<<SQL
SELECT inferred.kelas_id, COUNT(*) AS weight
FROM (
    SELECT kelas_id FROM penilaian_sikap WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM presensi_siswa WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM catatan_walikelas WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM status_naik_kelas WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM status_kelulusan_siswa WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM prestasi_siswa WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM siswa_ekstrakurikuler WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL SELECT kelas_id FROM penempatan_prakerin WHERE siswa_id = :student_id AND tahun_ajaran_id = :school_year_id
    UNION ALL
    SELECT gmpk.kelas_id
    FROM penilaian_pengetahuan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    WHERE p.siswa_id = :student_id AND mp.tahun_ajaran_id = :school_year_id
    UNION ALL
    SELECT gmpk.kelas_id
    FROM penilaian_keterampilan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    WHERE p.siswa_id = :student_id AND mp.tahun_ajaran_id = :school_year_id
    UNION ALL
    SELECT kelas_id FROM penilaian_kurmer_mapel_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    WHERE p.siswa_id = :student_id AND mp.tahun_ajaran_id = :school_year_id
) inferred
WHERE inferred.kelas_id IS NOT NULL
GROUP BY inferred.kelas_id
ORDER BY weight DESC
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $classId = (int) ($row['kelas_id'] ?? 0);
        if ($classId <= 0) {
            return null;
        }

        $class = Classroom::findWithRelations($classId);
        if ($class === null) {
            return null;
        }

        return self::normalizePlacement([
            'siswa_id' => $studentId,
            'kelas_id' => $classId,
            'tahun_ajaran_id' => $schoolYearId,
            'kelas_tingkat' => $class['tingkat'] ?? null,
            'kelas_nama' => $class['nama'] ?? null,
            'jurusan_id' => $class['jurusan_id'] ?? null,
            'kurikulum' => $class['kurikulum'] ?? null,
            'wali_kelas_id' => $class['wali_kelas_id'] ?? null,
            'jurusan_nama' => $class['jurusan_nama'] ?? null,
            'tahun_ajaran_nama' => $class['tahun_ajaran_nama'] ?? null,
            'tahun_ajaran_semester' => $class['tahun_ajaran_semester'] ?? null,
            'wali_kelas_nama' => $class['wali_kelas_nama'] ?? null,
            'wali_kelas_nip' => $class['wali_kelas_nip'] ?? null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function inferStudentsByClassYear(int $classId, int $schoolYearId): array
    {
        $sql = <<<SQL
SELECT DISTINCT s.*, k.nama AS kelas_nama, k.tingkat AS kelas_tingkat, ta.nama AS tahun_ajaran_nama
FROM siswa s
JOIN (
    SELECT siswa_id FROM penilaian_sikap WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM presensi_siswa WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM catatan_walikelas WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM status_naik_kelas WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM status_kelulusan_siswa WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM prestasi_siswa WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM siswa_ekstrakurikuler WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION SELECT siswa_id FROM penempatan_prakerin WHERE kelas_id = :class_id AND tahun_ajaran_id = :school_year_id
    UNION
    SELECT p.siswa_id
    FROM penilaian_pengetahuan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    WHERE gmpk.kelas_id = :class_id AND mp.tahun_ajaran_id = :school_year_id
    UNION
    SELECT p.siswa_id
    FROM penilaian_keterampilan_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    JOIN (
        SELECT guru_mata_pelajaran_id
        FROM guru_mata_pelajaran_kelas
        GROUP BY guru_mata_pelajaran_id
        HAVING COUNT(*) = 1
    ) unique_gmpk ON unique_gmpk.guru_mata_pelajaran_id = gmp.id
    JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
    WHERE gmpk.kelas_id = :class_id AND mp.tahun_ajaran_id = :school_year_id
    UNION
    SELECT p.siswa_id
    FROM penilaian_kurmer_mapel_siswa p
    JOIN guru_mata_pelajaran gmp ON gmp.id = p.guru_mata_pelajaran_id
    JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
    WHERE p.kelas_id = :class_id AND mp.tahun_ajaran_id = :school_year_id
) inferred ON inferred.siswa_id = s.id
LEFT JOIN kelas k ON k.id = :class_id
LEFT JOIN tahun_ajaran ta ON ta.id = :school_year_id
ORDER BY s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizePlacement(array $row): array
    {
        return [
            'student_id' => isset($row['siswa_id']) ? (int) $row['siswa_id'] : null,
            'class_id' => isset($row['kelas_id']) ? (int) $row['kelas_id'] : null,
            'school_year_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null,
            'class_level' => isset($row['kelas_tingkat']) ? (int) $row['kelas_tingkat'] : null,
            'class_name' => $row['kelas_nama'] ?? null,
            'major_id' => isset($row['jurusan_id']) ? (int) $row['jurusan_id'] : null,
            'major_name' => $row['jurusan_nama'] ?? null,
            'curriculum' => $row['kurikulum'] ?? null,
            'homeroom_teacher_id' => isset($row['wali_kelas_id']) ? (int) $row['wali_kelas_id'] : null,
            'homeroom_teacher_name' => $row['wali_kelas_nama'] ?? null,
            'homeroom_teacher_nip' => $row['wali_kelas_nip'] ?? null,
            'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
            'school_year_semester' => isset($row['tahun_ajaran_semester']) ? (int) $row['tahun_ajaran_semester'] : null,
        ];
    }
}
