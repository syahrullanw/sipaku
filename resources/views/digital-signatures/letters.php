<?php
$records = is_array($records ?? null) ? $records : [];
$statusFilter = in_array($statusFilter ?? 'pending', ['pending', 'approved', 'revoked', 'all'], true) ? $statusFilter : 'pending';
$digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
$activeYear = is_array($activeYear ?? null) ? $activeYear : [];
$headmasterName = (string) ($headmasterName ?? '');
$activeYearName = $activeYear['nama'] ?? '-';
$activeSemester = isset($activeYear['semester_aktif']) ? (int) $activeYear['semester_aktif'] : 1;
$semesterLabel = $activeSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';

$statusLabels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'revoked' => 'Dicabut',
    'all' => 'Semua',
];

$statusBadgeClasses = [
    'pending' => 'border-amber-200 bg-amber-50 text-amber-600',
    'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-600',
    'revoked' => 'border-rose-200 bg-rose-50 text-rose-600',
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
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Persetujuan Persuratan</h2>
                <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-xs text-slate-500">
                    Kepala Sekolah: <span class="font-semibold text-slate-700"><?= htmlspecialchars($headmasterName !== '' ? $headmasterName : '-', ENT_QUOTES, 'UTF-8') ?></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $digitalSignatureEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                    <?= $digitalSignatureEnabled ? 'TTD Digital Aktif' : 'TTD Digital Nonaktif' ?>
                </span>
            </div>
        </div>

        <?php if (!$digitalSignatureEnabled): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                TTD digital belum diaktifkan oleh admin. Aktifkan fitur ini sebelum menyetujui surat agar QR dapat diterbitkan.
            </div>
        <?php endif; ?>

        <form method="get" class="mt-6 flex flex-wrap items-end gap-4">
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Filter Status</label>
                <select
                    id="status"
                    name="status"
                    class="mt-2 block w-48 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                >
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $statusFilter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200"
                >
                    Terapkan
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

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold text-slate-800">Daftar Pengajuan Surat</h3>
            <span class="text-xs text-slate-400"><?= htmlspecialchars((string) count($records), ENT_QUOTES, 'UTF-8') ?> dokumen</span>
        </div>

        <?php if (empty($records)): ?>
            <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center">
                <i class="ri-mail-open-line text-3xl text-slate-300"></i>
                <p class="mt-3 text-sm font-semibold text-slate-700">Belum ada pengajuan persuratan pada status ini.</p>
                <p class="mt-2 text-xs text-slate-500">
                    Surat baru akan muncul setelah diajukan oleh Tata Usaha melalui modul persuratan.
                </p>
            </div>
        <?php else: ?>
            <div class="mt-6 space-y-4">
                <?php foreach ($records as $record): ?>
                    <?php
                        $documentTitle = $record['document_title'] ?? '-';
                        $documentType = (string) ($record['document_type'] ?? '');
                        $documentTypeLabel = $documentTypeLabels[$documentType] ?? ucfirst(str_replace('_', ' ', $documentType));
                        $status = (string) ($record['status'] ?? 'pending');
                        $statusBadgeClass = $statusBadgeClasses[$status] ?? 'border-slate-200 bg-slate-50 text-slate-500';
                        $letterInfo = is_array($record['letter'] ?? null) ? $record['letter'] : [];
                        $letterNumber = $letterInfo['number'] ?? '-';
                        $letterSubject = $letterInfo['subject'] ?? '-';
                        $signDateLabel = $letterInfo['sign_date_formatted'] ?? ($letterInfo['sign_date'] ?? '');
                        $effectiveStart = $letterInfo['effective_start_formatted'] ?? null;
                        $effectiveEnd = $letterInfo['effective_end_formatted'] ?? null;
                        $requestedAt = $record['requested_at_formatted'] ?? null;
                        $approvedAt = $record['approved_at_formatted'] ?? null;
                        $verificationUrl = $record['verification_url'] ?? null;
                        $approvalNote = $record['approval_note'] ?? null;
                        $payload = is_array($record['payload_data'] ?? null) ? $record['payload_data'] : [];
                        $submittedBy = isset($record['requested_by']) ? (int) $record['requested_by'] : null;
                        $documentId = (int) ($record['id'] ?? 0);
                        $pdfMeta = is_array($record['pdf_meta'] ?? null)
                            ? $record['pdf_meta']
                            : (is_array($payload['pdf'] ?? null) ? $payload['pdf'] : []);
                        $pdfUrl = $pdfMeta['url'] ?? ($pdfMeta['path'] ?? null);
                        $signedPdfUrl = $pdfMeta['signed_url'] ?? ($pdfMeta['signed_path'] ?? null);
                        $pdfOptions = is_array($pdfMeta['options'] ?? $pdfMeta['signature_options'] ?? null) ? ($pdfMeta['options'] ?? $pdfMeta['signature_options']) : null;
                        $requiresPdfSignature = ($pdfUrl !== null && $pdfUrl !== '')
                            || ($signedPdfUrl !== null && $signedPdfUrl !== '')
                            || is_array($pdfOptions);
                        $pdfMissing = $requiresPdfSignature && ($pdfUrl === null || $pdfUrl === '');
                    ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 md:flex-row md:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                                        <?= htmlspecialchars($documentTypeLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <?php if ($letterNumber !== null && $letterNumber !== ''): ?>
                                    <p class="mt-1 text-xs text-slate-500">Nomor Surat: <?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($letterSubject !== null && $letterSubject !== ''): ?>
                                    <p class="mt-1 text-xs text-slate-500">Perihal: <?= htmlspecialchars($letterSubject, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($signDateLabel): ?>
                                    <p class="mt-1 text-xs text-slate-500">Tanggal Penetapan: <?= htmlspecialchars($signDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($effectiveStart && $effectiveEnd): ?>
                                    <p class="mt-1 text-xs text-slate-500">Masa Berlaku: <?= htmlspecialchars($effectiveStart, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= htmlspecialchars($effectiveEnd, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($requestedAt): ?>
                                    <p class="mt-2 text-xs text-slate-400">Diajukan pada <?= htmlspecialchars($requestedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($pdfUrl): ?>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                        <a
                                            href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-1 font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                                        >
                                            <i class="ri-file-pdf-line text-sm text-rose-500"></i>
                                            PDF Asli
                                        </a>
                                        <?php if ($signedPdfUrl): ?>
                                            <a
                                                href="<?= htmlspecialchars($signedPdfUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1 rounded border border-emerald-200 px-2 py-1 font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50"
                                            >
                                                <i class="ri-shield-check-line text-sm"></i>
                                                PDF Bertanda Tangan
                                            </a>
                                        <?php endif; ?>
                                        <?php if (is_array($pdfOptions)): ?>
                                            <span class="inline-flex items-center rounded-full border border-slate-200 px-2 py-0.5 font-semibold text-slate-600">
                                                Hal. <?= htmlspecialchars((string) ($pdfOptions['page'] ?? 1), ENT_QUOTES, 'UTF-8') ?> · X <?= htmlspecialchars(number_format((float) ($pdfOptions['x_percent'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>% · Y <?= htmlspecialchars(number_format((float) ($pdfOptions['y_percent'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>% · W <?= htmlspecialchars(number_format((float) ($pdfOptions['width_percent'] ?? 0), 0), ENT_QUOTES, 'UTF-8') ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payload !== []): ?>
                                    <details class="mt-3 text-xs text-slate-500">
                                        <summary class="cursor-pointer text-indigo-600 hover:text-indigo-500">Detail data surat</summary>
                                        <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-900 p-3 font-mono text-[11px] leading-relaxed text-slate-100"><?= htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-start gap-3 md:flex-col md:items-end">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($approvedAt): ?>
                                    <p class="text-xs text-emerald-600">Disetujui <?= htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php elseif ($status === 'pending'): ?>
                                    <p class="text-xs text-slate-500">Menunggu persetujuan</p>
                                <?php endif; ?>
                                <?php if ($approvalNote): ?>
                                    <p class="mt-2 max-w-xs text-right text-xs text-slate-500">Catatan: <?= htmlspecialchars($approvalNote, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if ($verificationUrl): ?>
                                    <a
                                        href="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                                    >
                                        <i class="ri-external-link-line text-sm"></i>
                                        Lihat halaman verifikasi
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <?php if ($status === 'pending'): ?>
                                <form action="<?= htmlspecialchars(base_url('kepala-sekolah/persuratan/' . $documentId . '/approve'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="kepala-sekolah/persuratan" />
                                    <?php if ($pdfMissing): ?>
                                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                            File PDF belum tersedia. Minta Tata Usaha mengunggah PDF dan menetapkan posisi QR sebelum menyetujui.
                                        </div>
                                    <?php endif; ?>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="approval_note_<?= (int) $documentId ?>">Catatan (opsional)</label>
                                    <textarea
                                        id="approval_note_<?= (int) $documentId ?>"
                                        name="approval_note"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        rows="2"
                                        placeholder="Tuliskan catatan jika diperlukan"
                                    ></textarea>
                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
                                        <?= $digitalSignatureEnabled && (!$requiresPdfSignature || $pdfUrl) ? '' : 'disabled' ?>
                                    >
                                        <i class="ri-checkbox-circle-line text-base"></i>
                                        Setujui &amp; Terbitkan QR
                                    </button>
                                </form>
                            <?php elseif ($status === 'approved'): ?>
                                <form action="<?= htmlspecialchars(base_url('kepala-sekolah/persuratan/' . $documentId . '/reset'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="kepala-sekolah/persuratan" />
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring focus:ring-rose-200"
                                    >
                                        <i class="ri-restart-line text-base"></i>
                                        Tarik Persetujuan
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-rose-600">Dokumen berada pada status dicabut. Ajukan ulang dari Tata Usaha setelah revisi.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
