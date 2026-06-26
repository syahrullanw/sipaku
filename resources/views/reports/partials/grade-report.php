<?php
    /** @var array<string, mixed> $report */
    /** @var bool $showPageBreak */
    $report = $report ?? [];
    $showPageBreak = $showPageBreak ?? false;
?>
<?php if ($showPageBreak): ?>
    <div style="page-break-before: always;"></div>
<?php endif; ?>
<?php
    $school = $report['school'] ?? [];
    $student = $report['student'] ?? [];
    $class = $report['class'] ?? [];
    $schoolYear = $report['schoolYear'] ?? null;
    $curriculum = $report['curriculum'] ?? 'k13';
    $subjects = $report['subjects'] ?? [];
    $attitudes = $report['attitudes'] ?? [];
    $attendance = $report['attendance'] ?? ['sakit' => 0, 'izin' => 0, 'bolos' => 0, 'alpa' => 0];
    $homeroomNote = trim((string) ($report['homeroomNote'] ?? ''));
    $extracurriculars = array_values(is_array($report['extracurriculars'] ?? null) ? ($report['extracurriculars'] ?? []) : []);
    $cocurriculars = array_values(is_array($report['cocurriculars'] ?? null) ? ($report['cocurriculars'] ?? []) : []);
    $prakerin = $report['prakerin'] ?? null;
    $printedDateLabel = $report['printedDateLabel'] ?? '';
    $semesterLabel = $report['semesterLabel'] ?? 'Semester 1 (Ganjil)';

    $formatScore = static function (?float $value): string {
        if ($value === null) {
            return '-';
        }

        if (abs($value - round($value)) < 0.001) {
            return number_format((int) round($value), 0, ',', '.');
        }

        return number_format($value, 1, ',', '.');
    };

    $knowledgeNote = trim($attitudes['spiritual'] ?? '');
    $socialNote = trim($attitudes['social'] ?? '');
    $kurmerMode = $curriculum === 'kurmer';
    $kurmerLevelLabels = [
        'BB' => 'Belum Berkembang',
        'MB' => 'Mulai Berkembang',
        'BSH' => 'Berkembang Sesuai Harapan',
        'SB' => 'Sangat Berkembang',
    ];
    $kurmerLevelDetails = [
        'BB' => [
            'label' => 'Belum Berkembang',
            'detail' => 'Siswa masih membutuhkan pendampingan intensif untuk mulai mengembangkan kemampuan.',
        ],
        'MB' => [
            'label' => 'Mulai Berkembang',
            'detail' => 'Siswa masih membutuhkan bimbingan dalam mengembangkan kemampuan.',
        ],
        'BSH' => [
            'label' => 'Berkembang Sesuai Harapan',
            'detail' => 'Siswa telah mengembangkan kemampuan hingga berada dalam tahap konsisten.',
        ],
        'SB' => [
            'label' => 'Sangat Berkembang',
            'detail' => 'Siswa mengembangkan kemampuan melampaui harapan.',
        ],
    ];
    $formatKurmerNarrative = static function (?string $text): string {
        $text = trim((string) $text);
        if ($text === '') {
            return '-';
        }

        // Satukan menjadi paragraf tunggal; ganti titik koma / baris baru menjadi kalimat mengalir.
        $text = str_replace(["\r\n", "\r", "\n"], '. ', $text);
        $text = str_replace(';', '. ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\.\s*/', '. ', $text) ?? $text;
        $text = preg_replace('/,\s*\./', '. ', $text) ?? $text;
        $text = preg_replace('/\.\s*,/', '. ', $text) ?? $text;
        $text = preg_replace('/\s+,/', ', ', $text) ?? $text;
        $text = preg_replace('/\.{2,}/', '.', $text) ?? $text;
        $text = trim($text, " .");

        if ($text !== '' && !preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }

        return $text;
    };
    $renderKurmerTpSources = static function ($rawSources): string {
        if (!is_array($rawSources)) {
            return '';
        }

        $items = array_values(array_filter($rawSources, static fn ($item) => is_array($item)));
        if (empty($items)) {
            return '';
        }

        $maxItems = 3;
        $formatted = [];

        foreach (array_slice($items, 0, $maxItems) as $tp) {
            $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? $tp['code'] ?? ''));
            $element = trim((string) ($tp['elemen'] ?? $tp['element'] ?? ''));
            $subElement = trim((string) ($tp['sub_elemen'] ?? $tp['subElemen'] ?? $tp['sub_element'] ?? ''));
            $tpDescription = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));

            $labelParts = [];
            if ($code !== '') {
                $labelParts[] = $code;
            }
            if ($element !== '' && $subElement !== '') {
                $labelParts[] = 'Elemen ' . $element . ' - Sub-elemen ' . $subElement;
            } elseif ($element !== '') {
                $labelParts[] = 'Elemen ' . $element;
            } elseif ($subElement !== '') {
                $labelParts[] = 'Sub-elemen ' . $subElement;
            }

            $label = !empty($labelParts) ? implode(' | ', $labelParts) : '';

            if ($label !== '' && $tpDescription !== '') {
                $formatted[] = $label . ': ' . $tpDescription;
            } elseif ($tpDescription !== '') {
                $formatted[] = $tpDescription;
            } elseif ($label !== '') {
                $formatted[] = $label;
            }
        }

        $remaining = count($items) - $maxItems;
        if ($remaining > 0) {
            $formatted[] = 'dan ' . $remaining . ' TP lainnya.';
        }

        return implode('; ', $formatted);
    };
    $buildKurmerNarrative = static function (array $payload) use ($formatKurmerNarrative, $renderKurmerTpSources): string {
        $subject = trim((string) ($payload['subject'] ?? ''));
        $subjectPhrase = $subject !== '' ? 'mata pelajaran ' . $subject : 'mata pelajaran ini';
        $capaianCode = strtoupper(trim((string) ($payload['capaianCode'] ?? '')));
        $capaianLabelRaw = trim((string) ($payload['capaianLabel'] ?? ''));
        $capaianLabel = $capaianLabelRaw === '-' ? '' : $capaianLabelRaw;
        $descriptionRaw = trim((string) ($payload['description'] ?? ''));
        $description = $formatKurmerNarrative($descriptionRaw);
        $followUp = trim((string) ($payload['tindakLanjut'] ?? ''));
        $tpSources = $payload['tpSources'] ?? [];
        $tpText = $renderKurmerTpSources($tpSources);

        $hasData = ($capaianCode !== '') || ($capaianLabel !== '') || ($description !== '' && $description !== '-') || $followUp !== '' || $tpText !== '';
        if (!$hasData) {
            return '-';
        }

        $sentences = [];

        if ($description !== '' && $description !== '-') {
            if (stripos($description, 'kekuatan:') === 0) {
                $strength = ltrim(substr($description, strlen('kekuatan:')));
                $sentences[] = 'Dalam ' . $subjectPhrase . ', Ananda menunjukkan kekuatan pada ' . $strength;
            } else {
                $sentences[] = 'Dalam ' . $subjectPhrase . ', ' . ltrim($description);
            }
        }

        $levelTextMap = [
            'BB' => 'capaian kompetensi masih belum berkembang optimal',
            'MB' => 'capaian kompetensi mulai berkembang',
            'BSH' => 'capaian kompetensi sudah berkembang sesuai harapan',
            'SB' => 'capaian kompetensi sangat berkembang dan konsisten',
        ];
        if ($capaianLabel !== '' || $capaianCode !== '') {
            $levelPhrase = 'capaian kompetensi berada di level ' . ($capaianLabel !== '' ? $capaianLabel : $capaianCode);
            if ($capaianLabel !== '' && $capaianCode !== '') {
                $levelPhrase .= ' (' . $capaianCode . ')';
            }
            if (isset($levelTextMap[$capaianCode]) && $levelTextMap[$capaianCode] !== '') {
                $levelPhrase .= ' dan ' . $levelTextMap[$capaianCode];
            }
            $sentences[] = ($description !== '' && $description !== '-' ? 'Secara capaian, ' : 'Pada ' . $subjectPhrase . ', ') . $levelPhrase;
        }

        if ($followUp !== '') {
            $followUpClean = preg_replace('/^(fokus\\s+(penguatan|tindak\\s*lanjut)\\s*:)\s*/i', '', $followUp) ?? $followUp;
            $sentences[] = 'Fokus tindak lanjut ke depan adalah ' . ($followUpClean !== '' ? $followUpClean : $followUp);
        }

        if ($tpText !== '') {
            $sentences[] = 'Tujuan pembelajaran yang diacu: ' . $tpText;
        }

        $sanitizedSentences = array_map(static function (string $sentence): string {
            return rtrim(trim($sentence), " .");
        }, $sentences);
        $combined = $formatKurmerNarrative(implode('. ', array_filter($sanitizedSentences, static function (string $sentence): bool {
            return $sentence !== '';
        })));
        return $combined !== '' ? $combined : '-';
    };
    $sectionTitleAttitude = $kurmerMode ? null : 'A. Sikap';
    $sectionTitleAcademic = $kurmerMode ? 'A. Capaian Pembelajaran (Kurikulum Merdeka)' : 'B. Pengetahuan dan Keterampilan';
    $sectionTitleCocurricular = $kurmerMode ? 'B. Kokurikuler' : null;
    $sectionTitleExtracurricular = $kurmerMode ? 'C. Ekstrakurikuler' : 'C. Ekstrakurikuler';
    $sectionTitlePrakerin = $kurmerMode ? 'D. Prakerin' : 'D. Prakerin';

    $classLevel = isset($class['tingkat']) ? (int) $class['tingkat'] : 0;
    $classPhase = $classLevel === 10 ? 'E' : (in_array($classLevel, [11, 12], true) ? 'F' : '-');

    $studentInfoColumns = [
        [
            'Nama Murid' => $student['nama'] ?? '-',
            'NIS/NISN' => ($student['nipd'] ?? '-') . ' / ' . ($student['nisn'] ?? '-'),
            'Sekolah' => $school['nama'] ?? '-',
            'Alamat' => $school['alamat'] ?? '-',
        ],
        [
            'Kelas' => $class['nama'] ?? '-',
            'Fase' => $classPhase,
            'Semester' => $semesterLabel,
            'Tahun Ajaran' => $schoolYear['nama'] ?? '-',
        ],
    ];

    $waliNama = $class['wali_kelas_nama'] ?? '________________';
    $digitalSignature = $report['digitalSignature'] ?? null;
    $digitalSignatureEnabled = is_array($digitalSignature) && ($digitalSignature['enabled'] ?? false);
    $digitalSignatureStatus = $digitalSignature['status'] ?? 'inactive';
    $digitalSignatureMessage = $digitalSignature['message'] ?? 'TTD digital belum disetujui oleh kepala sekolah.';
    if ($digitalSignatureMessage === '') {
        $digitalSignatureMessage = 'TTD digital belum disetujui oleh kepala sekolah.';
    }
    $digitalSignatureVerificationUrl = $digitalSignature['verificationUrl'] ?? null;
    $digitalSignatureToken = $digitalSignature['signatureToken'] ?? null;
    $digitalSignatureApprovedAt = $digitalSignature['approvedAtLabel'] ?? ($digitalSignature['approvedAt'] ?? '');
    $headmasterName = trim((string) ($digitalSignature['headmasterName'] ?? '')) !== '' ? (string) $digitalSignature['headmasterName'] : ($school['kepala_sekolah'] ?? '________________');
    static $gradeStylesRendered = false;
