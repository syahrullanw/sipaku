<?php

namespace App\Models;

use Core\Model;
use PDO;

class Major extends Model
{
    protected static ?string $table = 'jurusan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(?string $status = null): array
    {
        $connection = static::connection();

        if ($status !== null) {
            $statement = $connection->prepare('SELECT * FROM jurusan WHERE status = :status ORDER BY nama ASC');

            if ($statement === false) {
                return [];
            }

            $statement->bindValue(':status', $status);
            $statement->execute();
        } else {
            $statement = $connection->query("SELECT * FROM jurusan ORDER BY (status = 'aktif') DESC, nama ASC");
        }

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function options(bool $onlyActive = true, ?int $includeId = null): array
    {
        $rows = $onlyActive ? static::allOrdered('aktif') : static::allOrdered();
        $options = [];

        foreach ($rows as $row) {
            $options[$row['id']] = $row['nama'];
        }

        if ($includeId !== null && !isset($options[$includeId])) {
            $record = static::find($includeId);
            if ($record !== null) {
                $options[$record['id']] = $record['nama'];
            }
        }

        return $options;
    }
}
