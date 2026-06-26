<?php
$letters = is_array($letters ?? null) ? $letters : [];
$statusFilter = in_array($statusFilter ?? 'pending', ['pending', 'approved', 'revoked', 'all'], true) ? $statusFilter : 'pending';
$yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
$selectedYearId = (int) ($selectedYearId ?? 0);
$currentYear = is_array($currentYear ?? null) ? $currentYear : null;
$activeYearName = $currentYear['nama'] ?? '-';
$activeSemester = isset($currentYear['semester_aktif']) ? (int) $currentYear['semester_aktif'] : 1;
$semesterLabel = $activeSemester === 2 ? 'Semester Genap' : 'Semester Ganjil';
$digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);

$statusLabels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'revoked' => 'Dicabut',
    'all' => 'Semua',
];

$documentTypeLabels = [
    'assignment_letter' => 'SK Penugasan Guru',
];

$summary = is_array($statusSummary ?? null) ? $statusSummary : [];
$statusCounts = [
    'pending' => (int) ($summary['pending'] ?? 0),
    'approved' => (int) ($summary['approved'] ?? 0),
    'revoked' => (int) ($summary['revoked'] ?? 0),
];

$statusBadgeClasses = [
    'pending' => 'border-amber-200 bg-amber-50 text-amber-600',
    'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-600',
    'revoked' => 'border-rose-200 bg-rose-50 text-rose-600',
];

