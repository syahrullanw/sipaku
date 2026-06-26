<?php

namespace App\Models;

use Core\Model;
use PDO;

class Teacher extends Model
{
    protected static ?string $table = 'guru';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        $sql = <<<SQL
SELECT g.*, u.username
FROM guru g
LEFT JOIN users u ON u.teacher_id = g.id
ORDER BY g.status ASC, g.nama ASC
SQL;

        $statement = static::connection()->query($sql);

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function options(bool $onlyActive = true, ?int $ensureId = null): array
    {
        $sql = 'SELECT id, nama, status FROM guru';
        if ($onlyActive) {
            $sql .= " WHERE status = 'aktif'";
        }
        $sql .= ' ORDER BY nama ASC';

        $statement = static::connection()->query($sql);
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        $options = [];

        foreach ($rows as $row) {
            $label = $row['nama'];
            if (($row['status'] ?? 'aktif') !== 'aktif') {
                $label .= ' (Nonaktif)';
            }
            $options[$row['id']] = $label;
        }

        if ($ensureId !== null && $ensureId > 0 && !isset($options[$ensureId])) {
            $teacher = static::find($ensureId);
            if ($teacher !== null) {
                $label = (string) ($teacher['nama'] ?? 'Guru');
                if (($teacher['status'] ?? 'aktif') !== 'aktif') {
                    $label .= ' (Nonaktif)';
                }
                $options[$teacher['id']] = $label;
            }
        }

        return $options;
    }


    public static function findByNip(string $nip): ?array
    {
        $nip = trim($nip);
        if ($nip === '') {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM guru WHERE nip = :nip LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':nip', $nip);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $statement = static::connection()->prepare('SELECT * FROM guru WHERE email = :email LIMIT 1');
        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':email', $email);
        $statement->execute();

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function findAllByNameInsensitive(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $statement = static::connection()->prepare('SELECT * FROM guru WHERE LOWER(nama) = LOWER(:name)');
        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':name', $name);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
