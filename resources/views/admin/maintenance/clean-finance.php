<?php
    $definitions = $datasetDefinitions ?? [];
    $counts = $datasetCounts ?? [];
    $report = is_array($report ?? null) ? $report : null;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $cancelSummary = is_array($report['cancel_summary'] ?? null) ? $report['cancel_summary'] : [];
    $datasetCount = count($definitions);
    $hasActiveYear = $activeYearId > 0;
    $activeYearId = (int) ($activeYear['id'] ?? 0);
    $activeSemester = (int) ($activeYear['semester_aktif'] ?? 1);
    $activeYearLabel = null;

    if ($activeYearId > 0) {
        $activeYearLabel = sprintf(
            '%s - %s',
            $activeYear['nama'] ?? 'Tahun Ajaran',
            $activeSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }
?>

<div class="grid gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Tahun Ajaran Aktif</h2>
            <?php if ($activeYearLabel !== null): ?>
                <p class="mt-2 text-sm text-slate-600">
                    Pembersihan data finansial akan difokuskan pada tahun ajaran berikut, kecuali data bersifat global.
                </p>
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="font-semibold text-slate-700"><?= htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($activeYear['tanggal_mulai']) && !empty($activeYear['tanggal_selesai'])): ?>
                        <p class="mt-1 text-xs">
                            Periode <?= htmlspecialchars($activeYear['tanggal_mulai'], ENT_QUOTES, 'UTF-8') ?> —
                            <?= htmlspecialchars($activeYear['tanggal_selesai'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Belum ada tahun ajaran yang berstatus aktif. Aktifkan tahun ajaran terlebih dahulu sebelum menjalankan pembersihan data keuangan.
                </div>
            <?php endif; ?>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                <p class="font-semibold text-amber-800">Peringatan</p>
                <ul class="mt-2 space-y-1 list-disc pl-5">
                    <li>Lakukan backup database sebelum menghapus data keuangan.</li>
                    <li>Data bertanda <span class="font-semibold text-rose-600">Global</span> akan dihapus untuk semua tahun.</li>
                    <li>Pembersihan tidak dapat dibatalkan. Pastikan pilihan Anda sesuai kebutuhan.</li>
                </ul>
            </div>
        </div>

        <?php if (!empty($summary)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-800">Ringkasan Pembersihan Terakhir</h2>
                    <?php if (!empty($report['timestamp'])): ?>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($report['timestamp'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-4 space-y-3">
                    <?php foreach ($summary as $key => $item): ?>
                        <div class="rounded-xl border border-slate-100 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">
                                <?= htmlspecialchars($item['label'] ?? $key, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <dl class="mt-2 grid grid-cols-3 gap-2 text-xs text-slate-500">
                                <div>
                                    <dt class="uppercase tracking-wide">Sebelum</dt>
                                    <dd class="mt-1 text-base font-semibold text-slate-700">
                                        <?= number_format((int) ($item['before'] ?? 0)) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Terhapus</dt>
                                    <dd class="mt-1 text-base font-semibold text-rose-600">
                                        <?= number_format((int) ($item['deleted'] ?? 0)) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Sisa</dt>
                                    <dd class="mt-1 text-base font-semibold text-emerald-600">
                                        <?= number_format((int) ($item['remaining'] ?? 0)) ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($cancelSummary)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-800">Tagihan yang Dibatalkan</h2>
                    <span class="text-xs text-slate-400"><?= number_format(count($cancelSummary)) ?> entri</span>
                </div>
                <div class="mt-4 space-y-3">
                    <?php foreach ($cancelSummary as $item): ?>
                        <?php
                            $code = (string) ($item['billing_code'] ?? '');
                            $title = (string) ($item['billing_title'] ?? '');
                            $items = (int) ($item['items'] ?? 0);
                            $installments = (int) ($item['installments'] ?? 0);
                            $kasRows = (int) ($item['kas_rows'] ?? 0);
                            $statusChanged = (bool) ($item['status_changed'] ?? false);
                            $previousStatus = (string) ($item['previous_status'] ?? '');
                        ?>
                        <div class="rounded-xl border border-slate-100 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">
                                <?= htmlspecialchars($code !== '' ? $code : ('Tagihan #' . ($item['billing_id'] ?? '?')), ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($statusChanged): ?>
                                    <span class="ml-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                        Dibatalkan
                                    </span>
                                <?php else: ?>
                                    <span class="ml-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-700">
                                        Sudah dibatalkan
                                    </span>
                                <?php endif; ?>
                            </p>
                            <?php if ($title !== ''): ?>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($previousStatus !== '' && !$statusChanged): ?>
                                <p class="text-xs text-rose-500">Status sebelumnya: <?= htmlspecialchars($previousStatus, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-slate-500">
                                <div>
                                    <dt class="uppercase tracking-wide">Item</dt>
                                    <dd class="mt-1 text-base font-semibold text-slate-700"><?= number_format($items) ?></dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Cicilan</dt>
                                    <dd class="mt-1 text-base font-semibold text-slate-700"><?= number_format($installments) ?></dd>
                                </div>
                                <div>
                                    <dt class="uppercase tracking-wide">Kas</dt>
                                    <dd class="mt-1 text-base font-semibold text-slate-700"><?= number_format($kasRows) ?></dd>
                                </div>
                            </dl>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="space-y-6 xl:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Pilih Data Keuangan yang Akan Dibersihkan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Centang jenis data yang ingin dihapus, kemudian ketik <span class="font-semibold text-slate-700">BERSIHKAN</span> untuk mengonfirmasi.
                </p>
            </div>

            <form
                action="<?= htmlspecialchars(base_url('admin/clean-data/keuangan'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="divide-y divide-slate-100"
                data-clean-finance-form
            >
                <?= csrf_field() ?>
                <div class="flex flex-wrap items-center gap-3 px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-[11px]">
                        <i class="ri-database-2-line text-base text-indigo-500"></i>
                        Dataset: <?= $datasetCount ?>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-[11px]">
                        <i class="ri-calendar-event-line text-base text-emerald-500"></i>
                        Tahun Aktif: <?= $activeYearLabel !== null ? htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') : 'Belum dipilih' ?>
                    </span>
                    <div class="ms-auto flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 disabled:opacity-50"
                            data-action="check-all"
                            <?= $hasActiveYear ? '' : 'disabled' ?>
                        >
                            <i class="ri-checkbox-line text-sm"></i>
                            Pilih Semua
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-rose-400 hover:text-rose-600 disabled:opacity-50"
                            data-action="uncheck-all"
                            <?= $hasActiveYear ? '' : 'disabled' ?>
                        >
                            <i class="ri-close-line text-sm"></i>
                            Kosongkan
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3 w-12">
                                    <span class="sr-only">Pilih</span>
                                </th>
                                <th class="px-6 py-3">Jenis Data</th>
                                <th class="px-6 py-3 text-right">Jumlah Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($definitions as $key => $definition): ?>
                                <?php
                                    $count = (int) ($counts[$key] ?? 0);
                                    $label = $definition['label'] ?? $key;
                                    $description = $definition['description'] ?? '';
                                    $scope = $definition['scope'] ?? 'year';
                                    $isGlobal = $scope === 'global';
                                ?>
                                <tr class="<?= $count > 0 ? 'hover:bg-slate-50' : '' ?>">
                                    <td class="px-6 py-4 align-top">
                                        <input
                                            type="checkbox"
                                            name="datasets[]"
                                            value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                            class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                            <?= $activeYearId > 0 ? '' : 'disabled' ?>
                                        />
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-slate-700">
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($isGlobal): ?>
                                                <span class="ml-2 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-rose-700">
                                                    Global
                                                </span>
                                            <?php else: ?>
                                                <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                    Per Tahun Ajaran
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($description !== ''): ?>
                                            <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="inline-flex items-center justify-end gap-2 text-sm font-semibold <?= $count > 0 ? 'text-slate-700' : 'text-slate-400' ?>">
                                            <i class="ri-database-2-line text-base <?= $count > 0 ? 'text-indigo-500' : 'text-slate-300' ?>"></i>
                                            <?= number_format($count) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($definitions)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-5 text-center text-sm text-slate-400">
                                        Belum ada konfigurasi dataset untuk pembersihan keuangan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-5 space-y-2">
                    <label for="target_billings" class="block text-sm font-medium text-slate-600">
                        Batalkan Tagihan Khusus
                    </label>
                    <textarea
                        id="target_billings"
                        name="target_billings"
                        rows="3"
                        placeholder="Masukkan kode/ID tagihan yang ingin dibatalkan, pisahkan dengan koma atau baris baru"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/40"
                        <?= $activeYearId > 0 ? '' : 'disabled' ?>
                    ></textarea>
                    <p class="text-xs text-slate-500">
                        Contoh: <span class="font-semibold text-slate-700">BIL/2025/001, BIL/2025/002, 48</span>
                    </p>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-slate-600">
                            Konfirmasi
                        </label>
                        <input
                            type="text"
                            id="confirmation"
                            name="confirmation"
                            placeholder="Ketik BERSIHKAN"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/40"
                            <?= $activeYearId > 0 ? '' : 'disabled' ?>
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Penulisan harus sama persis: <span class="font-semibold text-slate-700">BERSIHKAN</span>.
                        </p>
                    </div>

                    <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <i class="ri-error-warning-line text-xl"></i>
                        <p>Langkah ini bersifat permanen dan akan menghapus data keuangan sesuai pilihan Anda.</p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        <?= $activeYearId > 0 ? '' : 'disabled' ?>
                    >
                        <i class="ri-delete-bin-6-line text-lg"></i>
                        Bersihkan Data Terpilih
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-clean-finance-form]');
    if (!form) {
        return;
    }

    var getCheckboxes = function () {
        return Array.prototype.slice.call(form.querySelectorAll('input[name="datasets[]"]:not([disabled])'));
    };

    var toggle = function (checked) {
        getCheckboxes().forEach(function (input) {
            input.checked = checked;
        });
    };

    var checkAllButton = form.querySelector('[data-action="check-all"]');
    var uncheckAllButton = form.querySelector('[data-action="uncheck-all"]');

    if (checkAllButton) {
        checkAllButton.addEventListener('click', function () {
            toggle(true);
        });
    }

    if (uncheckAllButton) {
        uncheckAllButton.addEventListener('click', function () {
            toggle(false);
        });
    }
});
</script>
