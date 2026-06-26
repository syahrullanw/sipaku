<?php
    $students = $students ?? [];
    $assessments = $assessments ?? [];
    $dudiMap = $dudiMap ?? [];
    $skkniMap = $skkniMap ?? [];
    $skkniList = $skkniList ?? [];
    $assessorMap = $assessorMap ?? [];
    $schoolProfile = $schoolProfile ?? [];
    $class = $class ?? null;
    $paperSize = $paperSize ?? 'f4';
    $logoPath = $schoolProfile['logo_sekolah'] ?? null;
    $logoUrl = $logoPath ? asset($logoPath) : null;
    $formatDate = static function (?string $value): string {
        return \App\Models\UkkStudentAssessment::formatIndonesianDate($value);
    };
?>
<style>
    .passport-wrapper { width: 100%; font-size: 11pt; color: #0f172a; }
    .passport-card { border: 1px solid #0f172a; border-radius: 8px; padding: 10mm; min-height: 240mm; }
    .passport-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8mm; }
    .passport-logo { width: 22mm; height: 22mm; display: flex; align-items: center; justify-content: center; }
    .passport-logo img { width: 100%; height: 100%; object-fit: contain; }
    .passport-logo-spacer { width: 22mm; height: 22mm; }
    .passport-header-text { flex: 1; text-align: center; }
    .passport-title { text-transform: uppercase; font-weight: 700; font-size: 15pt; margin-bottom: 2mm; }
    .passport-subtitle { font-size: 11pt; color: #475569; }
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .info-table td { padding: 4px 6px; font-size: 10.5pt; }
    .info-table td:first-child { width: 42mm; font-weight: 600; }
    .detail-box { border: 1px solid #0f172a; border-radius: 6px; padding: 6mm; margin-bottom: 8mm; }
    .detail-box h4 { margin: 0 0 4mm 0; font-size: 11pt; font-weight: 700; }
    .score-table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    .score-table th, .score-table td { border: 1px solid #0f172a; padding: 5px 6px; font-size: 10pt; }
    .page-break { page-break-before: always; }
</style>

<?php foreach ($students as $index => $student): ?>
    <?php
        $sid = (int) ($student['id'] ?? 0);
        $assessment = $assessments[$sid] ?? [];
        $skkni = isset($assessment['skkni_id']) ? ($skkniMap[(int) $assessment['skkni_id']] ?? null) : null;
        $dudi = isset($assessment['dudi_id']) ? ($dudiMap[(int) $assessment['dudi_id']] ?? null) : null;
        $assessor = isset($assessment['asesor_id']) ? ($assessorMap[(int) $assessment['asesor_id']] ?? null) : null;
        $units = [];
        if (!empty($skkni['unit_kompetensi'])) {
            $units = preg_split('/\\r\\n|\\r|\\n/', (string) $skkni['unit_kompetensi']);
            $units = array_values(array_filter(array_map('trim', $units), static fn ($v) => $v !== ''));
        }

        $packageId = (int) ($skkni['paket_ujian_id'] ?? ($assessment['skkni_paket_id'] ?? 0));
        $packageName = (string) ($skkni['paket_nama'] ?? ($assessment['skkni_paket_nama'] ?? ''));
        $packageSkkni = [];

        if ($packageId > 0 && !empty($skkniList)) {
            foreach ($skkniList as $row) {
                if ((int) ($row['paket_ujian_id'] ?? 0) === $packageId) {
                    $packageSkkni[] = $row;
                    if ($packageName === '' && !empty($row['paket_nama'])) {
                        $packageName = (string) $row['paket_nama'];
                    }
                }
            }
        }

        $usePackageList = $packageId > 0 && !empty($packageSkkni);
    ?>
    <?php if ($index > 0): ?>
        <div class="page-break"></div>
    <?php endif; ?>
    <div class="passport-wrapper">
        <div class="passport-card">
            <div class="passport-header">
                <div class="passport-logo">
                    <?php if ($logoUrl !== null): ?>
                        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah">
                    <?php endif; ?>
                </div>
                <div class="passport-header-text">
                    <div class="passport-title">Skill Passport Siswa</div>
                    <div class="passport-subtitle"><?= htmlspecialchars($schoolProfile['nama'] ?? 'Sekolah', ENT_QUOTES, 'UTF-8') ?> &middot; Tahun Ajaran <?= htmlspecialchars($schoolProfile['tahun_ajaran_nama'] ?? ($class['tahun_ajaran_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="passport-logo-spacer" aria-hidden="true"></div>
            </div>

            <table class="info-table">
                <tr>
                    <td>Nama</td>
                    <td>
                        : <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                    </td>
                </tr>
                <tr>
                    <td>NISN / NIPD</td>
                    <td>: <?= htmlspecialchars(($student['nisn'] ?? '-') . ' / ' . ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td>Kelas / Jurusan</td>
                    <td>: <?= htmlspecialchars(($class['nama'] ?? '-') . ' / ' . ($class['jurusan_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td>DUDI</td>
                    <td>: <?= htmlspecialchars($dudi['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td>Asesor</td>
                    <td>: <?= htmlspecialchars($assessor['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            </table>

            <div class="detail-box">
                <h4>Standar Kompetensi (SKKNI)</h4>
                <?php if ($usePackageList): ?>
                    <p style="margin:0 0 2mm 0; font-weight:600;">Paket Ujian: <?= htmlspecialchars($packageName !== '' ? $packageName : 'Paket #' . $packageId, ENT_QUOTES, 'UTF-8') ?></p>
                    <ul style="margin:0; padding-left:16px; font-size:10.5pt; line-height:1.5;">
                        <?php foreach ($packageSkkni as $item): ?>
                            <li><?= htmlspecialchars(($item['kode'] ?? '-') . ' - ' . ($item['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin:0 0 2mm 0; font-weight:600;"><?= htmlspecialchars(($skkni['kode'] ?? '-') . ' - ' . ($skkni['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($units)): ?>
                        <ul style="margin:0; padding-left:16px; font-size:10.5pt; line-height:1.5;">
                            <?php foreach ($units as $unit): ?>
                                <li><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="margin:0; color:#64748b;">Unit kompetensi belum diisi.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <table class="score-table">
                <thead>
                    <tr>
                        <th>Komponen</th>
                        <th style="width:28mm;">Nilai</th>
                        <th style="width:35mm;">Predikat</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Uji Teori</td>
                        <td class="text-center"><?= htmlspecialchars($assessment['nilai_teori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td rowspan="3" class="text-center align-middle font-semibold"><?= htmlspecialchars($assessment['predikat'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td rowspan="3"><?= nl2br(htmlspecialchars($assessment['catatan'] ?? 'Lulus kompeten sesuai standar.', ENT_QUOTES, 'UTF-8')) ?></td>
                    </tr>
                    <tr>
                        <td>Uji Praktik</td>
                        <td class="text-center"><?= htmlspecialchars($assessment['nilai_praktik'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td>Nilai Akhir</td>
                        <td class="text-center font-semibold"><?= htmlspecialchars($assessment['nilai_akhir'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top:10mm; display:flex; justify-content:space-between; gap:12mm; font-size:10.5pt;">
                <div style="flex:1; text-align:center;">
                    <p>&nbsp;</p>
                    <p>DUDI / Asesor</p>
                    <div style="height:20mm;"></div>
                    <p class="underline font-semibold"><?= htmlspecialchars($assessor['nama'] ?? '________________', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div style="flex:1; text-align:center;">
                    <p><?= htmlspecialchars($schoolProfile['kabupaten'] ?? '', ENT_QUOTES, 'UTF-8') ?><?= !empty($assessment['tanggal_sertifikat']) ? ', ' . htmlspecialchars($formatDate((string) $assessment['tanggal_sertifikat']), ENT_QUOTES, 'UTF-8') : '' ?></p>
                    <p>Kepala Sekolah</p>
                    <div style="height:20mm;"></div>
                    <p class="underline font-semibold"><?= htmlspecialchars($schoolProfile['kepala_sekolah'] ?? '________________', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
