<?php
    $achievements = $report['achievements'] ?? [];
    $homeroomNote = trim((string) ($report['homeroomNote'] ?? ''));
?>
<style>
    .achievement-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 10.5pt;
    }

    .achievement-table th,
    .achievement-table td {
        border: 1px solid #0f172a;
        padding: 4px 6px;
    }

    .achievement-table th {
        text-align: center;
        font-weight: 600;
    }

    .section-wrapper {
        margin-bottom: 8mm;
    }

    .notes-box {
        border: 1px solid #0f172a;
        min-height: 30mm;
        padding: 4mm;
        font-size: 10.5pt;
    }

    @media print {
        .achievement-table tbody tr {
            page-break-inside: avoid;
        }
    }
</style>
<div>
    <div class="section-wrapper">
        <p class="fw-semibold">F. Prestasi</p>
        <table class="achievement-table">
            <thead>
                <tr>
                    <th style="width: 12mm;">No</th>
                    <th>Jenis Prestasi</th>
                    <th style="width: 60mm;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($achievements)): ?>
                    <tr>
                        <td class="text-center">-</td>
                        <td colspan="2">Belum ada data prestasi.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($achievements as $index => $achievement): ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($achievement['jenis'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($achievement['keterangan'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-wrapper">
        <p class="fw-semibold">G. Catatan Wali Kelas</p>
        <div class="notes-box">
            <?= nl2br(htmlspecialchars($homeroomNote !== '' ? $homeroomNote : 'Belum ada catatan.', ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </div>

    <div class="section-wrapper">
        <p class="fw-semibold">H. Tanggapan Orang Tua / Wali</p>
        <div class="notes-box"></div>
    </div>
</div>
