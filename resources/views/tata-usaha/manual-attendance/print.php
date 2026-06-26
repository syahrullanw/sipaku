<?php
$printGroups = is_array($printGroups ?? null) ? $printGroups : [];
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : [];
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : [];
$attendanceType = (string) ($attendanceType ?? 'kelas');
$schoolName = trim((string) ($schoolProfile['nama'] ?? 'Sekolah'));
$days = range(1, 31);
$schoolAddressParts = array_values(array_filter([
    $schoolProfile['alamat'] ?? null,
    $schoolProfile['desa'] ?? null,
    $schoolProfile['kecamatan'] ?? null,
    $schoolProfile['kabupaten'] ?? null,
    $schoolProfile['provinsi'] ?? null,
], static fn ($value): bool => is_string($value) && trim($value) !== ''));
$schoolAddress = implode(', ', array_map(static fn ($value): string => trim((string) $value), $schoolAddressParts));

if (empty($printGroups)) {
    $students = is_array($students ?? null) ? $students : [];
    $selectedClass = is_array($selectedClass ?? null) ? $selectedClass : [];
    $printGroups[] = [
        'attendance_type' => $attendanceType,
        'class' => $selectedClass,
        'class_label' => trim((string) ($classLabel ?? '-')),
        'class_label_with_year' => trim((string) ($classLabelWithYear ?? ($classLabel ?? '-'))),
        'students' => $students,
        'subject_assignment' => null,
    ];
}
?>

