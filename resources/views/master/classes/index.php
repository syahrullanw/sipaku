<?php
    $isEditing = isset($editingClass) && $editingClass !== null;
    $activeYear = $activeYear ?? null;
    $selectedYearId = (int) ($selectedYearId ?? 0);
    $selectedYearLabel = $selectedYearLabel ?? null;
    if ($selectedYearLabel === null && $activeYear !== null) {
        $selectedYearLabel = sprintf('%s - %s', $activeYear['nama'], (int) ($activeYear['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
    }
    $disableClassForm = (!$isEditing && ($activeYear === null)) || (!$isEditing && empty($majorsOptions));
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Kelas' : 'Tambah Kelas' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/kelas/' . $editingClass['id'] . '/update') : base_url('master/kelas'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) ($editingClass['tahun_ajaran_id'] ?? $selectedYearId), ENT_QUOTES, 'UTF-8') ?>" />
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    <?php if ($selectedYearLabel !== null): ?>
                        Data tersimpan pada: <span class="font-semibold text-slate-700"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="font-semibold text-rose-600">Belum ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="jurusan_id" class="block text-sm font-medium text-slate-600">Jurusan</label>
                    <select
                        id="jurusan_id"
                        name="jurusan_id"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $disableClassForm ? 'disabled' : '' ?>
                    >
                        <option value="">-- Pilih jurusan --</option>
                        <?php $selectedMajor = (int) old('jurusan_id', $editingClass['jurusan_id'] ?? 0); ?>
                        <?php foreach ($majorsOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedMajor === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($majorsOptions)): ?>
                        <p class="mt-2 text-xs text-slate-400">Tidak ada jurusan aktif yang tersedia.</p>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tingkat" class="block text-sm font-medium text-slate-600">Tingkat</label>
                        <input
                            type="number"
                            id="tingkat"
                            name="tingkat"
                            min="10"
                            max="13"
                            value="<?= htmlspecialchars((string) old('tingkat', $editingClass['tingkat'] ?? '10'), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            <?= $disableClassForm ? 'disabled' : '' ?>
                        />
                    </div>
                    <div>
                        <label for="nama" class="block text-sm font-medium text-slate-600">Nama Kelas</label>
                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            value="<?= htmlspecialchars((string) old('nama', $editingClass['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="XI RPL 1"
                            <?= $disableClassForm ? 'disabled' : '' ?>
                        />
                    </div>
                </div>
                <div>
                    <label for="kurikulum" class="block text-sm font-medium text-slate-600">Kurikulum</label>
                    <?php $selectedKurikulum = old('kurikulum', $editingClass['kurikulum'] ?? 'k13'); ?>
                    <select
                        id="kurikulum"
                        name="kurikulum"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $disableClassForm ? 'disabled' : '' ?>
                    >
                        <option value="k13" <?= $selectedKurikulum === 'k13' ? 'selected' : '' ?>>K13</option>
                        <option value="kurmer" <?= $selectedKurikulum === 'kurmer' ? 'selected' : '' ?>>Kurikulum Merdeka</option>
                    </select>
                </div>
                <div>
                    <label for="wali_kelas_id" class="block text-sm font-medium text-slate-600">Wali Kelas</label>
                    <select
                        id="wali_kelas_id"
                        name="wali_kelas_id"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $disableClassForm ? 'disabled' : '' ?>
                    >
                        <option value="">-- Pilih wali kelas --</option>
                        <?php $selectedTeacher = (int) old('wali_kelas_id', $editingClass['wali_kelas_id'] ?? 0); ?>
                        <?php foreach ($teacherOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedTeacher === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/kelas'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Kelas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4">Tahun Ajaran</th>
                            <th class="px-6 py-4">Jurusan</th>
                            <th class="px-6 py-4">Kurikulum</th>
                            <th class="px-6 py-4">Wali Kelas</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700">
                                    <?= htmlspecialchars('Kelas ' . $class['tingkat'] . ' - ' . $class['nama'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($class['tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($class['jurusan_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars(strtoupper($class['kurikulum'] ?? 'k13'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($class['wali_kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars(base_url('master/kelas?edit=' . urlencode((string) $class['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <form action="<?= htmlspecialchars(base_url('master/kelas/' . $class['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus kelas ini?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data kelas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
