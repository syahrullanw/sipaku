<?php

namespace App\Models;

use Core\Model;
use PDO;

class PracticeProcurementRequest extends Model
{
    protected static ?string $table = 'pengadaan_praktikum';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FUNDED = 'funded';
    public const STATUS_REPORTED = 'reported';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Menunggu Review',
            self::STATUS_APPROVED => 'Disetujui Kepala Sekolah',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_FUNDED => 'Dicairkan Bendahara',
            self::STATUS_REPORTED => 'LPJ Terkirim',
        ];
    }

    public static function generateCode(int $schoolYearId): string
    {
        $connection = static::connection();
        $statement = $connection->prepare('SELECT COUNT(*) FROM ' . static::$table . ' WHERE tahun_ajaran_id = :year');

        if ($statement === false) {
            return 'PR-' . date('Y') . '-001';
        }

        $statement->bindValue(':year', $schoolYearId, PDO::PARAM_INT);
        $statement->execute();

        $count = (int) ($statement->fetchColumn() ?: 0);
        $sequence = str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);

        return sprintf('PR-%s-%s', date('Y'), $sequence);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list(array $filters = []): array
    {
        $connection = static::connection();
        $sql = <<<SQL
SELECT
    p.*,
    j.nama AS jurusan_nama,
    g.nama AS guru_nama,
    reviewer.name AS reviewer_name,
    funder.name AS funder_name
FROM pengadaan_praktikum p
JOIN jurusan j ON j.id = p.jurusan_id
JOIN guru g ON g.id = p.guru_id
LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by_user_id
LEFT JOIN users funder ON funder.id = p.funded_by_user_id
SQL;

        $clauses = [];
        $params = [];

        if (isset($filters['teacher_id'])) {
            $clauses[] = 'p.guru_id = :teacher';
            $params[':teacher'] = (int) $filters['teacher_id'];
        }

        if (isset($filters['year_id'])) {
            $clauses[] = 'p.tahun_ajaran_id = :year';
            $params[':year'] = (int) $filters['year_id'];
        }

        if (!empty($filters['jurusan_ids']) && is_array($filters['jurusan_ids'])) {
            $placeholders = [];
            foreach ($filters['jurusan_ids'] as $index => $jurusanId) {
                $placeholder = ':major_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = (int) $jurusanId;
            }

            if (!empty($placeholders)) {
                $clauses[] = 'p.jurusan_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $placeholders = [];
            foreach ($filters['statuses'] as $index => $status) {
                $placeholder = ':status_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = (string) $status;
            }

            if (!empty($placeholders)) {
                $clauses[] = 'p.status IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';

        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($params as $placeholder => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            }

            $statement->bindValue($placeholder, $value, $type);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findDetailed(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    p.*,
    j.nama AS jurusan_nama,
    g.nama AS guru_nama,
    reviewer.name AS reviewer_name,
    funder.name AS funder_name
FROM pengadaan_praktikum p
JOIN jurusan j ON j.id = p.jurusan_id
JOIN guru g ON g.id = p.guru_id
LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by_user_id
LEFT JOIN users funder ON funder.id = p.funded_by_user_id
WHERE p.id = :id
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':id', $id, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}
