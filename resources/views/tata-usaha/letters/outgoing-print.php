<?php
$letter = is_array($letter ?? null) ? $letter : [];
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$tembusanLines = is_array($letter['tembusan_lines'] ?? null) ? $letter['tembusan_lines'] : [];
$bodyText = isset($letter['body_text']) ? (string) $letter['body_text'] : '';
$bodyHtml = isset($letter['body_html']) ? (string) $letter['body_html'] : '';
$number = trim((string) ($letter['nomor_surat'] ?? ''));
$letterType = strtoupper(trim((string) ($letter['jenis_label'] ?? 'Surat')));
$subject = trim((string) ($letter['perihal'] ?? ''));
$recipient = trim((string) ($letter['tujuan'] ?? ''));
$attachment = trim((string) ($letter['lampiran'] ?? ''));
$attachments = is_array($letter['lampiran_records'] ?? null) ? array_values($letter['lampiran_records']) : [];
$letterDateRaw = trim((string) ($letter['tanggal_surat'] ?? ''));
$indonesianMonths = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];
$letterDate = '';

if ($letterDateRaw !== '') {
    try {
        $letterDateObj = new DateTimeImmutable($letterDateRaw);
        $monthIndex = (int) $letterDateObj->format('n');
        $monthName = $indonesianMonths[$monthIndex] ?? strtolower($letterDateObj->format('F'));
        $letterDate = sprintf(
            '%s %s %s',
            $letterDateObj->format('d'),
            $monthName,
            $letterDateObj->format('Y')
        );
    } catch (\Exception) {
        $letterDate = $letterDateRaw;
    }
}

if ($letterDate === '') {
    $letterDate = trim((string) ($letter['tanggal_surat_formatted'] ?? ''));
}
$signer = trim((string) ($letter['tanda_tangan'] ?? ''));
$schoolName = trim((string) ($schoolProfile['nama'] ?? ''));
$schoolCity = '';

if ($schoolProfile !== null) {
    $cityCandidates = [
        $schoolProfile['kota'] ?? null,
        $schoolProfile['kabupaten'] ?? null,
        $schoolProfile['kota_kabupaten'] ?? null,
    ];

    foreach ($cityCandidates as $candidate) {
        $candidate = is_string($candidate) ? trim($candidate) : '';

        if ($candidate !== '') {
            $schoolCity = $candidate;
            break;
        }
    }
}
$signatureTitle = $signer !== '' ? $signer : 'Tata Usaha';
$letterheadPath = $schoolProfile['kop_surat'] ?? '';
$letterheadUrl = $letterheadPath !== '' ? asset($letterheadPath) : null;
$signature = is_array($letter['digital_signature'] ?? null) ? $letter['digital_signature'] : null;
$signatureQrValue = '';
$signatureStatusLabel = 'Status TTD tidak diketahui';
$signatureStatus = null;
$signatureApprovedAt = null;
$signatureApproverName = null;
$signatureApproverRole = null;
$signatureToken = null;

if ($signature !== null) {
    $signatureStatusLabel = $signature['status_label'] ?? $signatureStatusLabel;
    $signatureStatus = $signature['status'] ?? null;
    $signatureApprovedAt = $signature['approved_at_formatted'] ?? null;
    $signatureApproverName = $signature['approver_name'] ?? null;
    $signatureApproverRole = $signature['approver_role'] ?? null;
    $signatureToken = $signature['token'] ?? null;
    $signatureQrValue = isset($signature['verification_url']) ? trim((string) $signature['verification_url']) : '';
}

$requiresHeadmasterDigital = (bool) ($letter['requires_headmaster_digital_signature'] ?? false);
$signatureMissing = (bool) ($letter['digital_signature_missing'] ?? false);
?>

