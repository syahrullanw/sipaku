<?php
    $record = $record ?? [];
    $payload = $payload ?? [];
    $currentStudent = is_array($currentStudent ?? null) ? $currentStudent : [];
    $student = $payload['student'] ?? [];
    $class = $payload['class'] ?? [];
    $subjects = isset($payload['subjects']) && is_array($payload['subjects']) ? $payload['subjects'] : [];
    $average = $payload['average'] ?? null;
    $schoolYear = $schoolYear ?? null;
    $schoolProfile = $schoolProfile ?? [];
    $headmaster = $headmaster ?? null;
    $verificationUrl = $verificationUrl ?? null;
    $schoolYearName = $payload['school_year_name'] ?? ($schoolYear['nama'] ?? '');

    $schoolName = $schoolProfile['nama'] ?? 'Sekolah';
    $schoolAddress = $schoolProfile['alamat'] ?? '';
    $schoolRegion = trim((string) ($schoolProfile['kabupaten'] ?? ''));
    $headmasterName = $headmaster['nama'] ?? '';
    $headmasterNip = $headmaster['nip'] ?? '';
    $approvedAt = $record['approved_at'] ?? null;
    $formatDate = static function (?string $date): string {
        if ($date === null || trim($date) === '') {
            return '';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return trim($date);
        }
        $months = [
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
        $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);
        return date('d', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
    };
    $approvedDateLabel = $approvedAt !== null ? $formatDate((string) $approvedAt) : $formatDate(date('Y-m-d'));
    $approvedAtDetail = $approvedAt !== null ? date('d/m/Y H:i', strtotime((string) $approvedAt)) : null;
    $approvalCity = $schoolProfile['kota'] ?? ($schoolProfile['kabupaten'] ?? '________');
    $sklNumber = trim((string) ($schoolProfile['skl_nomor_surat'] ?? ''));
    $plenaryDate = $formatDate($schoolProfile['skl_tanggal_rapat_pleno'] ?? null);
    $titimangsaDate = $formatDate($schoolProfile['skl_titimangsa'] ?? null);
    $signatureToken = $record['signature_token'] ?? null;
    $letterheadPath = $schoolProfile['kop_surat'] ?? '';
    $letterheadUrl = $letterheadPath !== '' ? asset($letterheadPath) : null;
    $resolveText = static function (...$values): string {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    };
    $preferredParent = static function (...$values): string {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text === '' || strcasecmp($text, 'belum') === 0) {
                continue;
            }

            return $text;
        }

        return '';
    };
    $studentBirthPlace = $resolveText($student['birth_place'] ?? '', $currentStudent['tempat_lahir'] ?? '');
    $studentBirthDate = $formatDate($resolveText($student['birth_date'] ?? '', $currentStudent['tanggal_lahir'] ?? ''));
    $studentBirthPlaceDate = trim((string) ($student['birth_place_date'] ?? ''));
    if ($studentBirthPlaceDate === '') {
        $studentBirthPlaceDate = trim($studentBirthPlace . ($studentBirthDate !== '' ? ', ' . $studentBirthDate : ''), ', ');
    }
    $parentName = $preferredParent(
        $student['parent_name'] ?? '',
        $student['father_name'] ?? '',
        $currentStudent['ayah_nama'] ?? '',
        $student['mother_name'] ?? '',
        $currentStudent['ibu_nama'] ?? ''
    );
    $openingRegion = $schoolRegion !== '' ? ' Kabupaten ' . $schoolRegion : '';
?>

