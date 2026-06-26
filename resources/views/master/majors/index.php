<?php $isEditing = isset($editingMajor) && $editingMajor !== null; ?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Jurusan' : 'Tambah Jurusan' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/jurusan/' . $editingMajor['id'] . '/update') : base_url('master/jurusan'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="kode" class="block text-sm font-medium text-slate-600">Kode</label>
                    <input
                        type="text"
                        id="kode"
                        name="kode"
                        value="<?= htmlspecialchars((string) old('kode', $editingMajor['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="RPL"
                    />
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingMajor['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Rekayasa Perangkat Lunak"
                    />
                </div>
                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-slate-600">Deskripsi</label>
                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="3"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Ringkasan jurusan"
                    ><?= htmlspecialchars((string) old('deskripsi', $editingMajor['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <?php $status = old('status', $editingMajor['status'] ?? 'aktif'); ?>
                <div>
                    <span class="block text-sm font-medium text-slate-600">Status</span>
                    <label class="mt-2 inline-flex items-center gap-3 cursor-pointer select-none">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status === 'aktif' ? 'aktif' : 'nonaktif', ENT_QUOTES, 'UTF-8') ?>" data-status-input />
                        <input
                            type="checkbox"
                            id="status-toggle"
                            data-status-toggle
                            <?= $status === 'aktif' ? 'checked' : '' ?>
                            class="sr-only peer"
                        />
                        <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full bg-slate-300 transition peer-checked:bg-emerald-500">
                            <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span>
                        </span>
                        <span class="text-sm font-semibold text-slate-600" data-status-label><?= $status === 'aktif' ? 'Aktif' : 'Nonaktif' ?></span>
                    </label>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/jurusan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Jurusan</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Deskripsi</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($majors as $major): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($major['kode'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($major['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($major['deskripsi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4">
                                    <?php $isActive = ($major['status'] ?? 'aktif') === 'aktif'; ?>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                                        <?= htmlspecialchars(strtoupper($major['status'] ?? 'nonaktif'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars(base_url('master/jurusan?edit=' . urlencode((string) $major['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <form action="<?= htmlspecialchars(base_url('master/jurusan/' . $major['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus jurusan ini?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($majors)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data jurusan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('[data-status-toggle]');
        const hiddenInput = document.querySelector('[data-status-input]');
        const label = document.querySelector('[data-status-label]');

        if (!toggle || !hiddenInput || !label) {
            return;
        }

        const sync = () => {
            if (toggle.checked) {
                hiddenInput.value = 'aktif';
                label.textContent = 'Aktif';
            } else {
                hiddenInput.value = 'nonaktif';
                label.textContent = 'Nonaktif';
            }
        };

        toggle.addEventListener('change', sync);
        sync();
    });
</script>