?>
<?php if (!$gradeStylesRendered): ?>
<style>
    .grade-header-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11pt;
        margin-bottom: 6mm;
        border: none;
    }

    .grade-header-table td {
        border: none;
        padding: 2px 0;
    }

    .grade-header-table td:first-child {
        width: 21mm;
    }

    .grade-header-table td:nth-child(2) {
        width: 3mm;
        text-align: center;
        padding-left: 0;
        padding-right: 2mm;
    }

    .grade-header-table td.value-cell {
        width: auto;
        white-space: normal;
    }

    .grade-header-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6mm;
        border: none;
    }

    .grade-header-grid td {
        vertical-align: top;
        width: 50%;
        padding-right: 6mm;
        border: none;
    }

    .grade-header-grid td:last-child {
        padding-right: 0;
    }

    .grade-section-title {
        font-weight: 600;
        margin: 3mm 0 1.5mm;
        font-size: 11pt;
    }

    .attitude-box {
        border: 1px solid #0f172a;
        padding: 4mm;
        min-height: 22mm;
    }

    .grade-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10pt;
        table-layout: fixed;
    }

    .grade-table th,
    .grade-table td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
        word-break: break-word;
    }

    .grade-table .col-number {
        width: 8mm;
    }

    .grade-table .col-subject {
        width: 45mm;
    }

    .grade-table .col-knowledge {
        width: 12mm;
    }

    .grade-table .col-skill {
        width: 12mm;
    }

    .grade-table .col-predicate {
        width: 24mm;
    }

    .grade-table .col-description {
        width: auto;
        min-width: 65mm;
    }

    .grade-table .description-text {
        font-size: 9pt;
        line-height: 1.3;
        text-align: justify;
    }

    .kurmer-level-legend {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5pt;
        margin-top: 3mm;
    }

    .kurmer-level-legend th,
    .kurmer-level-legend td {
        border: 1px solid #0f172a;
        padding: 6px;
        text-align: center;
    }

    .kurmer-level-legend th {
        font-weight: 700;
    }

    .simple-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10pt;
    }

    .simple-table th,
    .simple-table td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
    }

    .simple-table th {
        text-align: center;
        font-weight: 600;
    }

    .grade-table th {
        text-align: center;
        font-weight: 600;
    }

    .grade-group-row td {
        font-weight: 600;
        background-color: #f1f5f9;
    }

    .note-muted {
        font-size: 9pt;
        color: #475569;
    }

    .grade-footer {
        margin-top: 10mm;
        display: flex;
        justify-content: space-between;
        gap: 8mm;
        font-size: 10.5pt;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .grade-footer .signature-block {
        flex: 1;
        text-align: center;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .grade-footer .signature-block p {
        margin: 0;
    }

    .attendance-table {
        border-collapse: collapse;
        width: 60mm;
        font-size: 10pt;
    }

    .attendance-table th,
    .attendance-table td {
        border: 1px solid #0f172a;
        padding: 3px 6px;
    }

    .digital-signature-card {
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6mm;
        padding: 6mm;
        border: 1px dashed #94a3b8;
        border-radius: 6px;
        background-color: #f8fafc;
        font-size: 10pt;
        color: #0f172a;
        max-width: 70mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .digital-signature-qr {
        width: 10mm;
        min-width: 10mm;
        height: 10mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .digital-signature-info {
        text-align: left;
        font-size: 10pt;
    }

    .digital-signature-status {
        font-weight: 600;
        font-size: 8pt;
        margin-bottom: 2mm;
    }

    .digital-signature-meta {
        margin: 1mm 0;
        font-size: 7pt;
        color: #475569;
    }

    .digital-signature-token {
        margin-top: 2mm;
        font-size: 3pt;
        font-family: 'Courier New', Courier, monospace;
        color: #1d4ed8;
        word-break: break-all;
    }

    .digital-signature-alert {
        margin: 6mm auto 0 auto;
        padding: 4mm;
        border: 1px dashed #f97316;
        border-radius: 6px;
        background-color: #fff7ed;
        color: #b45309;
        font-size: 10pt;
        max-width: 70mm;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .signature-spacer {
        height: 29mm;
    }

    @media print {
        .grade-table thead,
        .simple-table thead {
            display: table-header-group;
        }

        .grade-table tbody tr,
        .simple-table tbody tr {
            page-break-inside: avoid;
        }
    }
</style>
<?php $gradeStylesRendered = true; ?>
<?php endif; ?>
<div>
    <h2 class="text-center uppercase fw-semibold" style="margin-bottom: 6mm;">Laporan Hasil Belajar Akhir Semester</h2>
    <table class="grade-header-grid">
        <tr>
            <?php foreach ($studentInfoColumns as $column): ?>
                <td>
                    <table class="grade-header-table">
                        <?php foreach ($column as $label => $value): ?>
                            <tr>
                                <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-center">:</td>
                                <td class="value-cell"><?= nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php if (!$kurmerMode): ?>
        <div>
            <p class="grade-section-title"><?= htmlspecialchars($sectionTitleAttitude ?? 'A. Sikap', ENT_QUOTES, 'UTF-8') ?></p>
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6mm;">
                <div>
                    <p class="fw-semibold">1. Sikap Spiritual</p>
                    <div class="attitude-box">
                        <?= nl2br(htmlspecialchars($knowledgeNote !== '' ? $knowledgeNote : 'Belum diinput.', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>
                <div>
                    <p class="fw-semibold">2. Sikap Sosial</p>
                    <div class="attitude-box">
                        <?= nl2br(htmlspecialchars($socialNote !== '' ? $socialNote : 'Belum diinput.', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-6">
        <p class="grade-section-title"><?= htmlspecialchars($sectionTitleAcademic, ENT_QUOTES, 'UTF-8') ?></p>
        <table class="grade-table">
            <thead>
                <?php if ($kurmerMode): ?>
                    <tr>
                        <th class="col-number">No</th>
                        <th class="col-subject">Mata Pelajaran</th>
                        <th class="col-skill">Nilai</th>
                        <th class="col-description">Deskripsi Naratif</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th class="col-number">No</th>
                        <th class="col-subject">Mata Pelajaran</th>
                        <th class="col-knowledge">NP</th>
                        <th class="col-skill">NK</th>
                        <th class="col-predicate">Predikat (Umum)</th>
                        <th class="col-description">Deskripsi (Umum)</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="<?= $kurmerMode ? '4' : '6' ?>" class="text-center">Belum ada data nilai yang dapat ditampilkan.</td>
                    </tr>
                <?php else: ?>
                    <?php $number = 1; ?>
                    <?php foreach ($subjects as $group): ?>
                        <tr class="grade-group-row">
                            <td colspan="<?= $kurmerMode ? '4' : '6' ?>"><?= htmlspecialchars($group['label'] ?? $group['code'] ?? 'Kelompok', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php foreach ($group['subjects'] as $subject): ?>
                            <?php if ($kurmerMode): ?>
                                <?php
                                    $kurmerSummary = $subject['kurmer_summary'] ?? [];
                                    $capaianCode = strtoupper((string) ($kurmerSummary['capaian'] ?? ''));
                                    $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : '-';
                                    $generalDescription = trim((string) ($kurmerSummary['description'] ?? ''));
                                    $tindakLanjut = trim((string) ($kurmerSummary['tindak_lanjut'] ?? ''));
                                    $displayScore = $kurmerSummary['score'] ?? null;
                                    $combinedNarrative = $buildKurmerNarrative([
                                        'subject' => $subject['subject_name'] ?? '',
                                        'capaianCode' => $capaianCode,
                                        'capaianLabel' => $capaianLabel,
                                        'description' => $generalDescription,
                                        'tindakLanjut' => $tindakLanjut,
                                        'tpSources' => $kurmerSummary['tp_sources'] ?? [],
                                    ]);
                                ?>
                                <tr>
                                    <td class="text-center col-number"><?= htmlspecialchars((string) $number++, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="col-subject">
                                        <div><?= htmlspecialchars($subject['subject_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="text-center col-skill">
                                        <?= htmlspecialchars($displayScore !== null ? $formatScore($displayScore) : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="col-description">
                                        <div class="description-text">
                                            <?= nl2br(htmlspecialchars($combinedNarrative !== '' ? $combinedNarrative : '-', ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                    <?php
                                        $kkmNote = ($subject['kkm_enabled'] ?? false) && ($subject['kkm_value'] ?? null) !== null
                                            ? 'KKM: ' . $formatScore((float) $subject['kkm_value'])
                                            : '';
                                        $knowledge = $subject['knowledge'] ?? ['score' => null, 'predicate' => null, 'description' => null];
                                    $skill = $subject['skill'] ?? ['score' => null, 'predicate' => null, 'description' => null];
                                    $skillEnabled = $subject['skill_enabled'] ?? true;

                                    $predicateParts = [];
                                    if (!empty($knowledge['predicate'])) {
                                        $predicateParts[] = 'Pengetahuan: ' . trim((string) $knowledge['predicate']);
                                    }
                                    if ($skillEnabled && !empty($skill['predicate'])) {
                                        $predicateParts[] = 'Keterampilan: ' . trim((string) $skill['predicate']);
                                    }
                                    $generalPredicate = !empty($predicateParts) ? implode(' | ', $predicateParts) : '-';

                                    $descriptionParts = [];
                                    if (!empty($knowledge['description'])) {
                                        $descriptionParts[] = 'Pengetahuan: ' . trim((string) $knowledge['description']);
                                    }
                                    if ($skillEnabled && !empty($skill['description'])) {
                                        $descriptionParts[] = 'Keterampilan: ' . trim((string) $skill['description']);
                                    }
                                        $generalDescription = !empty($descriptionParts) ? implode("\n\n", $descriptionParts) : '-';
                                        $displayKnowledgeScore = $knowledge['score'] ?? null;
                                        $displaySkillScore = $skillEnabled ? ($skill['score'] ?? null) : null;
                                        if ($kurmerMode) {
                                            $generalDescription = $formatKurmerNarrative($generalDescription);
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center col-number"><?= htmlspecialchars((string) $number++, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="col-subject">
                                            <div><?= htmlspecialchars($subject['subject_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ($kkmNote !== ''): ?>
                                            <div class="note-muted"><?= htmlspecialchars($kkmNote, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center col-knowledge"><?= htmlspecialchars($formatScore($displayKnowledgeScore), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center col-skill">
                                        <?= htmlspecialchars($skillEnabled ? $formatScore($displaySkillScore) : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="col-predicate text-center"><?= htmlspecialchars($generalPredicate, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="col-description">
                                        <div class="description-text">
                                            <?= nl2br(htmlspecialchars($generalDescription, ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($kurmerMode): ?>
            <p class="note-muted" style="margin-top: 2mm;">Keterangan: Narasi mencakup capaian (BB/MB/BSH/SB) dan tindak lanjut; nilai angka bersifat opsional.</p>
            <div style="page-break-inside: avoid; break-inside: avoid;">
                <p class="fw-semibold" style="margin:3mm 0 2mm 0;">Keterangan Skala Capaian</p>
                <table class="kurmer-level-legend">
                    <thead>
                        <tr>
                            <?php foreach (['BB', 'MB', 'BSH', 'SB'] as $code): ?>
                                <th><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach (['BB', 'MB', 'BSH', 'SB'] as $code): ?>
                                <?php $detail = $kurmerLevelDetails[$code] ?? ['label' => $code, 'detail' => '-']; ?>
                                <td>
                                    <div style="font-weight:600; margin-bottom:2mm;"><?= htmlspecialchars($detail['label'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size:9pt;"><?= nl2br(htmlspecialchars($detail['detail'], ENT_QUOTES, 'UTF-8')) ?></div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="note-muted" style="margin-top: 2mm;">Keterangan: NP = Nilai Pengetahuan, NK = Nilai Keterampilan.</p>
        <?php endif; ?>
    </div>

    <?php if ($kurmerMode): ?>
        <div class="mt-6">
            <p class="grade-section-title"><?= htmlspecialchars($sectionTitleCocurricular, ENT_QUOTES, 'UTF-8') ?></p>
            <table class="simple-table">
                <thead>
                    <tr>
                        <th style="width: 10mm;">No</th>
                        <th style="width: 40mm;">Kegiatan</th>
                        <th style="width: 35mm;">Tema</th>
                        <th>Deskripsi Capaian</th>
                        <th style="width: 35mm;">Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cocurriculars)): ?>
                        <tr>
                            <td class="text-center">-</td>
                            <td colspan="4">Belum ada ringkasan kokurikuler.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cocurriculars as $index => $activity): ?>
                            <?php
                                $activityName = trim((string) ($activity['name'] ?? 'Kegiatan'));
                                $theme = trim((string) ($activity['theme'] ?? ''));
                                $summaryText = trim((string) ($activity['summary'] ?? ''));
                                $generalDescription = trim((string) ($activity['description'] ?? ''));
                                $descriptionText = $summaryText !== '' ? $summaryText : ($generalDescription !== '' ? $generalDescription : '-');
                                $followUp = trim((string) ($activity['follow_up'] ?? ''));
                            ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($theme !== '' ? $theme : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= nl2br(htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8')) ?></td>
                                <td><?= nl2br(htmlspecialchars($followUp !== '' ? $followUp : '-', ENT_QUOTES, 'UTF-8')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-6">
        <p class="grade-section-title"><?= htmlspecialchars($sectionTitleExtracurricular, ENT_QUOTES, 'UTF-8') ?></p>
        <table class="simple-table">
            <thead>
                <tr>
                    <th style="width: 12mm;">No</th>
                    <th>Kegiatan</th>
                    <th style="width: 20mm;">Nilai Akhir</th>
                    <th style="width: 25mm;">Predikat</th>
                    <th>Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($extracurriculars)): ?>
                    <tr>
                        <td class="text-center">-</td>
                        <td colspan="4">Belum ada nilai ekstrakurikuler.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($extracurriculars as $index => $activity): ?>
                        <?php
                            $activityName = trim((string) ($activity['ekstrakurikuler_nama'] ?? ''));
                            $activityName = $activityName !== '' ? $activityName : 'Ekstrakurikuler';
                            $finalScore = isset($activity['nilai_akhir']) ? $formatScore((float) $activity['nilai_akhir']) : '-';
                            $predicate = trim((string) ($activity['predikat'] ?? ''));
                            $predicate = $predicate !== '' ? $predicate : '-';
                            $descriptionText = trim((string) ($activity['deskripsi'] ?? ''));
                            $descriptionText = $descriptionText !== '' ? $descriptionText : '-';
                        ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= htmlspecialchars($finalScore, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= nl2br(htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <p class="grade-section-title"><?= htmlspecialchars($sectionTitlePrakerin, ENT_QUOTES, 'UTF-8') ?></p>
        <table class="simple-table">
            <thead>
                <tr>
                    <th style="width: 12mm;">No</th>
                    <th>Tempat Prakerin</th>
                    <th style="width: 25mm;">Nilai Keaktifan</th>
                    <th style="width: 25mm;">Nilai Jurnal</th>
                    <th style="width: 25mm;">Nilai Laporan</th>
                    <th style="width: 20mm;">Nilai Akhir</th>
                    <th style="width: 25mm;">Predikat</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($prakerin === null): ?>
                    <tr>
                        <td class="text-center">-</td>
                        <td colspan="6">Belum ada data prakerin.</td>
                    </tr>
                <?php else: ?>
                    <?php
                        $placeName = trim((string) ($prakerin['place_name'] ?? ''));
                        $placeName = $placeName !== '' ? $placeName : 'Tempat Prakerin';
                        $keaktifan = isset($prakerin['nilai_keaktifan']) ? $formatScore((float) $prakerin['nilai_keaktifan']) : '-';
                        $jurnal = isset($prakerin['nilai_jurnal']) ? $formatScore((float) $prakerin['nilai_jurnal']) : '-';
                        $laporan = isset($prakerin['nilai_laporan']) ? $formatScore((float) $prakerin['nilai_laporan']) : '-';
                        $final = isset($prakerin['nilai_akhir']) ? $formatScore((float) $prakerin['nilai_akhir']) : '-';
                        $predicate = trim((string) ($prakerin['predikat'] ?? ''));
                        $predicate = $predicate !== '' ? $predicate : '-';
                    ?>
                    <tr>
                        <td class="text-center">1</td>
                        <td><?= htmlspecialchars($placeName, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= htmlspecialchars($keaktifan, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= htmlspecialchars($jurnal, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= htmlspecialchars($laporan, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= htmlspecialchars($final, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-center"><?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <p class="fw-semibold">E. Ketidakhadiran</p>
        <table class="attendance-table">
            <tr>
                <th>Keterangan</th>
                <th>Jumlah</th>
            </tr>
            <tr>
                <td>Sakit</td>
                <td class="text-center"><?= htmlspecialchars((string) ($attendance['sakit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Izin</td>
                <td class="text-center"><?= htmlspecialchars((string) ($attendance['izin'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Tanpa Keterangan</td>
                <td class="text-center">
                    <?= htmlspecialchars((string) (($attendance['bolos'] ?? 0) + ($attendance['alpa'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="grade-footer">
        <div class="signature-block">
            <p>Mengetahui,</p>
            <p>Orang Tua / Wali</p>
            <div class="signature-spacer"></div>
            <p>(................................................)</p>
        </div>
        <div class="signature-block">
            <p><?= htmlspecialchars($school['kabupaten'] ?? '-', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Wali Kelas</p>
            <div class="signature-spacer"></div>
            <p class="fw-semibold underline"><?= htmlspecialchars($waliNama, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="signature-block">
            <p>Mengetahui,</p>
            <p>Kepala Sekolah</p>
            <?php if ($digitalSignatureEnabled && $digitalSignatureStatus === 'approved' && $digitalSignatureVerificationUrl !== null): ?>
                <div class="digital-signature-card">
                    <div class="digital-signature-qr" data-qr-value="<?= htmlspecialchars($digitalSignatureVerificationUrl, ENT_QUOTES, 'UTF-8') ?>" data-qr-size="70"></div>
                    <div class="digital-signature-info">
                        <p class="digital-signature-status">TTD Digital Disetujui</p>
                        <?php if (is_string($digitalSignatureApprovedAt) && $digitalSignatureApprovedAt !== ''): ?>
                            <p class="digital-signature-meta">Disetujui <?= htmlspecialchars($digitalSignatureApprovedAt, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p class="digital-signature-meta">Disahkan oleh <?= htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (is_string($digitalSignatureToken) && $digitalSignatureToken !== ''): ?>
                            <p class="digital-signature-token">Kode: <?= htmlspecialchars($digitalSignatureToken, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="digital-signature-alert">
                    <?= htmlspecialchars($digitalSignatureMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="signature-spacer"></div>
            <?php endif; ?>
            <p class="fw-semibold"><?= htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
</div>
