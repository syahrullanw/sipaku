<?php

namespace App\Services;

class LedgerExporter
{
    /**
     * @param array<string, mixed> $assignment
     * @param array<string, mixed>|null $class
     * @param array<string, mixed> $setting
     * @param array<int, array<string, mixed>> $rows
     */
    public static function makePdf(
        array $assignment,
        ?array $class,
        array $setting,
        array $rows,
        bool $hasSkill,
        bool $isKurmer = false
    ): string {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');

        $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'));
        $subjectCode = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
        $classLabel = $class !== null
            ? trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')))
            : '';
        $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
        $kkmValue = $setting['nilai_kkm'] ?? null;

        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->SetTitle(self::convert("Legger Nilai {$subjectName}"));
        $pdf->SetAuthor(self::convert((string) config('app.name', 'Aplikasi Sekolah')));
        $leftMargin = 10;
        $topMargin = 12;
        $rightMargin = 10;
        $bottomMargin = 12;
        $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
        $pdf->SetAutoPageBreak(true, $bottomMargin);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, self::convert('Legger Nilai Mata Pelajaran'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, self::convert($subjectName . ($subjectCode !== '' ? ' (' . $subjectCode . ')' : '')), 0, 1, 'C');

        $infoLine = [];
        if ($classLabel !== '') {
            $infoLine[] = 'Kelas: ' . $classLabel;
        }
        $infoLine[] = 'Diunduh: ' . date('d/m/Y H:i');
        if ($kkmEnabled && $kkmValue !== null) {
            $infoLine[] = 'KKM: ' . number_format((float) $kkmValue, 2, ',', '.');
        }

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, self::convert(implode(' | ', $infoLine)), 0, 1, 'C');
        $pdf->Ln(2);

        $columns = self::buildColumns($hasSkill, $isKurmer);

        $cellMargin = 2.0;
        if (method_exists($pdf, 'SetCellMargin')) {
            $cellMargin = 1.5;
            $pdf->SetCellMargin($cellMargin);
        }
        $pdf->SetDrawColor(188, 195, 206);

        $headerLineHeight = 6.0;
        $bodyLineHeight = 5.0;

        $pdf->SetFont('Arial', 'B', 8);
        self::renderTableHeader($pdf, $columns, $headerLineHeight, $cellMargin);

        $pdf->SetFont('Arial', '', 8);

