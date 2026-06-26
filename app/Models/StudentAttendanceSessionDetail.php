<?php

namespace App\Models;

use App\Support\AttendanceStatus;
use Core\Model;
use PDO;

class StudentAttendanceSessionDetail extends Model
{
    protected static ?string $table = 'presensi_siswa_sesi_detail';

    public static function findForSessionAndStudent(int $sessionId, int $studentId): ?array
    {
        if ($sessionId <= 0 || $studentId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE sesi_id = :session_id AND siswa_id = :student_id LIMIT 1',
            static::table()
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return null;
        }

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bySession(int $sessionId): array
    {
        if ($sessionId <= 0) {
            return [];
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE sesi_id = :session_id',
            static::table()
        );

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $mapped = [];

        foreach ($rows as $row) {
            $studentId = (int) ($row['siswa_id'] ?? 0);

            if ($studentId <= 0) {
                continue;
            }

            $mapped[$studentId] = $row;
        }

        return $mapped;
    }

    public static function recordScan(int $sessionId, int $studentId, ?string $note = null): void
    {
        if ($sessionId <= 0 || $studentId <= 0) {
            return;
        }

        if (!Student::isActiveId($studentId)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existing = static::findForSessionAndStudent($sessionId, $studentId);
        $normalizedNote = static::normalizeNote($note);

        if ($existing !== null) {
            static::updateById($existing['id'], [
                'status' => 'hadir',
                'metode' => 'qr',
                'catatan' => $normalizedNote,
                'presensi_pada' => $now,
                'dicatat_oleh_user_id' => null,
                'updated_at' => $now,
            ]);

            return;
        }

        static::create([
            'sesi_id' => $sessionId,
            'siswa_id' => $studentId,
            'status' => 'hadir',
            'metode' => 'qr',
            'catatan' => $normalizedNote,
            'presensi_pada' => $now,
            'dicatat_oleh_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function upsertManual(
        int $sessionId,
        int $studentId,
        string $status,
        ?int $userId,
        ?string $note = null
    ): void {
        if ($sessionId <= 0 || $studentId <= 0) {
            return;
        }

        if (!Student::isActiveId($studentId)) {
            return;
        }

        $normalizedStatus = AttendanceStatus::normalize($status);
        $normalizedNote = static::normalizeNote($note);
        $now = date('Y-m-d H:i:s');
        $existing = static::findForSessionAndStudent($sessionId, $studentId);

        if ($existing !== null) {
            $currentStatus = (string) ($existing['status'] ?? '');
            $currentMethod = (string) ($existing['metode'] ?? '');
            $currentNote = $existing['catatan'] ?? null;

            if (
                $currentStatus === $normalizedStatus
                && $currentMethod === 'qr'
                && $normalizedNote === $currentNote
            ) {
                return;
            }

            $payload = [
                'status' => $normalizedStatus,
                'catatan' => $normalizedNote,
                'dicatat_oleh_user_id' => $userId,
                'updated_at' => $now,
            ];

            if ($currentMethod === 'qr' && $normalizedStatus === 'hadir' && $normalizedNote === $currentNote) {
                $payload['metode'] = 'qr';
                $payload['presensi_pada'] = $existing['presensi_pada'] ?? $now;
            } else {
                $payload['metode'] = 'manual';
                $payload['presensi_pada'] = $existing['presensi_pada'] ?? $now;
            }

            static::updateById($existing['id'], $payload);

            return;
        }

        static::create([
            'sesi_id' => $sessionId,
            'siswa_id' => $studentId,
            'status' => $normalizedStatus,
            'metode' => 'manual',
            'catatan' => $normalizedNote,
            'presensi_pada' => $now,
            'dicatat_oleh_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function countsByStatus(int $sessionId): array
    {
        if ($sessionId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT status, COUNT(*) AS total
FROM presensi_siswa_sesi_detail
WHERE sesi_id = :session_id
GROUP BY status
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $totals = [];

        foreach ($rows as $row) {
            $statusKey = isset($row['status']) ? AttendanceStatus::normalize((string) $row['status']) : null;

            if ($statusKey === null) {
                continue;
            }

            $totals[$statusKey] = (int) ($row['total'] ?? 0);
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    public static function summaryForStudent(int $studentId, string $startDate, string $endDate): array
    {
        if ($studentId <= 0) {
            return [];
        }

        $sql = <<<SQL
SELECT detail.status, COUNT(*) AS total
FROM presensi_siswa_sesi_detail detail
JOIN presensi_siswa_sesi sesi ON sesi.id = detail.sesi_id
WHERE detail.siswa_id = :student_id
  AND sesi.tanggal BETWEEN :start_date AND :end_date
GROUP BY detail.status
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $statement->bindValue(':start_date', $startDate);
        $statement->bindValue(':end_date', $endDate);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $summary = [];

        foreach ($rows as $row) {
            $statusKey = isset($row['status']) ? AttendanceStatus::normalize((string) $row['status']) : null;

            if ($statusKey === null) {
                continue;
            }

            $summary[$statusKey] = (int) ($row['total'] ?? 0);
        }

        return $summary;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $trimmed = trim($note);

        return $trimmed === '' ? null : $trimmed;
    }
}
