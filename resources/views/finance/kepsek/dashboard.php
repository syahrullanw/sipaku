<?php
/** @var array<string, mixed> $summary */
/** @var array<int, array<string, mixed>> $topRevenue */
/** @var array<int, array<string, mixed>> $loanSummary */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>

<div class="space-y-8">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200/60 bg-emerald-50 p-5 shadow-sm shadow-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-300">Saldo Kas</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-200"><?= htmlspecialchars($formatCurrency((float) ($summary['cash_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pemasukan Bulan Ini</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($summary['monthly_income'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pengeluaran Bulan Ini</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($summary['monthly_expense'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-amber-200/60 bg-amber-50 p-5 shadow-sm shadow-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-amber-600 dark:text-amber-300">Menunggu Persetujuan</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-200"><?= number_format((int) ($summary['pending_approvals'] ?? 0), 0, ',', '.') ?></p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Sumber Pemasukan Terbesar Bulan Ini</h2>
            </div>
            <div class="px-6 py-4">
                <?php if (empty($topRevenue)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pemasukan tercatat bulan ini.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($topRevenue as $item): ?>
                            <li class="flex items-center justify-between rounded-lg border border-slate-200/70 px-4 py-3 text-sm dark:border-slate-700/70">
                                <span class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($item['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($item['total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Status Kasbon Guru</h2>
            </div>
            <div class="px-6 py-4">
                <?php if (empty($loanSummary)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada kasbon pada tahun ajaran aktif.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($loanSummary as $item): ?>
                            <?php
                            $status = (string) ($item['status'] ?? '');
                            $badgeClass = match ($status) {
                                'disetujui' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                            };
                            ?>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200/70 px-4 py-3 text-sm dark:border-slate-700/70">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white"><?= number_format((int) ($item['total'] ?? 0), 0, ',', '.') ?> kasbon</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Ringkasan Persetujuan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tinjau permohonan pada menu persetujuan untuk mengambil tindakan.</p>
            </div>
            <a href="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/approval'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                Lihat Daftar Persetujuan
            </a>
        </div>
    </div>
</div>
