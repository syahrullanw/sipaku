<?php
$letter = is_array($letter ?? null) ? $letter : [];
$digitalSignatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
$pdfMeta = is_array($letter['pdf'] ?? null) ? $letter['pdf'] : [];
$pdfUrl = isset($pdfMeta['url']) ? (string) $pdfMeta['url'] : null;
$pdfSignedUrl = isset($pdfMeta['signed_url']) ? (string) $pdfMeta['signed_url'] : null;
$pdfOptions = is_array($pdfMeta['options'] ?? null) ? $pdfMeta['options'] : null;
$letterId = (int) ($letter['id'] ?? 0);
$letterNumber = trim((string) ($letter['nomor_surat'] ?? ''));
$letterSubject = trim((string) ($letter['perihal'] ?? ''));
$letterType = trim((string) ($letter['jenis_label'] ?? ''));
$letterDate = trim((string) ($letter['tanggal_surat_formatted'] ?? ($letter['tanggal_surat'] ?? '')));
$defaultPage = isset($pdfOptions['page']) ? (int) $pdfOptions['page'] : 1;
$defaultX = isset($pdfOptions['x_percent']) ? (float) $pdfOptions['x_percent'] : 70.0;
$defaultY = isset($pdfOptions['y_percent']) ? (float) $pdfOptions['y_percent'] : 65.0;
$defaultWidth = isset($pdfOptions['width_percent']) ? (float) $pdfOptions['width_percent'] : 20.0;
$formAction = base_url('tata-usaha/persuratan/surat-keluar/' . $letterId . '/preview-ttd');
$initialPdfUrl = isset($existingPdfUrl) && is_string($existingPdfUrl) ? $existingPdfUrl : $pdfUrl;
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$headmasterOption = is_array($headmasterOption ?? null) ? $headmasterOption : null;
$defaultCityValue = isset($pdfOptions['city']) && $pdfOptions['city'] !== null ? (string) $pdfOptions['city'] : (string) ($defaultCity ?? '');
$defaultTitimangsaValue = isset($pdfOptions['titimangsa']) && $pdfOptions['titimangsa'] !== null ? (string) $pdfOptions['titimangsa'] : $letterDate;
$defaultHeadmasterName = isset($pdfOptions['headmaster_name']) && $pdfOptions['headmaster_name'] !== null
    ? (string) $pdfOptions['headmaster_name']
    : (string) ($headmasterOption['name'] ?? '');