<div style="max-width: 760px; margin: 0 auto; font-size: 14pt; line-height: 1.45;">
    <?php if ($letterheadUrl !== null): ?>
        <div style="margin-bottom: 18px;">
            <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Surat" style="display: block; width: 100%; height: auto;" />
        </div>
    <?php else: ?>
        <div style="text-align: center; margin-bottom: 18px;">
            <p style="margin: 0; text-transform: uppercase;">PEMERINTAH</p>
            <h1 style="margin: 4px 0 2px; font-size: 22pt; font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($schoolAddress !== ''): ?>
                <p style="margin: 0;"><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="padding: 0 42px;">
        <div style="text-align: center; margin: 6px 0 22px;">
            <div style="font-size: 13pt; font-weight: 700; text-transform: uppercase; text-decoration: underline;">Surat Keterangan Lulus</div>
            <?php if ($sklNumber !== ''): ?>
                <div style="margin-top: 4px; font-size: 10pt;">Nomor: <?= htmlspecialchars($sklNumber, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>

        <p style="margin: 0 0 18px; font-size: 11pt;">
            Kepala <?= htmlspecialchars($schoolName . $openingRegion . ' Tahun Ajaran ' . ($schoolYearName !== '' ? $schoolYearName : '-'), ENT_QUOTES, 'UTF-8') ?>,
            <br>
            dengan berdasarkan:
        </p>

        <table style="width: 100%; border: none; border-collapse: separate; border-spacing: 0 8px; font-size: 11pt; line-height: 1.35;">
            <tr>
                <td style="width: 48px; border: none; padding: 0; vertical-align: top; text-align: right;">1.</td>
                <td style="border: none; padding: 0 0 0 18px; text-align: justify;">Penyelesaian seluruh program pembelajaran pada Kurikulum 2013/Kurikulum Nasional;</td>
            </tr>
            <tr>
                <td style="border: none; padding: 0; vertical-align: top; text-align: right;">2.</td>
                <td style="border: none; padding: 0 0 0 18px; text-align: justify;">Kriteria kelulusan dari satuan pendidikan sesuai dengan peraturan perundang-undangan;</td>
            </tr>
            <tr>
                <td style="border: none; padding: 0; vertical-align: top; text-align: right;">3.</td>
                <td style="border: none; padding: 0 0 0 18px; text-align: justify;">Rapat Pleno Dewan Guru tentang Penetapan Kelulusan pada tanggal <?= htmlspecialchars($plenaryDate !== '' ? $plenaryDate : '................................', ENT_QUOTES, 'UTF-8') ?>;</td>
            </tr>
        </table>

        <p style="margin: 26px 0 12px; font-size: 11pt;">Menerangkan bahwa:</p>

        <table style="width: 100%; margin-top: 0; border-collapse: separate; border-spacing: 0 10px; border: none; font-size: 11pt; line-height: 1.35;">
            <tr>
                <td style="width: 44%; padding: 0; border: none;">Nama</td>
                <td style="width: 28px; padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none; font-weight: 700;"><?= htmlspecialchars((string) ($student['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">Tempat dan Tanggal Lahir</td>
                <td style="padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none;"><?= htmlspecialchars($studentBirthPlaceDate !== '' ? $studentBirthPlaceDate : '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">Nama Orang Tua</td>
                <td style="padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none;"><?= htmlspecialchars($parentName !== '' ? $parentName : '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">Nomor Induk Siswa</td>
                <td style="padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none;"><?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">Nomor Induk Siswa Nasional</td>
                <td style="padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none;"><?= htmlspecialchars((string) ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 0; border: none;">Peminatan/Mapel Pilihan</td>
                <td style="padding: 0; border: none; text-align: center;">:</td>
                <td style="padding: 0; border: none;"><?= htmlspecialchars((string) ($class['major'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td style="padding: 24px 0 0; border: none;">Dinyatakan</td>
                <td style="padding: 24px 0 0; border: none; text-align: center;">:</td>
                <td style="padding: 18px 0 0; border: none; font-size: 20pt; font-weight: 700; letter-spacing: 11px;">LULUS</td>
            </tr>
            <tr>
                <td style="padding: 10px 0 0; border: none;">dengan Rata-rata Nilai*)</td>
                <td style="padding: 10px 0 0; border: none; text-align: center;">:</td>
                <td style="padding: 10px 0 0; border: none;"><?= htmlspecialchars((string) ($average ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>

        <div style="margin-top: 48px; width: 320px; margin-left: auto; text-align: left;">
            <p style="margin: 0; font-size: 11pt;"><?= htmlspecialchars($approvalCity, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($titimangsaDate !== '' ? $titimangsaDate : $approvedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin: 0; font-size: 11pt;">Kepala Sekolah,</p>
        </div>

        <?php if ($verificationUrl !== null): ?>
            <div style="margin-top: 8px; width: 320px; margin-left: auto; text-align: left;">
                <div style="display: inline-flex; gap: 8px; align-items: center;">
                    <div style="border: 1px solid #999; padding: 3px; display: inline-block;">
                        <div data-qr-value="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>" data-qr-size="80"></div>
                    </div>
                    <div style="text-align: left; max-width: 228px;">
                        <p style="margin: 0; font-size: 10px;">TTD digital terverifikasi</p>
                        <?php if ($approvedAtDetail !== null): ?>
                            <p style="margin: 0; font-size: 9px;">Disetujui <?= htmlspecialchars($approvedAtDetail, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($signatureToken !== null && $signatureToken !== ''): ?>
                            <p style="margin: 0; font-size: 8px;">Kode: <?= htmlspecialchars($signatureToken, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px; width: 320px; margin-left: auto; text-align: left;">
            <?php if ($headmasterName !== ''): ?>
                <p style="margin: 0; font-size: 13pt;"><?= htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p style="margin: 0; font-size: 13pt;">....................................</p>
            <?php endif; ?>
            <?php if ($headmasterNip !== ''): ?>
                <p style="margin: 0; font-size: 10pt;">NIP. <?= htmlspecialchars($headmasterNip, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
