<?php

namespace App\Models;

use Core\Model;
use PDO;

class AcademicPosition extends Model
{
    protected static ?string $table = 'jabatan_akademik';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const SYSTEM_POSITIONS = [
        [
            'assigns_user_role' => 'bendahara',
            'nama' => 'Bendahara',
            'deskripsi' => 'Mengelola administrasi keuangan sekolah',
            'level' => 1,
            'requires_major' => false,
        ],
        [
            'assigns_user_role' => 'tata_usaha',
            'nama' => 'Staf Tata Usaha',
            'deskripsi' => 'Menangani administrasi tata usaha sekolah',
            'level' => 2,
            'requires_major' => false,
        ],
        [
            'assigns_user_role' => 'waka_kurikulum',
            'nama' => 'Waka Kurikulum',
            'deskripsi' => 'Mengelola kurikulum dan kegiatan akademik',
            'level' => 5,
            'match_by_level' => false,
            'requires_major' => false,
        ],
        [
            'assigns_user_role' => 'kepala_prodi',
            'nama' => 'Kepala Program Studi',
            'deskripsi' => 'Mengkoordinasikan kebutuhan jurusan dan pengadaan praktik',
            'level' => 6,
            'match_by_level' => false,
            'requires_major' => true,
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allOrdered(): array
    {
        $statement = static::connection()->query('SELECT * FROM jabatan_akademik ORDER BY COALESCE(level, 999), nama ASC');

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public static function isSystem(int $id): bool
    {
        $record = static::find($id);

        return $record !== null && (int) ($record['is_system'] ?? 0) === 1;
    }

    public static function ensureSystemPositions(): void
    {
        foreach (self::SYSTEM_POSITIONS as $position) {
            $assignsRole = $position['assigns_user_role'] ?? null;
            if (is_string($assignsRole)) {
                $assignsRole = trim($assignsRole);
                if ($assignsRole === '') {
                    $assignsRole = null;
                }
            } else {
                $assignsRole = null;
            }

            $targetLevel = (int) $position['level'];
            $matchByLevel = (bool) ($position['match_by_level'] ?? true);

            $existing = null;

            if ($assignsRole !== null) {
                $existing = self::findByAssignsRole($assignsRole);
            }

            if ($existing === null) {
                $existing = self::findByNameAndCategory($position['nama'], 'guru');
            }

            if ($existing === null && $matchByLevel) {
                $existing = self::findByLevel($targetLevel);
            }

            if ($existing === null) {
                $timestamp = date('Y-m-d H:i:s');
                self::create([
                    'nama' => $position['nama'],
                    'deskripsi' => $position['deskripsi'],
                    'level' => $targetLevel,
                    'kategori' => 'guru',
                    'assigns_user_role' => $assignsRole,
                    'requires_major' => (int) (!empty($position['requires_major'])),
                    'is_system' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                continue;
            }

            $updates = [];

            if ((int) ($existing['level'] ?? 0) !== $targetLevel) {
                $updates['level'] = $targetLevel;
            }

            if (($existing['kategori'] ?? '') !== 'guru') {
                $updates['kategori'] = 'guru';
            }

            $currentAssignsRole = $existing['assigns_user_role'] ?? null;
            if (is_string($currentAssignsRole)) {
                $currentAssignsRole = trim($currentAssignsRole);
                if ($currentAssignsRole === '') {
                    $currentAssignsRole = null;
                }
            } elseif ($currentAssignsRole !== null) {
                $currentAssignsRole = (string) $currentAssignsRole;
                if ($currentAssignsRole === '') {
                    $currentAssignsRole = null;
                }
            }

            if ($currentAssignsRole !== $assignsRole) {
                $updates['assigns_user_role'] = $assignsRole;
            }

            if ((int) ($existing['is_system'] ?? 0) !== 1) {
                $updates['is_system'] = 1;
            }

            $requiresMajor = (int) (!empty($position['requires_major']));
            if ((int) ($existing['requires_major'] ?? 0) !== $requiresMajor) {
                $updates['requires_major'] = $requiresMajor;
            }

            if (!isset($existing['deskripsi']) || trim((string) $existing['deskripsi']) === '') {
                $updates['deskripsi'] = $position['deskripsi'];
            }

            if (!empty($updates)) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                self::updateById($existing['id'], $updates);
            }
        }
    }

    public static function nextLevel(): int
    {
        $statement = static::connection()->query('SELECT MAX(level) FROM jabatan_akademik WHERE level IS NOT NULL');

        if ($statement === false) {
            return 1;
        }

        $max = $statement->fetchColumn();

        $numericMax = $max !== false && $max !== null ? (int) $max : 0;
        $candidate = $numericMax + 1;

        if ($candidate <= 0) {
            $candidate = 1;
        }

        while (self::levelExists($candidate)) {
            $candidate++;
        }

        return $candidate;
    }

    public static function findByAssignsRole(string $role): ?array
    {
        if ($role === '') {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM jabatan_akademik WHERE assigns_user_role = :role LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':role', $role);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function findByLevel(int $level): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM jabatan_akademik WHERE level = :level LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':level', $level, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function findByNameAndCategory(string $name, string $category = 'guru'): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM jabatan_akademik WHERE nama = :name AND kategori = :category LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':name', $name);
        $statement->bindValue(':category', $category);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function levelExists(int $level): bool
    {
        $statement = static::connection()->prepare(
            'SELECT COUNT(*) FROM jabatan_akademik WHERE level = :level'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bindValue(':level', $level, PDO::PARAM_INT);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count !== false && (int) $count > 0;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::allOrdered() as $row) {
            $options[$row['id']] = $row['nama'];
        }

        return $options;
    }
}
