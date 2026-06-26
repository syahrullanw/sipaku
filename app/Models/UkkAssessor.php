<?php

namespace App\Models;

use Core\Model;
use PDO;

class UkkAssessor extends Model
{
    protected static ?string $table = 'ukk_asesor';

    /**
     * @param array<int, int> $dudiIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function mapByDudi(array $dudiIds): array
    {
        if (empty($dudiIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($dudiIds), '?'));
        $sql = <<<SQL
SELECT a.*, d.nama AS dudi_nama
FROM ukk_asesor a
LEFT JOIN ukk_dudi d ON d.id = a.dudi_id
WHERE a.dudi_id IN ({$placeholders})
ORDER BY d.nama ASC, a.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($dudiIds as $index => $id) {
            $statement->bindValue($index + 1, (int) $id, PDO::PARAM_INT);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $dudiId = (int) ($row['dudi_id'] ?? 0);
            if (!isset($map[$dudiId])) {
                $map[$dudiId] = [];
            }

            $map[$dudiId][] = $row;
        }

        return $map;
    }
}
