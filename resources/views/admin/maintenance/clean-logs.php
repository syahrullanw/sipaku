<?php
    $definitions = is_array($datasetDefinitions ?? null) ? $datasetDefinitions : [];
    $counts = is_array($datasetCounts ?? null) ? $datasetCounts : [];
    $report = is_array($report ?? null) ? $report : null;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
?>

<div class="grid gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-base font-semibold text-slate-800 dark:text-white">Pembersihan Log Aktivitas</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Gunakan panel ini untuk menghapus log aktivitas pengguna yang sudah tidak diperlukan,
                misalnya data lebih dari 3 bulan, catatan error, atau seluruh log audit.
            </p>
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-600 dark:bg-amber-500/10 dark:text-amber-200">
                <p class="font-semibold">Perhatian</p>
                <ul class="mt-2 list-disc space-y-1 pl-4">
                    <li>Lakukan backup database sebelum membersihkan log.</li>
                    <li>Tindakan ini permanen dan tidak dapat dibatalkan.</li>
                    <li>Log yang dihapus tidak akan tercatat pada audit berikutnya.</li>
                </ul>
            </div>
        </div>

        <?php if (!empty($summary)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-white">Ringkasan Terakhir</h3>
                    <?php if (!empty($report['timestamp'])): ?>
                        <span class="text-xs text-slate-400 dark:text-slate-500"><?= htmlspecialchars($report['timestamp'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-4 space-y-3">
                    <?php foreach ($summary as $item): ?>
                        <div class="rounded-xl border border-slate-100 px-4 py-3 dark:border-slate-700">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <?= htmlspecialchars($item['label'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <dl class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <div class="flex justify-between">
                                    <dt>Sebelum</dt>
                                    <dd><?= number_format((int) ($item['before'] ?? 0)) ?></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Dihapus</dt>
                                    <dd><?= number_format((int) ($item['deleted'] ?? 0)) ?></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Sisa</dt>
                                    <dd><?= number_format((int) ($item['remaining'] ?? 0)) ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="xl:col-span-8">
        <form
            action="<?= htmlspecialchars(base_url('admin/clean-data/log'), ENT_QUOTES, 'UTF-8') ?>"
            method="post"
            class="space-y-6"
        >
            <?= csrf_field() ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800 dark:text-white">Pilih Dataset</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Centang jenis log yang ingin dihapus.</p>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                        onclick="document.querySelectorAll('[data-log-dataset]').forEach(cb => cb.checked = true);"
                    >
                        Pilih Semua
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <?php foreach ($definitions as $key => $definition): ?>
                        <?php $count = $counts[$key] ?? 0; ?>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 px-4 py-3 hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-500/60">
                            <input
                                type="checkbox"
                                name="datasets[]"
                                value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                data-log-dataset
                            />
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    <?= htmlspecialchars($definition['label'] ?? ucfirst($key), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars($definition['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    Total kandidat: <?= number_format((int) $count) ?> log
                                </p>
                            </div>
                        </label>
                    <?php endforeach; ?>

                    <?php if (empty($definitions)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-300">Tidak ada definisi dataset.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 dark:border-rose-600 dark:bg-rose-500/10">
                <p class="text-sm font-semibold text-rose-700 dark:text-rose-200">Konfirmasi Penghapusan</p>
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-300">
                    Ketik <strong>BERSIHKAN</strong> untuk mengonfirmasi bahwa Anda setuju menghapus log yang dipilih.
                </p>
                <input
                    type="text"
                    name="confirmation"
                    class="mt-3 w-full rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/40 dark:border-rose-600 dark:bg-rose-500/10 dark:text-white"
                    placeholder="BERSIHKAN"
                    required
                />
                <div class="mt-4 flex items-center justify-end gap-3">
                    <a
                        href="<?= htmlspecialchars(base_url('admin/log-aktivitas'), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        Lihat Log
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-rose-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                    >
                        Hapus Log Terpilih
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

