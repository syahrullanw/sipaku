<?php

namespace App\Models;

use Core\Model;
use PDO;

class SubjectAssessmentSetting extends Model
{
    protected static ?string $table = 'pengaturan_penilaian_mapel';

    public static function findByAssignment(int $assignmentId): ?array
    {
        if ($assignmentId <= 0) {
            return null;
        }

        $statement = static::connection()->prepare(
            'SELECT * FROM pengaturan_penilaian_mapel WHERE guru_mata_pelajaran_id = :assignment LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':assignment', $assignmentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    public static function ensureDefault(int $assignmentId): ?array
    {
        if ($assignmentId <= 0) {
            return null;
        }

        $existing = static::findByAssignment($assignmentId);

        if ($existing !== null) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');

        $payload = [
            'guru_mata_pelajaran_id' => $assignmentId,
            'enable_keterampilan' => 1,
            'enable_kkm' => 0,
            'nilai_kkm' => 75,
            'bobot_manual' => 0,
            'bobot_kd' => 25,
            'bobot_uts' => 35,
            'bobot_uas' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $statement = static::connection()->prepare(
            'INSERT INTO pengaturan_penilaian_mapel (guru_mata_pelajaran_id, enable_keterampilan, enable_kkm, nilai_kkm, bobot_manual, bobot_kd, bobot_uts, bobot_uas, created_at, updated_at)
             VALUES (:guru_mata_pelajaran_id, :enable_keterampilan, :enable_kkm, :nilai_kkm, :bobot_manual, :bobot_kd, :bobot_uts, :bobot_uas, :created_at, :updated_at)'
        );

        if ($statement === false) {
            return null;
        }

        foreach ($payload as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        if (!$statement->execute()) {
            return null;
        }

        return static::findByAssignment($assignmentId);
    }

    /**
     * @param array<int> $assignmentIds
     * @return array<int, array<string, mixed>>
     */
    public static function mapByAssignments(array $assignmentIds): array
    {
        $filteredIds = array_values(array_filter(array_unique(array_map(
            static fn ($id) => (int) $id,
            $assignmentIds
        )), static fn (int $id) => $id > 0));

        if ($filteredIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($filteredIds as $index => $assignmentId) {
            $placeholders[] = ':assignment_' . $index;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE guru_mata_pelajaran_id IN (%s)',
            static::table(),
            implode(', ', $placeholders)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($filteredIds as $index => $assignmentId) {
            $statement->bindValue(':assignment_' . $index, $assignmentId, PDO::PARAM_INT);
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
            $assignmentId = (int) ($row['guru_mata_pelajaran_id'] ?? 0);
            if ($assignmentId <= 0) {
                continue;
            }

            $map[$assignmentId] = $row;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function upsertForAssignment(int $assignmentId, array $attributes): bool
    {
        if ($assignmentId <= 0) {
            return false;
        }

        $attributes['guru_mata_pelajaran_id'] = $assignmentId;
        $attributes['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($attributes['created_at'])) {
            $attributes['created_at'] = $attributes['updated_at'];
        }

        $columns = array_keys($attributes);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);
        $updates = array_map(static fn ($column) => sprintf('%s = VALUES(%s)', $column, $column), array_diff($columns, ['guru_mata_pelajaran_id']));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            static::table(),
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return false;
        }

        foreach ($attributes as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }

        return $statement->execute();
    }

    /**
     * @return array{weight_kd: float, weight_uts: float, weight_uas: float}
     */
    public static function resolveWeights(?array $setting): array
    {
        $default = [
            'weight_kd' => 0.25,
            'weight_uts' => 0.35,
            'weight_uas' => 0.40,
        ];

        if ($setting === null) {
            return $default;
        }

        $isManual = (int) ($setting['bobot_manual'] ?? 0) === 1;
        $kd = (float) ($setting['bobot_kd'] ?? 25);
        $uts = (float) ($setting['bobot_uts'] ?? 35);
        $uas = (float) ($setting['bobot_uas'] ?? 40);

        if (!$isManual) {
            return $default;
        }

        $total = $kd + $uts + $uas;

        if ($total <= 0.0) {
            return $default;
        }

        return [
            'weight_kd' => $kd / 100,
            'weight_uts' => $uts / 100,
            'weight_uas' => $uas / 100,
        ];
    }
}
