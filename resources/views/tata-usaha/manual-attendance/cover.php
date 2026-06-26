<?php
$printGroups = is_array($printGroups ?? null) ? $printGroups : [];
$selectedClass = is_array($selectedClass ?? null) ? $selectedClass : [];
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : [];
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : [];
$attendanceType = (string) ($attendanceType ?? 'kelas');
$attendanceTypeLabel = (string) ($attendanceTypeLabel ?? 'Presensi Kelas');
$schoolYearName = trim((string) ($schoolYear['nama'] ?? ($selectedClass['tahun_ajaran_nama'] ?? '-')));
$schoolName = trim((string) ($schoolProfile['nama'] ?? 'Sekolah'));
$logoPath = trim((string) ($schoolProfile['logo_sekolah'] ?? ''));
$logoUrl = $logoPath !== '' ? asset($logoPath) : null;
$npsn = trim((string) ($schoolProfile['npsn'] ?? ''));
$nss = trim((string) ($schoolProfile['nss'] ?? ''));
$addressParts = array_values(array_filter([
    $schoolProfile['alamat'] ?? null,
    $schoolProfile['desa'] ?? null,
    $schoolProfile['kecamatan'] ?? null,
    $schoolProfile['kabupaten'] ?? null,
    $schoolProfile['provinsi'] ?? null,
    $schoolProfile['kode_pos'] ?? null,
], static fn ($value): bool => is_string($value) && trim($value) !== ''));
$schoolAddress = implode(', ', array_map(static fn ($value): string => trim((string) $value), $addressParts));
$contactParts = array_values(array_filter([
    $schoolProfile['telepon'] ?? null,
    $schoolProfile['email'] ?? null,
    $schoolProfile['website'] ?? null,
], static fn ($value): bool => is_string($value) && trim($value) !== ''));
$schoolContact = implode(' | ', array_map(static fn ($value): string => trim((string) $value), $contactParts));
$cityLabel = trim((string) ($schoolProfile['kabupaten'] ?? $schoolProfile['kecamatan'] ?? ''));

if (empty($printGroups)) {
    $printGroups[] = [
        'attendance_type' => $attendanceType,
        'class' => $selectedClass,
        'class_label' => trim((string) ($classLabel ?? '-')),
        'subject_assignment' => null,
    ];
}
?>

<style>
    @page {
        size: 215mm 330mm;
        margin: 14mm;
    }

    .print-content {
        max-width: 187mm !important;
    }

    .attendance-cover {
        border: 2px solid #0f172a;
        box-sizing: border-box;
        color: #0f172a;
        display: flex;
        flex-direction: column;
        font-family: Arial, Helvetica, sans-serif;
        min-height: 302mm;
        padding: 15mm 16mm;
        position: relative;
        text-align: center;
    }

    .attendance-cover.cover-break {
        page-break-before: always;
    }

    .attendance-cover::before,
    .attendance-cover::after {
        content: "";
        pointer-events: none;
        position: absolute;
    }

    .attendance-cover::before {
        border: 1px solid #334155;
        inset: 5mm;
    }

    .attendance-cover::after {
        border-bottom: 1px solid #64748b;
        border-top: 1px solid #64748b;
        inset: 9mm 12mm;
    }

    .attendance-cover .corner {
        border-color: #0f172a;
        border-style: solid;
        height: 18mm;
        position: absolute;
        width: 18mm;
        z-index: 1;
    }

    .attendance-cover .corner-top-left {
        border-width: 2px 0 0 2px;
        left: 8mm;
        top: 8mm;
    }

    .attendance-cover .corner-top-right {
        border-width: 2px 2px 0 0;
        right: 8mm;
        top: 8mm;
    }

    .attendance-cover .corner-bottom-left {
        border-width: 0 0 2px 2px;
        bottom: 8mm;
        left: 8mm;
    }

    .attendance-cover .corner-bottom-right {
        border-width: 0 2px 2px 0;
        bottom: 8mm;
        right: 8mm;
    }

    .attendance-cover .cover-section {
        position: relative;
        z-index: 2;
    }

    .attendance-cover .school-logo {
        height: 34mm;
        margin: 0 auto 10mm;
        object-fit: contain;
        width: 34mm;
    }

    .attendance-cover .school-name {
        font-size: 18pt;
        font-weight: 700;
        line-height: 1.2;
        margin: 0;
        text-transform: uppercase;
    }

    .attendance-cover .school-meta {
        font-size: 10pt;
        line-height: 1.45;
        margin: 4mm auto 0;
        max-width: 145mm;
    }

    .attendance-cover .divider {
        border-top: 2px solid #0f172a;
        margin: 12mm auto 18mm;
        width: 120mm;
    }

    .attendance-cover .cover-title {
        font-size: 28pt;
        font-weight: 700;
        line-height: 1.15;
        margin: 0;
        text-transform: uppercase;
    }

    .attendance-cover .cover-subtitle {
        font-size: 18pt;
        font-weight: 700;
        margin: 5mm 0 0;
        text-transform: uppercase;
    }

    .attendance-cover .detail-table {
        border-collapse: collapse;
        font-size: 14pt;
        margin: 22mm auto 0;
        text-align: left;
        width: 132mm;
    }

    .attendance-cover .detail-table td {
        border: 0;
        padding: 3mm 0;
        vertical-align: top;
    }

    .attendance-cover .detail-label {
        font-weight: 700;
        width: 42mm;
    }

    .attendance-cover .detail-separator {
        width: 6mm;
    }

    .attendance-cover .detail-value {
        border-bottom: 1px solid #0f172a !important;
        font-weight: 700;
    }

    .attendance-cover .cover-spacer {
        flex: 1;
    }

    .attendance-cover .cover-footer {
        font-size: 11pt;
        line-height: 1.5;
        margin-top: 18mm;
        position: relative;
        z-index: 2;
    }
