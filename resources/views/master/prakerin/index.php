<?php
$isEditing = isset($editingPlace) && $editingPlace !== null;
$activeSchoolYear = $activeSchoolYear ?? null;
$mentorOptions = $mentorOptions ?? [];
$hasActiveYear = is_array($activeSchoolYear);
$semesterNumber = (int) ($activeSchoolYear['semester_aktif'] ?? 1);
$semesterLabel = $semesterNumber === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
$activeYearLabel = $hasActiveYear ? sprintf('%s - %s', $activeSchoolYear['nama'] ?? 'Tahun Ajaran', $semesterLabel) : null;
$formDisabled = !$hasActiveYear || empty($mentorOptions);
?>

<div class="grid gap-6 lg:grid-cols-12 xl:gap-8">
    <div class="lg:col-span-5 xl:col-span-4 2xl:col-span-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Tempat Prakerin' : 'Tambah Tempat Prakerin' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/prakerin/' . $editingPlace['id'] . '/update') : base_url('master/prakerin'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <?php if ($hasActiveYear): ?>
                        <span class="font-semibold text-slate-700">Tahun Ajaran Aktif:</span>
                        <span><?= htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="font-semibold text-amber-600">Belum ada tahun ajaran aktif. Silakan tetapkan tahun ajaran aktif terlebih dahulu.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama Tempat</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingPlace['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="PT Maju Jaya"
                    />
                </div>
                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-slate-600">Deskripsi</label>
                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="4"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Ringkasan singkat mengenai tempat prakerin"
                    ><?= htmlspecialchars((string) old('deskripsi', $editingPlace['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="pembina_guru_id" class="block text-sm font-medium text-slate-600">Pembina</label>
                    <select
                        id="pembina_guru_id"
                        name="pembina_guru_id"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $formDisabled ? 'disabled' : '' ?>
                        required
                    >
                        <option value="">Pilih pembina aktif</option>
                        <?php foreach (($mentorOptions ?? []) as $mentorId => $mentorName): ?>
                            <option
                                value="<?= htmlspecialchars((string) $mentorId, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string) $mentorId === (string) old('pembina_guru_id', $editingPlace['pembina_guru_id'] ?? '') ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($mentorName, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($mentorOptions ?? [])): ?>
                        <p class="mt-2 text-xs text-amber-500">Belum ada guru aktif yang terdaftar pada semester berjalan.</p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300"
                        <?= $formDisabled ? 'disabled' : '' ?>
                    >
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/prakerin'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7 xl:col-span-8 2xl:col-span-9">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Tempat Prakerin</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Deskripsi</th>
                            <th class="px-6 py-4">Pembina</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($places as $place): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700"><?= htmlspecialchars($place['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($place['deskripsi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($place['pembina_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars(base_url('master/prakerin?edit=' . urlencode((string) $place['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <form action="<?= htmlspecialchars(base_url('master/prakerin/' . $place['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus tempat prakerin ini?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($places)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data tempat prakerin.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
