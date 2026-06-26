<?php
    /** @var array<int, array<string, mixed>> $releases */

    $releases = $releases ?? [];
    $currentVersion = (string) ($currentVersion ?? '');

    $sectionTone = static function (string $section): string {
        return match (strtolower($section)) {
            'added' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:border-emerald-500/30',
            'changed' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:border-sky-500/30',
            'fixed' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:border-amber-500/30',
            default => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-700/30 dark:text-gray-200 dark:border-slate-600',
        };
    };

    $statusTone = static function (string $status): string {
        return strtolower($status) === 'released'
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'
            : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200';
    };
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">Riwayat Rilis</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900 dark:text-gray-100">Changelog</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-gray-300">
                Catatan pembaruan aplikasi per versi untuk membantu admin, guru, siswa, dan petugas sekolah memahami fitur baru, perubahan, dan bug fix yang sudah masuk.
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-gray-500">Versi Aktif</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-gray-100">
                v<?= htmlspecialchars($currentVersion !== '' ? $currentVersion : '-', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>

    <?php if (empty($releases)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            Belum ada changelog yang tersedia.
        </div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($releases as $release): ?>
                <?php
                    $version = (string) ($release['version'] ?? '');
                    $title = (string) ($release['title'] ?? ('SIPAKU ' . $version));
                    $status = (string) ($release['status'] ?? '');
                    $sections = (array) ($release['sections'] ?? []);
                    $isCurrent = $currentVersion !== '' && $version === $currentVersion;
                ?>
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-gray-100">
                                    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                </h2>
                                <?php if ($isCurrent): ?>
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200">Aktif</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($version !== ''): ?>
                                <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Versi <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($status !== ''): ?>
                            <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold <?= htmlspecialchars($statusTone($status), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($sections)): ?>
                        <p class="mt-4 text-sm text-slate-500 dark:text-gray-400">Tidak ada rincian perubahan.</p>
                    <?php else: ?>
                        <div class="mt-5 grid gap-4 lg:grid-cols-3">
                            <?php foreach ($sections as $section => $items): ?>
                                <?php
                                    $items = array_values((array) $items);
                                    if (empty($items)) {
                                        continue;
                                    }
                                ?>
                                <section class="rounded-lg border p-4 <?= htmlspecialchars($sectionTone((string) $section), ENT_QUOTES, 'UTF-8') ?>">
                                    <h3 class="text-sm font-semibold"><?= htmlspecialchars((string) $section, ENT_QUOTES, 'UTF-8') ?></h3>
                                    <ul class="mt-3 space-y-2 text-sm leading-6">
                                        <?php foreach ($items as $item): ?>
                                            <li class="flex gap-2">
                                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>
                                                <span><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
