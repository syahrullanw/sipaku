<?php
    $school = $report['school'] ?? [];
    $student = $report['student'] ?? [];
    $class = $report['class'] ?? [];
    $schoolYear = $report['schoolYear'] ?? null;
    $subjects = $report['subjects'] ?? [];
    $attendance = $report['attendance'] ?? ['sakit' => 0, 'izin' => 0, 'bolos' => 0, 'alpa' => 0];
    $semesterLabel = $report['semesterLabel'] ?? 'Semester 1 (Ganjil)';
    $printedDateLabel = $report['printedDateLabel'] ?? '';
    $cityLabel = $school['kabupaten'] ?? '-';
    $digitalSignature = $report['digitalSignature'] ?? null;
    $digitalSignatureEnabled = is_array($digitalSignature) && ($digitalSignature['enabled'] ?? false);
    $digitalSignatureStatus = $digitalSignature['status'] ?? 'inactive';
    $digitalSignatureMessage = $digitalSignature['message'] ?? '';
    $digitalSignatureVerificationUrl = $digitalSignature['verificationUrl'] ?? null;
    $digitalSignatureToken = $digitalSignature['signatureToken'] ?? null;
    $digitalSignatureHeadmaster = trim((string) ($digitalSignature['headmasterName'] ?? '')) !== '' ? (string) $digitalSignature['headmasterName'] : ($school['kepala_sekolah'] ?? '________________');
    $digitalSignatureApprovedAt = $digitalSignature['approvedAtLabel'] ?? ($digitalSignature['approvedAt'] ?? '');
    if ($digitalSignatureMessage === '') {
        $digitalSignatureMessage = 'TTD digital belum disetujui oleh kepala sekolah.';
    }
    $waliNama = $class['wali_kelas_nama'] ?? '________________';

    $formatScore = static function (?float $value): string {
        if ($value === null) {
            return '-';
        }

        if (abs($value - round($value)) < 0.001) {
            return number_format((int) round($value), 0, ',', '.');
        }

        return number_format($value, 1, ',', '.');
    };

    $schoolYearName = is_array($schoolYear ?? null) ? ($schoolYear['nama'] ?? '-') : '-';

    $infoRows = [
        'Nama Sekolah' => $school['nama'] ?? '-',
        'Alamat Sekolah' => $school['alamat'] ?? '-',
        'Nama Siswa' => $student['nama'] ?? '-',
        'NIPD / NISN' => ($student['nipd'] ?? '-') . ' / ' . ($student['nisn'] ?? '-'),
        'Kelas' => ($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-'),
        'Semester' => $semesterLabel,
        'Tahun Pelajaran' => $schoolYearName,
    ];

    $attendanceSick = (int) ($attendance['sakit'] ?? 0);
    $attendancePermit = (int) ($attendance['izin'] ?? 0);
    $attendanceUnexcused = (int) ($attendance['bolos'] ?? 0) + (int) ($attendance['alpa'] ?? 0);
?>
<style>
    .midterm-title {
        text-align: center;
        text-transform: uppercase;
        font-weight: 600;
        font-size: 16pt;
        margin-bottom: 10mm;
    }

    .midterm-info-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11pt;
        margin-bottom: 8mm;
    }

    .midterm-info-table td {
        border: none;
        padding: 2px 0;
    }

    .midterm-info-table td:first-child {
        width: 38mm;
    }

    .midterm-score-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5pt;
    }

    .midterm-score-table th,
    .midterm-score-table td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
    }

    .midterm-score-table th {
        text-align: center;
        font-weight: 600;
    }

    .midterm-score-group td {
        font-weight: 600;
        background-color: #f1f5f9;
    }

    .midterm-attendance {
        margin-top: 12mm;
        width: 60mm;
    }

    .midterm-attendance table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5pt;
    }

    .midterm-attendance th,
    .midterm-attendance td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
        text-align: center;
    }

    .midterm-attendance th:first-child,
    .midterm-attendance td:first-child {
        text-align: left;
    }

    @media print {
        .midterm-score-table thead {
            display: table-header-group;
        }

        .midterm-score-table tbody tr {
            page-break-inside: avoid;
        }
    }
</style>

