<?php

namespace App\Models;

use Core\Model;
use PDO;

class Student extends Model
{
    protected static ?string $table = 'siswa';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithRelations(?array $classIds = null, ?string $status = null, ?string $keyword = null): array
    {
        $connection = static::connection();
        $params = [];
        $clauses = [];

        if ($classIds !== null) {
            $filteredIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $classIds), static fn (int $id) => $id > 0)));

            if (empty($filteredIds)) {
                return [];
            }

            $placeholders = [];
            foreach ($filteredIds as $index => $classId) {
                $placeholder = ':class_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $classId;
            }

            $clauses[] = 's.kelas_id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($status !== null) {
            $normalized = strtolower(trim($status));
            if ($normalized !== '') {
                $clauses[] = 's.status = :status';
                $params[':status'] = $normalized;
            }
        }

        if ($keyword !== null) {
            $normalizedKeyword = trim($keyword);
            if ($normalizedKeyword !== '') {
                $clauses[] = '(s.nama LIKE :keyword OR s.nisn LIKE :keyword OR s.nipd LIKE :keyword OR s.nik LIKE :keyword OR k.nama LIKE :keyword)';
                $params[':keyword'] = '%' . $normalizedKeyword . '%';
            }
        }

        $whereClause = '';
        if (!empty($clauses)) {
            $whereClause = 'WHERE ' . implode(' AND ', $clauses);
        }

        $sql = <<<SQL
