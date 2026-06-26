<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class OutgoingLetterPdfService
{
    private const STORAGE_DIR = 'uploads/surat-keluar';

    /**
     * Store uploaded PDF file to public directory.
     *
     * @param array<string, mixed>|null $file
     * @return string|false|null Relative path on success, false on error, null when no file provided.
     */
    public static function storeUploadedPdf(?array $file, ?string $existingPath = null): string|false|null
    {
        if ($file === null) {
            return null;
        }

        $error = $file['error'] ?? \UPLOAD_ERR_NO_FILE;

        if ($error === \UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== \UPLOAD_ERR_OK) {
            return false;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return false;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($mime !== '' && !str_contains($mime, 'pdf')) {
            return null;
        }

        $stored = ManagedFileStorage::storeUploadedPublic($file, 'persuratan', 'surat-keluar', 'surat', 'pdf', [
            'existing_path' => $existingPath,
        ]);

        return $stored ?? false;
    }

    private static function sanitizePath(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        // limit to relative public paths
        $path = ltrim($path, '/');
        if (strlen($path) > 200) {
            $path = substr($path, 0, 200);
        }

        return $path === '' ? null : $path;
    }

    /**
     * Normalize signature placement options from request input.
     *
     * @param array<string, mixed>|null $input
     * @return array<string, float|int>
     */
    public static function normalizeSignatureOptions(?array $input): array
    {
        $page = isset($input['page']) ? (int) $input['page'] : 1;
        $xPercent = isset($input['x_percent']) ? (float) $input['x_percent'] : 70.0;
        $yPercent = isset($input['y_percent']) ? (float) $input['y_percent'] : 65.0;
        $widthPercent = isset($input['width_percent']) ? (float) $input['width_percent'] : 20.0;
        $city = isset($input['city']) ? self::sanitizeText($input['city'], 120) : null;
        $titimangsa = isset($input['titimangsa']) ? self::sanitizeText($input['titimangsa'], 120) : null;
        $headmaster = isset($input['headmaster_name']) ? self::sanitizeText($input['headmaster_name'], 150) : null;
        $metaTitle = isset($input['signature_meta_title']) ? self::sanitizeText($input['signature_meta_title'], 120) : null;
        $metaNote = isset($input['signature_meta_note']) ? self::sanitizeText($input['signature_meta_note'], 200) : null;
        $useLetterhead = isset($input['use_letterhead']) ? (bool) $input['use_letterhead'] : false;
        $letterheadPath = isset($input['letterhead_path']) ? self::sanitizePath($input['letterhead_path']) : null;
        $schoolName = isset($input['school_name']) ? self::sanitizeText($input['school_name'], 180) : null;

        $page = max(1, $page);
        $xPercent = self::clampPercent($xPercent);
        $yPercent = self::clampPercent($yPercent);
        $widthPercent = self::clampPercent($widthPercent, 5.0, 60.0);

        return [
            'page' => $page,
            'x_percent' => $xPercent,
            'y_percent' => $yPercent,
            'width_percent' => $widthPercent,
            'city' => $city,
            'titimangsa' => $titimangsa,
            'headmaster_name' => $headmaster,
            'signature_mode' => 'metadata',
            'signature_meta_title' => $metaTitle,
            'signature_meta_note' => $metaNote,
            'use_letterhead' => $useLetterhead,
            'letterhead_path' => $useLetterhead ? $letterheadPath : null,
            'school_name' => $schoolName,
        ];
    }

    /**
     * Decode stored signature options string into array.
     *
     * @param string|array<string, mixed>|null $raw
     * @return array<string, float|int>|null
     */
    public static function decodeSignatureOptions(string|array|null $raw): ?array
    {
        if (is_array($raw)) {
            $normalized = self::normalizeSignatureOptions($raw);
            return $normalized;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        $normalized = self::normalizeSignatureOptions($decoded);

        foreach (['city', 'titimangsa', 'headmaster_name'] as $key) {
            if (isset($decoded[$key]) && $normalized[$key] === null) {
                $normalized[$key] = self::sanitizeText($decoded[$key], $key === 'headmaster_name' ? 150 : 120);
            }
        }

        foreach (['signature_meta_title' => 120, 'signature_meta_note' => 200] as $metaKey => $limit) {
            if (isset($decoded[$metaKey]) && (!isset($normalized[$metaKey]) || $normalized[$metaKey] === null)) {
                $normalized[$metaKey] = self::sanitizeText($decoded[$metaKey], $limit);
            }
        }

        if (!isset($normalized['use_letterhead'])) {
            $normalized['use_letterhead'] = isset($decoded['use_letterhead']) ? (bool) $decoded['use_letterhead'] : false;
        }

        if (!isset($normalized['letterhead_path']) && isset($decoded['letterhead_path'])) {
            $normalized['letterhead_path'] = self::sanitizePath($decoded['letterhead_path']);
        }

        return $normalized;
    }

    /**
     * Apply QR code onto the PDF and return the relative signed path on success.
     *
     * @param array<string, mixed> $letter
     */
    public static function applySignature(array $letter, string $qrValue, ?string $existingSignedPath = null): ?string
    {
        $pdfPath = (string) ($letter['pdf_path'] ?? '');
        $options = self::decodeSignatureOptions($letter['pdf_signature_options'] ?? null);

        if ($pdfPath === '' || $options === null) {
            return null;
        }

        $source = public_path($pdfPath);

        if (!is_file($source) || !is_readable($source)) {
            return null;
        }

        $qrImage = self::createQrImage($qrValue);

        if ($qrImage === null) {
            return null;
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');
        require_once base_path('app/Libraries/Fpdi/autoload.php');

        $pdf = new Fpdi();

        try {
            $pageCount = $pdf->setSourceFile($source);
        } catch (\Throwable) {
            @unlink($qrImage);

            return null;
        }

        $targetPage = (int) ($options['page'] ?? 1);
        $useLetterhead = isset($options['use_letterhead']) ? (bool) $options['use_letterhead'] : false;
        $letterheadPath = isset($options['letterhead_path']) ? (string) $options['letterhead_path'] : '';

        if ($targetPage > $pageCount) {
            $targetPage = $pageCount;
        }

        for ($page = 1; $page <= $pageCount; $page++) {
            try {
                $templateId = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($templateId);
            } catch (\Throwable) {
                continue;
            }

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            if ($useLetterhead && $letterheadPath !== '') {
                $absoluteLetterhead = public_path($letterheadPath);
                if (is_file($absoluteLetterhead) && is_readable($absoluteLetterhead)) {
                    try {
                        $pdf->Image($absoluteLetterhead, 0, 0, $size['width'], 0, '', '', '', false, 300);
                    } catch (\Throwable) {
                        // ignore letterhead failure
                    }
                }
            }

            if ($page === $targetPage) {
                $width = $size['width'];
                $height = $size['height'];

                $qrWidth = $width * ((float) $options['width_percent'] / 100.0);
                $originXPercent = isset($options['x_percent']) ? (float) $options['x_percent'] : 0.0;
                $originYPercent = isset($options['y_percent']) ? (float) $options['y_percent'] : 0.0;
                $city = isset($options['city']) ? (string) $options['city'] : '';
                $titimangsa = isset($options['titimangsa']) ? (string) $options['titimangsa'] : '';
                $headmasterName = isset($options['headmaster_name']) ? (string) $options['headmaster_name'] : '';
                $metaTitle = isset($options['signature_meta_title']) ? (string) $options['signature_meta_title'] : '';
                $metaNote = isset($options['signature_meta_note']) ? (string) $options['signature_meta_note'] : '';

                // hindari auto page-break memecah blok tanda tangan
                $prevAuto = $pdf->AutoPageBreak ?? true;
                $prevMargin = $pdf->bMargin ?? 0;
                $pdf->SetAutoPageBreak(false);

                // hindari auto page-break memecah blok tanda tangan
                $prevAuto = $pdf->AutoPageBreak ?? true;
                $prevMargin = $pdf->bMargin ?? 0;
                $pdf->SetAutoPageBreak(false);

                try {
                    $metaLines = [];
                    if ($city !== '' || $titimangsa !== '') {
                        $cityLine = trim($city . ($titimangsa !== '' ? ($city !== '' ? ', ' : '') . $titimangsa : ''));
                        if ($cityLine !== '') {
                            $metaLines[] = $cityLine;
                        }
                    }
                    $metaLines[] = $metaTitle !== '' ? $metaTitle : 'TTD Disetujui Kepala Sekolah';
                    $fallbackHeadmaster = $headmasterName !== '' ? $headmasterName : 'Kepala Sekolah';
                    $metaLines[] = $metaNote !== '' ? $metaNote : 'Disahkan oleh ' . $fallbackHeadmaster;
                    $metaLines = array_values(array_filter($metaLines, static fn ($line) => $line !== ''));

                    $qrWidthPercent = self::clampPercent((float) $options['width_percent'], 5.0, 60.0);
                    $blockWidthPercent = self::clampPercent(min(max($qrWidthPercent * 2.2, $qrWidthPercent + 15.0), 90.0));
                    $paddingPercent = self::clampPercent(min(max($qrWidthPercent * 0.18, 2.0), 10.0));
                    $fontSize = max(8.0, min(16.0, $qrWidthPercent * 0.4));
                    $lineHeight = max($fontSize * 0.55, 2.0);
                    $lineCount = max(count($metaLines), 2);

                    $blockWidth = $width * ($blockWidthPercent / 100.0);
                    $padding = $width * ($paddingPercent / 100.0);
                    $minTextWidth = max(45.0, $qrWidth * 0.8);
                    $minBlockWidth = $qrWidth + ($padding * 2.0) + $minTextWidth;
                    if ($blockWidth < $minBlockWidth) {
                        $blockWidth = min(max($minBlockWidth, $qrWidth + $padding * 2.0 + 12.0), $width - 10.0);
                    }
                    $textWidth = max($blockWidth - $qrWidth - ($padding * 2.0), $minTextWidth);
                    $textHeight = $lineHeight * $lineCount;
                    $blockHeight = max($qrWidth, $textHeight + $padding * 2.0);
                    $blockHeight = max($blockHeight, $qrWidth + 6.0);
                    $maxBlockHeight = max($height - 10.0, $qrWidth + 6.0);
                    if ($blockHeight > $maxBlockHeight) {
                        $blockHeight = $maxBlockHeight;
                    }

                    $originX = $width * (self::clampPercent($originXPercent, 0.0, 100.0) / 100.0);
                    $originY = $height * (self::clampPercent($originYPercent, 0.0, 100.0) / 100.0);
                    $maxOriginX = max(5.0, $width - $blockWidth - 5.0);
                    $maxOriginY = max(5.0, $height - $blockHeight - 5.0);
                    $blockX = max(5.0, min($maxOriginX, $originX));
                    $blockY = max(5.0, min($maxOriginY, $originY));

                    $qrX = $blockX + $padding;
                    $qrY = $blockY + $padding;
                    $textX = $qrX + $qrWidth + $padding;
                    $textY = $qrY;

                    $pdf->Image($qrImage, $qrX, $qrY, $qrWidth, $qrWidth, 'PNG');
                    $pdf->SetTextColor(33, 43, 54);
                    $pdf->SetFont('Arial', '', $fontSize);

                    foreach ($metaLines as $idx => $line) {
                        $lineY = $textY + ($idx * $lineHeight);
                        $pdf->SetXY($textX, $lineY);
                        $pdf->MultiCell($textWidth, $lineHeight, $line, 0, 'L');
                    }
                } catch (\Throwable) {
                    // ignore drawing failures
                }

                // kembalikan auto page-break agar halaman lain tetap normal
                $pdf->SetAutoPageBreak($prevAuto, $prevMargin);

                // kembalikan auto page-break agar halaman lain tetap normal
                $pdf->SetAutoPageBreak($prevAuto, $prevMargin);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'surat-signed-');
        if ($tempFile === false) {
            @unlink($qrImage);

            return null;
        }

        try {
            $pdf->Output($tempFile, 'F');
        } catch (\Throwable) {
            @unlink($qrImage);
            @unlink($tempFile);

            return null;
        }

        @unlink($qrImage);
        $contents = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($contents === false) {
            return null;
        }

        return ManagedFileStorage::storePublicContents($contents, 'persuratan', 'surat-keluar-ttd', 'surat-signed-' . ($letter['id'] ?? '0'), 'pdf', [
            'existing_path' => $existingSignedPath,
            'related_type' => 'surat_keluar',
            'related_id' => (int) ($letter['id'] ?? 0),
            'original_name' => 'surat-signed-' . ($letter['id'] ?? '0') . '.pdf',
        ]);
    }

    private static function deleteFile(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        ManagedFileStorage::deletePublic($path);
    }

    private static function clampPercent(float $value, float $min = 0.0, float $max = 100.0): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private static function sanitizeText(mixed $value, int $maxLength = 150): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $maxLength);
        } else {
            $text = substr($text, 0, $maxLength);
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Create QR image on temporary path.
     */
    private static function createQrImage(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'qr-img-');

        if ($temp === false) {
            return null;
        }

        require_once base_path('app/Libraries/QrCode/phpqrcode.php');

        if (!class_exists('QRCode')) {
            return null;
        }

        try {
            $qr = \QRCode::getMinimumQRCode($value, \QR_ERROR_CORRECT_LEVEL_H);
            $image = $qr->createImage(8, 2, 0x000000, 0xFFFFFF, true);
            imagepng($image, $temp);
            imagedestroy($image);
        } catch (\Throwable) {
            @unlink($temp);

            return null;
        }

        return $temp;
    }
}
