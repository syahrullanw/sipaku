<?php

namespace App\Models;

use Core\Model;
use PDO;

class OutgoingLetterAttachment extends Model
{
    protected static ?string $table = 'surat_keluar_lampiran';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listForLetter(int $letterId): array
    {
        $statement = static::connection()->prepare(
            'SELECT nomor, isi_html, isi_text FROM surat_keluar_lampiran WHERE surat_keluar_id = :letter_id ORDER BY nomor ASC'
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':letter_id', $letterId, PDO::PARAM_INT);

        if (!$statement->execute()) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @throws \Throwable
     */
    public static function replaceForLetter(int $letterId, array $attachments): void
    {
        $connection = static::connection();
        $connection->beginTransaction();

        try {
            $delete = $connection->prepare('DELETE FROM surat_keluar_lampiran WHERE surat_keluar_id = :letter_id');

            if ($delete === false) {
                throw new \RuntimeException('Gagal mempersiapkan penghapusan lampiran.');
            }

            $delete->bindValue(':letter_id', $letterId, PDO::PARAM_INT);
            $delete->execute();

            if (!empty($attachments)) {
                $insert = $connection->prepare(
                    'INSERT INTO surat_keluar_lampiran (surat_keluar_id, nomor, isi_html, isi_text, created_at, updated_at) VALUES (:letter_id, :number, :html, :text, :created_at, :updated_at)'
                );

                if ($insert === false) {
                    throw new \RuntimeException('Gagal mempersiapkan penyimpanan lampiran.');
                }

                $now = date('Y-m-d H:i:s');

                foreach ($attachments as $attachment) {
                    $number = isset($attachment['number']) ? (int) $attachment['number'] : 0;
                    $html = (string) ($attachment['body_html'] ?? '');
                    $text = (string) ($attachment['body_text'] ?? '');

                    $insert->bindValue(':letter_id', $letterId, PDO::PARAM_INT);
                    $insert->bindValue(':number', $number, PDO::PARAM_INT);
                    $insert->bindValue(':html', $html);
                    $insert->bindValue(':text', $text);
                    $insert->bindValue(':created_at', $now);
                    $insert->bindValue(':updated_at', $now);

                    if (!$insert->execute()) {
                        throw new \RuntimeException('Gagal menyimpan lampiran surat.');
                    }
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}