<style>
    @page {
        size: 330mm 215mm;
        margin: 8mm;
    }

    .print-content {
        max-width: 314mm !important;
    }

    .manual-attendance-sheet {
        color: #0f172a;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 8pt;
    }

    .manual-attendance-sheet.sheet-break {
        page-break-before: always;
    }

    .manual-attendance-sheet .sheet-heading {
        margin-bottom: 4mm;
        text-align: center;
    }

    .manual-attendance-sheet .sheet-heading h1 {
        margin: 0;
        font-size: 13pt;
        font-weight: 700;
        text-transform: uppercase;
    }

    .manual-attendance-sheet .sheet-heading p {
        margin: 1mm 0 0;
        font-size: 8pt;
    }

    .manual-attendance-sheet .meta-grid {
        margin-bottom: 3mm;
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
    }

    .manual-attendance-sheet .meta-grid td {
        border: 0;
        padding: 0.6mm 1mm;
        vertical-align: top;
    }

    .manual-attendance-sheet .meta-label {
        width: 26mm;
        font-weight: 700;
        white-space: nowrap;
    }

    .manual-attendance-sheet .meta-value {
        border-bottom: 1px solid #0f172a !important;
        min-width: 58mm;
    }

    .manual-attendance-sheet .class-note-list {
        border: 1px solid #0f172a;
        border-bottom: 0;
        font-size: 7.2pt;
        margin-top: -1mm;
        padding: 1.2mm 1.6mm;
    }

    .manual-attendance-sheet .class-note-list span {
        display: inline-block;
        margin-right: 4mm;
        white-space: nowrap;
    }

    .manual-attendance-sheet .attendance-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 7.2pt;
    }

    .manual-attendance-sheet .attendance-table thead {
        display: table-header-group;
    }

    .manual-attendance-sheet .attendance-table th,
    .manual-attendance-sheet .attendance-table td {
        border: 1px solid #0f172a;
        height: 5.4mm;
        padding: 0.7mm 0.8mm;
        text-align: center;
        vertical-align: middle;
    }

    .manual-attendance-sheet .attendance-table th {
        background: #f1f5f9;
        font-weight: 700;
    }

    .manual-attendance-sheet .attendance-table .student-name {
        line-height: 1.15;
        text-align: left;
        word-break: break-word;
    }

    .manual-attendance-sheet .attendance-table .identifier {
        font-size: 6.8pt;
        line-height: 1.1;
        word-break: break-word;
    }

    .manual-attendance-sheet .attendance-table .class-section-row td {
        background: #e2e8f0;
        font-weight: 700;
        height: 5.6mm;
        text-align: left;
    }

    .manual-attendance-sheet .attendance-table .student-note {
        font-size: 6.6pt;
        line-height: 1.1;
        word-break: break-word;
    }

    .manual-attendance-sheet .signature-row {
        display: flex;
        justify-content: space-between;
        gap: 24mm;
        margin-top: 8mm;
        page-break-inside: avoid;
        text-align: center;
    }

    .manual-attendance-sheet .signature-box {
        width: 68mm;
    }

    .manual-attendance-sheet .signature-space {
        height: 22mm;
    }

    @media print {
        .manual-attendance-sheet .attendance-table th {
            background: #f1f5f9 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .manual-attendance-sheet .attendance-table .class-section-row td {
            background: #e2e8f0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<?php foreach ($printGroups as $groupIndex => $group): ?>
    <?php
        $groupClass = is_array($group['class'] ?? null) ? $group['class'] : [];
        $groupStudents = is_array($group['students'] ?? null) ? $group['students'] : [];
        $groupClassSections = is_array($group['class_sections'] ?? null) ? $group['class_sections'] : [];
        $groupAssignment = is_array($group['subject_assignment'] ?? null) ? $group['subject_assignment'] : null;
        $groupType = (string) ($group['attendance_type'] ?? $attendanceType);
        $isMultiClassGroup = $groupType === 'kelas' && !empty($group['is_multi_class']) && count($groupClassSections) > 1;
        $classLabel = trim((string) ($group['class_label'] ?? '-'));
        $schoolYearName = trim((string) ($schoolYear['nama'] ?? ($groupClass['tahun_ajaran_nama'] ?? '-')));
        $homeroomName = trim((string) ($groupClass['wali_kelas_nama'] ?? ''));
        $homeroomDisplay = $isMultiClassGroup ? 'Lihat keterangan kelas' : $homeroomName;
        $subjectLabel = trim((string) ($groupAssignment['subject_label'] ?? ''));
        $teacherLabel = trim((string) ($groupAssignment['teacher_label'] ?? ($groupAssignment['guru_nama'] ?? '')));
        $sheetTitle = $groupType === 'mapel' ? 'Daftar Hadir Siswa Mata Pelajaran' : 'Daftar Hadir Siswa';
        $primarySignerTitle = $groupType === 'mapel' ? 'Guru Pengampu' : 'Wali Kelas';
        $primarySignerName = $groupType === 'mapel' ? $teacherLabel : ($isMultiClassGroup ? '' : $homeroomName);
        $tableColumnCount = 39;
        $bodyRows = [];
        $studentNumber = 0;

        if ($isMultiClassGroup) {
            foreach ($groupClassSections as $section) {
                $sectionStudents = is_array($section['students'] ?? null) ? $section['students'] : [];
                $sectionLabel = trim((string) ($section['class_label'] ?? '-'));
                $sectionShortLabel = trim((string) ($section['class_short_label'] ?? $sectionLabel));
                $sectionHomeroom = trim((string) ($section['homeroom'] ?? ''));
                $sectionStudentCount = (int) ($section['student_count'] ?? count($sectionStudents));

                $bodyRows[] = [
                    'type' => 'section',
                    'label' => $sectionLabel,
                    'short_label' => $sectionShortLabel,
                    'homeroom' => $sectionHomeroom,
                    'student_count' => $sectionStudentCount,
                ];

                foreach ($sectionStudents as $student) {
                    $studentNote = trim((string) ($student['manual_attendance_class_short_label'] ?? $sectionShortLabel));
                    $bodyRows[] = [
                        'type' => 'student',
                        'number' => ++$studentNumber,
                        'student' => $student,
                        'note' => $studentNote,
                    ];
                }
            }
        } else {
            foreach ($groupStudents as $student) {
                $bodyRows[] = [
                    'type' => 'student',
                    'number' => ++$studentNumber,
                    'student' => $student,
                    'note' => '',
                ];
            }
        }

        $blankRows = empty($bodyRows) ? range(1, 20) : [];
    ?>
    <div class="manual-attendance-sheet <?= $groupIndex > 0 ? 'sheet-break' : '' ?>">
        <div class="sheet-heading">
            <h1><?= htmlspecialchars($sheetTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars(strtoupper($schoolName), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($schoolAddress !== ''): ?>
                <p><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <table class="meta-grid">
            <tr>
                <td class="meta-label">Kelas</td>
                <td class="meta-value"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="width: 16mm;"></td>
                <td class="meta-label">Bulan</td>
                <td class="meta-value">&nbsp;</td>
            </tr>
            <tr>
                <td class="meta-label">Tahun Ajaran</td>
                <td class="meta-value"><?= htmlspecialchars($schoolYearName, ENT_QUOTES, 'UTF-8') ?></td>
                <td></td>
                <td class="meta-label">Wali Kelas</td>
                <td class="meta-value"><?= htmlspecialchars($homeroomDisplay !== '' ? $homeroomDisplay : ' ', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php if ($groupType === 'mapel'): ?>
                <tr>
                    <td class="meta-label">Mata Pelajaran</td>
                    <td class="meta-value"><?= htmlspecialchars($subjectLabel !== '' ? $subjectLabel : ' ', ENT_QUOTES, 'UTF-8') ?></td>
                    <td></td>
                    <td class="meta-label">Guru Pengampu</td>
                    <td class="meta-value"><?= htmlspecialchars($teacherLabel !== '' ? $teacherLabel : ' ', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if ($isMultiClassGroup): ?>
            <div class="class-note-list">
                <span><strong>Keterangan kelas:</strong></span>
                <?php foreach ($groupClassSections as $section): ?>
                    <?php
                        $noteShortLabel = trim((string) ($section['class_short_label'] ?? $section['class_label'] ?? '-'));
                        $noteFullLabel = trim((string) ($section['class_label'] ?? $noteShortLabel));
                        $noteHomeroom = trim((string) ($section['homeroom'] ?? ''));
                    ?>
                    <span>
                        <strong><?= htmlspecialchars($noteShortLabel !== '' ? $noteShortLabel : '-', ENT_QUOTES, 'UTF-8') ?></strong>
                        = <?= htmlspecialchars($noteFullLabel !== '' ? $noteFullLabel : '-', ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($noteHomeroom !== ''): ?>
                            (Wali: <?= htmlspecialchars($noteHomeroom, ENT_QUOTES, 'UTF-8') ?>)
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <table class="attendance-table">
            <colgroup>
                <col style="width: 8mm;" />
                <col style="width: 22mm;" />
                <col style="width: 50mm;" />
                <col style="width: 8mm;" />
                <?php foreach ($days as $_day): ?>
                    <col style="width: 5.4mm;" />
                <?php endforeach; ?>
                <col style="width: 7mm;" />
                <col style="width: 7mm;" />
                <col style="width: 7mm;" />
                <col style="width: 25mm;" />
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">No</th>
                    <th rowspan="2">NIPD/NISN</th>
                    <th rowspan="2">Nama Siswa</th>
                    <th rowspan="2">L/P</th>
                    <th colspan="31">Tanggal</th>
                    <th colspan="3">Jumlah</th>
                    <th rowspan="2">Ket.</th>
                </tr>
                <tr>
                    <?php foreach ($days as $day): ?>
                        <th><?= $day ?></th>
                    <?php endforeach; ?>
                    <th>S</th>
                    <th>I</th>
                    <th>A</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bodyRows as $row): ?>
                    <?php
                        if (($row['type'] ?? '') === 'section') {
                            $sectionText = 'Kelas: ' . (string) ($row['label'] ?? '-');
                            $sectionHomeroom = trim((string) ($row['homeroom'] ?? ''));
                            $sectionStudentCount = (int) ($row['student_count'] ?? 0);
                            if ($sectionHomeroom !== '') {
                                $sectionText .= ' | Wali Kelas: ' . $sectionHomeroom;
                            }
                            $sectionText .= ' | ' . $sectionStudentCount . ' siswa';
                        ?>
                            <tr class="class-section-row">
                                <td colspan="<?= $tableColumnCount ?>"><?= htmlspecialchars($sectionText, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php
                            continue;
                        }

                        $student = is_array($row['student'] ?? null) ? $row['student'] : [];
                        $studentNote = trim((string) ($row['note'] ?? ''));
                        $identifier = trim((string) ($student['nipd'] ?? ''));
                        $nisn = trim((string) ($student['nisn'] ?? ''));
                        if ($identifier === '') {
                            $identifier = $nisn;
                        } elseif ($nisn !== '') {
                            $identifier .= ' / ' . $nisn;
                        }
                        $gender = trim((string) ($student['jenis_kelamin'] ?? ''));
                    ?>
                    <tr>
                        <td><?= (int) ($row['number'] ?? 0) ?></td>
                        <td class="identifier"><?= htmlspecialchars($identifier !== '' ? $identifier : '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="student-name"><?= htmlspecialchars((string) ($student['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($gender, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php foreach ($days as $_day): ?>
                            <td>&nbsp;</td>
                        <?php endforeach; ?>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="student-note"><?= htmlspecialchars($studentNote !== '' ? $studentNote : ' ', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($blankRows as $rowNumber): ?>
                    <tr>
                        <td><?= $rowNumber ?></td>
                        <td>&nbsp;</td>
                        <td class="student-name">&nbsp;</td>
                        <td>&nbsp;</td>
                        <?php foreach ($days as $_day): ?>
                            <td>&nbsp;</td>
                        <?php endforeach; ?>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="signature-row">
            <div class="signature-box">
                <p>Mengetahui,</p>
                <p><?= htmlspecialchars($primarySignerTitle, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="signature-space"></div>
                <p style="font-weight:700;text-decoration:underline;"><?= htmlspecialchars($primarySignerName !== '' ? $primarySignerName : '________________________', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="signature-box">
                <p>&nbsp;</p>
                <p>Petugas Presensi</p>
                <div class="signature-space"></div>
                <p style="font-weight:700;text-decoration:underline;">________________________</p>
            </div>
        </div>
    </div>
<?php endforeach; ?>
