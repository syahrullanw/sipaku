<?php
    /** @var array<int, array<string, mixed>> $transcripts */

    $formatScore = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2, ',', '.');
    };

    $formatDate = static function (?string $date): string {
        if ($date === null || trim($date) === '' || $date === '0000-00-00') {
            return '';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return trim($date);
        }
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return date('d', $timestamp) . ' ' . ($months[(int) date('n', $timestamp)] ?? date('F', $timestamp)) . ' ' . date('Y', $timestamp);
    };

    $valueOrDash = static function ($value): string {
        $text = trim((string) $value);
        return $text !== '' ? $text : '-';
    };

    $renderTranscript = static function (array $transcript) use ($formatScore, $formatDate, $valueOrDash): void {
        $student = $transcript['student'] ?? [];
        $class = $transcript['class'] ?? [];
        $school = $transcript['schoolProfile'] ?? [];
        $graduationRecord = $transcript['graduationRecord'] ?? [];
        $rows = $transcript['transcriptRows'] ?? [];
        $digitalSignature = $transcript['digitalSignature'] ?? null;
        $printedDateLabel = (string) ($transcript['printedDateLabel'] ?? '');
        $graduationDateLabel = (string) ($transcript['graduationDateLabel'] ?? $printedDateLabel);
        $documentNumber = (string) ($transcript['documentNumber'] ?? '');
        $scopeLabel = (string) ($transcript['transcriptScopeLabel'] ?? 'Kelas 12');
        $transcriptAverage = $transcript['transcriptAverage'] ?? null;

        $studentName = $valueOrDash($student['nama'] ?? null);
        $birthPlace = trim((string) ($student['tempat_lahir'] ?? ''));
        $birthDate = $formatDate(isset($student['tanggal_lahir']) ? (string) $student['tanggal_lahir'] : null);
        $birthPlaceDate = trim($birthPlace . ($birthDate !== '' ? ', ' . $birthDate : ''), ', ');
        $schoolName = $valueOrDash($school['nama'] ?? null);
        $npsn = $valueOrDash($school['npsn'] ?? null);
        $diplomaNumber = $valueOrDash($graduationRecord['nomor_ijazah'] ?? ($student['nomor_seri_ijazah'] ?? null));
        $specializationType = $valueOrDash($graduationRecord['jenis_kekhususan'] ?? null);
        $programName = $valueOrDash($class['jurusan_nama'] ?? ($student['jurusan_nama'] ?? null));
        $concentrationName = $programName;
        $headmasterName = $valueOrDash($school['kepala_sekolah'] ?? null);
        $headmasterNip = trim((string) ($school['kepala_sekolah_nip'] ?? ''));
        $letterheadPath = trim((string) ($school['kop_surat'] ?? ''));
        $letterheadUrl = $letterheadPath !== '' ? asset($letterheadPath) : null;
        $city = $valueOrDash($school['kabupaten'] ?? ($school['desa'] ?? null));
    ?>
    <div class="transcript-page">
        <div class="transcript-letterhead">
            <?php if ($letterheadUrl !== null): ?>
                <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Satuan Pendidikan">
            <?php else: ?>
                <div class="transcript-letterhead-title">
                    <?= htmlspecialchars($schoolName !== '-' ? strtoupper($schoolName) : 'KOP SATUAN PENDIDIKAN', ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="transcript-title">
            <h1>Transkrip Nilai</h1>
            <p>Nomor: <?= htmlspecialchars($documentNumber !== '' ? $documentNumber : '................................', ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <table class="identity-table">
            <tr>
                <td class="identity-label">Satuan Pendidikan</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Nomor Pokok Sekolah Nasional</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($npsn, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Nama Lengkap</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Tempat, Tanggal Lahir</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($valueOrDash($birthPlaceDate), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Nomor Induk Siswa Nasional</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($valueOrDash($student['nisn'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Nomor Ijazah</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($diplomaNumber, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Tanggal Kelulusan</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($valueOrDash($graduationDateLabel), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Jenis Kekhususan</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($specializationType, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Program Keahlian</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($programName, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td class="identity-label">Konsentrasi Keahlian</td>
                <td class="identity-separator">:</td>
                <td class="identity-value"><?= htmlspecialchars($concentrationName, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>

        <table class="score-table">
            <thead>
                <tr>
                    <th class="col-no">No.</th>
                    <th>Mata Pelajaran</th>
                    <th class="col-score">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td class="col-no"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($row['display_name'] ?? $row['subject_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-score"><?= htmlspecialchars($formatScore($row['score'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td class="col-no">1.</td>
                        <td>Belum ada data nilai.</td>
                        <td class="col-score">-</td>
                    </tr>
                <?php endif; ?>
                <tr class="average-row">
                    <td colspan="2" class="average-label">Rata-rata Nilai</td>
                    <td class="col-score"><?= htmlspecialchars($formatScore($transcriptAverage), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            </tbody>
        </table>
        <p class="scope-note">Cakupan nilai: <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="signature-area">
            <div class="signature-block">
                <p><?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p>Kepala Sekolah,</p>
                <div class="signature-space">
                    <?php if (is_array($digitalSignature) && ($digitalSignature['enabled'] ?? false) && ($digitalSignature['status'] ?? '') === 'approved' && !empty($digitalSignature['verificationUrl'])): ?>
                        <div class="digital-signature-qr" data-qr-value="<?= htmlspecialchars((string) $digitalSignature['verificationUrl'], ENT_QUOTES, 'UTF-8') ?>" data-qr-size="76"></div>
                        <div class="digital-signature-label">
                            TTD digital<br>
                            <?= htmlspecialchars((string) ($digitalSignature['approvedAtLabel'] ?? 'Disetujui'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p><span class="signature-name"><?= htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
        </div>
    </div>
<?php
    };
?>

<style>
    @page {
        size: 210mm 330mm;
        margin: 8mm 9mm 9mm;
    }

    .transcript-page {
        box-sizing: border-box;
        width: 100%;
        min-height: 313mm;
        border: 0;
        padding: 7mm 7mm 8mm;
        color: #111827;
        font-family: "Times New Roman", Times, serif;
        font-size: 11pt;
        line-height: 1.25;
        page-break-after: always;
    }

    .transcript-page:last-child {
        page-break-after: auto;
    }

    .transcript-letterhead {
        text-align: center;
        margin: 0 auto 6mm;
        overflow: visible;
    }

    .transcript-letterhead img {
        display: block;
        width: 112%;
        max-width: none;
        height: auto;
        object-fit: contain;
        margin: 0 -6%;
    }

    .transcript-letterhead-title {
        font-size: 12pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .transcript-title {
        text-align: center;
        margin-bottom: 6mm;
    }

    .transcript-title h1 {
        margin: 0;
        font-size: 15pt;
        line-height: 1.1;
        text-transform: uppercase;
        font-weight: 700;
    }

    .transcript-title p {
        margin: 1mm 0 0;
        font-size: 10.5pt;
    }

    .identity-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6mm;
        font-size: 10.5pt;
    }

    .identity-table td {
        border: 0;
        padding: 0.8mm 1mm;
        vertical-align: top;
    }

    .identity-label {
        width: 54mm;
        font-weight: 600;
    }

    .identity-separator {
        width: 4mm;
        text-align: center;
    }

    .identity-value {
        border-bottom: 1px dotted #6b7280 !important;
    }

    .score-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 10.5pt;
    }

    .score-table th,
    .score-table td {
        border: 1px solid #3f3f46;
        padding: 2mm 2.4mm;
    }

    .score-table th {
        text-align: center;
        font-weight: 700;
    }

    .score-table .col-no {
        width: 14mm;
        text-align: center;
    }

    .score-table .col-score {
        width: 31mm;
        text-align: center;
    }

    .score-table .average-row td {
        font-weight: 700;
    }

    .score-table .average-label {
        text-align: center;
    }

    .scope-note {
        margin: 2mm 0 0;
        font-size: 9.5pt;
        color: #4b5563;
    }

    .signature-area {
        margin-top: 10mm;
        display: flex;
        justify-content: flex-end;
        font-size: 10.5pt;
    }

    .signature-block {
        width: 68mm;
        text-align: left;
    }

    .signature-block p {
        margin: 0 0 1.5mm;
    }

    .signature-space {
        height: 24mm;
        display: flex;
        align-items: flex-end;
        gap: 4mm;
    }

    .digital-signature-qr {
        width: 20mm;
        height: 20mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .digital-signature-label {
        font-size: 8.5pt;
        color: #374151;
        line-height: 1.2;
    }

    .signature-name {
        display: inline-block;
        min-width: 55mm;
        font-weight: 700;
    }

    @media print {
        .transcript-page {
            min-height: auto;
            padding: 0;
        }
    }
</style>

<?php foreach ($transcripts as $transcript): ?>
    <?php $renderTranscript($transcript); ?>
<?php endforeach; ?>
