<?php
    /** @var array<int, string> $roleLabels */
    /** @var array<int, array{label:string, entries:array<int, array<string, mixed>>}> $guideGroups */

    $roleLabels = $roleLabels ?? [];
    $guideGroups = $guideGroups ?? [];
    $totalEntries = array_sum(array_map(static fn (array $group): int => count($group['entries'] ?? []), $guideGroups));
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Pusat Bantuan</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-gray-100">Pedoman Penggunaan</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-gray-300">
                Panduan ringkas ini menampilkan modul yang tersedia untuk akun Anda, beserta alur penggunaan utama dan catatan penting agar input data tetap rapi.
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Akses Akun</div>
            <div class="mt-2 flex flex-wrap gap-2">
                <?php if (empty($roleLabels)): ?>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-gray-200">Pengguna</span>
                <?php else: ?>
                    <?php foreach ($roleLabels as $roleLabel): ?>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">
                            <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Jumlah Modul</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-gray-100"><?= (int) $totalEntries ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Kategori</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-gray-100"><?= count($guideGroups) ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Status</div>
            <div class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Disesuaikan dengan hak akses login</div>
        </div>
    </div>

    <?php if (empty($guideGroups)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            Belum ada pedoman yang tersedia untuk akun ini. Hubungi admin untuk mengecek pengaturan hak akses.
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($guideGroups as $group): ?>
                <?php
                    $groupLabel = (string) ($group['label'] ?? 'Modul');
                    $entries = array_values((array) ($group['entries'] ?? []));
                ?>
                <?php if (empty($entries)): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-2 dark:border-slate-700">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-gray-100">
                            <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <span class="text-xs font-semibold text-slate-400 dark:text-gray-500"><?= count($entries) ?> pedoman</span>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <?php foreach ($entries as $entry): ?>
                            <?php
                                $label = (string) ($entry['label'] ?? 'Modul');
                                $description = (string) ($entry['description'] ?? '');
                                $steps = array_values((array) ($entry['steps'] ?? []));
                                $tips = array_values((array) ($entry['tips'] ?? []));
                                $url = $entry['url'] ?? null;
                            ?>
                            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-900 dark:text-gray-100">
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </h3>
                                        <?php if ($description !== ''): ?>
                                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-gray-300">
                                                <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (is_string($url) && $url !== ''): ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/40 dark:text-indigo-200 dark:hover:bg-indigo-500/10">
                                            <i class="ri-external-link-line text-sm"></i>
                                            Buka Menu
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($steps)): ?>
                                    <div class="mt-4">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Alur Penggunaan</div>
                                        <ol class="mt-2 space-y-2 text-sm leading-6 text-slate-600 dark:text-gray-300">
                                            <?php foreach ($steps as $index => $step): ?>
                                                <li class="flex gap-3">
                                                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-gray-200"><?= $index + 1 ?></span>
                                                    <span><?= htmlspecialchars((string) $step, ENT_QUOTES, 'UTF-8') ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($tips)): ?>
                                    <div class="mt-4 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-sm leading-6 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                                        <div class="font-semibold">Catatan</div>
                                        <ul class="mt-1 space-y-1">
                                            <?php foreach ($tips as $tip): ?>
                                                <li><?= htmlspecialchars((string) $tip, ENT_QUOTES, 'UTF-8') ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
