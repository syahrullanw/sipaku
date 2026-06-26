<?php
$groups = array_values(array_filter($groups ?? [], static function ($group) {
    return ! empty($group['cards'] ?? []);
}));
$printHeading = trim((string) ($printHeading ?? 'Kartu Pelajar'));
$printSubheading = trim((string) ($printSubheading ?? ''));
$school = $school ?? [];
$logoPath = $school['logo_sekolah'] ?? null;
$logoUrl = $logoPath ? asset($logoPath) : null;
$schoolName = trim((string) ($school['nama'] ?? 'Sekolah Menengah Kejuruan'));
$schoolAddressParts = array_filter([
    $school['alamat'] ?? null,
    $school['desa'] ?? null,
    $school['kecamatan'] ?? null,
    $school['kabupaten'] ?? null,
    $school['provinsi'] ?? null,
], static fn ($value) => is_string($value) && trim($value) !== '');
$schoolAddress = implode(', ', array_map(static fn ($value) => trim((string) $value), $schoolAddressParts));
$schoolEmail = trim((string) ($school['email'] ?? ''));
$schoolWebsite = trim((string) ($school['website'] ?? ''));
$schoolWebsite = preg_replace('#^https?://#i', '', $schoolWebsite) ?? $schoolWebsite;
$schoolContactParts = array_values(array_filter([
    $schoolEmail,
    $schoolWebsite,
], static fn ($value) => is_string($value) && trim($value) !== ''));
$schoolContactLine = implode('  |  ', $schoolContactParts);
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);
    return sprintf('%d %s %s', (int) date('j', $timestamp), $month, date('Y', $timestamp));
};
?>
<style>
/* MEMAKSA WARNA BACKGROUND TERCETAK */
*, *::before, *::after {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact !important; 
    print-color-adjust: exact !important;
    color-adjust: exact !important;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #0b1423;
}

.print-header {
    text-align: center;
    margin-bottom: 5mm;
}

.print-header h1 {
    margin: 0;
    font-size: 16pt;
    font-weight: 700;
    text-transform: uppercase;
}

.print-header p {
    margin: 4px 0 0;
    font-size: 10pt;
    color: #475569;
}

.group-heading {
    margin: 0 0 4mm;
    font-size: 11pt;
    font-weight: 600;
    text-transform: uppercase;
    color: #1d4ed8;
    border-bottom: 1px dashed #93c5fd;
    padding-bottom: 2mm;
}

.cards-grid {
    margin: 0 -2mm 8mm;
}

.cards-grid::after {
    content: ''; display: block; clear: both;
}

.card-wrapper {
    float: left;
    width: 50%;
    padding: 0 2mm 6mm;
    page-break-inside: avoid;
}

/* =========================================
   KARTU PELAJAR UTAMA
   ========================================= */
