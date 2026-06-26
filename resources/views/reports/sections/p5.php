<?php
    /** @var array<string, mixed> $report */
    $projects = $report['p5_projects'] ?? [];
    $student = $report['student'] ?? [];
    $class = $report['class'] ?? [];
    $paperSize = $paperSize ?? 'f4';
?>
<style>
    @media print {
        .p5-section table tbody tr {
            page-break-inside: avoid;
        }
    }
</style>
<div class="p5-section">
    <h2 class="text-center uppercase fw-semibold" style="margin-bottom: 6mm;">Projek Penguatan Profil Pelajar Pancasila</h2>
    <?php if (empty($projects)): ?>
        <p class="text-center">Belum ada data projek P5.</p>
    <?php else: ?>
        <?php foreach ($projects as $idx => $project): ?>
            <?php $separatorStyle = $idx > 0 ? 'padding-top:3mm;' : ''; ?>
            <div style="<?= $separatorStyle ?> margin-bottom: 7mm;">
                <div style="margin-bottom: 4mm; font-size:10pt; color:#334155;">
                    <strong>Projek <?= htmlspecialchars((string) ($idx + 1), ENT_QUOTES, 'UTF-8') ?></strong> dari <?= htmlspecialchars((string) count($projects), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="margin-bottom:6mm; border:1px solid #0f172a; border-radius:6px; padding:5mm; font-size:10.5pt; page-break-inside: avoid; break-inside: avoid;">
                <?php $summary = $project['summary'] ?? null; ?>
                <p style="font-weight:700; margin:0 0 3mm 0;">Ringkasan Projek</p>
                <p style="margin:0 0 2mm 0;">Capaian Akhir: <strong><?= htmlspecialchars($summary['capaian_akhir_enum'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p style="margin:0 0 2mm 0;">Deskripsi: <?= nl2br(htmlspecialchars($summary['deskripsi_umum'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></p>
                <p style="margin:0;">Tindak Lanjut: <?= nl2br(htmlspecialchars($summary['tindak_lanjut'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></p>
            </div>

            <table style="width:100%; font-size:10.5pt; border-collapse:collapse; margin-bottom:8mm; border:1px solid #0f172a;">
                <tr>
                    <td style="width:28mm; font-weight:600;">Judul Projek</td>
                    <td>: <?= htmlspecialchars($project['title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Tema</td>
                    <td>: <?= htmlspecialchars($project['theme'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php if (!empty($project['mentor'])): ?>
                    <tr>
                        <td style="font-weight:600;">Pembimbing</td>
                        <td>: <?= htmlspecialchars($project['mentor'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($project['start_date']) || !empty($project['end_date'])): ?>
                    <tr>
                        <td style="font-weight:600;">Periode</td>
                        <td>:
                            <?= htmlspecialchars($project['start_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            s.d.
                            <?= htmlspecialchars($project['end_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <table style="width:100%; border-collapse:collapse; font-size:10pt; margin-top:4mm;">
                <thead>
                    <tr>
                        <th style="border:1px solid #0f172a; padding:6px; width:8mm;">No</th>
                        <th style="border:1px solid #0f172a; padding:6px; width:40mm;">Elemen / TP</th>
                        <th style="border:1px solid #0f172a; padding:6px; width:20mm;">Capaian</th>
                        <th style="border:1px solid #0f172a; padding:6px;">Catatan</th>
                        <th style="border:1px solid #0f172a; padding:6px; width:18mm;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($project['elements'])): ?>
                        <tr>
                            <td colspan="5" style="border:1px solid #0f172a; padding:6px; text-align:center;">Belum ada elemen yang dinilai.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($project['elements'] as $index => $element): ?>
                            <tr>
                                <td style="border:1px solid #0f172a; padding:6px; text-align:center;"><?= $index + 1 ?></td>
                                <td style="border:1px solid #0f172a; padding:6px;">
                                    <div style="font-weight:600;"><?= htmlspecialchars($element['code'] ?? 'EL', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($element['name'])): ?>
                                        <div style="font-size:9pt; color:#475569;"><?= htmlspecialchars($element['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($element['tp'])): ?>
                                        <div style="font-size:9pt; color:#334155; margin-top:2mm;"><?= nl2br(htmlspecialchars($element['tp'], ENT_QUOTES, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="border:1px solid #0f172a; padding:6px; text-align:center; font-weight:600;">
                                    <?= htmlspecialchars($element['capaian'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td style="border:1px solid #0f172a; padding:6px; font-size:9pt;">
                                    <?= nl2br(htmlspecialchars($element['catatan'] ?? '-', ENT_QUOTES, 'UTF-8')) ?>
                                </td>
                                <td style="border:1px solid #0f172a; padding:6px; text-align:center;">
                                    <?= htmlspecialchars($element['nilai'] !== null ? (string) $element['nilai'] : '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
                <div style="margin-top:3mm;"></div>
            </div>
        <?php endforeach; ?>
        <?php
            $digitalSignature = $report['digitalSignature'] ?? null;
            $digitalEnabled = is_array($digitalSignature) && ($digitalSignature['enabled'] ?? false);
            $headmasterName = '';
            if (is_array($digitalSignature)) {
                $headmasterName = (string) ($digitalSignature['headmasterName'] ?? '');
            }
            if ($headmasterName === '' && isset($class['kepala_sekolah'])) {
                $headmasterName = (string) $class['kepala_sekolah'];
            }
            $headmasterName = $headmasterName !== '' ? $headmasterName : '________________';
            $waliNama = $class['wali_kelas_nama'] ?? '________________';
            $parentLabel = '(................................................)';
            $kabupatenLabel = isset($kabupatenLabel) && $kabupatenLabel !== '' ? $kabupatenLabel : ($class['kabupaten'] ?? '-');
        ?>
        <div style="margin-top: 10mm; display:flex; justify-content: space-between; gap:8mm; font-size:10.5pt; page-break-inside: avoid; break-inside: avoid;">
            <div style="flex:1; text-align:center;">
                <p>Orang Tua / Wali</p>
                <div style="height:26mm;"></div>
                <p><?= htmlspecialchars($parentLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div style="flex:1; text-align:center;">
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <?php if ($digitalEnabled && ($digitalSignature['status'] ?? '') === 'approved' && !empty($digitalSignature['verificationUrl'] ?? null)): ?>
                    <div style="margin:2mm auto 4mm auto; display:flex; align-items:center; justify-content:center; gap:6mm; padding:4mm; border:1px dashed #94a3b8; border-radius:6px; background-color:#f8fafc; max-width:70mm; page-break-inside: avoid; break-inside: avoid;">
                        <div style="width:14mm; min-width:14mm; height:14mm; display:flex; align-items:center; justify-content:center;" data-qr-value="<?= htmlspecialchars($digitalSignature['verificationUrl'], ENT_QUOTES, 'UTF-8') ?>" data-qr-size="65"></div>
                        <div style="text-align:left; font-size:9pt;">
                            <p style="font-weight:600; margin:0 0 2mm 0;">TTD Digital Disetujui</p>
                            <?php if (!empty($digitalSignature['approvedAtLabel'] ?? '')): ?>
                                <p style="margin:0 0 1mm 0; font-size:8pt; color:#475569;">Disetujui <?= htmlspecialchars($digitalSignature['approvedAtLabel'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if (!empty($digitalSignature['signatureToken'] ?? '')): ?>
                                <p style="margin:0; font-size:4pt; color:#475569;">Kode: <?= htmlspecialchars($digitalSignature['signatureToken'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="height:26mm;"></div>
                <?php endif; ?>
                <p style="font-weight:600; margin:0;"><?= htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div style="flex:1; text-align:center;">
                <p><?= htmlspecialchars($kabupatenLabel, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($printedDateLabel ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                <p>Wali Kelas</p>
                <div style="height:26mm;"></div>
                <p style="font-weight:600; text-decoration: underline; margin:0;"><?= htmlspecialchars($waliNama, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>
