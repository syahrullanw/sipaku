<?php

namespace App\Models;

use Core\Model;
use PDO;

class Attitude extends Model
{
    protected static ?string $table = 'data_sikap';

    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'spiritual' => 'Sikap Spiritual',
        'sosial' => 'Sikap Sosial',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(?string $type = null): array
    {
        $connection = static::connection();
        $params = [];
        $wheres = [];

        if ($type !== null && array_key_exists($type, self::TYPES)) {
            $wheres[] = 'jenis = :jenis';
            $params[':jenis'] = $type;
        }

        $sql = 'SELECT * FROM data_sikap';

        if (!empty($wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }

        $sql .= ' ORDER BY FIELD(jenis, \'spiritual\', \'sosial\'), kode ASC, nama ASC';

        $statement = $connection->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return self::TYPES;
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(string $type, bool $onlyActive = true): array
    {
        if (!array_key_exists($type, self::TYPES)) {
            return [];
        }

        $connection = static::connection();

        $sql = 'SELECT id, nama FROM data_sikap WHERE jenis = :jenis';
        if ($onlyActive) {
            $sql .= ' AND status = \'aktif\'';
        }

        $sql .= ' ORDER BY nama ASC';

        $statement = $connection->prepare($sql);
        $statement->bindValue(':jenis', $type);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $options[$row['id']] = $row['nama'];
        }

        return $options;
    }

    /**
     * @return array<int>
     */
    public static function idsByType(string $type, bool $onlyActive = false): array
    {
        if (!array_key_exists($type, self::TYPES)) {
            return [];
        }

        $sql = 'SELECT id FROM data_sikap WHERE jenis = :jenis';
        if ($onlyActive) {
            $sql .= ' AND status = \'aktif\'';
        }

        $statement = static::connection()->prepare($sql);
        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':jenis', $type);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($rows === false) {
            return [];
        }

        return array_map(static fn ($value) => (int) $value, $rows);
    }

    public static function findByTypeAndCode(string $type, string $code): ?array
    {
        if (!array_key_exists($type, self::TYPES) || $code === '') {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM data_sikap WHERE jenis = :jenis AND kode = :kode LIMIT 1'
        );
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':jenis', $type);
        $statement->bindValue(':kode', $code);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }
}