<style>
    .midterm-signature {
        margin-top: 10mm;
        display: flex;
        justify-content: space-between;
        gap: 7mm;
        width: 100%;
        font-size: 10.5pt;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .midterm-signature .signature-block {
        flex: 1;
        text-align: center;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .signature-spacer {
        height: 30mm;
    }

    .digital-signature-card {
        margin: 3mm auto 5mm auto;
        display: flex;
        align-items: center;
        gap: 6mm;
        padding: 5mm;
        border: 1px dashed #94a3b8;
        border-radius: 6px;
        background-color: #f8fafc;
        max-width: 100mm;
        color: #0f172a;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .digital-signature-qr {
        width: 13mm;
        min-width: 15mm;
        height: 13mm;
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
        font-size: 7pt;
        margin-bottom: 2mm;
    }

    .digital-signature-meta {
        margin: 1mm 0;
        font-size: 6pt;
        color: #475569;
    }

    .digital-signature-token {
        margin-top: 1mm;
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
</style>

<div>
    <h1 class="midterm-title">Laporan Hasil Belajar Tengah Semester</h1>

    <table class="midterm-info-table">
        <?php foreach ($infoRows as $label => $value): ?>
            <tr>
                <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="width: 6mm; text-align: center;">:</td>
                <td><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <table class="midterm-score-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10mm;">No</th>
                <th rowspan="2">Mata Pelajaran</th>
                <th colspan="2">Pengetahuan</th>
                <th colspan="2">Keterampilan</th>
            </tr>
            <tr>
                <th style="width: 18mm;">Angka</th>
                <th style="width: 18mm;">Predikat</th>
                <th style="width: 18mm;">Angka</th>
                <th style="width: 18mm;">Predikat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Belum ada data nilai yang dapat ditampilkan.</td>
                </tr>
            <?php else: ?>
                <?php $number = 1; ?>
                <?php foreach ($subjects as $group): ?>
                    <tr class="midterm-score-group">
                        <td colspan="6"><?= htmlspecialchars($group['label'] ?? $group['code'] ?? 'Kelompok Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php foreach ($group['subjects'] as $subject): ?>
                        <?php
                            $knowledge = $subject['knowledge'] ?? ['score' => null, 'predicate' => null];
                            $skill = $subject['skill'] ?? ['score' => null, 'predicate' => null];
                            $skillEnabled = $subject['skill_enabled'] ?? true;
                            $knowledgeScore = $knowledge['score'] !== null ? $formatScore((float) $knowledge['score']) : '-';
                            $knowledgePredicate = $knowledge['predicate'] ?? '-';
                            $skillScore = ($skill['score'] !== null && $skillEnabled) ? $formatScore((float) $skill['score']) : '-';
                            $skillPredicate = ($skill['predicate'] ?? null) !== null && $skillEnabled ? $skill['predicate'] : '-';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= htmlspecialchars((string) $number++, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($subject['subject_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($knowledgeScore, ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($knowledgePredicate !== '' ? $knowledgePredicate : '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($skillScore, ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($skillPredicate !== '' ? $skillPredicate : '-', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="midterm-attendance">
        <p style="font-weight: 600; margin-top: 10mm; margin-bottom: 4mm;">Ketidakhadiran</p>
        <table>
            <tr>
                <th style="width: 34mm;">Keterangan</th>
                <th>Jumlah</th>
            </tr>
            <tr>
                <td>Sakit</td>
                <td><?= htmlspecialchars((string) $attendanceSick, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Izin</td>
                <td><?= htmlspecialchars((string) $attendancePermit, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>Tanpa Keterangan</td>
                <td><?= htmlspecialchars((string) $attendanceUnexcused, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>
    </div>

    <div class="midterm-signature">
        <div class="signature-block">
            <p><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Wali Kelas</p>
            <div class="signature-spacer"></div>
            <p class="fw-semibold underline"><?= htmlspecialchars($waliNama, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="signature-block">
            <p><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Kepala Sekolah</p>
            <?php if ($digitalSignatureEnabled && $digitalSignatureStatus === 'approved' && $digitalSignatureVerificationUrl !== null): ?>
                <div class="digital-signature-card">
                    <div class="digital-signature-qr" data-qr-value="<?= htmlspecialchars($digitalSignatureVerificationUrl, ENT_QUOTES, 'UTF-8') ?>" data-qr-size="80"></div>
                    <div class="digital-signature-info">
                        <p class="digital-signature-status">TTD Digital Disetujui</p>
                        <?php if (is_string($digitalSignatureApprovedAt) && $digitalSignatureApprovedAt !== ''): ?>
                            <p class="digital-signature-meta">Disetujui <?= htmlspecialchars($digitalSignatureApprovedAt, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p class="digital-signature-meta">Disahkan oleh <?= htmlspecialchars($digitalSignatureHeadmaster, ENT_QUOTES, 'UTF-8') ?></p>
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
            <p class="fw-semibold"><?= htmlspecialchars($digitalSignatureHeadmaster, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
</div>
