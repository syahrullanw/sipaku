<?php
/** @var array<int, array<string, mixed>> $activeBills */
/** @var array<int, array<string, mixed>> $billHistory */
/** @var array<int, array<string, mixed>> $payments */
/** @var array<string, mixed>|null $savingAccount */
/** @var array<int, array<string, mixed>> $savingTransactions */
/** @var float $outstandingAmount */
/** @var float $savingBalance */
/** @var array<int, array<string, mixed>> $unexpectedExpenses */
/** @var array<string, array<string, mixed>> $unexpectedReports */
/** @var array<int, array<string, mixed>> $activePurchases */
/** @var array<int, array<string, mixed>> $purchaseHistory */
/** @var array<int, array<string, mixed>> $purchasePayments */
/** @var float $purchaseOutstanding */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$weekdayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];

$paymentMethodLabel = static function (string $method): string {
    return match ($method) {
        'tunai' => 'Tunai',
        'transfer' => 'Transfer',
        'tabungan' => 'Saldo Tabungan',
        default => ucfirst($method),
    };
};

$purchasePaymentLabels = [
    'cash' => 'Tunai siswa',
    'tabungan' => 'Potong tabungan',
    'sekolah' => 'Kas sekolah',
];

$savingLastUpdated = null;
if ($savingAccount !== null) {
    $timeCandidate = $savingAccount['updated_at'] ?? $savingAccount['created_at'] ?? null;
    if ($timeCandidate !== null) {
        $parsed = strtotime((string) $timeCandidate);
        if ($parsed !== false) {
            $savingLastUpdated = date('d M Y H:i', $parsed);
        }
    }
}
?>

