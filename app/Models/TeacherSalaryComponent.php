<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherSalaryComponent extends Model
{
    protected static ?string $table = 'teacher_salary_components';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byRecord(int $recordId): array
    {
        if ($recordId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM teacher_salary_components WHERE teacher_salary_record_id = :record ORDER BY id ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':record', $recordId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}

