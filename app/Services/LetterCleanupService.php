<?php

namespace App\Services;

use App\Support\DigitalDocumentTypes;
use Core\Database;
use InvalidArgumentException;
use PDO;

class LetterCleanupService
{
    private const DATASETS = [
        'outgoing_letters' => [
            'label' => 'Surat Keluar',
            'description' => 'Menghapus data surat keluar beserta metadata nomor surat dan lampiran internal pada tahun ajaran terpilih.',
            'scope' => 'year',
        ],
        'incoming_letters' => [
            'label' => 'Surat Masuk',
            'description' => 'Menghapus data surat masuk termasuk agenda penerimaan dan arsip dokumen pada tahun ajaran terpilih.',
            'scope' => 'year',
        ],
        'letter_signatures' => [
            'label' => 'Antrian TTD Digital Surat',
            'description' => 'Menghapus riwayat permintaan dan persetujuan tanda tangan digital untuk persuratan pada tahun ajaran terpilih.',
            'scope' => 'year',
        ],
    ];

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::connection();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function datasets(): array
    {
        return self::DATASETS;
    }

    /**
     * @return array<string, int>
     */
    public function countAll(int $yearId): array
    {
        if ($yearId <= 0) {
            return array_fill_keys(array_keys(self::DATASETS), 0);
        }

        return [
            'outgoing_letters' => $this->countOutgoingLetters($yearId),
            'incoming_letters' => $this->countIncomingLetters($yearId),
            'letter_signatures' => $this->countLetterSignatures($yearId),
        ];
    }

    /**
     * @param array<int, string> $datasetKeys
     * @return array<string, array<string, int>>
     */
    public function clean(int $yearId, array $datasetKeys): array
    {
        if ($yearId <= 0) {
            throw new InvalidArgumentException('Tahun ajaran tidak valid.');
        }

        $definitions = self::DATASETS;
        $normalized = [];

        foreach ($datasetKeys as $key) {
            $key = (string) $key;

            if ($key === '' || !isset($definitions[$key])) {
                continue;
            }

            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        if (empty($normalized)) {
            return [];
        }

        $report = [];

        $this->connection->beginTransaction();

        try {
            foreach ($normalized as $dataset) {
                $deleted = 0;

                switch ($dataset) {
                    case 'outgoing_letters':
                        $deleted = $this->deleteOutgoingLetters($yearId);
                        break;
                    case 'incoming_letters':
                        $deleted = $this->deleteIncomingLetters($yearId);
                        break;
                    case 'letter_signatures':
                        $deleted = $this->deleteLetterSignatures($yearId);
                        break;
                }

                $report[$dataset] = [
                    'deleted' => $deleted,
                ];
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $report;
    }

    private function countOutgoingLetters(int $yearId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM surat_keluar WHERE tahun_ajaran_id = :year'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    private function countIncomingLetters(int $yearId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM surat_masuk WHERE tahun_ajaran_id = :year'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    private function countLetterSignatures(int $yearId): int
    {
        $letterTypes = DigitalDocumentTypes::letterTypes();

        $placeholders = implode(', ', array_fill(0, count($letterTypes), '?'));

        $sql = <<<SQL
SELECT COUNT(*)
FROM digital_document_signatures
WHERE tahun_ajaran_id = ?
  AND document_type IN ($placeholders)
SQL;

        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $index = 1;
        $statement->bindValue($index++, $yearId, PDO::PARAM_INT);

        foreach ($letterTypes as $type) {
            $statement->bindValue($index++, $type);
        }

        if (!$statement->execute()) {
            return 0;
        }

        $result = $statement->fetchColumn();

        return $result === false ? 0 : (int) $result;
    }

    private function deleteOutgoingLetters(int $yearId): int
    {
        $statement = $this->connection->prepare(
            'DELETE FROM surat_keluar WHERE tahun_ajaran_id = :year'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        return $statement->rowCount();
    }

    private function deleteIncomingLetters(int $yearId): int
    {
        $statement = $this->connection->prepare(
            'DELETE FROM surat_masuk WHERE tahun_ajaran_id = :year'
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bindValue(':year', $yearId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return 0;
        }

        return $statement->rowCount();
    }

    private function deleteLetterSignatures(int $yearId): int
    {
        $letterTypes = DigitalDocumentTypes::letterTypes();

        if (empty($letterTypes)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($letterTypes), '?'));

        $sql = <<<SQL
DELETE FROM digital_document_signatures
WHERE tahun_ajaran_id = ?
  AND document_type IN ($placeholders)
SQL;

        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            return 0;
        }

        $index = 1;
        $statement->bindValue($index++, $yearId, PDO::PARAM_INT);

        foreach ($letterTypes as $type) {
            $statement->bindValue($index++, $type);
        }

        if (!$statement->execute()) {
            return 0;
        }

        return $statement->rowCount();
    }
}
