<?php
/** @var bool $hasActiveYear */
/** @var float $balance */
/** @var array<int, array<string, mixed>> $billingOptions */
/** @var array<int, array<string, mixed>> $purchaseOptions */
/** @var array<int, array<string, mixed>> $transactions */
/** @var float $savingsOutstanding */
/** @var array<int, array<string, mixed>> $savingsHistory */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>

<div class="space-y-8">
    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum ditetapkan.</p>
            <p class="mt-1">Tetapkan tahun ajaran aktif terlebih dahulu untuk mengelola kas utama.</p>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Kas Utama Sekolah</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gunakan kas utama sebagai penampung sementara saat memindahkan dana antar kas.</p>
            </div>
            <div class="rounded-xl border border-slate-200/70 bg-white px-4 py-3 text-right shadow-sm dark:border-slate-700/70 dark:bg-slate-800/50">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Kas Utama</p>
                <p class="text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($balance), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="mt-4 rounded-lg border border-slate-200/60 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700/70 dark:bg-slate-800/60 dark:text-slate-200">
            <p class="flex items-center justify-between">
                <span>Saldo tabungan siswa yang dipinjam sementara</span>
                <span class="font-semibold <?= $savingsOutstanding > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300' ?>">
                    <?= htmlspecialchars($formatCurrency($savingsOutstanding), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </p>
        </div>
    </div>

    <div class="grid gap-8 xl:grid-cols-2">
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Tambahkan Dana Eksternal</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Catat dana masuk dari sumber luar (misal pinjaman atau donasi) yang langsung menambah kas utama.</p>
                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/eksternal'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label for="external-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            id="external-amount"
                            name="amount"
                            placeholder="cth. 10.000.000"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="external-recorded-at" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal &amp; Waktu</label>
                        <input
                            type="datetime-local"
                            id="external-recorded-at"
                            name="recorded_at"
                            value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="external-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Keterangan</label>
                        <textarea
                            id="external-note"
                            name="note"
                            rows="2"
                            placeholder="Sumber dana atau catatan tambahan"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Simpan Dana Masuk
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Pinjam Dana Tabungan ke Kas Utama</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Catat dana tabungan siswa yang digunakan sementara untuk kebutuhan operasional.</p>
                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/tabungan/pinjam'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label for="savings-borrow-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            id="savings-borrow-amount"
                            name="amount"
                            placeholder="cth. 25.000.000"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="savings-borrow-recorded-at" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal &amp; Waktu</label>
                        <input
                            type="datetime-local"
                            id="savings-borrow-recorded-at"
                            name="recorded_at"
                            value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="savings-borrow-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Keterangan</label>
                        <textarea
                            id="savings-borrow-note"
                            name="note"
                            rows="2"
                            placeholder="Catatan penggunaan dana tabungan"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Pinjamkan ke Kas Utama
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Pindahkan Dana Antar Kas</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gunakan formulir berikut untuk memindahkan dana antara kas tagihan, kas pembelian perlengkapan, dan kas utama.</p>

                <div class="mt-4 space-y-5">
                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/transfer-masuk'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 rounded-lg border border-slate-200/60 bg-slate-50 p-4 dark:border-slate-700/70 dark:bg-slate-800/50">
                        <?= csrf_field() ?>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kas Tagihan ➜ Kas Utama</h3>
                        <div>
                            <label for="transfer-in-billing" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pilih Kas Tagihan</label>
                            <select
                                id="transfer-in-billing"
                                name="billing_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                                <option value="">Pilih kas tagihan</option>
                                <?php foreach ($billingOptions as $option): ?>
                                    <option value="<?= (int) ($option['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($option['title'] ?? 'Tagihan', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($option['code'] ?? '')): ?>
                                            (<?= htmlspecialchars($option['code'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?php endif; ?>
                                        · Saldo <?= htmlspecialchars($formatCurrency((float) ($option['balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="transfer-in-amount" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</label>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    id="transfer-in-amount"
                                    name="amount"
                                    placeholder="cth. 5.000.000"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                            <div>
                                <label for="transfer-in-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal &amp; Waktu</label>
                                <input
                                    type="datetime-local"
                                    id="transfer-in-date"
                                    name="recorded_at"
                                    value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                        </div>
                        <div>
                            <label for="transfer-in-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                            <textarea
                                id="transfer-in-note"
                                name="note"
                                rows="2"
                                placeholder="Catatan tambahan (opsional)"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                Pindahkan ke Kas Utama
                            </button>
                        </div>
                    </form>

                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/transfer-keluar'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 rounded-lg border border-slate-200/60 bg-slate-50 p-4 dark:border-slate-700/70 dark:bg-slate-800/50">
                        <?= csrf_field() ?>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kas Utama ➜ Kas Tagihan</h3>
                        <div>
                            <label for="transfer-out-billing" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pilih Kas Tagihan</label>
                            <select
                                id="transfer-out-billing"
                                name="billing_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                                <option value="">Pilih kas tagihan</option>
                                <?php foreach ($billingOptions as $option): ?>
                                    <option value="<?= (int) ($option['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($option['title'] ?? 'Tagihan', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($option['code'] ?? '')): ?>
                                            (<?= htmlspecialchars($option['code'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="transfer-out-amount" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</label>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    id="transfer-out-amount"
                                    name="amount"
                                    placeholder="cth. 5.000.000"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                            <div>
                                <label for="transfer-out-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal &amp; Waktu</label>
                                <input
                                    type="datetime-local"
                                    id="transfer-out-date"
                                    name="recorded_at"
                                    value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                        </div>
                        <div>
                            <label for="transfer-out-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                            <textarea
                                id="transfer-out-note"
                                name="note"
                                rows="2"
                                placeholder="Catatan tambahan (opsional)"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                Kembalikan ke Kas Tagihan
                            </button>
                        </div>
                    </form>

                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/pembelian/transfer-masuk'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 rounded-lg border border-slate-200/60 bg-white p-4 dark:border-slate-700/70 dark:bg-slate-900/40">
                        <?= csrf_field() ?>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kas Pembelian ➜ Kas Utama</h3>
                        <div>
                            <label for="purchase-transfer-in" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pilih Kas Pembelian</label>
                            <select
                                id="purchase-transfer-in"
                                name="purchase_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                                <option value="">Pilih kas pembelian</option>
                                <?php foreach ($purchaseOptions as $option): ?>
                                    <option value="<?= (int) ($option['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($option['title'] ?? 'Pembelian', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($option['student'] ?? '')): ?>
                                            · <?= htmlspecialchars($option['student'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                        <?php if (!empty($option['code'] ?? '')): ?>
                                            (<?= htmlspecialchars($option['code'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?php endif; ?>
                                        · Saldo <?= htmlspecialchars($formatCurrency((float) ($option['balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="purchase-transfer-in-amount" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</label>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    id="purchase-transfer-in-amount"
                                    name="amount"
                                    placeholder="cth. 3.000.000"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                            <div>
                                <label for="purchase-transfer-in-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal &amp; Waktu</label>
                                <input
                                    type="datetime-local"
                                    id="purchase-transfer-in-date"
                                    name="recorded_at"
                                    value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                        </div>
                        <div>
                            <label for="purchase-transfer-in-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                            <textarea
                                id="purchase-transfer-in-note"
                                name="note"
                                rows="2"
                                placeholder="Catatan tambahan (opsional)"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                Kembalikan ke Kas Utama
                            </button>
                        </div>
                    </form>

                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/pembelian/transfer-keluar'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 rounded-lg border border-slate-200/60 bg-slate-50 p-4 dark:border-slate-700/70 dark:bg-slate-800/50">
                        <?= csrf_field() ?>
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Kas Utama ➜ Kas Pembelian</h3>
                        <div>
                            <label for="purchase-transfer-out" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pilih Kas Pembelian</label>
                            <select
                                id="purchase-transfer-out"
                                name="purchase_id"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                                <option value="">Pilih kas pembelian</option>
                                <?php foreach ($purchaseOptions as $option): ?>
                                    <option value="<?= (int) ($option['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($option['title'] ?? 'Pembelian', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($option['student'] ?? '')): ?>
                                            · <?= htmlspecialchars($option['student'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                        <?php if (!empty($option['code'] ?? '')): ?>
                                            (<?= htmlspecialchars($option['code'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="purchase-transfer-out-amount" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</label>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    id="purchase-transfer-out-amount"
                                    name="amount"
                                    placeholder="cth. 2.500.000"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                            <div>
                                <label for="purchase-transfer-out-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal &amp; Waktu</label>
                                <input
                                    type="datetime-local"
                                    id="purchase-transfer-out-date"
                                    name="recorded_at"
                                    value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    required
                                >
                            </div>
                        </div>
                        <div>
                            <label for="purchase-transfer-out-note" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                            <textarea
                                id="purchase-transfer-out-note"
                                name="note"
                                rows="2"
                                placeholder="Catatan tambahan (opsional)"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                Kirim ke Kas Pembelian
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Pengembalian Dana Tabungan</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kembalikan dana tabungan yang sebelumnya dipakai setelah kas utama mencukupi.</p>
                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kas-utama/tabungan/kembalikan'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label for="savings-return-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            id="savings-return-amount"
                            name="amount"
                            placeholder="cth. 10.000.000"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="savings-return-recorded-at" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal &amp; Waktu</label>
                        <input
                            type="datetime-local"
                            id="savings-return-recorded-at"
                            name="recorded_at"
                            value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="savings-return-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Keterangan</label>
                        <textarea
                            id="savings-return-note"
                            name="note"
                            rows="2"
                            placeholder="Catatan tambahan (opsional)"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Kembalikan ke Tabungan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Transaksi Kas Utama</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Catatan perpindahan dana antar kas maupun tambahan dari sumber eksternal.</p>
            <?php if (empty($transactions)): ?>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Belum ada transaksi yang tercatat.</p>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Tanggal</th>
                                <th class="py-3 pr-4 font-semibold">Deskripsi</th>
                                <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <?php
                                    $type = (string) ($transaction['tipe'] ?? '');
                                    $badgeClass = $type === 'masuk'
                                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300'
                                        : 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300';
                                    $sourceType = (string) ($transaction['sumber_tipe'] ?? '');
                                    $targetType = (string) ($transaction['tujuan_tipe'] ?? '');
                                    $descriptionParts = [];
                                    if ($sourceType === 'eksternal') {
                                        $descriptionParts[] = 'Dana eksternal';
                                    } elseif ($sourceType === 'tagihan') {
                                        $descriptionParts[] = 'Tagihan #' . ($transaction['sumber_id'] ?? '-');
                                    } elseif ($sourceType === 'tabungan') {
                                        $descriptionParts[] = 'Tabungan siswa';
                                    } elseif ($sourceType === 'pembelian') {
                                        $descriptionParts[] = 'Kas pembelian #' . ($transaction['sumber_id'] ?? '-');
                                    } else {
                                        $descriptionParts[] = ucfirst(str_replace('_', ' ', $sourceType));
                                    }
                                    if ($targetType === 'tagihan') {
                                        $descriptionParts[] = '→ Tagihan #' . ($transaction['tujuan_id'] ?? '-');
                                    } elseif ($targetType === 'tabungan') {
                                        $descriptionParts[] = '→ Tabungan siswa';
                                    } elseif ($targetType === 'tak_terduga') {
                                        $descriptionParts[] = '→ Pengeluaran tak terduga';
                                    } elseif ($targetType === 'pembelian') {
                                        $descriptionParts[] = '→ Pembelian #' . ($transaction['tujuan_id'] ?? '-');
                                    }
                                    $description = trim((string) ($transaction['keterangan'] ?? ''));
                                ?>
                                <tr>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($transaction['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $badgeClass ?>">
                                            <?= htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(implode(' ', $descriptionParts), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($description !== ''): ?>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right font-semibold align-top <?= $type === 'masuk' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
                                        <?= htmlspecialchars($formatCurrency((float) ($transaction['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Penyesuaian Tabungan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Monitor peminjaman dan pengembalian dana tabungan siswa yang digunakan sementara.</p>
            <?php if (empty($savingsHistory)): ?>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Belum ada data penyesuaian tabungan.</p>
            <?php else: ?>
                <div class="mt-4 space-y-3">
                    <?php foreach ($savingsHistory as $item): ?>
                        <?php
                            $type = (string) ($item['tipe'] ?? '');
                            $badgeClass = $type === 'pinjam'
                                ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300'
                                : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300';
                        ?>
                        <div class="rounded-lg border border-slate-200/70 px-4 py-3 text-sm dark:border-slate-700/70">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= $type === 'pinjam' ? 'Pinjam dari Tabungan' : 'Kembalikan ke Tabungan' ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($item['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                                    <?= htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                Nominal: <span class="font-semibold"><?= htmlspecialchars($formatCurrency((float) ($item['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                            <?php if (!empty($item['keterangan'])): ?>
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300"><?= nl2br(htmlspecialchars((string) $item['keterangan'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
