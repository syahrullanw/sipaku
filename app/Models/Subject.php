<?php

namespace App\Models;

use Core\Model;
use PDO;

class Subject extends Model
{
    protected static ?string $table = 'mata_pelajaran';

    /**
     * @var array<int, array{code: string, label: string}>
     */
    public const GROUPS = [
        ['code' => 'A', 'label' => 'A. Muatan Nasional'],
        ['code' => 'B', 'label' => 'B. Muatan Kewilayahan'],
        ['code' => 'C1', 'label' => 'C1. Dasar Bidang Keahlian'],
        ['code' => 'C2', 'label' => 'C2. Dasar Program Keahlian'],
        ['code' => 'C3', 'label' => 'C3. Kompetensi Keahlian'],
        ['code' => 'D', 'label' => 'D. MULOK'],
    ];

    /**
     * @var array<int, string>
     */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(?int $schoolYearId = null): array
    {
        $connection = static::connection();
        $codes = [];

        foreach (self::GROUPS as $group) {
            $codes[] = $connection->quote($group['code']);
        }

        $orderClause = implode(', ', $codes);

        $baseSql = <<<SQL
SELECT mp.*, j.nama AS jurusan_nama, ta.nama AS tahun_ajaran_nama, ta.tanggal_mulai, ta.semester_aktif AS tahun_ajaran_semester
FROM mata_pelajaran mp
JOIN tahun_ajaran ta ON ta.id = mp.tahun_ajaran_id
LEFT JOIN jurusan j ON j.id = mp.jurusan_id
%s
ORDER BY FIELD(mp.jenis, {$orderClause}), ta.tanggal_mulai DESC, mp.nama ASC
SQL;

        $where = '';
        if ($schoolYearId !== null) {
            $where = 'WHERE mp.tahun_ajaran_id = :school_year_id';
        }

        $sql = sprintf($baseSql, $where);

        $statement = $connection->prepare($sql);
        if ($statement === false) {
            return [];
        }

        if ($schoolYearId !== null) {
            $statement->bindValue(':school_year_id', $schoolYearId, PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithDisplayGroup(?int $schoolYearId = null): array
    {
        $subjects = static::allOrdered($schoolYearId);
        $labels = [];

        foreach (self::GROUPS as $group) {
            $labels[$group['code']] = $group['label'];
        }

        foreach ($subjects as $index => $subject) {
            $code = $subject['jenis'];
            $subjects[$index]['jenis_label'] = $labels[$code] ?? $code;
            $subjects[$index]['jurusan_nama'] = $subject['jurusan_nama'] ?? null;
            $semesterValue = (int) ($subject['tahun_ajaran_semester'] ?? 1);
            $subjects[$index]['tahun_ajaran_semester_label'] = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
        }

        return $subjects;
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        $options = [];

        foreach (self::GROUPS as $group) {
            $options[$group['code']] = $group['label'];
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(?int $schoolYearId = null): array
    {
        $rows = static::allOrdered($schoolYearId);
        $options = [];

        foreach ($rows as $row) {
            $label = sprintf('%s - %s', $row['kode'], $row['nama']);
            if (!empty($row['jurusan_nama'])) {
                $label .= sprintf(' (%s)', $row['jurusan_nama']);
            }

            if (!empty($row['tahun_ajaran_nama'])) {
                $semesterLabel = $row['tahun_ajaran_semester_label'] ?? null;
                if ($semesterLabel === null) {
                    $semesterValue = (int) ($row['tahun_ajaran_semester'] ?? 1);
                    $semesterLabel = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                }
                $label .= sprintf(' [%s - %s]', $row['tahun_ajaran_nama'], $semesterLabel);
            }

            $options[$row['id']] = $label;
        }

        return $options;
    }

    public static function findByCodeAndYear(string $code, int $schoolYearId): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $schoolYearId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM mata_pelajaran WHERE kode = :kode AND tahun_ajaran_id = :year LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':kode', $code);
        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }
}
