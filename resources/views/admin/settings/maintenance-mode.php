<?php
    $isEnabled = isset($isEnabled) ? (bool) $isEnabled : false;
    $statusLabel = $isEnabled ? 'Aktif' : 'Nonaktif';
    $statusClasses = $isEnabled
        ? 'bg-amber-100 text-amber-800 border-amber-200'
        : 'bg-emerald-100 text-emerald-700 border-emerald-200';
?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Maintenance Mode</p>
                <p class="mt-2 text-2xl font-bold text-slate-800"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-sm text-slate-500">
                    Saat aktif, hanya akun admin yang dapat masuk dan mengakses aplikasi. Pengguna lain akan melihat halaman maintenance.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold <?= $statusClasses ?>">
                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form action="<?= htmlspecialchars(base_url('admin/maintenance-mode'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
            <?= csrf_field() ?>
            <div>
                <p class="text-sm font-semibold text-slate-700">Ubah Mode</p>
                <p class="mt-1 text-xs text-slate-500">Gunakan mode ini ketika aplikasi sedang diperbaiki atau diuji oleh admin.</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <?php $actions = [
                        ['value' => 'enable', 'label' => 'Aktifkan maintenance', 'description' => 'Blokir akses publik, guru, siswa, dan role non-admin.'],
                        ['value' => 'disable', 'label' => 'Nonaktifkan maintenance', 'description' => 'Buka kembali akses aplikasi untuk semua pengguna sesuai hak akses.'],
                    ]; ?>
                    <?php foreach ($actions as $action): ?>
                        <?php $checked = ($isEnabled && $action['value'] === 'enable') || (!$isEnabled && $action['value'] === 'disable'); ?>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-indigo-200 hover:bg-indigo-50">
                            <input
                                type="radio"
                                name="action"
                                value="<?= htmlspecialchars($action['value'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-1 h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                <?= $checked ? 'checked' : '' ?>
                                required
                            />
                            <span>
                                <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($action['description'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a
                    href="<?= htmlspecialchars(base_url('dashboard'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                    <i class="ri-tools-line text-base"></i>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
