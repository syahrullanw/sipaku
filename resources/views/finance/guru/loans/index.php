<?php
/** @var array<int, array<string, mixed>> $loans */
/** @var array<int, array<string, mixed>> $activeLoans */
/** @var bool $canSubmit */
/** @var array<int, string> $submissionErrors */
/** @var string|null $headmasterName */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$statusBadge = static function (string $status): string {
    return match ($status) {
        'diajukan', 'menunggu_acc' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
        'disetujui' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
        'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
    };
};
?>

<div class="space-y-8">
    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Pengajuan Kasbon Guru</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Ajukan kasbon/pinjaman untuk kebutuhan mendesak. Permohonan akan dikirim ke kepala sekolah<?= $headmasterName !== null ? ' (' . htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') . ')' : '' ?> untuk persetujuan.
            </p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <?php if (!$canSubmit): ?>
                <div class="rounded-lg border border-amber-200/70 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    <p class="font-semibold">Pengajuan belum dapat dilakukan.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <?php foreach ($submissionErrors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/guru/kasbon'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 lg:grid-cols-12">
                    <?= csrf_field() ?>
                    <div class="lg:col-span-4">
                        <label for="loan-amount" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal Pengajuan <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            id="loan-amount"
                            name="nominal"
                            value="<?= htmlspecialchars((string) old('nominal', ''), ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="cth. 5.000.000"
                            inputmode="decimal"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        >
                    </div>
                    <div class="lg:col-span-2">
                        <label for="loan-tenor" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tenor (bulan)</label>
                        <input
                            type="number"
                            min="0"
                            max="60"
                            id="loan-tenor"
                            name="tenor"
                            value="<?= htmlspecialchars((string) old('tenor', ''), ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="cth. 6"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Biarkan kosong bila belum ditentukan.</p>
                    </div>
                    <div class="lg:col-span-6">
                        <label for="loan-purpose" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tujuan Penggunaan <span class="text-rose-500">*</span></label>
                        <textarea
                            id="loan-purpose"
                            name="tujuan"
                            rows="4"
                            placeholder="Jelaskan kebutuhan kasbon secara ringkas"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            required
                        ><?= htmlspecialchars((string) old('tujuan', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="lg:col-span-12">
                        <label for="loan-note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan Tambahan (opsional)</label>
                        <textarea
                            id="loan-note"
                            name="catatan"
                            rows="3"
                            placeholder="Tambahkan catatan untuk bendahara jika diperlukan"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ><?= htmlspecialchars((string) old('catatan', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="lg:col-span-12 flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                        >
                            Ajukan Kasbon
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Riwayat Pengajuan Kasbon</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pantau status persetujuan dan pencairan kasbon yang pernah diajukan.</p>
        </div>
        <div class="px-6 py-5">
            <?php if (empty($loans)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pengajuan kasbon yang tercatat.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $status = (string) ($loan['status'] ?? '');
                            $badgeClass = $statusBadge($status);
                            $submittedAt = isset($loan['tanggal_pengajuan']) ? date('d M Y', strtotime((string) $loan['tanggal_pengajuan'])) : '-';
                            $approvedAt = isset($loan['tanggal_acc']) ? date('d M Y H:i', strtotime((string) $loan['tanggal_acc'])) : null;
                            $disbursedAt = isset($loan['tanggal_cair']) ? date('d M Y H:i', strtotime((string) $loan['tanggal_cair'])) : null;
                        ?>
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700/70">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars((string) ($loan['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Diajukan: <?= htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal Diminta</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($loan['nominal_diminta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Terhutang</p>
                                    <p class="font-medium text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($loan['saldo_terhutang'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tenor</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(isset($loan['tenor_bulan']) && $loan['tenor_bulan'] !== null ? $loan['tenor_bulan'] . ' bulan' : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Disetujui</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($approvedAt ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Cair</p>
                                    <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($disbursedAt ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <?php if (!empty($loan['tujuan'])): ?>
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tujuan</p>
                                    <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $loan['tujuan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($loan['catatan'])): ?>
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800/50 dark:text-slate-200">
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</p>
                                    <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $loan['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($loan['catatan_penolakan'])): ?>
                                <div class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/20 dark:text-rose-200">
                                    <p class="text-xs uppercase tracking-wide font-semibold">Catatan Penolakan</p>
                                    <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $loan['catatan_penolakan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
