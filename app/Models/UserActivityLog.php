<?php

namespace App\Models;

use Core\Model;
use PDO;

class UserActivityLog extends Model
{
    protected static ?string $table = 'user_activity_logs';

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     pagination: array{
     *         page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int
     *     }
     * }
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $connection = static::connection();
        $conditions = [];
        $bindings = [];

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $conditions[] = '(actor_name LIKE :keyword OR actor_username LIKE :keyword OR request_path LIKE :keyword OR action_description LIKE :keyword OR ip_address LIKE :keyword OR user_agent LIKE :keyword OR route_action LIKE :keyword)';
            $bindings[':keyword'] = '%' . $keyword . '%';
        }

        $method = strtoupper(trim((string) ($filters['method'] ?? '')));
        if ($method !== '') {
            $conditions[] = 'request_method = :method';
            $bindings[':method'] = $method;
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $conditions[] = 'actor_role = :role';
            $bindings[':role'] = $role;
        }

        $statusRange = trim((string) ($filters['status_range'] ?? ''));
        if ($statusRange === 'none') {
            $conditions[] = 'status_code IS NULL';
        } elseif ($statusRange !== '') {
            $range = static::statusRange($statusRange);
            if ($range !== null) {
                [$min, $max] = $range;
                $conditions[] = 'status_code BETWEEN :status_min AND :status_max';
                $bindings[':status_min'] = $min;
                $bindings[':status_max'] = $max;
            }
        }

        $hasError = (string) ($filters['has_error'] ?? '');
        if ($hasError === '1') {
            $conditions[] = "(error_message IS NOT NULL AND error_message <> '')";
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $conditions[] = 'created_at >= :date_from';
            $bindings[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $conditions[] = 'created_at <= :date_to';
            $bindings[':date_to'] = $dateTo . ' 23:59:59';
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $orderClause = 'ORDER BY created_at DESC, id DESC';

        $countSql = 'SELECT COUNT(*) FROM ' . static::table() . ' ' . $whereClause;
        $total = 0;
        $countStatement = $connection->prepare($countSql);

        if ($countStatement !== false) {
            foreach ($bindings as $key => $value) {
                $countStatement->bindValue($key, $value);
            }

            if ($countStatement->execute()) {
                $count = $countStatement->fetchColumn();
                $total = $count === false ? 0 : (int) $count;
            }
        }

        $perPage = max(1, min($perPage, 200));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = sprintf(
            'SELECT * FROM %s %s %s LIMIT :limit OFFSET :offset',
            static::table(),
            $whereClause,
            $orderClause
        );

        $data = [];
        $statement = $connection->prepare($sql);

        if ($statement !== false) {
            foreach ($bindings as $key => $value) {
                $statement->bindValue($key, $value);
            }

            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

            if ($statement->execute()) {
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $data = $rows === false ? [] : $rows;
            }
        }

        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private static function statusRange(string $key): ?array
    {
        return match ($key) {
            '2xx' => [200, 299],
            '3xx' => [300, 399],
            '4xx' => [400, 499],
            '5xx' => [500, 599],
            default => null,
        };
    }

    public static function enforceLimit(int $maxRecords): void
    {
        $maxRecords = max(0, $maxRecords);

        if ($maxRecords <= 0) {
            return;
        }

        $total = static::count();

        if ($total <= $maxRecords) {
            return;
        }

        $surplus = $total - $maxRecords;
        $connection = static::connection();
        $sql = sprintf('DELETE FROM %s ORDER BY created_at ASC, id ASC LIMIT :limit', static::table());
        $statement = $connection->prepare($sql);

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':limit', $surplus, PDO::PARAM_INT);
        $statement->execute();
    }
}
