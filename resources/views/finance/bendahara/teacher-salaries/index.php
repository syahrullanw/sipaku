<?php
/** @var bool $hasActiveYear */
/** @var string $section */
/** @var string $period */
/** @var array<int, string> $availablePeriods */
/** @var array<int, array<string, mixed>> $teacherOptions */
/** @var array<int, array<string, mixed>> $recordSummaries */
/** @var array<string, string> $componentTypeLabels */
/** @var array<string, mixed>|null $selectedTeacher */
/** @var array<string, mixed>|null $salaryForm */
/** @var array<string, mixed> $settingsContext */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$formatNumber = static fn (?float $value): string => $value !== null && $value > 0 ? number_format($value, 2, ',', '.') : '';
$formatInt = static fn (float $value): string => $value > 0 ? number_format($value, 0, ',', '.') : '0';
$nowMonth = date('Y-m');
?>

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Input Gaji Guru</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Catat jam mengajar, validasi honor jabatan/aktivitas, dan cairkan gaji guru lengkap dengan slip.
            </p>
        </div>
        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara'), ENT_QUOTES, 'UTF-8') ?>"
           class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            &larr; Kembali ke Dashboard
        </a>
    </div>

    <?php if (!$hasActiveYear): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">Tahun ajaran aktif belum ditetapkan.</p>
            <p class="mt-1">Tetapkan tahun ajaran aktif terlebih dahulu agar penggajian guru dapat dicatat.</p>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-3 rounded-lg border border-slate-200/70 bg-white p-1 text-sm shadow-sm dark:border-slate-700/70 dark:bg-slate-900/60 dark:text-slate-200">
        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=payroll'), ENT_QUOTES, 'UTF-8') ?>"
           class="flex-1 rounded-md px-3 py-2 text-center font-medium <?= $section === 'payroll' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">
            Penggajian
        </a>
        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=settings'), ENT_QUOTES, 'UTF-8') ?>"
           class="flex-1 rounded-md px-3 py-2 text-center font-medium <?= $section === 'settings' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">
            Pengaturan Komponen
        </a>
    </div>

    <?php if ($section === 'payroll'): ?>
        <div class="space-y-6">
            <div class="rounded-xl border border-slate-200/70 bg-white/80 p-6 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <form method="get" class="grid gap-4 md:grid-cols-12">
                    <input type="hidden" name="section" value="payroll">
                    <div class="md:col-span-4">
                        <label for="periode" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Periode Gaji</label>
                        <input
                            type="month"
                            id="periode"
                            name="periode"
                            value="<?= htmlspecialchars($period !== '' ? $period : $nowMonth, ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                    </div>
                    <div class="md:col-span-5">
                        <label for="teacher-id" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Pilih Guru</label>
                        <select
                            id="teacher-id"
                            name="teacher_id"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            <option value="">-- Pilih guru --</option>
                            <?php foreach ($teacherOptions as $option): ?>
                                <?php $value = (int) ($option['id'] ?? 0); ?>
                                <option value="<?= $value ?>" <?= $selectedTeacher !== null && $selectedTeacher['id'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($option['name'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?><?= ($option['status'] ?? 'aktif') !== 'aktif' ? ' (Nonaktif)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex items-end gap-3">
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Tampilkan
                        </button>
                        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=payroll'), ENT_QUOTES, 'UTF-8') ?>"
                           class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                            Reset
                        </a>
                    </div>
                </form>

                <?php if (!empty($availablePeriods)): ?>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold uppercase tracking-wide">Riwayat Periode:</span>
                        <?php foreach ($availablePeriods as $availablePeriod): ?>
                            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=payroll&periode=' . urlencode($availablePeriod)), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-full border border-slate-200 px-3 py-1 <?=
                                    $availablePeriod === $period
                                        ? 'bg-sky-600 text-white dark:border-sky-600'
                                        : 'text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                               ?>">
                                <?= htmlspecialchars($availablePeriod, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/70 px-6 py-4 dark:border-slate-700/60">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Rekap Penggajian Periode <?= htmlspecialchars($period !== '' ? $period : $nowMonth, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <div class="overflow-x-auto px-6 py-4">
                    <?php if (empty($recordSummaries)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada data penggajian untuk periode ini.</p>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="py-3 pr-4 font-semibold">Guru</th>
                                    <th class="py-3 pr-4 text-right font-semibold">Jam Mengajar</th>
                                    <th class="py-3 pr-4 text-right font-semibold">Total Bruto</th>
                                    <th class="py-3 pr-4 text-right font-semibold">Total Potongan</th>
                                    <th class="py-3 pr-4 text-right font-semibold">Total Diterima</th>
                                    <th class="py-3 pr-4 font-semibold">Status</th>
                                    <th class="py-3 pr-0 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                                <?php foreach ($recordSummaries as $summary): ?>
                                    <?php $summaryId = (int) ($summary['id'] ?? 0); ?>
                                    <tr>
                                        <td class="py-3 pr-4 align-top">
                                            <p class="font-semibold text-slate-800 dark:text-white">
                                                <?= htmlspecialchars((string) ($summary['guru_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($summary['guru_nip'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                        </td>
                                        <td class="py-3 pr-4 text-right font-mono text-sm"><?= $formatNumber((float) ($summary['teaching_hours'] ?? 0.0)) ?></td>
                                        <td class="py-3 pr-4 text-right font-mono text-sm"><?= $formatCurrency((float) ($summary['total_bruto'] ?? 0.0)) ?></td>
                                        <td class="py-3 pr-4 text-right font-mono text-sm text-rose-500"><?= $formatCurrency((float) ($summary['total_deduction'] ?? 0.0)) ?></td>
                                        <td class="py-3 pr-4 text-right font-mono text-sm text-emerald-600"><?= $formatCurrency((float) ($summary['total_net'] ?? 0.0)) ?></td>
                                        <td class="py-3 pr-4">
                                            <?php
                                            $status = (string) ($summary['status'] ?? 'draft');
                                            $statusMap = [
                                                'draft' => ['label' => 'Draf', 'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300'],
                                                'validated' => ['label' => 'Siap Cair', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200'],
                                                'disbursed' => ['label' => 'Cair', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'],
                                            ];
                                            $statusInfo = $statusMap[$status] ?? $statusMap['draft'];
                                            ?>
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $statusInfo['class'] ?>">
                                                <?= $statusInfo['label'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 pr-0 text-right">
                                            <div class="flex justify-end gap-2">
                                                <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=payroll&periode=' . urlencode((string) ($summary['periode'] ?? '')) . '&teacher_id=' . (int) ($summary['guru_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
                                                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                                    Buka
                                                </a>
                                                <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian/slip/' . $summaryId), ENT_QUOTES, 'UTF-8') ?>"
                                                   class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                                   target="_blank" rel="noopener">
                                                    Slip
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="border-b border-slate-200/70 px-6 py-4 dark:border-slate-700/60">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Form Penggajian</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Isi jumlah jam mengajar, cek komponen honor, lalu simpan draf sebelum validasi dan pencairan.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <?php if ($selectedTeacher === null || $period === ''): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Pilih periode dan guru untuk mulai mencatat penggajian.</p>
                    <?php elseif ($salaryForm === null): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Tidak dapat menyiapkan form penggajian. Pastikan konfigurasi komponen gaji sudah lengkap.</p>
                    <?php else: ?>
                        <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            <div class="flex flex-wrap justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Guru</p>
                                    <p class="text-base font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars((string) ($selectedTeacher['name'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Periode</p>
                                    <p class="text-base font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($salaryForm['period'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Status</p>
                                    <?php
                                    $status = (string) ($salaryForm['status'] ?? 'draft');
                                    $statusMapView = [
                                        'draft' => ['label' => 'Draf', 'class' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'],
                                        'validated' => ['label' => 'Siap Cair', 'class' => 'bg-amber-200 text-amber-700 dark:bg-amber-500/30 dark:text-amber-100'],
                                        'disbursed' => ['label' => 'Sudah Cair', 'class' => 'bg-emerald-200 text-emerald-700 dark:bg-emerald-500/30 dark:text-emerald-100'],
                                    ];
                                    $badge = $statusMapView[$status] ?? $statusMapView['draft'];
                                    ?>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badge['class'] ?>">
                                        <?= $badge['label'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-6" data-salary-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="periode" value="<?= htmlspecialchars($salaryForm['period'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="teacher_id" value="<?= (int) ($salaryForm['teacher']['id'] ?? $selectedTeacher['id']) ?>">
                            <?php if ($salaryForm['recordId'] !== null): ?>
                                <input type="hidden" name="record_id" value="<?= (int) $salaryForm['recordId'] ?>">
                            <?php endif; ?>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="teaching-hours" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Total Jam Mengajar Bulan Ini</label>
                                    <input
                                        type="text"
                                        id="teaching-hours"
                                        name="teaching_hours"
                                        value="<?= htmlspecialchars($formatNumber((float) $salaryForm['teachingHours']), ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Contoh: 72"
                                        inputmode="decimal"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                    >
                                </div>
                                <div>
                                    <label for="hourly-rate" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tarif Honor Per Jam</label>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                                        <input
                                            type="text"
                                            id="hourly-rate"
                                            name="hourly_rate"
                                            value="<?= htmlspecialchars($formatNumber((float) $salaryForm['hourlyRate']), ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Contoh: 75000"
                                            inputmode="decimal"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700" data-salary-components>
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                        <tr>
                                            <th class="px-4 py-3 font-semibold">Gunakan</th>
                                            <th class="px-4 py-3 font-semibold">Komponen</th>
                                            <th class="px-4 py-3 text-right font-semibold">Jumlah</th>
                                            <th class="px-4 py-3 text-right font-semibold">Tarif</th>
                                            <th class="px-4 py-3 text-right font-semibold">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <?php $index = 0; ?>
                                        <?php foreach ($salaryForm['components'] as $component): ?>
                                            <?php
                                            $componentId = (int) ($component['id'] ?? 0);
                                            $type = (string) ($component['type'] ?? 'adjustment');
                                            $label = (string) ($component['label'] ?? '');
                                            $quantity = $component['quantity'] ?? null;
                                            $rate = $component['rate'] ?? null;
                                            $amount = (float) ($component['amount'] ?? 0.0);
                                            $checked = !isset($component['include']) || $component['include'] === true;
                                            ?>
                                            <tr class="align-top">
                                                <td class="px-4 py-3">
                                                    <input type="hidden" name="components[<?= $index ?>][include]" value="0">
                                                    <input
                                                        type="checkbox"
                                                        name="components[<?= $index ?>][include]"
                                                        value="1"
                                                        class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                        <?= $checked ? 'checked' : '' ?>
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                    <input type="hidden" name="components[<?= $index ?>][type]" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="components[<?= $index ?>][code]" value="<?= htmlspecialchars((string) ($component['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php if ($componentId > 0): ?>
                                                        <input type="hidden" name="components[<?= $index ?>][id]" value="<?= $componentId ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php $metadataValue = is_string($component['metadata'] ?? null) ? (string) $component['metadata'] : ''; ?>
                                                    <input type="hidden" name="components[<?= $index ?>][metadata]" value="<?= htmlspecialchars($metadataValue, ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="space-y-1">
                                                        <input
                                                            type="text"
                                                            name="components[<?= $index ?>][label]"
                                                            value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                        >
                                                        <p class="text-xs text-slate-400">
                                                            <?= htmlspecialchars($componentTypeLabels[$type] ?? ucfirst($type), ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                        <?php $componentHint = trim((string) ($component['hint'] ?? '')); ?>
                                                        <?php if ($componentHint !== ''): ?>
                                                            <p class="text-xs italic text-slate-400"><?= htmlspecialchars($componentHint, ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <input
                                                        type="text"
                                                        name="components[<?= $index ?>][quantity]"
                                                        value="<?= htmlspecialchars($quantity !== null ? $formatNumber((float) $quantity) : '', ENT_QUOTES, 'UTF-8') ?>"
                                                        placeholder="-"
                                                        inputmode="decimal"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <input
                                                        type="text"
                                                        name="components[<?= $index ?>][rate]"
                                                        value="<?= htmlspecialchars($rate !== null ? $formatNumber((float) $rate) : '', ENT_QUOTES, 'UTF-8') ?>"
                                                        placeholder="0"
                                                        inputmode="decimal"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <span class="text-xs text-slate-400">Rp</span>
                                                        <input
                                                            type="text"
                                                            name="components[<?= $index ?>][amount]"
                                                            value="<?= htmlspecialchars($formatNumber($amount), ENT_QUOTES, 'UTF-8') ?>"
                                                            placeholder="0"
                                                            inputmode="decimal"
                                                            class="w-40 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                        >
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $index++; ?>
                                        <?php endforeach; ?>

                                        <?php for ($i = 0; $i < 3; $i++): ?>
                                            <tr class="align-top">
                                                <td class="px-4 py-3">
                                                    <input type="hidden" name="components[<?= $index ?>][include]" value="0">
                                                    <input type="hidden" name="components[<?= $index ?>][metadata]" value="">
                                                    <input
                                                        type="checkbox"
                                                        name="components[<?= $index ?>][include]"
                                                        value="1"
                                                        class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                    <select name="components[<?= $index ?>][type]" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" <?= !$hasActiveYear ? 'disabled' : '' ?>>
                                                        <option value="activity">Honor Kegiatan</option>
                                                        <option value="adjustment">Penyesuaian (+)</option>
                                                        <option value="deduction">Potongan (-)</option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="text"
                                                        name="components[<?= $index ?>][label]"
                                                        placeholder="Nama komponen"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                    <input type="hidden" name="components[<?= $index ?>][code]" value="">
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <input
                                                        type="text"
                                                        name="components[<?= $index ?>][quantity]"
                                                        placeholder="-"
                                                        inputmode="decimal"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <input
                                                        type="text"
                                                        name="components[<?= $index ?>][rate]"
                                                        placeholder="0"
                                                        inputmode="decimal"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <span class="text-xs text-slate-400">Rp</span>
                                                        <input
                                                            type="text"
                                                            name="components[<?= $index ?>][amount]"
                                                            placeholder="0"
                                                            inputmode="decimal"
                                                            class="w-40 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-right text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                        >
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $index++; ?>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Ringkasan Komponen</h3>
                                    <dl class="mt-3 space-y-2">
                                        <div class="flex justify-between">
                                            <dt>Honor Mengajar</dt>
                                            <dd class="font-medium" data-salary-summary="total_teaching"><?= $formatCurrency((float) $salaryForm['totals']['total_teaching']) ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Tugas Khusus</dt>
                                            <dd class="font-medium" data-salary-summary="total_special"><?= $formatCurrency((float) $salaryForm['totals']['total_special']) ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Jabatan Akademik</dt>
                                            <dd class="font-medium" data-salary-summary="total_academic"><?= $formatCurrency((float) $salaryForm['totals']['total_academic']) ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Honor Kegiatan</dt>
                                            <dd class="font-medium" data-salary-summary="total_activity"><?= $formatCurrency((float) $salaryForm['totals']['total_activity']) ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt>Penyesuaian (+)</dt>
                                            <dd class="font-medium" data-salary-summary="total_adjustment"><?= $formatCurrency((float) $salaryForm['totals']['total_adjustment']) ?></dd>
                                        </div>
                                        <div class="flex justify-between text-rose-600">
                                            <dt>Potongan</dt>
                                            <dd class="font-medium" data-salary-summary="total_deduction">- <?= $formatCurrency((float) $salaryForm['totals']['total_deduction']) ?></dd>
                                        </div>
                                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-800 dark:border-slate-700 dark:text-emerald-200">
                                            <dt>Total Diterima</dt>
                                            <dd data-salary-summary="total_net"><?= $formatCurrency((float) $salaryForm['totals']['total_net']) ?></dd>
                                        </div>
                                    </dl>
                                </div>
                                <div>
                                    <label for="note" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan Bendahara</label>
                                    <textarea
                                        id="note"
                                        name="note"
                                        rows="5"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        placeholder="Tambahkan catatan terkait perubahan komponen atau validasi gaji"
                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                    ><?= htmlspecialchars((string) $salaryForm['note'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex gap-2">
                                    <?php if ($salaryForm['recordId'] !== null): ?>
                                        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian/slip/' . (int) $salaryForm['recordId']), ENT_QUOTES, 'UTF-8') ?>"
                                           target="_blank"
                                           rel="noopener"
                                           class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                                            Cetak Slip
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                    >
                                        Simpan Draf
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($salaryForm['recordId'] !== null): ?>
                            <div class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Validasi &amp; Pencairan</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Setelah gaji tervalidasi, lanjutkan pencairan untuk menandai honor sebagai terbayar dan menghasilkan slip final.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian/' . (int) $salaryForm['recordId'] . '/status'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="validate">
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-1 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200"
                                                <?= $salaryForm['status'] === 'disbursed' ? 'disabled' : '' ?>>
                                            Tandai Siap Cair
                                        </button>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian/' . (int) $salaryForm['recordId'] . '/status'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="revert">
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
                                                <?= $salaryForm['status'] === 'disbursed' ? 'disabled' : '' ?>>
                                            Kembalikan ke Draf
                                        </button>
                                    </form>
                                    <form method="post" action="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/penggajian/' . (int) $salaryForm['recordId'] . '/status'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="disburse">
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900"
                                                <?= !$hasActiveYear || $salaryForm['status'] === 'disbursed' ? 'disabled' : '' ?>>
                                            Cairkan Gaji
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php
        $hourlyRateSetting = (float) ($settingsContext['hourlyRate'] ?? 0.0);
        $specialRolesSettings = $settingsContext['specialRoles'] ?? [];
        $academicSettings = $settingsContext['academicPositions'] ?? [];
        $activitySettings = $settingsContext['activities'] ?? [];
        ?>
        <div class="rounded-xl border border-slate-200/70 bg-white/80 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru/pengaturan'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="divide-y divide-slate-200 dark:divide-slate-700">
                <?= csrf_field() ?>

                <section class="px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Honor Mengajar</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Tentukan tarif honor setiap jam pelajaran yang menjadi dasar perhitungan gaji guru.
                    </p>
                    <div class="mt-4 max-w-md">
                        <label for="hourly-rate-setting" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tarif per jam pelajaran</label>
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                            <input
                                type="text"
                                id="hourly-rate-setting"
                                name="hourly_rate"
                                value="<?= htmlspecialchars($formatNumber($hourlyRateSetting), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Contoh: 75000"
                                inputmode="decimal"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                <?= !$hasActiveYear ? 'disabled' : '' ?>
                            >
                        </div>
                    </div>
                </section>

                <section class="px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Honor Tugas Khusus &amp; Jabatan</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Sesuaikan nominal honor untuk tugas tambahan maupun jabatan akademik guru.
                    </p>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <?php foreach ($specialRolesSettings as $role): ?>
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars((string) ($role['label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($role['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Honor per penugasan</label>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                                    <input
                                        type="text"
                                        name="special[<?= htmlspecialchars((string) ($role['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>]"
                                        value="<?= htmlspecialchars($formatNumber((float) ($role['amount'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="0"
                                        inputmode="decimal"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                    >
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Jabatan Akademik</th>
                                    <th class="px-5 py-3 font-semibold text-right">Honor per guru</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php if (empty($academicSettings)): ?>
                                    <tr>
                                        <td colspan="2" class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">Belum ada data jabatan akademik untuk guru.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($academicSettings as $position): ?>
                                        <tr>
                                            <td class="px-5 py-3 text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string) ($position['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="inline-flex min-w-[180px] items-center gap-2">
                                                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                                                    <input
                                                        type="text"
                                                        name="positions[<?= (int) ($position['id'] ?? 0) ?>]"
                                                        value="<?= htmlspecialchars($formatNumber((float) ($position['amount'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?>"
                                                        placeholder="0"
                                                        inputmode="decimal"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                                                    >
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Honor Kegiatan Sekolah</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Tambahkan daftar honor untuk kegiatan khusus atau insidental yang melibatkan guru. Honor dapat diatur berbeda untuk setiap kegiatan.
                    </p>

                    <div class="mt-4 space-y-4">
                        <?php foreach ($activitySettings as $activity): ?>
                            <?php $activityId = (int) ($activity['id'] ?? 0); ?>
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <div class="grid gap-3 md:grid-cols-12 md:items-center">
                                    <div class="md:col-span-5">
                                        <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="activity-name-<?= $activityId ?>">Nama kegiatan</label>
                                        <input
                                            type="text"
                                            id="activity-name-<?= $activityId ?>"
                                            name="activities_existing[<?= $activityId ?>][name]"
                                            value="<?= htmlspecialchars((string) ($activity['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Misal: Kegiatan MPLS"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                        >
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="activity-amount-<?= $activityId ?>">Honor untuk guru</label>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                                            <input
                                                type="text"
                                                id="activity-amount-<?= $activityId ?>"
                                                name="activities_existing[<?= $activityId ?>][amount]"
                                                value="<?= htmlspecialchars($formatNumber((float) ($activity['amount'] ?? 0.0)), ENT_QUOTES, 'UTF-8') ?>"
                                                placeholder="0"
                                                inputmode="decimal"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                <?= !$hasActiveYear ? 'disabled' : '' ?>
                                            >
                                        </div>
                                    </div>
                                    <div class="md:col-span-3 md:mt-6">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                            <input
                                                type="checkbox"
                                                name="activities_existing[<?= $activityId ?>][delete]"
                                                value="1"
                                                class="h-4 w-4 rounded border-slate-300 text-rose-500 focus:ring-rose-400"
                                                <?= !$hasActiveYear ? 'disabled' : '' ?>
                                            >
                                            Hapus kegiatan
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="rounded-lg border border-dashed border-slate-200 px-4 py-4 dark:border-slate-700">
                                <div class="grid gap-3 md:grid-cols-12 md:items-center">
                                    <div class="md:col-span-5">
                                        <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="new-activity-name-<?= $i ?>">Nama kegiatan baru</label>
                                        <input
                                            type="text"
                                            id="new-activity-name-<?= $i ?>"
                                            name="activities_new[<?= $i ?>][name]"
                                            placeholder="Tambah kegiatan"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            <?= !$hasActiveYear ? 'disabled' : '' ?>
                                        >
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200" for="new-activity-amount-<?= $i ?>">Honor guru</label>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Rp</span>
                                            <input
                                                type="text"
                                                id="new-activity-amount-<?= $i ?>"
                                                name="activities_new[<?= $i ?>][amount]"
                                                placeholder="0"
                                                inputmode="decimal"
                                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                                <?= !$hasActiveYear ? 'disabled' : '' ?>
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3 px-6 py-5">
                    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/gaji-guru?section=settings'), ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                        Reset
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                        <?= !$hasActiveYear ? 'disabled' : '' ?>
                    >
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($section === 'payroll'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-salary-form]');
    const componentsTable = form ? form.querySelector('[data-salary-components]') : null;
    if (!form || !componentsTable) {
        return;
    }

    const summaryNodes = {};
    document.querySelectorAll('[data-salary-summary]').forEach((node) => {
        const key = node.getAttribute('data-salary-summary');
        if (key) {
            summaryNodes[key] = node;
        }
    });

    const currencyFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const formatNumberForInput = (value) => {
        if (!Number.isFinite(value) || value <= 0) {
            return '';
        }
        return currencyFormatter.format(Math.round(value));
    };

    const parseNumber = (raw) => {
        if (!raw) {
            return 0;
        }

        let value = raw.replace(/[^\d,.\-]/g, '');
        if (!value) {
            return 0;
        }

        const lastComma = value.lastIndexOf(',');
        const lastDot = value.lastIndexOf('.');

        if (lastComma !== -1 && lastDot !== -1) {
            if (lastComma > lastDot) {
                value = value.replace(/\./g, '').replace(',', '.');
            } else {
                value = value.replace(/,/g, '');
            }
        } else if (lastComma !== -1) {
            const fractionLength = value.length - lastComma - 1;
            if (fractionLength > 0 && fractionLength <= 2) {
                value = value.replace(/\./g, '').replace(',', '.');
            } else {
                value = value.replace(/,/g, '');
            }
        } else if (lastDot !== -1) {
            const fractionLength = value.length - lastDot - 1;
            if (fractionLength > 0 && fractionLength <= 2) {
                value = value.replace(/,/g, '');
            } else {
                value = value.replace(/\./g, '');
            }
        } else {
            value = value.replace(/[.,]/g, '');
        }

        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const round = (amount) => Math.round((amount + Number.EPSILON) * 100) / 100;
    const formatCurrency = (amount, withSign = false) => {
        const rounded = Math.round(amount);
        const formatted = 'Rp ' + currencyFormatter.format(Math.abs(rounded));
        if (!withSign) {
            return formatted;
        }
        return rounded < 0 ? '- ' + formatted : formatted;
    };

    const computeAmountValue = (amountInput, quantityInput, rateInput) => {
        let amount = 0;

        if (amountInput) {
            amount = round(parseNumber(amountInput.value));
        }

        if (amount > 0) {
            return amount;
        }

        const quantity = quantityInput ? parseNumber(quantityInput.value) : 0;
        const rate = rateInput ? parseNumber(rateInput.value) : 0;
        const computed = round(quantity * rate);

        return computed > 0 ? computed : 0;
    };

    const recalc = () => {
        const teachingHours = parseNumber(form.querySelector('input[name="teaching_hours"]')?.value ?? '0');
        const hourlyRate = parseNumber(form.querySelector('input[name="hourly_rate"]')?.value ?? '0');

        const totals = {
            total_teaching: round(teachingHours * hourlyRate),
            total_special: 0,
            total_academic: 0,
            total_activity: 0,
            total_adjustment: 0,
            total_deduction: 0,
        };

        componentsTable.querySelectorAll('tbody tr').forEach((row) => {
            const includeInput = row.querySelector('input[type="checkbox"][name$="[include]"]');
            if (!includeInput || !includeInput.checked) {
                return;
            }

            const typeField = row.querySelector('[name$="[type]"]');
            if (!typeField) {
                return;
            }

            const amountField = row.querySelector('input[name$="[amount]"]');
            const quantityField = row.querySelector('input[name$="[quantity]"]');
            const rateField = row.querySelector('input[name$="[rate]"]');

            const type = typeField.value || 'adjustment';
            const amount = computeAmountValue(amountField, quantityField, rateField);
            if (amount <= 0) {
                return;
            }

            switch (type) {
                case 'special':
                    totals.total_special += amount;
                    break;
                case 'academic':
                    totals.total_academic += amount;
                    break;
                case 'activity':
                    totals.total_activity += amount;
                    break;
                case 'deduction':
                    totals.total_deduction += amount;
                    break;
                default:
                    totals.total_adjustment += amount;
            }
        });

        const totalBruto = totals.total_teaching + totals.total_special + totals.total_academic + totals.total_activity + totals.total_adjustment;
        const totalNet = round(totalBruto - totals.total_deduction);

        if (summaryNodes.total_teaching) {
            summaryNodes.total_teaching.textContent = formatCurrency(totals.total_teaching);
        }
        if (summaryNodes.total_special) {
            summaryNodes.total_special.textContent = formatCurrency(totals.total_special);
        }
        if (summaryNodes.total_academic) {
            summaryNodes.total_academic.textContent = formatCurrency(totals.total_academic);
        }
        if (summaryNodes.total_activity) {
            summaryNodes.total_activity.textContent = formatCurrency(totals.total_activity);
        }
        if (summaryNodes.total_adjustment) {
            summaryNodes.total_adjustment.textContent = formatCurrency(totals.total_adjustment);
        }
        if (summaryNodes.total_deduction) {
            summaryNodes.total_deduction.textContent = totals.total_deduction > 0 ? '- ' + formatCurrency(totals.total_deduction) : '- Rp 0';
        }
        if (summaryNodes.total_net) {
            summaryNodes.total_net.textContent = formatCurrency(totalNet, true);
        }
    };

    const setupRowAutoCalc = (row) => {
        const amountInput = row.querySelector('input[name$="[amount]"]');
        const quantityInput = row.querySelector('input[name$="[quantity]"]');
        const rateInput = row.querySelector('input[name$="[rate]"]');

        if (amountInput) {
            amountInput.addEventListener('input', () => {
                if (amountInput.value.trim() === '') {
                    delete amountInput.dataset.manual;
                } else {
                    amountInput.dataset.manual = 'true';
                }
                recalc();
            });
        }

        const autoUpdateAmount = () => {
            if (!amountInput) {
                recalc();
                return;
            }

            if (amountInput.dataset.manual === 'true' && amountInput.value.trim() !== '') {
                recalc();
                return;
            }

            const quantity = quantityInput ? parseNumber(quantityInput.value) : 0;
            const rate = rateInput ? parseNumber(rateInput.value) : 0;
            const computed = round(quantity * rate);

            if (computed > 0) {
                amountInput.value = formatNumberForInput(computed);
            } else if (!amountInput.dataset.manual) {
                amountInput.value = '';
            }

            recalc();
        };

        if (quantityInput) {
            quantityInput.addEventListener('input', autoUpdateAmount);
        }

        if (rateInput) {
            rateInput.addEventListener('input', autoUpdateAmount);
        }
    };

    componentsTable.querySelectorAll('tbody tr').forEach(setupRowAutoCalc);

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement) {
            recalc();
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement) {
            recalc();
        }
    });

    recalc();
});
</script>
<?php endif; ?>
