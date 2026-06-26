<?php
/** @var bool $hasActiveYear */
/** @var array<int, string> $classOptions */
/** @var array<int, string> $studentOptions */
/** @var array<int, array<string, mixed>> $purchases */
/** @var array<int, array<string, mixed>> $paymentablePurchases */
/** @var array<string, float|int> $summary */
/** @var array<string, string> $purchaseTypes */
/** @var array<string, string> $paymentMethods */
/** @var array<string, mixed> $filters */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$statusLabels = [
    'menunggu_pembayaran' => 'Menunggu pembayaran',
    'menunggu_verifikasi' => 'Menunggu verifikasi',
    'cicilan_berjalan' => 'Cicilan berjalan',
    'lunas' => 'Lunas',
    'gagal' => 'Gagal',
    'dibatalkan' => 'Dibatalkan',
];
$paymentBadges = [
    'cash' => 'Bayar tunai siswa',
    'tabungan' => 'Potong tabungan',
    'sekolah' => 'Kas sekolah',
];
$disabled = $hasActiveYear ? '' : 'disabled';
$selectedStudent = (int) (old('student_id') ?? 0);
$selectedJenis = old('jenis', 'lain');
$selectedPayment = old('payment_method', 'cash');
$paymentSelectValue = (int) (old('purchase_id') ?? 0);
$paymentAmountValue = old('amount', '');
$paymentMethodValue = old('payment_method', 'cash');
$paymentModeValue = old('payment_mode', 'partial');
?>

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Catat Pembelian Baru</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Rekam pembelian atribut, seragam, atau perlengkapan lain dan pilih siapa yang menanggung biaya.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">Periode aktif</span>
            </div>
            <?php if (!$hasActiveYear): ?>
                <p class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/40 dark:text-amber-200">Tentukan tahun ajaran aktif sebelum mencatat pembelian.</p>
            <?php endif; ?>
            <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/pembelian'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Siswa
                        <select name="student_id" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" <?= $disabled ?>>
                            <option value="">Pilih siswa</option>
                            <?php foreach ($studentOptions as $id => $label): ?>
                                <?php $optionDisabled = str_contains((string) $label, ' - Nonaktif'); ?>
                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedStudent === (int) $id ? 'selected' : '' ?> <?= $optionDisabled ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Nama pembelian
                        <input type="text" name="item_label" value="<?= htmlspecialchars(old('item_label', ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" placeholder="Contoh: Seragam luar kelas" <?= $disabled ?>>
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Nominal
                        <input type="text" name="amount" value="<?= htmlspecialchars(old('amount', ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" placeholder="0" <?= $disabled ?>>
                    </label>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Jenis
                        <select name="jenis" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" <?= $disabled ?>>
                            <?php foreach ($purchaseTypes as $code => $label): ?>
                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $code === $selectedJenis ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Metode pembayaran
                        <select name="payment_method" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" <?= $disabled ?>>
                            <?php foreach ($paymentMethods as $code => $label): ?>
                                <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $code === $selectedPayment ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Catatan (opsional)
                    <textarea name="note" rows="2" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" <?= $disabled ?>><?= htmlspecialchars(old('note', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
                <div class="flex items-center justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Siswa bisa memiliki lebih dari satu pembelian yang tertunda.</p>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:bg-sky-500 dark:hover:bg-sky-600" <?= $hasActiveYear ? '' : 'disabled' ?>>Catat pembelian</button>
                </div>
            </form>
        </div>
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Rekap Pembelian</p>
                <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?> pembelian</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Total pembelian yang dicatat.</p>
            </div>
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Total nominal</p>
                <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($summary['total_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Termasuk pembayaran dari siswa dan sekolah.</p>
            </div>
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Outstanding</p>
                <p class="mt-3 text-2xl font-semibold text-rose-600 dark:text-rose-300"><?= htmlspecialchars($formatCurrency((float) ($summary['outstanding'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Masih perlu dibayar siswa.</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Pembelian</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Urut berdasarkan pencatatan terbaru.</p>
            </div>
            <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/pembelian'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex flex-col gap-3 md:flex-row md:items-center">
                <select name="class_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">Semua kelas</option>
                    <?php foreach ($classOptions as $id => $label): ?>
                        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) ($filters['class_id'] ?? 0) === (int) $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="q" value="<?= htmlspecialchars($filters['query'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari siswa atau item" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1 dark:bg-slate-700 dark:hover:bg-slate-600">Filter</button>
            </form>
        </div>

        <?php if (empty($purchases)): ?>
            <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Belum ada pembelian yang tercatat untuk filter tersebut.</p>
        <?php else: ?>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="pb-3 pr-4">Tanggal</th>
                            <th class="pb-3 pr-4">Siswa</th>
                            <th class="pb-3 pr-4">Item</th>
                            <th class="pb-3 pr-4">Nominal</th>
                            <th class="pb-3 pr-4">Metode</th>
                            <th class="pb-3 pr-4">Status</th>
                            <th class="pb-3 pr-4">Sisa</th>
                            <th class="pb-3 pr-4">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($purchases as $purchase): ?>
                            <tr class="text-slate-700 dark:text-slate-200" data-purchase-row="<?= htmlspecialchars((string) ($purchase['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <td class="py-3 pr-4 text-xs text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars(date('d M Y', strtotime((string) ($purchase['created_at'] ?? $purchase['updated_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">
                                        <?= htmlspecialchars($purchase['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($purchase, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($purchase, 'ml-1 align-middle') ?>
                                    </p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        <?= htmlspecialchars($purchase['nipd'] ?? $purchase['nisn'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        <?= !empty($purchase['kelas_nama']) ? ' · ' . htmlspecialchars($purchase['kelas_nama'], ENT_QUOTES, 'UTF-8') : '' ?>
                                    </p>
                                </td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium"><?= htmlspecialchars($purchase['tagihan_judul'] ?? $purchase['item_label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">#<?= htmlspecialchars($purchase['kode'] ?? $purchase['tagihan_kode'] ?? '—', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(($purchase['jenis'] ?? 'lain'), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="py-3 pr-4 font-semibold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($formatCurrency((float) ($purchase['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">
                                        <?= htmlspecialchars($paymentBadges[$purchase['metode_pembayaran']] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">
                                        <?= htmlspecialchars($statusLabels[$purchase['tagihan_status'] ?? $purchase['status'] ?? 'menunggu_pembayaran'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="py-3 pr-4 font-semibold text-slate-900 dark:text-white" data-purchase-outstanding>
                                    <?= htmlspecialchars($formatCurrency((float) ($purchase['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="py-3 pr-4 text-xs text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars($purchase['catatan'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Bayar Pembelian Terbuka</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Cairkan pembelian yang masih memiliki sisa.</p>
                <?php $purchaseFeedback = session_flash('purchase_payment_status'); ?>
                <?php $purchaseFeedbackLevel = session_flash('purchase_payment_level') ?? 'success'; ?>
                <div
                    data-purchase-feedback
                    data-level="<?= htmlspecialchars($purchaseFeedbackLevel, ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-3 rounded-xl border px-4 py-3 text-sm shadow-sm transition-colors duration-300 <?= $purchaseFeedback ? '' : 'hidden' ?> <?= $purchaseFeedbackLevel === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-200' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200' ?>"
                >
                    <span data-purchase-feedback-text><?= htmlspecialchars($purchaseFeedback ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (empty($paymentablePurchases)): ?>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Semua pembelian sudah lunas.</p>
                <?php else: ?>
                    <form id="purchase-payment-form" action="<?= htmlspecialchars(base_url('keuangan/bendahara/pembelian/bayar'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-4 space-y-4">
                        <?= csrf_field() ?>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Pilih pembelian
                                <select id="purchase-for-payment" name="purchase_id" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" required>
                                    <option value="">Pilih pembelian</option>
                                    <?php foreach ($paymentablePurchases as $purchase): ?>
                                        <option
                                            value="<?= htmlspecialchars((string) ($purchase['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-outstanding="<?= htmlspecialchars((string) number_format((float) ($purchase['sisa_nominal'] ?? 0), 0, '', ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-student="<?= htmlspecialchars((string) ($purchase['siswa_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $paymentSelectValue > 0 && $paymentSelectValue === (int) ($purchase['id'] ?? 0) ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(($purchase['siswa_nama'] ?? '') . ' · ' . ($purchase['item_label'] ?? $purchase['tagihan_judul'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Sisa <?= htmlspecialchars($formatCurrency((float) ($purchase['sisa_nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Nominal bayar
                                <input type="text" name="amount" id="purchase-payment-amount" value="<?= htmlspecialchars($paymentAmountValue, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" required />
                            </label>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Jenis pembayaran</p>
                            <div class="mt-2 flex flex-wrap gap-4 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="payment_mode" value="partial" class="text-sky-600 focus:ring-sky-500" <?= $paymentModeValue === 'partial' ? 'checked' : '' ?>>
                                    <span>Cicilan / nominal custom</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="payment_mode" value="full" class="text-sky-600 focus:ring-sky-500" <?= $paymentModeValue === 'full' ? 'checked' : '' ?>>
                                    <span>Lunasi penuh (ambil sisa otomatis)</span>
                                </label>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Metode pembayaran
                                <select name="payment_method" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="cash" <?= $paymentMethodValue === 'cash' ? 'selected' : '' ?>>Tunai siswa</option>
                                    <option value="tabungan" <?= $paymentMethodValue === 'tabungan' ? 'selected' : '' ?>>Potong tabungan</option>
                                    <option value="sekolah" <?= $paymentMethodValue === 'sekolah' ? 'selected' : '' ?>>Kas sekolah</option>
                                </select>
                            </label>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:bg-emerald-500 dark:hover:bg-emerald-600">Bayar sekarang</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    (function () {
        var select = document.getElementById('purchase-for-payment');
        var amountInput = document.getElementById('purchase-payment-amount');
        var form = document.getElementById('purchase-payment-form');
        var feedback = document.querySelector('[data-purchase-feedback]');
        var feedbackText = feedback ? feedback.querySelector('[data-purchase-feedback-text]') : null;
        var baseClasses = 'mt-3 rounded-xl border px-4 py-3 text-sm shadow-sm transition-colors duration-300';
        var successClasses = baseClasses + ' border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200';
        var errorClasses = baseClasses + ' border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/15 dark:text-rose-200';
        var paymentModeInputs = form ? form.querySelectorAll('input[name="payment_mode"]') : null;

        function renderFeedback(message, level) {
            if (!feedback || !feedbackText) {
                return;
            }

            if (!message) {
                feedbackText.textContent = '';
                feedback.className = baseClasses + ' hidden';
                return;
            }

            feedbackText.textContent = message;
            feedback.className = level === 'error' ? errorClasses : successClasses;
        }

        function getSelectedOutstanding() {
            if (!select) {
                return 0;
            }
            var option = select.options[select.selectedIndex];
            if (!option) {
                return 0;
            }

            var value = option.getAttribute('data-outstanding') || '0';
            var parsed = parseFloat(value);

            return isNaN(parsed) ? 0 : parsed;
        }

        function syncAmountInput(forceFill) {
            if (!amountInput) {
                return;
            }

            var checked = form ? form.querySelector('input[name="payment_mode"]:checked') : null;
            var mode = checked ? checked.value : 'partial';
            var outstanding = getSelectedOutstanding();

            if (mode === 'full') {
                amountInput.value = outstanding > 0 ? outstanding : '';
                amountInput.setAttribute('readonly', 'readonly');
            } else {
                amountInput.removeAttribute('readonly');
                if (forceFill && amountInput.value === '' && outstanding > 0) {
                    amountInput.value = outstanding;
                }
            }
        }

        function updateOutstandingDisplay(purchaseId, formatted, raw) {
            if (!purchaseId) {
                return;
            }

            var row = document.querySelector('[data-purchase-row="' + purchaseId + '"]');
            if (row) {
                var cell = row.querySelector('[data-purchase-outstanding]');
                if (cell) {
                    cell.textContent = formatted;
                }
            }

            if (select) {
                var option = select.querySelector('option[value="' + purchaseId + '"]');
                if (option) {
                    option.dataset.outstanding = raw;
                }
            }

            syncAmountInput(false);
        }

        if (select) {
            select.addEventListener('change', function () {
                syncAmountInput(false);
            });
        }

        if (paymentModeInputs) {
            paymentModeInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    syncAmountInput(false);
                });
            });
        }

        syncAmountInput(false);

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var formData = new FormData(form);
                if (!formData.has('ajax')) {
                    formData.append('ajax', '1');
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return response.text().then(function (body) {
                                throw new Error('HTTP ' + response.status + ': ' + body);
                            });
                        }

                        return response.text().then(function (text) {
                            try {
                                return JSON.parse(text);
                            } catch (error) {
                                throw new Error('Invalid JSON response: ' + text);
                            }
                        });
                    })
                    .then(function (json) {
                        if (!json) {
                            renderFeedback('Respon tidak valid dari server.', 'error');
                            return;
                        }

                        renderFeedback(json.message || 'Tidak ada respon.', json.success ? 'success' : 'error');
                        if (json.success) {
                            updateOutstandingDisplay(json.purchase_id, json.formatted_outstanding, json.outstanding);
                        }
                    })
                    .catch(function (error) {
                        var message = 'Gagal menghubungi server.';
                        if (error instanceof Error && error.message) {
                            message = error.message;
                        }
                        renderFeedback(message, 'error');
                    });
            });
        }
    })();
</script>
