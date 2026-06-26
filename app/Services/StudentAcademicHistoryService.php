<?php

namespace App\Services;

use Core\Database;
use PDO;

class StudentAcademicHistoryService
{
    /**
     * @param array<int, array<string, mixed>> $students
     *
     * @return array{
     *     promotions: array<int, array<int, array<string, mixed>>>,
     *     graduations: array<int, array<int, array<string, mixed>>>,
     *     achievements: array<int, array<int, array<string, mixed>>>,
     *     extracurriculars: array<int, array<int, array<string, mixed>>>,
     *     attendance: array<int, array<int, array<string, mixed>>>,
     *     attitudes: array<int, array<int, array<string, mixed>>>,
     *     notes: array<int, array<int, array<string, mixed>>>,
     *     prakerin: array<int, array<int, array<string, mixed>>>
     * }
     */
    public static function collect(array $students): array
    {
        $studentIds = array_values(array_unique(array_filter(
            array_map(static function (array $student): int {
                return isset($student['id']) ? (int) $student['id'] : 0;
            }, $students),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($studentIds)) {
            return [
                'promotions' => [],
                'graduations' => [],
                'achievements' => [],
                'extracurriculars' => [],
                'attendance' => [],
                'attitudes' => [],
                'notes' => [],
                'prakerin' => [],
                'subjects' => [],
            ];
        }

        return [
            'promotions' => self::fetchPromotions($studentIds),
            'graduations' => self::fetchGraduations($studentIds),
            'achievements' => self::fetchAchievements($studentIds),
            'extracurriculars' => self::fetchExtracurriculars($studentIds),
            'attendance' => self::fetchAttendance($studentIds),
            'attitudes' => self::fetchAttitudes($studentIds),
            'notes' => self::fetchNotes($studentIds),
            'prakerin' => self::fetchPrakerin($studentIds),
            'subjects' => self::fetchSubjectScores($studentIds),
        ];
    }

    /**
     * @param array<int> $ids
     *
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private static function buildPlaceholders(array $ids): array
    {
        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id) {
            $placeholder = ':student_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        return [$placeholders, $params];
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchPromotions(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    snk.*,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif,
    ta.tanggal_mulai,
    ta.tanggal_selesai,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    g.nama AS guru_nama
FROM status_naik_kelas snk
LEFT JOIN tahun_ajaran ta ON ta.id = snk.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = snk.kelas_id
LEFT JOIN guru g ON g.id = snk.guru_id
WHERE snk.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, snk.created_at) ASC, k.tingkat ASC, k.nama ASC, snk.created_at ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null,
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'class_level' => isset($row['kelas_tingkat']) ? (int) $row['kelas_tingkat'] : null,
                'status' => $row['status'] ?? null,
                'note' => $row['catatan'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchGraduations(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    sks.*,
    ta.nama AS tahun_ajaran_nama,
    ta.tanggal_mulai,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    g.nama AS guru_nama
FROM status_kelulusan_siswa sks
LEFT JOIN tahun_ajaran ta ON ta.id = sks.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = sks.kelas_id
LEFT JOIN guru g ON g.id = sks.guru_id
WHERE sks.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, sks.created_at) ASC, k.tingkat ASC, k.nama ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_id' => isset($row['tahun_ajaran_id']) ? (int) $row['tahun_ajaran_id'] : null,
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'class_level' => isset($row['kelas_tingkat']) ? (int) $row['kelas_tingkat'] : null,
                'status' => $row['status'] ?? null,
                'note' => $row['catatan'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchAchievements(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    pa.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama
FROM prestasi_siswa pa
LEFT JOIN tahun_ajaran ta ON ta.id = pa.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = pa.kelas_id
LEFT JOIN guru g ON g.id = pa.guru_id
WHERE pa.siswa_id IN (%s)
ORDER BY pa.created_at DESC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'type' => $row['jenis'] ?? null,
                'description' => $row['keterangan'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchExtracurriculars(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    se.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama,
    e.nama AS ekstrakurikuler_nama
FROM siswa_ekstrakurikuler se
LEFT JOIN tahun_ajaran ta ON ta.id = se.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = se.kelas_id
LEFT JOIN guru g ON g.id = se.guru_id
LEFT JOIN ekstrakurikuler e ON e.id = se.ekstrakurikuler_id
WHERE se.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, se.created_at) ASC, e.nama ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'activity_name' => $row['ekstrakurikuler_nama'] ?? null,
                'scores' => [
                    'activity' => isset($row['nilai_keaktifan']) ? (float) $row['nilai_keaktifan'] : null,
                    'skill' => isset($row['nilai_kemampuan_teknis']) ? (float) $row['nilai_kemampuan_teknis'] : null,
                    'attendance' => isset($row['nilai_kehadiran']) ? (float) $row['nilai_kehadiran'] : null,
                    'final' => isset($row['nilai_akhir']) ? (float) $row['nilai_akhir'] : null,
                ],
                'predicate' => $row['predikat'] ?? null,
                'description' => $row['deskripsi'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchAttendance(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    ps.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama
FROM presensi_siswa ps
LEFT JOIN tahun_ajaran ta ON ta.id = ps.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = ps.kelas_id
LEFT JOIN guru g ON g.id = ps.guru_id
WHERE ps.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, ps.created_at) ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'sick' => isset($row['sakit']) ? (int) $row['sakit'] : 0,
                'permit' => isset($row['izin']) ? (int) $row['izin'] : 0,
                'truant' => isset($row['bolos']) ? (int) $row['bolos'] : 0,
                'absent' => isset($row['alpa']) ? (int) $row['alpa'] : 0,
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchAttitudes(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    ps.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama,
    ds1.nama AS selalu_1_nama,
    ds2.nama AS selalu_2_nama,
    ds3.nama AS meningkat_nama
FROM penilaian_sikap ps
LEFT JOIN tahun_ajaran ta ON ta.id = ps.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = ps.kelas_id
LEFT JOIN guru g ON g.id = ps.guru_id
LEFT JOIN data_sikap ds1 ON ds1.id = ps.data_sikap_selalu_1_id
LEFT JOIN data_sikap ds2 ON ds2.id = ps.data_sikap_selalu_2_id
LEFT JOIN data_sikap ds3 ON ds3.id = ps.data_sikap_meningkat_id
WHERE ps.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, ps.created_at) ASC, ps.jenis ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $always = array_values(array_filter([
                $row['selalu_1_nama'] ?? null,
                $row['selalu_2_nama'] ?? null,
            ], static fn ($value): bool => is_string($value) && trim($value) !== ''));

            $records[$studentId][] = [
                'type' => $row['jenis'] ?? '',
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'always' => $always,
                'improving' => $row['meningkat_nama'] ?? null,
                'note' => $row['catatan'] ?? null,
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchNotes(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    cw.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama
FROM catatan_walikelas cw
LEFT JOIN tahun_ajaran ta ON ta.id = cw.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = cw.kelas_id
LEFT JOIN guru g ON g.id = cw.guru_id
WHERE cw.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, cw.created_at) ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'note' => $row['catatan'] ?? null,
                'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchPrakerin(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    pp.*,
    ta.nama AS tahun_ajaran_nama,
    k.nama AS kelas_nama,
    g.nama AS guru_nama,
    tp.nama AS tempat_nama,
    pa.nilai_keaktifan,
    pa.nilai_jurnal,
    pa.nilai_laporan,
    pa.nilai_akhir,
    pa.predikat,
    pa.created_at AS assessment_created_at,
    pa.updated_at AS assessment_updated_at
FROM penempatan_prakerin pp
LEFT JOIN tahun_ajaran ta ON ta.id = pp.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = pp.kelas_id
LEFT JOIN guru g ON g.id = pp.guru_id
LEFT JOIN tempat_prakerin tp ON tp.id = pp.tempat_prakerin_id
LEFT JOIN penilaian_prakerin pa
    ON pa.siswa_id = pp.siswa_id
    AND pa.tahun_ajaran_id = pp.tahun_ajaran_id
WHERE pp.siswa_id IN (%s)
ORDER BY COALESCE(ta.tanggal_mulai, pp.created_at) ASC
SQL;

        $statement = Database::connection()->prepare(sprintf($sql, implode(', ', $placeholders)));

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

            $records[$studentId][] = [
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'teacher_name' => $row['guru_nama'] ?? null,
                'place_name' => $row['tempat_nama'] ?? null,
                'scores' => [
                    'activity' => isset($row['nilai_keaktifan']) ? (float) $row['nilai_keaktifan'] : null,
                    'journal' => isset($row['nilai_jurnal']) ? (float) $row['nilai_jurnal'] : null,
                    'report' => isset($row['nilai_laporan']) ? (float) $row['nilai_laporan'] : null,
                    'final' => isset($row['nilai_akhir']) ? (float) $row['nilai_akhir'] : null,
                    'predicate' => $row['predikat'] ?? null,
                ],
                'updated_at' => $row['assessment_updated_at'] ?? $row['updated_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param array<int> $studentIds
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function fetchSubjectScores(array $studentIds): array
    {
        [$placeholders, $params] = self::buildPlaceholders($studentIds);

        $sql = <<<SQL
SELECT
    pps.siswa_id,
    mp.tahun_ajaran_id,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif,
    ta.tanggal_mulai,
    mp.nama AS subject_name,
    mp.kode AS subject_code,
    mp.jenis AS subject_type,
    k.nama AS kelas_nama,
    k.kurikulum AS kurikulum,
    pps.nilai_akhir AS knowledge_score,
    pps.predikat AS knowledge_predicate,
    pps.deskripsi AS knowledge_description,
    pps.updated_at AS knowledge_updated_at,
    pks.nilai_akhir AS skill_score,
    pks.predikat AS skill_predicate,
    pks.deskripsi AS skill_description,
    pks.updated_at AS skill_updated_at,
    NULL AS kurmer_capaian,
    NULL AS kurmer_description,
    NULL AS kurmer_tindak_lanjut,
    NULL AS kurmer_score,
    NULL AS kurmer_tp_sources
FROM penilaian_pengetahuan_siswa pps
JOIN guru_mata_pelajaran gmp ON gmp.id = pps.guru_mata_pelajaran_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN siswa s ON s.id = pps.siswa_id
LEFT JOIN penilaian_keterampilan_siswa pks
    ON pks.guru_mata_pelajaran_id = gmp.id
    AND pks.siswa_id = pps.siswa_id
LEFT JOIN guru_mata_pelajaran_kelas gmpk
    ON gmpk.guru_mata_pelajaran_id = gmp.id
    AND gmpk.kelas_id = s.kelas_id
LEFT JOIN kelas k ON k.id = gmpk.kelas_id
WHERE pps.siswa_id IN (%s)
ORDER BY ta.tanggal_mulai ASC, mp.nama ASC
SQL;

        $connection = Database::connection();
        $rows = [];

        $statement = $connection->prepare(sprintf($sql, implode(', ', $placeholders)));
        if ($statement !== false) {
            foreach ($params as $placeholder => $value) {
                $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
            }

            if ($statement->execute()) {
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        $skillOnlySql = <<<SQL
SELECT
    pks.siswa_id,
    mp.tahun_ajaran_id,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif,
    ta.tanggal_mulai,
    mp.nama AS subject_name,
    mp.kode AS subject_code,
    mp.jenis AS subject_type,
    k.nama AS kelas_nama,
    k.kurikulum AS kurikulum,
    NULL AS knowledge_score,
    NULL AS knowledge_predicate,
    NULL AS knowledge_description,
    NULL AS knowledge_updated_at,
    pks.nilai_akhir AS skill_score,
    pks.predikat AS skill_predicate,
    pks.deskripsi AS skill_description,
    pks.updated_at AS skill_updated_at,
    NULL AS kurmer_capaian,
    NULL AS kurmer_description,
    NULL AS kurmer_tindak_lanjut,
    NULL AS kurmer_score,
    NULL AS kurmer_tp_sources
FROM penilaian_keterampilan_siswa pks
JOIN guru_mata_pelajaran gmp ON gmp.id = pks.guru_mata_pelajaran_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN siswa s ON s.id = pks.siswa_id
LEFT JOIN penilaian_pengetahuan_siswa pps
    ON pps.guru_mata_pelajaran_id = gmp.id
    AND pps.siswa_id = pks.siswa_id
LEFT JOIN guru_mata_pelajaran_kelas gmpk
    ON gmpk.guru_mata_pelajaran_id = gmp.id
    AND gmpk.kelas_id = s.kelas_id
LEFT JOIN kelas k ON k.id = gmpk.kelas_id
WHERE pks.siswa_id IN (%s)
  AND pps.id IS NULL
ORDER BY ta.tanggal_mulai ASC, mp.nama ASC
SQL;

        $skillStatement = $connection->prepare(sprintf($skillOnlySql, implode(', ', $placeholders)));
        if ($skillStatement !== false) {
            foreach ($params as $placeholder => $value) {
                $skillStatement->bindValue($placeholder, $value, PDO::PARAM_INT);
            }

            if ($skillStatement->execute()) {
                $skillRows = $skillStatement->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($skillRows) && !empty($skillRows)) {
                    $rows = array_merge($rows, $skillRows);
                }
            }
        }

        $kurmerSql = <<<SQL
SELECT
    pkms.siswa_id,
    mp.tahun_ajaran_id,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif,
    ta.tanggal_mulai,
    mp.nama AS subject_name,
    mp.kode AS subject_code,
    mp.jenis AS subject_type,
    COALESCE(k.nama, k2.nama) AS kelas_nama,
    LOWER(COALESCE(k.kurikulum, k2.kurikulum, 'kurmer')) AS kurikulum,
    NULL AS knowledge_score,
    NULL AS knowledge_predicate,
    NULL AS knowledge_description,
    NULL AS knowledge_updated_at,
    NULL AS skill_score,
    NULL AS skill_predicate,
    NULL AS skill_description,
    NULL AS skill_updated_at,
    pkms.capaian_akhir_enum AS kurmer_capaian,
    pkms.deskripsi_umum AS kurmer_description,
    pkms.tindak_lanjut AS kurmer_tindak_lanjut,
    pkms.nilai_opsional AS kurmer_score,
    pkms.sumber_tp AS kurmer_tp_sources
FROM penilaian_kurmer_mapel_siswa pkms
JOIN guru_mata_pelajaran gmp ON gmp.id = pkms.guru_mata_pelajaran_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = pkms.kelas_id
LEFT JOIN guru_mata_pelajaran_kelas gmpk ON gmpk.guru_mata_pelajaran_id = gmp.id
LEFT JOIN kelas k2 ON k2.id = gmpk.kelas_id
WHERE pkms.siswa_id IN (%s)
ORDER BY ta.tanggal_mulai ASC, mp.nama ASC
SQL;

        $kurmerStatement = $connection->prepare(sprintf($kurmerSql, implode(', ', $placeholders)));
        if ($kurmerStatement !== false) {
            foreach ($params as $placeholder => $value) {
                $kurmerStatement->bindValue($placeholder, $value, PDO::PARAM_INT);
            }

            if ($kurmerStatement->execute()) {
                $kurmerRows = $kurmerStatement->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($kurmerRows) && !empty($kurmerRows)) {
                    $rows = array_merge($rows, $kurmerRows);
                }
            }
        }

        return self::buildSubjectRecordMap($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function buildSubjectRecordMap(array $rows): array
    {
        $records = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);
            $schoolYearId = (int) ($row['tahun_ajaran_id'] ?? 0);
            if ($studentId <= 0 || $schoolYearId <= 0) {
                continue;
            }

            $records[$studentId][$schoolYearId] ??= [
                'school_year_id' => $schoolYearId,
                'school_year_name' => $row['tahun_ajaran_nama'] ?? null,
                'semester' => isset($row['semester_aktif']) ? (int) $row['semester_aktif'] : null,
                'sort_key' => $row['tanggal_mulai'] ?? null,
                'subjects' => [],
            ];

            $curriculumRaw = isset($row['kurikulum']) ? (string) $row['kurikulum'] : 'k13';
            $curriculum = strtolower($curriculumRaw) === 'kurmer' ? 'kurmer' : 'k13';
            $knowledgeScore = isset($row['knowledge_score']) ? (float) $row['knowledge_score'] : null;
            $skillScore = isset($row['skill_score']) ? (float) $row['skill_score'] : null;
            $scoreComponents = [];
            if ($knowledgeScore !== null) {
                $scoreComponents[] = $knowledgeScore;
            }
            if ($skillScore !== null) {
                $scoreComponents[] = $skillScore;
            }
            $average = null;
            if (!empty($scoreComponents)) {
                $average = array_sum($scoreComponents) / count($scoreComponents);
            }

            $records[$studentId][$schoolYearId]['subjects'][] = [
                'name' => $row['subject_name'] ?? '-',
                'code' => $row['subject_code'] ?? null,
                'type' => $row['subject_type'] ?? null,
                'class_name' => $row['kelas_nama'] ?? null,
                'curriculum' => $curriculum,
                'knowledge_score' => $knowledgeScore,
                'knowledge_predicate' => $row['knowledge_predicate'] ?? null,
                'skill_score' => $skillScore,
                'skill_predicate' => $row['skill_predicate'] ?? null,
                'average_score' => $curriculum === 'kurmer' ? null : $average,
                'kurmer_capaian' => $row['kurmer_capaian'] ?? null,
                'kurmer_description' => $row['kurmer_description'] ?? null,
                'kurmer_tindak_lanjut' => $row['kurmer_tindak_lanjut'] ?? null,
                'kurmer_score' => isset($row['kurmer_score']) ? (float) $row['kurmer_score'] : null,
                'kurmer_tp_sources' => $row['kurmer_tp_sources'] ?? null,
            ];
        }

        return $records;
    }
}
