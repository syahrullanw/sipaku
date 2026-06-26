<?php
    /** @var array<int, array<string, mixed>> $attitudes */
    /** @var array<string, string> $typeOptions */

    $isEditing = isset($editingAttitude) && $editingAttitude !== null;
    $selectedType = $selectedType ?? 'spiritual';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl font-semibold text-slate-800">Data Sikap</h2>
        <p class="text-sm text-slate-500">Kelola indikator sikap spiritual dan sikap sosial sebagai data dasar penilaian.</p>
    </div>
    <div class="inline-flex rounded-full border border-slate-200 bg-white p-1 text-sm font-semibold text-slate-500">
        <?php foreach ($typeOptions as $value => $label): ?>
            <?php $isActive = $selectedType === $value; ?>
            <a
                href="<?= htmlspecialchars(base_url('master/data-sikap?jenis=' . $value), ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center rounded-full px-4 py-1.5 <?= $isActive ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>"
            >
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-800">Import Data Sikap</h3>
            <p class="mt-1 text-xs text-slate-500">
                Gunakan file XLS/XLSX dengan kolom wajib: <code class="font-mono text-[11px]">kode</code>, <code class="font-mono text-[11px]">nama</code>.
                Kolom opsional: <code class="font-mono text-[11px]">deskripsi</code>, <code class="font-mono text-[11px]">status</code> (aktif/nonaktif), <code class="font-mono text-[11px]">jenis</code> (spiritual/sosial).
                Jika kolom <code class="font-mono text-[11px]">jenis</code> dikosongkan maka data akan disimpan sebagai <strong><?= htmlspecialchars($typeOptions[$selectedType] ?? 'Sikap', ENT_QUOTES, 'UTF-8') ?></strong>.
            </p>
        </div>
        <form
            action="<?= htmlspecialchars(base_url('master/data-sikap/import'), ENT_QUOTES, 'UTF-8') ?>"
            method="post"
            enctype="multipart/form-data"
            class="flex flex-col gap-2 sm:flex-row sm:items-center"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="jenis" value="<?= htmlspecialchars($selectedType, ENT_QUOTES, 'UTF-8') ?>">
            <input
                type="file"
                name="import_file"
                accept=".xls,.xlsx"
                required
                class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500 focus:outline-none"
            />
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                <i class="ri-upload-cloud-line text-base"></i>
                <span>Import</span>
            </button>
        </form>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Data Sikap' : 'Tambah Data Sikap' ?>
            </h3>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/data-sikap/' . $editingAttitude['id'] . '/update') : base_url('master/data-sikap'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="jenis" class="block text-sm font-medium text-slate-600">Jenis Sikap</label>
                    <select
                        id="jenis"
                        name="jenis"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        required
                    >
                        <?php $currentType = old('jenis', $isEditing ? ($editingAttitude['jenis'] ?? $selectedType) : $selectedType); ?>
                        <?php foreach ($typeOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $currentType === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="kode" class="block text-sm font-medium text-slate-600">Kode</label>
                    <input
                        type="text"
                        id="kode"
                        name="kode"
                        value="<?= htmlspecialchars((string) old('kode', $editingAttitude['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm uppercase tracking-wide focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="SS-01"
                    />
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama / Indikator</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingAttitude['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Menunjukkan perilaku syukur"
                    />
                </div>
                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-slate-600">Deskripsi Detail</label>
                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="3"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Tambahkan uraian indikator sikap"
                    ><?= htmlspecialchars((string) old('deskripsi', $editingAttitude['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <?php $statusValue = old('status', $editingAttitude['status'] ?? 'aktif'); ?>
                <div>
                    <span class="block text-sm font-medium text-slate-600">Status</span>
                    <label class="mt-2 inline-flex items-center gap-3 cursor-pointer select-none">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusValue === 'nonaktif' ? 'nonaktif' : 'aktif', ENT_QUOTES, 'UTF-8') ?>" data-status-input />
                        <input
                            type="checkbox"
                            data-status-toggle
                            <?= $statusValue === 'aktif' ? 'checked' : '' ?>
                            class="sr-only peer"
                        />
                        <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full bg-slate-300 transition peer-checked:bg-emerald-500">
                            <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span>
                        </span>
                        <span class="text-sm font-semibold text-slate-600" data-status-label><?= $statusValue === 'aktif' ? 'Aktif' : 'Nonaktif' ?></span>
                    </label>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/data-sikap?jenis=' . ($editingAttitude['jenis'] ?? $selectedType)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">Daftar <?= htmlspecialchars($typeOptions[$selectedType] ?? 'Sikap', ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-xs text-slate-500">Menampilkan data berstatus aktif dan nonaktif.</p>
                </div>
                <a
                    href="<?= htmlspecialchars(base_url('master/data-sikap?jenis=' . $selectedType), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                >
                    <i class="ri-refresh-line text-sm"></i> Segarkan
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Nama / Indikator</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Diperbarui</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($attitudes as $attitude): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($attitude['kode'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-slate-700"><?= htmlspecialchars($attitude['nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if (!empty($attitude['deskripsi'])): ?>
                                        <p class="mt-1 text-xs text-slate-500"><?= nl2br(htmlspecialchars($attitude['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php $isActive = ($attitude['status'] ?? 'aktif') === 'aktif'; ?>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                                        <?= htmlspecialchars(strtoupper($attitude['status'] ?? 'nonaktif'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    <?= htmlspecialchars($attitude['updated_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a
                                            href="<?= htmlspecialchars(base_url('master/data-sikap?jenis=' . $attitude['jenis'] . '&edit=' . $attitude['id']), ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                        >
                                            Edit
                                        </a>
                                        <form
                                            action="<?= htmlspecialchars(base_url('master/data-sikap/' . $attitude['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>"
                                            method="post"
                                            onsubmit="return confirm('Hapus data sikap ini?');"
                                        >
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attitudes)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data sikap untuk jenis ini.</td>
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
