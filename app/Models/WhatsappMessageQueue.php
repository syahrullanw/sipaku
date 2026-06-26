<?php

namespace App\Models;

use Core\Model;
use DateTimeImmutable;
use PDO;
use Throwable;

class WhatsappMessageQueue extends Model
{
    protected static ?string $table = 'whatsapp_message_queue';

    public static function findActiveByHash(string $hash): ?array
    {
        $statement = static::connection()->prepare(
            'SELECT * FROM whatsapp_message_queue WHERE message_hash = :hash AND status IN (\'pending\', \'processing\') ORDER BY available_at ASC, id ASC LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':hash', $hash);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function latestPendingAvailableAt(): ?string
    {
        $statement = static::connection()->query(
            "SELECT MAX(available_at) FROM whatsapp_message_queue WHERE status IN ('pending','processing')"
        );

        if ($statement === false) {
            return null;
        }

        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public static function latestSentAt(): ?string
    {
        $statement = static::connection()->query(
            "SELECT MAX(sent_at) FROM whatsapp_message_queue WHERE status = 'sent'"
        );

        if ($statement === false) {
            return null;
        }

        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public static function createPending(array $attributes): ?int
    {
        return static::createAndReturnId($attributes);
    }

    public static function createProcessing(array $attributes): ?int
    {
        return static::createAndReturnId($attributes);
    }

    public static function claimDue(): ?array
    {
        $pdo = static::connection();
        $now = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare(
                "SELECT * FROM whatsapp_message_queue WHERE status = 'pending' AND available_at <= :now ORDER BY available_at ASC, id ASC LIMIT 1 FOR UPDATE"
            );

            if ($select === false) {
                $pdo->rollBack();

                return null;
            }

            $select->bindValue(':now', $now);
            $select->execute();

            $row = $select->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $pdo->commit();

                return null;
            }

            $update = $pdo->prepare(
                "UPDATE whatsapp_message_queue SET status = 'processing', attempts = attempts + 1, last_attempt_at = :now, updated_at = :now WHERE id = :id"
            );

            if ($update === false) {
                $pdo->rollBack();

                return null;
            }

            $update->bindValue(':now', $now);
            $update->bindValue(':id', $row['id']);
            $update->execute();

            if ((int) $update->rowCount() === 0) {
                $pdo->rollBack();

                return null;
            }

            $pdo->commit();

            $row['status'] = 'processing';
            $row['attempts'] = ((int) ($row['attempts'] ?? 0)) + 1;
            $row['last_attempt_at'] = $now;

            return $row;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    public static function markSent(int $id, ?string $response, ?int $statusCode): void
    {
        $timestamp = date('Y-m-d H:i:s');

        static::updateById($id, [
            'status' => 'sent',
            'sent_at' => $timestamp,
            'last_response' => $response,
            'response_status' => $statusCode,
            'last_error' => null,
            'updated_at' => $timestamp,
        ]);
    }

    public static function markRetry(int $id, DateTimeImmutable $availableAt, string $error, ?string $response, ?int $statusCode): void
    {
        $timestamp = date('Y-m-d H:i:s');

        static::updateById($id, [
            'status' => 'pending',
            'available_at' => $availableAt->format('Y-m-d H:i:s'),
            'last_error' => $error,
            'last_response' => $response,
            'response_status' => $statusCode,
            'updated_at' => $timestamp,
        ]);
    }

    public static function markFailed(int $id, string $error, ?string $response, ?int $statusCode): void
    {
        $timestamp = date('Y-m-d H:i:s');

        static::updateById($id, [
            'status' => 'failed',
            'last_error' => $error,
            'last_response' => $response,
            'response_status' => $statusCode,
            'updated_at' => $timestamp,
        ]);
    }

    public static function latest(int $limit = 20): array
    {
        $limit = max(1, $limit);
        $statement = static::connection()->prepare(
            'SELECT * FROM whatsapp_message_queue ORDER BY created_at DESC, id DESC LIMIT :limit'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public static function countByStatus(string $status): int
    {
        $statement = static::connection()->prepare(
            'SELECT COUNT(*) FROM whatsapp_message_queue WHERE status = :status'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':status', $status);
        $statement->execute();
        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }
}
