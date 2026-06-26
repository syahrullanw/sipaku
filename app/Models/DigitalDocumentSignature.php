<?php

namespace App\Models;

use Core\Model;
use PDO;

class DigitalDocumentSignature extends Model
{
    protected static ?string $table = 'digital_document_signatures';

    public static function findByDocument(int $schoolYearId, string $documentType, string $documentKey): ?array
    {
        $sql = <<<SQL
SELECT *
FROM digital_document_signatures
WHERE tahun_ajaran_id = :year_id
  AND document_type = :document_type
  AND document_key = :document_key
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);
        $statement->bindValue(':document_key', $documentKey);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public static function findByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $sql = <<<SQL
SELECT *
FROM digital_document_signatures
WHERE signature_token = :token
LIMIT 1
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(':token', $token);

        if (!$statement->execute()) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * Ensure a digital signature record exists and is up-to-date for the given document.
     *
     * @param array<string, mixed> $payload
     */
    public static function ensure(
        int $schoolYearId,
        string $documentType,
        string $documentKey,
        string $documentTitle,
        array $payload,
        ?int $studentId = null,
        ?int $classId = null,
        ?int $requestedBy = null
    ): ?array {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payloadJson === false) {
            return null;
        }

        $payloadHash = hash('sha256', $payloadJson);

        $existing = static::findByDocument($schoolYearId, $documentType, $documentKey);
        $existingPayload = null;

        if ($existing !== null && isset($existing['payload']) && is_string($existing['payload'])) {
            $decodedPayload = json_decode($existing['payload'], true);

            if (is_array($decodedPayload)) {
                $existingPayload = $decodedPayload;
            }
        }

        if ($existing === null) {
            static::create([
                'tahun_ajaran_id' => $schoolYearId,
                'document_type' => $documentType,
                'document_key' => $documentKey,
                'document_title' => $documentTitle,
                'student_id' => $studentId,
                'class_id' => $classId,
                'status' => 'pending',
                'payload' => $payloadJson,
                'payload_hash' => $payloadHash,
                'requested_by' => $requestedBy,
            ]);

            return static::findByDocument($schoolYearId, $documentType, $documentKey);
        }

        $updates = [];

        if ((string) ($existing['document_title'] ?? '') !== $documentTitle) {
            $updates['document_title'] = $documentTitle;
        }

        $existingStudentId = isset($existing['student_id']) ? (int) $existing['student_id'] : null;
        $existingClassId = isset($existing['class_id']) ? (int) $existing['class_id'] : null;

        if ($existingStudentId !== $studentId) {
            $updates['student_id'] = $studentId;
        }

        if ($existingClassId !== $classId) {
            $updates['class_id'] = $classId;
        }

        if ((string) ($existing['payload_hash'] ?? '') !== $payloadHash) {
            $updates['payload'] = $payloadJson;
            $updates['payload_hash'] = $payloadHash;

            $shouldResetApproval = ($existing['status'] ?? 'pending') === 'approved';

            if (
                $shouldResetApproval
                && is_array($existingPayload)
                && array_key_exists('requested_at', $existingPayload)
            ) {
                $shouldResetApproval = false;
            }

            if ($shouldResetApproval) {
                $updates['status'] = 'pending';
                $updates['signature_token'] = null;
                $updates['approved_at'] = null;
                $updates['approved_by'] = null;
                $updates['approval_note'] = null;
            }
        }

        if (($existing['status'] ?? 'pending') === 'pending' && $requestedBy !== null && $requestedBy > 0) {
            $previousRequestedBy = isset($existing['requested_by']) ? (int) $existing['requested_by'] : 0;

            if ($previousRequestedBy !== $requestedBy) {
                $updates['requested_by'] = $requestedBy;
            }
        }

        if (!empty($updates)) {
            static::updateById($existing['id'], $updates);
        }

        return static::findByDocument($schoolYearId, $documentType, $documentKey);
    }

    public static function revokeForDocument(
        int $schoolYearId,
        string $documentType,
        string $documentKey,
        ?string $note = null,
        ?int $revokedBy = null
    ): void {
        $sql = <<<SQL
UPDATE digital_document_signatures
SET status = 'revoked',
    signature_token = NULL,
    approved_at = NULL,
    approved_by = NULL,
    approval_note = :note,
    requested_by = IFNULL(requested_by, :revoked_by),
    updated_at = NOW()
WHERE tahun_ajaran_id = :year_id
  AND document_type = :document_type
  AND document_key = :document_key
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return;
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);
        $statement->bindValue(':document_key', $documentKey);
        $statement->bindValue(':note', $note);
        $statement->bindValue(':revoked_by', $revokedBy, $revokedBy !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $statement->execute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForYear(int $schoolYearId, ?string $status = null): array
    {
        $sql = <<<SQL
SELECT
    dds.*,
    s.nama AS student_name,
    s.nipd AS student_nipd,
    s.nisn AS student_nisn,
    k.nama AS class_name,
    k.tingkat AS class_level
FROM digital_document_signatures dds
LEFT JOIN siswa s ON s.id = dds.student_id
LEFT JOIN kelas k ON k.id = dds.class_id
WHERE dds.tahun_ajaran_id = :year_id
SQL;

        $bindings = [':year_id' => $schoolYearId];

        if ($status !== null) {
            $sql .= ' AND dds.status = :status';
            $bindings[':status'] = $status;
        }

        $sql .= ' ORDER BY dds.updated_at DESC';

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function mapByClass(int $schoolYearId, int $classId, string $documentType): array
    {
        if ($schoolYearId <= 0 || $classId <= 0 || $documentType === '') {
            return [];
        }

        $sql = <<<SQL
SELECT
    dds.*,
    s.nama AS student_name,
    s.nipd AS student_nipd,
    s.nisn AS student_nisn
FROM digital_document_signatures dds
LEFT JOIN siswa s ON s.id = dds.student_id
WHERE dds.tahun_ajaran_id = :year_id
  AND dds.class_id = :class_id
  AND dds.document_type = :document_type
ORDER BY s.nama ASC
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $studentId = isset($row['student_id']) ? (int) $row['student_id'] : 0;

            if ($studentId <= 0) {
                continue;
            }

            $map[$studentId] = $row;
        }

        return $map;
    }

    /**
     * @return array<int>
     */
    public static function pendingIdsByClass(int $schoolYearId, int $classId, string $documentType): array
    {
        if ($schoolYearId <= 0 || $classId <= 0 || $documentType === '') {
            return [];
        }

        $sql = <<<SQL
SELECT dds.id
FROM digital_document_signatures dds
WHERE dds.tahun_ajaran_id = :year_id
  AND dds.class_id = :class_id
  AND dds.document_type = :document_type
  AND dds.status = 'pending'
SQL;

        $statement = static::connection()->prepare($sql);

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':year_id', $schoolYearId, PDO::PARAM_INT);
        $statement->bindValue(':class_id', $classId, PDO::PARAM_INT);
        $statement->bindValue(':document_type', $documentType);

        if (!$statement->execute()) {
            return [];
        }

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($rows === false) {
            return [];
        }

        return array_map(static fn ($value) => (int) $value, $rows);
    }

    public static function findWithRelations(int $id): ?array
    {
        $sql = <<<SQL
SELECT
    dds.*,
    s.nama AS student_name,
    s.nipd AS student_nipd,
    s.nisn AS student_nisn,
    k.nama AS class_name,
    k.tingkat AS class_level
FROM digital_document_signatures dds
LEFT JOIN siswa s ON s.id = dds.student_id
LEFT JOIN kelas k ON k.id = dds.class_id
WHERE dds.id = :id
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
