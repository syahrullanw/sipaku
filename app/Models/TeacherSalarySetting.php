<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherSalarySetting extends Model
{
    protected static ?string $table = 'teacher_salary_settings';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bySchoolYear(int $schoolYearId): array
    {
        if ($schoolYearId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM teacher_salary_settings WHERE tahun_ajaran_id = :year ORDER BY category ASC, name ASC'
        );

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

    public static function findByYearAndCode(int $schoolYearId, string $code): ?array
    {
        if ($schoolYearId <= 0 || $code === '') {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM teacher_salary_settings WHERE tahun_ajaran_id = :year AND code = :code LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':code', $code);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function upsert(
        int $schoolYearId,
        string $code,
        string $name,
        string $category,
        float $amount,
        ?int $referenceId = null,
        ?string $metadata = null
    ): bool {
        if ($schoolYearId <= 0 || $code === '' || $name === '' || $category === '') {
            return false;
        }

        $existing = static::findByYearAndCode($schoolYearId, $code);
        $now = date('Y-m-d H:i:s');

        if ($existing !== null) {
            return static::updateById($existing['id'], [
                'name' => $name,
                'category' => $category,
                'reference_id' => $referenceId,
                'amount' => $amount,
                'metadata' => $metadata,
                'updated_at' => $now,
            ]);
        }

        return static::create([
            'tahun_ajaran_id' => $schoolYearId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'reference_id' => $referenceId,
            'amount' => $amount,
            'metadata' => $metadata,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