.student-card {
    width: 86mm;
    height: 54mm;
    margin: 0 auto;
    border-radius: 4mm;
    background: #ffffff;
    background: linear-gradient(180deg, #f4f8fb 0%, #ffffff 70%) !important;
    border: 1px solid #cbd5e1;
    overflow: hidden;
    position: relative;
}

/* HEADER KARTU */
.card-top {
    background-color: #4f8ba0 !important; /* !important ditambahkan agar tidak di-override saat print */
    color: #ffffff !important;
    padding: 2.25mm 3.5mm;
    height: 14.5mm;
}

.card-top::after { content: ''; display: block; clear: both; }

.logo-badge {
    float: left;
    width: 10mm;
    height: 10mm;
    border-radius: 2mm;
    background-color: #ffffff !important;
    padding: 1mm;
}

.logo-badge img {
    width: 100%; height: 100%; object-fit: contain;
}

.school-meta {
    float: left;
    width: 65mm;
    margin-left: 2.5mm;
    margin-top: 1.2mm;
}

.school-meta h2 {
    margin: 0; font-size: 7.5pt; font-weight: 700;
    text-transform: uppercase; line-height: 1.1;
}

.school-meta p {
    margin: 1.5px 0 0; font-size: 5pt; line-height: 1.1; opacity: 0.95;
}

.school-contact {
    margin-top: 1px;
    font-size: 4.2pt;
    line-height: 1.05;
    opacity: 0.95;
    white-space: nowrap;
    overflow: hidden;
}

/* JUDUL KARTU */
.card-title {
    text-align: center;
    font-size: 6.8pt;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #4f8ba0 !important;
    margin: 1.5mm 0 1mm;
}

.card-title::after {
    content: ''; display: block;
    width: 30mm; height: 1px;
    background-color: #4f8ba0 !important;
    margin: 1px auto 0; opacity: 0.4;
}

/* BODY KARTU */
.card-main {
    padding: 0 3.5mm;
    height: 34mm;
}

.card-main::after { content: ''; display: block; clear: both; }

/* PANEL FOTO */
.photo-panel {
    float: left;
    width: 21mm;
}

.photo-frame {
    width: 19.5mm;
    height: 26mm;
    border-radius: 2mm;
    border: 1px solid #4f8ba0 !important;
    background-color: #ffffff !important;
    overflow: hidden;
}

.photo-frame img {
    width: 100%; height: 100%; object-fit: cover;
}

.photo-frame .placeholder {
    font-size: 4.5pt; font-weight: 600; color: #94a3b8;
    text-align: center; padding: 8mm 1mm 0; line-height: 1.2;
}

/* PANEL DATA TULISAN */
.info-panel {
    float: right;
    width: 56mm;
    background-color: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 2mm;
    padding: 1.5mm 2mm;
    height: 31mm;
}

table {
    width: 100%;
    border-collapse: collapse;
    border: none !important;
}

table tr, table td, table th {
    border: none !important;
}

.biodata-table td {
    padding: 0.6mm 0;
    vertical-align: top;
    font-size: 5.5pt;
    font-weight: 700;
    line-height: 1.15;
    color: #0b1423 !important;
}

.biodata-table .label-td {
    width: 14mm;
    color: #4f6a80 !important;
    text-transform: uppercase;
}

.biodata-table .sep-td {
    width: 2mm;
    text-align: center;
    color: #4f6a80 !important;
}

.val-address {
    max-height: 6mm;
    overflow: hidden;
    display: block;
}

/* BAGIAN KELAS & QR CODE */
.bottom-section {
    margin-top: 1mm;
    padding-top: 1mm;
    border-top: 1px dashed rgba(79, 140, 159, 0.4) !important;
}

.bottom-table td {
    padding-top: 0.5mm;
    vertical-align: top;
}

.meta-td {
    width: 38mm;
}

.meta-item {
    font-size: 5.5pt;
    line-height: 1.2;
    margin-bottom: 0.5mm;
}

.m-label {
    display: inline-block;
    width: 9.5mm;
    font-weight: 700;
    color: #4f6a80 !important;
    text-transform: uppercase;
}

.m-val {
    font-weight: 700;
    color: #0b1423 !important;
}

/* KOTAK QR CODE */
.qr-td {
    width: 14mm;
    text-align: right;
}

.qr-box {
    width: 11mm;
    height: 11mm;
    border-radius: 1.5mm;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    margin-left: auto;
    overflow: hidden;
    padding: 0;
}

.qr-box canvas, .qr-box img {
    width: 100% !important; height: 100% !important; display: block;
}

.qr-caption {
    font-size: 4.5pt;
    font-weight: 700;
    color: #4f8ba0 !important;
    text-transform: uppercase;
    text-align: center;
    width: 11mm;
    float: right;
    margin-top: 1px;
}

/* Pengaturan khusus media cetak tambahan jika browser masih bandel */
@media print {
    body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<div class="print-header">
    <h1><?= htmlspecialchars($printHeading !== '' ? $printHeading : 'Kartu Pelajar', ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($printSubheading !== ''): ?>
        <p><?= htmlspecialchars($printSubheading, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>

<?php if (empty($groups)): ?>
    <p style="text-align:center;font-size:12pt;color:#ef4444;">Tidak ada data kartu pelajar yang dapat dicetak.</p>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <?php
        $label = trim((string) ($group['label'] ?? ''));
        $cards = $group['cards'] ?? [];
        if (empty($cards)) continue;
        ?>
        <?php if ($label !== ''): ?>
            <h2 class="group-heading"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <?php endif; ?>

        <div class="cards-grid">
            <?php foreach ($cards as $card): ?>
                <?php
                $student = $card['student'] ?? [];
                $validationUrl = trim((string) ($card['validationUrl'] ?? ''));
                $qrValue = trim((string) ($card['qrValue'] ?? $validationUrl));
                $photoPath = $student['foto_path'] ?? null;
                $photoUrl = $photoPath ? asset($photoPath) : null;
                ?>
                <div class="card-wrapper">
                    <article class="student-card">
                        <div class="card-top">
                            <div class="logo-badge">
                                <?php if ($logoUrl !== null): ?>
                                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" />
                                <?php endif; ?>
                            </div>
                            <div class="school-meta">
                                <h2><?= htmlspecialchars($schoolName !== '' ? $schoolName : 'Sekolah Menengah Kejuruan', ENT_QUOTES, 'UTF-8') ?></h2>
                                <?php if ($schoolAddress !== ''): ?>
                                    <p><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($schoolContactLine !== ''): ?>
                                    <div class="school-contact"><?= htmlspecialchars($schoolContactLine, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-title">KARTU PELAJAR</div>

                        <div class="card-main">
                            <div class="photo-panel">
                                <div class="photo-frame">
                                    <?php if ($photoUrl !== null): ?>
                                        <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto" />
                                    <?php else: ?>
                                        <div class="placeholder">FOTO BELUM TERSEDIA</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-panel">
                                <table class="biodata-table">
                                    <tr>
                                        <td class="label-td">NAMA</td>
                                        <td class="sep-td">:</td>
                                        <td>
                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">TGL LAHIR</td>
                                        <td class="sep-td">:</td>
                                        <td><?= htmlspecialchars($formatDate($student['tanggal_lahir'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">ALAMAT</td>
                                        <td class="sep-td">:</td>
                                        <td><span class="val-address"><?= htmlspecialchars($student['alamat'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    </tr>
                                </table>

                                <div class="bottom-section">
                                    <table class="bottom-table">
                                        <tr>
                                            <td class="meta-td">
                                                <div class="meta-item">
                                                    <span class="m-label">KELAS</span>
                                                    <span class="m-val">: <?= htmlspecialchars(trim((string) ($student['kelas_nama'] ?? '')) ?: '-', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <div class="meta-item">
                                                    <span class="m-label">NIPD</span>
                                                    <span class="m-val">: <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </td>
                                            <td class="qr-td">
                                                <div class="qr-box" data-qr-value="<?= htmlspecialchars($qrValue, ENT_QUOTES, 'UTF-8') ?>"></div>
                                                <div class="qr-caption">VALIDASI</div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
