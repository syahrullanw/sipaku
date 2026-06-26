<?php

namespace App\Models;

use Core\Model;
use PDO;

class LessonSchedule extends Model
{
    protected static ?string $table = 'jadwal_pelajaran';

    private const DAY_ORDER = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

    /**
     * @return array<string, string>
     */
    public static function dayOptions(): array
    {
        return [
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'sabtu' => 'Sabtu',
            'minggu' => 'Minggu',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listWithRelations(?int $schoolYearId = null): array
    {
        $connection = static::connection();
        $params = [];

        $whereClause = '';

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $whereClause = 'WHERE jp.tahun_ajaran_id = :tahun_ajaran_id';
            $params[':tahun_ajaran_id'] = $schoolYearId;
        }

        $dayOrder = implode("', '", self::DAY_ORDER);

        $sql = <<<SQL
SELECT
    jp.*,
    g.nama AS guru_nama,
    g.nip AS guru_nip,
    mp.kode AS mata_pelajaran_kode,
    mp.nama AS mata_pelajaran_nama,
    mp.jenis AS mata_pelajaran_jenis,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    j.nama AS jurusan_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester
FROM jadwal_pelajaran jp
JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
JOIN guru g ON g.id = gmp.guru_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN kelas k ON k.id = jp.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
JOIN tahun_ajaran ta ON ta.id = jp.tahun_ajaran_id
{$whereClause}
ORDER BY FIELD(jp.hari, '{$dayOrder}'), jp.waktu_mulai ASC, g.nama ASC
SQL;

        $statement = $connection->prepare($sql);

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

        return $rows === false ? [] : $rows;
    }

    public static function findWithRelations(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $dayOrder = implode("', '", self::DAY_ORDER);

        $sql = <<<SQL
SELECT
    jp.*,
    g.nama AS guru_nama,
    g.nip AS guru_nip,
    gmp.guru_id AS guru_id,
    gmp.mata_pelajaran_id AS mata_pelajaran_id,
    mp.kode AS mata_pelajaran_kode,
    mp.nama AS mata_pelajaran_nama,
    mp.jenis AS mata_pelajaran_jenis,
    mp.tahun_ajaran_id AS mata_pelajaran_tahun_ajaran_id,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    k.jurusan_id AS kelas_jurusan_id,
    j.nama AS jurusan_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester
FROM jadwal_pelajaran jp
JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
JOIN guru g ON g.id = gmp.guru_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN kelas k ON k.id = jp.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
JOIN tahun_ajaran ta ON ta.id = jp.tahun_ajaran_id
WHERE jp.id = :id
ORDER BY FIELD(jp.hari, '{$dayOrder}'), jp.waktu_mulai ASC
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forTeacher(int $teacherId, ?int $schoolYearId = null): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $connection = static::connection();
        $params = [':teacher_id' => $teacherId];

        $whereParts = ['gmp.guru_id = :teacher_id'];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $whereParts[] = 'jp.tahun_ajaran_id = :tahun_ajaran_id';
            $params[':tahun_ajaran_id'] = $schoolYearId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        $dayOrder = implode("', '", self::DAY_ORDER);

        $sql = <<<SQL
SELECT
    jp.*,
    mp.nama AS mata_pelajaran_nama,
    mp.kode AS mata_pelajaran_kode,
    mp.jenis AS mata_pelajaran_jenis,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    j.nama AS jurusan_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester
FROM jadwal_pelajaran jp
JOIN guru_mata_pelajaran gmp ON gmp.id = jp.guru_mata_pelajaran_id
JOIN mata_pelajaran mp ON mp.id = gmp.mata_pelajaran_id
JOIN kelas k ON k.id = jp.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
JOIN tahun_ajaran ta ON ta.id = jp.tahun_ajaran_id
{$whereClause}
ORDER BY FIELD(jp.hari, '{$dayOrder}'), jp.waktu_mulai ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $paramType = $placeholder === ':teacher_id' ? PDO::PARAM_INT : PDO::PARAM_INT;
            $statement->bindValue($placeholder, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
