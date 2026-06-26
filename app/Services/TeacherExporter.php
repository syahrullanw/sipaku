<?php

namespace App\Services;

use App\Support\SimpleXlsxBuilder;
use App\Support\DemoMode;

class TeacherExporter
{
    /**
     * @param array<int, array<string, mixed>> $teachers
     */
    public static function toPdf(array $teachers, string $scopeLabel, string $statusLabel, array $options): string
    {
        if (DemoMode::isEnabled()) {
            $teachers = DemoMode::maskTeachers($teachers);
        }

        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');

        $pdf = new \FPDF('P', 'mm', 'A4');
        $leftMargin = 12;
        $rightMargin = 12;
        $topMargin = 16;
        $bottomMargin = 16;
        $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
        $pdf->SetAutoPageBreak(true, $bottomMargin);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, self::convert('Daftar Guru'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 10);
        $infoParts = [
            'Filter: ' . $scopeLabel,
            'Status: ' . $statusLabel,
            'Ekspor: ' . date('d M Y H:i'),
        ];
        $pdf->MultiCell(0, 5, self::convert(implode(' | ', array_filter($infoParts))), 0, 'C');
        $pdf->Ln(2);

        $prepared = [];
        foreach ($teachers as $teacher) {
            $prepared[] = self::normalizeTeacher($teacher, $options);
        }

        $columns = [
            [
                'label' => 'No',
                'width' => 8,
                'align' => 'C',
                'value' => static fn (array $row, int $index): string => (string) ($index + 1),
            ],
            [
                'label' => 'Nama',
                'width' => 60,
                'align' => 'L',
                'value' => static fn (array $row): string => $row['name'],
            ],
            [
                'label' => 'NIP',
                'width' => 24,
                'align' => 'L',
                'value' => static fn (array $row): string => $row['nip'],
            ],
            [
                'label' => 'Jenis GTK / Status Kepegawaian',
                'width' => 40,
                'align' => 'L',
                'value' => static fn (array $row): string => self::combineLines($row['jenis_gtk'], $row['employment_status']),
            ],
            [
                'label' => 'Status',
                'width' => 16,
                'align' => 'C',
                'value' => static fn (array $row): string => $row['status'],
            ],
            [
                'label' => 'Kontak',
                'width' => 38,
                'align' => 'L',
                'value' => static fn (array $row): string => self::combineLines($row['email'], $row['phone']),
            ],
        ];

        $headerHeight = 7.0;
        $lineHeight = 5.2;
        $cellMargin = 1.5;
        $pdf->SetDrawColor(190, 196, 205);
        $pdf->SetFont('Arial', 'B', 9);
        self::renderHeader($pdf, $columns, $headerHeight);

        $pdf->SetFont('Arial', '', 8.5);

        foreach ($prepared as $index => $row) {
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
     * @param array<int, array<string, mixed>> $teachers
     */
    public static function toExcel(array $teachers, array $options): string
    {
        if (DemoMode::isEnabled()) {
            $teachers = DemoMode::maskTeachers($teachers);
        }

        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'nip', 'label' => 'NIP'],
            ['key' => 'nomor_surat_tugas', 'label' => 'Nomor Surat Tugas'],
            ['key' => 'tanggal_surat_tugas', 'label' => 'Tanggal Surat Tugas'],
            ['key' => 'sekolah_induk', 'label' => 'Sekolah Induk'],
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'nik', 'label' => 'NIK'],
            ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin'],
            ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir'],
            ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir'],
            ['key' => 'nama_ibu_kandung', 'label' => 'Nama Ibu Kandung'],
            ['key' => 'agama', 'label' => 'Agama'],
            ['key' => 'status_perkawinan', 'label' => 'Status Perkawinan'],
            ['key' => 'nama_pasangan', 'label' => 'Nama Pasangan'],
            ['key' => 'pekerjaan_pasangan', 'label' => 'Pekerjaan Pasangan'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'telepon', 'label' => 'Telepon'],
            ['key' => 'alamat', 'label' => 'Alamat'],
            ['key' => 'npwp', 'label' => 'NPWP'],
            ['key' => 'nama_wp', 'label' => 'Nama WP'],
            ['key' => 'jenis_gtk', 'label' => 'Jenis GTK'],
            ['key' => 'nuptk', 'label' => 'NUPTK'],
            ['key' => 'status_kepegawaian', 'label' => 'Status Kepegawaian'],
            ['key' => 'sk_pengangkatan', 'label' => 'SK Pengangkatan'],
            ['key' => 'tmt_pengangkatan', 'label' => 'TMT Pengangkatan'],
            ['key' => 'lembaga_pengangkat', 'label' => 'Lembaga Pengangkat'],
            ['key' => 'kartu_pasangan', 'label' => 'Kartu Pasangan'],
            ['key' => 'pendidikan_terakhir', 'label' => 'Pendidikan Terakhir'],
            ['key' => 'status_kuliah', 'label' => 'Status Kuliah'],
            ['key' => 'tahun_pensiun', 'label' => 'Tahun Pensiun'],
            ['key' => 'tugas_tambahan', 'label' => 'Tugas Tambahan'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'username', 'label' => 'Username'],
            ['key' => 'created_at', 'label' => 'Created At'],
            ['key' => 'updated_at', 'label' => 'Updated At'],
        ];

        $rows = [];
        $headers = array_merge(['No'], array_column($columns, 'label'));
        $rows[] = $headers;

        foreach ($teachers as $index => $teacher) {
            $normalized = self::normalizeTeacher($teacher, $options);
            $row = [$index + 1];

            foreach ($columns as $column) {
                $row[] = $normalized[$column['key']] ?? '-';
            }

            $rows[] = $row;
        }

