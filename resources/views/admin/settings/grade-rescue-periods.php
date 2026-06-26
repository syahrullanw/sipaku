<?php
    $periods = isset($periods) && is_array($periods) ? $periods : [];
    $years = isset($years) && is_array($years) ? $years : [];
    $editing = isset($editing) && is_array($editing) ? $editing : null;
    $isEditing = $editing !== null;

    $toInputDateTimeLocal = static function (?string $value): string {
        if ($value === null || trim($value) === '') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $ts);
    };
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Periode Rescue' : 'Tambah Periode Rescue' ?>
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Atur kapan walikelas diizinkan upload nilai rescue.
            </p>

            <form
                action="<?= htmlspecialchars($isEditing ? base_url('admin/periode-rescue-nilai/' . (int) $editing['id'] . '/update') : base_url('admin/periode-rescue-nilai'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>

                <div>
                    <label for="tahun_ajaran_id" class="block text-sm font-medium text-slate-600">Tahun Ajaran</label>
                    <select id="tahun_ajaran_id" name="tahun_ajaran_id" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Pilih Tahun Ajaran</option>
                        <?php foreach ($years as $year): ?>
                            <?php $yearId = (int) ($year['id'] ?? 0); ?>
                            <option value="<?= $yearId ?>" <?= ((int) old('tahun_ajaran_id', $editing['tahun_ajaran_id'] ?? 0) === $yearId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($year['nama'] ?? ('TA #' . $yearId)), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="semester" class="block text-sm font-medium text-slate-600">Semester</label>
                        <?php $sem = strtolower((string) old('semester', $editing['semester'] ?? 'ganjil')); ?>
                        <select id="semester" name="semester" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="ganjil" <?= $sem === 'ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="genap" <?= $sem === 'genap' ? 'selected' : '' ?>>Genap</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-600">Status</label>
                        <?php $status = strtolower((string) old('status', $editing['status'] ?? 'aktif')); ?>
                        <select id="status" name="status" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama Periode</label>
                    <input id="nama" name="nama" type="text" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm" value="<?= htmlspecialchars((string) old('nama', $editing['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Rescue Nilai UTS Semester Genap">
                </div>

                <div>
                    <label for="mulai_at" class="block text-sm font-medium text-slate-600">Mulai</label>
                    <input id="mulai_at" name="mulai_at" type="datetime-local" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) old('mulai_at', $isEditing ? $toInputDateTimeLocal($editing['mulai_at'] ?? null) : ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div>
                    <label for="selesai_at" class="block text-sm font-medium text-slate-600">Selesai</label>
                    <input id="selesai_at" name="selesai_at" type="datetime-local" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) old('selesai_at', $isEditing ? $toInputDateTimeLocal($editing['selesai_at'] ?? null) : ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div>
                    <label for="catatan" class="block text-sm font-medium text-slate-600">Catatan</label>
                    <textarea id="catatan" name="catatan" rows="3" class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Opsional"><?= htmlspecialchars((string) old('catatan', $editing['catatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan Periode' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('admin/periode-rescue-nilai'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            Batal
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Periode Rescue Nilai</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Periode</th>
                            <th class="px-6 py-4">Waktu</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (empty($periods)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500">Belum ada periode rescue.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($periods as $period): ?>
                                <?php
                                    $id = (int) ($period['id'] ?? 0);
                                    $statusValue = strtolower((string) ($period['status'] ?? 'nonaktif'));
                                    $statusBadge = $statusValue === 'aktif'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                        : 'border-slate-200 bg-slate-50 text-slate-500';
                                ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($period['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            <?= htmlspecialchars((string) ($period['tahun_ajaran_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                            &middot; Semester <?= htmlspecialchars(ucfirst((string) ($period['semester'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <?php if (!empty($period['catatan'])): ?>
                                            <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) $period['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <p><?= htmlspecialchars((string) ($period['mulai_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-400">s.d. <?= htmlspecialchars((string) ($period['selesai_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= $statusBadge ?>">
                                            <?= $statusValue === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="<?= htmlspecialchars(base_url('admin/periode-rescue-nilai?edit=' . $id), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">
                                                Edit
                                            </a>
                                            <form action="<?= htmlspecialchars(base_url('admin/periode-rescue-nilai/' . $id . '/toggle'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-50">
                                                    <?= $statusValue === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                            <form action="<?= htmlspecialchars(base_url('admin/periode-rescue-nilai/' . $id . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus periode ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
