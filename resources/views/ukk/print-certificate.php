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
    $certificatePrintSide = $certificatePrintSide ?? 'front';
    $printFront = $certificatePrintSide !== 'back';
    $printBack = $certificatePrintSide === 'back';

    $logoPath = $schoolProfile['logo_sekolah'] ?? ($schoolProfile['lambang_negara'] ?? null);
    $logoUrl = $logoPath ? asset($logoPath) : null;

    $formatDate = static function (?string $value): string {
        return \App\Models\UkkStudentAssessment::formatIndonesianDate($value);
    };

    $predicateMap = [
        'Sangat Kompeten' => 'Very Competent',
        'Kompeten' => 'Competent',
        'Belum Kompeten' => 'Not Yet Competent',
        'Sangat Baik' => 'Very Good',
        'Baik' => 'Good',
        'Cukup' => 'Fair',
        'Perlu Bimbingan' => 'Needs Guidance',
        'A' => 'Excellent',
        'B' => 'Good',
        'C' => 'Fair',
        'D' => 'Poor',
    ];
?>
<style>
    .ukk-certificate {
        width: 100%;
        color: #111827;
        font-size: 11pt;
    }
    .certificate-page {
        position: relative;
        overflow: hidden;
        min-height: 245mm;
        padding: 10mm 12mm 12mm 28mm;
    }
    .certificate-page.units-page {
        padding: 12mm;
    }
    .certificate-accent {
        position: absolute;
        inset: 0 auto 0 0;
        width: 45mm;
        height: 100%;
        z-index: 0;
    }
    .certificate-content {
        position: relative;
        z-index: 1;
        text-align: center;
    }
    .certificate-top {
        position: relative;
        display: flex;
        justify-content: center;
        margin: 2mm 0 4mm;
    }
    .certificate-line {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background: #cbd5e1;
    }
    .certificate-logo {
        position: relative;
        background: #ffffff;
        padding: 0 6mm;
    }
    .certificate-logo img {
        width: 24mm;
        height: 24mm;
        object-fit: contain;
        display: block;
    }
    .certificate-title {
        font-weight: 700;
        font-size: 15pt;
        letter-spacing: 0.5px;
    }
    .certificate-subtitle {
        font-style: italic;
        color: #2563eb;
        font-size: 14pt;
        font-weight: 600;
        margin-top: 1mm;
    }
    .certificate-number {
        margin-top: 3mm;
        font-size: 11pt;
    }
    .cert-line {
        margin: 3mm 0;
        font-size: 11pt;
        line-height: 1.4;
    }
    .cert-line .en {
        display: block;
        font-style: italic;
        font-size: 10pt;
        color: #475569;
    }
    .cert-name {
        margin-top: 6mm;
        font-size: 16pt;
        font-weight: 700;
        text-transform: uppercase;
    }
    .cert-id {
        margin-top: 2mm;
        font-size: 11pt;
    }
    .cert-emphasis {
        margin: 2mm 0 4mm;
        font-size: 12pt;
        font-weight: 700;
    }
    .cert-predicate {
        margin-top: 2mm;
        font-size: 13pt;
        font-weight: 700;
    }
    .cert-predicate-en {
        font-size: 12pt;
        font-weight: 700;
    }
    .cert-validity {
        margin-top: 6mm;
    }
    .cert-date {
        margin-top: 4mm;
        font-size: 11pt;
    }
    .certificate-signatures {
        display: flex;
        justify-content: space-between;
        gap: 18mm;
        margin-top: 12mm;
        font-size: 10.5pt;
        text-align: center;
    }
    .signature-block {
        flex: 1;
    }
    .signature-title {
        font-weight: 600;
    }
    .signature-sub {
        font-style: italic;
        color: #475569;
        font-size: 9.5pt;
    }
    .signature-space {
        height: 24mm;
    }
    .signature-name {
        font-weight: 700;
    }
    .signature-role {
        font-size: 10pt;
    }
    .signature-role-en {
        font-size: 9.5pt;
        font-style: italic;
        color: #475569;
    }
    .signature-instansi {
        font-size: 9.5pt;
        font-style: italic;
        color: #475569;
    }
    .units-header {
        text-align: center;
        margin-bottom: 6mm;
    }
    .units-title {
        font-weight: 700;
        font-size: 14pt;
    }
    .units-subtitle {
        font-style: italic;
        font-size: 11pt;
        color: #475569;
    }
    .units-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5pt;
        margin-top: 3mm;
    }
    .units-table th,
    .units-table td {
        border: 1px solid #111827;
        padding: 4px 6px;
    }
    .units-table th {
        background: #e5e7eb;
        text-align: center;
    }
    .units-code {
        width: 38mm;
        font-weight: 600;
        text-align: center;
    }
    .units-note {
        color: #475569;
        font-style: italic;
        text-align: center;
    }
    .units-signatures {
        display: flex;
        justify-content: space-between;
        gap: 20mm;
        margin-top: 10mm;
        font-size: 10.5pt;
    }
    .units-signatures .signature-block {
        text-align: left;
    }
    .page-break {
        page-break-before: always;
    }