$defaultUseLetterhead = isset($pdfOptions['use_letterhead']) ? (bool) $pdfOptions['use_letterhead'] : false;
$letterheadAvailable = !empty($schoolProfile['kop_surat'] ?? '');
$defaultMetaTitle = isset($pdfOptions['signature_meta_title']) ? (string) $pdfOptions['signature_meta_title'] : 'TTD Disetujui Kepala Sekolah';
$defaultMetaNote = isset($pdfOptions['signature_meta_note']) ? (string) $pdfOptions['signature_meta_note'] : 'Disahkan oleh ' . $defaultHeadmasterName;
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$defaultSchoolName = isset($schoolProfile['nama']) ? (string) $schoolProfile['nama'] : 'Sekolah';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-slate-800">Atur Posisi QR TTD Digital</h1>
            <p class="mt-1 text-sm text-slate-500">Surat <?= htmlspecialchars($letterNumber !== '' ? $letterNumber : '-', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($letterType !== '' ? $letterType : '-', ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($letterSubject !== ''): ?>
                <p class="text-xs text-slate-500">Perihal: <?= htmlspecialchars($letterSubject, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="<?= htmlspecialchars(base_url('tata-usaha/persuratan#surat-keluar'), ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50"
            >
                <i class="ri-arrow-left-line text-base"></i>
                Kembali
            </a>
            <?php if ($pdfUrl !== null): ?>
                <a
                    href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50"
                >
                    <i class="ri-file-pdf-line text-base text-rose-500"></i>
                    Lihat PDF Asli
                </a>
            <?php endif; ?>
            <?php if ($pdfSignedUrl !== null): ?>
                <a
                    href="<?= htmlspecialchars($pdfSignedUrl, ENT_QUOTES, 'UTF-8') ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50"
                >
                    <i class="ri-shield-check-line text-base"></i>
                    PDF Bertanda Tangan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$digitalSignatureEnabled): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
            TTD digital belum diaktifkan oleh admin. Aktifkan terlebih dahulu untuk menerbitkan QR.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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

                <input
                    type="file"
                    name="surat_pdf"
                    accept="application/pdf,.pdf"
                    class="mt-2 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border file:border-slate-200 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    data-pdf-input
                    data-pdf-file-label
                />

                <div class="relative overflow-hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50" data-pdf-preview>
                    <canvas class="block w-full" data-pdf-canvas></canvas>
                    <div class="absolute inset-0 z-20 cursor-move rounded-lg border-2 border-indigo-500 bg-indigo-500/10 shadow-lg" data-qr-overlay hidden></div>
                    <div class="absolute inset-0 flex items-center justify-center text-sm text-slate-400" data-pdf-placeholder>
                        Pilih PDF untuk menampilkan preview dan mengatur posisi QR.
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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
                            value="<?= htmlspecialchars((string) $defaultPage, ENT_QUOTES, 'UTF-8') ?>"
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
                        <input type="range" min="8" max="60" step="1" value="<?= htmlspecialchars((string) $defaultWidth, ENT_QUOTES, 'UTF-8') ?>" class="h-2 w-full cursor-pointer accent-indigo-600" data-qr-size-input />
                        <span class="w-12 text-right text-xs text-slate-600"><span data-qr-size-label><?= htmlspecialchars((string) $defaultWidth, ENT_QUOTES, 'UTF-8') ?></span>%</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Posisi</p>
                    <div class="text-xs text-slate-600">
                        X: <span data-qr-x-label><?= htmlspecialchars(number_format($defaultX, 1), ENT_QUOTES, 'UTF-8') ?></span>% · Y: <span data-qr-y-label><?= htmlspecialchars(number_format($defaultY, 1), ENT_QUOTES, 'UTF-8') ?></span>%
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
                    <p class="text-[11px] leading-relaxed text-slate-500">Geser kotak QR pada preview untuk menentukan posisi tanda tangan digital. Koordinat disimpan otomatis saat disimpan.</p>
                </div>

                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-3 text-xs text-indigo-700">
                    Simpan untuk memperbarui posisi QR. Jika TTD digital sudah disetujui sebelumnya, status akan kembali menunggu persetujuan.
                </div>

                <input type="hidden" name="pdf_signature_x" value="<?= htmlspecialchars((string) $defaultX, ENT_QUOTES, 'UTF-8') ?>" data-qr-x-input>
                <input type="hidden" name="pdf_signature_y" value="<?= htmlspecialchars((string) $defaultY, ENT_QUOTES, 'UTF-8') ?>" data-qr-y-input>
                <input type="hidden" name="pdf_signature_width" value="<?= htmlspecialchars((string) $defaultWidth, ENT_QUOTES, 'UTF-8') ?>" data-qr-width-input>

                    <input type="hidden" name="signature_mode" value="metadata" />
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_city">Kota (opsional)</label>
                            <input
                                type="text"
                                id="signature_city"
                                name="signature_city"
                                value="<?= htmlspecialchars($defaultCityValue, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Misal: Cirebon"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_titimangsa">Titimangsa (opsional)</label>
                            <input
                                type="text"
                                id="signature_titimangsa"
                                name="signature_titimangsa"
                                value="<?= htmlspecialchars($defaultTitimangsaValue, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Misal: 06 Desember 2025"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_headmaster_name">Nama Kepala Sekolah (opsional)</label>
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
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="hidden" name="use_letterhead" value="0" />
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
                    <div class="space-y-2" data-metadata-fields>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="signature_meta_title">Judul Metadata (opsional)</label>
                        <input
                            type="text"
                            id="signature_meta_title"
                            name="signature_meta_title"
                            value="<?= htmlspecialchars($defaultMetaTitle, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-1 block w-full rounded border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                        <input type="hidden" id="signature_meta_note" name="signature_meta_note" value="<?= htmlspecialchars($defaultMetaNote, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        <i class="ri-save-3-line text-base"></i>
                        Simpan Posisi QR
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="<?= htmlspecialchars(asset('js/vendor/pdf.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => initPreviewPage());

    function initPreviewPage() {
        const pdfjsLib = window.pdfjsLib || window['pdfjs-dist/build/pdf'] || null;
        const pdfUrl = <?= $initialPdfUrl !== null ? '"' . htmlspecialchars($initialPdfUrl, ENT_QUOTES, 'UTF-8') . '"' : 'null' ?>;
        const canvas = document.querySelector('[data-pdf-canvas]');
        const overlay = document.querySelector('[data-qr-overlay]');
        if (overlay && !overlay.querySelector('[data-block-qr]')) {
            overlay.innerHTML = '<div data-block-qr class="pointer-events-none absolute rounded bg-indigo-200/50 border border-indigo-400 shadow-sm"></div><div data-block-text class="pointer-events-none absolute text-[12px] leading-tight text-slate-900"></div>';
        }
        const blockQrBox = overlay ? overlay.querySelector('[data-block-qr]') : null;
        const blockTextBox = overlay ? overlay.querySelector('[data-block-text]') : null;
        const placeholder = document.querySelector('[data-pdf-placeholder]');
        const fileInput = document.querySelector('[data-pdf-input]');
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
        const cityInput = document.getElementById('signature_city');
        const titimangsaInput = document.getElementById('signature_titimangsa');
        const headmasterInput = document.getElementById('signature_headmaster_name');
        const centerBtn = document.querySelector('[data-qr-center]');
        const resetBtn = document.querySelector('[data-qr-reset]');
        const defaultHeadmasterFallback = "<?= htmlspecialchars($defaultHeadmasterName, ENT_QUOTES, 'UTF-8') ?>";

        if (!pdfjsLib || !canvas || !overlay) {
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
                    });
            });
        }

        function loadPdfFromUrl(url) {
            if (!url) return;
            placeholder?.setAttribute('hidden', 'hidden');
            pdfjsLib.getDocument(url).promise
                .then((doc) => {
                    pdfDoc = doc;
                    currentPage = clampNumber(Number(pageInput?.value || 1), 1, doc.numPages);
                    renderPage();
                    if (fileInput) {
                        fileInput.disabled = true;
                    }
                })
                .catch(() => placeholder?.removeAttribute('hidden'));
        }

        function loadPdfFromFile(file) {
            if (!file || typeof FileReader === 'undefined') return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const data = event.target?.result;
                if (!(data instanceof ArrayBuffer)) return;

                pdfjsLib.getDocument({ data }).promise
                    .then((doc) => {
                        pdfDoc = doc;
                        currentPage = clampNumber(Number(pageInput?.value || 1), 1, doc.numPages);
                        renderPage();
                    })
                    .catch(() => placeholder?.removeAttribute('hidden'));
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
                    fileInput.disabled = true;
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

        if (pdfUrl) {
            loadPdfFromUrl(pdfUrl);
        }
    }

    function clampNumber(value, min, max) {
        if (Number.isNaN(value)) return min;
        if (value < min) return min;
        if (value > max) return max;
        return value;
    }
</script>