<style>
    .outgoing-letter-page {
        max-width: 720px;
        margin: 0 auto;
        font-size: 12pt;
    }

    .outgoing-letter-page,
    .outgoing-letter-page * {
        box-sizing: border-box;
    }

    .outgoing-letter-letterhead {
        margin-bottom: 12px;
    }

    .outgoing-letter-letterhead img {
        display: block;
        width: 100%;
        height: auto;
    }

    .outgoing-letter-body {
        width: 100%;
    }

    .outgoing-letter-page.has-letterhead .outgoing-letter-body {
        padding-left: 13mm;
        padding-right: 13mm;
    }

    .letter-rich-text {
        line-height: 1.7;
        font-size: 12pt;
    }

    .letter-rich-text p {
        margin: 0 0 12px 0;
    }

    .letter-rich-text ol,
    .letter-rich-text ul {
        margin: 0 0 12px 24px;
        padding: 0 0 0 16px;
    }

    .letter-rich-text ol li,
    .letter-rich-text ul li {
        margin-bottom: 4px;
    }

    .letter-rich-text table {
        border-collapse: collapse;
        width: auto;
        max-width: 100%;
        margin: 16px auto;
    }

    .letter-rich-text table th,
    .letter-rich-text table td {
        border: 1px solid #1e293bcc;
        padding: 8px 12px;
    }

    .letter-rich-text .text-center {
        text-align: center !important;
    }

    .letter-rich-text .text-right {
        text-align: right !important;
    }

    .letter-rich-text .text-left {
        text-align: left !important;
    }

    .letter-rich-text .fw-bold {
        font-weight: 600;
    }
    .letter-rich-text .fw-bolder {
        font-weight: 700;
    }

    .letter-rich-text .fst-italic {
        font-style: italic;
    }

    .digital-signature-card {
        margin: 3mm auto 5mm auto;
        display: flex;
        align-items: flex-start;
        gap: 3mm;
        padding: 5mm;
        border: 1px dashed #94a3b8;
        border-radius: 6px;
        background-color: #f8fafc;
        max-width: 100mm;
        color: #0f172a;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .digital-signature-card__qr {
        flex: 0 0 auto;
        padding: 4px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #cbd5f5;
    }

    .digital-signature-card__body {
        font-size: 12pt;
        color: #0f172a;
        line-height: 1.5;
    }

    .digital-signature-card__title {
        font-weight: 700;
        font-size: 10pt;
        margin-bottom: 4px;
    }

    .digital-signature-card__meta {
        font-size: 9pt;
    }

    .digital-signature-card__token {
        margin-top: 6px;
        font-size: 3pt;
        color: #475569;
        font-family: "JetBrains Mono", "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
    }
</style>

