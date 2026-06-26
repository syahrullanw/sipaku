<?php
/** @var bool $hasActiveYear */
/** @var array<int, array<string, mixed>> $pendingActivities */
/** @var array<int, array<string, mixed>> $awaitingApprovalActivities */
/** @var array<int, array<string, mixed>> $readyActivities */
/** @var array<int, array<string, mixed>> $historyActivities */
/** @var float $generalCashBalance */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$statusBadge = static function (string $status): string {
    return match ($status) {
        'diajukan' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
        'menunggu_acc', 'diverifikasi_bendahara' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
        'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
        'selesai' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
    };
};

$nowLocal = date('Y-m-d\TH:i');
?>

<div class="space-y-8">
    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200/70 bg-amber-50 p-6 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <h2 class="text-lg font-semibold">Tahun ajaran aktif belum dipilih.</h2>
            <p class="mt-2 text-sm">Silakan tetapkan tahun ajaran aktif terlebih dahulu untuk mengelola dana kegiatan.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <p class="text-sm text-slate-500 dark:text-slate-400">Saldo Kas Utama</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency($generalCashBalance), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Pastikan saldo mencukupi sebelum melakukan pencairan dana kegiatan.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/dana-kegiatan/lpj'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                <span class="ri-clipboard-line text-base"></span>
                Progress LPJ Dana Kegiatan
            </a>
        </div>

        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Perlu Verifikasi Bendahara</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Periksa kelengkapan pengajuan sebelum diteruskan ke kepala sekolah.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <?php if (empty($pendingActivities)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada pengajuan baru.</p>
                    <?php else: ?>
                        <?php foreach ($pendingActivities as $activity): ?>
                            <?php $activityId = (int) ($activity['id'] ?? 0); ?>
                            <div class="rounded-xl border border-slate-200/70 p-5 dark:border-slate-700/70">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($activity['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge((string) ($activity['status'] ?? '')) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($activity['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi Biaya</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Pengajuan</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($activity['tanggal_pengajuan'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($activity['deskripsi'])): ?>
                                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                                        <p class="font-semibold uppercase tracking-wide">Rincian</p>
                                        <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $activity['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-5 flex flex-col gap-3 lg:flex-row">
                                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/dana-kegiatan/' . $activityId . '/verifikasi'), ENT_QUOTES, 'UTF-8') ?>" class="flex-1 space-y-3">
                                        <?= csrf_field() ?>
                                        <textarea
                                            name="catatan"
                                            rows="2"
                                            placeholder="Catatan untuk kepala sekolah (opsional)"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        ></textarea>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                        >
                                            <span class="ri-checkbox-circle-line text-base"></span>
                                            Teruskan ke Kepala Sekolah
                                        </button>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/dana-kegiatan/' . $activityId . '/tolak'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 lg:w-60" onsubmit="return confirm('Tolak pengajuan dana kegiatan ini?');">
                                        <?= csrf_field() ?>
                                        <input
                                            type="text"
                                            name="catatan"
                                            placeholder="Alasan penolakan"
                                            class="w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-sm text-rose-600 placeholder:text-rose-400 focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200"
                                        >
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-2 rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                        >
                                            <span class="ri-close-circle-line text-base"></span>
                                            Tolak Pengajuan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Menunggu ACC Kepala Sekolah</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pengajuan sudah diverifikasi dan sedang menunggu keputusan kepala sekolah.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <?php if (empty($awaitingApprovalActivities)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada pengajuan di antrian kepala sekolah.</p>
                    <?php else: ?>
                        <?php foreach ($awaitingApprovalActivities as $activity): ?>
                            <div class="rounded-xl border border-slate-200/70 p-5 dark:border-slate-700/70">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($activity['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge((string) ($activity['status'] ?? '')) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($activity['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi Biaya</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Diverifikasi pada</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($activity['updated_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($activity['catatan'])): ?>
                                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                                        <p class="font-semibold uppercase tracking-wide">Catatan</p>
                                        <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $activity['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Siap Dicairkan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pengajuan yang telah disetujui kepala sekolah dan menunggu pencairan.</p>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <?php if (empty($readyActivities)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada pengajuan yang siap dicairkan.</p>
                    <?php else: ?>
                            <?php foreach ($readyActivities as $activity): ?>
                                <?php
                                    $activityId = (int) ($activity['id'] ?? 0);
                                    $defaultNominal = old('nominal');
                                    if ($defaultNominal === null || $defaultNominal === '') {
                                        $defaultNominal = isset($activity['estimasi_biaya']) ? (string) (float) $activity['estimasi_biaya'] : '';
                                    }
                                ?>
                                <div class="rounded-xl border border-slate-200/70 p-5 dark:border-slate-700/70">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($activity['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge((string) ($activity['status'] ?? '')) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($activity['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi Biaya</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Disetujui Kepala Sekolah</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($activity['tanggal_acc'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/dana-kegiatan/' . $activityId . '/cairkan'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-4 lg:grid-cols-12">
                                        <div class="lg:col-span-3">
                                            <label for="amount-<?= $activityId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nominal Pencairan <span class="text-rose-500">*</span></label>
                                            <input
                                                type="text"
                                                id="amount-<?= $activityId ?>"
                                                name="nominal"
                                                value="<?= htmlspecialchars($defaultNominal, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                inputmode="decimal"
                                                required
                                            >
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Masukkan angka tanpa pemisah ribuan, contoh: 100000.</p>
                                        </div>
                                        <div class="lg:col-span-5">
                                            <label for="type-<?= $activityId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jenis Pengeluaran <span class="text-rose-500">*</span></label>
                                            <input
                                                type="text"
                                                id="type-<?= $activityId ?>"
                                                name="jenis_pengeluaran"
                                                value="Pencairan Dana Kegiatan"
                                                maxlength="120"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                required
                                            >
                                        </div>
                                        <div class="lg:col-span-4">
                                            <label for="time-<?= $activityId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Waktu Pencairan</label>
                                            <input
                                                type="datetime-local"
                                                id="time-<?= $activityId ?>"
                                                name="waktu_pencairan"
                                                value="<?= htmlspecialchars($nowLocal, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            >
                                        </div>
                                    </div>
                                    <div>
                                        <label for="note-<?= $activityId ?>" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan Pencairan</label>
                                        <textarea
                                            id="note-<?= $activityId ?>"
                                            name="catatan"
                                            rows="3"
                                            placeholder="Informasi tambahan, nomor bukti, atau deskripsi penggunaan"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        ></textarea>
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                            onclick="return confirm('Konfirmasi pencairan dana kegiatan ini?');"
                                        >
                                            <span class="ri-hand-coin-line text-base"></span>
                                            Cairkan Dana
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Pengajuan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan status terakhir pengajuan dana kegiatan.</p>
                </div>
                <div class="px-6 py-4">
                    <?php if (empty($historyActivities)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada riwayat pengajuan.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                    <tr>
                                        <th class="py-3 pr-4 font-semibold">Kode</th>
                                        <th class="py-3 pr-4 font-semibold">Guru</th>
                                        <th class="py-3 pr-4 font-semibold">Judul</th>
                                        <th class="py-3 pr-4 font-semibold">Status</th>
                                        <th class="py-3 pr-4 font-semibold text-right">Estimasi</th>
                                        <th class="py-3 pr-0 font-semibold">Diupdate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                                    <?php foreach ($historyActivities as $activity): ?>
                                        <?php $status = (string) ($activity['status'] ?? ''); ?>
                                        <tr>
                                            <td class="py-3 pr-4 align-top font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($activity['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 pr-4 align-top"><?= htmlspecialchars($activity['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 pr-4 align-top">
                                                <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if (!empty($activity['catatan'])): ?>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $activity['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 pr-4 align-top">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadge($status) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="py-3 pr-4 text-right align-top"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 pr-0 align-top text-sm"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($activity['updated_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>