SELECT s.*, ta.nama AS tahun_ajaran_nama, k.nama AS kelas_nama, j.nama AS jurusan_nama
FROM siswa s
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
%s
ORDER BY s.nama ASC
SQL;

        $statement = $connection->prepare(sprintf($sql, $whereClause));

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $parameterType = PDO::PARAM_INT;
            if ($placeholder === ':status' || $placeholder === ':keyword') {
                $parameterType = PDO::PARAM_STR;
            }
            $statement->bindValue($placeholder, $value, $parameterType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forCbt(?int $classId = null, ?int $schoolYearId = null, string $keyword = ''): array
    {
        $connection = static::connection();
        $wheres = [];
        $params = [];

        if ($classId !== null && $classId > 0) {
            $wheres[] = 's.kelas_id = :class_id';
            $params[':class_id'] = $classId;
        }

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $wheres[] = 's.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $wheres[] = '(s.nama LIKE :keyword OR s.nisn LIKE :keyword OR s.nipd LIKE :keyword OR k.nama LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereClause = '';
        if (!empty($wheres)) {
            $whereClause = 'WHERE ' . implode(' AND ', $wheres);
        }

        $sql = <<<SQL
SELECT
    s.id,
    s.nama,
    s.nisn,
    s.nipd,
    s.foto_path,
    s.kelas_id,
    s.tahun_ajaran_id,
    s.status,
    s.status_dapodik,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    ta.nama AS tahun_ajaran_nama,
    u.username AS account_username
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
LEFT JOIN users u ON u.student_id = s.id
%s
ORDER BY
    COALESCE(k.tingkat, 0) ASC,
    k.nama ASC,
    s.nama ASC
SQL;

        $statement = $connection->prepare(sprintf($sql, $whereClause));

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            if ($placeholder === ':keyword') {
                $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
            } else {
                $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
            }
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latest(int $limit = 5): array
    {
        $sql = <<<SQL
SELECT s.*, ta.nama AS tahun_ajaran_nama, k.nama AS kelas_nama
FROM siswa s
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = s.kelas_id
ORDER BY s.created_at DESC
LIMIT :limit
SQL;

        $statement = static::connection()->prepare($sql);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<string, mixed>|null $student
     */
    public static function hasActiveStatus(?array $student): bool
    {
        if ($student === null) {
            return false;
        }

        $status = static::statusFromRecord($student);

        return $status === null || $status === 'aktif';
    }

    /**
     * @param array<string, mixed> $student
     */
    public static function isInactiveRecord(array $student): bool
    {
        return static::statusFromRecord($student) === 'nonaktif';
    }

    public static function isActiveId(int $studentId): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        $statement = static::connection()->prepare('SELECT status FROM siswa WHERE id = :id LIMIT 1');

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':id', $studentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return false;
        }

        $status = $statement->fetchColumn();

        return strtolower(trim((string) ($status === false ? '' : $status))) === 'aktif';
    }

    /**
     * @param array<string, mixed> $student
     */
    private static function statusFromRecord(array $student): ?string
    {
        $status = null;
        foreach (['student_status', 'siswa_status', 'status_siswa'] as $key) {
            if (array_key_exists($key, $student)) {
                $status = (string) $student[$key];
                break;
            }
        }

        if ($status === null && array_key_exists('status', $student)) {
            $status = (string) $student['status'];
        }

        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim(str_replace([' ', '-'], '_', $status)));

        return in_array($normalized, ['aktif', 'nonaktif'], true) ? $normalized : null;
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(int|array|null $classId = null, ?int $schoolYearId = null, ?int $includeId = null): array
    {
        $connection = static::connection();

        $wheres = [];
        $params = [];

        if (is_array($classId)) {
            $filteredIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $classId), static fn (int $id) => $id > 0)));

            if (!empty($filteredIds)) {
                $placeholders = [];
                foreach ($filteredIds as $index => $id) {
                    $placeholder = ':class_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $id;
                }

                $wheres[] = 's.kelas_id IN (' . implode(', ', $placeholders) . ')';
            } else {
                return [];
            }
        } elseif ($classId !== null) {
            $wheres[] = 's.kelas_id = :class_id';
            $params[':class_id'] = $classId;
        }

        if ($schoolYearId !== null) {
            $wheres[] = 's.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $whereClause = '';
        if (!empty($wheres)) {
            $whereClause = 'WHERE ' . implode(' AND ', $wheres);
        }

        $sql = <<<SQL
SELECT s.id, s.nama, s.nipd, s.nisn, s.status, s.status_dapodik, k.nama AS kelas_nama
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
%s
ORDER BY s.nama ASC
SQL;

        $statement = $connection->prepare(sprintf($sql, $whereClause));
        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
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
SELECT s.id, s.nama, s.nipd, s.nisn, s.status, s.status_dapodik, k.nama AS kelas_nama
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
WHERE s.id = :include_id
LIMIT 1
SQL);

                if ($statement !== false) {
                    $statement->bindValue(':include_id', $includeId, PDO::PARAM_INT);
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
            $label = $row['nama'] ?? '';
            $identifiers = [];
            if (!empty($row['nipd'])) {
                $identifiers[] = $row['nipd'];
            }
            if (!empty($row['nisn'])) {
                $identifiers[] = $row['nisn'];
            }
            if (!empty($identifiers)) {
                $label .= ' - ' . implode(' / ', $identifiers);
            }
            if (!empty($row['kelas_nama'])) {
                $label .= sprintf(' (%s)', $row['kelas_nama']);
            }
            if (strtolower((string) ($row['status'] ?? 'aktif')) === 'nonaktif') {
                $label .= ' - Nonaktif';
            }
            $dapodikStatus = strtolower(trim(str_replace([' ', '-'], '_', (string) ($row['status_dapodik'] ?? ''))));
            if (in_array($dapodikStatus, ['belum_masuk', 'belum_masuk_dapodik'], true)) {
                $label .= ' - Belum masuk Dapodik';
            }

            $options[$row['id']] = trim($label);
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function transferStudents(?string $keyword = null): array
    {
        $connection = static::connection();
        $params = [];

        $where = "WHERE s.sekolah_asal IS NOT NULL AND TRIM(s.sekolah_asal) <> ''";
        $keyword = $keyword !== null ? trim($keyword) : '';
        if ($keyword !== '') {
            $where .= "\nAND (s.nama LIKE :keyword OR s.nisn LIKE :keyword OR s.nipd LIKE :keyword OR s.nik LIKE :keyword OR s.sekolah_asal LIKE :keyword OR k.nama LIKE :keyword)";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $sql = <<<SQL
SELECT s.*, ta.nama AS tahun_ajaran_nama, k.nama AS kelas_nama, j.nama AS jurusan_nama
FROM siswa s
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
{$where}
ORDER BY s.created_at DESC, s.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byClass(int $classId, ?int $schoolYearId = null, ?string $keyword = null): array
    {
        if ($classId <= 0) {
            return [];
        }

        $connection = static::connection();

        $wheres = ['s.kelas_id = :class_id'];
        $params = [':class_id' => $classId];

        if ($schoolYearId !== null) {
            $wheres[] = 's.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $keyword = $keyword !== null ? trim((string) $keyword) : '';
        if ($keyword !== '') {
            $normalizedKeyword = function_exists('mb_strtolower')
                ? mb_strtolower($keyword, 'UTF-8')
                : strtolower($keyword);
            $wheres[] = '(LOWER(s.nama) LIKE :keyword OR LOWER(COALESCE(s.nipd, \'\')) LIKE :keyword OR LOWER(COALESCE(s.nisn, \'\')) LIKE :keyword OR LOWER(COALESCE(s.nik, \'\')) LIKE :keyword)';
            $params[':keyword'] = '%' . $normalizedKeyword . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $wheres);

        $sql = <<<SQL
SELECT
    s.*,
    k.nama AS kelas_nama,
    ta.nama AS tahun_ajaran_nama
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
{$whereClause}
ORDER BY s.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $paramType = PDO::PARAM_INT;
            if ($placeholder === ':keyword') {
                $paramType = PDO::PARAM_STR;
            }
            $statement->bindValue($placeholder, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int, int|string> $classIds
     * @return array<int, array<string, mixed>>
     */
    public static function byClasses(array $classIds, ?int $schoolYearId = null, ?string $keyword = null): array
    {
        $filteredIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $classIds), static fn (int $id) => $id > 0)));

        if (empty($filteredIds)) {
            return [];
        }

        $connection = static::connection();

        $wheres = [];
        $params = [];
        $placeholders = [];

        foreach ($filteredIds as $index => $classId) {
            $placeholder = ':class_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $classId;
        }

        $wheres[] = 's.kelas_id IN (' . implode(', ', $placeholders) . ')';

        if ($schoolYearId !== null) {
            $wheres[] = 's.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $keyword = $keyword !== null ? trim((string) $keyword) : '';
        if ($keyword !== '') {
            $normalizedKeyword = function_exists('mb_strtolower')
                ? mb_strtolower($keyword, 'UTF-8')
                : strtolower($keyword);
            $wheres[] = '(LOWER(s.nama) LIKE :keyword OR LOWER(COALESCE(s.nipd, \'\')) LIKE :keyword OR LOWER(COALESCE(s.nisn, \'\')) LIKE :keyword OR LOWER(COALESCE(s.nik, \'\')) LIKE :keyword)';
            $params[':keyword'] = '%' . $normalizedKeyword . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $wheres);

        $sql = <<<SQL
SELECT
    s.*,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    ta.nama AS tahun_ajaran_nama
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
{$whereClause}
ORDER BY
    COALESCE(k.tingkat, 0) ASC,
    k.nama ASC,
    s.nama ASC
SQL;

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $paramType = PDO::PARAM_INT;
            if ($placeholder === ':keyword') {
                $paramType = PDO::PARAM_STR;
            }
            $statement->bindValue($placeholder, $value, $paramType);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function findWithRelations(int $studentId): ?array
    {
        if ($studentId <= 0) {
            return null;
        }

        $sql = <<<SQL
SELECT
    s.*,
    k.nama AS kelas_nama,
    k.tingkat AS kelas_tingkat,
    k.jurusan_id AS kelas_jurusan_id,
    k.wali_kelas_id,
    j.nama AS jurusan_nama,
    ta.nama AS tahun_ajaran_nama,
    ta.semester_aktif AS tahun_ajaran_semester,
    ta.status AS tahun_ajaran_status,
    g.nama AS wali_kelas_nama,
    g.nip AS wali_kelas_nip
FROM siswa s
LEFT JOIN kelas k ON k.id = s.kelas_id
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
LEFT JOIN guru g ON g.id = k.wali_kelas_id
WHERE s.id = :id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $studentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function unassigned(?string $keyword = null): array
    {
        $keyword = $keyword !== null ? trim((string) $keyword) : '';

        $sql = <<<SQL
SELECT s.*
FROM siswa s
WHERE s.kelas_id IS NULL
SQL;

        $params = [];
        if ($keyword !== '') {
            $normalizedKeyword = function_exists('mb_strtolower')
                ? mb_strtolower($keyword, 'UTF-8')
                : strtolower($keyword);
            $sql .= "\nAND (LOWER(s.nama) LIKE :keyword OR LOWER(COALESCE(s.nipd, '')) LIKE :keyword OR LOWER(COALESCE(s.nisn, '')) LIKE :keyword OR LOWER(COALESCE(s.nik, '')) LIKE :keyword)";
            $params[':keyword'] = '%' . $normalizedKeyword . '%';
        }

        $sql .= "\nORDER BY s.nama ASC";

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int, int> $studentIds
     */
    public static function assignToClass(array $studentIds, int $classId, int $schoolYearId): bool
    {
        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $studentIds),
            static fn (int $id) => $id > 0
        )));

        if (empty($normalizedIds) || $classId <= 0 || $schoolYearId <= 0) {
            return false;
        }

        $placeholders = [];
        foreach ($normalizedIds as $index => $id) {
            $placeholders[] = ':id_' . $index;
        }

        $sql = sprintf(
            'UPDATE %s SET kelas_id = :class_id, tahun_ajaran_id = :school_year_id, updated_at = :updated_at WHERE id IN (%s)',
            static::table(),
            implode(', ', $placeholders),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');

        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':updated_at', $timestamp);

        foreach ($normalizedIds as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }

        $success = $statement->execute();

        if ($success) {
            StudentPlacementHistory::upsertMany($normalizedIds, $classId, $schoolYearId);
        }

        return $success;
    }

    /**
     * @param array<int, int> $studentIds
     */
    public static function clearClassAssignments(array $studentIds): bool
    {
        $normalizedIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $studentIds),
            static fn (int $id) => $id > 0
        )));

        if (empty($normalizedIds)) {
            return false;
        }

        $placeholders = [];
        foreach ($normalizedIds as $index => $id) {
            $placeholders[] = ':id_' . $index;
        }

        $sql = sprintf(
            'UPDATE %s SET kelas_id = NULL, tahun_ajaran_id = NULL, updated_at = :updated_at WHERE id IN (%s)',
            static::table(),
            implode(', ', $placeholders),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');
        $statement->bindValue(':updated_at', $timestamp);

        foreach ($normalizedIds as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }

        return $statement->execute();
    }

    public static function countBySchoolYear(int $schoolYearId): int
    {
        if ($schoolYearId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM siswa WHERE tahun_ajaran_id = :school_year_id';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function classGenderSummary(?int $schoolYearId = null): array
    {
        $connection = static::connection();

        $whereClauses = [];
        $params = [];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $whereClauses[] = 'k.tahun_ajaran_id = :school_year_id';
            $params[':school_year_id'] = $schoolYearId;
        }

        $where = '';
        if (!empty($whereClauses)) {
            $where = 'WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql = <<<SQL
SELECT
    k.id,
    k.nama,
    k.tingkat,
    k.tahun_ajaran_id,
    j.nama AS jurusan_nama,
    COUNT(s.id) AS total_students,
    SUM(CASE WHEN s.jenis_kelamin = 'L' THEN 1 ELSE 0 END) AS total_male,
    SUM(CASE WHEN s.jenis_kelamin = 'P' THEN 1 ELSE 0 END) AS total_female
FROM kelas k
LEFT JOIN jurusan j ON j.id = k.jurusan_id
LEFT JOIN siswa s ON s.kelas_id = k.id
{$where}
GROUP BY k.id, k.nama, k.tingkat, k.tahun_ajaran_id, j.nama
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

        if ($rows === false) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'class_id' => (int) ($row['id'] ?? 0),
                'class_name' => (string) ($row['nama'] ?? ''),
                'class_level' => isset($row['tingkat']) ? (int) $row['tingkat'] : null,
                'major_name' => $row['jurusan_nama'] ?? null,
                'total_students' => isset($row['total_students']) ? (int) $row['total_students'] : 0,
                'total_male' => isset($row['total_male']) ? (int) $row['total_male'] : 0,
                'total_female' => isset($row['total_female']) ? (int) $row['total_female'] : 0,
            ];
        }, $rows);
    }

    /**
     * @return array{total:int,male:int,female:int}
     */
    public static function unassignedGenderSummary(?int $schoolYearId = null): array
    {
        $connection = static::connection();

        $params = [];
        $whereClauses = ['s.kelas_id IS NULL'];

        if ($schoolYearId !== null && $schoolYearId > 0) {
            $whereClauses[] = '(s.tahun_ajaran_id = :school_year_id OR s.tahun_ajaran_id IS NULL)';
            $params[':school_year_id'] = $schoolYearId;
        }

        $where = 'WHERE ' . implode(' AND ', $whereClauses);

        $sql = <<<SQL
SELECT
    COUNT(s.id) AS total_students,
    SUM(CASE WHEN s.jenis_kelamin = 'L' THEN 1 ELSE 0 END) AS total_male,
    SUM(CASE WHEN s.jenis_kelamin = 'P' THEN 1 ELSE 0 END) AS total_female
FROM siswa s
{$where}
SQL;

        $statement = $connection->prepare($sql);
        if ($statement === false) {
            return ['total' => 0, 'male' => 0, 'female' => 0];
        }

        foreach ($params as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return ['total' => 0, 'male' => 0, 'female' => 0];
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['total' => 0, 'male' => 0, 'female' => 0];
        }

        return [
            'total' => isset($row['total_students']) ? (int) $row['total_students'] : 0,
            'male' => isset($row['total_male']) ? (int) $row['total_male'] : 0,
            'female' => isset($row['total_female']) ? (int) $row['total_female'] : 0,
        ];
    }

    public static function updatePhoto(int $studentId, ?string $photoPath): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        $sql = sprintf(
            'UPDATE %s SET foto_path = :path, updated_at = :updated_at WHERE id = :id',
            static::table(),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':id', $studentId, PDO::PARAM_INT);
        if ($photoPath !== null && $photoPath !== '') {
            $statement->bindValue(':path', $photoPath, PDO::PARAM_STR);
        } else {
            $statement->bindValue(':path', null, PDO::PARAM_NULL);
        }
        $statement->bindValue(':updated_at', date('Y-m-d H:i:s'));

        return $statement->execute();
    }

    /**
     * @param array<string, string|null> $documents
     */
    public static function updateDocuments(int $studentId, array $documents): bool
    {
        if ($studentId <= 0 || empty($documents)) {
            return false;
        }

        $allowedColumns = [
            'scan_ijazah_path',
            'scan_rapor_path',
            'scan_kartu_keluarga_path',
            'scan_akta_lahir_path',
            'scan_ktp_ayah_path',
            'scan_ktp_ibu_path',
        ];

        $setClauses = [];
        $params = [];

        foreach ($documents as $column => $path) {
            if (!in_array($column, $allowedColumns, true)) {
                continue;
            }

            $placeholder = ':' . $column;
            $setClauses[] = sprintf('%s = %s', $column, $placeholder);
            if ($path !== null && $path !== '') {
                $params[$placeholder] = [$path, PDO::PARAM_STR];
            } else {
                $params[$placeholder] = [null, PDO::PARAM_NULL];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = :updated_at';

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            static::table(),
            implode(', ', $setClauses),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($params as $placeholder => [$value, $type]) {
            $statement->bindValue($placeholder, $value, $type);
        }

        $statement->bindValue(':updated_at', date('Y-m-d H:i:s'));
        $statement->bindValue(':id', $studentId, PDO::PARAM_INT);

        return $statement->execute();
    }

    public static function findByNisn(string $nisn): ?array
    {
        $nisn = trim($nisn);
        if ($nisn === '') {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM siswa WHERE nisn = :nisn LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':nisn', $nisn);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function findByNipd(string $nipd): ?array
    {
        $nipd = trim($nipd);
        if ($nipd === '') {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM siswa WHERE nipd = :nipd LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':nipd', $nipd);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function findByNik(string $nik): ?array
    {
        $nik = trim($nik);
        if ($nik === '') {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM siswa WHERE nik = :nik LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':nik', $nik);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }
}
