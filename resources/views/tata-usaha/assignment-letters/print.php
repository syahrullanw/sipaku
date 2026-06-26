<?php
$letter = is_array($letter ?? null) ? $letter : [];
$teachers = is_array($teachers ?? null) ? $teachers : [];
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : null;
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$headmaster = is_array($headmaster ?? null) ? $headmaster : null;
$period = is_array($period ?? null) ? $period : [];
$positionSummary = is_array($positionSummary ?? null) ? $positionSummary : [];

$letterNumber = $letter['number'] ?? '';
$letterSubject = $letter['subject'] ?? '';
$letterPlace = $letter['place'] ?? '';
$letterSignDateFormatted = $letter['sign_date_formatted'] ?? null;
$effectiveStartFormatted = $letter['effective_start_formatted'] ?? null;
$effectiveEndFormatted = $letter['effective_end_formatted'] ?? null;

$menimbang = is_array($letter['menimbang'] ?? null) ? $letter['menimbang'] : [];
$mengingat = is_array($letter['mengingat'] ?? null) ? $letter['mengingat'] : [];
$menetapkan = is_array($letter['menetapkan'] ?? null) ? $letter['menetapkan'] : [];
$tembusan = array_values(array_filter(
    is_array($letter['tembusan'] ?? null) ? $letter['tembusan'] : [],
    static fn ($value): bool => trim((string) $value) !== ''
));

$schoolName = trim((string) ($schoolProfile['nama'] ?? 'Sekolah'));
$schoolCity = trim((string) ($schoolProfile['kabupaten'] ?? $schoolProfile['kecamatan'] ?? ''));
$schoolAddress = trim((string) ($schoolProfile['alamat'] ?? ''));
$schoolContact = trim((string) ($schoolProfile['telepon'] ?? ''));
$schoolEmail = trim((string) ($schoolProfile['email'] ?? ''));

$signature = is_array($signature ?? null) ? $signature : [];
$signatureStatus = (string) ($signature['status'] ?? 'inactive');
$signatureMessage = $signature['status_message'] ?? ($signature['available'] ?? false ? '' : ($signature['reason'] ?? ''));
$signatureVerificationUrl = $signature['verification_url'] ?? null;
$signatureApprovedAt = $signature['approved_at_formatted'] ?? null;

$ordinalLabels = ['PERTAMA', 'KEDUA', 'KETIGA', 'KEEMPAT', 'KELIMA', 'KEENAM', 'KETUJUH', 'KEDELAPAN', 'KESEMBILAN', 'KESEPULUH'];

/**
 * @return string
 */
$makeOrdinalLabel = static function (int $index) use ($ordinalLabels): string {
    if (isset($ordinalLabels[$index])) {
        return $ordinalLabels[$index];
    }

    return 'KE-' . strtoupper((string) ($index + 1));
};

$periodLabel = $period['label'] ?? ($letter['period_label'] ?? null);

$lampiranRows = [];
$rowNumber = 1;
$totalHours = 0;

foreach ($teachers as $teacher) {
    $teacherName = trim((string) ($teacher['name'] ?? 'Guru'));
   $teacherNip = trim((string) ($teacher['nip'] ?? ''));
   $assignments = is_array($teacher['assignments'] ?? null) ? $teacher['assignments'] : [];

    foreach ($assignments as $assignment) {
        $subjectName = trim((string) ($assignment['subject_name'] ?? 'Mata Pelajaran'));
        $subjectCode = trim((string) ($assignment['subject_code'] ?? ''));
        $classLabels = is_array($assignment['class_labels'] ?? null) ? $assignment['class_labels'] : [];
        $scheduleLabels = is_array($assignment['schedule_labels'] ?? null) ? $assignment['schedule_labels'] : [];
        $hours = (int) ($assignment['total_hours'] ?? 0);

        $lampiranRows[] = [
            'no' => $rowNumber++,
            'teacher_name' => $teacherName,
            'teacher_nip' => $teacherNip,
            'subject' => $subjectName !== '' ? $subjectName : 'Mata Pelajaran',
            'subject_code' => $subjectCode,
            'classes' => $classLabels,
            'schedules' => $scheduleLabels,
            'hours' => $hours,
        ];

        $totalHours += $hours;
    }
}
?>

