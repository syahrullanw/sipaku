<?php
$yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
$selectedYearId = (int) ($selectedYearId ?? 0);
$digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
$letterTypeOptions = is_array($letterTypeOptions ?? null) ? $letterTypeOptions : [];
$defaultUnitCode = isset($defaultUnitCode) && is_string($defaultUnitCode) ? trim($defaultUnitCode) : '';
$letterheadUrl = isset($letterheadUrl) && is_string($letterheadUrl) ? $letterheadUrl : null;
$nextOutgoingSequenceLabel = isset($nextOutgoingSequenceLabel) ? (string) $nextOutgoingSequenceLabel : '001';
$todayDate = $todayDate ?? date('Y-m-d');
$defaultPdfPage = max(1, (int) ($defaultPdfPage ?? 1));
$defaultPdfX = (float) ($defaultPdfX ?? 70);
$defaultPdfY = (float) ($defaultPdfY ?? 65);
$defaultPdfWidth = (float) ($defaultPdfWidth ?? 20);
$defaultCaptionX = isset($defaultCaptionX) ? (float) $defaultCaptionX : $defaultPdfX + 2;
$defaultCaptionY = isset($defaultCaptionY) ? (float) $defaultCaptionY : $defaultPdfY + $defaultPdfWidth + 2;
$defaultCaptionWidth = isset($defaultCaptionWidth) ? (float) $defaultCaptionWidth : $defaultPdfWidth + 10;
$defaultCaptionX = max(0, min(100, $defaultCaptionX));
$defaultCaptionY = max(0, min(100, $defaultCaptionY));
$defaultCaptionWidth = max(10, min(80, $defaultCaptionWidth));
$headmasterOption = is_array($headmasterOption ?? null) ? $headmasterOption : null;
$signerDefault = isset($signerDefault) ? (string) $signerDefault : 'Kepala Sekolah';
$defaultCityValue = isset($defaultCity) && is_string($defaultCity) ? $defaultCity : '';
$defaultTitimangsaValue = date('d F Y');
$defaultHeadmasterName = isset($headmasterOption['name']) ? (string) $headmasterOption['name'] : '';
$letterheadAvailable = isset($letterheadUrl) && $letterheadUrl !== null;
$defaultUseLetterhead = old('use_letterhead', '0') === '1';
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$defaultSchoolName = isset($schoolProfile['nama']) ? (string) $schoolProfile['nama'] : 'Sekolah';
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-base font-semibold text-slate-800">Pencatatan Surat Keluar (PDF)</h1>
                <p class="mt-1 text-sm text-slate-500">Gunakan halaman ini khusus untuk surat yang sudah siap dalam format PDF dan perlu dibubuhi QR TTD digital.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="<?= htmlspecialchars(base_url('tata-usaha/persuratan#surat-keluar'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50"
                >
                    <i class="ri-arrow-left-line text-base"></i>
                    Kembali ke Persuratan
                </a>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $digitalSignatureEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                    <?= $digitalSignatureEnabled ? 'TTD Digital Aktif' : 'TTD Digital Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Form Surat Keluar PDF</h2>
                <p class="mt-1 text-sm text-slate-500">Nomor otomatis akan mengikuti urutan terbaru: <span class="font-semibold text-indigo-700"><?= htmlspecialchars($nextOutgoingSequenceLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-700">
                Surat manual dan surat PDF dipisahkan agar lebih rapi. Gunakan halaman ini hanya bila surat sudah jadi dalam PDF.
            </div>
        </div>

        <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/persuratan/surat-keluar'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4" enctype="multipart/form-data" data-outgoing-letter-form>
            <?= csrf_field() ?>
            <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="mode_pdf" value="1">

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="tahun_ajaran_id_select" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun Ajaran</label>
                    <select
                        id="tahun_ajaran_id_select"
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
                    <label for="jenis_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Surat</label>
                    <select
                        id="jenis_surat"
                        name="jenis_surat"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    >
                        <?php foreach ($letterTypeOptions as $value => $option): ?>
                            <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(sprintf('%s - %s', $option['code'] ?? '-', $option['label'] ?? $value), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="unit_kode" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Unit / Lembaga</label>
                    <input
                        type="text"
                        id="unit_kode"
                        name="unit_kode"
                        value="<?= htmlspecialchars($defaultUnitCode, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Misal: SMA-SM"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="tujuan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tujuan Surat</label>
                    <input
                        type="text"
                        id="tujuan"
                        name="tujuan"
                        placeholder="Nama penerima / instansi tujuan"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
                <div>
                    <label for="perihal" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Perihal</label>
                    <input
                        type="text"
                        id="perihal"
                        name="perihal"
                        placeholder="Misal: Permohonan Kerjasama"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="tanggal_surat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal Surat</label>
                    <input
                        type="date"
                        id="tanggal_surat"
                        name="tanggal_surat"
                        value="<?= htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8') ?>"
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
                        value="<?= htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="lampiran" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Lampiran</label>
                    <input
                        type="text"
                        id="lampiran"
                        name="lampiran"
                        placeholder="Opsional, misal: 2 lembar"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
                <div>
                    <label for="tanda_tangan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Penandatangan</label>
                    <input
                        type="text"
                        id="tanda_tangan"
                        name="tanda_tangan"
                        value="<?= htmlspecialchars($signerDefault, ENT_QUOTES, 'UTF-8') ?>"
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
                        checked
                    />
                    <label for="ajukan_ttd" class="flex-1">
                        Ajukan TTD digital kepala sekolah untuk surat ini (disarankan tetap aktif agar verifikasi QR tersedia).
                    </label>
                </div>
            <?php endif; ?>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">File PDF untuk TTD Digital</p>
                        <p class="text-xs text-slate-500">Unggah PDF final surat. QR akan ditempatkan sesuai posisi di preview.</p>
                    </div>
                    <div class="text-xs text-indigo-600">
                        <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-semibold">
                            <i class="ri-focus-2-line text-sm"></i>
                            Atur posisi QR di preview
                        </span>
                    </div>
                </div>

                <div class="mt-3 space-y-3">
                    <input
                        type="file"
                        name="surat_pdf"
                        accept="application/pdf,.pdf"
                        class="block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-50"
                        data-pdf-input
                        required
                    />
                    <div class="flex items-center gap-3 text-xs">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                            data-pdf-preview-btn
                        >
                            <i class="ri-eye-line text-sm"></i>
                            Tampilkan Preview
                        </button>
                        <span class="text-slate-500" data-pdf-status>Preview muncul otomatis setelah file dipilih.</span>
                    </div>

                <div class="relative overflow-hidden rounded-xl border border-dashed border-slate-200 bg-white" data-pdf-preview>
                    <canvas class="block w-full" data-pdf-canvas></canvas>
                    <div class="absolute inset-0 z-20 cursor-move rounded-lg border-2 border-indigo-500 bg-indigo-500/10 shadow-lg" data-qr-overlay hidden></div>
                    <div class="absolute inset-0 flex items-center justify-center text-xs text-slate-400" data-pdf-placeholder>
                        Pilih PDF untuk menampilkan preview dan mengatur posisi QR.
                    </div>
                </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Halaman</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" class="inline-flex items-center justify-center rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50" data-pdf-page-prev>
                                        <i class="ri-arrow-left-s-line text-sm"></i>
                                    </button>
                                    <input
                                        type="number"
                                        name="pdf_signature_page"
                                        min="1"
                                        value="<?= htmlspecialchars((string) $defaultPdfPage, ENT_QUOTES, 'UTF-8') ?>"
                                        class="h-10 w-16 rounded border border-slate-200 px-2 text-center text-sm"
                                        data-pdf-page-input
                                    />
                                    <span class="text-xs text-slate-500">/ <span data-pdf-page-total>1</span></span>
                                    <button type="button" class="inline-flex items-center justify-center rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50" data-pdf-page-next>
                                        <i class="ri-arrow-right-s-line text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ukuran QR</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <input type="range" min="8" max="60" step="1" value="<?= htmlspecialchars((string) $defaultPdfWidth, ENT_QUOTES, 'UTF-8') ?>" class="h-2 w-full cursor-pointer accent-indigo-600" data-qr-size-input />
                                    <span class="w-12 text-right text-xs text-slate-600"><span data-qr-size-label><?= htmlspecialchars((string) $defaultPdfWidth, ENT_QUOTES, 'UTF-8') ?></span>%</span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Posisi</p>
                                <div class="text-xs text-slate-600">
                                    X: <span data-qr-x-label><?= htmlspecialchars(number_format($defaultPdfX, 1), ENT_QUOTES, 'UTF-8') ?></span>% · Y: <span data-qr-y-label><?= htmlspecialchars(number_format($defaultPdfY, 1), ENT_QUOTES, 'UTF-8') ?></span>%
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button type="button" class="inline-flex items-center gap-1 rounded border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50" data-qr-center>
                                        <i class="ri-focus-2-line text-sm"></i>
                                        Tengah
                                    </button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50" data-qr-reset>
                                        <i class="ri-refresh-line text-sm"></i>
                                        Reset
                                    </button>
                                </div>
                                <p class="text-[11px] leading-relaxed text-slate-500">Geser kotak QR pada preview untuk menentukan posisi tanda tangan digital. Koordinat tersimpan saat disimpan.</p>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600">
                            <p class="font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
                            <p>Pastikan PDF sudah final sebelum disimpan. Jika perlu revisi, unggah ulang PDF dan simpan untuk memperbarui posisi QR.</p>
                            <?php if ($letterheadUrl !== null): ?>
                                <div>
                                    <p class="mt-2 font-semibold text-slate-600">Kop Aktif</p>
                                    <img src="<?= htmlspecialchars($letterheadUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kop Surat" class="mt-1 rounded-lg border border-slate-200" />
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <input type="hidden" name="pdf_signature_x" value="<?= htmlspecialchars((string) $defaultPdfX, ENT_QUOTES, 'UTF-8') ?>" data-qr-x-input>
                <input type="hidden" name="pdf_signature_y" value="<?= htmlspecialchars((string) $defaultPdfY, ENT_QUOTES, 'UTF-8') ?>" data-qr-y-input>
                <input type="hidden" name="pdf_signature_width" value="<?= htmlspecialchars((string) $defaultPdfWidth, ENT_QUOTES, 'UTF-8') ?>" data-qr-width-input>
                    </div>
                </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_headmaster_name">Nama Kepala Sekolah (TTD PDF)</label>
                <input
                    type="text"
                    id="signature_headmaster_name"
                    name="signature_headmaster_name"
                    value="<?= htmlspecialchars($defaultHeadmasterName, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Nama Kepala Sekolah"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                />
            </div>

            <input type="hidden" name="signature_school_name" value="<?= htmlspecialchars($defaultSchoolName, ENT_QUOTES, 'UTF-8') ?>">

            <input type="hidden" name="signature_mode" value="metadata">

            <div data-metadata-fields>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_city">Kota / Titimangsa (opsional)</label>
                        <input
                            type="text"
                            id="signature_city"
                            name="signature_city"
                            value="<?= htmlspecialchars($defaultCityValue, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Misal: Indramayu"
                            class="mt-1 block w-full rounded border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_titimangsa">Titimangsa (opsional)</label>
                        <input
                            type="text"
                            id="signature_titimangsa"
                            name="signature_titimangsa"
                            value="<?= htmlspecialchars($defaultTitimangsaValue, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Misal: 02 Desember 2025"
                            class="mt-1 block w-full rounded border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_meta_title">Judul Metadata</label>
                <input
                    type="text"
                    id="signature_meta_title"
                    name="signature_meta_title"
                    value="TTD Disetujui Kepala Sekolah"
                    class="mt-1 block w-full rounded border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                />
                <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_meta_note">Catatan Metadata</label>
                <textarea
                    id="signature_meta_note"
                    name="signature_meta_note"
                    rows="2"
                    class="mt-1 block w-full rounded border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                >Disahkan oleh <?= htmlspecialchars($defaultHeadmasterName, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="flex items-center gap-2 text-sm text-slate-600">
                <input type="hidden" name="use_letterhead" value="0">
                <input
                    type="checkbox"
                    id="use_letterhead"
                    name="use_letterhead"
                    value="1"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    <?= $defaultUseLetterhead ? 'checked' : '' ?>
                    <?= !$letterheadAvailable ? 'disabled' : '' ?>
                />
                <label for="use_letterhead">Gunakan kop sekolah di header PDF <?= !$letterheadAvailable ? '(kop belum tersedia)' : '' ?></label>
            </div>

            <div>
                <label for="tembusan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tembusan</label>
                <textarea
                    id="tembusan"
                    name="tembusan"
                    rows="2"
                    placeholder="Tuliskan satu penerima per baris (opsional)"
                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                ></textarea>
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                >
                    <i class="ri-send-plane-line text-base"></i>
                    Simpan Surat PDF &amp; Generate Nomor
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= htmlspecialchars(asset('js/vendor/pdf.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        initPdfPreview();
    });

    function initPdfPreview() {
        const pdfjsLib = window.pdfjsLib || window['pdfjs-dist/build/pdf'] || null;
        const canvas = document.querySelector('[data-pdf-canvas]');
        const overlay = document.querySelector('[data-qr-overlay]');
        if (overlay && !overlay.querySelector('[data-block-qr]')) {
            overlay.innerHTML = '<div data-block-qr class="pointer-events-none absolute rounded bg-indigo-200/50 border border-indigo-400 shadow-sm"></div><div data-block-text class="pointer-events-none absolute text-[12px] leading-tight text-slate-900"></div>';
        }
        const blockQrBox = overlay ? overlay.querySelector('[data-block-qr]') : null;
        const blockTextBox = overlay ? overlay.querySelector('[data-block-text]') : null;
        const placeholder = document.querySelector('[data-pdf-placeholder]');
        const fileInput = document.querySelector('[data-pdf-input]');
        const previewBtn = document.querySelector('[data-pdf-preview-btn]');
        const statusEl = document.querySelector('[data-pdf-status]');
        const pageInput = document.querySelector('[data-pdf-page-input]');
        const pageTotal = document.querySelector('[data-pdf-page-total]');
        const prevBtn = document.querySelector('[data-pdf-page-prev]');
        const nextBtn = document.querySelector('[data-pdf-page-next]');
        const sizeInput = document.querySelector('[data-qr-size-input]');
        const sizeLabel = document.querySelector('[data-qr-size-label]');
        const xLabel = document.querySelector('[data-qr-x-label]');
        const yLabel = document.querySelector('[data-qr-y-label]');
        const xInput = document.querySelector('[data-qr-x-input]');
        const yInput = document.querySelector('[data-qr-y-input]');
        const widthInput = document.querySelector('[data-qr-width-input]');
        const metaTitleInput = document.getElementById('signature_meta_title');
        const metaNoteInput = document.getElementById('signature_meta_note');
        const cityInput = document.querySelector('input[name="signature_city"]');
        const titimangsaInput = document.querySelector('input[name="signature_titimangsa"]');
        const headmasterInput = document.querySelector('input[name="signature_headmaster_name"]');
        const centerBtn = document.querySelector('[data-qr-center]');
        const resetBtn = document.querySelector('[data-qr-reset]');
        const defaultHeadmasterFallback = "<?= htmlspecialchars($defaultHeadmasterName, ENT_QUOTES, 'UTF-8') ?>";

        if (!pdfjsLib || !canvas || !overlay) {
            if (statusEl) {
                statusEl.textContent = 'Preview gagal dimuat (pdf.js tidak tersedia).';
                statusEl.classList.add('text-rose-600');
            }
            return;
        }

        if (pdfjsLib.GlobalWorkerOptions) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "<?= htmlspecialchars(asset('js/vendor/pdf.worker.min.js'), ENT_QUOTES, 'UTF-8') ?>";
        }
        if (pdfjsLib.disableWorker !== undefined) {
            pdfjsLib.disableWorker = false;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let pdfDoc = null;
        let currentPage = clampNumber(Number(pageInput?.value || 1), 1, Number.MAX_SAFE_INTEGER);
        let renderTask = null;
        let dragging = false;
        let metadataLines = [];

        const defaults = {
            x: clampNumber(Number(xInput?.value || 70), 0, 100),
            y: clampNumber(Number(yInput?.value || 65), 0, 100),
            width: clampNumber(Number(widthInput?.value || 20), 5, 60),
        };

        function buildMetadataLines() {
            const city = cityInput ? cityInput.value.trim() : '';
            const titimangsa = titimangsaInput ? titimangsaInput.value.trim() : '';
            const headmaster = headmasterInput ? headmasterInput.value.trim() : '';
            const metaTitle = metaTitleInput ? metaTitleInput.value.trim() : '';
            const metaNote = metaNoteInput ? metaNoteInput.value.trim() : '';
            const lines = [];

            if (city !== '' || titimangsa !== '') {
                const cityLine = (city + (titimangsa !== '' ? (city !== '' ? ', ' : '') + titimangsa : '')).trim();
                if (cityLine !== '') {
                    lines.push(cityLine);
                }
            }

            lines.push(metaTitle !== '' ? metaTitle : 'TTD Disetujui Kepala Sekolah');
            const fallbackNote = 'Disahkan oleh ' + (headmaster !== '' ? headmaster : defaultHeadmasterFallback);
            lines.push(metaNote !== '' ? metaNote : fallbackNote);

            return lines;
        }

        function refreshMetadataText() {
            metadataLines = buildMetadataLines();
            if (!blockTextBox) {
                return;
            }
            blockTextBox.innerHTML = '';
            metadataLines.forEach((line) => {
                const div = document.createElement('div');
                div.textContent = line;
                blockTextBox.appendChild(div);
            });
        }

        function computeBlockMetrics(qrWidthPercent) {
            const qrWidth = clampNumber(qrWidthPercent, 5, 60);
            const lineCount = Math.max(metadataLines.length, 2);
            const fontSize = Math.max(11, Math.min(16, qrWidth * 0.4));
            const lineHeight = fontSize * 1.2;
            const textHeight = (lineHeight * lineCount) / 2.5;
            const blockWidth = clampNumber(qrWidth * 2.2, qrWidth + 15, 90);
            const padding = clampNumber(qrWidth * 0.18, 2, 10);
            const blockHeight = clampNumber(Math.max(qrWidth, textHeight + padding * 2), qrWidth + 6, 95);
            const textWidth = Math.max(blockWidth - qrWidth - padding * 2, 12);
            const qrRel = (qrWidth / blockWidth) * 100;
            const padRel = (padding / blockWidth) * 100;
            const textRelLeft = padRel + qrRel + padRel;
            const textRelWidth = Math.max((textWidth / blockWidth) * 100, 12);

            return { qrWidth, blockWidth, blockHeight, padRel, qrRel, fontSize, textRelLeft, textRelWidth };
        }

        function setOverlayPosition(xPercent, yPercent, qrWidthPercent) {
            const { qrWidth, blockWidth, blockHeight, padRel, qrRel, fontSize, textRelLeft, textRelWidth } = computeBlockMetrics(qrWidthPercent);
            const maxX = Math.max(0, 100 - blockWidth);
            const maxY = Math.max(0, 100 - blockHeight);
            const clampedX = clampNumber(xPercent, 0, maxX);
            const clampedY = clampNumber(yPercent, 0, maxY);

            if (widthInput) widthInput.value = qrWidth.toFixed(2);
            if (sizeInput) sizeInput.value = qrWidth;
            if (xInput) xInput.value = clampedX.toFixed(2);
            if (yInput) yInput.value = clampedY.toFixed(2);

            overlay.style.width = blockWidth + '%';
            overlay.style.height = blockHeight + '%';
            overlay.style.left = clampedX + '%';
            overlay.style.top = clampedY + '%';
            overlay.hidden = false;

            if (blockQrBox) {
                blockQrBox.style.position = 'absolute';
                blockQrBox.style.left = padRel + '%';
                blockQrBox.style.top = padRel + '%';
                blockQrBox.style.width = qrRel + '%';
                blockQrBox.style.height = qrRel + '%';
            }

            if (blockTextBox) {
                blockTextBox.style.position = 'absolute';
                blockTextBox.style.left = textRelLeft + '%';
                blockTextBox.style.top = padRel + '%';
                blockTextBox.style.width = textRelWidth + '%';
                blockTextBox.style.maxWidth = textRelWidth + '%';
                blockTextBox.style.fontSize = fontSize + 'px';
                blockTextBox.style.lineHeight = '1.2';
                blockTextBox.style.whiteSpace = 'normal';
                blockTextBox.style.wordBreak = 'break-word';
                blockTextBox.style.textAlign = 'left';
                blockTextBox.style.display = 'block';
            }

            updateLabels(clampedX, clampedY, qrWidth);
        }

        function updateMetadataBlock() {
            refreshMetadataText();
            setOverlayPosition(
                Number(xInput?.value || defaults.x),
                Number(yInput?.value || defaults.y),
                Number(widthInput?.value || defaults.width)
            );
        }

        function updateLabels(xPercent, yPercent, widthPercent) {
            if (xLabel) xLabel.textContent = xPercent.toFixed(1);
            if (yLabel) yLabel.textContent = yPercent.toFixed(1);
            if (sizeLabel) sizeLabel.textContent = widthPercent.toFixed(0);
        }

        function renderPage() {
            if (!pdfDoc) return;

            currentPage = clampNumber(currentPage, 1, pdfDoc.numPages);

            if (pageInput) pageInput.value = currentPage;
            if (pageTotal) pageTotal.textContent = String(pdfDoc.numPages);

            pdfDoc.getPage(currentPage).then((page) => {
                const viewport = page.getViewport({ scale: 1 });
                const scale = Math.min(2.4, 900 / viewport.width);
                const scaledViewport = page.getViewport({ scale });
                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;
                placeholder?.setAttribute('hidden', 'hidden');

                if (renderTask && typeof renderTask.cancel === 'function') {
                    try {
                        renderTask.cancel();
                    } catch (_error) {}
                }

                renderTask = page.render({ canvasContext: ctx, viewport: scaledViewport });
                renderTask.promise
                    .then(() => {
                        setOverlayPosition(
                            Number(xInput?.value || defaults.x),
                            Number(yInput?.value || defaults.y),
                            Number(widthInput?.value || defaults.width)
                        );
                    })
                    .catch(() => {
                        placeholder?.removeAttribute('hidden');
                        if (statusEl) {
                            statusEl.textContent = 'Gagal memuat PDF. Pastikan file tidak rusak.';
                            statusEl.classList.add('text-rose-600');
                        }
                    });
            });
        }

        function loadPdfFromFile(file) {
            if (!file || typeof FileReader === 'undefined') return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const data = event.target?.result;
                if (!(data instanceof ArrayBuffer)) return;
                placeholder?.setAttribute('hidden', 'hidden');

                pdfjsLib.getDocument({ data }).promise
                    .then((doc) => {
                        pdfDoc = doc;
                        currentPage = clampNumber(Number(pageInput?.value || 1), 1, doc.numPages);
                        renderPage();
                    })
                    .catch((err) => {
                        console.error('pdf.js render error', err);
                        placeholder?.removeAttribute('hidden');
                        if (statusEl) {
                            statusEl.textContent = 'Gagal memuat PDF. Pastikan file tidak rusak.';
                            statusEl.classList.add('text-rose-600');
                        }
                    });
            };
            reader.readAsArrayBuffer(file);
        }

        function moveOverlay(event) {
            const rect = canvas.getBoundingClientRect();
            const widthPx = rect.width;
            const heightPx = rect.height;
            if (!widthPx || !heightPx) return;

            const widthPercent = clampNumber(Number(widthInput?.value || sizeInput?.value || defaults.width), 5, 60);
            const qrWidthPx = (widthPercent / 100) * widthPx;
            const maxX = Math.max(0, widthPx - qrWidthPx);
            const maxY = Math.max(0, heightPx - qrWidthPx);

            let xPx = event.clientX - rect.left - qrWidthPx / 2;
            let yPx = event.clientY - rect.top - qrWidthPx / 2;
            xPx = clampNumber(xPx, 0, maxX);
            yPx = clampNumber(yPx, 0, maxY);

            const xPercent = (xPx / widthPx) * 100;
            const yPercent = (yPx / heightPx) * 100;

            setOverlayPosition(xPercent, yPercent, widthPercent);
        }

        overlay.addEventListener('pointerdown', (event) => {
            dragging = true;
            overlay.setPointerCapture(event.pointerId);
            moveOverlay(event);
        });
        overlay.addEventListener('pointermove', (event) => {
            if (!dragging) return;
            moveOverlay(event);
        });
        const stopDrag = () => { dragging = false; };
        overlay.addEventListener('pointerup', stopDrag);
        overlay.addEventListener('pointercancel', stopDrag);

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0];
                if (file) {
                    loadPdfFromFile(file);
                    if (statusEl) {
                        statusEl.textContent = 'Preview dimuat dari file terpilih.';
                        statusEl.classList.remove('text-rose-600');
                    }
                    placeholder?.setAttribute('hidden', 'hidden');
                }
            });
        }

        if (previewBtn) {
            previewBtn.addEventListener('click', () => {
                const file = fileInput && fileInput.files ? fileInput.files[0] : null;
                if (!file) {
                    if (statusEl) {
                        statusEl.textContent = 'Pilih file PDF terlebih dahulu.';
                        statusEl.classList.add('text-rose-600');
                    }
                    return;
                }
                loadPdfFromFile(file);
                if (statusEl) {
                    statusEl.textContent = 'Preview dimuat dari file terpilih.';
                    statusEl.classList.remove('text-rose-600');
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (!pdfDoc) return;
                currentPage = clampNumber(currentPage - 1, 1, pdfDoc.numPages);
                renderPage();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (!pdfDoc) return;
                currentPage = clampNumber(currentPage + 1, 1, pdfDoc.numPages);
                renderPage();
            });
        }
        if (pageInput) {
            pageInput.addEventListener('change', () => {
                const target = clampNumber(Number(pageInput.value || 1), 1, pdfDoc ? pdfDoc.numPages : Number.MAX_SAFE_INTEGER);
                currentPage = target;
                renderPage();
            });
        }
        if (sizeInput) {
            sizeInput.addEventListener('input', () => {
                const width = clampNumber(Number(sizeInput.value || defaults.width), 5, 60);
                setOverlayPosition(Number(xInput?.value || defaults.x), Number(yInput?.value || defaults.y), width);
            });
        }
        if (centerBtn) {
            centerBtn.addEventListener('click', () => {
                const width = clampNumber(Number(widthInput?.value || defaults.width), 5, 60);
                const center = (100 - width) / 2;
                setOverlayPosition(center, center, width);
            });
        }
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                setOverlayPosition(defaults.x, defaults.y, defaults.width);
            });
        }

        refreshMetadataText();
        setOverlayPosition(defaults.x, defaults.y, defaults.width);
        [metaTitleInput, metaNoteInput, headmasterInput, cityInput, titimangsaInput].forEach((input) => {
            if (input) {
                input.addEventListener('input', updateMetadataBlock);
            }
        });
    }

    function clampNumber(value, min, max) {
        if (Number.isNaN(value)) return min;
        if (value < min) return min;
        if (value > max) return max;
        return value;
    }
</script>
