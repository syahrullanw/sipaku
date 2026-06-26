<?php
/** @var array<int, array<string, mixed>> $activities */
/** @var bool $canSubmit */
/** @var array<int, string> $submissionErrors */
/** @var string|null $headmasterName */
/** @var int $maxAttachmentSizeKb */
/** @var array<int, string> $allowedAttachmentMimes */
/** @var array<string, array<string, mixed>> $reports */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$statusBadge = static function (string $status): string {
    return match ($status) {
        'diajukan' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
        'diverifikasi_bendahara', 'menunggu_acc' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
        'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
        'selesai' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
        'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
        default => 'bg-slate-200 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
    };
};

$friendlyMimeNames = [
    'application/pdf' => 'PDF',
    'image/jpeg' => 'JPG',
    'image/png' => 'PNG',
];

$allowedDescriptions = array_values(array_unique(array_map(
    static fn (string $mime): string => $friendlyMimeNames[$mime] ?? strtoupper($mime),
    $allowedAttachmentMimes
)));
?>

<div class="space-y-8">
    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Pengajuan Dana Kegiatan</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Ajukan kebutuhan dana kegiatan sekolah. Pengajuan akan diverifikasi bendahara dan diteruskan kepada kepala sekolah<?= $headmasterName !== null ? ' (' . htmlspecialchars($headmasterName, ENT_QUOTES, 'UTF-8') . ')' : '' ?> untuk persetujuan.
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
                <form
                    method="post"
                    action="<?= htmlspecialchars(base_url('keuangan/guru/dana-kegiatan'), ENT_QUOTES, 'UTF-8') ?>"
                    enctype="multipart/form-data"
                    class="space-y-5"
                >
                    <?= csrf_field() ?>
                    <div class="grid gap-4 lg:grid-cols-12">
                        <div class="lg:col-span-4">
                            <label for="activity-category" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Kategori Kegiatan <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="activity-category"
                                name="kategori"
                                value="<?= htmlspecialchars((string) old('kategori', ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="cth. Lomba, Workshop, Kegiatan Kelas"
                                maxlength="100"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                        </div>
                        <div class="lg:col-span-5">
                            <label for="activity-title" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Judul Kegiatan <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="activity-title"
                                name="judul"
                                value="<?= htmlspecialchars((string) old('judul', ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="cth. Kunjungan Industri XI TKJ"
                                maxlength="180"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                        </div>
                        <div class="lg:col-span-3">
                            <label for="activity-estimate" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Estimasi Biaya <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="activity-estimate"
                                name="estimasi"
                                value="<?= htmlspecialchars((string) old('estimasi', ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="cth. 3.500.000"
                                inputmode="decimal"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                required
                            >
                        </div>
                    </div>
                    <div>
                        <label for="activity-description" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Rincian Kegiatan</label>
                        <textarea
                            id="activity-description"
                            name="deskripsi"
                            rows="4"
                            placeholder="Tuliskan kebutuhan, peserta, dan jadwal pelaksanaan"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        ><?= htmlspecialchars((string) old('deskripsi', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div>
                        <label for="activity-attachment" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Lampiran (Opsional)</label>
                        <input
                            type="file"
                            id="activity-attachment"
                            name="lampiran"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-slate-700 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700"
                        >
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Format yang diperbolehkan: <?= htmlspecialchars(implode(', ', $allowedDescriptions), ENT_QUOTES, 'UTF-8') ?>. Maksimal <?= htmlspecialchars((string) $maxAttachmentSizeKb, ENT_QUOTES, 'UTF-8') ?> KB.
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                        >
                            <span class="ri-send-plane-line text-base"></span>
                            Kirim Pengajuan
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Pengajuan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pantau progres persetujuan dan pencairan dana kegiatan Anda.</p>
                </div>
                <a
                    href="<?= htmlspecialchars(base_url('keuangan/guru'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-200/70 bg-indigo-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-600 shadow-sm hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 focus:ring-offset-2 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-200 dark:focus:ring-offset-slate-900"
                >
                    <span class="ri-dashboard-line text-base"></span>
                    Ringkasan
                </a>
            </div>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($activities)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pengajuan dana kegiatan.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Kegiatan</th>
                                <th class="py-3 pr-4 font-semibold">Kategori</th>
                                <th class="py-3 pr-4 text-right font-semibold">Estimasi</th>
                                <th class="py-3 pr-4 font-semibold">Status</th>
                                <th class="py-3 pr-4 font-semibold">LPJ</th>
                                <th class="py-3 pr-4 font-semibold">Diajukan</th>
                                <th class="py-3 pr-0 font-semibold">Keputusan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                    $activityId = (int) ($activity['id'] ?? 0);
                                    $status = (string) ($activity['status'] ?? '');
                                    $badgeClass = $statusBadge($status);
                                    $submittedAt = isset($activity['tanggal_pengajuan']) ? date('d M Y H:i', strtotime((string) $activity['tanggal_pengajuan'])) : '-';
                                    $approvedAt = isset($activity['tanggal_acc']) && $activity['tanggal_acc'] !== null ? date('d M Y H:i', strtotime((string) $activity['tanggal_acc'])) : '-';
                                    $reportKey = (string) $activityId;
                                    $lpj = $reports[$reportKey] ?? null;
                                    $lpjUpdatedAt = $lpj['updated_at'] ?? $lpj['created_at'] ?? null;
                                    $lpjAllowed = in_array($status, ['disetujui', 'selesai'], true);
                                    $lpjRoute = base_url('keuangan/guru/dana-kegiatan/' . $activityId . '/lpj');
                                ?>
                                <tr>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!empty($activity['deskripsi'])): ?>
                                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $activity['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($activity['catatan'])): ?>
                                            <div class="mt-2 rounded-md bg-slate-50 p-2 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                                                <p class="font-semibold uppercase tracking-wide">Catatan</p>
                                                <p class="mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars((string) $activity['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 align-top"><?= htmlspecialchars($activity['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 text-right align-top font-semibold"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <?php if ($lpj !== null): ?>
                                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">Sudah dikumpulkan</p>
                                            <?php if ($lpjUpdatedAt !== null): ?>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Terakhir: <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $lpjUpdatedAt)), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-xs font-semibold text-amber-600 dark:text-amber-300">Belum dikumpulkan</p>
                                        <?php endif; ?>
                                        <?php if ($lpjAllowed && $activityId > 0): ?>
                                            <a
                                                href="<?= htmlspecialchars($lpjRoute, ENT_QUOTES, 'UTF-8') ?>"
                                                class="mt-2 inline-flex items-center gap-1 rounded-lg border border-indigo-200/70 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 focus:ring-offset-1 dark:border-indigo-500/40 dark:text-indigo-200 dark:hover:bg-indigo-500/10 dark:focus:ring-offset-slate-900"
                                            >
                                                <span class="ri-edit-line text-sm"></span>
                                                Kelola LPJ
                                            </a>
                                        <?php else: ?>
                                            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Menunggu persetujuan akhir.</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 align-top text-sm"><?= htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-3 pr-0 align-top text-sm"><?= htmlspecialchars($approvedAt, ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
