<?php

namespace App\Models;

use Core\Model;
use PDO;

class SchoolProfile extends Model
{
    protected static ?string $table = 'sekolah';
    protected static bool $schemaEnsured = false;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        static::ensureSchema();

        $statement = static::connection()->query('SELECT * FROM sekolah ORDER BY nama ASC');

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function first(): ?array
    {
        static::ensureSchema();

        $statement = static::connection()->query('SELECT * FROM sekolah ORDER BY id ASC LIMIT 1');

        if ($statement === false) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        static::ensureSchema();

        $options = [];

        foreach (static::allOrdered() as $row) {
            $options[$row['id']] = $row['nama'];
        }

        return $options;
    }

    public static function ensureSchema(): void
    {
        if (static::$schemaEnsured) {
            return;
        }

        $connection = static::connection();
        $statement = $connection->query("SHOW COLUMNS FROM sekolah LIKE 'transkrip_nomor_prefix'");

        if ($statement === false || $statement->fetch(PDO::FETCH_ASSOC) === false) {
            $connection->exec("ALTER TABLE sekolah ADD COLUMN transkrip_nomor_prefix VARCHAR(30) NULL");
        }

        static::$schemaEnsured = true;
    }
}
