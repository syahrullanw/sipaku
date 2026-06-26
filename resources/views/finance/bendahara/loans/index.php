<?php
/** @var array<int, array<string, mixed>> $awaitingLoans */
/** @var array<int, array<string, mixed>> $readyLoans */
/** @var array<int, array<string, mixed>> $historyLoans */
/** @var array<int, array<string, mixed>> $cashOptions */
/** @var float $generalCashBalance */
/** @var bool $hasActiveYear */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$formatDate = static function (?string $timestamp, string $fallback = '-'): string {
    if ($timestamp === null || $timestamp === '') {
        return $fallback;
    }

    $parsed = strtotime($timestamp);

    return $parsed === false ? $fallback : date('d M Y H:i', $parsed);
};
$statusBadge = static function (string $status): string {
    return match ($status) {
        'diajukan', 'menunggu_acc' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
        'disetujui' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
        'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
    };
};
$defaultDisbursement = date('Y-m-d\TH:i');
?>

<div class="space-y-8">
    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum ditetapkan.</p>
            <p class="mt-1">Tentukan tahun ajaran aktif terlebih dahulu untuk mengelola pencairan kasbon guru.</p>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Kasbon Guru Siap Dicairkan</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Kasbon yang sudah disetujui kepala sekolah dapat dicairkan dengan memilih sumber kas aktif. Sistem otomatis mencatat arus kas keluar dan mengurangi saldo kas.
            </p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <?php if (empty($readyLoans)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada kasbon yang siap dicairkan.</p>
            <?php else: ?>
                <?php $hasGeneralCash = $generalCashBalance > 0; ?>
                <?php if ($hasGeneralCash): ?>
                    <div class="rounded-lg border border-emerald-200/60 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                        Kas utama tersedia sebesar <span class="font-semibold"><?= htmlspecialchars($formatCurrency($generalCashBalance), ENT_QUOTES, 'UTF-8') ?></span> dan dapat digunakan sebagai sumber pencairan.
                    </div>
                <?php endif; ?>
                <?php if (!$hasGeneralCash && empty($cashOptions)): ?>
                    <div class="rounded-lg border border-amber-200/70 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="font-semibold">Tidak ditemukan kas aktif dengan saldo positif.</p>
                        <p class="mt-1">Tambahkan kas utama atau aktifkan kas tagihan terlebih dahulu sebelum melakukan pencairan.</p>
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <?php foreach ($readyLoans as $loan): ?>
                        <?php
                            $loanId = (int) ($loan['id'] ?? 0);
                            $teacherName = (string) ($loan['guru_nama'] ?? 'Guru');
                            $loanCode = (string) ($loan['kode'] ?? '-');
                            $amount = (float) ($loan['nominal_diminta'] ?? 0);
                            $tenor = $loan['tenor_bulan'] !== null ? (int) $loan['tenor_bulan'] : null;
                            $approvedAt = $formatDate($loan['tanggal_acc'] ?? null, '-');
                        ?>
                        <div class="rounded-xl border border-slate-200/70 p-5 dark:border-slate-700/70">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Kasbon: <?= htmlspecialchars($loanCode, ENT_QUOTES, 'UTF-8') ?> · Disetujui: <?= htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge((string) ($loan['status'] ?? '')) ?>">Disetujui</span>
                            </div>
                            <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($amount), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tenor</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($tenor !== null ? $tenor . ' bulan' : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Terhutang</p>
                                    <p class="font-medium text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($loan['saldo_terhutang'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <?php if (!empty($loan['tujuan'])): ?>
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tujuan</p>
                                    <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $loan['tujuan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/kasbon/' . $loanId . '/cairkan'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-4 lg:grid-cols-12">
                                <?= csrf_field() ?>
                                <div class="lg:col-span-4">
                                    <label for="cash-<?= $loanId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Sumber Kas <span class="text-rose-500">*</span></label>
                                    <select
                                        id="cash-<?= $loanId ?>"
                                        name="cash_source"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        <?= (!$hasGeneralCash && empty($cashOptions)) ? 'disabled' : '' ?>
                                        required
                                    >
                                        <option value="">Pilih kas aktif</option>
                                        <?php if ($hasGeneralCash): ?>
                                            <option value="general">
                                                Kas Utama · Saldo <?= htmlspecialchars($formatCurrency($generalCashBalance), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endif; ?>
                                        <?php foreach ($cashOptions as $cash): ?>
                                            <option value="billing:<?= (int) ($cash['id'] ?? 0) ?>">
                                                <?= htmlspecialchars((string) ($cash['judul'] ?? 'Kas'), ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($cash['kode'])): ?>
                                                    (<?= htmlspecialchars((string) $cash['kode'], ENT_QUOTES, 'UTF-8') ?>)
                                                <?php endif; ?>
                                                · Saldo <?= htmlspecialchars($formatCurrency((float) ($cash['saldo'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="lg:col-span-4">
                                    <label for="time-<?= $loanId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tanggal &amp; Waktu Pencairan</label>
                                    <input
                                        type="datetime-local"
                                        id="time-<?= $loanId ?>"
                                        name="disbursement_time"
                                        value="<?= htmlspecialchars($defaultDisbursement, ENT_QUOTES, 'UTF-8') ?>"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        required
                                    >
                                </div>
                                <div class="lg:col-span-4">
                                    <label for="note-<?= $loanId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan (opsional)</label>
                                    <input
                                        type="text"
                                        id="note-<?= $loanId ?>"
                                        name="note"
                                        placeholder="cth. dicairkan tunai"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                </div>
                                <div class="lg:col-span-12 flex justify-end">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                        <?= (!$hasGeneralCash && empty($cashOptions)) ? 'disabled' : '' ?>
                                    >
                                        Cairkan Kasbon
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Menunggu Persetujuan Kepala Sekolah</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kasbon masih menunggu keputusan kepala sekolah sebelum dapat dicairkan.</p>
            </div>
            <div class="px-6 py-5">
                <?php if (empty($awaitingLoans)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada kasbon yang menunggu persetujuan.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($awaitingLoans as $loan): ?>
                            <div class="rounded-lg border border-slate-200/70 px-4 py-3 text-sm dark:border-slate-700/70">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($loan['guru_nama'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($loan['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · Diajukan <?= htmlspecialchars($formatDate($loan['created_at'] ?? null, '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusBadge((string) ($loan['status'] ?? '')) ?>">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($loan['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">Nominal: <span class="font-medium"><?= htmlspecialchars($formatCurrency((float) ($loan['nominal_diminta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Pencairan Kasbon</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Daftar pencairan terbaru sebagai referensi audit.</p>
            </div>
            <div class="px-6 py-5">
                <?php if (empty($historyLoans)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pencairan kasbon yang tercatat.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($historyLoans as $loan): ?>
                            <div class="rounded-lg border border-slate-200/70 px-4 py-3 text-sm dark:border-slate-700/70">
                                <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($loan['guru_nama'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($loan['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> · Dicairkan <?= htmlspecialchars($formatDate($loan['tanggal_cair'] ?? null, '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">Nominal: <span class="font-medium"><?= htmlspecialchars($formatCurrency((float) ($loan['nominal_diminta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span></p>
                                <?php if (!empty($loan['catatan'])): ?>
                                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $loan['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
