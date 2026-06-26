<?php
/** @var array<int, array<string, mixed>> $requests */
/** @var array<string, string> $statusLabels */
/** @var array<int, string> $statusTabs */
/** @var string $currentTab */
/** @var array<string, mixed>|null $activeYear */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$tabLabels = [
    'approved' => 'Menunggu Pencairan',
    'funded' => 'Selesai Dicairkan',
    'reported' => 'LPJ Masuk',
    'all' => 'Semua Pengajuan',
];
$statusClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'bg-emerald-100 text-emerald-700',
        'funded' => 'bg-indigo-100 text-indigo-700',
        'reported' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
};
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-800">Pengadaan Alat Praktik Jurusan</h2>
            <p class="text-sm text-slate-500">
                Cairkan pengajuan alat praktik yang telah disetujui kepala sekolah dan pantau LPJ dari Kaprodi setelah pencairan.
            </p>
            <?php if ($activeYear !== null): ?>
                <p class="mt-1 text-xs text-slate-400">Tahun ajaran aktif: <?= htmlspecialchars((string) ($activeYear['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 flex flex-wrap gap-2">
            <?php foreach ($statusTabs as $tab): ?>
                <?php $isActive = $currentTab === $tab; ?>
                <a
                    href="<?= htmlspecialchars(base_url('keuangan/bendahara/pengadaan?status=' . $tab), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-medium <?= $isActive ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>"
                >
                    <?= htmlspecialchars($tabLabels[$tab] ?? ucfirst($tab), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Pengajuan</th>
                        <th class="px-6 py-3">Estimasi</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-6 text-center text-slate-500">Tidak ada pengajuan sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $row): ?>
                            <?php $status = (string) ($row['status'] ?? ''); ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($row['judul'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500">
                                        Jurusan: <?= htmlspecialchars((string) ($row['jurusan_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        • Kaprodi: <?= htmlspecialchars((string) ($row['guru_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        • Kode: <?= htmlspecialchars((string) ($row['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <?php if (!empty($row['review_note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Catatan Kepala Sekolah: <?= htmlspecialchars((string) $row['review_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['funding_note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Catatan Pencairan: <?= htmlspecialchars((string) $row['funding_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['lpj_deskripsi'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Ringkasan LPJ: <?= nl2br(htmlspecialchars((string) $row['lpj_deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['lpj_lampiran'])): ?>
                                        <?php $lpjLink = base_url('storage/' . ltrim((string) $row['lpj_lampiran'], '/')); ?>
                                        <p class="mt-1 text-xs">
                                            <a href="<?= htmlspecialchars($lpjLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Lihat Lampiran LPJ</a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= $formatCurrency((float) ($row['total_estimasi'] ?? 0)) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass($status) ?>">
                                        <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($status === 'approved'): ?>
                                        <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/pengadaan/' . (int) ($row['id'] ?? 0) . '/fund'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-2">
                                            <?= csrf_field() ?>
                                            <textarea
                                                name="catatan"
                                                rows="2"
                                                placeholder="Catatan pencairan (opsional)"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none focus:ring"
                                            ></textarea>
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">
                                                Tandai Sudah Dicairkan
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-xs text-slate-400">Tidak ada aksi</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
