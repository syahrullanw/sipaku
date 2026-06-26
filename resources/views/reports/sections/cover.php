<?php
    $school = $report['school'] ?? [];
    $student = $report['student'] ?? [];
    $schoolYear = $report['schoolYear'] ?? null;
    $schoolYearName = $schoolYear['nama'] ?? 'Tahun Pelajaran';
    $studentName = $student['nama'] ?? '-';
    $studentNipd = $student['nipd'] ?? '-';
    $studentNisn = $student['nisn'] ?? '-';
    $kabupaten = $school['kabupaten'] ?? '';
    $province = $school['provinsi'] ?? '';
    $footerLocation = trim($kabupaten) !== '' ? $kabupaten : '________________';
    $lambangNegaraPath = !empty($school['lambang_negara']) ? asset($school['lambang_negara']) : null;
    $logoSekolahPath = !empty($school['logo_sekolah']) ? asset($school['logo_sekolah']) : null;
    $paperSize = strtolower((string) ($paperSize ?? 'f4'));
    if (!in_array($paperSize, ['f4', 'a4'], true)) {
        $paperSize = 'f4';
    }

    $containerPaddingVertical = $paperSize === 'a4' ? 15 : 12;
    $coverPaddingVertical = $paperSize === 'a4' ? 24 : 28;
    $coverPaddingHorizontal = $paperSize === 'a4' ? 22 : 24;
    $coverGap = $paperSize === 'a4' ? 12 : 14;
    $coverMinHeight = sprintf('calc(100vh - %dmm)', $containerPaddingVertical * 2);
    $coverPadding = sprintf('%smm %smm', $coverPaddingVertical, $coverPaddingHorizontal);
?>
<style>
    /* ==== PENGATURAN CETAK & HALAMAN ==== */
    @page {
        size: <?= htmlspecialchars(strtoupper($paperSize), ENT_QUOTES, 'UTF-8') ?>;
        margin: 20mm;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: "Times New Roman", serif;
        background: #fff;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* ==== FRAME COVER ==== */
    .cover-frame {
        border: 3px solid #0f172a;
        min-height: <?= htmlspecialchars($coverMinHeight, ENT_QUOTES, 'UTF-8') ?>;
        max-height: 330mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-evenly;
        text-align: center;
        padding: <?= htmlspecialchars($coverPadding, ENT_QUOTES, 'UTF-8') ?>;
        box-sizing: border-box;
        gap: <?= htmlspecialchars($coverGap . 'mm', ENT_QUOTES, 'UTF-8') ?>;
        page-break-after: always;
    }

    /* ==== EMBLEM & LOGO ==== */
    .cover-emblem,
    .cover-logo {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cover-emblem {
        width: 45mm;
        height: 45mm;
    }

    .cover-logo {
        width: 40mm;
        height: 40mm;
    }

    .cover-emblem img,
    .cover-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: block;
        page-break-inside: avoid;
    }

    /* ==== JUDUL COVER ==== */
    .cover-title {
        line-height: 1.6;
        page-break-inside: avoid;
    }

    .cover-title h1 {
        margin: 0;
        font-size: 16pt;
        letter-spacing: 1px;
    }

    .cover-title p {
        margin: 2mm 0 0;
        font-size: 12pt;
    }

    .uppercase {
        text-transform: uppercase;
    }

    .fw-semibold {
        font-weight: 600;
    }

    /* ==== DATA PESERTA ==== */
    .cover-student {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 4mm;
        page-break-inside: avoid;
    }

    .cover-student .label {
        font-size: 11pt;
    }

    .cover-student .value-box {
        border: 2px solid #0f172a;
        padding: 5mm 7mm;
        font-size: 12pt;
        font-weight: 600;
    }

    /* ==== FOOTER ==== */
    .cover-footer {
        line-height: 1.6;
        font-size: 11pt;
        page-break-inside: avoid;
    }

    /* ==== MODE CETAK ==== */
    @media print {
        html, body {
            width: <?= $paperSize === 'a4' ? '210mm' : '216mm' ?>;
            height: <?= $paperSize === 'a4' ? '297mm' : '330mm' ?>;
        }
        .cover-frame {
            border: 3px solid #0f172a;
            page-break-after: always;
        }
    }
</style>

<div class="cover-frame">
    <!-- LAMBANG NEGARA -->
    <div class="cover-emblem">
        <?php if ($lambangNegaraPath !== null): ?>
            <img src="<?= htmlspecialchars($lambangNegaraPath, ENT_QUOTES, 'UTF-8') ?>" alt="Lambang Negara" />
        <?php else: ?>
            <span>LAMBANG<br>NEGARA</span>
        <?php endif; ?>
    </div>

    <!-- JUDUL -->
    <div class="cover-title">
        <p class="fw-semibold uppercase">Laporan</p>
        <h1 class="fw-semibold uppercase">Hasil Pencapaian Kompetensi Peserta Didik</h1>
        <p class="fw-semibold uppercase"><?= htmlspecialchars((string) $school['nama'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= htmlspecialchars($schoolYearName, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- LOGO SEKOLAH -->
    <div class="cover-logo">
        <?php if ($logoSekolahPath !== null): ?>
            <img src="<?= htmlspecialchars($logoSekolahPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah" />
        <?php else: ?>
            <span>LOGO<br>SEKOLAH</span>
        <?php endif; ?>
    </div>

    <!-- DATA SISWA -->
    <div class="cover-student">
        <div class="label">Nama Peserta Didik</div>
        <div class="value-box"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="label">NIPD / NISN</div>
        <div class="value-box"><?= htmlspecialchars($studentNipd . ' / ' . $studentNisn, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- FOOTER -->
    <div class="cover-footer">
        <p>Kementerian Pendidikan dan Kebudayaan Republik Indonesia</p>
        <p>
            <?= htmlspecialchars($kabupaten !== '' ? strtoupper($kabupaten) : '________________', ENT_QUOTES, 'UTF-8') ?>
            <?= $province !== '' ? ', ' . htmlspecialchars(strtoupper($province), ENT_QUOTES, 'UTF-8') : '' ?>
        </p>
    </div>
</div>
