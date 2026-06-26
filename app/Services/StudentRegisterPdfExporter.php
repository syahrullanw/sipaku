<?php

namespace App\Services;

class StudentRegisterPdfExporter
{
    /**
     * @param array<string, mixed> $student
     * @param array<string, mixed> $history
     * @param array<string, mixed>|null $schoolProfile
     */
    public static function make(array $student, array $history, ?array $schoolProfile = null): string
    {
        self::ensurePdfReady();

        $leftMargin = 12.0;
        $rightMargin = 12.0;
        $topMargin = 18.0;
        $bottomMargin = 16.0;
        $cellMargin = 1.8;

        // F4 size to mirror print preview (approximately 215 x 330 mm)
        $pdf = new \FPDF('P', 'mm', [215, 330]);
        $pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
        $pdf->SetAutoPageBreak(true, $bottomMargin);
        $pdf->AddPage();

        $layout = [
            'left_margin' => $leftMargin,
            'right_margin' => $rightMargin,
            'top_margin' => $topMargin,
            'bottom_margin' => $bottomMargin,
            'cell_margin' => $cellMargin,
            'usable_width' => $pdf->GetPageWidth() - $leftMargin - $rightMargin,
        ];

        $studentId = isset($student['id']) ? (int) $student['id'] : 0;

        self::renderLetterhead($pdf, $layout, $schoolProfile);
        self::renderStudentHeader($pdf, $layout, $student);

        self::renderSectionTitle($pdf, 'Data Diri', $layout);
        self::renderDefinitionTable($pdf, self::buildIdentityRows($student), $layout);

        foreach (['ayah' => 'Data Ayah', 'ibu' => 'Data Ibu', 'wali' => 'Data Wali'] as $prefix => $title) {
            $parentRows = self::buildParentRows($student, $prefix);
            self::renderSectionTitle($pdf, $title, $layout);
            if (empty($parentRows)) {
                self::renderEmptyText($pdf, 'Belum ada ' . strtolower($title) . '.');
            } else {
                self::renderDefinitionTable($pdf, $parentRows, $layout);
            }
        }

        $promotions = self::extractHistory($history, 'promotions', $studentId);
        $graduations = self::extractHistory($history, 'graduations', $studentId);
        $attendance = self::extractHistory($history, 'attendance', $studentId);
        $attitudes = self::extractHistory($history, 'attitudes', $studentId);
        $achievements = self::extractHistory($history, 'achievements', $studentId);
        $extracurriculars = self::extractHistory($history, 'extracurriculars', $studentId);
        $notes = self::extractHistory($history, 'notes', $studentId);
        $prakerin = self::extractHistory($history, 'prakerin', $studentId);
        $subjects = self::extractHistory($history, 'subjects', $studentId);

        self::renderSectionTitle($pdf, 'Riwayat Naik Kelas', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 42, 'align' => 'L'],
                ['label' => 'Kelas', 'width' => 32, 'align' => 'L'],
                ['label' => 'Status', 'width' => 28, 'align' => 'L'],
                ['label' => 'Catatan', 'width' => 89, 'align' => 'L'],
            ],
            self::mapPromotions($promotions),
            $layout,
            'Belum ada data status naik kelas.'
        );

        self::renderSectionTitle($pdf, 'Riwayat Kelulusan', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 42, 'align' => 'L'],
                ['label' => 'Kelas', 'width' => 32, 'align' => 'L'],
                ['label' => 'Status', 'width' => 28, 'align' => 'L'],
                ['label' => 'Catatan', 'width' => 89, 'align' => 'L'],
            ],
            self::mapGraduations($graduations),
            $layout,
            'Belum ada data kelulusan.'
        );

        self::renderSectionTitle($pdf, 'Riwayat Presensi', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 42, 'align' => 'L'],
                ['label' => 'Kelas', 'width' => 30, 'align' => 'L'],
                ['label' => 'Sakit', 'width' => 20, 'align' => 'C'],
                ['label' => 'Izin', 'width' => 20, 'align' => 'C'],
                ['label' => 'Bolos', 'width' => 20, 'align' => 'C'],
                ['label' => 'Alpa', 'width' => 27, 'align' => 'C'],
            ],
            self::mapAttendance($attendance),
            $layout,
            'Belum ada data presensi.'
        );

        self::renderSectionTitle($pdf, 'Nilai Sikap', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 36, 'align' => 'L'],
                ['label' => 'Jenis', 'width' => 22, 'align' => 'L'],
                ['label' => 'Menonjol', 'width' => 44, 'align' => 'L'],
                ['label' => 'Perlu Peningkatan', 'width' => 34, 'align' => 'L'],
                ['label' => 'Catatan', 'width' => 45, 'align' => 'L'],
            ],
            self::mapAttitudes($attitudes),
            $layout,
            'Belum ada penilaian sikap.'
        );

        self::renderSectionTitle($pdf, 'Prestasi Siswa', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 38, 'align' => 'L'],
                ['label' => 'Kelas', 'width' => 30, 'align' => 'L'],
                ['label' => 'Jenis', 'width' => 35, 'align' => 'L'],
                ['label' => 'Keterangan', 'width' => 78, 'align' => 'L'],
            ],
            self::mapAchievements($achievements),
            $layout,
            'Belum ada data prestasi.'
        );

        self::renderSectionTitle($pdf, 'Ekskul & Pengembangan Diri', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 38, 'align' => 'L'],
                ['label' => 'Ekskul', 'width' => 50, 'align' => 'L'],
                ['label' => 'Nilai Akhir', 'width' => 32, 'align' => 'C'],
                ['label' => 'Predikat', 'width' => 60, 'align' => 'L'],
            ],
            self::mapExtracurriculars($extracurriculars),
            $layout,
            'Belum ada nilai ekskul.'
        );

        self::renderSectionTitle($pdf, 'Catatan Wali Kelas', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 38, 'align' => 'L'],
                ['label' => 'Kelas', 'width' => 32, 'align' => 'L'],
                ['label' => 'Catatan', 'width' => 111, 'align' => 'L'],
            ],
            self::mapNotes($notes),
            $layout,
            'Belum ada catatan wali kelas.'
        );

        self::renderSectionTitle($pdf, 'Riwayat Prakerin', $layout);
        self::renderTable(
            $pdf,
            [
                ['label' => 'Tahun Ajaran', 'width' => 38, 'align' => 'L'],
                ['label' => 'Tempat', 'width' => 60, 'align' => 'L'],
                ['label' => 'Nilai Akhir', 'width' => 34, 'align' => 'C'],
                ['label' => 'Predikat', 'width' => 49, 'align' => 'L'],
            ],
            self::mapPrakerin($prakerin),
            $layout,
            'Belum ada riwayat prakerin.'
        );

        self::renderSectionTitle($pdf, 'Nilai Mapel per Semester', $layout);
        self::renderSubjectHistory($pdf, $subjects, $layout);

        return (string) $pdf->Output('S');
    }

    /**
     * @param array<string, mixed>|null $schoolProfile
     * @param array<string, float> $layout
     */
    private static function renderLetterhead(\FPDF $pdf, array $layout, ?array $schoolProfile): void
    {
        $letterheadPath = self::resolvePublicPath($schoolProfile['kop_surat'] ?? null);

        if ($letterheadPath !== null) {
            // Fit letterhead across printable width
            $pdf->Image($letterheadPath, $layout['left_margin'], $pdf->GetY(), $layout['usable_width']);
            $pdf->Ln(10);
        } else {
            $schoolName = trim((string) ($schoolProfile['nama'] ?? ''));
            $address = trim((string) ($schoolProfile['alamat'] ?? ''));
            $npsn = trim((string) ($schoolProfile['npsn'] ?? ''));
            $phone = trim((string) ($schoolProfile['telepon'] ?? ''));

            if ($schoolName === '' && $address === '' && $npsn === '' && $phone === '') {
                return;
            }

            $pdf->SetFont('Arial', 'B', 13);
            if ($schoolName !== '') {
                $pdf->Cell(0, 7, self::convert(strtoupper($schoolName)), 0, 1, 'C');
            }

            $infoParts = array_filter([
                $address,
                $npsn !== '' ? 'NPSN: ' . $npsn : null,
                $phone !== '' ? 'Telepon: ' . $phone : null,
            ], static fn (?string $value): bool => $value !== null && $value !== '');

            if (!empty($infoParts)) {
                $pdf->SetFont('Arial', '', 9.5);
                $pdf->MultiCell(0, 4.2, self::convert(implode(' | ', $infoParts)), 0, 'C');
            }
            $pdf->Ln(4);
        }

        // Divider
        $currentY = $pdf->GetY();
        $pdf->SetDrawColor(148, 163, 184);
        $pdf->Line($layout['left_margin'], $currentY, $pdf->GetPageWidth() - $layout['right_margin'], $currentY);
        $pdf->Ln(6);
        $pdf->SetDrawColor(0, 0, 0);
    }

    /**
     * @param array<string, float> $layout
     * @param array<string, mixed> $student
     */
    private static function renderStudentHeader(\FPDF $pdf, array $layout, array $student): void
    {
        $photoWidth = 42;
        $photoHeight = 58;
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();

        $photoPath = self::resolvePublicPath($student['foto_path'] ?? null);
        if ($photoPath !== null) {
            $pdf->Image($photoPath, $startX, $startY, $photoWidth, $photoHeight);
        } else {
            $pdf->SetDrawColor(203, 213, 225);
            $pdf->Rect($startX, $startY, $photoWidth, $photoHeight);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetXY($startX, $startY + ($photoHeight / 2) - 3);
            $pdf->Cell($photoWidth, 6, self::convert('Foto belum tersedia'), 0, 0, 'C');
        }
        $pdf->SetDrawColor(0, 0, 0);

        $contentX = $startX + $photoWidth + 10;
        $contentWidth = $layout['usable_width'] - ($photoWidth + 10);

        // Judul diratakan ke tengah halaman
        $pdf->SetXY($layout['left_margin'], $startY);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell($layout['usable_width'], 7, self::convert('BUKU INDUK SISWA'), 0, 1, 'C');

        $studentName = strtoupper(self::valueOrDash($student['nama'] ?? null));
        $pdf->SetXY($contentX, $startY + 9);
        $pdf->SetFont('Arial', 'B', 12.5);
        $pdf->Cell($contentWidth, 6.5, self::convert($studentName), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 10.2);
        $headerRows = [
            'NIPD' => $student['nipd'] ?? null,
            'NISN' => $student['nisn'] ?? null,
            'Kelas' => $student['kelas_nama'] ?? null,
            'Jurusan' => $student['jurusan_nama'] ?? null,
            'Status' => $student['status'] ?? null,
            'Status Dapodik' => self::dapodikStatusLabel($student['status_dapodik'] ?? null),
            'Tahun Ajaran' => $student['tahun_ajaran_nama'] ?? null,
        ];

        $labelWidth = 33;
        $valueWidth = $contentWidth - $labelWidth;
        foreach ($headerRows as $label => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $pdf->SetX($contentX);
            $pdf->Cell($labelWidth, 5.4, self::convert($label), 0, 0, 'L');
            $pdf->Cell($valueWidth, 5.4, self::convert(self::valueOrDash($value)), 0, 1, 'L');
        }

        $pdf->SetY(max($startY + $photoHeight, $pdf->GetY()) + 8);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function buildIdentityRows(array $student): array
    {
        $genderMap = [
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
        ];

        return [
            ['label' => 'Nama Lengkap', 'value' => self::valueOrDash($student['nama'] ?? null)],
            ['label' => 'NIPD', 'value' => self::valueOrDash($student['nipd'] ?? null)],
            ['label' => 'NISN', 'value' => self::valueOrDash($student['nisn'] ?? null)],
            ['label' => 'NIK', 'value' => self::valueOrDash($student['nik'] ?? null)],
            ['label' => 'Nomor KK', 'value' => self::valueOrDash($student['nomor_kk'] ?? null)],
            ['label' => 'Tempat/Tanggal Lahir', 'value' => trim(
                ($student['tempat_lahir'] ?? '') . ' / ' . self::valueOrDash($student['tanggal_lahir'] ?? null),
                ' / '
            ) ?: '-'],
            ['label' => 'Jenis Kelamin', 'value' => $genderMap[$student['jenis_kelamin'] ?? ''] ?? '-'],
            ['label' => 'Agama', 'value' => self::valueOrDash($student['agama'] ?? null)],
            ['label' => 'Alamat Lengkap', 'value' => self::buildAddress($student)],
            ['label' => 'Telepon', 'value' => self::valueOrDash($student['telepon'] ?? null)],
            ['label' => 'HP', 'value' => self::valueOrDash($student['hp'] ?? null)],
            ['label' => 'Email', 'value' => self::valueOrDash($student['email'] ?? null)],
            ['label' => 'Jenis Tinggal', 'value' => self::valueOrDash($student['jenis_tinggal'] ?? null)],
            ['label' => 'Alat Transportasi', 'value' => self::valueOrDash($student['alat_transportasi'] ?? null)],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function buildParentRows(array $student, string $prefix): array
    {
        $fields = [
            'Nama' => $student[$prefix . '_nama'] ?? null,
            'Tahun Lahir' => $student[$prefix . '_tahun_lahir'] ?? null,
            'Pendidikan' => $student[$prefix . '_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student[$prefix . '_pekerjaan'] ?? null,
            'Penghasilan' => $student[$prefix . '_penghasilan'] ?? null,
            'NIK' => $student[$prefix . '_nik'] ?? null,
        ];

        $rows = [];

        foreach ($fields as $label => $value) {
            $valueString = self::valueOrDash($value);
            if ($valueString === '-' && $label !== 'Nama') {
                $rows[] = ['label' => $label, 'value' => '-'];
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $valueString];
        }

        $hasData = array_filter($rows, static fn (array $row): bool => $row['value'] !== '-');

        return empty($hasData) ? [] : $rows;
    }

    private static function buildAddress(array $student): string
    {
        $segments = [];
        $address = trim((string) ($student['alamat'] ?? ''));
        if ($address !== '') {
            $segments[] = $address;
        }

        $rt = self::valueOrDash($student['rt'] ?? null);
        $rw = self::valueOrDash($student['rw'] ?? null);
        if ($rt !== '-' || $rw !== '-') {
            $segments[] = sprintf('RT %s / RW %s', $rt, $rw);
        }

        foreach (['dusun' => 'Dusun', 'kelurahan' => 'Kelurahan', 'kecamatan' => 'Kecamatan'] as $key => $label) {
            $value = self::valueOrDash($student[$key] ?? null);
            if ($value !== '-') {
                $segments[] = $label . ': ' . $value;
            }
        }

        $postal = self::valueOrDash($student['kode_pos'] ?? null);
        if ($postal !== '-') {
            $segments[] = 'Kode Pos: ' . $postal;
        }

        return empty($segments) ? '-' : implode(', ', $segments);
    }

    /**
     * @param array<int, array{label: string, value: string}> $rows
     * @param array<string, float> $layout
     */
    private static function renderDefinitionTable(\FPDF $pdf, array $rows, array $layout): void
    {
        if (empty($rows)) {
            return;
        }

        $labelWidth = 50;
        $valueWidth = max(40.0, $layout['usable_width'] - $labelWidth);
        $lineHeight = 5.5;

        foreach ($rows as $row) {
            $text = self::convert($row['value']);
            $label = self::convert($row['label']);
            $lines = self::countLines($pdf, $valueWidth, $text, $layout['cell_margin']);
            $rowHeight = $lineHeight * max(1, $lines);

            if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $layout['bottom_margin']) {
                $pdf->AddPage();
            }

            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell($labelWidth, $rowHeight, $label, 0, 0, 'L');

            $pdf->SetFont('Arial', '', 9.5);
            $pdf->MultiCell($valueWidth, $lineHeight, $text, 0, 'L');
        }

        $pdf->Ln(3);
    }

    private static function renderSectionTitle(\FPDF $pdf, string $title, array $layout): void
    {
        $pdf->SetFont('Arial', 'B', 11.5);
        $pdf->Cell(0, 6.5, self::convert(strtoupper($title)), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9.6);
        // subtle underline to mirror print preview grouping
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Line($layout['left_margin'], $pdf->GetY(), $pdf->GetPageWidth() - $layout['right_margin'], $pdf->GetY());
        $pdf->Ln(3);
        $pdf->SetDrawColor(0, 0, 0);
    }

    private static function renderEmptyText(\FPDF $pdf, string $text): void
    {
        $pdf->SetFont('Arial', 'I', 9.2);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->MultiCell(0, 5.2, self::convert($text), 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);
    }

    /**
     * @param array<string, mixed> $history
     *
     * @return array<int, array<string, mixed>>
     */
    private static function extractHistory(array $history, string $key, int $studentId): array
    {
        if (!isset($history[$key]) || !is_array($history[$key])) {
            return [];
        }

        return $history[$key][$studentId] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $promotions
     *
     * @return array<int, array<int, string>>
     */
    private static function mapPromotions(array $promotions): array
    {
        $rows = [];
        foreach ($promotions as $promotion) {
            $rows[] = [
                self::valueOrDash($promotion['school_year_name'] ?? null),
                self::valueOrDash($promotion['class_name'] ?? null),
                self::valueOrDash($promotion['status'] ?? null),
                self::valueOrDash($promotion['note'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $graduations
     *
     * @return array<int, array<int, string>>
     */
    private static function mapGraduations(array $graduations): array
    {
        $rows = [];
        foreach ($graduations as $record) {
            $rows[] = [
                self::valueOrDash($record['school_year_name'] ?? null),
                self::valueOrDash($record['class_name'] ?? null),
                self::valueOrDash($record['status'] ?? null),
                self::valueOrDash($record['note'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $attendance
     *
     * @return array<int, array<int, string>>
     */
    private static function mapAttendance(array $attendance): array
    {
        $rows = [];
        foreach ($attendance as $record) {
            $rows[] = [
                self::valueOrDash($record['school_year_name'] ?? null),
                self::valueOrDash($record['class_name'] ?? null),
                (string) ($record['sick'] ?? 0),
                (string) ($record['permit'] ?? 0),
                (string) ($record['truant'] ?? 0),
                (string) ($record['absent'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $attitudes
     *
     * @return array<int, array<int, string>>
     */
    private static function mapAttitudes(array $attitudes): array
    {
        $rows = [];
        foreach ($attitudes as $attitude) {
            $always = $attitude['always'] ?? [];
            if (is_array($always)) {
                $always = array_filter(array_map('trim', $always), static fn (string $value): bool => $value !== '');
            } else {
                $always = [];
            }

            $rows[] = [
                self::valueOrDash($attitude['school_year_name'] ?? null),
                ucwords(trim((string) ($attitude['type'] ?? ''))),
                empty($always) ? '-' : implode(', ', $always),
                self::valueOrDash($attitude['improving'] ?? null),
                self::valueOrDash($attitude['note'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $achievements
     *
     * @return array<int, array<int, string>>
     */
    private static function mapAchievements(array $achievements): array
    {
        $rows = [];
        foreach ($achievements as $achievement) {
            $rows[] = [
                self::valueOrDash($achievement['school_year_name'] ?? null),
                self::valueOrDash($achievement['class_name'] ?? null),
                self::valueOrDash($achievement['type'] ?? null),
                self::valueOrDash($achievement['description'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $extracurriculars
     *
     * @return array<int, array<int, string>>
     */
    private static function mapExtracurriculars(array $extracurriculars): array
    {
        $rows = [];
        foreach ($extracurriculars as $record) {
            $finalScore = isset($record['scores']['final'])
                ? number_format((float) $record['scores']['final'], 2)
                : '-';

            $rows[] = [
                self::valueOrDash($record['school_year_name'] ?? null),
                self::valueOrDash($record['activity_name'] ?? null),
                $finalScore,
                self::valueOrDash($record['predicate'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $notes
     *
     * @return array<int, array<int, string>>
     */
    private static function mapNotes(array $notes): array
    {
        $rows = [];
        foreach ($notes as $note) {
            $rows[] = [
                self::valueOrDash($note['school_year_name'] ?? null),
                self::valueOrDash($note['class_name'] ?? null),
                self::valueOrDash($note['note'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $prakerin
     *
     * @return array<int, array<int, string>>
     */
    private static function mapPrakerin(array $prakerin): array
    {
        $rows = [];
        foreach ($prakerin as $record) {
            $finalScore = isset($record['scores']['final'])
                ? number_format((float) $record['scores']['final'], 2)
                : '-';
            $predicate = $record['scores']['predicate'] ?? ($record['predicate'] ?? null);

            $rows[] = [
                self::valueOrDash($record['school_year_name'] ?? null),
                self::valueOrDash($record['place_name'] ?? null),
                $finalScore,
                self::valueOrDash($predicate),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, float> $layout
     */
    private static function renderSubjectHistory(\FPDF $pdf, array $entries, array $layout): void
    {
        $records = array_values($entries);

        if (!empty($records)) {
            usort(
                $records,
                static function (array $left, array $right): int {
                    $leftKey = $left['sort_key'] ?? null;
                    $rightKey = $right['sort_key'] ?? null;
                    if ($leftKey === $rightKey) {
                        return 0;
                    }
                    if ($leftKey === null) {
                        return 1;
                    }
                    if ($rightKey === null) {
                        return -1;
                    }

                    return strcmp((string) $leftKey, (string) $rightKey);
                }
            );
        }

        if (empty($records)) {
            self::renderEmptyText($pdf, 'Belum ada data nilai mapel per semester.');

            return;
        }

        $columnsK13 = [
            ['label' => 'Mata Pelajaran', 'width' => 64, 'align' => 'L'],
            ['label' => 'Kelas', 'width' => 24, 'align' => 'L'],
            ['label' => 'Pengetahuan', 'width' => 32, 'align' => 'L'],
            ['label' => 'Keterampilan', 'width' => 32, 'align' => 'L'],
            ['label' => 'Rata-rata', 'width' => 35, 'align' => 'C'],
        ];
        $columnsKurmer = [
            ['label' => 'Mata Pelajaran (KurMer)', 'width' => 60, 'align' => 'L'],
            ['label' => 'Kelas', 'width' => 24, 'align' => 'L'],
            ['label' => 'Capaian', 'width' => 30, 'align' => 'L'],
            ['label' => 'Nilai Opsional', 'width' => 24, 'align' => 'C'],
            ['label' => 'Narasi / Tindak Lanjut', 'width' => 53, 'align' => 'L'],
        ];
        $kurmerLevelLabels = [
            'BB' => 'Belum Berkembang',
            'MB' => 'Mulai Berkembang',
            'BSH' => 'Berkembang Sesuai Harapan',
            'SB' => 'Sangat Berkembang',
        ];

        foreach ($records as $record) {
            $schoolYearName = self::valueOrDash($record['school_year_name'] ?? null);
            $semesterLabel = ((int) ($record['semester'] ?? 1)) === 2 ? 'Semester Genap' : 'Semester Ganjil';
            $title = trim($schoolYearName . ' · ' . $semesterLabel, ' ·');

            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 5.5, self::convert($title), 0, 1, 'L');

            $subjects = isset($record['subjects']) && is_array($record['subjects']) ? $record['subjects'] : [];
            $kurmerSubjects = array_values(array_filter($subjects, static fn ($subject) => is_array($subject) && strtolower((string) ($subject['curriculum'] ?? '')) === 'kurmer'));
            $k13Subjects = array_values(array_filter($subjects, static fn ($subject) => !is_array($subject) ? false : strtolower((string) ($subject['curriculum'] ?? '')) !== 'kurmer'));

            $rowsK13 = [];
            foreach ($k13Subjects as $subject) {
                $rowsK13[] = [
                    self::valueOrDash($subject['name'] ?? null),
                    self::valueOrDash($subject['class_name'] ?? null),
                    self::formatScore($subject['knowledge_score'] ?? null, $subject['knowledge_predicate'] ?? null),
                    self::formatScore($subject['skill_score'] ?? null, $subject['skill_predicate'] ?? null),
                    isset($subject['average_score'])
                        ? number_format((float) $subject['average_score'], 2)
                        : '-',
                ];
            }

            $rowsKurmer = [];
            foreach ($kurmerSubjects as $subject) {
                $capaianCode = strtoupper(trim((string) ($subject['kurmer_capaian'] ?? '')));
                $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : '-';
                $kurmerScore = isset($subject['kurmer_score']) ? number_format((float) $subject['kurmer_score'], 2) : '-';
                $description = trim((string) ($subject['kurmer_description'] ?? ''));
                $tindakLanjut = trim((string) ($subject['kurmer_tindak_lanjut'] ?? ''));
                $tpSourcesRaw = $subject['kurmer_tp_sources'] ?? null;
                if (is_string($tpSourcesRaw)) {
                    $decoded = json_decode($tpSourcesRaw, true);
                    $tpSourcesRaw = is_array($decoded) ? $decoded : [];
                }
                $tpSources = is_array($tpSourcesRaw) ? $tpSourcesRaw : [];
                $tpSummary = '';
                if (!empty($tpSources)) {
                    $tpParts = [];
                    $used = 0;
                    foreach (array_slice($tpSources, 0, 2) as $tp) {
                        $used++;
                        $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? ''));
                        $tpDesc = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));
                        $label = $code !== '' ? $code : 'TP';
                        $tpParts[] = $tpDesc !== '' ? ($label . ' - ' . $tpDesc) : $label;
                    }
                    $remaining = count($tpSources) - $used;
                    if ($remaining > 0) {
                        $tpParts[] = $remaining . ' TP lain';
                    }
                    $tpSummary = implode('; ', array_filter($tpParts));
                }

                $narrativeParts = [];
                if ($description !== '') {
                    $narrativeParts[] = $description;
                }
                if ($tindakLanjut !== '') {
                    $narrativeParts[] = 'Tindak lanjut: ' . $tindakLanjut;
                }
                if ($tpSummary !== '') {
                    $narrativeParts[] = 'TP: ' . $tpSummary;
                }

                $rowsKurmer[] = [
                    self::valueOrDash($subject['name'] ?? null),
                    self::valueOrDash($subject['class_name'] ?? null),
                    trim($capaianCode !== '' ? $capaianCode : '-') . ($capaianLabel !== '' && $capaianLabel !== $capaianCode ? ' (' . $capaianLabel . ')' : ''),
                    $kurmerScore,
                    !empty($narrativeParts) ? implode("\n", $narrativeParts) : '-',
                ];
            }

            if (!empty($rowsK13)) {
                self::renderTable($pdf, $columnsK13, $rowsK13, $layout, 'Belum ada data nilai mapel.');
                $pdf->Ln(1);
            }
            if (!empty($rowsKurmer)) {
                self::renderTable($pdf, $columnsKurmer, $rowsKurmer, $layout, 'Belum ada data nilai mapel.');
                $pdf->Ln(1);
            }
            if (empty($rowsK13) && empty($rowsKurmer)) {
                self::renderEmptyText($pdf, 'Belum ada data nilai mapel.');
                $pdf->Ln(1);
            }
        }
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<string, float> $layout
     */
    private static function renderTable(\FPDF $pdf, array $columns, array $rows, array $layout, string $emptyText): void
    {
        if (empty($rows)) {
            self::renderEmptyText($pdf, $emptyText);

            return;
        }

        $headerHeight = 6.5;
        $lineHeight = 5.4;

        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->SetFont('Arial', 'B', 9);

        self::renderTableHeader($pdf, $columns, $headerHeight);

        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $row) {
            $cells = [];
            $maxLines = 1;

            foreach ($columns as $index => $column) {
                $text = self::convert(trim((string) ($row[$index] ?? '')));
                if ($text === '') {
                    $text = self::convert('-');
                }
                $lines = self::countLines($pdf, $column['width'], $text, $layout['cell_margin']);
                $maxLines = max($maxLines, $lines);
                $cells[] = [
                    'text' => $text,
                    'width' => $column['width'],
                    'align' => $column['align'] ?? 'L',
                ];
            }

            $rowHeight = $lineHeight * $maxLines;
            if ($pdf->GetY() + $rowHeight > $pdf->GetPageHeight() - $layout['bottom_margin']) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 9);
                self::renderTableHeader($pdf, $columns, $headerHeight);
                $pdf->SetFont('Arial', '', 9);
            }

            $pdf->SetX($layout['left_margin']);
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

        $pdf->Ln(2);
    }

    private static function renderTableHeader(\FPDF $pdf, array $columns, float $height): void
    {
        foreach ($columns as $column) {
            $pdf->Cell($column['width'], $height, self::convert($column['label']), 1, 0, 'C', true);
        }
        $pdf->Ln();
    }

    private static function formatScore(?float $score, ?string $predicate): string
    {
        if ($score === null) {
            return '-';
        }

        $formatted = number_format((float) $score, 2);

        if ($predicate !== null && trim($predicate) !== '') {
            return $formatted . ' (' . trim($predicate) . ')';
        }

        return $formatted;
    }

    private static function ensurePdfReady(): void
    {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        if (!class_exists(\FPDF::class, false)) {
            require_once base_path('app/Libraries/Fpdf/fpdf.php');
        }
    }

    private static function resolvePublicPath(?string $path): ?string
    {
        $trimmed = trim((string) ($path ?? ''));
        if ($trimmed === '') {
            return null;
        }

        // Already an absolute filesystem path
        if (is_file($trimmed)) {
            return $trimmed;
        }

        $relative = ltrim($trimmed, '/');
        $candidates = [
            public_path($relative),
            public_path($trimmed),
        ];

        // If value is a URL, try to map its path back to public folder
        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($trimmed, PHP_URL_PATH);
            if (is_string($urlPath) && $urlPath !== '') {
                $candidates[] = public_path(ltrim($urlPath, '/'));
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function valueOrDash(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $string = trim((string) $value);

        return $string === '' ? '-' : $string;
    }

    private static function dapodikStatusLabel(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? '')));
        $labels = [
            'aktif' => 'Aktif',
            'belum_masuk' => 'Belum Masuk Dapodik',
            'mutasi' => 'Mutasi',
            'pindah' => 'Pindah',
            'residu' => 'Residu',
        ];

        return $labels[$status] ?? self::valueOrDash($value);
    }

    private static function convert(?string $text): string
    {
        $value = (string) ($text ?? '');

        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
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
            $words = preg_split('/ +/', $chunk);
            $lineWidth = 0;
            $lines = 1;

            foreach ($words as $index => $word) {
                $wordWidth = $pdf->GetStringWidth($word);
                if ($index === 0) {
                    $lineWidth = $wordWidth;
                    continue;
                }

                if ($lineWidth + $spaceWidth + $wordWidth <= $available) {
                    $lineWidth += $spaceWidth + $wordWidth;
                    continue;
                }

                $lines++;
                $lineWidth = $wordWidth;
            }

            $lineCount += $lines;
        }

        return max(1, $lineCount);
    }
}