<div class="outgoing-letter-page<?= $letterheadUrl !== null ? ' has-letterhead' : '' ?>">
    <?php if ($letterheadUrl !== null): ?>
        <div class="outgoing-letter-letterhead">
            <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Surat" />
        </div>
    <?php endif; ?>
    <div class="outgoing-letter-body">
        <div style="text-align: center; margin-bottom: 16px;">
           <div style="font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-top: 4px; text-decoration: underline;">
                <?= htmlspecialchars($letterType !== '' ? $letterType : 'SURAT', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php if ($number !== ''): ?>
                <div style="margin-top: 4px;">Nomor: <?= htmlspecialchars($number, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <table style="width: 100%; margin: 0 auto 16px auto; border-collapse: collapse;">
            <tr>
                <td style="width: 18%; vertical-align: top;">Lampiran</td>
                <td style="width: 2%; vertical-align: top;">:</td>
                <td style="vertical-align: top;"><?= $attachment !== '' ? nl2br(htmlspecialchars($attachment, ENT_QUOTES, 'UTF-8')) : '-' ?></td>
            </tr>
            <tr>
                <td style="vertical-align: top;">Perihal</td>
                <td style="vertical-align: top;">:</td>
                <td style="vertical-align: top; font-weight: bold;"><?= htmlspecialchars($subject !== '' ? $subject : '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>

        <?php if ($recipient !== ''): ?>
            <div style="margin-bottom: 12px;">
                Yth. <?= htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8') ?><br />
                di tempat
            </div>
        <?php endif; ?>

        <div class="letter-rich-text" style="margin-top: 12px; text-align: justify;">
            <?php if ($bodyHtml !== ''): ?>
                <?= $bodyHtml ?>
            <?php elseif ($bodyText !== ''): ?>
                <?= nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8')) ?>
            <?php else: ?>
                ...
            <?php endif; ?>
        </div>

        <div style="margin-top: 36px; width: 100%; display: flex; justify-content: flex-end;">
            <div style="text-align: center; width: 320px;">
                <?php if ($letterDate !== ''): ?>
                    <div>
                        <?= htmlspecialchars(($schoolCity !== '' ? $schoolCity . ', ' : '') . $letterDate, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <div><?= htmlspecialchars($signatureTitle, ENT_QUOTES, 'UTF-8') ?></div>

                <?php if ($signature !== null && $signatureQrValue !== ''): ?>
                    <div class="digital-signature-card">
                        <div class="digital-signature-card__qr" data-qr-value="<?= htmlspecialchars($signatureQrValue, ENT_QUOTES, 'UTF-8') ?>" data-qr-size="110"></div>
                        <div class="digital-signature-card__body">
                            <div class="digital-signature-card__title">
                                <?= htmlspecialchars($signatureStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if ($signatureApprovedAt !== null): ?>
                                <div class="digital-signature-card__meta">
                                    Disetujui <?= htmlspecialchars($signatureApprovedAt, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($signatureApproverName !== null): ?>
                                <div class="digital-signature-card__meta">
                                    Disahkan oleh <?= htmlspecialchars($signatureApproverName, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($signatureToken !== null): ?>
                                <div class="digital-signature-card__token">
                                    Kode: <?= htmlspecialchars($signatureToken, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($signature !== null && $signatureStatus === 'pending'): ?>
                    <div style="margin-top: 10px; font-size: 10pt; color: #f97316;">
                        TTD digital kepala sekolah sedang menunggu persetujuan.
                    </div>
                <?php elseif ($signatureMissing): ?>
                    <div style="margin-top: 10px; font-size: 10pt; color: #b91c1c;">
                        TTD digital kepala sekolah belum diajukan.
                    </div>
                <?php endif; ?>
            </div>
        </div>




        <?php if (!empty($tembusanLines)): ?>
            <div style="margin-top: 32px;">
                <div style="font-weight: bold;">Tembusan:</div>
                <ol style="margin-top: 8px; padding-left: 20px;">
                    <?php foreach ($tembusanLines as $line): ?>
                        <li><?= htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>
    <?php if ($requiresHeadmasterDigital): ?>
            <div style="margin-top: 18px; font-size: 9pt; color: #64748b;">
                <?php if ($signature !== null && !empty($signature['verification_url'])): ?>
                    <?= htmlspecialchars($signature['verification_url'], ENT_QUOTES, 'UTF-8') ?>
                <?php elseif ($signature !== null): ?>
                    TTD digital kepala sekolah untuk surat ini sedang menunggu persetujuan.
                <?php elseif ($signatureMissing): ?>
                    TTD digital kepala sekolah belum diajukan untuk surat ini.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($attachments)): ?>
    <?php foreach ($attachments as $entry): ?>
        <?php
            $attachmentNumber = isset($entry['number']) ? (int) $entry['number'] : 0;
            $attachmentHtml = isset($entry['body_html']) ? (string) $entry['body_html'] : '';
            $attachmentTitle = $attachmentNumber > 0
                ? sprintf('Lampiran %d', $attachmentNumber)
                : 'Lampiran';
        ?>
        <div class="outgoing-letter-page<?= $letterheadUrl !== null ? ' has-letterhead' : '' ?>" style="page-break-before: always;">
            <?php if ($letterheadUrl !== null): ?>
                <div class="outgoing-letter-letterhead">
                    <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Surat" />
                </div>
            <?php endif; ?>
            <div class="outgoing-letter-body">
                <div style="text-align: center; margin-bottom: 16px;">
                    <?php if ($schoolName !== ''): ?>
                        <div style="font-size: 16pt; font-weight: bold; text-transform: uppercase;"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <div style="font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-top: 4px;">
                        <?= htmlspecialchars($attachmentTitle, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if ($number !== ''): ?>
                        <div style="margin-top: 4px;">Nomor Surat: <?= htmlspecialchars($number, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <div class="letter-rich-text" style="margin-top: 12px; text-align: justify;">
                    <?= $attachmentHtml !== '' ? $attachmentHtml : '<p>&nbsp;</p>' ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
