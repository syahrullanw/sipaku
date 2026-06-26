<?php

namespace Core;

use PDO;

abstract class Model
{
    protected static string $primaryKey = 'id';

    protected static ?string $table = null;

    protected static function table(): string
    {
        if (static::$table !== null) {
            return static::$table;
        }

        $class = static::class;
        $segments = explode('\\', $class);
        $name = end($segments);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name ?? ''));
    }

    protected static function connection(): PDO
    {
        return Database::connection();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): bool
    {
        $statement = static::prepareInsertStatement($attributes);

        if ($statement === null) {
            return false;
        }

        return $statement->execute();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function createAndReturnId(array $attributes): ?int
    {
        $statement = static::prepareInsertStatement($attributes);

        if ($statement === null) {
            return null;
        }

        if (!$statement->execute()) {
            return null;
        }

        $id = static::connection()->lastInsertId();

        if ($id === false) {
            return null;
        }

        $numericId = (int) $id;

        return $numericId > 0 ? $numericId : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected static function prepareInsertStatement(array $attributes): ?\PDOStatement
    {
        if (empty($attributes)) {
            return null;
        }

        $columns = array_keys($attributes);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::table(),
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        foreach ($attributes as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        return $statement;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function updateById(mixed $id, array $attributes): bool
    {
        $columns = array_keys($attributes);
        $assignments = array_map(static fn ($column) => "{$column} = :{$column}", $columns);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__id LIMIT 1',
            static::table(),
            implode(', ', $assignments),
            static::$primaryKey,
        );

        $statement = static::connection()->prepare($sql);

        foreach ($attributes as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        $statement->bindValue(':__id', $id);

        return $statement->execute();
    }

    public static function deleteById(mixed $id): bool
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :id LIMIT 1',
            static::table(),
            static::$primaryKey,
        );

        $statement = static::connection()->prepare($sql);
        $statement->bindValue(':id', $id);

        return $statement->execute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $statement = static::connection()->query('SELECT * FROM ' . static::table());

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function find(mixed $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            static::table(),
            static::$primaryKey,
        );

        $statement = static::connection()->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS aggregate FROM %s', static::table());
        $statement = static::connection()->query($sql);

        if ($statement === false) {
            return 0;
        }

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @param array<string, mixed> $conditions
     */
    public static function exists(array $conditions, ?int $ignoreId = null): bool
    {
        if (empty($conditions)) {
            return false;
        }

        $table = static::table();
        $clauses = [];
        foreach ($conditions as $column => $value) {
            $clauses[] = sprintf('%s = :%s', $column, $column);
        }

        if ($ignoreId !== null) {
            $clauses[] = sprintf('%s <> :__ignore_id', static::$primaryKey);
        }

        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, implode(' AND ', $clauses));
        $statement = static::connection()->prepare($sql);

        foreach ($conditions as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        if ($ignoreId !== null) {
            $statement->bindValue(':__ignore_id', $ignoreId, PDO::PARAM_INT);
        }

        $statement->execute();
        $result = $statement->fetchColumn();

        return $result !== false && (int) $result > 0;
    }
}