</style>

<?php foreach ($printGroups as $groupIndex => $group): ?>
    <?php
        $groupClass = is_array($group['class'] ?? null) ? $group['class'] : [];
        $groupClassSections = is_array($group['class_sections'] ?? null) ? $group['class_sections'] : [];
        $groupAssignment = is_array($group['subject_assignment'] ?? null) ? $group['subject_assignment'] : null;
        $groupType = (string) ($group['attendance_type'] ?? $attendanceType);
        $classLabel = trim((string) ($group['class_label'] ?? '-'));
        $groupSchoolYearName = trim((string) ($schoolYear['nama'] ?? ($groupClass['tahun_ajaran_nama'] ?? $schoolYearName)));
        $subjectLabel = trim((string) ($groupAssignment['subject_label'] ?? ''));
        $teacherLabel = trim((string) ($groupAssignment['teacher_label'] ?? ($groupAssignment['guru_nama'] ?? '')));
        $subtitle = $groupType === 'mapel' ? 'Presensi Mapel' : 'Presensi Kelas';
        $classSectionLabels = [];
        foreach ($groupClassSections as $section) {
            $sectionLabel = trim((string) ($section['class_short_label'] ?? $section['class_label'] ?? ''));
            if ($sectionLabel !== '') {
                $classSectionLabels[] = $sectionLabel;
            }
        }
        $classSectionText = implode(', ', $classSectionLabels);
    ?>
    <div class="attendance-cover <?= $groupIndex > 0 ? 'cover-break' : '' ?>">
        <span class="corner corner-top-left"></span>
        <span class="corner corner-top-right"></span>
        <span class="corner corner-bottom-left"></span>
        <span class="corner corner-bottom-right"></span>

        <div class="cover-section">
            <?php if ($logoUrl !== null): ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah" class="school-logo" />
            <?php endif; ?>

            <h1 class="school-name"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></h1>

            <div class="school-meta">
                <?php if ($npsn !== '' || $nss !== ''): ?>
                    <div>
                        <?php if ($npsn !== ''): ?>
                            NPSN: <?= htmlspecialchars($npsn, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        <?php if ($npsn !== '' && $nss !== ''): ?>
                            |
                        <?php endif; ?>
                        <?php if ($nss !== ''): ?>
                            NSS: <?= htmlspecialchars($nss, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($schoolAddress !== ''): ?>
                    <div><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($schoolContact !== ''): ?>
                    <div><?= htmlspecialchars($schoolContact, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cover-section divider"></div>

        <div class="cover-section">
            <p class="cover-title">Buku Presensi Siswa</p>
            <p class="cover-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>

            <table class="detail-table">
                <tr>
                    <td class="detail-label">Kelas</td>
                    <td class="detail-separator">:</td>
                    <td class="detail-value"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td class="detail-label">Tahun Ajaran</td>
                    <td class="detail-separator">:</td>
                    <td class="detail-value"><?= htmlspecialchars($groupSchoolYearName, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php if ($groupType === 'kelas' && count($classSectionLabels) > 1): ?>
                    <tr>
                        <td class="detail-label">Rombel</td>
                        <td class="detail-separator">:</td>
                        <td class="detail-value"><?= htmlspecialchars($classSectionText, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($groupType === 'mapel'): ?>
                    <tr>
                        <td class="detail-label">Mapel</td>
                        <td class="detail-separator">:</td>
                        <td class="detail-value"><?= htmlspecialchars($subjectLabel !== '' ? $subjectLabel : '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td class="detail-label">Guru</td>
                        <td class="detail-separator">:</td>
                        <td class="detail-value"><?= htmlspecialchars($teacherLabel !== '' ? $teacherLabel : '-', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="cover-spacer"></div>

        <div class="cover-footer">
            <?php if ($cityLabel !== ''): ?>
                <div><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div><?= htmlspecialchars($groupSchoolYearName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
<?php endforeach; ?>
