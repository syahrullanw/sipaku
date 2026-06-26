<?php

namespace App\Models;

use Core\Model;
use PDO;

class PpdbPeriod extends Model
{
    protected static ?string $table = 'ppdb_periode';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithResponsibles(): array
    {
        $sql = <<<SQL
SELECT
    p.*,
    COALESCE(GROUP_CONCAT(CONCAT(g.nama, ' (', r.peran, ')') ORDER BY r.peran SEPARATOR ', '), '') AS penanggung_jawab_nama
FROM ppdb_periode p
LEFT JOIN ppdb_periode_penanggung_jawab r ON r.periode_id = p.id
LEFT JOIN guru g ON g.id = r.guru_id
GROUP BY p.id
ORDER BY p.created_at DESC, p.id DESC
SQL;

        $statement = static::connection()->query($sql);

        if ($statement === false) {
            return [];
        }

        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $records === false ? [] : $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findWithResponsibles(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    p.*,
    COALESCE(GROUP_CONCAT(CONCAT(g.nama, ' (', r.peran, ')') ORDER BY r.peran SEPARATOR ', '), '') AS penanggung_jawab_nama
FROM ppdb_periode p
LEFT JOIN ppdb_periode_penanggung_jawab r ON r.periode_id = p.id
LEFT JOIN guru g ON g.id = r.guru_id
WHERE p.id = :id
GROUP BY p.id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function active(): ?array
    {
        $sql = <<<SQL
SELECT p.*
FROM ppdb_periode p
WHERE p.status = 'aktif'
ORDER BY p.updated_at DESC, p.id DESC
LIMIT 1
SQL;

        $statement = static::connection()->query($sql);

        if ($statement === false) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByToken(string $token): ?array
    {
        $sql = <<<SQL
SELECT *
FROM ppdb_periode
WHERE token_pendaftaran = :token
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':token', $token);
        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByCode(string $code): ?array
    {
        $sql = <<<SQL
SELECT *
FROM ppdb_periode
WHERE kode = :code
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':code', $code);
        $statement->execute();
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $identifier) === 1) {
            $period = static::findByToken($identifier);
            if ($period !== null) {
                return $period;
            }
        }

        return static::findByCode($identifier);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function stages(): array
    {
        return [
            'pendaftaran' => [
                'label' => 'Pendaftaran',
                'column' => 'pendaftaran_diaktifkan',
            ],
            'seleksi' => [
                'label' => 'Seleksi Akademik',
                'column' => 'seleksi_diaktifkan',
            ],
            'pengumuman' => [
                'label' => 'Pengumuman',
                'column' => 'pengumuman_diaktifkan',
            ],
            'daftar_ulang' => [
                'label' => 'Daftar Ulang',
                'column' => 'daftar_ulang_diaktifkan',
            ],
            'pembayaran' => [
                'label' => 'Pembayaran',
                'column' => 'pembayaran_diaktifkan',
            ],
        ];
    }

    public static function stageColumn(string $stage): ?string
    {
        $definitions = static::stages();

        if (!isset($definitions[$stage])) {
            return null;
        }

        return $definitions[$stage]['column'] ?? null;
    }

    public static function isStageEnabled(array $period, string $stage): bool
    {
        $column = static::stageColumn($stage);

        if ($column === null) {
            return false;
        }

        return (bool) ($period[$column] ?? false);
    }

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
            $exists = static::exists(['token_pendaftaran' => $token]);
        } while ($exists);

        return $token;
    }

    /**
     * @return array<int, string>
     */
    public static function options(?string $statusFilter = null): array
    {
        $query = 'SELECT id, nama, kode, status FROM ppdb_periode';
        $params = [];

        if ($statusFilter !== null) {
            $query .= ' WHERE status = :status';
            $params[':status'] = $statusFilter;
        }

        $query .= ' ORDER BY created_at DESC, id DESC';

        $statement = static::connection()->prepare($query);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $options = [];

        foreach ($rows as $row) {
            $label = (string) ($row['nama'] ?? 'Periode');
            $status = (string) ($row['status'] ?? '');

            if ($status !== '') {
                $label .= sprintf(' (%s)', ucfirst($status));
            }

            $options[(int) $row['id']] = $label;
        }

        return $options;
    }
}