<div class="space-y-8">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-amber-200/60 bg-amber-50 p-5 shadow-sm shadow-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-amber-700 dark:text-amber-200">Sisa Tagihan Berjalan</p>
            <p class="mt-2 text-2xl font-semibold text-amber-800 dark:text-amber-100"><?= htmlspecialchars($formatCurrency((float) $outstandingAmount), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-200/80">Pastikan melunasi sebelum jatuh tempo.</p>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50 p-5 shadow-sm shadow-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-200">Saldo Tabungan Aktif</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-800 dark:text-emerald-100"><?= htmlspecialchars($formatCurrency((float) $savingBalance), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($savingAccount === null): ?>
                <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-200/80">Belum ada tabungan aktif untuk tahun ajaran ini.</p>
            <?php else: ?>
                <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-200/80">
                    <?php if ($savingLastUpdated !== null): ?>
                        Saldo terakhir diperbarui pada <?= htmlspecialchars($savingLastUpdated, ENT_QUOTES, 'UTF-8') ?>.
                    <?php else: ?>
                        Tabungan aktif terdeteksi, namun waktu pembaruan belum tersedia.
                    <?php endif; ?>
                </p>
                <p class="mt-1 text-xs text-emerald-700/70 dark:text-emerald-200/70">Anda dapat menggunakan saldo tabungan untuk membayar tagihan yang masih aktif.</p>
            <?php endif; ?>
        </div>
        <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-5 shadow-sm shadow-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-sky-700 dark:text-sky-200">Sisa Pembelian Perlengkapan</p>
            <p class="mt-2 text-2xl font-semibold text-sky-800 dark:text-sky-100"><?= htmlspecialchars($formatCurrency((float) $purchaseOutstanding), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-sky-700/80 dark:text-sky-200/80">Segera konfirmasi pembayaran pembelian atribut & perlengkapan.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tagihan & Pembelian Aktif</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tagihan sekolah dan pembelian perlengkapan yang masih berjalan.</p>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                <?php if (empty($activeBills)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada tagihan aktif. Selamat!</p>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Tagihan</th>
                                <th class="py-3 pr-4 font-semibold">Jatuh Tempo</th>
                                <th class="py-3 pr-4 text-right font-semibold">Total</th>
                                <th class="py-3 pr-4 text-right font-semibold">Sisa</th>
                            <th class="py-3 pr-4 text-right font-semibold">Status</th>
                            <th class="py-3 pr-0 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($activeBills as $bill): ?>
                                <?php
                                $isPurchase = !empty($bill['is_purchase']);
                                $recurringType = $isPurchase ? 'tidak' : (string) ($bill['rutin_tipe'] ?? 'tidak');
                                $isRecurring = !$isPurchase && in_array($recurringType, ['mingguan', 'bulanan'], true);
                                $nextSchedule = !$isPurchase && !empty($bill['rutin_jadwal_berikutnya']) ? date('d M Y', strtotime((string) $bill['rutin_jadwal_berikutnya'])) : null;
                                $lastGenerated = !$isPurchase && !empty($bill['rutin_terakhir_generate']) ? date('d M Y', strtotime((string) $bill['rutin_terakhir_generate'])) : null;
                                $scheduleLabel = null;
                                if ($isRecurring) {
                                    if ($recurringType === 'mingguan') {
                                        $dayName = $weekdayNames[(int) ($bill['rutin_hari_mingguan'] ?? 0)] ?? 'Senin';
                                        $scheduleLabel = 'Rutin mingguan • setiap ' . $dayName;
                                    } elseif ($recurringType === 'bulanan') {
                                        $dateNumber = (int) ($bill['rutin_tanggal_bulanan'] ?? 1);
                                        $scheduleLabel = 'Rutin bulanan • setiap tanggal ' . $dateNumber;
                                    }
                                }
                                $displayDue = $bill['tanggal_jatuh_tempo'] ?? null;
                                if ($displayDue === null && $nextSchedule !== null) {
                                    $displayDue = $bill['rutin_jadwal_berikutnya'];
                                }
                                if ($displayDue === null && !empty($bill['created_at'])) {
                                    $displayDue = $bill['created_at'];
                                }
                                ?>
                                <tr>
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($bill['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($bill['kategori_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?><?= $isPurchase && !empty($bill['kode']) ? ' • #' . htmlspecialchars((string) $bill['kode'], ENT_QUOTES, 'UTF-8') : '' ?></p>
                                        <?php if ($scheduleLabel !== null): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if ($nextSchedule !== null): ?>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">Generate berikutnya: <?= htmlspecialchars($nextSchedule, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <?php if ($lastGenerated !== null): ?>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">Terakhir generate: <?= htmlspecialchars($lastGenerated, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php elseif ($isPurchase && !empty($bill['catatan'])): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Catatan: <?= htmlspecialchars((string) $bill['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <?= htmlspecialchars($displayDue !== null ? date('d M Y', strtotime((string) $displayDue)) : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right font-semibold"><?= htmlspecialchars($formatCurrency((float) ($bill['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 text-right font-semibold text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($bill['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 text-right">
                                        <?php
                                        $status = (string) ($bill['status'] ?? '');
                                        $statusClass = match ($status) {
                                            'menunggu_verifikasi' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                            'cicilan_berjalan' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                            'menunggu_pembayaran' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                            default => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                        };
                                        ?>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-0 text-right">
                                        <?php $remainingAmount = (float) ($bill['sisa_nominal'] ?? 0); ?>
                                        <?php if ($status === 'menunggu_verifikasi'): ?>
                                            <span class="text-xs text-amber-600 dark:text-amber-300">Menunggu verifikasi</span>
                                        <?php elseif ($remainingAmount <= 0): ?>
                                            <span class="text-xs text-emerald-600 dark:text-emerald-300">Sudah lunas</span>
                                        <?php elseif ($isPurchase): ?>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Hubungi bendahara untuk pembayaran.</span>
                                        <?php elseif ($savingBalance + 0.0001 < $remainingAmount): ?>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Saldo tabungan belum cukup</span>
                                        <?php else: ?>
                                            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/siswa/tagihan/' . (int) ($bill['id'] ?? 0) . '/bayar-tabungan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2" onsubmit="return confirm('Gunakan saldo tabungan untuk membayar tagihan ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900">
                                                    Bayar via Tabungan
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Tabungan Siswa</h3>
            <?php if ($savingAccount === null): ?>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Tabungan belum aktif. Hubungi bendahara untuk membuka tabungan.</p>
            <?php else: ?>
                <div class="mt-4 rounded-lg border border-slate-200/70 bg-slate-50 p-4 dark:border-slate-700/70 dark:bg-slate-800/60">
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Terakhir</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($savingAccount['saldo_terakhir'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="mt-6">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat Transaksi</p>
                    <?php if (empty($savingTransactions)): ?>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada transaksi.</p>
                    <?php else: ?>
                        <ul class="mt-2 space-y-3">
                            <?php foreach ($savingTransactions as $transaction): ?>
                                <li class="flex items-center justify-between rounded-lg border border-slate-200/70 px-3 py-2 text-sm dark:border-slate-700/70">
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(ucfirst((string) ($transaction['jenis'] ?? '-')) , ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($formatCurrency((float) ($transaction['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($transaction['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Saldo: <?= htmlspecialchars($formatCurrency((float) ($transaction['saldo_setelah'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($activePurchases)): ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pembelian Perlengkapan Aktif</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Detail pembelian atribut/perlengkapan yang belum lunas.</p>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Pembelian</th>
                            <th class="py-3 pr-4 font-semibold">Dicatat Pada</th>
                            <th class="py-3 pr-4 font-semibold">Metode</th>
                            <th class="py-3 pr-4 text-right font-semibold">Total</th>
                            <th class="py-3 pr-4 text-right font-semibold">Sisa</th>
                            <th class="py-3 pr-0 text-right font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                        <?php foreach ($activePurchases as $purchase): ?>
                            <?php
                                $method = (string) ($purchase['metode_pembayaran'] ?? 'cash');
                                $methodLabel = $purchasePaymentLabels[$method] ?? ucfirst($method);
                                $status = (string) ($purchase['status'] ?? 'menunggu_pembayaran');
                                $statusClass = match ($status) {
                                    'cicilan_berjalan' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                    'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                    default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                };
                                $recordedAt = $purchase['created_at'] ?? null;
                            ?>
                            <tr>
                                <td class="py-3 pr-4">
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($purchase['judul'] ?? $purchase['item_label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">#<?= htmlspecialchars($purchase['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($purchase['kategori_nama'] ?? 'Pembelian Perlengkapan', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4 text-sm text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars($recordedAt !== null ? date('d M Y', strtotime((string) $recordedAt)) : '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 pr-4 text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right font-semibold"><?= htmlspecialchars($formatCurrency((float) ($purchase['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-4 text-right font-semibold text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($purchase['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-3 pr-0 text-right">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Tagihan & Pembayaran</h2>
        </div>
        <div class="grid gap-6 px-6 py-4 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tagihan/Pembelian Terakhir</h3>
                <?php if (empty($billHistory)): ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada riwayat tagihan.</p>
                <?php else: ?>
                    <ul class="mt-3 space-y-3 text-sm">
                        <?php foreach ($billHistory as $bill): ?>
                            <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($bill['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php
                                    $status = (string) ($bill['status'] ?? '');
                                    $statusClass = match ($status) {
                                        'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
                                    };
                                    ?>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php
                                $isPurchaseHistory = !empty($bill['is_purchase']);
                                $historyRecurringType = $isPurchaseHistory ? 'tidak' : (string) ($bill['rutin_tipe'] ?? 'tidak');
                                if ($historyRecurringType === 'mingguan') {
                                    $historyDayName = $weekdayNames[(int) ($bill['rutin_hari_mingguan'] ?? 0)] ?? 'Senin';
                                    echo '<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rutin mingguan • setiap ' . htmlspecialchars($historyDayName, ENT_QUOTES, 'UTF-8') . '</p>';
                                } elseif ($historyRecurringType === 'bulanan') {
                                    $historyDate = (int) ($bill['rutin_tanggal_bulanan'] ?? 1);
                                    echo '<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rutin bulanan • setiap tanggal ' . htmlspecialchars((string) $historyDate, ENT_QUOTES, 'UTF-8') . '</p>';
                                }
                                if (!$isPurchaseHistory) {
                                    $historyNext = !empty($bill['rutin_jadwal_berikutnya']) ? date('d M Y', strtotime((string) $bill['rutin_jadwal_berikutnya'])) : null;
                                    if ($historyNext !== null) {
                                        echo '<p class="text-xs text-slate-500 dark:text-slate-400">Periode berikutnya: ' . htmlspecialchars($historyNext, ENT_QUOTES, 'UTF-8') . '</p>';
                                    }
                                } elseif (!empty($bill['kode'])) {
                                    echo '<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kode pembelian: #' . htmlspecialchars((string) $bill['kode'], ENT_QUOTES, 'UTF-8') . '</p>';
                                }
                                ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Total: <?= htmlspecialchars($formatCurrency((float) ($bill['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> | Sisa: <?= htmlspecialchars($formatCurrency((float) ($bill['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pembayaran Terakhir</h3>
                <?php if (empty($payments)): ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada pembayaran.</p>
                <?php else: ?>
                    <ul class="mt-3 space-y-3 text-sm">
                        <?php foreach ($payments as $payment): ?>
                            <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($payment['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($payment['kode_transaksi'] ?? '', ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($paymentMethodLabel((string) ($payment['metode'] ?? '-')), ENT_QUOTES, 'UTF-8') ?><?= !empty($payment['is_purchase']) ? ' • Pembelian' : '' ?></p>
                                    </div>
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($payment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <?php
                                $status = (string) ($payment['status'] ?? '');
                                $statusClass = match ($status) {
                                    'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                    'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                    default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                };
                                ?>
                                <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($purchaseHistory) || !empty($purchasePayments)): ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Pembelian Perlengkapan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Catatan pembelian terbaru beserta pembayaran terkait.</p>
            </div>
            <div class="grid gap-6 px-6 py-4 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat Pembelian</h3>
                    <?php if (empty($purchaseHistory)): ?>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat pembelian.</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-3 text-sm">
                            <?php foreach ($purchaseHistory as $purchase): ?>
                                <?php
                                    $status = (string) ($purchase['status'] ?? 'menunggu_pembayaran');
                                    $statusClass = match ($status) {
                                        'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'cicilan_berjalan' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                        default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                    };
                                ?>
                                <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                    <div class="flex items-center justify-between">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($purchase['item_label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kode: #<?= htmlspecialchars($purchase['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Total <?= htmlspecialchars($formatCurrency((float) ($purchase['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> • Sisa <?= htmlspecialchars($formatCurrency((float) ($purchase['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pembayaran Pembelian</h3>
                    <?php if (empty($purchasePayments)): ?>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada pembayaran pembelian.</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-3 text-sm">
                            <?php foreach ($purchasePayments as $payment): ?>
                                <?php $method = (string) ($payment['metode'] ?? 'cash'); ?>
                                <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($payment['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">#<?= htmlspecialchars($payment['kode_transaksi'] ?? '-', ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($purchasePaymentLabels[$method] ?? ucfirst($method), ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($payment['tanggal_bayar'] ? date('d M Y H:i', strtotime((string) $payment['tanggal_bayar'])) : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($payment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pengeluaran Tak Terduga</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Lihat status LPJ untuk dana darurat yang Anda terima.</p>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($unexpectedExpenses)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pengeluaran tak terduga atas nama Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Tanggal</th>
                                <th class="py-3 pr-4 font-semibold">Keterangan</th>
                                <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                                <th class="py-3 pr-0 font-semibold">LPJ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($unexpectedExpenses as $expense): ?>
                                <?php
                                    $expenseId = (int) ($expense['id'] ?? 0);
                                    $lpj = $unexpectedReports[(string) $expenseId] ?? null;
                                    $lpjRoute = base_url('keuangan/siswa/pengeluaran-tak-terduga/' . $expenseId . '/lpj');
                                    $lpjUpdatedAt = $lpj['updated_at'] ?? $lpj['created_at'] ?? null;
                                ?>
                                <tr>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($expense['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($expense['kode_transaksi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <?php if (!empty($expense['deskripsi'])): ?>
                                            <p><?= nl2br(htmlspecialchars((string) $expense['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                        <?php else: ?>
                                            <p class="text-slate-400 dark:text-slate-500">Tidak ada keterangan.</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right align-top font-semibold text-rose-600 dark:text-rose-300">
                                        <?= htmlspecialchars($formatCurrency((float) ($expense['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3 pr-0 align-top">
                                        <?php if ($lpj !== null): ?>
                                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">Sudah dikumpulkan</p>
                                            <?php if ($lpjUpdatedAt !== null): ?>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Terakhir: <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $lpjUpdatedAt)), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-xs font-semibold text-amber-600 dark:text-amber-300">Belum dikumpulkan</p>
                                        <?php endif; ?>
                                        <?php if ($expenseId > 0): ?>
                                            <a href="<?= htmlspecialchars($lpjRoute, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                                <span class="ri-edit-line text-sm"></span>
                                                Kelola LPJ
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
