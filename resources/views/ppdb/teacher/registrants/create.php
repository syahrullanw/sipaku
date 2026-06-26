<?php
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $periodId = (int) ($selectedPeriodId ?? 0);
    $period = $selectedPeriod ?? null;
    $periodOptions = $periodOptions ?? [];
    $majorOptions = $majorOptions ?? [];
    $extracurricularOptions = $extracurricularOptions ?? [];
    $storeRoute = $storeRoute ?? 'ppdb/guru/pendaftar';
    $backRoute = $backRoute ?? 'ppdb/guru/pendaftar';
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100">Tambah Data Pendaftar</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Input pendaftar PPDB yang datang langsung ke sekolah.</p>
        </div>
        <a href="<?= htmlspecialchars(base_url($backRoute . '?periode_id=' . $periodId), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-gray-200 dark:hover:bg-slate-700/50">
            Kembali ke Data Pendaftar
        </a>
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

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <?php if ($period === null): ?>
            <p class="text-sm text-slate-500 dark:text-gray-400">Periode PPDB tidak ditemukan.</p>
        <?php elseif (!($canManualRegister ?? false)): ?>
            <p class="text-sm text-slate-500 dark:text-gray-400">Tahap pendaftaran sedang dinonaktifkan untuk periode ini.</p>
        <?php elseif (empty($majorOptions) || empty($extracurricularOptions)): ?>
            <p class="text-sm text-slate-500 dark:text-gray-400">Data jurusan atau ekskul belum tersedia. Silakan lengkapi data master terlebih dahulu.</p>
        <?php else: ?>
            <form action="<?= htmlspecialchars(base_url($storeRoute), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-4">
                <?= csrf_field() ?>

                <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                    Periode
                    <select name="periode_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                        <?php foreach ($periodOptions as $id => $label): ?>
                            <option value="<?= (int) $id ?>" <?= (int) $id === $periodId ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Nama Lengkap
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars((string) old('nama_lengkap'), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Tanggal Lahir
                        <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars((string) old('tanggal_lahir'), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Jenis Kelamin
                        <select name="jenis_kelamin" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                            <option value="">Pilih...</option>
                            <option value="L" <?= (string) old('jenis_kelamin') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= (string) old('jenis_kelamin') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Nama Ibu
                        <input type="text" name="nama_wali" value="<?= htmlspecialchars((string) old('nama_wali'), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300 md:col-span-2">
                        Alamat
                        <textarea name="alamat" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"><?= htmlspecialchars((string) old('alamat'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        No HP / WhatsApp
                        <input type="text" name="telepon" value="<?= htmlspecialchars((string) old('telepon'), ENT_QUOTES, 'UTF-8') ?>" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300">
                        Pilihan Ekskul
                        <select name="extracurricular_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                            <option value="">Pilih...</option>
                            <?php foreach ($extracurricularOptions as $id => $label): ?>
                                <option value="<?= (int) $id ?>" <?= (int) old('extracurricular_id', 0) === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-600 dark:text-gray-300 md:col-span-2">
                        Jurusan
                        <select name="major_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100">
                            <option value="">Pilih...</option>
                            <?php foreach ($majorOptions as $id => $label): ?>
                                <option value="<?= (int) $id ?>" <?= (int) old('major_id', 0) === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                    Simpan Data Pendaftar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
