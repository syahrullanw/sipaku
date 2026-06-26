<?php
    $definitions = is_array($datasetDefinitions ?? null) ? $datasetDefinitions : [];
    $counts = is_array($datasetCounts ?? null) ? $datasetCounts : [];
    $yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
    $selectedYearId = (int) ($selectedYearId ?? 0);
    $report = is_array($report ?? null) ? $report : null;
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $selectedYearLabel = $selectedYearId > 0 ? ($yearOptions[$selectedYearId] ?? '') : '';
    $hasSelectedYear = $selectedYearId > 0;
    $scopeLabels = [
        'year' => 'Per Tahun Ajaran',
        'global' => 'Global',
    ];
?>

<div class="grid gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Target Tahun Ajaran Persuratan</h2>
            <p class="mt-2 text-sm text-slate-600">
                Tentukan tahun ajaran yang akan dibersihkan. Seluruh surat masuk, surat keluar, dan antrian tanda tangan digital akan difilter berdasarkan pilihan ini.
            </p>

            <?php if (!empty($yearOptions)): ?>
                <form
                    action="<?= htmlspecialchars(base_url('admin/clean-data/persuratan'), ENT_QUOTES, 'UTF-8') ?>"
                    method="get"
                    class="mt-4 space-y-3"
                >
                    <label for="target_year" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Tahun Ajaran Target
                    </label>
                    <select
                        id="target_year"
                        name="target_year"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        onchange="this.form.submit();"
                    >
                        <?php foreach ($yearOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= $selectedYearId === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selectedYearLabel !== ''): ?>
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <p class="font-semibold text-slate-700">Tahun dipilih</p>
                        <p class="mt-1 text-xs"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Belum ada data tahun ajaran. Tambahkan tahun ajaran pada menu Master › Tahun Ajaran sebelum menjalankan pembersihan.
                </div>
            <?php endif; ?>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                <p class="font-semibold text-amber-800">Peringatan penting</p>
                <ul class="mt-2 space-y-1 list-disc pl-5">
                    <li>Lakukan backup database sebelum menghapus data persuratan.</li>
                    <li>Dataset hanya dihapus untuk tahun ajaran yang dipilih.</li>
                    <li>Tindakan ini permanen dan tidak dapat dibatalkan.</li>
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
                <?php if (!empty($report['target_year']['label'])): ?>
                    <p class="mt-1 text-xs text-slate-500">
                        Tahun ajaran: <?= htmlspecialchars((string) $report['target_year']['label'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
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
    </div>

    <div class="space-y-6 xl:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Pilih Data Persuratan yang Akan Dibersihkan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Centang dataset yang ingin dihapus, lalu masukkan kata <span class="font-semibold text-slate-700">BERSIHKAN</span> untuk mengonfirmasi.
                </p>
            </div>

            <form
                action="<?= htmlspecialchars(base_url('admin/clean-data/persuratan'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="divide-y divide-slate-100"
                data-clean-letters-form
            >
                <?= csrf_field() ?>
                <input type="hidden" name="target_year_id" value="<?= $selectedYearId > 0 ? (int) $selectedYearId : 0 ?>" />

                <div class="flex flex-wrap items-center gap-3 px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-[11px]">
                        <i class="ri-calendar-event-line text-base text-indigo-500"></i>
                        Target: <?= $selectedYearLabel !== '' ? htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') : 'Belum dipilih' ?>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-[11px]">
                        <i class="ri-database-2-line text-base text-emerald-500"></i>
                        Dataset: <?= count($definitions) ?>
                    </span>
                    <div class="ms-auto flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 disabled:opacity-50"
                            data-action="check-all"
                            <?= $hasSelectedYear ? '' : 'disabled' ?>
                        >
                            <i class="ri-checkbox-line text-sm"></i>
                            Pilih Semua
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-rose-400 hover:text-rose-600 disabled:opacity-50"
                            data-action="uncheck-all"
                            <?= $hasSelectedYear ? '' : 'disabled' ?>
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
                                <th class="px-6 py-3 w-12"><span class="sr-only">Pilih</span></th>
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
                                    $scopeLabel = $scopeLabels[$scope] ?? 'Per Tahun Ajaran';
                                ?>
                                <tr class="<?= $count > 0 ? 'hover:bg-slate-50' : '' ?>">
                                    <td class="px-6 py-4 align-top">
                                        <input
                                            type="checkbox"
                                            name="datasets[]"
                                            value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                            class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                            <?= $hasSelectedYear ? '' : 'disabled' ?>
                                        />
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-slate-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <?php if ($description !== ''): ?>
                                                <p class="text-xs text-slate-500"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
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
                                        Belum ada konfigurasi dataset untuk pembersihan persuratan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                            <?= $hasSelectedYear ? '' : 'disabled' ?>
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Penulisan harus sama persis: <span class="font-semibold text-slate-700">BERSIHKAN</span>.
                        </p>
                    </div>

                    <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <i class="ri-error-warning-line text-xl"></i>
                        <p>Pembersihan persuratan akan menghapus data permanen. Pastikan backup terbaru telah tersedia.</p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        <?= $hasSelectedYear ? '' : 'disabled' ?>
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
    var form = document.querySelector('[data-clean-letters-form]');
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

