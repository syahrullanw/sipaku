<?php

namespace App\Services\Finance;

use App\Models\AccountabilityReport;
use App\Models\ActivityFund;
use App\Models\UnexpectedExpense;
use App\Services\ManagedFileStorage;
use RuntimeException;

class AccountabilityReportService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function submit(string $entityType, int $entityId, array $data): int
    {
        if (!in_array($entityType, ['dana_kegiatan', 'pengeluaran_tak_terduga'], true)) {
            throw new RuntimeException('Tipe entitas LPJ tidak dikenal.');
        }

        if ($entityId <= 0) {
            throw new RuntimeException('Entitas LPJ tidak valid.');
        }

        $title = trim((string) ($data['judul'] ?? ''));
        $note = trim((string) ($data['deskripsi'] ?? ''));
        $amount = static::normalizeAmount((string) ($data['nominal'] ?? '0'));
        $reportedAt = static::normalizeDateTime((string) ($data['tanggal'] ?? ''));
        $userId = isset($data['dibuat_oleh']) ? (int) $data['dibuat_oleh'] : null;
        $file = $data['lampiran'] ?? null;

        if ($title === '') {
            throw new RuntimeException('Judul LPJ harus diisi.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nominal LPJ harus lebih dari nol.');
        }

        static::assertEntityExists($entityType, $entityId);

        $existing = AccountabilityReport::findByEntity($entityType, $entityId);
        $now = date('Y-m-d H:i:s');
        $relativePath = $existing['bukti_path'] ?? null;

        if (is_array($file)) {
            $relativePath = static::handleFileUpload($file, $relativePath);
        }

        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'judul' => $title,
            'deskripsi' => $note === '' ? null : $note,
            'nominal' => $amount,
            'tanggal' => $reportedAt,
            'bukti_path' => $relativePath,
            'dibuat_oleh' => $userId,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            AccountabilityReport::updateById($existing['id'], $payload);

            return (int) $existing['id'];
        }

        $payload['created_at'] = $now;

        $reportId = AccountabilityReport::createAndReturnId($payload);

        if ($reportId === null) {
            throw new RuntimeException('Gagal menyimpan LPJ.');
        }

        return $reportId;
    }

    private static function assertEntityExists(string $entityType, int $entityId): void
    {
        $exists = match ($entityType) {
            'dana_kegiatan' => ActivityFund::find($entityId),
            'pengeluaran_tak_terduga' => UnexpectedExpense::find($entityId),
            default => null,
        };

        if ($exists === null) {
            throw new RuntimeException('Entitas LPJ tidak ditemukan.');
        }
    }

    private static function normalizeAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d,\.]/', '', $raw);

        if ($clean === null || $clean === '') {
            return 0.0;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif ($lastDot !== false && ($lastComma === false || $lastDot > $lastComma)) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(['.', ','], '', $clean);
        }

        return (float) $clean;
    }

    private static function normalizeDateTime(string $input): string
    {
        if ($input === '') {
            return date('Y-m-d H:i:s');
        }

        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d H:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $input);
            if ($parsed instanceof \DateTimeInterface) {
                if ($format === 'Y-m-d') {
                    $parsed = $parsed->setTime((int) date('H'), (int) date('i'));
                }

                return $parsed->format('Y-m-d H:i:s');
            }
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed>|null $file
     */
    private static function handleFileUpload(?array $file, ?string $previousPath): ?string
    {
        if ($file === null) {
            return $previousPath;
        }

        $error = $file['error'] ?? \UPLOAD_ERR_NO_FILE;

        if ($error === \UPLOAD_ERR_NO_FILE) {
            return $previousPath;
        }

        if ($error !== \UPLOAD_ERR_OK) {
            throw new RuntimeException('Gagal mengunggah lampiran LPJ.');
        }

        $tmpName = $file['tmp_name'] ?? '';

        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Berkas LPJ tidak valid.');
        }

        $size = filesize($tmpName);
        $maxSizeKb = (int) config('finance.max_receipt_size_kb', 2048);

        if ($size !== false && $size > $maxSizeKb * 1024) {
            throw new RuntimeException('Lampiran LPJ melebihi batas ukuran ' . $maxSizeKb . ' KB.');
        }

        $allowedMimes = (array) config('finance.allowed_receipt_mimetypes', []);
        $mime = mime_content_type($tmpName);

        if (!is_string($mime) || $mime === '' || !in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Lampiran LPJ harus berupa PDF atau gambar (JPG/PNG).');
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
                default => 'dat',
            };
        }

        $stored = ManagedFileStorage::storeUploadedStorage($file, 'keuangan', 'lpj', 'lpj', $extension, [
            'existing_path' => $previousPath,
        ]);

        if ($stored === null) {
            throw new RuntimeException('Gagal menyimpan lampiran LPJ.');
        }

        return $stored['relative'];
    }
}
