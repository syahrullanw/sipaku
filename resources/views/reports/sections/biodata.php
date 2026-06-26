<?php
    $student = $report['student'] ?? [];
    $class = $report['class'] ?? [];
    $school = $report['school'] ?? [];
    $formatDate = static function (?string $date): string {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '-';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '-';
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

        return sprintf('%d %s %s', (int) date('j', $timestamp), $month, date('Y', $timestamp));
    };

    $genderMapping = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    $valueOrDash = static function (mixed $value): string {
        if ($value === null) {
            return '-';
        }

        $string = trim((string) $value);

        return $string === '' ? '-' : $string;
    };

    $buildAddress = static function (array $student) use ($valueOrDash): string {
        $lines = [];

        $address = trim((string) ($student['alamat'] ?? ''));
        if ($address !== '') {
            $lines[] = $address;
        }

        $rt = trim((string) ($student['rt'] ?? ''));
        $rw = trim((string) ($student['rw'] ?? ''));
        if ($rt !== '' || $rw !== '') {
            $lines[] = sprintf('RT %s / RW %s', $rt !== '' ? $rt : '-', $rw !== '' ? $rw : '-');
        }

        $dusun = trim((string) ($student['dusun'] ?? ''));
        if ($dusun !== '') {
            $lines[] = 'Dusun: ' . $dusun;
        }

        $kelurahan = trim((string) ($student['kelurahan'] ?? ''));
        if ($kelurahan !== '') {
            $lines[] = 'Kelurahan: ' . $kelurahan;
        }

        $kecamatan = trim((string) ($student['kecamatan'] ?? ''));
        if ($kecamatan !== '') {
            $lines[] = 'Kecamatan: ' . $kecamatan;
        }

        $kodePos = trim((string) ($student['kode_pos'] ?? ''));
        if ($kodePos !== '') {
            $lines[] = 'Kode Pos: ' . $kodePos;
        }

        if (empty($lines)) {
            return '-';
        }

        return implode("\n", $lines);
    };

    $buildHousehold = static function (array $student) use ($valueOrDash): string {
        $anakKe = $valueOrDash($student['anak_ke'] ?? null);
        $jenisTinggal = $valueOrDash($student['jenis_tinggal'] ?? null);
        $transportasi = $valueOrDash($student['alat_transportasi'] ?? null);

        return sprintf(
            "Anak ke       : %s\nJenis Tinggal : %s\nAlat Transportasi : %s",
            $anakKe,
            $jenisTinggal,
            $transportasi
        );
    };

    $buildAcademic = static function (array $student) use ($valueOrDash): string {
        return sprintf(
            "Nomor Peserta Ujian Nasional : %s\nNomor Seri Ijazah           : %s\nSKHUN                       : %s",
            $valueOrDash($student['nomor_peserta_ujian'] ?? null),
            $valueOrDash($student['nomor_seri_ijazah'] ?? null),
            $valueOrDash($student['skhun'] ?? null)
        );
    };

    $buildParent = static function (array $student, string $prefix) use ($valueOrDash): string {
        $nameKey = $prefix . '_nama';
        $rawName = $student[$nameKey] ?? '';
        if ($prefix === 'wali' && trim((string) $rawName) === '') {
            return '-';
        }

        return sprintf(
            "Nama        : %s\nTahun Lahir : %s\nPendidikan  : %s\nPekerjaan   : %s\nPenghasilan : %s\nNIK         : %s",
            $valueOrDash($rawName),
            $valueOrDash($student[$prefix . '_tahun_lahir'] ?? null),
            $valueOrDash($student[$prefix . '_jenjang_pendidikan'] ?? null),
            $valueOrDash($student[$prefix . '_pekerjaan'] ?? null),
            $valueOrDash($student[$prefix . '_penghasilan'] ?? null),
            $valueOrDash($student[$prefix . '_nik'] ?? null)
        );
    };

    $buildBantuan = static function (array $student) use ($valueOrDash): string {
        $lines = [];

        $penerimaKps = (int) ($student['penerima_kps'] ?? 0) === 1
            ? 'Ya (' . $valueOrDash($student['nomor_kps'] ?? null) . ')'
            : 'Tidak';
        $lines[] = 'Penerima KPS : ' . $penerimaKps;

        $penerimaKip = (int) ($student['penerima_kip'] ?? 0) === 1
            ? 'Ya (' . $valueOrDash($student['nomor_kip'] ?? null) . '; Nama: ' . $valueOrDash($student['nama_di_kip'] ?? null) . ')'
            : 'Tidak';
        $lines[] = 'Penerima KIP : ' . $penerimaKip;

        $lines[] = 'Nomor KKS    : ' . $valueOrDash($student['nomor_kks'] ?? null);

        $layakPip = (int) ($student['layak_pip'] ?? 0) === 1 ? 'Ya' : 'Tidak';
        $lines[] = 'Layak PIP    : ' . $layakPip;

        if ((int) ($student['layak_pip'] ?? 0) === 1) {
            $lines[] = 'Alasan       : ' . $valueOrDash($student['alasan_layak_pip'] ?? null);
        }

        return implode("\n", $lines);
    };

    $buildBank = static function (array $student) use ($valueOrDash): string {
        return sprintf(
            "Bank         : %s\nNomor Rekening : %s\nAtas Nama    : %s",
            $valueOrDash($student['bank'] ?? null),
            $valueOrDash($student['nomor_rekening_bank'] ?? null),
            $valueOrDash($student['rekening_atas_nama'] ?? null)
        );
    };

    $genderLabel = $genderMapping[$student['jenis_kelamin'] ?? ''] ?? '-';

    $biodataRows = [
        ['no' => '1.', 'label' => 'Nama Peserta Didik (Lengkap)', 'value' => $valueOrDash($student['nama'] ?? null)],
        ['no' => '2.', 'label' => 'Nomor Induk (NIPD)', 'value' => $valueOrDash($student['nipd'] ?? null)],
        ['no' => '3.', 'label' => 'NISN', 'value' => $valueOrDash($student['nisn'] ?? null)],
        ['no' => '4.', 'label' => 'NIK', 'value' => $valueOrDash($student['nik'] ?? null)],
        ['no' => '5.', 'label' => 'Nomor KK', 'value' => $valueOrDash($student['nomor_kk'] ?? null)],
        ['no' => '6.', 'label' => 'Tempat, Tanggal Lahir', 'value' => trim(($student['tempat_lahir'] ?? '') . ', ' . $formatDate($student['tanggal_lahir'] ?? null), ', ')],
        ['no' => '7.', 'label' => 'Jenis Kelamin', 'value' => $genderLabel],
        ['no' => '8.', 'label' => 'Agama', 'value' => $valueOrDash($student['agama'] ?? null)],
        ['no' => '9.', 'label' => 'Alamat Peserta Didik', 'value' => $buildAddress($student)],
        ['no' => '10.', 'label' => 'Keluarga & Tempat Tinggal', 'value' => $buildHousehold($student)],
        ['no' => '11.', 'label' => 'Data Akademik', 'value' => $buildAcademic($student)],
        ['no' => '12.', 'label' => 'Nomor Registrasi Akta Lahir', 'value' => $valueOrDash($student['nomor_registrasi_akta_lahir'] ?? null)],
        ['no' => '13.', 'label' => 'Madrasah / Sekolah Asal', 'value' => $valueOrDash($student['sekolah_asal'] ?? null)],
        ['no' => '14.', 'label' => 'Data Ayah', 'value' => $buildParent($student, 'ayah')],
        ['no' => '15.', 'label' => 'Data Ibu', 'value' => $buildParent($student, 'ibu')],
        ['no' => '16.', 'label' => 'Data Wali', 'value' => $buildParent($student, 'wali')],
        ['no' => '17.', 'label' => 'Bantuan Sosial', 'value' => $buildBantuan($student)],
        ['no' => '18.', 'label' => 'Rekening Bank', 'value' => $buildBank($student)],
    ];

    $kabupaten = $school['kabupaten'] ?? '-';
    $printedDateLabel = $report['printedDateLabel'] ?? '';
    $waliNama = $class['wali_kelas_nama'] ?? '________________';
    $studentPhotoPath = trim((string) ($student['foto_path'] ?? ''));
    $studentPhotoUrl = $studentPhotoPath !== '' ? asset($studentPhotoPath) : null;
