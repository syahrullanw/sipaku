<?php
/** @var array<int, array<string, mixed>> $requests */
/** @var array<string, string> $statusLabels */
/** @var array<int, string> $statusTabs */
/** @var string $currentTab */
/** @var array<string, mixed>|null $activeYear */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$tabLabels = [
    'submitted' => 'Menunggu Persetujuan',
    'approved' => 'Telah Disetujui',
    'rejected' => 'Ditolak',
    'all' => 'Semua Pengajuan',
];
$statusClass = static function (string $status): string {
    return match ($status) {
        'submitted' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'funded' => 'bg-indigo-100 text-indigo-700',
        'reported' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-200 text-slate-700',
    };
};
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-800">Persetujuan Pengajuan Pengadaan Praktikum</h2>
            <p class="text-sm text-slate-500">
                Telaah permohonan dari kepala program studi sebelum diteruskan ke bendahara.
            </p>
            <?php if ($activeYear !== null): ?>
                <p class="mt-1 text-xs text-slate-400">Tahun ajaran aktif: <?= htmlspecialchars((string) ($activeYear['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 flex flex-wrap gap-2">
            <?php foreach ($statusTabs as $tab): ?>
                <?php $isActive = $currentTab === $tab; ?>
                <a
                    href="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/pengadaan?status=' . $tab), ENT_QUOTES, 'UTF-8') ?>"
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
                                        Kaprodi: <?= htmlspecialchars((string) ($row['guru_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        • Jurusan: <?= htmlspecialchars((string) ($row['jurusan_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        • Kode: <?= htmlspecialchars((string) ($row['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <?php if (!empty($row['tujuan'])): ?>
                                        <p class="mt-1 text-xs text-slate-500"><?= nl2br(htmlspecialchars((string) $row['tujuan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['rincian_kebutuhan'])): ?>
                                        <p class="mt-1 text-xs text-slate-500">Rincian: <?= nl2br(htmlspecialchars((string) $row['rincian_kebutuhan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($row['review_note']) && $status !== 'submitted'): ?>
                                        <p class="mt-1 text-xs text-slate-500">Catatan Anda: <?= htmlspecialchars((string) $row['review_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= $formatCurrency((float) ($row['total_estimasi'] ?? 0)) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass($status) ?>">
                                        <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($status === 'submitted'): ?>
                                        <div class="space-y-3">
                                            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/pengadaan/' . (int) ($row['id'] ?? 0) . '/approve'), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrf_field() ?>
                                                <textarea
                                                    name="catatan"
                                                    rows="2"
                                                    placeholder="Catatan (opsional)"
                                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-emerald-500 focus:outline-none focus:ring"
                                                ></textarea>
                                                <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500">
                                                    Setujui & Teruskan
                                                </button>
                                            </form>
                                            <form method="post" action="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/pengadaan/' . (int) ($row['id'] ?? 0) . '/reject'), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrf_field() ?>
                                                <textarea
                                                    name="catatan"
                                                    rows="2"
                                                    placeholder="Catatan penolakan"
                                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-rose-500 focus:outline-none focus:ring"
                                                    required
                                                ></textarea>
                                                <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                    Tolak Pengajuan
                                                </button>
                                            </form>
                                        </div>
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
