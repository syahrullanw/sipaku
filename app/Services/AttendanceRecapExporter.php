<?php

namespace App\Services;

class AttendanceRecapExporter
{
    /**
     * @param array<int, array<string, mixed>> $sessions
     * @param array<string, mixed> $filters
     * @param array<string, int> $totals
     */
    public static function teacherPdf(
        array $teacher,
        array $sessions,
        array $filters,
        array $totals
    ): string {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');

        $teacherName = trim((string) ($teacher['name'] ?? ($teacher['username'] ?? 'Guru')));
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $classLabel = $filters['class_label'] ?? null;
        $subjectLabel = $filters['subject_label'] ?? null;

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle(self::convert('Rekap Presensi Siswa'));
        $pdf->SetAuthor(self::convert((string) config('app.name', 'Aplikasi Sekolah')));
        $leftMargin = 12;
        $topMargin = 16;
        $rightMargin = 12;
        $bottomMargin = 14;
        $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
        $pdf->SetAutoPageBreak(true, $bottomMargin);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, self::convert('Rekap Presensi Siswa'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 11);
        $infoParts = ['Guru: ' . $teacherName];
        if ($startDate !== null && $endDate !== null) {
            $infoParts[] = 'Periode: ' . self::formatDate($startDate) . ' s.d. ' . self::formatDate($endDate);
        } elseif ($startDate !== null) {
            $infoParts[] = 'Mulai: ' . self::formatDate($startDate);
        } elseif ($endDate !== null) {
            $infoParts[] = 'Sampai: ' . self::formatDate($endDate);
        }

        if ($classLabel !== null && $classLabel !== '') {
            $infoParts[] = 'Kelas: ' . $classLabel;
        }

        if ($subjectLabel !== null && $subjectLabel !== '') {
            $infoParts[] = 'Mapel: ' . $subjectLabel;
        }

        $pdf->MultiCell(0, 6, self::convert(implode(' | ', $infoParts)), 0, 'C');
        $pdf->Ln(2);

        if (!empty($totals)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, self::convert('Ringkasan Status'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $labelMap = [
                'hadir' => 'Hadir',
                'izin' => 'Izin',
                'sakit' => 'Sakit',
                'bolos' => 'Bolos',
                'alpa' => 'Tanpa Keterangan',
            ];
            $summary = [];
            foreach ($totals as $status => $count) {
                $summary[] = ($labelMap[$status] ?? ucfirst($status)) . ': ' . (int) $count;
            }
            $pdf->MultiCell(0, 5, self::convert(implode('  |  ', $summary)), 0, 'L');
            $pdf->Ln(2);
        }

        $columns = [
            [
                'label' => 'No',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row, int $index): string => (string) ($index + 1),
            ],
            [
                'label' => 'Tanggal',
                'width' => 25,
                'align' => 'L',
                'value' => static fn (array $row): string => self::formatDate((string) ($row['tanggal'] ?? '')),
            ],
            [
                'label' => 'Mapel',
                'width' => 38,
                'align' => 'L',
                'value' => static fn (array $row): string => (string) ($row['mata_pelajaran_nama'] ?? '-'),
            ],
            [
                'label' => 'Kelas',
                'width' => 26,
                'align' => 'L',
                'value' => static fn (array $row): string => self::formatClass($row),
            ],
            [
                'label' => 'Agenda',
                'width' => 55,
                'align' => 'L',
                'value' => static function (array $row): string {
                    $agenda = (string) ($row['agenda'] ?? '-');
                    if ((string) ($row['tipe_sesi'] ?? 'jadwal') !== 'pengganti') {
                        return $agenda;
                    }

                    $scheduledTeacher = trim((string) ($row['guru_jadwal_nama'] ?? ''));
                    $note = trim((string) ($row['catatan_pengganti'] ?? ''));
                    $parts = ['Guru Pengganti'];
                    if ($scheduledTeacher !== '') {
                        $parts[] = 'jadwal asli: ' . $scheduledTeacher;
                    }
                    if ($note !== '') {
                        $parts[] = $note;
                    }

                    return $agenda . ' | ' . implode(' | ', $parts);
                },
            ],
            [
                'label' => 'H',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row): string => (string) ($row['total_hadir'] ?? 0),
            ],
            [
                'label' => 'I',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row): string => (string) ($row['total_izin'] ?? 0),
            ],
            [
                'label' => 'S',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row): string => (string) ($row['total_sakit'] ?? 0),
            ],
            [
                'label' => 'B',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row): string => (string) ($row['total_bolos'] ?? 0),
            ],
            [
                'label' => 'A',
                'width' => 10,
                'align' => 'C',
                'value' => static fn (array $row): string => (string) ($row['total_alpa'] ?? 0),
            ],
        ];

        $headerHeight = 7.0;
        $lineHeight = 5.5;
        $cellMargin = method_exists($pdf, 'SetCellMargin') ? 1.5 : 2.0;
        if (method_exists($pdf, 'SetCellMargin')) {
            $pdf->SetCellMargin($cellMargin);
        }

        $pdf->SetDrawColor(190, 196, 205);
        $pdf->SetFont('Arial', 'B', 9);
        self::renderHeader($pdf, $columns, $headerHeight);

        $pdf->SetFont('Arial', '', 8.5);

        foreach ($sessions as $index => $session) {
            $prepared = [];
            $maxLines = 1;

            foreach ($columns as $column) {
                $value = call_user_func($column['value'], $session, $index);
                $converted = self::convert($value);
                $lines = self::countLines($pdf, $column['width'], $converted, $cellMargin);
                $maxLines = max($maxLines, $lines);
                $prepared[] = [
                    'text' => $converted,
                    'width' => $column['width'],
                    'align' => $column['align'],
                ];
            }

            $rowHeight = $lineHeight * $maxLines;

            $pageBottom = $pdf->GetPageHeight() - $bottomMargin;
            if ($pdf->GetY() + $rowHeight > $pageBottom) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 9);
                self::renderHeader($pdf, $columns, $headerHeight);
                $pdf->SetFont('Arial', '', 8.5);
            }

            $startX = $pdf->GetX();
            $startY = $pdf->GetY();
            $currentX = $startX;

            foreach ($prepared as $cell) {
                $pdf->SetXY($currentX, $startY);
                $pdf->Rect($currentX, $startY, $cell['width'], $rowHeight);
                $pdf->MultiCell($cell['width'], $lineHeight, $cell['text'], 0, $cell['align']);
                $currentX += $cell['width'];
                $pdf->SetXY($currentX, $startY);
            }

            $pdf->SetXY($startX, $startY + $rowHeight);
        }

        return (string) $pdf->Output('S');
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

    private static function formatDate(string $date): string
    {
        if ($date === '') {
            return '-';
        }

        $time = strtotime($date);

        return $time === false ? $date : date('d M Y', $time);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function formatClass(array $row): string
    {
        $format = static function (string $grade, string $name, string $major): string {
            $grade = trim($grade);
            $name = trim($name);
            $label = trim($grade . ' ' . $name);
            $major = trim($major);

            if ($major !== '') {
                $label = trim($label . ' (' . $major . ')');
            }

            return $label !== '' ? $label : '-';
        };

        $primary = $format(
            (string) ($row['kelas_tingkat'] ?? ''),
            (string) ($row['kelas_nama'] ?? ''),
            (string) ($row['jurusan_nama'] ?? '')
        );
        $parallel = $format(
            (string) ($row['kelas_paralel_tingkat'] ?? ''),
            (string) ($row['kelas_paralel_nama'] ?? ''),
            (string) ($row['jurusan_paralel_nama'] ?? '')
        );

        if ($parallel !== '-' && $parallel !== $primary) {
            if ($primary === '-') {
                return $parallel;
            }

            return trim($primary . ' + ' . $parallel);
        }

        return $primary !== '' ? $primary : '-';
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
}
