<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <title><?= htmlspecialchars($title ?? 'Cetak', ENT_QUOTES, 'UTF-8') ?></title>
        <?php
            $paperSizeValue = isset($paperSize) ? strtolower((string) $paperSize) : 'f4';
            if (!in_array($paperSizeValue, ['f4', 'a4'], true)) {
                $paperSizeValue = 'f4';
            }

            if ($paperSizeValue === 'a4') {
                $pageSizeCss = '210mm 297mm';
                $pageMarginCss = '15mm';
                $previewPaddingTop = '15mm';
                $previewPaddingSides = '15mm';
                $printContentMaxWidth = '180mm';
            } else {
                $pageSizeCss = '215mm 330mm';
                $pageMarginCss = '12mm';
                $previewPaddingTop = '12mm';
                $previewPaddingSides = '12mm';
                $printContentMaxWidth = '191mm';
            }

            $previewPaddingCss = sprintf('%s %s', $previewPaddingTop, $previewPaddingSides);
            $paperSizeLabel = $paperSizeValue === 'a4'
                ? 'A4 (29,7 x 21 cm)'
                : 'F4 / Folio (33 x 21,5 cm)';
        ?>
        <style>
            :root {
                color-scheme: light;
            }

            @page {
                size: <?= $pageSizeCss ?>;
                margin: <?= $pageMarginCss ?>;
            }

            body {
                margin: 0;
                font-family: 'Times New Roman', Times, serif;
                font-size: 12pt;
                color: #111827;
                background-color: #ffffff;
            }

            .print-container {
                box-sizing: border-box;
                padding: <?= $previewPaddingCss ?>;
                min-height: 100vh;
            }

            .print-actions {
                display: flex;
                justify-content: space-between;
                margin-bottom: 16px;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .print-actions .paper-size-label {
                font-size: 11pt;
                color: #475569;
            }

            .print-actions button {
                background-color: #1d4ed8;
                border: none;
                color: #ffffff;
                padding: 8px 16px;
                font-size: 11pt;
                border-radius: 6px;
                cursor: pointer;
            }

            .print-actions button:focus-visible {
                outline: 2px solid #1d4ed8;
                outline-offset: 2px;
            }

            .print-content {
                width: 100%;
                max-width: <?= $printContentMaxWidth ?>;
                margin: 0 auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #0f172a;
                padding: 4px 6px;
            }

            th {
                font-weight: bold;
                text-align: center;
            }

            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            .text-left {
                text-align: left;
            }

            .uppercase {
                text-transform: uppercase;
            }

            .mt-2 {
                margin-top: 8px;
            }

            .mt-4 {
                margin-top: 16px;
            }

            .mt-6 {
                margin-top: 24px;
            }

            .fw-semibold {
                font-weight: 600;
            }

            .underline {
                text-decoration: underline;
            }

            @media print {
                .print-actions {
                    display: none;
                }

                body {
                    background-color: #ffffff !important;
                }

                .print-container {
                    padding: 0;
                    min-height: auto;
                }
            }
        </style>
    </head>
    <body>
        <div class="print-container">
            <div class="print-actions">
                <span class="paper-size-label">Ukuran kertas: <?= htmlspecialchars($paperSizeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
            </div>
            <div class="print-content">
                <?= $slot ?>
            </div>
        </div>
        <script src="<?= htmlspecialchars(asset('js/vendor/qrcode.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof QRCode === 'undefined') {
                    return;
                }

                document.querySelectorAll('[data-qr-value]').forEach(function (element) {
                    if (element.getAttribute('data-qr-rendered') === '1') {
                        return;
                    }

                    var value = element.getAttribute('data-qr-value');

                    if (!value || value === '') {
                        return;
                    }

                    var sizeAttr = element.getAttribute('data-qr-size');
                    var size = parseInt(sizeAttr || '128', 10);

                    if (!Number.isFinite(size) || size <= 0) {
                        size = 128;
                    }

                    element.innerHTML = '';

                    try {
                        new QRCode(element, {
                            text: value,
                            width: size,
                            height: size,
                        });
                        element.setAttribute('data-qr-rendered', '1');
                    } catch (error) {
                        element.innerHTML = '<span style="font-size:10px;color:#9ca3af;">QR gagal dimuat</span>';
                    }
                });
            });
        </script>
    </body>
</html>
