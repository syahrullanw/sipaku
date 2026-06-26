<?php

namespace App\Models;

use Core\Model;
use PDO;

class AccountabilityReport extends Model
{
    protected static ?string $table = 'lpj_keuangan';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function mapByEntity(string $entityType, array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int) $id, $entityIds), static fn (int $id) => $id > 0)));

        if ($entityType === '' || empty($entityIds)) {
            return [];
        }

        $placeholders = [];
        foreach ($entityIds as $index => $id) {
            $placeholders[] = ':entity_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE entity_type = :type AND entity_id IN (%s)',
            static::table(),
            implode(', ', $placeholders),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':type', $entityType);
        foreach ($entityIds as $index => $id) {
            $statement->bindValue(':entity_' . $index, $id, PDO::PARAM_INT);
        }
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $key = (string) ($row['entity_id'] ?? '');
            if ($key !== '') {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    public static function findByEntity(string $entityType, int $entityId): ?array
    {
        if ($entityType === '' || $entityId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE entity_type = :type AND entity_id = :entity LIMIT 1',
            static::table(),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':type', $entityType);
        $statement->bindValue(':entity', $entityId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}

