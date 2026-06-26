<?php
/** @var array<string, mixed> $payment */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');

$paymentCode = (string) ($payment['kode_transaksi'] ?? '');
$studentName = (string) ($payment['siswa_nama'] ?? '-');
$studentNis = (string) ($payment['siswa_nis'] ?? '-');
$billingTitle = (string) ($payment['tagihan_judul'] ?? '-');
$billingCode = (string) ($payment['tagihan_kode'] ?? '-');
$categoryName = (string) ($payment['kategori_nama'] ?? '-');
$method = match ((string) ($payment['metode'] ?? '-')) {
    'tunai' => 'Tunai',
    'transfer' => 'Transfer',
    'tabungan' => 'Saldo Tabungan',
    default => ucfirst((string) ($payment['metode'] ?? '-')),
};
$status = strtoupper(str_replace('_', ' ', (string) ($payment['status'] ?? '-')));
$amount = $formatCurrency((float) ($payment['nominal'] ?? 0));
$remaining = isset($payment['sisa_nominal']) ? $formatCurrency((float) $payment['sisa_nominal']) : '-';
$totalTagihan = isset($payment['tagihan_total']) ? $formatCurrency((float) $payment['tagihan_total']) : '-';
$note = (string) ($payment['catatan'] ?? '');
$paidAt = isset($payment['tanggal_bayar']) ? date('d M Y H:i', strtotime((string) $payment['tanggal_bayar'])) : '-';
$verifiedAt = isset($payment['diverifikasi_pada']) ? date('d M Y H:i', strtotime((string) $payment['diverifikasi_pada'])) : '-';
$verifiedBy = (string) ($payment['diverifikasi_oleh_nama'] ?? '-');
$generatedAt = date('d M Y H:i');
$shareableUrl = isset($shareableUrl) ? (string) $shareableUrl : '';
$fallbackQr = absolute_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/slip');
$qrValue = $shareableUrl !== '' ? $shareableUrl : $fallbackQr;
?>

<section>
    <header class="text-center">
        <h1 class="uppercase fw-semibold">Bukti Pembayaran Resmi</h1>
        <p class="mt-2">Diterbitkan oleh <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?> pada <strong><?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <p>Kode Transaksi: <span class="fw-semibold"><?= htmlspecialchars($paymentCode, ENT_QUOTES, 'UTF-8') ?></span></p>
    </header>

    <table class="mt-4">
        <tbody>
            <tr>
                <th class="text-left" style="width: 35%;">Nama Siswa</th>
                <td>
                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                    <?= student_status_badge($payment, 'ml-1 align-middle') ?>
                    <?= student_dapodik_badge($payment, 'ml-1 align-middle') ?>
                </td>
            </tr>
            <tr>
                <th class="text-left">NIS</th>
                <td><?= htmlspecialchars($studentNis !== '' ? $studentNis : '-', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Tagihan</th>
                <td>
                    <?= htmlspecialchars($billingTitle, ENT_QUOTES, 'UTF-8') ?><br/>
                    <span style="font-size: 10pt; color: #475569;">Kode: <?= htmlspecialchars($billingCode, ENT_QUOTES, 'UTF-8') ?> • Kategori: <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></span>
                </td>
            </tr>
            <tr>
                <th class="text-left">Metode Pembayaran</th>
                <td><?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Nominal Dibayarkan</th>
                <td><?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Total Tagihan</th>
                <td><?= htmlspecialchars($totalTagihan, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Saldo kas tagihan</th>
                <td><?= htmlspecialchars($formatCurrency((float) ($payment['kas_saldo'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Sisa Tagihan Setelah Bayar</th>
                <td><?= htmlspecialchars($remaining, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Status</th>
                <td><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Tanggal Pembayaran</th>
                <td><?= htmlspecialchars($paidAt, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th class="text-left">Diverifikasi Oleh</th>
                <td><?= htmlspecialchars($verifiedBy, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($verifiedAt, ENT_QUOTES, 'UTF-8') ?>)</td>
            </tr>
            <?php if ($note !== ''): ?>
                <tr>
                    <th class="text-left">Catatan</th>
                    <td><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-6" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <p>Slip ini diterbitkan otomatis oleh sistem dan berlaku sebagai bukti pembayaran sah tanpa tanda tangan.</p>
            <p style="font-size:10pt; color:#475569;">Simpan salinan ini atau cetak untuk arsip.</p>
        </div>
        <div data-qr-value="<?= htmlspecialchars($qrValue, ENT_QUOTES, 'UTF-8') ?>" data-qr-size="128"></div>
    </div>
</section>