$outgoingLetters = is_array($outgoingLetters ?? null) ? $outgoingLetters : [];
$incomingLetters = is_array($incomingLetters ?? null) ? $incomingLetters : [];
$outgoingLetterTypes = is_array($outgoingLetterTypes ?? null) ? $outgoingLetterTypes : [];
$commonLetterTemplates = is_array($commonLetterTemplates ?? null) ? $commonLetterTemplates : [];
$commonLetterTemplatesJson = json_encode(
    $commonLetterTemplates,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '{}';
$defaultUnitCode = isset($defaultUnitCode) && is_string($defaultUnitCode) ? trim($defaultUnitCode) : '';
$nextOutgoingSequence = max(1, (int) ($nextOutgoingSequence ?? 1));
$nextIncomingAgenda = max(1, (int) ($nextIncomingAgenda ?? 1));
$nextOutgoingSequenceLabel = str_pad((string) $nextOutgoingSequence, 3, '0', STR_PAD_LEFT);
$nextIncomingAgendaLabel = str_pad((string) $nextIncomingAgenda, 3, '0', STR_PAD_LEFT);
$outgoingLetterOptions = [];
$defaultOutgoingLetterType = null;
$letterheadPath = isset($letterheadPath) && is_string($letterheadPath) ? trim($letterheadPath) : '';
$letterheadUrl = isset($letterheadUrl) && is_string($letterheadUrl) ? $letterheadUrl : null;
$schoolProfileExists = isset($schoolProfileExists) ? (bool) $schoolProfileExists : false;
$headmasterOption = is_array($headmasterOption ?? null) ? $headmasterOption : null;
$signerDefault = 'Kepala Sekolah';
$editOutgoingLetter = is_array($editOutgoingLetter ?? null) ? $editOutgoingLetter : null;
$editAttachmentBodies = is_array($editAttachmentBodies ?? null) ? $editAttachmentBodies : [];
$editOutgoingId = $editOutgoingLetter !== null ? (int) ($editOutgoingLetter['id'] ?? 0) : 0;
$isEditingOutgoing = $editOutgoingId > 0;
$editOutgoingNumber = $isEditingOutgoing ? trim((string) ($editOutgoingLetter['nomor_surat'] ?? '')) : '';
$cancelEditUrl = base_url('tata-usaha/persuratan' . ($selectedYearId > 0 ? '?tahun_ajaran_id=' . rawurlencode((string) $selectedYearId) : '') . '#surat-keluar');

foreach ($outgoingLetterTypes as $key => $option) {
    if (!is_array($option)) {
        continue;
    }

    $value = (string) ($option['value'] ?? $key);
    $code = (string) ($option['code'] ?? '');
    $label = trim((string) ($option['label'] ?? $value));

    $outgoingLetterOptions[$value] = [
        'value' => $value,
        'code' => $code,
        'label' => $label !== '' ? $label : strtoupper($value),
    ];

    if ($defaultOutgoingLetterType === null) {
        $defaultOutgoingLetterType = $outgoingLetterOptions[$value];
    }
}

$previewNumber = null;

if (is_array($defaultOutgoingLetterType)) {
    $previewUnit = $defaultUnitCode !== '' ? $defaultUnitCode : 'SEKOLAH';
    $previewCode = $defaultOutgoingLetterType['code'] ?? '';

    if ($previewCode !== '') {
        $previewNumber = sprintf(
            '%s.%s/%s/%s/%s',
            $previewCode,
            $nextOutgoingSequenceLabel,
            $previewUnit,
            \App\Support\LetterNumber::romanMonth((int) date('n')),
            date('Y')
        );
    }
}

$todayDate = date('Y-m-d');
$selectedLetterType = (string) old(
    'jenis_surat',
    $isEditingOutgoing ? (string) ($editOutgoingLetter['kode_jenis'] ?? '') : (string) ($defaultOutgoingLetterType['value'] ?? '')
);
$unitCodeValue = (string) old('unit_kode', $isEditingOutgoing ? (string) ($editOutgoingLetter['unit_kode'] ?? '') : $defaultUnitCode);
$recipientValue = (string) old('tujuan', $isEditingOutgoing ? (string) ($editOutgoingLetter['tujuan'] ?? '') : '');
$subjectValue = (string) old('perihal', $isEditingOutgoing ? (string) ($editOutgoingLetter['perihal'] ?? '') : '');
$letterDateValue = (string) old('tanggal_surat', $isEditingOutgoing ? (string) ($editOutgoingLetter['tanggal_surat'] ?? '') : $todayDate);
$recordedDateValue = (string) old('tanggal_dicatat', $isEditingOutgoing ? (string) ($editOutgoingLetter['tanggal_dicatat'] ?? '') : $todayDate);
$signerValue = (string) old('tanda_tangan', $isEditingOutgoing ? (string) ($editOutgoingLetter['tanda_tangan'] ?? '') : $signerDefault);
$tembusanValue = (string) old('tembusan', $isEditingOutgoing ? (string) ($editOutgoingLetter['tembusan'] ?? '') : '');
$bodyValue = (string) old('isi', $isEditingOutgoing ? (string) ($editOutgoingLetter['isi'] ?? '') : '');
$noteValue = (string) old('catatan', $isEditingOutgoing ? (string) ($editOutgoingLetter['catatan'] ?? '') : '');
$oldLampiran = (string) old('lampiran', '');
$editLampiranValue = $isEditingOutgoing ? (string) ($editOutgoingLetter['lampiran'] ?? '') : '';
$initialLampiranValue = $oldLampiran !== '' ? $oldLampiran : ($editLampiranValue !== '' ? $editLampiranValue : '-');
$initialLampiranValue = $initialLampiranValue !== '' && $initialLampiranValue !== '0' ? $initialLampiranValue : '-';
if ($initialLampiranValue !== '-' && !ctype_digit($initialLampiranValue)) {
    $initialLampiranValue = $isEditingOutgoing ? (string) ($editOutgoingLetter['lampiran_total'] ?? '') : '-';
    $initialLampiranValue = $initialLampiranValue !== '' && $initialLampiranValue !== '0' ? $initialLampiranValue : '-';
}
$oldAttachmentBodies = old('lampiran_teks', null);
$initialAttachmentBodies = [];

if (is_array($oldAttachmentBodies)) {
    ksort($oldAttachmentBodies);

    foreach ($oldAttachmentBodies as $value) {
        $initialAttachmentBodies[] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }
} elseif ($isEditingOutgoing && !empty($editAttachmentBodies)) {
    foreach ($editAttachmentBodies as $value) {
        $initialAttachmentBodies[] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }
}

$initialAttachmentBodiesJson = htmlspecialchars(json_encode($initialAttachmentBodies, JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');
$maxOutgoingAttachments = isset($maxOutgoingAttachments) ? max(1, (int) $maxOutgoingAttachments) : 5;
$successMessage = session_flash('success');
$errorMessage = session_flash('error');
$warningMessage = session_flash('warning');
$infoMessage = session_flash('info');
$ajukanTtdValue = (string) old('ajukan_ttd', '1');
$ajukanTtdChecked = $ajukanTtdValue === '1';
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Rekap Persuratan</h2>
                <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-xs text-slate-500">
                    Semua surat dengan TTD kepala sekolah harus diajukan terlebih dahulu agar QR dapat diterbitkan. Gunakan menu SK Penugasan untuk melakukan pengajuan baru.
                </p>
            </div>
            <div class="flex flex-col items-start gap-2 md:items-end">
                <a
                    href="<?= htmlspecialchars(base_url('tata-usaha/sk-penugasan'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50"
                >
                    <i class="ri-file-add-line text-sm"></i>
                    Kelola SK &amp; Pengajuan QR
                </a>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $digitalSignatureEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                    <?= $digitalSignatureEnabled ? 'TTD Digital Aktif' : 'TTD Digital Nonaktif' ?>
                </span>
            </div>
        </div>
        <?php if (!$digitalSignatureEnabled): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                TTD digital belum diaktifkan oleh admin pada tahun ajaran ini. Hubungi admin untuk mengaktifkannya sebelum mengajukan QR.
            </div>
        <?php endif; ?>

        <form method="get" class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="tahun_ajaran_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun Ajaran</label>
                <select
                    id="tahun_ajaran_id"
                    name="tahun_ajaran_id"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                >
                    <?php foreach ($yearOptions as $id => $label): ?>
                        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $id === $selectedYearId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select
                    id="status"
                    name="status"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                >
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $statusFilter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200"
                >
                    Terapkan Filter
                </button>
            </div>
        </form>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Menunggu Persetujuan</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800"><?= htmlspecialchars((string) $statusCounts['pending'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Disetujui</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600"><?= htmlspecialchars((string) $statusCounts['approved'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dicabut</p>
                <p class="mt-1 text-2xl font-semibold text-rose-600"><?= htmlspecialchars((string) $statusCounts['revoked'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($warningMessage)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <?= htmlspecialchars($warningMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($infoMessage)): ?>
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">
            <?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold text-slate-800">Daftar Surat</h3>
            <span class="text-xs text-slate-400"><?= htmlspecialchars((string) count($letters), ENT_QUOTES, 'UTF-8') ?> dokumen</span>
        </div>

        <?php if (empty($letters)): ?>
            <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center">
                <i class="ri-mail-close-line text-3xl text-slate-300"></i>
                <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada surat dengan TTD digital pada tahun ajaran ini.</p>
                <p class="mt-2 text-xs text-slate-500">
                    Ajukan dokumen melalui modul SK Penugasan atau persuratan lain untuk memulai proses persetujuan kepala sekolah.
                </p>
            </div>
        <?php else: ?>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3 text-left">Dokumen</th>
                            <th class="px-4 py-3 text-left">Rincian Surat</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($letters as $record): ?>
                            <?php
                                $documentTitle = $record['document_title'] ?? '-';
                                $documentType = (string) ($record['document_type'] ?? '');
                                $documentTypeLabel = $documentTypeLabels[$documentType] ?? ucfirst(str_replace('_', ' ', $documentType));
                                $status = (string) ($record['status'] ?? 'pending');
                                $statusBadgeClass = $statusBadgeClasses[$status] ?? 'border-slate-200 bg-slate-50 text-slate-500';
                                $letterInfo = is_array($record['letter'] ?? null) ? $record['letter'] : [];
                                $letterNumber = $letterInfo['number'] ?? '-';
                                $letterSubject = $letterInfo['subject'] ?? '-';
                                $signDate = $letterInfo['sign_date_formatted'] ?? ($letterInfo['sign_date'] ?? null);
                                $effectiveStart = $letterInfo['effective_start_formatted'] ?? null;
                                $effectiveEnd = $letterInfo['effective_end_formatted'] ?? null;
                                $verificationUrl = $record['verification_url'] ?? null;
                                $requestedAt = $record['requested_at_label'] ?? null;
                                $approvedAt = $record['approved_at_label'] ?? null;
                                $updatedAt = $record['updated_at_label'] ?? null;
                                $approvalNote = $record['approval_note'] ?? null;
                            ?>
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($documentTypeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($requestedAt): ?>
                                        <p class="mt-2 text-xs text-slate-400">Diajukan: <?= htmlspecialchars($requestedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($approvedAt): ?>
                                        <p class="mt-1 text-xs text-emerald-600">Disetujui: <?= htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php elseif ($updatedAt): ?>
                                        <p class="mt-1 text-xs text-slate-400">Diperbarui: <?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top text-slate-600">
                                    <p class="text-sm font-medium text-slate-700">Nomor: <?= htmlspecialchars($letterNumber !== '' ? $letterNumber : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-500">Perihal: <?= htmlspecialchars($letterSubject !== '' ? $letterSubject : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($signDate): ?>
                                        <p class="mt-1 text-xs text-slate-500">Tanggal SK: <?= htmlspecialchars($signDate, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($effectiveStart && $effectiveEnd): ?>
                                        <p class="mt-1 text-xs text-slate-500">Masa berlaku: <?= htmlspecialchars($effectiveStart, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= htmlspecialchars($effectiveEnd, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($approvalNote): ?>
                                        <p class="mt-2 text-xs text-slate-500">Catatan Kepala Sekolah: <?= htmlspecialchars($approvalNote, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-xs text-slate-500">
                                    <?php if ($verificationUrl): ?>
                                        <a
                                            href="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-500"
                                        >
                                            <i class="ri-external-link-line text-sm"></i>
                                            Buka halaman verifikasi
                                        </a>
                                    <?php else: ?>
                                        <span>QR belum aktif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <details id="pengaturan-kop" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <summary class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between cursor-pointer">
            <span>
                <span class="text-base font-semibold text-slate-800">Pengaturan Kop Surat</span>
                <span class="mt-1 block text-sm text-slate-500">
                    Unggah file kop surat berformat JPG untuk digunakan pada setiap cetakan surat keluar.
                </span>
            </span>
            <span class="text-xs text-slate-400">Klik untuk buka</span>
        </summary>

        <div class="mt-6">
            <?php if (!$schoolProfileExists): ?>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                    Lengkapi terlebih dahulu profil sekolah pada menu Master &gt; Sekolah sebelum mengunggah kop surat.
                </div>
            <?php endif; ?>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,360px)_1fr]">
                <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-5">
                    <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/kop'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-4">
                        <?= csrf_field() ?>
                        <div>
                            <label for="kop_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">File Kop Surat (JPG)</label>
                            <input
                                type="file"
                                id="kop_surat"
                                name="kop_surat"
                                accept=".jpg,.jpeg,image/jpeg"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-50"
                                <?= $schoolProfileExists ? ' required' : ' disabled' ?>
                            />
                            <p class="mt-2 text-xs text-slate-500">Disarankan ukuran lebar minimal 1200px agar hasil cetak tajam.</p>
                        </div>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:bg-slate-400"
                        <?= $schoolProfileExists ? '' : ' disabled aria-disabled="true"' ?>
                    >
                        <i class="ri-upload-2-line text-base"></i>
                        Simpan Kop Surat
                    </button>
                </form>
                <?php if ($letterheadPath !== ''): ?>
                    <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/kop'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Hapus kop surat saat ini?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="remove" value="1">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                        >
                            <i class="ri-delete-bin-line text-base"></i>
                            Hapus
                        </button>
                    </form>
                <?php endif; ?>
            </div>

                <div class="rounded-xl border border-dashed border-slate-200 bg-white p-4">
                    <?php if ($letterheadUrl !== null): ?>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preview Kop Surat</p>
                        <div class="mt-3 overflow-hidden rounded-lg border border-slate-200 bg-white">
                            <img
                                src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="Kop Surat"
                                class="h-auto w-full"
                            />
                        </div>
                    <?php else: ?>
                        <div class="flex h-full flex-col items-center justify-center gap-3 py-12 text-center text-slate-400">
                            <i class="ri-image-line text-3xl"></i>
                            <p class="text-sm font-medium text-slate-500">Belum ada kop surat yang diunggah.</p>
                            <p class="text-xs text-slate-400">Unggah file JPG untuk menampilkan kop surat di halaman ini dan pada cetak surat keluar.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </details>

    <details id="surat-keluar" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <summary class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between cursor-pointer">
            <span>
                <span class="text-base font-semibold text-slate-800">Pencatatan Surat Keluar</span>
                <span class="mt-1 block text-sm text-slate-500">
                    Gunakan formulir ini untuk membuat surat keluar beserta nomor otomatis sesuai kode jenis surat.
                </span>
            </span>
            <span class="text-xs text-slate-400">Klik untuk buka</span>
        </summary>

        <div class="mt-6 space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Nomor Berikutnya</p>
                    <p class="mt-1 font-mono text-base">
                        <?= $previewNumber !== null ? htmlspecialchars($previewNumber, ENT_QUOTES, 'UTF-8') : htmlspecialchars($nextOutgoingSequenceLabel, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mt-1 text-xs text-indigo-500">
                        Urutan ke-<?= htmlspecialchars($nextOutgoingSequenceLabel, ENT_QUOTES, 'UTF-8') ?> &middot; Bulan <?= htmlspecialchars(\App\Support\LetterNumber::romanMonth((int) date('n')), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="flex flex-col items-start gap-2">
                    <a
                        href="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar/pdf'), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:border-amber-300 hover:bg-amber-100"
                    >
                        <i class="ri-file-pdf-line text-base"></i>
                        Mode Unggah PDF + QR
                    </a>
                    <p class="text-xs text-slate-500">Pisahkan surat PDF di halaman khusus agar lebih rapi.</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,360px)_1fr]">
            <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-5" data-outgoing-letter-form>
                <?= csrf_field() ?>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($isEditingOutgoing): ?>
                    <input type="hidden" name="outgoing_letter_id" value="<?= htmlspecialchars((string) $editOutgoingId, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        Mode edit surat keluar<?= $editOutgoingNumber !== '' ? ' nomor ' . htmlspecialchars($editOutgoingNumber, ENT_QUOTES, 'UTF-8') : '' ?>. Setelah disimpan, status persetujuan kepala sekolah akan mengikuti revisi terbaru.
                        <a href="<?= htmlspecialchars($cancelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="ml-1 font-semibold text-amber-800 underline">Batalkan edit</a>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="jenis_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Surat</label>
                    <select
                        id="jenis_surat"
                        name="jenis_surat"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                        <?= $isEditingOutgoing ? 'disabled' : '' ?>
                    >
                        <?php foreach ($outgoingLetterOptions as $value => $option): ?>
                            <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $selectedLetterType ? 'selected' : '' ?>>
                                <?= htmlspecialchars(sprintf('%s - %s', $option['code'] ?? '-', $option['label'] ?? $value), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEditingOutgoing): ?>
                        <input type="hidden" name="jenis_surat" value="<?= htmlspecialchars($selectedLetterType, ENT_QUOTES, 'UTF-8') ?>">
                        <p class="mt-2 text-xs text-slate-500">Jenis surat tidak dapat diubah saat revisi.</p>
                    <?php endif; ?>
                </div>

                <?php if (!$isEditingOutgoing && !empty($commonLetterTemplates)): ?>
                    <div>
                        <label for="template_preset" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Template Siap Pakai</label>
                        <select
                            id="template_preset"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            data-letter-template-preset
                        >
                            <option value="">Pilih template yang sering digunakan</option>
                            <?php foreach ($commonLetterTemplates as $templateKey => $template): ?>
                                <option value="<?= htmlspecialchars((string) $templateKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($template['label'] ?? $templateKey), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-2 text-xs text-slate-500">
                            Template akan mengisi jenis surat, tujuan, perihal, lampiran, tembusan, dan isi surat. Semua teks tetap dapat diedit.
                        </p>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="template_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Upload Template Eksternal (Opsional)</label>
                    <div class="mt-2 space-y-2 rounded-lg border border-dashed border-indigo-200 bg-white px-3 py-3">
                        <input
                            type="file"
                            id="template_surat"
                            name="template_surat"
                            accept=".docx,.txt"
                            class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border file:border-indigo-200 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            data-template-upload
                            data-upload-url="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar/template'), ENT_QUOTES, 'UTF-8') ?>"
                        />
                        <p class="text-xs text-slate-500">
                            Unggah file DOCX atau TXT (maks. 5MB) untuk mengisi otomatis tujuan, perihal, tembusan, dan isi surat. Periksa kembali hasilnya sebelum menyimpan.
                        </p>
                        <p class="text-xs font-semibold text-slate-500" data-template-status hidden></p>
                    </div>
                </div>

                <div>
                    <label for="unit_kode" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Unit / Lembaga</label>
                    <input
                        type="text"
                        id="unit_kode"
                        name="unit_kode"
                        value="<?= htmlspecialchars($unitCodeValue, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Misal: SMA-SM"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>

                <div>
                    <label for="tujuan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tujuan Surat</label>
                    <input
                        type="text"
                        id="tujuan"
                        name="tujuan"
                        placeholder="Nama penerima / instansi tujuan"
                        value="<?= htmlspecialchars($recipientValue, ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>

                <div>
                    <label for="perihal" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Perihal</label>
                    <input
                        type="text"
                        id="perihal"
                        name="perihal"
                        placeholder="Misal: Undangan Rapat Koordinasi"
                        value="<?= htmlspecialchars($subjectValue, ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tanggal_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Surat</label>
                        <input
                            type="date"
                            id="tanggal_surat"
                            name="tanggal_surat"
                            value="<?= htmlspecialchars($letterDateValue, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        />
                    </div>
                    <div>
                        <label for="tanggal_dicatat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Dicatat</label>
                        <input
                            type="date"
                            id="tanggal_dicatat"
                            name="tanggal_dicatat"
                            value="<?= htmlspecialchars($recordedDateValue, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="lampiran" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</label>
                        <select
                            id="lampiran"
                            name="lampiran"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            data-lampiran-count-input
                        >
                            <option value="-" <?= $initialLampiranValue === '-' ? 'selected' : '' ?>>Tidak Ada (-)</option>
                            <?php for ($i = 1; $i <= $maxOutgoingAttachments; $i++): ?>
                                <option value="<?= $i ?>" <?= $initialLampiranValue === (string) $i ? 'selected' : '' ?>><?= $i ?> Lampiran</option>
                            <?php endfor; ?>
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Pilih jumlah lampiran yang ingin dibuat. Maksimal <?= htmlspecialchars((string) $maxOutgoingAttachments, ENT_QUOTES, 'UTF-8') ?> lampiran.</p>
                    </div>
                    <div>
                        <label for="tanda_tangan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Penandatangan</label>
                    <input
                        type="text"
                        id="tanda_tangan"
                        name="tanda_tangan"
                        placeholder="Misal: Kepala Sekolah"
                        value="<?= htmlspecialchars($signerValue, ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                        <?php if ($headmasterOption !== null): ?>
                            <p class="mt-2 text-xs text-slate-500">Nama Kepala Sekolah: <span class="font-semibold text-slate-700"><?= htmlspecialchars($headmasterOption['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></p>
                        <?php endif; ?>
                        <?php if ($digitalSignatureEnabled): ?>
                            <span class="mt-2 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                                <i class="ri-qr-code-line text-sm"></i>
                                QR Digital Aktif
                            </span>
                        <?php endif; ?>
                        <p class="mt-2 text-xs text-slate-500">Ubah bila penandatangan berbeda.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <div
                            class="space-y-4"
                            data-lampiran-container
                            data-initial-lampiran="<?= $initialAttachmentBodiesJson ?>"
                            data-max-lampiran="<?= htmlspecialchars((string) $maxOutgoingAttachments, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500" data-lampiran-empty>
                                Pilih jumlah lampiran untuk menampilkan editor lampiran terpisah.
                            </div>
                            <?php for ($i = 1; $i <= $maxOutgoingAttachments; $i++): ?>
                                <?php $initialLampiranHtml = $initialAttachmentBodies[$i - 1] ?? ''; ?>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-lampiran-block data-lampiran-index="<?= $i ?>" hidden>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran <?= $i ?></p>
                                            <p class="text-xs text-slate-500">Lampiran dicetak di halaman terpisah. Gunakan tabel untuk daftar atau rincian.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50"
                                            data-lampiran-fullscreen
                                        >
                                            <i class="ri-expand-diagonal-line text-xs"></i>
                                            Perbesar Editor
                                        </button>
                                    </div>
                                    <textarea class="mt-3 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" rows="12" placeholder="Tuliskan isi lampiran" name="lampiran_teks[<?= $i ?>]" id="lampiran_teks_<?= $i ?>" data-lampiran-editor><?= htmlspecialchars($initialLampiranHtml, ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php if ($digitalSignatureEnabled): ?>
                    <div class="flex items-start gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-slate-600">
                        <input type="hidden" name="ajukan_ttd" value="0">
                        <input
                            type="checkbox"
                            id="ajukan_ttd"
                            name="ajukan_ttd"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500"
                            <?= $ajukanTtdChecked ? 'checked' : '' ?>
                        />
                        <label for="ajukan_ttd" class="flex-1">
                            Ajukan TTD digital kepala sekolah untuk surat ini (disarankan tetap aktif agar verifikasi QR tersedia).
                        </label>
                    </div>
                <?php endif; ?>

            <div>
                <label for="tembusan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tembusan</label>
                <textarea
                    id="tembusan"
                    name="tembusan"
                    rows="3"
                    placeholder="Tuliskan satu penerima per baris (opsional)"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                ><?= htmlspecialchars($tembusanValue, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div>
                <label for="isi" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Isi Surat</label>
                <input
                    type="hidden"
                    id="isi"
                    name="isi"
                    data-letter-body-input
                    value="<?= htmlspecialchars($bodyValue, ENT_QUOTES, 'UTF-8') ?>"
                />
                <textarea
                    id="letter-editor"
                    data-letter-editor
                    placeholder="Tuliskan isi surat (opsional)"
                ></textarea>
                <div class="mt-2 flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <span>Editor mendukung format dasar, tabel sederhana, dan mode layar penuh (ikon maximize).</span>
                    <button
                        type="button"
                        class="inline-flex items-center rounded border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition hover:border-indigo-300 hover:bg-indigo-50"
                        data-letter-editor-fullscreen
                    >
                        Perbesar Editor
                    </button>
                </div>
            </div>

            <div>
                <label for="catatan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Internal</label>
                <textarea
                    id="catatan"
                    name="catatan"
                    rows="2"
                    placeholder="Opsional"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                ><?= htmlspecialchars($noteValue, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    <i class="ri-send-plane-line text-base"></i>
                    <?= $isEditingOutgoing ? 'Simpan Perubahan' : 'Simpan &amp; Generate Nomor' ?>
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Surat</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tujuan &amp; Perihal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (empty($outgoingLetters)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Belum ada surat keluar yang tercatat pada tahun ajaran ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($outgoingLetters as $letter): ?>
                                <?php
                                    $letterId = (int) ($letter['id'] ?? 0);
                                    $letterNumber = trim((string) ($letter['nomor_surat'] ?? ''));
                                    $letterTypeLabel = trim((string) ($letter['jenis_label'] ?? ''));
                                    $letterUnit = trim((string) ($letter['unit_kode'] ?? ''));
                                    $letterTarget = trim((string) ($letter['tujuan'] ?? ''));
                                    $letterSubject = trim((string) ($letter['perihal'] ?? ''));
                                    $letterDate = trim((string) ($letter['tanggal_surat_formatted'] ?? ($letter['tanggal_surat'] ?? '')));
                                    $recordedDate = trim((string) ($letter['tanggal_dicatat_formatted'] ?? ''));
                                    $letterAttachment = trim((string) ($letter['lampiran'] ?? ''));
                                    $signature = is_array($letter['digital_signature'] ?? null) ? $letter['digital_signature'] : null;
                                    $signatureClass = $signature['status_class'] ?? 'border-slate-200 bg-slate-50 text-slate-500';
                                    $signatureLabel = $signature['status_label'] ?? '';
                                    $signatureUrl = $signature['verification_url'] ?? null;
                                    $requiresHeadmasterDigital = (bool) ($letter['requires_headmaster_digital_signature'] ?? false);
                                    $signatureMissing = (bool) ($letter['digital_signature_missing'] ?? false);
                                    $pdfMeta = is_array($letter['pdf'] ?? null) ? $letter['pdf'] : [];
                                    $pdfUrl = $pdfMeta['url'] ?? null;
                                    $signedPdfUrl = $pdfMeta['signed_url'] ?? null;
                                    $previewUrl = $letterId > 0 ? base_url('tata-usaha/persuratan/surat-keluar/' . $letterId . '/preview-ttd') : null;
                                    $isApprovedSignature = isset($signature['status']) && $signature['status'] === 'approved';
                                    $editParams = [];
                                    if ($selectedYearId > 0) {
                                        $editParams['tahun_ajaran_id'] = $selectedYearId;
                                    }
                                    if ($letterId > 0) {
                                        $editParams['edit_surat'] = $letterId;
                                    }
                                    $editUrl = $editParams !== []
                                        ? base_url('tata-usaha/persuratan?' . http_build_query($editParams) . '#surat-keluar')
                                        : null;
                                ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">
                                        <?= htmlspecialchars($letterNumber !== '' ? $letterNumber : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <span class="block font-medium text-slate-700">
                                            <?= htmlspecialchars($letterTypeLabel !== '' ? $letterTypeLabel : 'Jenis Surat', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <?php if ($letterUnit !== ''): ?>
                                            <span class="mt-1 block text-xs text-slate-500">Unit: <?= htmlspecialchars($letterUnit, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <span class="block"><?= htmlspecialchars($letterDate !== '' ? $letterDate : '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($recordedDate !== ''): ?>
                                            <span class="mt-1 block text-xs text-slate-400">Dicatat: <?= htmlspecialchars($recordedDate, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <?php if ($letterTarget !== ''): ?>
                                            <span class="block font-medium text-slate-700"><?= htmlspecialchars($letterTarget, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <span class="block text-xs text-slate-500"><?= htmlspecialchars($letterSubject !== '' ? $letterSubject : '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($requiresHeadmasterDigital): ?>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                <?php if ($signature !== null): ?>
                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 font-semibold <?= htmlspecialchars($signatureClass, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($signatureLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <?php if ($signatureUrl !== null): ?>
                                                        <a
                                                            href="<?= htmlspecialchars($signatureUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-500"
                                                        >
                                                            <i class="ri-external-link-line"></i>
                                                            Verifikasi
                                                        </a>
                                                    <?php endif; ?>
                                                <?php elseif ($signatureMissing): ?>
                                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-600">
                                                        TTD digital kepala sekolah belum diajukan
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <?= $letterAttachment !== '' ? nl2br(htmlspecialchars($letterAttachment, ENT_QUOTES, 'UTF-8')) : '-' ?>
                                    </td>
                                    <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                            <?php if ($signedPdfUrl !== null || $pdfUrl !== null): ?>
                                                <a
                                                    href="<?= htmlspecialchars($signedPdfUrl !== null ? $signedPdfUrl : $pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center rounded-lg border <?= $signedPdfUrl !== null ? 'border-emerald-200 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50' : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50' ?> px-2.5 py-1.5 text-xs font-semibold"
                                                >
                                                    <i class="ri-file-pdf-line text-sm"></i>
                                                    <?= $signedPdfUrl !== null ? 'PDF Bertanda Tangan' : 'PDF Asli' ?>
                                                </a>
                                            <?php endif; ?>
                                                <?php if ($letterId > 0): ?>
                                                    <?php if ($editUrl !== null): ?>
                                                        <a
                                                            href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                            class="inline-flex items-center rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50"
                                                        >
                                                            Edit
                                                        </a>
                                                    <?php endif; ?>
                                                    <a
                                                        href="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar/' . $letterId), ENT_QUOTES, 'UTF-8') ?>"
                                                        class="inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50"
                                                    >
                                                    Detail
                                                </a>
                                                <a
                                                    href="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar/' . $letterId . '/cetak'), ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex items-center rounded-lg border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50"
                                                >
                                                    Cetak
                                                </a>
                                                <form
                                                    method="post"
                                                    action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar/' . $letterId . '/hapus'), ENT_QUOTES, 'UTF-8') ?>"
                                                    onsubmit="return confirm('Hapus surat keluar ini?');"
                                                >
                                                    <?= csrf_field() ?>
                                                    <button
                                                        type="submit"
                                                    class="inline-flex items-center rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                                                >
                                                    Hapus
                                                </button>
                                                </form>
                                                <?php if ($previewUrl !== null): ?>
                                                    <a
                                                        href="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                        class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold <?= $isApprovedSignature ? 'border-slate-200 text-slate-400 cursor-not-allowed opacity-60' : 'border-amber-200 text-amber-700 hover:border-amber-300 hover:bg-amber-50' ?>"
                                                        <?= $isApprovedSignature ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                                                    >
                                                        Atur QR PDF
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </details>

    <details id="surat-masuk" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <summary class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between cursor-pointer">
            <span>
                <span class="text-base font-semibold text-slate-800">Pencatatan Surat Masuk</span>
                <span class="mt-1 block text-sm text-slate-500">
                    Simpan nomor agenda surat masuk untuk memudahkan pelacakan dokumen fisik maupun digital.
                </span>
            </span>
            <span class="text-xs text-slate-400">Klik untuk buka</span>
        </summary>

        <div class="mt-6 space-y-6">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500">Nomor Agenda Berikutnya</p>
                <p class="mt-1 font-mono text-base"><?= htmlspecialchars($nextIncomingAgendaLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-emerald-500">Total surat masuk: <?= htmlspecialchars((string) count($incomingLetters), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,320px)_1fr]">
            <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-masuk'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-5">
                <?= csrf_field() ?>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="nomor_surat_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Surat</label>
                    <input
                        type="text"
                        id="nomor_surat_masuk"
                        name="nomor_surat"
                        placeholder="Nomor surat dari pengirim"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    />
                </div>

                <div>
                    <label for="asal_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Asal Surat</label>
                    <input
                        type="text"
                        id="asal_surat"
                        name="asal_surat"
                        placeholder="Nama instansi / pihak pengirim"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    />
                </div>

                <div>
                    <label for="perihal_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Perihal</label>
                    <input
                        type="text"
                        id="perihal_masuk"
                        name="perihal"
                        placeholder="Misal: Permohonan Data"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tanggal_surat_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Surat</label>
                        <input
                            type="date"
                            id="tanggal_surat_masuk"
                            name="tanggal_surat"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="tanggal_diterima" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Diterima</label>
                        <input
                            type="date"
                            id="tanggal_diterima"
                            name="tanggal_diterima"
                            value="<?= htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        />
                    </div>
                </div>

                <div>
                    <label for="penerima" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Penerima Internal</label>
                    <input
                        type="text"
                        id="penerima"
                        name="penerima"
                        placeholder="Nama staf/instansi internal (opsional)"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>

                <div>
                    <label for="lampiran_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</label>
                    <input
                        type="text"
                        id="lampiran_masuk"
                        name="lampiran"
                        placeholder="Opsional"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>

                <div>
                    <label for="catatan_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
                    <textarea
                        id="catatan_masuk"
                        name="catatan"
                        rows="3"
                        placeholder="Catatan tindak lanjut (opsional)"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    ></textarea>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <i class="ri-inbox-archive-line text-base"></i>
                        Simpan Surat Masuk
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Agenda</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor &amp; Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Asal</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Perihal</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php if (empty($incomingLetters)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                        Belum ada surat masuk yang tercatat pada tahun ajaran ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($incomingLetters as $incoming): ?>
                                    <?php
                                        $incomingId = (int) ($incoming['id'] ?? 0);
                                        $agendaNumber = str_pad((string) ($incoming['nomor_agenda'] ?? ''), 3, '0', STR_PAD_LEFT);
                                        $letterNumber = trim((string) ($incoming['nomor_surat'] ?? ''));
                                        $letterDate = trim((string) ($incoming['tanggal_surat_formatted'] ?? ($incoming['tanggal_surat'] ?? '')));
                                        $receivedDate = trim((string) ($incoming['tanggal_diterima_formatted'] ?? ($incoming['tanggal_diterima'] ?? '')));
                                        $origin = trim((string) ($incoming['asal_surat'] ?? ''));
                                        $subject = trim((string) ($incoming['perihal'] ?? ''));
                                        $incomingAttachment = trim((string) ($incoming['lampiran'] ?? ''));
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">
                                            <?= htmlspecialchars($agendaNumber !== '' ? $agendaNumber : '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <span class="block font-medium text-slate-700"><?= htmlspecialchars($letterNumber !== '' ? $letterNumber : '-', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($letterDate !== ''): ?>
                                                <span class="mt-1 block text-xs text-slate-500">Tanggal Surat: <?= htmlspecialchars($letterDate, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($receivedDate !== ''): ?>
                                                <span class="mt-1 block text-xs text-slate-500">Diterima: <?= htmlspecialchars($receivedDate, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <?= htmlspecialchars($origin !== '' ? $origin : '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <?= htmlspecialchars($subject !== '' ? $subject : '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <?= $incomingAttachment !== '' ? nl2br(htmlspecialchars($incomingAttachment, ENT_QUOTES, 'UTF-8')) : '-' ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end">
                                                <?php if ($incomingId > 0): ?>
                                                    <form
                                                        method="post"
                                                        action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-masuk/' . $incomingId . '/hapus'), ENT_QUOTES, 'UTF-8') ?>"
                                                        onsubmit="return confirm('Hapus surat masuk ini?');"
                                                    >
                                                        <?= csrf_field() ?>
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
    </details>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<style>
    body.note-fullscreen {
        overflow: hidden;
        background: rgba(15, 23, 42, 0.55);
    }

    .note-editor.note-frame.fullscreen {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 28px 60px rgba(15, 23, 42, 0.35);
        margin: 32px auto;
        max-width: 1080px;
        width: calc(100vw - 64px) !important;
    }

    .note-editor.note-frame.fullscreen .note-toolbar {
        border-radius: 16px 16px 0 0;
    }

    .note-editor.note-frame.fullscreen .note-editing-area {
        border-radius: 0 0 16px 16px;
        background: #ffffff;
    }

    .note-editor.note-frame.fullscreen .note-editing-area .note-editable {
        min-height: calc(100vh - 220px);
        background: #ffffff;
        color: #0f172a;
        padding-bottom: 48px;
    }

    .note-editor.note-frame.fullscreen .note-status-output {
        background: transparent;
    }
</style>

<script type="application/json" data-letter-template-presets><?= $commonLetterTemplatesJson ?></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const outgoingForm = document.querySelector('[data-outgoing-letter-form]');
        const editorApi = initSummernoteEditor({
            editorSelector: '[data-letter-editor]',
            hiddenSelector: '[data-letter-body-input]',
            fullscreenSelector: '[data-letter-editor-fullscreen]',
        });

        initLampiranEditors({
            form: outgoingForm,
            countInput: document.querySelector('[data-lampiran-count-input]'),
            container: document.querySelector('[data-lampiran-container]'),
        });

        initTemplateUpload(editorApi);
        initLetterTemplatePresets(editorApi);
    });

    function initSummernoteEditor(config) {
        const hiddenInput = document.querySelector(config.hiddenSelector);
        if (!hiddenInput) {
            return {
                setValue(value) {
                    // no-op
                },
                getValue() {
                    return '';
                },
            };
        }

        const $ = window.jQuery;

        if (typeof $ !== 'function') {
            console.warn('Summernote membutuhkan jQuery agar berfungsi.');
            return {
                setValue(value) {
                    hiddenInput.value = value ?? '';
                },
                getValue() {
                    return hiddenInput.value || '';
                },
            };
        }

        const $editor = $(config.editorSelector);

        if ($editor.length === 0) {
            return {
                setValue(value) {
                    hiddenInput.value = value ?? '';
                },
                getValue() {
                    return hiddenInput.value || '';
                },
            };
        }

        const initialHtml = hiddenInput.value || '';
        let isSettingValue = false;
        const fullscreenButton = document.querySelector(config.fullscreenSelector ?? '');

        $editor.summernote({
            placeholder: $editor.attr('placeholder') || '',
            tabsize: 2,
            height: 320,
            minHeight: 280,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['table', 'link']],
                ['view', ['fullscreen', 'codeview']],
            ],
            callbacks: {
                onInit() {
                    isSettingValue = true;
                    $editor.summernote('code', initialHtml);
                    hiddenInput.value = initialHtml;
                    updateFullscreenLabel(false);
                    isSettingValue = false;
                },
                onChange(contents) {
                    if (isSettingValue) {
                        return;
                    }

                    hiddenInput.value = contents;
                },
                onFullscreen(isFullscreen) {
                    updateFullscreenLabel(isFullscreen);
                },
            },
        });

        const form = hiddenInput.closest('form');

        if (form) {
            form.addEventListener('submit', () => {
                hiddenInput.value = $editor.summernote('code');
            });
        }

        if (fullscreenButton) {
            fullscreenButton.addEventListener('click', (event) => {
                event.preventDefault();
                $editor.summernote('fullscreen.toggle');
            });
        }

        function updateFullscreenLabel(isFullscreen) {
            if (!fullscreenButton) {
                return;
            }

            fullscreenButton.textContent = isFullscreen ? 'Keluar Layar Penuh' : 'Perbesar Editor';
        }

        return {
            setValue(value) {
                const html = value ?? '';
                isSettingValue = true;
                $editor.summernote('code', html);
                hiddenInput.value = html;
                isSettingValue = false;
            },
            getValue() {
                return hiddenInput.value || '';
            },
        };
    }

    function initLampiranEditors(config) {
        const countInput = config.countInput;
        const container = config.container;
        const form = config.form instanceof HTMLFormElement ? config.form : null;

        if (!countInput || !container) {
            return;
        }

        const maxAttachments = Number.parseInt(container.dataset.maxLampiran || '5', 10) || 5;
        const initialData = parseLampiranInitialData(container.dataset.initialLampiran || '[]');
        const nonEmptyInitial = initialData.filter((value) => typeof value === 'string' && value.trim() !== '');
        const blocks = Array.from(container.querySelectorAll('[data-lampiran-block]'));
        const emptyState = container.querySelector('[data-lampiran-empty]');
        const $ = window.jQuery;
        const defaultHtml = '<p><br></p>';

        const entries = blocks.map((block) => {
            const textarea = block.querySelector('[data-lampiran-editor]');
            const fullscreenButton = block.querySelector('[data-lampiran-fullscreen]');
            const index = Number.parseInt(block.getAttribute('data-lampiran-index') || '0', 10);

            if (textarea) {
                textarea.dataset.initialHtml = textarea.value || '';
            }

            return {
                index,
                block,
                textarea,
                fullscreenButton,
                instance: null,
                initialized: false,
                active: false,
            };
        }).filter((entry) => entry.index > 0).sort((a, b) => a.index - b.index);

        const parseCount = (value) => {
            if (value === '-' || value === '' || value === null || value === undefined) {
                return 0;
            }

            const parsed = Number.parseInt(String(value), 10);

            if (!Number.isFinite(parsed) || parsed <= 0) {
                return 0;
            }

            return Math.min(parsed, maxAttachments);
        };

        const updateEmptyState = () => {
            if (!emptyState) {
                return;
            }

            emptyState.hidden = entries.some((entry) => entry.active);
        };

        const ensureInitialized = (entry) => {
            if (!entry || entry.initialized || !entry.textarea) {
                return;
            }

            if (typeof $ === 'function') {
                const $editor = $(entry.textarea);
                entry.instance = $editor;
                let isSettingValue = false;
                const initialHtml = entry.textarea.dataset.initialHtml && entry.textarea.dataset.initialHtml.trim() !== ''
                    ? entry.textarea.dataset.initialHtml
                    : defaultHtml;

                $editor.summernote({
                    placeholder: entry.textarea.getAttribute('placeholder') || '',
                    tabsize: 2,
                    height: 280,
                    minHeight: 240,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['fontname', ['fontname']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['table', 'link']],
                        ['view', ['fullscreen', 'codeview']],
                    ],
                    callbacks: {
                        onInit() {
                            isSettingValue = true;
                            $editor.summernote('code', initialHtml);
                            entry.textarea.value = initialHtml;
                            isSettingValue = false;
                        },
                        onChange(contents) {
                            if (isSettingValue) {
                                return;
                            }

                            entry.textarea.value = contents;
                        },
                        onBlur() {
                            entry.textarea.value = $editor.summernote('code');
                        },
                    },
                });

                if (entry.fullscreenButton) {
                    entry.fullscreenButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        $editor.summernote('fullscreen.toggle');
                    });
                }
            } else {
                entry.textarea.value = entry.textarea.dataset.initialHtml || '';
                if (entry.fullscreenButton) {
                    entry.fullscreenButton.hidden = true;
                }
            }

            entry.initialized = true;
        };

        const applyActiveCount = (count) => {
            entries.forEach((entry) => {
                const shouldBeActive = entry.index <= count;
                entry.active = shouldBeActive;

                if (shouldBeActive) {
                    ensureInitialized(entry);
                    entry.block.hidden = false;
                } else {
                    entry.block.hidden = true;
                }
            });

            updateEmptyState();
        };

        const initialSelected = parseCount(countInput.value);
        const fallbackCount = initialSelected > 0
            ? initialSelected
            : Math.min(nonEmptyInitial.length, maxAttachments);

        if (fallbackCount > 0) {
            countInput.value = String(fallbackCount);
        }

        applyActiveCount(fallbackCount);

        countInput.addEventListener('change', () => {
            const count = parseCount(countInput.value);
            applyActiveCount(count);
        });

        if (form) {
            form.addEventListener('submit', () => {
                entries.forEach((entry) => {
                    if (!entry.textarea) {
                        return;
                    }

                    if (entry.active && entry.instance && typeof entry.instance.summernote === 'function') {
                        entry.textarea.value = entry.instance.summernote('code');
                    } else if (!entry.active) {
                        entry.textarea.value = '';
                    }
                });
            });
        }
    }

    function parseLampiranInitialData(serialized) {
        try {
            const parsed = JSON.parse(serialized || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch (_error) {
            return [];
        }
    }

    function initLetterTemplatePresets(editorApi) {
        const presetSelect = document.querySelector('[data-letter-template-preset]');
        const templateSource = document.querySelector('[data-letter-template-presets]');

        if (!presetSelect || !templateSource) {
            return;
        }

        let templates = {};

        try {
            const parsed = JSON.parse(templateSource.textContent || '{}');
            templates = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (_error) {
            templates = {};
        }

        presetSelect.addEventListener('change', () => {
            const template = templates[presetSelect.value];

            if (!template || typeof template !== 'object') {
                return;
            }

            setInputValue('jenis_surat', template.type || '');
            setInputValue('tujuan', template.recipient || '');
            setInputValue('perihal', template.subject || '');
            setInputValue('tembusan', template.carbon_copy || '');
            setInputValue('lampiran', template.attachment || '-');

            const body = typeof template.body === 'string' ? template.body : '';

            if (typeof editorApi?.setValue === 'function') {
                editorApi.setValue(body);
            } else {
                setInputValue('isi', body);
            }
        });

        function setInputValue(id, value) {
            const field = document.getElementById(id);

            if (!field) {
                return;
            }

            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function initTemplateUpload(editorApi) {
        const uploadInput = document.querySelector('[data-template-upload]');
        const statusEl = document.querySelector('[data-template-status]');
        const tujuanInput = document.getElementById('tujuan');
        const perihalInput = document.getElementById('perihal');
        const tembusanInput = document.getElementById('tembusan');
        const hiddenBodyInput = document.querySelector('[data-letter-body-input]');
        const form = uploadInput ? uploadInput.closest('form') : null;
        const tokenInput = form ? form.querySelector('input[name="_token"]') : null;
        const uploadUrl = uploadInput ? uploadInput.getAttribute('data-upload-url') : null;

        if (!uploadInput) {
            return;
        }

        uploadInput.addEventListener('change', () => {
            const file = uploadInput.files && uploadInput.files[0];
            if (!file) {
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                setStatus('Ukuran file melebihi 5MB. Gunakan file yang lebih kecil.', true);
                uploadInput.value = '';
                return;
            }

            if (!tokenInput || !tokenInput.value) {
                setStatus('Token keamanan tidak ditemukan. Muat ulang halaman lalu coba lagi.', true);
                uploadInput.value = '';
                return;
            }

            if (!uploadUrl) {
                setStatus('URL unggah template tidak tersedia.', true);
                uploadInput.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('_token', tokenInput.value);
            formData.append('template_surat', file);

            setStatus('Memproses template surat...', false, 'loading');
            uploadInput.disabled = true;

            fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then(async (response) => {
                    const rawBody = await response.text();
                    let payload = null;

                    if (rawBody.trim() !== '') {
                        try {
                            payload = JSON.parse(rawBody);
                        } catch (_error) {
                            if (!response.ok) {
                                throw new Error(extractPlainText(rawBody));
                            }
                        }
                    }

                    if (!response.ok || !payload) {
                        const message = payload && typeof payload.message === 'string'
                            ? payload.message
                            : extractPlainText(rawBody !== undefined ? rawBody : '') || 'Template surat tidak dapat diproses.';
                        throw new Error(message);
                    }

                    return payload;
                })
                .then((payload) => {
                    const data = payload.data || {};

                    if (tujuanInput && typeof data.tujuan === 'string' && data.tujuan !== '') {
                        tujuanInput.value = data.tujuan;
                    }

                    if (perihalInput && typeof data.perihal === 'string' && data.perihal !== '') {
                        perihalInput.value = data.perihal;
                    }

                    if (tembusanInput) {
                        tembusanInput.value = typeof data.tembusan === 'string' ? data.tembusan : '';
                    }

                    const rawBody = typeof data.isi === 'string' ? data.isi : '';
                    const bodyValue = rawBody && rawBody.includes('<')
                        ? rawBody
                        : plainTextToHtml(rawBody);

                    if (typeof editorApi?.setValue === 'function') {
                        editorApi.setValue(bodyValue);
                    } else if (hiddenBodyInput) {
                        hiddenBodyInput.value = bodyValue;
                    }

                    setStatus(payload.message || 'Template surat berhasil dianalisis.', false);
                })
                .catch((error) => {
                    const message = error instanceof Error ? error.message : 'Template surat tidak dapat diproses.';
                    setStatus(message, true);
                })
                .finally(() => {
                    uploadInput.value = '';
                    uploadInput.disabled = false;
                });
        });

        function setStatus(message, isError, state) {
            if (!statusEl) {
                return;
            }

            statusEl.hidden = false;
            statusEl.textContent = message;
            statusEl.classList.remove('text-emerald-600', 'text-slate-500', 'text-rose-600');

            if (isError) {
                statusEl.classList.add('text-rose-600');
            } else if (state === 'loading') {
                statusEl.classList.add('text-slate-500');
            } else {
            statusEl.classList.add('text-emerald-600');
            }
        }
    }


    function plainTextToHtml(text) {
        const value = (text || '').replace(/\r\n/g, '\n');
        const lines = value.split('\n');
        const blocks = [];
        let listType = null;
        let listItems = [];

        const flushList = () => {
            if (listItems.length === 0) {
                return;
            }
            const tag = listType === 'ol' ? 'ol' : 'ul';
            blocks.push(`<${tag}>${listItems.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</${tag}>`);
            listItems = [];
            listType = null;
        };

        lines.forEach((line) => {
            const decoded = line.replace(/&nbsp;/gi, ' ').replace(/&amp;nbsp;/gi, ' ');
            const trimmed = decoded.trim();

            if (trimmed === '') {
                flushList();
                return;
            }

            if (/^(?:-|\*|•)\s+/.test(trimmed)) {
                const content = trimmed.replace(/^(?:-|\*|•)\s+/, '').trim();
                if (listType !== 'ul') {
                    flushList();
                    listType = 'ul';
                }
                listItems.push(content);
                return;
            }

            if (/^(?:\d+|[a-z])[\.)]\s+/.test(trimmed)) {
                const content = trimmed.replace(/^(?:\d+|[a-z])[\.)]\s+/, '').trim();
                if (listType !== 'ol') {
                    flushList();
                    listType = 'ol';
                }
                listItems.push(content);
                return;
            }

            flushList();
            blocks.push(`<p>${escapeHtml(trimmed)}</p>`);
        });

        flushList();

        return blocks.join('\n');
    }

    function escapeHtml(text) {
        return (text || '').replace(/[&<>"']/g, (char) => {
            switch (char) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#039;';
                default:
                    return char;
            }
        });
    }

    function extractPlainText(htmlOrText) {
        if (typeof htmlOrText !== 'string' || htmlOrText.trim() === '') {
            return '';
        }

        const temp = document.createElement('div');
        temp.innerHTML = htmlOrText;

        return temp.textContent ? temp.textContent.trim() : htmlOrText.trim();
    }

</script>
