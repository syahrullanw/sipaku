<?php

namespace App\Models;

use Core\Model;
use PDO;

class SchoolYear extends Model
{
    protected static ?string $table = 'tahun_ajaran';
    protected static bool $schemaEnsured = false;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        static::ensureSchema();

        $statement = static::connection()->query('SELECT * FROM tahun_ajaran ORDER BY tanggal_mulai DESC');

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function find(mixed $id): ?array
    {
        static::ensureSchema();

        return parent::find($id);
    }

    public static function options(): array
    {
        $rows = static::allOrdered();

        $options = [];

        foreach ($rows as $row) {
            $semester = (int) ($row['semester_aktif'] ?? 1);
            $semesterLabel = $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
            $options[$row['id']] = sprintf('%s - %s', $row['nama'], $semesterLabel);
        }

        return $options;
    }

    public static function active(): ?array
    {
        static::ensureSchema();

        $statement = static::connection()->prepare("SELECT * FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1");

        if ($statement === false || !$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function ensureSchema(): void
    {
        if (static::$schemaEnsured) {
            return;
        }

        $connection = static::connection();
        $columns = [
            'skl_nomor_surat' => "ALTER TABLE tahun_ajaran ADD COLUMN skl_nomor_surat VARCHAR(190) NULL AFTER tanggal_raport_tengah_semester",
            'skl_tanggal_rapat_pleno' => "ALTER TABLE tahun_ajaran ADD COLUMN skl_tanggal_rapat_pleno DATE NULL AFTER skl_nomor_surat",
            'skl_titimangsa' => "ALTER TABLE tahun_ajaran ADD COLUMN skl_titimangsa DATE NULL AFTER skl_tanggal_rapat_pleno",
            'transkrip_nomor_prefix' => "ALTER TABLE tahun_ajaran ADD COLUMN transkrip_nomor_prefix VARCHAR(80) NULL AFTER skl_titimangsa",
        ];

        foreach ($columns as $column => $alterSql) {
            $statement = $connection->query("SHOW COLUMNS FROM tahun_ajaran LIKE '{$column}'");

            if ($statement === false || $statement->fetch(PDO::FETCH_ASSOC) === false) {
                $connection->exec($alterSql);
            }
        }

        $statement = $connection->query("SHOW COLUMNS FROM tahun_ajaran LIKE 'transkrip_nomor_prefix'");
        $column = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        $type = is_array($column) ? strtolower((string) ($column['Type'] ?? '')) : '';
        if ($type !== '' && !str_starts_with($type, 'varchar(80)')) {
            $connection->exec('ALTER TABLE tahun_ajaran MODIFY COLUMN transkrip_nomor_prefix VARCHAR(80) NULL');
        }

        static::$schemaEnsured = true;
    }

    /**
     * @return array<int>
     */
    public static function relatedIds(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $current = static::find($schoolYearId);

        if ($current === null) {
            return [];
        }

        $currentKey = static::academicCycleKey($current);

        if ($currentKey === '') {
            return [$schoolYearId];
        }

        $relatedIds = [];

        foreach (static::allOrdered() as $year) {
            $yearId = (int) ($year['id'] ?? 0);

            if ($yearId <= 0) {
                continue;
            }

            if (static::academicCycleKey($year) !== $currentKey) {
                continue;
            }

            $relatedIds[] = $yearId;
        }

        if (empty($relatedIds)) {
            return [$schoolYearId];
        }

        $relatedIds = array_values(array_unique(array_filter(
            $relatedIds,
            static fn (int $id): bool => $id > 0
        )));

        sort($relatedIds);

        return $relatedIds;
    }

    public static function previousEvenSemester(int $currentYearId): ?array
    {
        if ($currentYearId <= 0) {
            return null;
        }

        $current = static::find($currentYearId);

        if ($current === null) {
            return null;
        }

        $startDate = $current['tanggal_mulai'] ?? null;

        $query = 'SELECT * FROM tahun_ajaran WHERE semester_aktif = 2';

        if (!empty($startDate)) {
            $query .= ' AND tanggal_mulai < :start_date';
        } else {
            $query .= ' AND id <> :current_id';
        }

        $query .= ' ORDER BY tanggal_mulai DESC LIMIT 1';

        $statement = static::connection()->prepare($query);

        if ($statement === false) {
            return null;
        }

        if (!empty($startDate)) {
            $statement->bindValue(':start_date', $startDate);
        } else {
            $statement->bindValue(':current_id', $currentYearId, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $year
     */
    protected static function academicCycleKey(array $year): string
    {
        $codeKey = static::normalizeAcademicCycleText($year['kode'] ?? null);
        if ($codeKey !== '') {
            return 'code:' . $codeKey;
        }

        $nameKey = static::normalizeAcademicCycleText($year['nama'] ?? null);
        if ($nameKey !== '') {
            return 'name:' . $nameKey;
        }

        return '';
    }

    protected static function normalizeAcademicCycleText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\bsemester\s*[12]\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(ganjil|genap)\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b[12]\b$/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized, " \t\n\r\0\x0B-_/");
    }
}
