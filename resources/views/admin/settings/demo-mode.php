<?php
    $isEnabled = isset($isEnabled) ? (bool) $isEnabled : false;
    $statusLabel = $isEnabled ? 'Aktif' : 'Nonaktif';
    $statusClasses = $isEnabled
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : 'bg-slate-100 text-slate-600 border-slate-200';
?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Mode Demo</p>
                <p class="mt-2 text-2xl font-bold text-slate-800"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-sm text-slate-500">
                    Saat mode demo aktif, data pribadi guru (NIP/NIK, email, telepon, alamat, NPWP, tanggal lahir, dan kontak keluarga)
                    otomatis disamarkan dan banner peringatan ditampilkan di header.
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    Kata sandi pengaturan berada di <code class="rounded bg-slate-100 px-2 py-1 text-[11px]">app/Config/demo.php</code>.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-semibold <?= $statusClasses ?>">
                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form action="<?= htmlspecialchars(base_url('admin/demo-mode'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold text-slate-700">Ubah Mode</p>
                    <p class="mt-1 text-xs text-slate-500">Pilih apakah mode demo akan dinyalakan atau dimatikan.</p>
                    <div class="mt-3 flex flex-col gap-2">
                        <?php $actions = [
                            ['value' => 'enable', 'label' => 'Aktifkan mode demo', 'description' => 'Sembunyikan data sensitif dan tampilkan banner peringatan.'],
                            ['value' => 'disable', 'label' => 'Nonaktifkan mode demo', 'description' => 'Tampilkan data sesuai aslinya.'],
                        ]; ?>
                        <?php foreach ($actions as $action): ?>
                            <?php $checked = ($isEnabled && $action['value'] === 'enable') || (!$isEnabled && $action['value'] === 'disable'); ?>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-3 py-2 transition hover:border-indigo-200 hover:bg-indigo-50">
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
                <div>
                    <label for="demo_password" class="block text-sm font-semibold text-slate-700">Password Mode Demo</label>
                    <p class="mt-1 text-xs text-slate-500">Hanya yang tahu password ini yang dapat mengaktifkan atau menonaktifkan mode demo.</p>
                    <input
                        type="password"
                        id="demo_password"
                        name="password"
                        required
                        autocomplete="off"
                        class="mt-3 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Masukkan password khusus"
                    />
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
                    <i class="ri-shield-keyhole-line text-base"></i>
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
