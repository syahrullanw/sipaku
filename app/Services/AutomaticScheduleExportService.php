<?php

namespace App\Services;

use App\Models\AutomaticSchedule;
use App\Support\SimpleXlsxBuilder;

class AutomaticScheduleExportService
{
    /**
     * @param array<string, mixed> $draft
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @param array<string, array<int, string>> $conflicts
     * @param array<int, array<string, mixed>> $classes
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $activities
     */
    public function makeXlsx(array $draft, array $items, array $context, array $conflicts, array $classes = [], array $periods = [], array $activities = []): string
    {
        $matrix = $this->buildOutputMatrix($items, $classes, $periods, $activities, $context);
        $sheet = $this->buildMatrixSheet($matrix, $context);
        $conflictRows = [
            ['Kategori', 'Catatan'],
        ];
        foreach ($conflicts as $category => $messages) {
            foreach ($messages as $message) {
                $conflictRows[] = [$this->conflictLabel($category), $message];
            }
        }
        if (count($conflictRows) === 1) {
            $conflictRows[] = ['Validasi', 'Tidak ada catatan konflik.'];
        }

        $metaRows = [
            ['Nama Draft', (string) ($draft['nama'] ?? '-')],
            ['Tahun Ajaran', (string) ($context['school_year'] ?? '-')],
            ['Semester', (string) ($context['semester_label'] ?? '-')],
            ['Tingkat', (string) ($context['level_label'] ?? 'Semua Tingkat')],
            ['Diekspor', date('d/m/Y H:i')],
        ];

        return SimpleXlsxBuilder::buildSheets([
            ['name' => 'Info', 'rows' => $metaRows],
            ['name' => 'Output Jadwal', 'rows' => $sheet['rows'], 'options' => $sheet['options']],
            ['name' => 'Konflik', 'rows' => $conflictRows],
        ]);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $classes
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $activities
     */
    public function makePdf(array $draft, array $items, array $context, array $classes = [], array $periods = [], array $activities = []): string
    {
        $fontPath = rtrim(base_path('app/Libraries/Fpdf/font'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $fontPath);
        }

        require_once base_path('app/Libraries/Fpdf/fpdf.php');

        $matrix = $this->buildOutputMatrix($items, $classes, $periods, $activities, $context);
        $pdf = new \FPDF('L', 'mm', [330, 210]);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $this->renderMatrixPdf($pdf, $matrix, $context);

        return (string) $pdf->Output('S');
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $classes
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $activities
     */
    public function makePrintHtml(array $items, array $context, array $classes = [], array $periods = [], array $activities = [], bool $withPrintButton = true): string
    {
        $matrix = $this->buildOutputMatrix($items, $classes, $periods, $activities, $context);
        $html = $this->renderMatrixHtml($matrix, $context, $withPrintButton);

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Jadwal Pelajaran</title></head><body>' . $html . '</body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $classes
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $activities
     */
    public function makePreviewHtml(array $items, array $context, array $classes = [], array $periods = [], array $activities = []): string
    {
        $matrix = $this->buildOutputMatrix($items, $classes, $periods, $activities, $context);
        $html = $this->renderMatrixHtml($matrix, $context, false);

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Preview Jadwal Pelajaran</title></head><body>' . $html . '</body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $classes
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $activities
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildOutputMatrix(array $items, array $classes, array $periods, array $activities, array $context): array
    {
        if (empty($classes)) {
            $classes = $this->classesFromItems($items);
        }

        usort($classes, static function (array $left, array $right): int {
            $levelCompare = (int) ($left['tingkat'] ?? $left['kelas_tingkat'] ?? 0) <=> (int) ($right['tingkat'] ?? $right['kelas_tingkat'] ?? 0);
            return $levelCompare !== 0 ? $levelCompare : strcmp((string) ($left['nama'] ?? $left['kelas_nama'] ?? ''), (string) ($right['nama'] ?? $right['kelas_nama'] ?? ''));
        });

        $teacherPalette = [
            '#22c55e', '#60a5fa', '#facc15', '#fb7185', '#38bdf8', '#a3e635', '#f97316', '#a78bfa',
            '#14b8a6', '#f59e0b', '#84cc16', '#ef4444', '#06b6d4', '#c084fc', '#10b981', '#94a3b8',
            '#eab308', '#4ade80', '#818cf8', '#f472b6', '#2dd4bf', '#fb923c', '#64748b', '#bef264',
        ];
        $teacherIdsForCode = [];
        foreach ($items as $item) {
            $teacherId = (int) ($item['guru_id'] ?? 0);
            if ($teacherId > 0) {
                $teacherIdsForCode[$teacherId] ??= trim((string) ($item['guru_nama'] ?? 'Guru #' . $teacherId));
            }
        }
        asort($teacherIdsForCode);

        $teacherSequence = [];
        $teacherColors = [];
        $teacherIndex = 1;
        foreach ($teacherIdsForCode as $teacherId => $teacherName) {
            $teacherSequence[(int) $teacherId] = $teacherIndex;
            $teacherColors[(int) $teacherId] = $teacherPalette[($teacherIndex - 1) % count($teacherPalette)];
            $teacherIndex++;
        }

        $assignmentLetterCount = [];
        $assignmentCodes = [];
        $assignmentRecap = [];
        $subjectLegend = [];
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'failed') {
                continue;
            }

            $assignmentId = (int) ($item['guru_mata_pelajaran_id'] ?? 0);
            $teacherId = (int) ($item['guru_id'] ?? 0);
            $classLevel = (int) ($item['kelas_tingkat'] ?? 0);
            if ($assignmentId <= 0 || $teacherId <= 0) {
                continue;
            }

            if (!isset($assignmentCodes[$assignmentId])) {
                $teacherNo = $teacherSequence[$teacherId] ?? $teacherId;
                $assignmentLetterCount[$teacherId] = ($assignmentLetterCount[$teacherId] ?? 0) + 1;
                $letter = chr(64 + min(26, (int) $assignmentLetterCount[$teacherId]));
                $assignmentCodes[$assignmentId] = str_pad((string) $teacherNo, 2, '0', STR_PAD_LEFT) . $letter;
            }

            $subjectCode = trim((string) ($item['mata_pelajaran_kode'] ?? ''));
            $subjectName = trim((string) ($item['mata_pelajaran_nama'] ?? 'Mapel'));
            if ($subjectCode !== '') {
                $subjectLegend[$subjectCode] = $subjectName;
            }

            $assignmentRecap[$assignmentId] ??= [
                'teacher_id' => $teacherId,
                'teacher_name' => trim((string) ($item['guru_nama'] ?? 'Guru')),
                'code' => $assignmentCodes[$assignmentId],
                'subject_code' => $subjectCode,
                'subject_name' => $subjectName,
                'color' => $teacherColors[$teacherId] ?? '#e2e8f0',
                'levels' => [10 => 0, 11 => 0, 12 => 0],
                'hours' => 0,
            ];

            $hours = (int) ($item['jumlah_jam'] ?? 0);
            if (isset($assignmentRecap[$assignmentId]['levels'][$classLevel])) {
                $assignmentRecap[$assignmentId]['levels'][$classLevel] += $hours;
            }
            $assignmentRecap[$assignmentId]['hours'] += $hours;
        }

        uasort($assignmentRecap, static function (array $left, array $right): int {
            $teacherCompare = strcmp((string) $left['teacher_name'], (string) $right['teacher_name']);
            return $teacherCompare !== 0 ? $teacherCompare : strcmp((string) $left['subject_name'], (string) $right['subject_name']);
        });
        ksort($subjectLegend);

        $headmasterName = trim((string) ($context['headmaster_name'] ?? ''));
        $headmasterNip = trim((string) ($context['headmaster_nip'] ?? ''));
        $signatureCity = trim((string) ($context['signature_city'] ?? ''));
        $signatureDate = trim((string) ($context['signature_date_label'] ?? date('d/m/Y')));
        $signatureQrValue = trim((string) ($context['signature_qr_value'] ?? ''));
        if ($signatureQrValue === '') {
            $signatureQrSource = implode('|', [
                (string) ($context['school_name'] ?? config('app.name', 'Sekolah')),
                (string) ($context['school_year'] ?? ''),
                (string) ($context['semester_label'] ?? ''),
                $headmasterName,
                $signatureDate,
            ]);
            $signatureQrValue = 'JADWAL-' . strtoupper(substr(sha1($signatureQrSource), 0, 20));
        } elseif (strlen($signatureQrValue) > 64) {
            $signatureQrValue = 'JADWAL-' . strtoupper(substr(sha1($signatureQrValue), 0, 20));
        }

        return [
            'classes' => $classes,
            'grid_rows' => $this->buildGridRows($periods, $items, $classes, $activities),
            'day_groups' => [
                ['senin', 'selasa', 'rabu'],
                ['kamis', 'jumat', 'sabtu'],
            ],
            'day_options' => AutomaticSchedule::DAYS,
            'assignment_codes' => $assignmentCodes,
            'teacher_colors' => $teacherColors,
            'assignment_recap' => array_values($assignmentRecap),
            'subject_legend' => $subjectLegend,
            'school_name' => (string) ($context['school_name'] ?? config('app.name', 'Sekolah')),
            'school_year' => (string) ($context['school_year'] ?? ''),
            'semester_label' => (string) ($context['semester_label'] ?? ''),
            'signature_city' => $signatureCity,
            'signature_date_label' => $signatureDate,
            'headmaster_name' => $headmasterName !== '' ? $headmasterName : '________________',
            'headmaster_nip' => $headmasterNip,
            'signature_qr_value' => $signatureQrValue,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function classesFromItems(array $items): array
    {
        $classes = [];
        foreach ($items as $item) {
            $classId = (int) ($item['kelas_id'] ?? 0);
            if ($classId <= 0 || isset($classes[$classId])) {
                continue;
            }
            $classes[$classId] = [
                'id' => $classId,
                'tingkat' => (int) ($item['kelas_tingkat'] ?? 0),
                'nama' => (string) ($item['kelas_nama'] ?? ''),
                'jurusan_nama' => (string) ($item['jurusan_nama'] ?? ''),
            ];
        }

        return array_values($classes);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $periods
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $classes
     * @param array<int, array<string, mixed>> $activities
     * @return array<int, array<string, mixed>>
     */
    private function buildGridRows(array $periods, array $items, array $classes, array $activities): array
    {
        $classIds = array_map(static fn (array $classroom): int => (int) ($classroom['id'] ?? 0), $classes);
        $activityMap = [];
        foreach ($activities as $activity) {
            $day = (string) ($activity['hari'] ?? '');
            for ($lessonNo = (int) ($activity['jam_ke_mulai'] ?? 0); $lessonNo <= (int) ($activity['jam_ke_selesai'] ?? 0); $lessonNo++) {
                if ($day !== '' && $lessonNo > 0) {
                    $activityMap[$day][$lessonNo] = (string) ($activity['nama'] ?? 'Kegiatan Tetap');
                }
            }
        }

        $itemMap = [];
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'failed') {
                continue;
            }
            $day = (string) ($item['hari'] ?? '');
            $classId = (int) ($item['kelas_id'] ?? 0);
            $start = (int) ($item['jam_ke_mulai'] ?? 0);
            $end = (int) ($item['jam_ke_selesai'] ?? 0);
            if ($day === '' || $classId <= 0 || $start <= 0 || $end < $start) {
                continue;
            }
            for ($lessonNo = $start; $lessonNo <= $end; $lessonNo++) {
                $itemMap[$day][$lessonNo][$classId][] = $item;
            }
        }

        $rows = [];
        foreach (array_keys(AutomaticSchedule::DAYS) as $day) {
            if (!isset($periods[$day])) {
                continue;
            }
            ksort($periods[$day]);
            foreach ($periods[$day] as $lessonNo => $period) {
                $cells = [];
                foreach ($classIds as $classId) {
                    $cells[$classId] = $itemMap[$day][$lessonNo][$classId] ?? [];
                }
                $rows[] = [
                    'day' => $day,
                    'lesson_no' => (int) $lessonNo,
                    'time' => substr((string) ($period['waktu_mulai'] ?? ''), 0, 5) . '-' . substr((string) ($period['waktu_selesai'] ?? ''), 0, 5),
                    'type' => isset($activityMap[$day][$lessonNo]) ? 'kegiatan' : (string) ($period['tipe'] ?? 'pelajaran'),
                    'label' => $activityMap[$day][$lessonNo] ?? (string) ($period['label'] ?? ''),
                    'cells' => $cells,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<string, mixed> $context
     * @return array{rows: array<int, array<int, string>>, options: array<string, mixed>}
     */
    private function buildMatrixSheet(array $matrix, array $context): array
    {
        $classes = $matrix['classes'] ?? [];
        $classCount = max(1, count($classes));
        $dayColumnCount = 2 + $classCount;
        $leftColumns = $dayColumnCount * 3;
        $recapStart = $leftColumns + 2;
        $recapColumns = 8;
        $totalColumns = $recapStart + $recapColumns - 1;
        $rows = [];
        $styles = [];
        $merges = [];
        $rowHeights = [];
        $columnWidths = [];

        $newRow = static fn (): array => array_fill(0, $totalColumns, '');
        $ensureRow = static function (int $row) use (&$rows, $newRow): void {
            while (count($rows) < $row) {
                $rows[] = $newRow();
            }
        };
        $set = static function (int $row, int $column, string $value) use (&$rows, $ensureRow): void {
            $ensureRow($row);
            $rows[$row - 1][$column - 1] = $value;
        };
        $style = function (string $range, array $style) use (&$styles): void {
            $styles[$range] = $style;
        };
        $merge = function (int $row1, int $col1, int $row2, int $col2) use (&$merges): void {
            if ($row1 === $row2 && $col1 === $col2) {
                return;
            }
            $merges[] = $this->xlsxColumn($col1) . $row1 . ':' . $this->xlsxColumn($col2) . $row2;
        };

        $set(1, 1, 'JADWAL PELAJARAN');
        $set(2, 1, strtoupper((string) ($matrix['school_name'] ?? 'SEKOLAH')));
        $set(3, 1, 'TAHUN PELAJARAN ' . (string) ($matrix['school_year'] ?? ''));
        $merge(1, 1, 1, $leftColumns);
        $merge(2, 1, 2, $leftColumns);
        $merge(3, 1, 3, $leftColumns);
        $style('A1:' . $this->xlsxColumn($leftColumns) . '3', ['bold' => true, 'align' => 'center', 'valign' => 'center', 'font_size' => 12]);

        $semesterStart = max($recapStart + 5, $totalColumns - 2);
        $set(1, $semesterStart, 'SEMESTER');
        $set(2, $semesterStart, str_contains(strtolower((string) ($matrix['semester_label'] ?? '')), 'genap') ? 'GENAP' : 'GANJIL');
        $merge(1, $semesterStart, 1, $totalColumns);
        $merge(2, $semesterStart, 3, $totalColumns);
        $style($this->xlsxColumn($semesterStart) . '1:' . $this->xlsxColumn($totalColumns) . '3', ['bold' => true, 'align' => 'center', 'valign' => 'center', 'fill' => 'B7D7A8', 'border' => true, 'wrap' => true]);

        foreach (range(1, $totalColumns) as $column) {
            $columnWidths[$column] = 5.0;
        }
        for ($dayIndex = 0; $dayIndex < 3; $dayIndex++) {
            $offset = ($dayIndex * $dayColumnCount) + 1;
            $columnWidths[$offset] = 10.5;
            $columnWidths[$offset + 1] = 5.0;
            for ($column = $offset + 2; $column < $offset + $dayColumnCount; $column++) {
                $columnWidths[$column] = 5.0;
            }
        }
        $columnWidths[$leftColumns + 1] = 2.0;
        foreach ([3.0, 24, 8, 24, 5, 5, 5, 7] as $index => $width) {
            $columnWidths[$recapStart + $index] = $width;
        }

        $periodsByDay = $this->periodsByDay($matrix['grid_rows'] ?? []);
        $groupStartRow = 5;
        foreach (($matrix['day_groups'] ?? []) as $groupIndex => $group) {
            $maxRows = 0;
            foreach ($group as $day) {
                $maxRows = max($maxRows, count($periodsByDay[$day] ?? []));
            }
            $titleRow = $groupStartRow;
            $headerRow = $groupStartRow + 1;
            $bodyStart = $groupStartRow + 2;
            $rowHeights[$titleRow] = 18;
            $rowHeights[$headerRow] = 24;
            for ($dayPosition = 0; $dayPosition < 3; $dayPosition++) {
                $day = $group[$dayPosition] ?? '';
                $dayStart = ($dayPosition * $dayColumnCount) + 1;
                $dayEnd = $dayStart + $dayColumnCount - 1;
                $set($titleRow, $dayStart, strtoupper((string) (($matrix['day_options'][$day] ?? ucfirst($day)))));
                $merge($titleRow, $dayStart, $titleRow, $dayEnd);
                $style($this->xlsxColumn($dayStart) . $titleRow . ':' . $this->xlsxColumn($dayEnd) . $titleRow, ['bold' => true, 'align' => 'center', 'valign' => 'center', 'fill' => 'D9D9D9', 'border' => true]);

                $set($headerRow, $dayStart, 'WAKTU');
                $set($headerRow, $dayStart + 1, 'JAM KE');
                $style($this->xlsxColumn($dayStart) . $headerRow . ':' . $this->xlsxColumn($dayStart + 1) . $headerRow, ['bold' => true, 'align' => 'center', 'valign' => 'center', 'fill' => 'F1F5F9', 'border' => true, 'wrap' => true]);
                foreach ($classes as $classIndex => $classroom) {
                    $column = $dayStart + 2 + $classIndex;
                    $set($headerRow, $column, $this->shortClassLabel($classroom));
                    $style($this->xlsxColumn($column) . $headerRow, ['bold' => true, 'align' => 'center', 'valign' => 'center', 'fill' => 'F1F5F9', 'border' => true, 'wrap' => true]);
                }
            }

            for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
                $sheetRow = $bodyStart + $rowIndex;
                $rowHeights[$sheetRow] = 18;
                foreach ($group as $dayPosition => $day) {
                    $period = $periodsByDay[$day][$rowIndex] ?? null;
                    $dayStart = ($dayPosition * $dayColumnCount) + 1;
                    $classStart = $dayStart + 2;
                    $classEnd = $dayStart + $dayColumnCount - 1;
                    if ($period === null) {
                        $style($this->xlsxColumn($dayStart) . $sheetRow . ':' . $this->xlsxColumn($classEnd) . $sheetRow, ['border' => true]);
                        continue;
                    }
                    $set($sheetRow, $dayStart, (string) ($period['time'] ?? '-'));
                    $set($sheetRow, $dayStart + 1, (string) ($period['lesson_no'] ?? '-'));
                    $style($this->xlsxColumn($dayStart) . $sheetRow . ':' . $this->xlsxColumn($dayStart + 1) . $sheetRow, ['align' => 'center', 'valign' => 'center', 'border' => true, 'font_size' => 10]);

                    $isBlocked = ($period['type'] ?? 'pelajaran') !== 'pelajaran';
                    if ($isBlocked) {
                        $set($sheetRow, $classStart, strtoupper((string) (($period['label'] ?? '') !== '' ? $period['label'] : 'Kegiatan')));
                        $merge($sheetRow, $classStart, $sheetRow, $classEnd);
                        $style($this->xlsxColumn($classStart) . $sheetRow . ':' . $this->xlsxColumn($classEnd) . $sheetRow, ['bold' => true, 'align' => 'center', 'valign' => 'center', 'border' => true, 'wrap' => true, 'font_size' => 10]);
                        continue;
                    }

                    foreach ($classes as $classIndex => $classroom) {
                        $classId = (int) ($classroom['id'] ?? 0);
                        $cellItems = $period['cells'][$classId] ?? [];
                        $cellItem = $cellItems[0] ?? null;
                        $assignmentId = is_array($cellItem) ? (int) ($cellItem['guru_mata_pelajaran_id'] ?? 0) : 0;
                        $teacherId = is_array($cellItem) ? (int) ($cellItem['guru_id'] ?? 0) : 0;
                        $code = (string) (($matrix['assignment_codes'][$assignmentId] ?? ''));
                        $column = $classStart + $classIndex;
                        $set($sheetRow, $column, $code);
                        $cellStyle = ['bold' => true, 'align' => 'center', 'valign' => 'center', 'border' => true, 'font_size' => 9];
                        if ($code !== '') {
                            $cellStyle['fill'] = $this->excelColor((string) ($matrix['teacher_colors'][$teacherId] ?? '#ffffff'));
                        }
                        $style($this->xlsxColumn($column) . $sheetRow, $cellStyle);
                    }
                }
            }

            $groupStartRow = $bodyStart + $maxRows + 2;
        }

        $legendRow = $groupStartRow;
        $legendItems = [];
        foreach (($matrix['subject_legend'] ?? []) as $code => $name) {
            $legendItems[] = trim((string) $name . ((string) $code !== '' ? ' (' . (string) $code . ')' : ''));
        }
        $legendHalf = max(1, (int) ceil(count($legendItems) / 2));
        foreach ($legendItems as $index => $label) {
            $row = $legendRow + ($index % $legendHalf);
            $col = $index < $legendHalf ? 1 : (int) floor($leftColumns / 2) + 1;
            $endCol = $index < $legendHalf ? (int) floor($leftColumns / 2) : $leftColumns;
            $set($row, $col, strtoupper($label));
            $merge($row, $col, $row, $endCol);
            $style($this->xlsxColumn($col) . $row . ':' . $this->xlsxColumn($endCol) . $row, ['border' => true, 'bold' => true, 'font_size' => 10, 'wrap' => true]);
        }

        $this->addRecapToSheet($matrix, $rows, $styles, $merges, $rowHeights, $recapStart, $newRow, $totalColumns);

        return [
            'rows' => $rows,
            'options' => [
                'cell_styles' => $styles,
                'merges' => $merges,
                'row_heights' => $rowHeights,
                'column_widths' => $columnWidths,
                'page_setup' => ['orientation' => 'landscape', 'paper_size' => 9],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<int, array<int, string>> $rows
     * @param array<string, array<string, mixed>> $styles
     * @param array<int, string> $merges
     * @param array<int, float|int> $rowHeights
     * @param callable(): array<int, string> $newRow
     */
    private function addRecapToSheet(array $matrix, array &$rows, array &$styles, array &$merges, array &$rowHeights, int $recapStart, callable $newRow, int $totalColumns): void
    {
        $ensureRow = static function (int $row) use (&$rows, $newRow): void {
            while (count($rows) < $row) {
                $rows[] = $newRow();
            }
        };
        $set = static function (int $row, int $column, string $value) use (&$rows, $ensureRow): void {
            $ensureRow($row);
            $rows[$row - 1][$column - 1] = $value;
        };
        $style = function (string $range, array $style) use (&$styles): void {
            $styles[$range] = $style;
        };
        $merge = function (int $row1, int $col1, int $row2, int $col2) use (&$merges): void {
            if ($row1 === $row2 && $col1 === $col2) {
                return;
            }
            $merges[] = $this->xlsxColumn($col1) . $row1 . ':' . $this->xlsxColumn($col2) . $row2;
        };

        $startRow = 5;
        $headers = ['NO', 'NAMA GURU', 'KODE GURU', 'MATA PELAJARAN', 'KELAS', '', '', 'JML JAM'];
        foreach ($headers as $index => $header) {
            $set($startRow, $recapStart + $index, $header);
        }
        foreach ([0, 1, 2, 3, 7] as $offset) {
            $merge($startRow, $recapStart + $offset, $startRow + 1, $recapStart + $offset);
        }
        $merge($startRow, $recapStart + 4, $startRow, $recapStart + 6);
        foreach (['X', 'XI', 'XII'] as $index => $label) {
            $set($startRow + 1, $recapStart + 4 + $index, $label);
        }
        $style($this->xlsxColumn($recapStart) . $startRow . ':' . $this->xlsxColumn($recapStart + 7) . ($startRow + 1), ['bold' => true, 'align' => 'center', 'valign' => 'center', 'fill' => 'D9D9D9', 'border' => true, 'wrap' => true, 'font_size' => 10]);

        $row = $startRow + 2;
        $number = 1;
        $totalX = 0;
        $totalXi = 0;
        $totalXii = 0;
        $totalHours = 0;
        foreach (($matrix['assignment_recap'] ?? []) as $recap) {
            $xHours = (int) ($recap['levels'][10] ?? 0);
            $xiHours = (int) ($recap['levels'][11] ?? 0);
            $xiiHours = (int) ($recap['levels'][12] ?? 0);
            $hours = (int) ($recap['hours'] ?? 0);
            $totalX += $xHours;
            $totalXi += $xiHours;
            $totalXii += $xiiHours;
            $totalHours += $hours;

            $values = [
                (string) $number++,
                (string) ($recap['teacher_name'] ?? '-'),
                (string) ($recap['code'] ?? ''),
                (string) ($recap['subject_name'] ?? '-'),
                $xHours > 0 ? (string) $xHours : '',
                $xiHours > 0 ? (string) $xiHours : '',
                $xiiHours > 0 ? (string) $xiiHours : '',
                (string) $hours,
            ];
            foreach ($values as $index => $value) {
                $set($row, $recapStart + $index, $value);
            }
            $rowHeights[$row] = 18;
            $style($this->xlsxColumn($recapStart) . $row . ':' . $this->xlsxColumn($recapStart + 7) . $row, ['border' => true, 'valign' => 'center', 'wrap' => true, 'font_size' => 9]);
            $style($this->xlsxColumn($recapStart) . $row, ['border' => true, 'align' => 'center', 'valign' => 'center', 'font_size' => 9]);
            $style($this->xlsxColumn($recapStart + 2) . $row, ['border' => true, 'align' => 'center', 'valign' => 'center', 'bold' => true, 'font_size' => 9]);
            $style($this->xlsxColumn($recapStart + 4) . $row . ':' . $this->xlsxColumn($recapStart + 7) . $row, ['border' => true, 'align' => 'center', 'valign' => 'center', 'bold' => true, 'font_size' => 9]);
            $style($this->xlsxColumn($recapStart + 1) . $row, ['border' => true, 'valign' => 'center', 'wrap' => true, 'font_size' => 9, 'fill' => $this->excelColor((string) ($recap['color'] ?? '#ffffff'))]);
            $style($this->xlsxColumn($recapStart + 3) . $row, ['border' => true, 'valign' => 'center', 'wrap' => true, 'bold' => true, 'font_size' => 9]);
            $row++;
        }

        foreach (['JUMLAH', '', '', '', (string) $totalX, (string) $totalXi, (string) $totalXii, (string) $totalHours] as $index => $value) {
            $set($row, $recapStart + $index, $value);
        }
        $merge($row, $recapStart, $row, $recapStart + 3);
        $style($this->xlsxColumn($recapStart) . $row . ':' . $this->xlsxColumn($recapStart + 7) . $row, ['border' => true, 'bold' => true, 'align' => 'center', 'valign' => 'center', 'font_size' => 9]);

        $signatureStart = $row + 3;
        $dateLine = trim((string) ($matrix['signature_city'] ?? ''));
        $dateLabel = trim((string) ($matrix['signature_date_label'] ?? ''));
        $dateLine = trim($dateLine . ($dateLine !== '' && $dateLabel !== '' ? ', ' : '') . $dateLabel);
        foreach ([
            $dateLine,
            'Kepala Sekolah',
            'TTD QR',
            (string) ($matrix['headmaster_name'] ?? '________________'),
            trim((string) ($matrix['headmaster_nip'] ?? '')) !== '' ? 'NIP. ' . trim((string) ($matrix['headmaster_nip'] ?? '')) : '',
            'QR: ' . (string) ($matrix['signature_qr_value'] ?? ''),
        ] as $index => $value) {
            if ($value === '') {
                continue;
            }
            $signatureRow = $signatureStart + $index;
            $set($signatureRow, $recapStart, $value);
            $merge($signatureRow, $recapStart, $signatureRow, $recapStart + 7);
            $rowHeights[$signatureRow] = $index === 5 ? 32 : 18;
            $style($this->xlsxColumn($recapStart) . $signatureRow . ':' . $this->xlsxColumn($recapStart + 7) . $signatureRow, [
                'align' => 'center',
                'valign' => 'center',
                'wrap' => true,
                'font_size' => $index === 5 ? 7 : 9,
                'bold' => in_array($index, [1, 2, 3], true),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<string, mixed> $context
     */
    private function renderMatrixPdf(\FPDF $pdf, array $matrix, array $context): void
    {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.15);
        $pageWidth = 330.0;
        $leftMargin = 5.0;
        $rightMargin = 5.0;
        $usableWidth = $pageWidth - $leftMargin - $rightMargin;
        $leftWidth = 215.0;
        $gap = 3.0;
        $recapWidth = $usableWidth - $leftWidth - $gap;
        $startY = 23.0;

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY($leftMargin, 7);
        $pdf->Cell($usableWidth, 5, self::convert('JADWAL PELAJARAN'), 0, 1, 'C');
        $pdf->SetX($leftMargin);
        $pdf->Cell($usableWidth, 5, self::convert(strtoupper((string) ($matrix['school_name'] ?? 'SEKOLAH'))), 0, 1, 'C');
        $pdf->SetX($leftMargin);
        $pdf->Cell($usableWidth, 5, self::convert('TAHUN PELAJARAN ' . (string) ($matrix['school_year'] ?? '')), 0, 1, 'C');

        $semesterText = str_contains(strtolower((string) ($matrix['semester_label'] ?? '')), 'genap') ? 'GENAP' : 'GANJIL';
        $this->setPdfFill($pdf, '#B7D7A8');
        $pdf->SetXY($pageWidth - $rightMargin - 36, 8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(36, 12, self::convert("SEMESTER\n" . $semesterText), 1, 0, 'C', true);

        $periodsByDay = $this->periodsByDay($matrix['grid_rows'] ?? []);
        $y = $startY;
        foreach (($matrix['day_groups'] ?? []) as $group) {
            $maxRows = 0;
            foreach ($group as $day) {
                $maxRows = max($maxRows, count($periodsByDay[$day] ?? []));
            }
            $this->renderPdfDayGroup($pdf, $matrix, $periodsByDay, $group, $leftMargin, $y, $leftWidth, $maxRows);
            $y += 10.1 + ($maxRows * 4.1) + 3.0;
        }

        $this->renderPdfSubjectLegend($pdf, $matrix, $leftMargin, $y, $leftWidth);
        $recapBottomY = $this->renderPdfRecap($pdf, $matrix, $leftMargin + $leftWidth + $gap, $startY, $recapWidth);
        $this->renderPdfSignature($pdf, $matrix, $leftMargin + $leftWidth + $gap, $recapBottomY + 5.0, $recapWidth);
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<string, array<int, array<string, mixed>>> $periodsByDay
     * @param array<int, string> $group
     */
    private function renderPdfDayGroup(\FPDF $pdf, array $matrix, array $periodsByDay, array $group, float $x, float $y, float $width, int $maxRows): void
    {
        $classes = $matrix['classes'] ?? [];
        $classCount = max(1, count($classes));
        $dayWidth = $width / 3;
        $timeWidth = 13.0;
        $lessonWidth = 7.0;
        $classWidth = max(3.0, ($dayWidth - $timeWidth - $lessonWidth) / $classCount);
        $titleH = 4.8;
        $headerH = 5.3;
        $rowH = 4.1;

        $pdf->SetFont('Arial', 'B', 7.2);
        foreach ($group as $dayIndex => $day) {
            $dayX = $x + ($dayIndex * $dayWidth);
            $this->pdfCell($pdf, $dayX, $y, $dayWidth, $titleH, strtoupper((string) (($matrix['day_options'][$day] ?? ucfirst($day)))), 'C', '#D9D9D9', true);
            $this->pdfCell($pdf, $dayX, $y + $titleH, $timeWidth, $headerH, 'WAKTU', 'C', '#F1F5F9', true);
            $this->pdfCell($pdf, $dayX + $timeWidth, $y + $titleH, $lessonWidth, $headerH, "JAM\nKE", 'C', '#F1F5F9', true);
            foreach ($classes as $classIndex => $classroom) {
                $this->pdfCell($pdf, $dayX + $timeWidth + $lessonWidth + ($classIndex * $classWidth), $y + $titleH, $classWidth, $headerH, $this->shortClassLabel($classroom), 'C', '#F1F5F9', true);
            }
        }

        $pdf->SetFont('Arial', '', 6.8);
        for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
            foreach ($group as $dayIndex => $day) {
                $period = $periodsByDay[$day][$rowIndex] ?? null;
                $dayX = $x + ($dayIndex * $dayWidth);
                $rowY = $y + $titleH + $headerH + ($rowIndex * $rowH);
                if ($period === null) {
                    $this->pdfCell($pdf, $dayX, $rowY, $dayWidth, $rowH, '', 'C', '#FFFFFF', false);
                    continue;
                }

                $this->pdfCell($pdf, $dayX, $rowY, $timeWidth, $rowH, (string) ($period['time'] ?? '-'), 'C', '#FFFFFF', false);
                $this->pdfCell($pdf, $dayX + $timeWidth, $rowY, $lessonWidth, $rowH, (string) ($period['lesson_no'] ?? '-'), 'C', '#FFFFFF', false);
                $isBlocked = ($period['type'] ?? 'pelajaran') !== 'pelajaran';
                if ($isBlocked) {
                    $pdf->SetFont('Arial', 'B', 6.8);
                    $this->pdfCell($pdf, $dayX + $timeWidth + $lessonWidth, $rowY, $dayWidth - $timeWidth - $lessonWidth, $rowH, strtoupper((string) (($period['label'] ?? '') !== '' ? $period['label'] : 'Kegiatan')), 'C', '#FFFFFF', false);
                    $pdf->SetFont('Arial', '', 6.8);
                    continue;
                }

                foreach ($classes as $classIndex => $classroom) {
                    $classId = (int) ($classroom['id'] ?? 0);
                    $cellItem = ($period['cells'][$classId] ?? [])[0] ?? null;
                    $assignmentId = is_array($cellItem) ? (int) ($cellItem['guru_mata_pelajaran_id'] ?? 0) : 0;
                    $teacherId = is_array($cellItem) ? (int) ($cellItem['guru_id'] ?? 0) : 0;
                    $code = (string) (($matrix['assignment_codes'][$assignmentId] ?? ''));
                    $fill = $code !== '' ? (string) ($matrix['teacher_colors'][$teacherId] ?? '#FFFFFF') : '#FFFFFF';
                    $pdf->SetFont('Arial', 'B', 6.4);
                    $this->pdfCell($pdf, $dayX + $timeWidth + $lessonWidth + ($classIndex * $classWidth), $rowY, $classWidth, $rowH, $code, 'C', $fill, $code !== '');
                    $pdf->SetFont('Arial', '', 6.8);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $matrix
     */
    private function renderPdfSubjectLegend(\FPDF $pdf, array $matrix, float $x, float $y, float $width): void
    {
        $items = [];
        foreach (($matrix['subject_legend'] ?? []) as $code => $name) {
            $items[] = strtoupper(trim((string) $name . ((string) $code !== '' ? ' (' . (string) $code . ')' : '')));
        }
        if (empty($items)) {
            return;
        }

        $half = max(1, (int) ceil(count($items) / 2));
        $cellW = $width / 2;
        $rowH = 4.0;
        $pdf->SetFont('Arial', 'B', 6.8);
        foreach ($items as $index => $label) {
            $column = $index < $half ? 0 : 1;
            $row = $index % $half;
            $this->pdfCell($pdf, $x + ($column * $cellW), $y + ($row * $rowH), $cellW, $rowH, $this->clip($label, 46), 'L', '#FFFFFF', false);
        }
    }

    /**
     * @param array<string, mixed> $matrix
     */
    private function renderPdfRecap(\FPDF $pdf, array $matrix, float $x, float $y, float $width): float
    {
        $weights = [3, 31, 9, 31, 5, 5, 5, 8];
        $sum = array_sum($weights);
        $cols = array_map(static fn (int $weight): float => $width * ($weight / $sum), $weights);
        $rowH = 4.1;
        $pdf->SetFont('Arial', 'B', 6.8);
        $headers = ['NO', 'NAMA GURU', "KODE\nGURU", 'MATA PELAJARAN', 'X', 'XI', 'XII', "JML\nJAM"];
        $cx = $x;
        foreach ($headers as $index => $header) {
            $height = in_array($index, [4, 5, 6], true) ? $rowH : $rowH * 2;
            $this->pdfCell($pdf, $cx, $y, $cols[$index], $height, $header, 'C', '#D9D9D9', true);
            $cx += $cols[$index];
        }
        $this->pdfCell($pdf, $x + array_sum(array_slice($cols, 0, 4)), $y, $cols[4] + $cols[5] + $cols[6], $rowH, 'KELAS', 'C', '#D9D9D9', true);
        $bodyY = $y + ($rowH * 2);
        $pdf->SetFont('Arial', '', 6.4);
        $number = 1;
        $totalX = 0;
        $totalXi = 0;
        $totalXii = 0;
        $totalHours = 0;
        foreach (($matrix['assignment_recap'] ?? []) as $recap) {
            $xHours = (int) ($recap['levels'][10] ?? 0);
            $xiHours = (int) ($recap['levels'][11] ?? 0);
            $xiiHours = (int) ($recap['levels'][12] ?? 0);
            $hours = (int) ($recap['hours'] ?? 0);
            $totalX += $xHours;
            $totalXi += $xiHours;
            $totalXii += $xiiHours;
            $totalHours += $hours;
            $values = [
                (string) $number++,
                (string) ($recap['teacher_name'] ?? '-'),
                (string) ($recap['code'] ?? ''),
                (string) ($recap['subject_name'] ?? '-'),
                $xHours > 0 ? (string) $xHours : '',
                $xiHours > 0 ? (string) $xiHours : '',
                $xiiHours > 0 ? (string) $xiiHours : '',
                (string) $hours,
            ];
            $cx = $x;
            foreach ($values as $index => $value) {
                $fill = $index === 1 ? (string) ($recap['color'] ?? '#FFFFFF') : '#FFFFFF';
                $align = in_array($index, [0, 2, 4, 5, 6, 7], true) ? 'C' : 'L';
                $text = in_array($index, [1, 3], true) ? $this->clip((string) $value, 28) : $this->clip((string) $value, 16);
                $this->pdfCell($pdf, $cx, $bodyY, $cols[$index], $rowH, $text, $align, $fill, $index === 1);
                $cx += $cols[$index];
            }
            $bodyY += $rowH;
        }

        $pdf->SetFont('Arial', 'B', 6.6);
        $this->pdfCell($pdf, $x, $bodyY, array_sum(array_slice($cols, 0, 4)), $rowH, 'JUMLAH', 'L', '#FFFFFF', false);
        $cx = $x + array_sum(array_slice($cols, 0, 4));
        foreach ([$totalX, $totalXi, $totalXii, $totalHours] as $index => $value) {
            $this->pdfCell($pdf, $cx, $bodyY, $cols[$index + 4], $rowH, (string) $value, 'C', '#FFFFFF', false);
            $cx += $cols[$index + 4];
        }

        return $bodyY + $rowH;
    }

    /**
     * @param array<string, mixed> $matrix
     */
    private function renderPdfSignature(\FPDF $pdf, array $matrix, float $x, float $y, float $width): void
    {
        if ($y > 170.0) {
            return;
        }

        $city = trim((string) ($matrix['signature_city'] ?? ''));
        $date = trim((string) ($matrix['signature_date_label'] ?? ''));
        $dateLine = trim($city . ($city !== '' && $date !== '' ? ', ' : '') . $date);
        $headmaster = trim((string) ($matrix['headmaster_name'] ?? '________________'));
        $nip = trim((string) ($matrix['headmaster_nip'] ?? ''));
        $qrValue = trim((string) ($matrix['signature_qr_value'] ?? ''));

        $pdf->SetFont('Arial', '', 7.0);
        if ($dateLine !== '') {
            $this->pdfText($pdf, $x, $y, $width, 4.0, $dateLine, 'C');
            $y += 4.0;
        }
        $this->pdfText($pdf, $x, $y, $width, 4.0, 'Kepala Sekolah', 'C');
        $y += 5.0;

        $qrSize = 18.0;
        $qrX = $x + (($width - $qrSize) / 2);
        $qrPath = $qrValue !== '' ? $this->createQrImage($qrValue) : null;
        if ($qrPath !== null) {
            $pdf->Image($qrPath, $qrX, $y, $qrSize, $qrSize, 'png');
            @unlink($qrPath);
        } else {
            $this->pdfCell($pdf, $qrX, $y, $qrSize, $qrSize, 'QR', 'C', '#FFFFFF', false);
        }

        $y += $qrSize + 3.0;
        $pdf->SetFont('Arial', 'B', 7.2);
        $this->pdfText($pdf, $x, $y, $width, 4.0, $headmaster, 'C');
        if ($nip !== '') {
            $pdf->SetFont('Arial', '', 6.6);
            $this->pdfText($pdf, $x, $y + 4.0, $width, 3.5, 'NIP. ' . $nip, 'C');
        }
    }

    private function pdfCell(\FPDF $pdf, float $x, float $y, float $w, float $h, string $text, string $align = 'C', string $fill = '#FFFFFF', bool $useFill = true): void
    {
        $this->setPdfFill($pdf, $fill);
        $pdf->SetXY($x, $y);
        $text = str_replace("\n", ' ', $text);
        $pdf->Cell($w, $h, self::convert($this->clip($text, max(3, (int) floor($w * 1.8)))), 1, 0, $align, $useFill);
    }

    private function pdfText(\FPDF $pdf, float $x, float $y, float $w, float $h, string $text, string $align = 'C'): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, self::convert($this->clip($text, max(3, (int) floor($w * 1.8)))), 0, 0, $align, false);
    }

    private function setPdfFill(\FPDF $pdf, string $hex): void
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $pdf->SetFillColor($r, $g, $b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $hex) ?? '');
        if (strlen($hex) === 8) {
            $hex = substr($hex, 2);
        }
        if (strlen($hex) !== 6) {
            $hex = 'FFFFFF';
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<string, mixed> $context
     */
    private function renderMatrixHtml(array $matrix, array $context, bool $withPrintButton): string
    {
        $periodsByDay = $this->periodsByDay($matrix['grid_rows'] ?? []);
        $classes = $matrix['classes'] ?? [];
        $semesterText = str_contains(strtolower((string) ($matrix['semester_label'] ?? '')), 'genap') ? 'GENAP' : 'GANJIL';
        $html = '<style>
            @page { size: 330mm 210mm; margin: 3mm; }
            * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; }
            body { margin: 0; font-family: Arial, sans-serif; color: #020617; background: #fff; }
            .toolbar { padding: 10px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; }
            .toolbar button { border: 1px solid #0f766e; background: #0f766e; color: #fff; border-radius: 6px; padding: 8px 12px; font-weight: 700; cursor: pointer; }
            .sheet { width: 324mm; margin: 0 auto; padding: 0; }
            .title { position: relative; text-align: center; font-weight: 900; text-transform: uppercase; line-height: 1.15; margin-bottom: 1mm; font-size: 12px; }
            .semester { position: absolute; right: 0; top: 0; min-width: 28mm; border: 1px solid #020617; background: #b7d7a8; padding: 1.4mm; }
            .grid { display: grid; grid-template-columns: 215mm 104mm; gap: 3mm; align-items: start; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7px; line-height: 1.02; }
            th, td { border: 1px solid #020617; padding: .45mm .55mm; overflow: hidden; text-align: center; vertical-align: middle; }
            th { background: #d9d9d9; font-weight: 900; text-transform: uppercase; }
            .subhead { background: #f1f5f9; }
            .left { text-align: left; }
            .bold { font-weight: 900; }
            .legend { margin-top: 2mm; display: grid; grid-template-columns: 1fr 1fr; gap: .7mm; font-size: 6.8px; font-weight: 700; text-transform: uppercase; }
            .legend div { border: 1px solid #020617; padding: .6mm 1mm; }
            .day-table { margin-bottom: .8mm; font-size: 10px; line-height: 1; }
            .day-table th, .day-table td { padding: .28mm .35mm; }
            .time-col { width: 13mm; }
            .jam-col { width: 7mm; }
            .recap { font-size: 6.5px; }
            .recap-no { width: 3.8mm; }
            .signature { margin-top: 1.5mm; text-align: center; font-size: 8px; line-height: 1.2; page-break-inside: avoid; }
            .signature p { margin: 0 0 1mm; }
            .signature-qr { width: 18mm; height: 18mm; margin: 1mm auto; display: flex; align-items: center; justify-content: center; }
            .signature-qr img { width: 18mm; height: 18mm; display: block; }
            .signature-name { font-weight: 900; text-decoration: underline; }
            .signature-nip { font-size: 7px; }
            @media print {
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
                html, body { width: 330mm; min-height: 210mm; }
                .no-print { display: none !important; }
                .sheet { margin: 0; }
            }
        </style>';
        if ($withPrintButton) {
            $html .= '<div class="toolbar no-print"><button onclick="window.print()">Cetak F4</button></div>';
        }
        $html .= '<div class="sheet"><div class="title">';
        $html .= '<div>JADWAL PELAJARAN</div><div>' . $this->e(strtoupper((string) ($matrix['school_name'] ?? 'SEKOLAH'))) . '</div><div>TAHUN PELAJARAN ' . $this->e((string) ($matrix['school_year'] ?? '')) . '</div>';
        $html .= '<div class="semester">SEMESTER<br>' . $this->e($semesterText) . '</div></div>';
        $html .= '<div class="grid"><div>';
        foreach (($matrix['day_groups'] ?? []) as $group) {
            $maxRows = 0;
            foreach ($group as $day) {
                $maxRows = max($maxRows, count($periodsByDay[$day] ?? []));
            }
            $html .= '<table class="day-table"><thead><tr>';
            foreach ($group as $day) {
                $html .= '<th colspan="' . (2 + count($classes)) . '">' . $this->e(strtoupper((string) (($matrix['day_options'][$day] ?? ucfirst($day))))) . '</th>';
            }
            $html .= '</tr><tr>';
            foreach ($group as $day) {
                $html .= '<th class="subhead time-col">Waktu</th><th class="subhead jam-col">Jam<br>Ke</th>';
                foreach ($classes as $classroom) {
                    $html .= '<th class="subhead">' . $this->e($this->shortClassLabel($classroom)) . '</th>';
                }
            }
            $html .= '</tr></thead><tbody>';
            for ($rowIndex = 0; $rowIndex < $maxRows; $rowIndex++) {
                $html .= '<tr>';
                foreach ($group as $day) {
                    $period = $periodsByDay[$day][$rowIndex] ?? null;
                    if ($period === null) {
                        $html .= '<td></td><td></td><td colspan="' . count($classes) . '"></td>';
                        continue;
                    }
                    $html .= '<td class="bold">' . $this->e((string) ($period['time'] ?? '-')) . '</td><td class="bold">' . $this->e((string) ($period['lesson_no'] ?? '-')) . '</td>';
                    if (($period['type'] ?? 'pelajaran') !== 'pelajaran') {
                        $html .= '<td colspan="' . count($classes) . '" class="bold">' . $this->e(strtoupper((string) (($period['label'] ?? '') !== '' ? $period['label'] : 'Kegiatan'))) . '</td>';
                        continue;
                    }
                    foreach ($classes as $classroom) {
                        $classId = (int) ($classroom['id'] ?? 0);
                        $cellItem = ($period['cells'][$classId] ?? [])[0] ?? null;
                        $assignmentId = is_array($cellItem) ? (int) ($cellItem['guru_mata_pelajaran_id'] ?? 0) : 0;
                        $teacherId = is_array($cellItem) ? (int) ($cellItem['guru_id'] ?? 0) : 0;
                        $code = (string) (($matrix['assignment_codes'][$assignmentId] ?? ''));
                        $fill = $code !== '' ? (string) ($matrix['teacher_colors'][$teacherId] ?? '#ffffff') : '#ffffff';
                        $html .= '<td class="bold" style="background-color:' . $this->e($fill) . ' !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact;">' . $this->e($code) . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        if (!empty($matrix['subject_legend'])) {
            $html .= '<div class="legend">';
            foreach ($matrix['subject_legend'] as $code => $name) {
                $html .= '<div>' . $this->e((string) $name . ((string) $code !== '' ? ' (' . (string) $code . ')' : '')) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div><div>' . $this->renderHtmlRecap($matrix) . $this->renderHtmlSignature($matrix) . '</div></div></div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $matrix
     */
    private function renderHtmlSignature(array $matrix): string
    {
        $city = trim((string) ($matrix['signature_city'] ?? ''));
        $date = trim((string) ($matrix['signature_date_label'] ?? ''));
        $dateLine = trim($city . ($city !== '' && $date !== '' ? ', ' : '') . $date);
        $headmaster = trim((string) ($matrix['headmaster_name'] ?? '________________'));
        $nip = trim((string) ($matrix['headmaster_nip'] ?? ''));
        $qrDataUri = $this->createQrDataUri((string) ($matrix['signature_qr_value'] ?? ''));

        $html = '<div class="signature">';
        if ($dateLine !== '') {
            $html .= '<p>' . $this->e($dateLine) . '</p>';
        }
        $html .= '<p>Kepala Sekolah</p>';
        $html .= '<div class="signature-qr">';
        if ($qrDataUri !== null) {
            $html .= '<img src="' . $this->e($qrDataUri) . '" alt="QR TTD Kepala Sekolah">';
        } else {
            $html .= '<span>QR</span>';
        }
        $html .= '</div>';
        $html .= '<p class="signature-name">' . $this->e($headmaster !== '' ? $headmaster : '________________') . '</p>';
        if ($nip !== '') {
            $html .= '<p class="signature-nip">NIP. ' . $this->e($nip) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $matrix
     */
    private function renderHtmlRecap(array $matrix): string
    {
        $html = '<table class="recap"><colgroup><col class="recap-no"><col><col style="width:9mm"><col><col style="width:5mm"><col style="width:5mm"><col style="width:5mm"><col style="width:7mm"></colgroup><thead><tr><th rowspan="2">No</th><th rowspan="2">Nama Guru</th><th rowspan="2">Kode<br>Guru</th><th rowspan="2">Mata Pelajaran</th><th colspan="3">Kelas</th><th rowspan="2">Jml<br>Jam</th></tr><tr><th>X</th><th>XI</th><th>XII</th></tr></thead><tbody>';
        $no = 1;
        $totalX = 0;
        $totalXi = 0;
        $totalXii = 0;
        $totalHours = 0;
        foreach (($matrix['assignment_recap'] ?? []) as $row) {
            $xHours = (int) ($row['levels'][10] ?? 0);
            $xiHours = (int) ($row['levels'][11] ?? 0);
            $xiiHours = (int) ($row['levels'][12] ?? 0);
            $hours = (int) ($row['hours'] ?? 0);
            $totalX += $xHours;
            $totalXi += $xiHours;
            $totalXii += $xiiHours;
            $totalHours += $hours;
            $html .= '<tr>';
            $html .= '<td>' . $no++ . '</td>';
            $html .= '<td class="left bold" style="background-color:' . $this->e((string) ($row['color'] ?? '#ffffff')) . ' !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact;">' . $this->e((string) ($row['teacher_name'] ?? '-')) . '</td>';
            $html .= '<td class="bold">' . $this->e((string) ($row['code'] ?? '')) . '</td>';
            $html .= '<td class="left bold">' . $this->e((string) ($row['subject_name'] ?? '-')) . '</td>';
            $html .= '<td class="bold">' . ($xHours > 0 ? $xHours : '') . '</td>';
            $html .= '<td class="bold">' . ($xiHours > 0 ? $xiHours : '') . '</td>';
            $html .= '<td class="bold">' . ($xiiHours > 0 ? $xiiHours : '') . '</td>';
            $html .= '<td class="bold">' . $hours . '</td>';
            $html .= '</tr>';
        }
        $html .= '<tr><td colspan="4" class="left bold">JUMLAH</td><td class="bold">' . $totalX . '</td><td class="bold">' . $totalXi . '</td><td class="bold">' . $totalXii . '</td><td class="bold">' . $totalHours . '</td></tr>';
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function filterItems(array $items, string $scope, int $scopeId): array
    {
        if ($scope === 'kelas' && $scopeId > 0) {
            return array_values(array_filter($items, static fn (array $item): bool => (int) ($item['kelas_id'] ?? 0) === $scopeId));
        }

        if ($scope === 'guru' && $scopeId > 0) {
            return array_values(array_filter($items, static fn (array $item): bool => (int) ($item['guru_id'] ?? 0) === $scopeId));
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function teacherRecap(array $items): array
    {
        $recap = [];

        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'failed') {
                continue;
            }

            $teacherId = (int) ($item['guru_id'] ?? 0);
            if ($teacherId <= 0) {
                continue;
            }

            $recap[$teacherId] ??= [
                'teacher_name' => (string) ($item['guru_nama'] ?? 'Guru'),
                'teacher_code' => (string) ($item['guru_nip'] ?? ''),
                'hours' => 0,
                'classes' => [],
                'subjects' => [],
            ];

            $recap[$teacherId]['hours'] += (int) ($item['jumlah_jam'] ?? 0);
            $recap[$teacherId]['classes'][$this->classLabel($item)] = $this->classLabel($item);
            $subject = trim((string) ($item['mata_pelajaran_kode'] ?? '') . ' ' . (string) ($item['mata_pelajaran_nama'] ?? ''));
            if ($subject !== '') {
                $recap[$teacherId]['subjects'][$subject] = $subject;
            }
        }

        foreach ($recap as &$row) {
            $row['classes'] = array_values($row['classes']);
            $row['subjects'] = array_values($row['subjects']);
        }
        unset($row);

        usort($recap, static fn (array $left, array $right): int => strcmp($left['teacher_name'], $right['teacher_name']));

        return array_values($recap);
    }

    /**
     * @param array<int, array<string, mixed>> $gridRows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function periodsByDay(array $gridRows): array
    {
        $periodsByDay = [];
        foreach ($gridRows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day !== '') {
                $periodsByDay[$day][] = $row;
            }
        }

        return $periodsByDay;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shortClassLabel(array $row): string
    {
        $name = strtoupper(trim((string) ($row['nama'] ?? $row['kelas_nama'] ?? '')));
        $level = (int) ($row['tingkat'] ?? $row['kelas_tingkat'] ?? 0);
        $levelRoman = [10 => 'X', 11 => 'XI', 12 => 'XII'][$level] ?? (string) $level;
        $name = preg_replace('/\b(X|XI|XII|10|11|12)\b/u', '', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return trim($levelRoman . ($name !== '' ? ' ' . $name : ''));
    }

    private function xlsxColumn(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $column = (int) (($column - $remainder) / 26);
        }

        return $letter;
    }

    private function excelColor(string $hex): string
    {
        $hex = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $hex) ?? '');
        if (strlen($hex) === 8) {
            $hex = substr($hex, 2);
        }

        return strlen($hex) === 6 ? $hex : 'FFFFFF';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function createQrDataUri(string $value): ?string
    {
        $bytes = $this->createQrPngBytes($value);

        return $bytes !== null ? 'data:image/png;base64,' . base64_encode($bytes) : null;
    }

    private function createQrImage(string $value): ?string
    {
        $bytes = $this->createQrPngBytes($value);
        if ($bytes === null) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'jadwal-qr-');
        if ($temp === false) {
            return null;
        }

        $path = $temp . '.png';
        @unlink($temp);
        if (file_put_contents($path, $bytes) === false) {
            return null;
        }

        return $path;
    }

    private function createQrPngBytes(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        require_once base_path('app/Libraries/QrCode/phpqrcode.php');

        if (!class_exists('QRCode') || !defined('QR_ERROR_CORRECT_LEVEL_H')) {
            return null;
        }

        try {
            $qr = \QRCode::getMinimumQRCode($value, \QR_ERROR_CORRECT_LEVEL_H);
            $image = $qr->createImage(5, 2, 0x000000, 0xFFFFFF, true);
            if (!is_resource($image) && !($image instanceof \GdImage)) {
                return null;
            }

            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        } catch (\Throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function renderPdfHeader(\FPDF $pdf, array $columns): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        foreach ($columns as $column) {
            $pdf->Cell($column['width'], 6, self::convert((string) $column['label']), 1, 0, 'C');
        }
        $pdf->Ln();
    }

    private function conflictLabel(string $key): string
    {
        return [
            'teacher_conflicts' => 'Guru Bentrok',
            'class_conflicts' => 'Kelas Bentrok',
            'room_conflicts' => 'Ruang Bentrok',
            'blocked_slots' => 'Slot Terblokir',
            'unavailable_teachers' => 'Guru Tidak Tersedia',
            'missing_hours' => 'Jam Kurang',
            'teacher_overloads' => 'Beban Guru',
            'empty_slots' => 'Slot Kosong',
            'failed_items' => 'Gagal Dijadwalkan',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function classLabel(array $item): string
    {
        $label = trim('Kelas ' . (string) ($item['kelas_tingkat'] ?? '-') . ' ' . (string) ($item['kelas_nama'] ?? '-'));
        if (!empty($item['jurusan_nama'])) {
            $label .= ' (' . $item['jurusan_nama'] . ')';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function roomLabel(array $item): string
    {
        $label = trim((string) ($item['ruangan_kode'] ?? '') . ' ' . (string) ($item['ruangan_nama'] ?? ''));

        return $label !== '' ? $label : '-';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function lessonRange(array $item): string
    {
        $start = (int) ($item['jam_ke_mulai'] ?? 0);
        $end = (int) ($item['jam_ke_selesai'] ?? 0);
        if ($start <= 0) {
            return '-';
        }

        return $end > $start ? $start . '-' . $end : (string) $start;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function timeRange(array $item): string
    {
        $start = $this->formatTime($item['waktu_mulai'] ?? null);
        $end = $this->formatTime($item['waktu_selesai'] ?? null);

        return $start !== '-' && $end !== '-' ? $start . '-' . $end : '-';
    }

    private function dayLabel(string $day): string
    {
        return [
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'sabtu' => 'Sabtu',
            'minggu' => 'Minggu',
        ][$day] ?? '-';
    }

    private function formatTime(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }

        return substr($text, 0, 5);
    }

    private function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, max(1, $max - 3)) . '...';
    }

    private static function convert(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);

        return $converted === false ? $value : $converted;
    }
}
