<?php

namespace App\Services;

use App\Support\SimpleXlsxBuilder;

class StudentExporter
{
    /**
     * @param array<int, array<string, mixed>> $students
     */
    public static function toPdf(array $students, string $statusLabel): string
    {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');

        $pdf = new \FPDF('L', 'mm', 'A4');
        $leftMargin = 5;
        $rightMargin = 5;
        $topMargin = 7;
        $bottomMargin = 7;
        $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
        $pdf->SetAutoPageBreak(true, $bottomMargin);
        $pdf->AddPage();
        $pdf->SetY($topMargin);

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, self::convert('Daftar Siswa'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 10);
        $infoParts = [
            'Status: ' . $statusLabel,
            'Ekspor: ' . date('d M Y H:i'),
        ];
        $pdf->MultiCell(0, 5, self::convert(implode(' | ', array_filter($infoParts))), 0, 'C');
        $pdf->Ln(2);

        $columns = [
            ['label' => 'No', 'width' => 8, 'align' => 'C', 'value' => static fn (array $row, int $index): string => (string) ($index + 1)],
            ['label' => 'Nama', 'width' => 46, 'align' => 'L', 'value' => static fn (array $row): string => $row['nama']],
            ['label' => 'Kelas', 'width' => 16, 'align' => 'L', 'value' => static fn (array $row): string => $row['kelas']],
            ['label' => 'NIPD', 'width' => 22, 'align' => 'L', 'value' => static fn (array $row): string => $row['nipd']],
            ['label' => 'NISN', 'width' => 22, 'align' => 'L', 'value' => static fn (array $row): string => $row['nisn']],
            ['label' => 'NIK', 'width' => 32, 'align' => 'L', 'value' => static fn (array $row): string => $row['nik']],
            ['label' => 'Tempat Lahir', 'width' => 30, 'align' => 'L', 'value' => static fn (array $row): string => $row['tempat_lahir']],
            ['label' => 'Tanggal Lahir', 'width' => 30, 'align' => 'C', 'value' => static fn (array $row): string => $row['tanggal_lahir']],
            ['label' => 'Nama Ayah', 'width' => 40, 'align' => 'L', 'value' => static fn (array $row): string => $row['ayah']],
            ['label' => 'Nama Ibu', 'width' => 40, 'align' => 'L', 'value' => static fn (array $row): string => $row['ibu']],
        ];

        $headerHeight = 7.0;
        $lineHeight = 5.2;
        $cellMargin = 1.5;
        $pdf->SetDrawColor(190, 196, 205);
        $pdf->SetFont('Arial', 'B', 9);
        self::renderHeader($pdf, $columns, $headerHeight);

        $pdf->SetFont('Arial', '', 8.5);

        foreach ($students as $index => $student) {
            $row = self::normalizeStudentForPdf($student);
            $cells = [];
            $maxLines = 1;

            foreach ($columns as $column) {
                $value = call_user_func($column['value'], $row, $index);
                $converted = self::convert(trim((string) $value));
                $lines = self::countLines($pdf, $column['width'], $converted, $cellMargin);
                $maxLines = max($maxLines, $lines);
                $cells[] = [
                    'text' => $converted === '' ? '-' : $converted,
                    'width' => $column['width'],
                    'align' => $column['align'],
                ];
            }

            $rowHeight = $lineHeight * $maxLines;
            if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $bottomMargin) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 9);
                self::renderHeader($pdf, $columns, $headerHeight);
                $pdf->SetFont('Arial', '', 8.5);
            }

            $rowStartX = $pdf->GetX();
            $rowStartY = $pdf->GetY();
            $currentX = $rowStartX;

            foreach ($cells as $cell) {
                $pdf->SetXY($currentX, $rowStartY);
                $pdf->Rect($currentX, $rowStartY, $cell['width'], $rowHeight);
                $pdf->MultiCell($cell['width'], $lineHeight, $cell['text'], 0, $cell['align']);
                $currentX += $cell['width'];
                $pdf->SetXY($currentX, $rowStartY);
            }

            $pdf->SetXY($rowStartX, $rowStartY + $rowHeight);
        }

        return (string) $pdf->Output('S');
    }

    /**
     * @param array<int, array<string, mixed>> $students
     */
    public static function toExcel(array $students): string
    {
        $rows = [];
        $columns = [];

        if (!empty($students)) {
            $columns = array_keys($students[0]);
        }

        if (!empty($columns)) {
            $labels = array_map(static fn (string $column): string => self::labelizeColumn($column), $columns);
            $rows[] = array_merge(['No'], $labels);
        } else {
            $rows[] = ['No'];
        }

        foreach ($students as $index => $student) {
            $row = [$index + 1];
            foreach ($columns as $column) {
                $value = $student[$column] ?? '';
                if ($column === 'tanggal_lahir') {
                    $value = self::formatDate($value);
                }
                $row[] = self::formatCell($value);
            }
            if (empty($columns)) {
                $row = [$index + 1];
            }
            $rows[] = $row;
        }

        return SimpleXlsxBuilder::build($rows, 'Daftar Siswa');
    }

    /**
     * @param array<string, mixed> $student
     */
    private static function normalizeStudentForPdf(array $student): array
    {
        return [
            'nama' => self::valueOrDash($student['nama'] ?? ''),
            'kelas' => self::valueOrDash($student['kelas_nama'] ?? ''),
            'nipd' => self::valueOrDash($student['nipd'] ?? ''),
            'nisn' => self::valueOrDash($student['nisn'] ?? ''),
            'nik' => self::valueOrDash($student['nik'] ?? ''),
            'tempat_lahir' => self::valueOrDash($student['tempat_lahir'] ?? ''),
            'tanggal_lahir' => self::formatDate($student['tanggal_lahir'] ?? ''),
            'ayah' => self::valueOrDash($student['ayah_nama'] ?? ''),
            'ibu' => self::valueOrDash($student['ibu_nama'] ?? ''),
        ];
    }

    private static function formatDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '-';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $raw;
        }

        return date('d/m/Y', $timestamp);
    }

    private static function renderHeader(\FPDF $pdf, array $columns, float $height): void
    {
        foreach ($columns as $column) {
            $pdf->Cell($column['width'], $height, self::convert($column['label']), 1, 0, 'C');
        }
        $pdf->Ln();
    }

    private static function convert(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
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

    private static function valueOrDash(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? '-' : $text;
    }

    private static function labelizeColumn(string $column): string
    {
        $clean = str_replace('_', ' ', $column);
        return ucwords($clean);
    }

    /**
     * @param scalar|null $value
     */
    private static function formatCell($value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