        return SimpleXlsxBuilder::build($rows, 'Daftar Guru');
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function normalizeTeacher(array $record, array $options): array
    {
        $gtkOptions = $options['gtkTypes'] ?? [];
        $employmentOptions = $options['employmentStatuses'] ?? [];
        $genderOptions = $options['genders'] ?? [];
        $religionOptions = $options['religions'] ?? [];
        $maritalOptions = $options['maritalStatuses'] ?? [];
        $educationOptions = $options['educationLevels'] ?? [];
        $studyOptions = $options['studyStatuses'] ?? [];

        $jenisGtkKey = trim((string) ($record['jenis_gtk'] ?? ''));
        $statusKepegawaianKey = trim((string) ($record['status_kepegawaian'] ?? ''));

        return [
            'id' => self::valueOrDash((string) ($record['id'] ?? '')),
            'name' => self::valueOrDash((string) ($record['nama'] ?? '')),
            'nip' => self::valueOrDash((string) ($record['nip'] ?? '')),
            'nomor_surat_tugas' => self::valueOrDash((string) ($record['nomor_surat_tugas'] ?? '')),
            'tanggal_surat_tugas' => self::formatDateField($record['tanggal_surat_tugas'] ?? ''),
            'sekolah_induk' => self::valueOrDash((string) ($record['sekolah_induk'] ?? '')),
            'nik' => self::valueOrDash((string) ($record['nik'] ?? '')),
            'jenis_kelamin' => self::mapOption($genderOptions, (string) ($record['jenis_kelamin'] ?? '')),
            'tempat_lahir' => self::valueOrDash((string) ($record['tempat_lahir'] ?? '')),
            'tanggal_lahir' => self::formatDateField($record['tanggal_lahir'] ?? ''),
            'nama_ibu_kandung' => self::valueOrDash((string) ($record['nama_ibu_kandung'] ?? '')),
            'agama' => self::mapOption($religionOptions, (string) ($record['agama'] ?? '')),
            'status_perkawinan' => self::mapOption($maritalOptions, (string) ($record['status_perkawinan'] ?? ''), '-'),
            'nama_pasangan' => self::valueOrDash((string) ($record['nama_pasangan'] ?? '')),
            'pekerjaan_pasangan' => self::valueOrDash((string) ($record['pekerjaan_pasangan'] ?? '')),
            'email' => self::valueOrDash((string) ($record['email'] ?? '')),
            'telepon' => self::valueOrDash((string) ($record['telepon'] ?? '')),
            'alamat' => self::valueOrDash((string) ($record['alamat'] ?? '')),
            'npwp' => self::valueOrDash((string) ($record['npwp'] ?? '')),
            'nama_wp' => self::valueOrDash((string) ($record['nama_wp'] ?? '')),
            'jenis_gtk' => self::mapOption($gtkOptions, $jenisGtkKey),
            'nuptk' => self::valueOrDash((string) ($record['nuptk'] ?? '')),
            'status_kepegawaian' => self::mapOption($employmentOptions, $statusKepegawaianKey),
            'sk_pengangkatan' => self::valueOrDash((string) ($record['sk_pengangkatan'] ?? '')),
            'tmt_pengangkatan' => self::formatDateField($record['tmt_pengangkatan'] ?? ''),
            'lembaga_pengangkat' => self::valueOrDash((string) ($record['lembaga_pengangkat'] ?? '')),
            'kartu_pasangan' => self::valueOrDash((string) ($record['kartu_pasangan'] ?? '')),
            'pendidikan_terakhir' => self::mapOption($educationOptions, (string) ($record['pendidikan_terakhir'] ?? ''), '-'),
            'status_kuliah' => self::mapOption($studyOptions, (string) ($record['status_kuliah'] ?? ''), '-'),
            'tahun_pensiun' => self::valueOrDash((string) ($record['tahun_pensiun'] ?? '')),
            'tugas_tambahan' => self::valueOrDash((string) ($record['tugas_tambahan'] ?? '')),
            'status' => self::formatStatusLabel((string) ($record['status'] ?? '')),
            'username' => self::valueOrDash((string) ($record['username'] ?? '')),
            'created_at' => self::formatDateTimeField($record['created_at'] ?? ''),
            'updated_at' => self::formatDateTimeField($record['updated_at'] ?? ''),
            'employment_status' => self::mapOption($employmentOptions, $statusKepegawaianKey),
            'phone' => self::valueOrDash((string) ($record['telepon'] ?? '')),
        ];
    }

    private static function formatStatusLabel(string $value): string
    {
        $normalized = strtolower(trim($value));
        $map = [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];

        if ($normalized === '') {
            return 'Aktif';
        }

        return $map[$normalized] ?? ucfirst($normalized);
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

    private static function combineLines(string $first, string $second): string
    {
        $parts = array_filter([$first, $second], static fn (string $value): bool => trim($value) !== '');

        if (empty($parts)) {
            return '-';
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, string> $map
     */
    private static function mapOption(array $map, string $key, string $fallback = '-'): string
    {
        $value = trim($key);

        if ($value === '') {
            return $fallback;
        }

        return $map[$value] ?? $value;
    }

    private static function valueOrDash(string $value): string
    {
        $text = trim($value);

        return $text === '' ? '-' : $text;
    }

    private static function formatDateField(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '-';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $raw;
        }

        return date('Y-m-d', $timestamp);
    }

    private static function formatDateTimeField(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '-';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $raw;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