</style>

<?php foreach ($students as $index => $student): ?>
    <?php if ($printBack && $index > 0): ?>
        <?php break; ?>
    <?php endif; ?>
    <?php
        $sid = (int) ($student['id'] ?? 0);
        $assessment = $assessments[$sid] ?? [];
        $skkni = isset($assessment['skkni_id']) ? ($skkniMap[(int) $assessment['skkni_id']] ?? null) : null;
        $dudi = isset($assessment['dudi_id']) ? ($dudiMap[(int) $assessment['dudi_id']] ?? null) : null;
        $assessor = isset($assessment['asesor_id']) ? ($assessorMap[(int) $assessment['asesor_id']] ?? null) : null;
        $issueDate = $assessment['tanggal_sertifikat'] ?? '';
        $formattedDate = $formatDate($issueDate);

        $certificateNumber = trim((string) ($assessment['nomor_sertifikat'] ?? ''));
        if ($certificateNumber === '') {
            $certificateNumber = '-';
        }

        $studentName = $student['nama'] ?? '-';
        $studentNisn = $student['nisn'] ?? '-';
        $competencyName = $class['jurusan_nama'] ?? ($skkni['judul'] ?? '-');
        $assignmentTitle = $skkni['judul'] ?? '-';
        $predicateLabel = trim((string) ($assessment['predikat'] ?? ''));
        if ($predicateLabel === '') {
            $predicateLabel = '-';
        }
        $predicateEn = $predicateLabel !== '-' ? ($predicateMap[$predicateLabel] ?? $predicateLabel) : '-';

        $schoolName = $schoolProfile['nama'] ?? 'Sekolah';
        $location = $schoolProfile['kabupaten'] ?? '';
        $principalName = $schoolProfile['kepala_sekolah'] ?? '________________';
        $assessorName = $assessor['nama'] ?? '________________';
        $dudiName = $dudi['nama'] ?? '';

        $units = [];
        $packageId = (int) ($skkni['paket_ujian_id'] ?? 0);
        if ($packageId <= 0 && isset($assessment['skkni_paket_id'])) {
            $packageId = (int) $assessment['skkni_paket_id'];
        }

        if ($packageId > 0 && !empty($skkniList)) {
            foreach ($skkniList as $row) {
                if ((int) ($row['paket_ujian_id'] ?? 0) === $packageId) {
                    $units[] = [
                        'code' => $row['kode'] ?? '-',
                        'title' => $row['judul'] ?? '-',
                    ];
                }
            }
        }

        if (empty($units) && !empty($skkni['unit_kompetensi'])) {
            $lines = preg_split('/\\r\\n|\\r|\\n/', (string) $skkni['unit_kompetensi']);
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                $code = '-';
                $title = $line;
                $parts = null;

                foreach (["\t", "|", ";"] as $delimiter) {
                    if (strpos($line, $delimiter) !== false) {
                        $split = array_map('trim', explode($delimiter, $line, 2));
                        if (($split[0] ?? '') !== '' && ($split[1] ?? '') !== '') {
                            $parts = $split;
                            break;
                        }
                    }
                }

                if ($parts === null && strpos($line, ' - ') !== false) {
                    $split = array_map('trim', explode(' - ', $line, 2));
                    if (($split[0] ?? '') !== '' && ($split[1] ?? '') !== '') {
                        $parts = $split;
                    }
                }

                if ($parts !== null) {
                    $code = $parts[0];
                    $title = $parts[1];
                } elseif (preg_match('/^([A-Z0-9]+(?:\\.[A-Z0-9]+)+)\\s+(.*)$/', $line, $matches)) {
                    $code = $matches[1];
                    $title = trim((string) $matches[2]);
                    if ($title === '') {
                        $title = $line;
                    }
                }

                $units[] = [
                    'code' => $code,
                    'title' => $title,
                ];
            }
        }

        $skkniCodeFallback = trim((string) ($skkni['kode'] ?? ($assessment['skkni_kode'] ?? '')));
        $skkniTitleFallback = trim((string) ($skkni['judul'] ?? ($assessment['skkni_judul'] ?? '')));

        if (empty($units) && ($skkniCodeFallback !== '' || $skkniTitleFallback !== '')) {
            $units[] = [
                'code' => $skkniCodeFallback !== '' ? $skkniCodeFallback : '-',
                'title' => $skkniTitleFallback !== '' ? $skkniTitleFallback : '-',
            ];
        }

        $internalAssessorName = trim((string) ($assessment['internal_assessor_name'] ?? ''));
        if ($internalAssessorName === '') {
            $internalAssessorName = trim((string) ($assessment['internal_assessor_teacher_name'] ?? ''));
        }

        $internalDisplayName = $internalAssessorName !== '' ? $internalAssessorName : '________________';
        $internalInstitution = $schoolName;

        $externalDisplayName = trim((string) $assessorName);
        if ($externalDisplayName === '') {
            $externalDisplayName = '________________';
        }
        $externalInstitution = $dudiName;

        $locationLabel = $location !== '' ? $location . ', ' : '';
    ?>
    <?php if ($printFront && $index > 0): ?>
        <div class="page-break"></div>
    <?php endif; ?>
    <div class="ukk-certificate">
        <?php if ($printFront): ?>
            <div class="certificate-page">
                <svg class="certificate-accent" viewBox="0 0 120 1000" preserveAspectRatio="none" aria-hidden="true">
                    <polygon points="0,0 90,220 0,440" fill="#36b6e5" opacity="0.95"></polygon>
                    <polygon points="0,200 95,420 0,640" fill="#f3a13b" opacity="0.9"></polygon>
                    <polygon points="0,420 100,640 0,860" fill="#f27c2b" opacity="0.9"></polygon>
                    <polygon points="0,640 115,880 0,1120" fill="#f05a64" opacity="0.9"></polygon>
                </svg>
                <div class="certificate-content">
                    <div class="certificate-top">
                        <div class="certificate-line"></div>
                        <div class="certificate-logo">
                            <?php if ($logoUrl !== null): ?>
                                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="certificate-title">SERTIFIKAT KOMPETENSI</div>
                    <div class="certificate-subtitle">COMPETENCY CERTIFICATE</div>
                    <div class="certificate-number">Nomor : <?= htmlspecialchars($certificateNumber, ENT_QUOTES, 'UTF-8') ?></div>

                    <p class="cert-line">
                        Dengan ini menyatakan bahwa,
                        <span class="en">Hereby declare that</span>
                    </p>
                    <div class="cert-name"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="cert-id">NISN: <?= htmlspecialchars($studentNisn, ENT_QUOTES, 'UTF-8') ?></div>

                    <p class="cert-line">
                        Telah mengikuti Uji Kompetensi Keahlian
                        <span class="en">Has taken the skills competency test</span>
                    </p>
                    <p class="cert-line">
                        pada Kompetensi / Konsentrasi Keahlian
                        <span class="en">on Competency of</span>
                    </p>
                    <div class="cert-emphasis"><?= htmlspecialchars($competencyName, ENT_QUOTES, 'UTF-8') ?></div>

                    <p class="cert-line">
                        pada Judul Penugasan
                        <span class="en">on Assignment</span>
                    </p>
                    <div class="cert-emphasis"><?= htmlspecialchars($assignmentTitle, ENT_QUOTES, 'UTF-8') ?></div>

                    <p class="cert-line">
                        dengan predikat
                        <span class="en">with achievement level</span>
                    </p>
                    <div class="cert-predicate"><?= htmlspecialchars($predicateLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="cert-predicate-en"><?= htmlspecialchars($predicateEn, ENT_QUOTES, 'UTF-8') ?></div>

                    <p class="cert-line cert-validity">
                        Sertifikat ini berlaku untuk : 3 (tiga) Tahun
                        <span class="en">This certificate is valid for : 3 (three) Years</span>
                    </p>
                    <div class="cert-date"><?= htmlspecialchars($locationLabel . $formattedDate, ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="certificate-signatures">
                        <div class="signature-block">
                            <div class="signature-title">Atas nama <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="signature-sub">On behalf of <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="signature-space"></div>
                            <div class="signature-name"><?= htmlspecialchars($principalName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="signature-role">Kepala Sekolah</div>
                            <div class="signature-role-en">School Principal</div>
                        </div>
                        <div class="signature-block">
                            <div class="signature-title"><?= htmlspecialchars($dudiName !== '' ? $dudiName : 'DUDI', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="signature-sub">&nbsp;</div>
                            <div class="signature-space"></div>
                            <div class="signature-name"><?= htmlspecialchars($assessorName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="signature-role">Penguji Eksternal</div>
                            <div class="signature-role-en">External Assessor</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($printBack): ?>
            <div class="certificate-page units-page">
                <div class="units-header">
                    <div class="units-title">DAFTAR UNIT KOMPETENSI</div>
                    <div class="units-subtitle">List of Competency Unit</div>
                </div>

                <table class="units-table">
                    <thead>
                        <tr>
                            <th class="units-code">
                                Kode Unit<br>
                                <span class="units-subtitle">Unit Code</span>
                            </th>
                            <th>
                                Judul Unit<br>
                                <span class="units-subtitle">Unit Title</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($units)): ?>
                            <?php foreach ($units as $unit): ?>
                                <tr>
                                    <td class="units-code"><?= htmlspecialchars($unit['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($unit['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="units-note">Unit kompetensi belum diisi.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="units-signatures">
                    <div class="signature-block">
                        <div class="signature-role">Penguji Internal</div>
                        <div class="signature-role-en">Internal Assessor</div>
                        <div class="signature-space"></div>
                        <div class="signature-name"><?= htmlspecialchars($internalDisplayName, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($internalInstitution !== ''): ?>
                            <div class="signature-instansi">(<?= htmlspecialchars($internalInstitution, ENT_QUOTES, 'UTF-8') ?>)</div>
                        <?php endif; ?>
                    </div>
                    <div class="signature-block">
                        <div class="signature-role">Penguji Eksternal</div>
                        <div class="signature-role-en">External Assessor</div>
                        <div class="signature-space"></div>
                        <div class="signature-name"><?= htmlspecialchars($externalDisplayName, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($externalInstitution !== ''): ?>
                            <div class="signature-instansi">(<?= htmlspecialchars($externalInstitution, ENT_QUOTES, 'UTF-8') ?>)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
