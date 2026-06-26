<?php
/** @var array<int, array{approval: array<string, mixed>, entity: array<string, mixed>|null}> $items */

$typeLabel = static function (string $type): string {
    return match ($type) {
        'kasbon' => 'Kasbon Guru',
        'dana_kegiatan' => 'Dana Kegiatan',
        'honor' => 'Honor Guru',
        'tagihan' => 'Tagihan',
        'pembayaran' => 'Pembayaran',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
};

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>

<div class="space-y-6">
    <?php if (empty($items)): ?>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-8 text-center shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-lg font-semibold text-slate-900 dark:text-white">Tidak ada permohonan yang menunggu persetujuan.</p>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Semua pengajuan sudah diproses.</p>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php
            $approval = $item['approval'];
            $entity = $item['entity'] ?? [];
            $type = (string) ($approval['entity_type'] ?? '');
            $approvalId = (int) ($approval['id'] ?? 0);
            ?>
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($typeLabel($type), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Diajukan pada <?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($approval['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($entity)): ?>
                            <div class="mt-3 grid gap-3 text-sm md:grid-cols-2 lg:grid-cols-3">
                                <?php if (isset($entity['kode'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string) $entity['kode'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['judul'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string) $entity['judul'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['estimasi_biaya'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimasi</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) $entity['estimasi_biaya']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['nominal_diminta'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal Pengajuan</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) $entity['nominal_diminta']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['nominal_diterima'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Nominal Honor</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) $entity['nominal_diterima']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['guru_nama'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Guru</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string) $entity['guru_nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['tenor_bulan']) && $entity['tenor_bulan'] !== null): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tenor</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string) $entity['tenor_bulan'] . ' bulan', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['saldo_terhutang'])): ?>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Terhutang</p>
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) $entity['saldo_terhutang']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['tujuan']) && trim((string) $entity['tujuan']) !== ''): ?>
                                    <div class="md:col-span-2 lg:col-span-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Tujuan</p>
                                        <p class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200"><?= nl2br(htmlspecialchars((string) $entity['tujuan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($entity['catatan']) && trim((string) $entity['catatan']) !== ''): ?>
                                    <div class="md:col-span-2 lg:col-span-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</p>
                                        <p class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200"><?= nl2br(htmlspecialchars((string) $entity['catatan'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Detail entitas tidak ditemukan. Mohon konfirmasi ke bendahara.</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2">
                        <form action="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/approval/' . $approvalId . '/approve'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline-flex">
                            <?= csrf_field() ?>
                            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                                Setujui
                            </button>
                        </form>
                        <form action="<?= htmlspecialchars(base_url('keuangan/kepala-sekolah/approval/' . $approvalId . '/reject'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="inline-flex items-center gap-2">
                            <?= csrf_field() ?>
                            <input type="text" name="catatan" class="hidden md:block w-40 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" placeholder="Catatan penolakan (opsional)" />
                            <button type="submit" class="inline-flex items-center rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500/60 focus:ring-offset-1 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                                Tolak
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