<div class="text-center">
    <p class="fw-semibold uppercase">Surat Keputusan</p>
    <p class="fw-semibold uppercase">Kepala <?= htmlspecialchars(strtoupper($schoolName), ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($schoolCity !== ''): ?>
        <p class="uppercase"><?= htmlspecialchars(strtoupper($schoolCity), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($letterNumber !== ''): ?>
        <p class="mt-2">Nomor: <?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p class="mt-2 fw-semibold">Tentang</p>
    <p class="uppercase"><?= htmlspecialchars(strtoupper($letterSubject !== '' ? $letterSubject : 'Penugasan Guru Mata Pelajaran'), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="mt-4">
    <p class="fw-semibold">Menimbang :</p>
    <ol style="margin-top: 8px; padding-left: 24px; list-style: none;">
        <?php foreach ($menimbang as $index => $item): ?>
            <?php
                $prefix = chr(97 + $index);
                if ($index >= 26) {
                    $prefix = (string) ($index + 1);
                }
            ?>
            <li style="display: flex; gap: 8px;">
                <span style="text-transform: lowercase;"><?= htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') ?>.</span>
                <span><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="mt-4">
    <p class="fw-semibold">Mengingat :</p>
    <ol style="margin-top: 8px; padding-left: 24px;">
        <?php foreach ($mengingat as $index => $item): ?>
            <li style="margin-bottom: 4px;">
                <?= $index + 1 ?>. <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<div class="mt-4">
    <p class="fw-semibold uppercase">Memutuskan :</p>
    <p class="mt-2 fw-semibold">Menetapkan :</p>
    <ol style="margin-top: 8px; padding-left: 0; list-style: none;">
        <?php foreach ($menetapkan as $index => $item): ?>
            <li style="margin-bottom: 6px;">
                <span class="fw-semibold"><?= htmlspecialchars($makeOrdinalLabel($index), ENT_QUOTES, 'UTF-8') ?></span>
                <span> : <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<?php if (!empty($tembusan)): ?>
    <div class="mt-4">
        <p class="fw-semibold">Tembusan :</p>
        <ol style="margin-top: 8px; padding-left: 24px;">
            <?php foreach ($tembusan as $index => $item): ?>
                <li><?= $index + 1 ?>. <?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
<?php endif; ?>

<?php $signatureQr = $letter['signature_qr'] ?? ''; ?>

<div class="mt-6" style="display: flex; justify-content: flex-end;">
    <div style="width: 320px; text-align: center;">
        <?php if ($letterPlace !== ''): ?>
            <p style="margin: 0;"><?= htmlspecialchars('Ditetapkan di ' . $letterPlace, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($letterSignDateFormatted !== null): ?>
            <p style="margin: 4px 0 8px;"><?= htmlspecialchars('Pada tanggal ' . $letterSignDateFormatted, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="fw-semibold" style="margin: 0 0 12px;"><?= htmlspecialchars($headmaster['position'] ?? 'Kepala Sekolah', ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (is_string($signatureQr) && $signatureQr !== ''): ?>
            <div
                data-qr-value="<?= htmlspecialchars($signatureQr, ENT_QUOTES, 'UTF-8') ?>"
                data-qr-size="180"
                data-qr-rendered="0"
                style="width: 180px; height: 180px; margin: 0 auto 12px; border: 1px solid #cbd5f5; padding: 10px; box-sizing: border-box; background-color: #ffffff;"
            ></div>
            <p style="margin: 0 0 16px; font-size: 11pt;">Ditandatangani secara elektronik melalui kode QR</p>
        <?php else: ?>
            <div style="height: 196px;"></div>
        <?php endif; ?>
        <p class="fw-semibold underline" style="margin: 0;"><?= htmlspecialchars($headmaster['name'] ?? '________________', ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!empty($headmaster['nip'])): ?>
            <p style="margin: 4px 0 0;">NIP. <?= htmlspecialchars($headmaster['nip'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($signatureStatus === 'approved' && $signatureApprovedAt !== null): ?>
            <p style="margin: 12px 0 0; font-size: 10pt; color: #059669;">Disetujui pada <?= htmlspecialchars($signatureApprovedAt, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($signatureVerificationUrl): ?>
                <p style="margin: 4px 0 0; font-size: 9pt; color: #0f172a;">Verifikasi: <?= htmlspecialchars($signatureVerificationUrl, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        <?php elseif ($signatureStatus !== 'approved'): ?>
            <p style="margin: 12px 0 0; font-size: 10pt; color: #b91c1c;">
                <?= htmlspecialchars($signatureMessage !== '' ? $signatureMessage : 'TTD digital belum disetujui.', ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($schoolAddress) || !empty($schoolContact) || !empty($schoolEmail)): ?>
    <div class="mt-6 text-sm">
        <p class="fw-semibold">Profil Sekolah</p>
        <p><?= htmlspecialchars($schoolAddress, ENT_QUOTES, 'UTF-8') ?></p>
        <p>
            <?php if ($schoolContact !== ''): ?>
                Telp. <?= htmlspecialchars($schoolContact, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <?php if ($schoolContact !== '' && $schoolEmail !== ''): ?>
                |
            <?php endif; ?>
            <?php if ($schoolEmail !== ''): ?>
                Email: <?= htmlspecialchars($schoolEmail, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<div style="page-break-before: always;"></div>

<div>
    <p class="fw-semibold">Lampiran A - Rincian Penugasan Mengajar</p>
    <?php if ($letterNumber !== ''): ?>
        <p>Nomor: <?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($periodLabel)): ?>
        <p>Periode: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($effectiveStartFormatted !== null && $effectiveEndFormatted !== null): ?>
        <p>Masa berlaku: <?= htmlspecialchars($effectiveStartFormatted, ENT_QUOTES, 'UTF-8') ?> s.d. <?= htmlspecialchars($effectiveEndFormatted, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</div>

<table class="mt-4">
    <thead>
        <tr>
            <th style="width: 40px;">No</th>
            <th style="width: 180px;">Nama Guru</th>
            <th>Mata Pelajaran</th>
            <th style="width: 180px;">Kelas</th>
            <th style="width: 200px;">Jadwal</th>
            <th style="width: 80px;">Jam</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($lampiranRows)): ?>
            <tr>
                <td colspan="6" class="text-center">Belum ada data penugasan.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($lampiranRows as $row): ?>
                <tr>
                    <td class="text-center"><?= (int) $row['no'] ?></td>
                    <td>
                        <span class="fw-semibold"><?= htmlspecialchars($row['teacher_name'], ENT_QUOTES, 'UTF-8') ?></span><br />
                        <?php if ($row['teacher_nip'] !== ''): ?>
                            <span style="font-size: 10pt;">NIP. <?= htmlspecialchars($row['teacher_nip'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-semibold"><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($row['subject_code'] !== ''): ?>
                            <br /><span style="font-size: 10pt;">Kode: <?= htmlspecialchars($row['subject_code'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['classes'])): ?>
                            <?php foreach ($row['classes'] as $label): ?>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><br />
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span>Belum diatur</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['schedules'])): ?>
                            <?php foreach ($row['schedules'] as $label): ?>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><br />
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span>Belum ada jadwal</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((int) $row['hours']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($lampiranRows)): ?>
    <p class="mt-3">Total jam mengajar per minggu: <?= number_format($totalHours) ?></p>
<?php endif; ?>

<?php if (!empty($positionSummary)): ?>
    <div style="page-break-before: always;"></div>
    <div>
        <p class="fw-semibold">Lampiran B - Daftar Jabatan Akademik Guru</p>
        <?php if ($letterNumber !== ''): ?>
            <p>Nomor SK: <?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (!empty($periodLabel)): ?>
            <p>Periode: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <?php foreach ($positionSummary as $positionName => $rows): ?>
        <p class="fw-semibold mt-4" style="margin-bottom: 8px;"><?= htmlspecialchars((string) $positionName, ENT_QUOTES, 'UTF-8') ?></p>
        <table style="margin-top: 4px;">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th style="width: 220px;">Nama Guru</th>
                    <th>Periode Tugas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                        $teacherName = (string) ($row['teacher_name'] ?? 'Guru');
                        $teacherNip = trim((string) ($row['teacher_nip'] ?? ''));
                        $startLabel = $row['start_date_formatted'] ?? null;
                        $endLabel = $row['end_date_formatted'] ?? null;
                        $periodValue = 'Tidak ditentukan';

                        if ($startLabel !== null && $endLabel !== null) {
                            $periodValue = $startLabel . ' s.d. ' . $endLabel;
                        } elseif ($startLabel !== null) {
                            $periodValue = 'Mulai ' . $startLabel;
                        } elseif ($endLabel !== null) {
                            $periodValue = 'Hingga ' . $endLabel;
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?= $index + 1 ?></td>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($teacherNip !== ''): ?>
                                <br /><span style="font-size: 10pt;">NIP. <?= htmlspecialchars($teacherNip, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($periodValue, ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($row['note'])): ?>
                                <br /><span style="font-size: 10pt;">Keterangan: <?= htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