        foreach ($rows as $index => $row) {
            $prepared = [];
            $maxLines = 1;

            foreach ($columns as $column) {
                $text = call_user_func($column['value'], $row, $index);
                $converted = self::convert($text);
                $lines = self::countLines($pdf, $column['width'], $converted, $cellMargin);
                $maxLines = max($maxLines, $lines);
                $prepared[] = [
                    'text' => $converted,
                    'width' => $column['width'],
                    'align' => $column['align'],
                ];
            }

            $rowHeight = $bodyLineHeight * $maxLines;

            $pageBottom = $pdf->GetPageHeight() - $bottomMargin;
            if ($pdf->GetY() + $rowHeight > $pageBottom) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 8);
                self::renderTableHeader($pdf, $columns, $headerLineHeight, $cellMargin);
                $pdf->SetFont('Arial', '', 8);
            }

            $startX = $pdf->GetX();
            $startY = $pdf->GetY();
            $currentX = $startX;

            foreach ($prepared as $cell) {
                $pdf->SetXY($currentX, $startY);
                $pdf->Rect($currentX, $startY, $cell['width'], $rowHeight);
                $pdf->MultiCell($cell['width'], $bodyLineHeight, $cell['text'], 0, $cell['align']);
                $currentX += $cell['width'];
                $pdf->SetXY($currentX, $startY);
            }

            $pdf->SetXY($startX, $startY + $rowHeight);
        }

        return (string) $pdf->Output('S');
    }

    /**
     * @param array<string, mixed> $assignment
     * @param array<string, mixed>|null $class
     * @param array<string, mixed> $setting
     * @param array<int, array<string, mixed>> $rows
     */
    public static function makeExcel(
        array $assignment,
        ?array $class,
        array $setting,
        array $rows,
        bool $hasSkill,
        bool $isKurmer = false
    ): string {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'));
        $subjectCode = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
        $classLabel = $class !== null
            ? trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')))
            : '';
        $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
        $kkmValue = $setting['nilai_kkm'] ?? null;

        $subjectLine = $subjectName . ($subjectCode !== '' ? ' (' . $subjectCode . ')' : '');

        $headerRows = [
            '<tr><th align="left">Legger Nilai Mata Pelajaran</th></tr>',
            '<tr><td>' . self::escapeHtml($subjectLine) . '</td></tr>',
        ];

        $metaParts = [];
        if ($classLabel !== '') {
            $metaParts[] = 'Kelas: ' . $classLabel;
        }
        $metaParts[] = 'Diunduh: ' . date('d/m/Y H:i');
        if ($kkmEnabled && $kkmValue !== null) {
            $metaParts[] = 'KKM: ' . number_format((float) $kkmValue, 2, ',', '.');
        }

        $headerRows[] = '<tr><td>' . self::escapeHtml(implode(' | ', $metaParts)) . '</td></tr>';

        $content = "\xEF\xBB\xBF";
        $content .= '<meta charset="UTF-8">';
        $content .= '<table border="0" cellspacing="0" cellpadding="2">' . implode('', $headerRows) . '</table><br>';

        $content .= '<table border="1" cellspacing="0" cellpadding="4">';
        $columns = self::buildColumns($hasSkill, $isKurmer);

        $content .= '<tr>';
        foreach ($columns as $column) {
            $colspan = isset($column['colspan']) ? (int) $column['colspan'] : 1;
            $rowspan = isset($column['rowspan']) ? (int) $column['rowspan'] : 1;
            $content .= sprintf(
                '<th%s%s>%s</th>',
                $colspan > 1 ? ' colspan="' . $colspan . '"' : '',
                $rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '',
                self::escapeHtml($column['label'])
            );
        }
        $content .= '</tr>';

        foreach ($rows as $index => $row) {
            $content .= '<tr>';
            foreach ($columns as $column) {
                $align = isset($column['align']) ? strtolower($column['align']) : 'l';
                $alignAttr = $align === 'c' ? ' align="center"' : ($align === 'r' ? ' align="right"' : '');
                $value = call_user_func($column['value'], $row, $index);
                $content .= '<td' . $alignAttr . '>' . self::escapeHtml($value) . '</td>';
            }
            $content .= '</tr>';
        }

        $content .= '</table>';

        return $content;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildColumns(bool $hasSkill, bool $isKurmer): array
    {
        if ($isKurmer) {
            return [
                [
                    'label' => 'No',
                    'width' => 10,
                    'align' => 'C',
                    'value' => static fn (array $row, int $index): string => (string) ($index + 1),
                ],
                [
                    'label' => 'Nama Siswa',
                    'width' => 46,
                    'align' => 'L',
                    'value' => static fn (array $row): string => (string) ($row['student']['nama'] ?? '-'),
                ],
                [
                    'label' => 'Capaian Akhir',
                    'width' => 20,
                    'align' => 'C',
                    'value' => static fn (array $row): string => self::formatText($row['kurmer_summary']['capaian_akhir_enum'] ?? $row['kurmer_summary']['capaian'] ?? '-'),
                ],
                [
                    'label' => 'Deskripsi Utama',
                    'width' => 70,
                    'align' => 'L',
                    'value' => static fn (array $row): string => self::formatDescriptionPlain($row['kurmer_summary']['deskripsi_umum'] ?? $row['kurmer_summary']['description'] ?? null),
                ],
                [
                    'label' => 'Tindak Lanjut',
                    'width' => 40,
                    'align' => 'L',
                    'value' => static fn (array $row): string => self::formatDescriptionPlain($row['kurmer_summary']['tindak_lanjut'] ?? null),
                ],
                [
                    'label' => 'Nilai Opsional',
                    'width' => 20,
                    'align' => 'C',
                    'value' => static fn (array $row): string => self::formatScore($row['kurmer_summary']['nilai_opsional'] ?? $row['kurmer_summary']['score'] ?? null),
                ],
            ];
        }

        $columns = [
            [
                'label' => 'No',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row, int $index): string => (string) ($index + 1),
            ],
            [
                'label' => 'Nama Siswa',
                'width' => 46,
                'align' => 'L',
                'value' => static fn (array $row): string => (string) ($row['student']['nama'] ?? '-'),
            ],
            [
                'label' => 'Nilai KD',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatScore($row['knowledge']['nilai_kd'] ?? null),
            ],
            [
                'label' => 'UTS',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatScore($row['knowledge']['nilai_uts'] ?? null),
            ],
            [
                'label' => 'UAS',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatScore($row['knowledge']['nilai_uas'] ?? null),
            ],
            [
                'label' => 'Nilai Akhir (P)',
                'width' => 20,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatScore($row['knowledge']['nilai_akhir'] ?? null),
            ],
            [
                'label' => 'Predikat (P)',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatText($row['knowledge']['predikat'] ?? '-'),
            ],
            [
                'label' => 'Deskripsi Pengetahuan',
                'width' => 44,
                'align' => 'L',
                'value' => static fn (array $row): string => self::formatDescriptionPlain($row['knowledge']['deskripsi'] ?? null),
            ],
        ];

        if ($hasSkill) {
            $columns[] = [
                'label' => 'Nilai Akhir (K)',
                'width' => 20,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatScore($row['skill']['nilai_akhir'] ?? null),
            ];
            $columns[] = [
                'label' => 'Predikat (K)',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => self::formatText($row['skill']['predikat'] ?? '-'),
            ];
            $columns[] = [
                'label' => 'Deskripsi Keterampilan',
                'width' => 54,
                'align' => 'L',
                'value' => static fn (array $row): string => self::formatDescriptionPlain($row['skill']['deskripsi'] ?? null),
            ];
        }

        return $columns;
    }

    private static function renderTableHeader(\FPDF $pdf, array $columns, float $lineHeight, float $cellMargin): void
    {
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();

        $cells = [];
        $maxLines = 1;

        foreach ($columns as $column) {
            $converted = self::convert($column['label']);
            $lines = self::countLines($pdf, $column['width'], $converted, $cellMargin);
            $maxLines = max($maxLines, $lines);
            $cells[] = [
                'text' => $converted,
                'width' => $column['width'],
            ];
        }

        $height = $lineHeight * $maxLines;

        $pdf->SetFillColor(240, 244, 255);
        $pdf->SetDrawColor(180, 186, 198);

        $currentX = $startX;
        foreach ($cells as $cell) {
            $pdf->SetXY($currentX, $startY);
            $pdf->Rect($currentX, $startY, $cell['width'], $height, 'DF');
            $pdf->MultiCell($cell['width'], $lineHeight, $cell['text'], 0, 'C');
            $currentX += $cell['width'];
            $pdf->SetXY($currentX, $startY);
        }

        $pdf->SetXY($startX, $startY + $height);
        $pdf->SetDrawColor(188, 195, 206);
    }

    private static function countLines(\FPDF $pdf, float $width, string $text, float $cellMargin): int
    {
        if ($width <= 0) {
            return 1;
        }

        $available = max(1.0, $width - (2 * $cellMargin));
        $text = str_replace("\r", '', $text);
        $chunks = explode("\n", $text);
        $lineCount = 0;
        $spaceWidth = $pdf->GetStringWidth(' ');

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                $lineCount++;
                continue;
            }

            $currentWidth = 0.0;
            $words = preg_split('/\s+/u', $chunk) ?: [];

            foreach ($words as $word) {
                $wordWidth = $pdf->GetStringWidth($word);

                if ($currentWidth === 0.0) {
                    $currentWidth = $wordWidth;
                    continue;
                }

                if ($currentWidth + $spaceWidth + $wordWidth <= $available) {
                    $currentWidth += $spaceWidth + $wordWidth;
                } else {
                    $lineCount++;
                    $currentWidth = $wordWidth;
                }
            }

            $lineCount++;
        }

        return max(1, $lineCount);
    }

    private static function formatScore(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $floatValue = (float) $value;

        if (abs($floatValue - round($floatValue)) < 0.01) {
            return number_format($floatValue, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($floatValue, 2, ',', '.'), '0'), ',');
    }

    private static function formatText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? '-' : $text;
    }

    private static function formatDescription(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '-';
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return str_replace(["\r\n", "\r", "\n"], '<br>', $escaped);
    }

    private static function formatDescriptionPlain(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? '-' : $text;
    }

    private static function convert(string $text): string
    {
        return (string) mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

}