?>
<style>
    .biodata-header {
        text-align: center;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 6mm;
    }

    .biodata-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11pt;
    }

    .biodata-table td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
        vertical-align: top;
    }

    .biodata-table td:first-child {
        width: 10mm;
        text-align: center;
        font-weight: 600;
    }

    .biodata-table td:nth-child(2) {
        width: 70mm;
        font-weight: 500;
    }

    .biodata-signature {
        margin-top: 14mm;
        width: 100%;
        display: flex;
        justify-content: flex-end;
        gap: 12mm;
        align-items: flex-start;
    }

    .biodata-signature p {
        margin: 0;
    }

    .signature-wrapper {
        width: 70mm;
        text-align: center;
    }

    .signature-spacer {
        height: 22mm;
    }

    .signature-photo {
        width: 35mm;
        height: 45mm;
        border: 1px solid #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: #f8fafc;
        font-size: 9pt;
        color: #64748b;
        text-align: center;
        padding: 2mm;
        box-sizing: border-box;
    }

    .signature-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @media print {
        .biodata-table tbody tr {
            page-break-inside: avoid;
        }
    }
</style>
<div>
    <h1 class="biodata-header">Profile Peserta Didik</h1>
    <table class="biodata-table">
        <?php foreach ($biodataRows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['no'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?= nl2br(htmlspecialchars($row['value'] !== '' ? (string) $row['value'] : '-', ENT_QUOTES, 'UTF-8')) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="biodata-signature">
        <div class="signature-photo">
            <?php if ($studentPhotoUrl !== null): ?>
                <img src="<?= htmlspecialchars($studentPhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto Siswa" />
            <?php else: ?>
                FOTO<br>3 x 4
            <?php endif; ?>
        </div>
        <div class="signature-wrapper">
            <p><?= htmlspecialchars($kabupaten, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Wali Kelas</p>
            <div class="signature-spacer"></div>
            <p class="fw-semibold underline"><?= htmlspecialchars($waliNama, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
</div>
