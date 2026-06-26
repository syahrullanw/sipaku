<?php

namespace App\Models;

use Core\Model;
use PDO;

class Classroom extends Model
{
    protected static ?string $table = 'kelas';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithRelations(?int $schoolYearId = null): array
    {
        $wheres = [];
        $params = [];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $wheres[] = 'k.tahun_ajaran_id = :year_id';
            $params[':year_id'] = $schoolYearId;
        }

        $whereClause = '';
        if (!empty($wheres)) {
            $whereClause = 'WHERE ' . implode(' AND ', $wheres);
        }

        $sql = <<<SQL
SELECT k.*, ta.nama AS tahun_ajaran_nama, j.kode AS jurusan_kode, j.nama AS jurusan_nama, g.nama AS wali_kelas_nama
FROM kelas k
LEFT JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN guru g ON g.id = k.wali_kelas_id
$whereClause
ORDER BY ta.tanggal_mulai DESC, k.tingkat ASC, k.nama ASC
SQL;

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

        return $rows === false ? [] : $rows;
    }

    public static function options(?int $schoolYearId = null, ?int $includeId = null): array
    {
        $connection = static::connection();

        $baseSql = <<<SQL
SELECT k.*, ta.nama AS tahun_ajaran_nama, j.kode AS jurusan_kode, j.nama AS jurusan_nama
FROM kelas k
LEFT JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
%s
ORDER BY ta.tanggal_mulai DESC, k.tingkat ASC, k.nama ASC
SQL;

        $where = '';
        if ($schoolYearId !== null) {
            $where = 'WHERE k.tahun_ajaran_id = :year';
        }

        $sql = sprintf($baseSql, $where);
        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        if ($schoolYearId !== null) {
            $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            $rows = [];
        }

        if ($includeId !== null) {
            $exists = array_filter($rows, static fn ($row) => (int) $row['id'] === $includeId);
            if (empty($exists)) {
                $statement = $connection->prepare(<<<SQL
SELECT k.*, ta.nama AS tahun_ajaran_nama, j.kode AS jurusan_kode, j.nama AS jurusan_nama
FROM kelas k
LEFT JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
WHERE k.id = :id
LIMIT 1
SQL);

                if ($statement !== false) {
                    $statement->bindValue(':id', $includeId, PDO::PARAM_INT);
                    $statement->execute();
                    $record = $statement->fetch(PDO::FETCH_ASSOC);
                    if ($record !== false) {
                        $rows[] = $record;
                    }
                }
            }
        }

        $options = [];

        foreach ($rows as $row) {
            $label = sprintf('%s - %s (%s)', $row['tingkat'], $row['nama'], $row['tahun_ajaran_nama'] ?? '-');
            $options[$row['id']] = $label;
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byYear(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $sql = 'SELECT * FROM kelas WHERE tahun_ajaran_id = :year ORDER BY tingkat ASC, nama ASC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function homeroomClassesForTeacher(int $teacherId, ?int $schoolYearId = null): array
    {
        if ($teacherId <= 0) {
            return [];
        }

        $connection = static::connection();
        $where = 'WHERE k.wali_kelas_id = :teacher_id';

        if ($schoolYearId !== null) {
            $where .= ' AND k.tahun_ajaran_id = :tahun_ajaran_id';
        }

        $sql = <<<SQL
SELECT
    k.*,
    ta.nama AS tahun_ajaran_nama,
    ta.status AS tahun_ajaran_status,
    j.kode AS jurusan_kode,
    j.nama AS jurusan_nama
FROM kelas k
LEFT JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
{$where}
ORDER BY ta.tanggal_mulai DESC, k.tingkat ASC, k.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);

        if ($schoolYearId !== null) {
            $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function teacherHasHomeroom(int $teacherId, ?int $schoolYearId = null): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        $connection = static::connection();

        $where = 'WHERE k.wali_kelas_id = :teacher_id';

        if ($schoolYearId !== null) {
            $where .= ' AND k.tahun_ajaran_id = :tahun_ajaran_id';
        }

        $sql = <<<SQL
SELECT 1
FROM kelas k
{$where}
LIMIT 1
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);

        if ($schoolYearId !== null) {
            $statement->bindValue(':tahun_ajaran_id', $schoolYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return false;
        }

        return $statement->fetchColumn() !== false;
    }

    public static function findWithRelations(int $classId): ?array
    {
        if ($classId <= 0) {
            return null;
        }

        $sql = <<<SQL
SELECT
    k.*,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester,
    ta.status AS tahun_ajaran_status,
    j.kode AS jurusan_kode,
    j.nama AS jurusan_nama,
    g.nama AS wali_kelas_nama,
    g.nip AS wali_kelas_nip
FROM kelas k
LEFT JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN guru g ON g.id = k.wali_kelas_id
WHERE k.id = :id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $classId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forSchoolYear(int $schoolYearId, ?int $majorId = null): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $connection = static::connection();
        $wheres = ['k.tahun_ajaran_id = :school_year'];
        $params = [':school_year' => $schoolYearId];

        if ($majorId !== null && $majorId > 0) {
            $wheres[] = 'k.jurusan_id = :major';
            $params[':major'] = $majorId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $wheres);

        $sql = <<<SQL
SELECT k.*, j.kode AS jurusan_kode, j.nama AS jurusan_nama
FROM kelas k
LEFT JOIN jurusan j ON j.id = k.jurusan_id
{$whereClause}
ORDER BY k.tingkat ASC, k.nama ASC
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
}
