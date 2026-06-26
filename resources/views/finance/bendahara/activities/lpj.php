<?php
/** @var bool $hasActiveYear */
/** @var array<int, array<string, mixed>> $activities */
/** @var array<string, array<string, mixed>> $reports */
/** @var string $statusFilter */
/** @var array<int, string> $allowedStatuses */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$statusOptions = array_combine($allowedStatuses, array_map(static fn (string $status): string => ucwords(str_replace('_', ' ', $status)), $allowedStatuses));
$statusOptions = array_merge(['' => 'Semua status'], $statusOptions);
?>

<div class="space-y-6">
    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Progress LPJ Dana Kegiatan</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pantau pengumpulan laporan pertanggungjawaban dari setiap pengajuan dana kegiatan.</p>
            </div>
            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/dana-kegiatan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                <span class="ri-arrow-left-line text-base"></span>
                Kembali ke Pengajuan
            </a>
        </div>
    </div>

    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200/70 bg-amber-50 px-6 py-4 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum dipilih.</p>
            <p class="mt-1">Silakan tetapkan tahun ajaran aktif terlebih dahulu untuk melihat progress LPJ.</p>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <form method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Daftar Pengajuan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tabel di bawah menampilkan pengajuan dana kegiatan beserta status LPJ.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <label for="status-filter" class="sr-only">Status Kegiatan</label>
                    <select id="status-filter" name="status" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 sm:w-56 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        Terapkan
                    </button>
                </div>
            </form>

            <?php if (empty($activities)): ?>
                <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Belum ada pengajuan yang perlu dipantau untuk LPJ pada tahun ajaran ini.</p>
            <?php else: ?>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Kode / Kegiatan</th>
                                <th class="py-3 pr-4 font-semibold">Guru</th>
                                <th class="py-3 pr-4 text-right font-semibold">Estimasi</th>
                                <th class="py-3 pr-4 font-semibold">Status Kegiatan</th>
                                <th class="py-3 pr-4 font-semibold">Status LPJ</th>
                                <th class="py-3 pr-0 font-semibold">Lampiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                    $activityId = (int) ($activity['id'] ?? 0);
                                    $report = $reports[(string) $activityId] ?? null;
                                    $activityStatus = (string) ($activity['status'] ?? 'diajukan');
                                    $statusBadge = match ($activityStatus) {
                                        'diajukan' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                        'diverifikasi_bendahara' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                        'menunggu_acc' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
                                        'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'selesai' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
                                    };

                                    $lpjLabel = 'Belum dikumpulkan';
                                    $lpjClass = 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300';
                                    $lpjTimestamp = null;
                                    $lpjLink = null;

                                    if ($report !== null) {
                                        $lpjLabel = 'Sudah dikumpulkan';
                                        $lpjClass = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300';
                                        $lpjTimestamp = $report['updated_at'] ?? $report['created_at'] ?? null;
                                        if (!empty($report['bukti_path'])) {
                                            $lpjLink = base_url('storage/' . ltrim((string) $report['bukti_path'], '/'));
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 pr-4 text-right align-top font-semibold"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $activityStatus)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $lpjClass ?>"><?= htmlspecialchars($lpjLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($lpjTimestamp !== null): ?>
                                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Diperbarui: <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $lpjTimestamp)), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-0 align-top text-sm">
                                        <?php if ($lpjLink !== null): ?>
                                            <a href="<?= htmlspecialchars($lpjLink, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900" target="_blank" rel="noopener noreferrer">
                                                <span class="ri-attachment-2 text-sm"></span>
                                                Lihat Lampiran
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 dark:text-slate-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

