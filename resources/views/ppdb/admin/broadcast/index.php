<?php
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $periodOptions = $periodOptions ?? [];
    $periodId = (int) ($selectedPeriodId ?? 0);
    $period = $selectedPeriod ?? null;
    $submitRoute = $submitRoute ?? 'ppdb/admin/broadcast';
    $indexRoute = $indexRoute ?? 'ppdb/admin/broadcast';
    $broadcastTemplate = (string) ($broadcastTemplate ?? '');
    $registrationTemplate = (string) ($registrationTemplate ?? '');
    $placeholders = $placeholders ?? [];
    $placeholderDescriptions = $placeholderDescriptions ?? [];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100">Broadcast PPDB</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Kirim pesan ke seluruh pendaftar dalam periode PPDB tertentu.</p>
        </div>
        <?php if (!empty($periodOptions)): ?>
            <form action="<?= htmlspecialchars(base_url($indexRoute), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex items-center gap-2">
                <label class="text-sm font-medium text-slate-600 dark:text-gray-300">
                    Periode
                    <select name="periode_id" class="ml-2 rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                        <?php foreach ($periodOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= $periodId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Kirim Pesan Broadcast</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">
                <?= $period !== null ? 'Periode aktif: ' . htmlspecialchars((string) ($period['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') : 'Pilih periode untuk mulai kirim pesan.' ?>
            </p>

            <form action="<?= htmlspecialchars(base_url($submitRoute), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-5 space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_broadcast" />
                <input type="hidden" name="periode_id" value="<?= $periodId ?>" />

                <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                    Pesan Broadcast
                    <textarea
                        name="broadcast_message"
                        rows="8"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"
                        placeholder="Contoh: Pengumuman seleksi akan dilaksanakan pada tanggal 10 Juni 2026 pukul 08.00 WIB."
                    ><?= htmlspecialchars((string) old('broadcast_message'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>

                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                    Kirim ke Semua Pendaftar
                </button>
            </form>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Placeholder Tersedia</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($placeholders as $placeholder): ?>
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 px-3 py-2 dark:border-indigo-400/40 dark:bg-indigo-500/10">
                            <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-200"><?= htmlspecialchars((string) $placeholder, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                <?= htmlspecialchars((string) ($placeholderDescriptions[$placeholder] ?? 'Placeholder dinamis dari data pendaftar/periode.'), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Pengaturan Template Pesan</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Template ini dipakai untuk notifikasi pendaftaran dan broadcast PPDB.</p>
                <form action="<?= htmlspecialchars(base_url($submitRoute), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-4 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_templates" />
                    <input type="hidden" name="periode_id" value="<?= $periodId ?>" />

                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Template Notifikasi Pendaftaran
                        <textarea name="registration_template" rows="6" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"><?= htmlspecialchars((string) old('registration_template', $registrationTemplate), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Template Broadcast
                        <textarea name="broadcast_template" rows="6" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"><?= htmlspecialchars((string) old('broadcast_template', $broadcastTemplate), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>

                    <button type="submit" class="inline-flex items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                        Simpan Template
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
