<?php
    $school = $report['school'] ?? [];
    $rows = [
        'Nama Sekolah' => $school['nama'] ?? '-',
        'NPSN' => $school['npsn'] ?? '-',
        'Alamat' => $school['alamat'] ?? '-',
        'Kecamatan' => $school['kecamatan'] ?? '-',
        'Kabupaten' => $school['kabupaten'] ?? '-',
        'Provinsi' => $school['provinsi'] ?? '-',
        'Telepon' => $school['telepon'] ?? '-',
        'Website' => $school['website'] ?? '-',
        'Email' => $school['email'] ?? '-',
        'Akreditasi' => $school['akreditasi'] ?? '-',
    ];
?>
<style>
    .school-info-title {
        text-align: center;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 10mm;
        letter-spacing: 0.5px;
    }

    .school-info-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 12pt;
    }

    .school-info-table td {
        border: none;
        padding: 4px 0;
        vertical-align: top;
    }

    .school-info-table td:first-child {
        width: 45mm;
    }
</style>
<div>
    <h1 class="school-info-title">Profile Sekolah</h1>
    <table class="school-info-table">
        <?php foreach ($rows as $label => $value): ?>
            <tr>
                <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center">:</td>
                <td><?= htmlspecialchars($value !== '' ? (string) $value : '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
