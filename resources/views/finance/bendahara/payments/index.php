<?php
/** @var array<int, array<string, mixed>> $pendingPayments */
/** @var array<int, array<string, mixed>> $recentPayments */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$paymentMethodLabel = static function (string $method): string {
    return match ($method) {
        'tunai' => 'Tunai',
        'transfer' => 'Transfer',
        'tabungan' => 'Saldo Tabungan',
        default => ucfirst($method),
    };
};
?>

<div class="space-y-8">
    <div class="rounded-xl border border-slate-200/60 bg-white/70 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex items-center justify-between border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Pembayaran Menunggu Verifikasi</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tinjau bukti pembayaran dan setujui sebelum status tagihan berubah menjadi lunas.</p>
            </div>
        </div>
        <div class="overflow-x-auto px-6 py-4">
            <?php if (empty($pendingPayments)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pembayaran yang perlu diverifikasi.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Waktu Bayar</th>
                            <th class="py-3 pr-4 font-semibold">Siswa</th>
                            <th class="py-3 pr-4 font-semibold">Tagihan</th>
                            <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                            <th class="py-3 pr-4 text-center font-semibold">Bukti</th>
                            <th class="py-3 pr-0 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                        <?php foreach ($pendingPayments as $payment): ?>
                            <tr>
                                <td class="py-3 pr-4 align-top">
                                    <p class="font-medium"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($payment['tanggal_bayar'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($payment['kode_transaksi'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4 align-top">
                                    <p class="font-semibold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($payment['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($payment, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($payment, 'ml-1 align-middle') ?>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Metode: <?= htmlspecialchars($paymentMethodLabel((string) ($payment['metode'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4 align-top">
                                    <p class="font-medium"><?= htmlspecialchars($payment['tagihan_judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Sisa setelah bayar: <?= htmlspecialchars($formatCurrency((float) ($payment['sisa_setelah'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4 text-right align-top font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($payment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-center align-top">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/slip'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Cetak</a>
                                        <?php if (!empty($payment['bukti_path'])): ?>
                                            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/lampiran'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-200 dark:bg-sky-500/20 dark:text-sky-200">Lampiran</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 pr-0 text-right align-top">
                                    <div class="flex justify-end gap-2">
                                        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/approve'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline-flex">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-slate-900">Setujui</button>
                                        </form>
                                        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/reject'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline-flex">
                                            <?= csrf_field() ?>
                                            <input type="text" name="alasan" placeholder="Alasan" class="hidden md:block w-32 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500/60 focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-slate-900">Tolak</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/70 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Pembayaran Terakhir</h2>
        </div>
        <div class="overflow-x-auto px-6 py-4">
            <?php if (empty($recentPayments)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat pembayaran.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Tanggal</th>
                            <th class="py-3 pr-4 font-semibold">Siswa</th>
                            <th class="py-3 pr-4 font-semibold">Tagihan</th>
                            <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                            <th class="py-3 pr-4 text-center font-semibold">Bukti</th>
                            <th class="py-3 pr-0 text-right font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                        <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td class="py-3 pr-4"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($payment['tanggal_bayar'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">
                                        <?= htmlspecialchars($payment['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($payment, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($payment, 'ml-1 align-middle') ?>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($payment['kode_transaksi'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4"><?= htmlspecialchars($payment['tagihan_judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right font-semibold"><?= htmlspecialchars($formatCurrency((float) ($payment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/slip'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Cetak</a>
                                        <?php if (!empty($payment['bukti_path'])): ?>
                                            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran/' . ($payment['id'] ?? 0) . '/lampiran'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-200 dark:bg-sky-500/20 dark:text-sky-200">Lampiran</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 pr-0 text-right">
                                    <?php
                                    $status = (string) ($payment['status'] ?? '');
                                    $statusClass = match ($status) {
                                        'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                        default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                    };
                                    ?>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
