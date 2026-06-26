<?php
$letter = is_array($letter ?? null) ? $letter : [];
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$tembusanLines = is_array($letter['tembusan_lines'] ?? null) ? $letter['tembusan_lines'] : [];
$bodyText = isset($letter['body_text']) ? (string) $letter['body_text'] : '';
$returnYearId = (int) ($letter['tahun_ajaran_id'] ?? 0);
$returnUrl = base_url('tata-usaha/persuratan');

if ($returnYearId > 0) {
    $returnUrl .= '?tahun_ajaran_id=' . rawurlencode((string) $returnYearId) . '#surat-keluar';
} else {
    $returnUrl .= '#surat-keluar';
}

$printUrl = base_url('tata-usaha/persuratan/surat-keluar/' . ($letter['id'] ?? 0) . '/cetak');
$deleteUrl = base_url('tata-usaha/persuratan/surat-keluar/' . ($letter['id'] ?? 0) . '/hapus');
$number = trim((string) ($letter['nomor_surat'] ?? ''));
$typeLabel = trim((string) ($letter['jenis_label'] ?? ''));
$unitCode = trim((string) ($letter['unit_kode'] ?? ''));
$subject = trim((string) ($letter['perihal'] ?? ''));
$recipient = trim((string) ($letter['tujuan'] ?? ''));
$attachment = trim((string) ($letter['lampiran'] ?? ''));
$attachments = is_array($letter['lampiran_records'] ?? null) ? array_values($letter['lampiran_records']) : [];
$signer = trim((string) ($letter['tanda_tangan'] ?? ''));
$note = trim((string) ($letter['catatan'] ?? ''));
$letterDate = trim((string) ($letter['tanggal_surat_formatted'] ?? ($letter['tanggal_surat'] ?? '')));
$recordedDate = trim((string) ($letter['tanggal_dicatat_formatted'] ?? ($letter['tanggal_dicatat'] ?? '')));
$createdAt = trim((string) ($letter['created_at_formatted'] ?? ($letter['created_at'] ?? '')));
$updatedAt = trim((string) ($letter['updated_at_formatted'] ?? ($letter['updated_at'] ?? '')));
$letterheadPath = $schoolProfile['kop_surat'] ?? '';
$letterheadUrl = $letterheadPath !== '' ? asset($letterheadPath) : null;
$pdfMeta = is_array($letter['pdf'] ?? null) ? $letter['pdf'] : [];
$pdfUrl = isset($pdfMeta['url']) ? (string) $pdfMeta['url'] : null;
$signedPdfUrl = isset($pdfMeta['signed_url']) ? (string) $pdfMeta['signed_url'] : null;
$pdfOptions = is_array($pdfMeta['options'] ?? null) ? $pdfMeta['options'] : null;
$previewUrl = $letterId > 0 ? base_url('tata-usaha/persuratan/surat-keluar/' . $letterId . '/preview-ttd') : null;
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Detail Surat Keluar</h1>
            <?php if ($number !== ''): ?>
                <p class="mt-1 text-sm text-slate-500">Nomor: <?= htmlspecialchars($number, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($typeLabel !== ''): ?>
                <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50"
            >
                <i class="ri-arrow-left-line text-base"></i>
                Kembali
            </a>
            <a
                href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50"
            >
                <i class="ri-printer-line text-base"></i>
                Cetak
            </a>
            <?php if ($previewUrl !== null): ?>
                <a
                    href="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:border-amber-300 hover:bg-amber-50"
                >
                    <i class="ri-focus-2-line text-base"></i>
                    Atur QR PDF
                </a>
            <?php endif; ?>
            <form
                method="post"
                action="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>"
                onsubmit="return confirm('Hapus surat keluar ini?');"
                class="inline-flex"
            >
                <?= csrf_field() ?>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                >
                    <i class="ri-delete-bin-line text-base"></i>
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid gap-6 md:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Surat</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($typeLabel !== '' ? $typeLabel : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Unit</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($unitCode !== '' ? $unitCode : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Surat</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($letterDate !== '' ? $letterDate : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Dicatat</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($recordedDate !== '' ? $recordedDate : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perihal</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($subject !== '' ? $subject : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tujuan</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($recipient !== '' ? $recipient : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</dt>
        <dd class="mt-1 text-sm text-slate-700">
                    <?= $attachment !== '' ? nl2br(htmlspecialchars($attachment, ENT_QUOTES, 'UTF-8')) : '-' ?>
        </dd>
    </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penandatangan</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($signer !== '' ? $signer : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <?php if ($schoolProfile !== null): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profil Sekolah</dt>
                    <dd class="mt-1 text-sm text-slate-700">
                        <?= htmlspecialchars($schoolProfile['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
            <?php endif; ?>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dibuat Pada</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($createdAt !== '' ? $createdAt : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Terakhir Diperbarui</dt>
                <dd class="mt-1 text-sm text-slate-700">
                    <?= htmlspecialchars($updatedAt !== '' ? $updatedAt : '-', ENT_QUOTES, 'UTF-8') ?>
                </dd>
            </div>
        </dl>

        <?php
            $requiresHeadmasterDigital = (bool) ($letter['requires_headmaster_digital_signature'] ?? false);
            $signature = is_array($letter['digital_signature'] ?? null) ? $letter['digital_signature'] : null;
            $signatureMissing = (bool) ($letter['digital_signature_missing'] ?? false);
        ?>

        <?php if ($requiresHeadmasterDigital): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Status TTD Digital Kepala Sekolah</h2>
                <?php if ($signature !== null): ?>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 font-semibold <?= htmlspecialchars($signature['status_class'] ?? 'border-slate-200 bg-slate-50 text-slate-500', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($signature['status_label'] ?? 'Status tidak diketahui', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if (!empty($signature['verification_url'])): ?>
                            <a
                                href="<?= htmlspecialchars($signature['verification_url'], ENT_QUOTES, 'UTF-8') ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-500"
                            >
                                <i class="ri-external-link-line"></i>
                                Verifikasi
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($signatureMissing): ?>
                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                        TTD digital kepala sekolah belum diajukan atau gagal diproses. Ajukan ulang melalui menu TTD digital.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($pdfUrl !== null): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Berkas PDF</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <a href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded border border-slate-200 px-2.5 py-1 font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50">
                        <i class="ri-file-pdf-line text-sm text-rose-500"></i>
                        PDF Asli
                    </a>
                    <?php if ($signedPdfUrl !== null): ?>
                        <a href="<?= htmlspecialchars($signedPdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded border border-emerald-200 px-2.5 py-1 font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50">
                            <i class="ri-shield-check-line text-sm"></i>
                            PDF Bertanda Tangan
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-semibold text-amber-600">
                            <i class="ri-time-line text-sm"></i>
                            Menunggu QR disematkan
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($pdfOptions !== null): ?>
                    <p class="mt-2 text-[11px] text-slate-500">
                        Posisi QR: halaman <?= htmlspecialchars((string) ($pdfOptions['page'] ?? 1), ENT_QUOTES, 'UTF-8') ?> · X <?= htmlspecialchars(number_format((float) ($pdfOptions['x_percent'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>% · Y <?= htmlspecialchars(number_format((float) ($pdfOptions['y_percent'] ?? 0), 1), ENT_QUOTES, 'UTF-8') ?>% · Lebar <?= htmlspecialchars(number_format((float) ($pdfOptions['width_percent'] ?? 0), 0), ENT_QUOTES, 'UTF-8') ?>%
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($letterheadUrl !== null): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Kop Surat Aktif</h2>
                <div class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <img
                        src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Kop Surat"
                        class="h-auto w-full"
                    />
                </div>
            </div>
        <?php endif; ?>

        <?php if ($bodyText !== ''): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Isi Surat</h2>
                <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 whitespace-pre-line">
                    <?= nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($attachments)): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Lampiran Surat</h2>
                <div class="mt-2 space-y-6">
                    <?php foreach ($attachments as $entry): ?>
                        <?php
                            $attachmentIndex = isset($entry['number']) ? (int) $entry['number'] : 0;
                            $attachmentHtml = isset($entry['body_html']) ? (string) $entry['body_html'] : '';
                            $attachmentText = isset($entry['body_text']) ? trim((string) $entry['body_text']) : '';
                            $attachmentLabel = $attachmentIndex > 0
                                ? sprintf('Lampiran %d', $attachmentIndex)
                                : 'Lampiran';
                            $hasAttachmentContent = $attachmentHtml !== '' && $attachmentText !== '';
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($attachmentLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="text-xs text-slate-500">Halaman terpisah saat dicetak</span>
                            </div>
                            <div class="prose prose-sm mt-3 max-w-none text-slate-700">
                                <?= $hasAttachmentContent ? $attachmentHtml : '<p class="text-slate-400">Tidak ada konten pada lampiran ini.</p>' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($tembusanLines)): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Tembusan</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
                    <?php foreach ($tembusanLines as $line): ?>
                        <li><?= htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($note !== ''): ?>
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Catatan Internal</h2>
                <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 whitespace-pre-line">
                    <?= nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
