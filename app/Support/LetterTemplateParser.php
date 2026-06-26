<?php

namespace App\Support;

use RuntimeException;

class LetterTemplateParser
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    /**
     * Parse important fields from an uploaded letter template.
     *
     * @return array{tujuan:?string, perihal:?string, tembusan:?string, isi:?string}
     */
    public static function parseFile(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : self::guessExtension($originalName);

        $text = match ($extension) {
            'docx' => self::readDocx($path),
            'txt', 'text' => self::readPlainText($path),
            default => null,
        };

        if ($text === null || trim($text) === '') {
            if (!in_array($extension, ['docx', 'txt', 'text'], true)) {
                throw new RuntimeException('Format template belum didukung. Gunakan berkas DOCX atau TXT.');
            }

            throw new RuntimeException('Tidak dapat membaca isi template surat.');
        }

        return self::extractFields($text);
    }

    public static function assertAllowedSize(int $size): void
    {
        if ($size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Ukuran file terlalu besar. Gunakan file maksimal 5MB.');
        }
    }

    private static function readDocx(string $path): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Server tidak mendukung pembacaan file DOCX.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            return null;
        }

        $xml = preg_replace('/<w:br[^>]*>/', "\n", $xml);
        $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $xml = preg_replace('/<w:tab[^>]*>/', "\t", $xml);

        $text = strip_tags((string) $xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return self::normalizeText($text);
    }

    private static function readPlainText(string $path): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return self::normalizeText($contents);
    }

    /**
     * @return array{tujuan:?string, perihal:?string, tembusan:?string, isi:?string}
     */
    private static function extractFields(string $text): array
    {
        $normalized = self::normalizeText($text);
        $lines = preg_split("/\n/u", $normalized) ?: [];

        $tujuan = null;
        $perihal = null;
        $tembusan = null;
        $tembusanLines = [];
        $bodyStartIndex = null;
        $bodyEndIndex = null;

        $tujuanIndex = null;
        $perihalIndex = null;
        $tembusanIndex = null;

        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $lower = mb_strtolower($trimmed, 'UTF-8');

            if ($perihal === null && preg_match('/^(perihal|hal)\s*[:\-]\s*(.+)$/iu', $trimmed, $matches)) {
                $perihal = trim($matches[2]);
                $perihalIndex = $i;
                continue;
            }

            if ($tujuan === null) {
                if (preg_match('/^(tujuan(\s+surat)?|kepada)\s*[:\-]\s*(.+)$/iu', $trimmed, $matches)) {
                    $tujuan = trim($matches[3]);
                    $tujuanIndex = $i;
                } elseif (preg_match('/^kepada\s+yth\.?\s*(.+)$/iu', $trimmed, $matches)) {
                    $tujuan = trim($matches[1], " .,;\t");
                    $tujuanIndex = $i;
                } elseif (preg_match('/^yth\.?\s*(.+)$/iu', $trimmed, $matches)) {
                    $tujuan = trim($matches[1], " .,;\t");
                    $tujuanIndex = $i;
                } elseif (str_starts_with($lower, 'kepada')) {
                    $tujuan = trim(preg_replace('/^kepada\s+/iu', '', $trimmed));
                    $tujuanIndex = $i;
                }
            }

            if ($tembusan === null && str_starts_with($lower, 'tembusan')) {
                $tembusanIndex = $i;

                $lineValue = trim(preg_replace('/^tembusan\s*[:\-]?\s*/iu', '', $trimmed));

                if ($lineValue !== '') {
                    $tembusanLines[] = self::cleanListItem($lineValue);
                }

                for ($j = $i + 1; $j < $lineCount; $j++) {
                    $next = trim($lines[$j]);

                    if ($next === '') {
                        break;
                    }

                    if (preg_match('/^(perihal|hal)\s*[:\-]/iu', $next)) {
                        break;
                    }

                    $tembusanLines[] = self::cleanListItem($next);
                }

                $tembusanLines = array_values(array_filter(
                    $tembusanLines,
                    static fn (string $value): bool => $value !== ''
                ));

                if ($tembusanLines !== []) {
                    $tembusanLines = array_unique($tembusanLines);
                    $tembusan = implode("\n", $tembusanLines);
                }
            }
        }

        if ($perihalIndex !== null) {
            for ($i = $perihalIndex + 1; $i < $lineCount; $i++) {
                if (trim($lines[$i]) !== '') {
                    $bodyStartIndex = $i;
                    break;
                }
            }
        }

        if ($bodyStartIndex === null && $tujuanIndex !== null) {
            for ($i = $tujuanIndex + 1; $i < $lineCount; $i++) {
                if (trim($lines[$i]) !== '') {
                    $bodyStartIndex = $i;
                    break;
                }
            }
        }

        if ($bodyStartIndex === null) {
            $bodyStartIndex = 0;
        }

        for ($i = $bodyStartIndex; $i < $lineCount; $i++) {
            $probe = mb_strtolower(trim($lines[$i]), 'UTF-8');

            if ($probe === '') {
                continue;
            }

            if (str_contains($probe, 'dengan hormat')) {
                $bodyStartIndex = $i;
                break;
            }
        }

        for ($i = $lineCount - 1; $i >= $bodyStartIndex; $i--) {
            $probe = mb_strtolower(trim($lines[$i]), 'UTF-8');

            if ($probe === '') {
                continue;
            }

            if (str_contains($probe, 'hormat kami') || str_contains($probe, 'ttd')) {
                $bodyEndIndex = $i - 1;
                break;
            }
        }

        if ($bodyEndIndex === null) {
            $bodyEndIndex = $lineCount - 1;
        }

        while ($bodyEndIndex > $bodyStartIndex && trim($lines[$bodyEndIndex]) === '') {
            $bodyEndIndex--;
        }

        $bodySlice = array_slice($lines, $bodyStartIndex, $bodyEndIndex - $bodyStartIndex + 1);
        $body = trim(implode("\n", array_map(static fn ($line) => rtrim($line), $bodySlice)));
        $body = $body === '' ? null : preg_replace("/\n{3,}/u", "\n\n", $body);

        return [
            'tujuan' => $tujuan !== null ? self::sanitizeLine($tujuan) : null,
            'perihal' => $perihal !== null ? self::sanitizeLine($perihal) : null,
            'tembusan' => $tembusan !== null ? self::sanitizeMultiline($tembusan) : null,
            'isi' => self::convertBodyToHtml($body),
        ];
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\xc2\xa0", ' ', $text); // replace non-breaking space
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;

        return trim($text);
    }

    private static function sanitizeLine(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;

        return $value;
    }

    private static function sanitizeMultiline(string $value): string
    {
        $lines = preg_split("/\n/u", $value) ?: [];
        $lines = array_map(static fn ($line) => self::sanitizeLine($line), $lines);

        return implode("\n", array_filter($lines, static fn ($line) => $line !== ''));
    }

    private static function cleanListItem(string $value): string
    {
        $cleaned = preg_replace('/^[\-\*\d\.\)\(]+\s*/u', '', $value);

        return self::sanitizeLine($cleaned ?? $value);
    }

    private static function guessExtension(string $name): string
    {
        $lower = strtolower($name);

        if (str_ends_with($lower, '.docx')) {
            return 'docx';
        }

        if (str_ends_with($lower, '.txt')) {
            return 'txt';
        }

        return '';
    }

    private static function convertBodyToHtml(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $cleaned = self::sanitizeMultiline($body);

        if ($cleaned === '') {
            return null;
        }

        $lines = preg_split("/\n/u", $cleaned) ?: [];
        $blocks = [];
        $listType = null;
        $listItems = [];

        $flushList = static function () use (&$blocks, &$listItems, &$listType): void {
            if ($listItems === []) {
                return;
            }

            $tag = $listType === 'ol' ? 'ol' : 'ul';
            $items = implode('', array_map(
                static fn (string $item): string => '<li>' . self::escapeHtml($item) . '</li>',
                $listItems
            ));

            $blocks[] = sprintf('<%1$s>%2$s</%1$s>', $tag, $items);
            $listItems = [];
            $listType = null;
        };

        foreach ($lines as $line) {
            $normalized = str_replace(["\xc2\xa0", '&nbsp;', '&amp;nbsp;'], ' ', $line);
            $trimmed = trim($normalized);

            if ($trimmed === '') {
                $flushList();

                continue;
            }

            if (preg_match('/^(?:-|\*|•)\s+(.+)$/u', $trimmed, $match)) {
                $content = self::sanitizeLine((string) ($match[1] ?? ''));

                if ($content !== '') {
                    if ($listType !== 'ul') {
                        $flushList();
                        $listType = 'ul';
                    }

                    $listItems[] = $content;
                }

                continue;
            }

            if (preg_match('/^(?:[0-9]+|[a-z])[\.\)]\s+(.+)$/iu', $trimmed, $match)) {
                $content = self::sanitizeLine((string) ($match[1] ?? ''));

                if ($content !== '') {
                    if ($listType !== 'ol') {
                        $flushList();
                        $listType = 'ol';
                    }

                    $listItems[] = $content;
                }

                continue;
            }

            $flushList();
            $blocks[] = '<p>' . self::escapeHtml($trimmed) . '</p>';
        }

        $flushList();

        return $blocks === [] ? null : implode("\n", $blocks);
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
