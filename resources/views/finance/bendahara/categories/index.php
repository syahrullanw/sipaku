<?php
/** @var array<int, array<string, mixed>> $categories */
/** @var array<string, mixed>|null $editingCategory */

$editingCategory = $editingCategory ?? null;
$editingCategoryId = $editingCategory !== null ? (int) ($editingCategory['id'] ?? 0) : 0;

$typeLabel = static fn (string $type): string => $type === 'insidental' ? 'Insidental' : 'Rutin';
$typeBadgeTone = static function (string $type): string {
    return $type === 'insidental'
        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200'
        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200';
};
$statusLabel = static fn (string $status): string => $status === 'nonaktif' ? 'Nonaktif' : 'Aktif';
$statusBadgeTone = static function (string $status): string {
    return $status === 'nonaktif'
        ? 'bg-slate-200 text-slate-600 dark:bg-slate-800/50 dark:text-slate-300'
        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200';
};
$orderDisplay = static function (mixed $order): string {
    if ($order === null || $order === '') {
        return '—';
    }

    return number_format((int) $order, 0, ',', '.');
};

$oldFormContext = (string) old('__form', '');
$oldEditId = (int) old('__edit_id', 0);

$createCodeValue = $oldFormContext === 'create' ? (string) old('kode', '') : '';
$createNameValue = $oldFormContext === 'create' ? (string) old('nama', '') : '';
$createTypeValue = $oldFormContext === 'create' ? (string) old('tipe', 'rutin') : 'rutin';
$createStatusValue = $oldFormContext === 'create' ? (string) old('status', 'aktif') : 'aktif';
$createOrderValue = $oldFormContext === 'create' ? (string) old('urutan', '') : '';

$allowedTypes = ['rutin', 'insidental'];
if (!in_array($createTypeValue, $allowedTypes, true)) {
    $createTypeValue = 'rutin';
}

$allowedStatus = ['aktif', 'nonaktif'];
if (!in_array($createStatusValue, $allowedStatus, true)) {
    $createStatusValue = 'aktif';
}

$editNameValue = $editingCategory !== null ? (string) ($editingCategory['nama'] ?? '') : '';
$editTypeValue = $editingCategory !== null ? (string) ($editingCategory['tipe'] ?? 'rutin') : 'rutin';
$editStatusValue = $editingCategory !== null ? (string) ($editingCategory['status'] ?? 'aktif') : 'aktif';
$editOrderValue = $editingCategory !== null ? (string) ($editingCategory['urutan'] ?? '') : '';

if ($editingCategory !== null) {
    if (!in_array($editTypeValue, $allowedTypes, true)) {
        $editTypeValue = 'rutin';
    }

    if (!in_array($editStatusValue, $allowedStatus, true)) {
        $editStatusValue = 'aktif';
    }

    if ($oldFormContext === 'update' && $oldEditId === $editingCategoryId) {
        $editNameValue = (string) old('nama', $editNameValue);
        $editTypeValue = (string) old('tipe', $editTypeValue);
        $editStatusValue = (string) old('status', $editStatusValue);
        $editOrderValue = (string) old('urutan', $editOrderValue);

        if (!in_array($editTypeValue, $allowedTypes, true)) {
            $editTypeValue = 'rutin';
        }
        if (!in_array($editStatusValue, $allowedStatus, true)) {
            $editStatusValue = 'aktif';
        }
    }
}
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Kelola Kategori Tagihan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Atur daftar kategori yang digunakan saat membuat tagihan siswa maupun guru.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/tagihan'), ENT_QUOTES, 'UTF-8') ?>"
           class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            &larr; Kembali ke Tagihan
        </a>
        <a href="#form-kategori"
           class="inline-flex items-center rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900">
            <i class="ri-add-line mr-1 text-base"></i>
            Tambah Kategori
        </a>
    </div>
</div>

<div id="form-kategori" class="mb-6 rounded-xl border border-slate-200/70 bg-white/90 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/70 dark:shadow-none">
    <div class="border-b border-slate-200/70 px-6 py-4 dark:border-slate-700/60">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Tambah Kategori Tagihan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Gunakan kode singkat yang mudah diingat, misalnya <span class="font-medium text-slate-700 dark:text-slate-200">SPP</span> atau <span class="font-medium text-slate-700 dark:text-slate-200">PRAKERIN</span>.</p>
    </div>
    <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="px-6 py-5">
        <?= csrf_field() ?>
        <div class="grid gap-4 md:grid-cols-5">
            <div>
                <label for="create-kode" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Kode</label>
                <input
                    type="text"
                    id="create-kode"
                    name="kode"
                    value="<?= htmlspecialchars($createCodeValue, ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="25"
                    placeholder="cth. SPP"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm uppercase tracking-wide text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    required
                >
            </div>
            <div class="md:col-span-2">
                <label for="create-nama" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nama Kategori</label>
                <input
                    type="text"
                    id="create-nama"
                    name="nama"
                    value="<?= htmlspecialchars($createNameValue, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="cth. Iuran Komite Sekolah"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    required
                >
            </div>
            <div>
                <label for="create-tipe" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tipe</label>
                <select
                    id="create-tipe"
                    name="tipe"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <option value="rutin" <?= $createTypeValue === 'rutin' ? 'selected' : '' ?>>Rutin</option>
                    <option value="insidental" <?= $createTypeValue === 'insidental' ? 'selected' : '' ?>>Insidental</option>
                </select>
            </div>
            <div>
                <label for="create-status" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Status</label>
                <select
                    id="create-status"
                    name="status"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <option value="aktif" <?= $createStatusValue === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $createStatusValue === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div>
                <label for="create-urutan" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Urutan</label>
                <input
                    type="number"
                    id="create-urutan"
                    name="urutan"
                    value="<?= htmlspecialchars($createOrderValue, ENT_QUOTES, 'UTF-8') ?>"
                    min="0"
                    placeholder="cth. 1"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                <p class="mt-1 text-xs text-slate-400">Kosongkan bila tidak perlu pengurutan khusus.</p>
            </div>
        </div>
        <div class="mt-5 flex justify-end">
            <button type="submit" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900">
                Simpan Kategori
            </button>
        </div>
    </form>
</div>

<?php if ($editingCategory !== null): ?>
    <div id="edit-kategori" class="mb-6 rounded-xl border border-sky-200/70 bg-sky-50/70 shadow-sm shadow-slate-100 dark:border-sky-500/40 dark:bg-sky-950/40 dark:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sky-200/70 px-6 py-4 dark:border-sky-500/30">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Edit Kategori</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Mengubah kategori <span class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string) ($editingCategory['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    (kode: <span class="font-mono text-xs uppercase text-slate-600 dark:text-slate-300"><?= htmlspecialchars((string) ($editingCategory['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>).
                </p>
            </div>
            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori'), ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center rounded-lg border border-sky-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-sky-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:border-sky-500/40 dark:bg-slate-900 dark:text-sky-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                Batal
            </a>
        </div>
        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori/' . $editingCategoryId), ENT_QUOTES, 'UTF-8') ?>" method="post" class="px-6 py-5">
            <?= csrf_field() ?>
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Kode</label>
                    <div class="flex h-10 items-center rounded-lg border border-transparent bg-white px-3 text-xs font-semibold uppercase tracking-wide text-slate-600 shadow-inner dark:bg-slate-900 dark:text-slate-300">
                        <?= htmlspecialchars((string) ($editingCategory['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Kode bersifat permanen untuk menjaga konsistensi data.</p>
                </div>
                <div class="md:col-span-2">
                    <label for="edit-nama" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nama Kategori</label>
                    <input
                        type="text"
                        id="edit-nama"
                        name="nama"
                        value="<?= htmlspecialchars($editNameValue, ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        required
                    >
                </div>
                <div>
                    <label for="edit-tipe" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tipe</label>
                    <select
                        id="edit-tipe"
                        name="tipe"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <option value="rutin" <?= $editTypeValue === 'rutin' ? 'selected' : '' ?>>Rutin</option>
                        <option value="insidental" <?= $editTypeValue === 'insidental' ? 'selected' : '' ?>>Insidental</option>
                    </select>
                </div>
                <div>
                    <label for="edit-status" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Status</label>
                    <select
                        id="edit-status"
                        name="status"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <option value="aktif" <?= $editStatusValue === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $editStatusValue === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div>
                    <label for="edit-urutan" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Urutan</label>
                    <input
                        type="number"
                        id="edit-urutan"
                        name="urutan"
                        value="<?= htmlspecialchars($editOrderValue, ENT_QUOTES, 'UTF-8') ?>"
                        min="0"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:bg-sky-500 dark:hover:bg-sky-600 dark:focus:ring-offset-slate-900">
                    Perbarui Kategori
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="rounded-xl border border-slate-200/70 bg-white/90 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/70 dark:shadow-none">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/70 px-6 py-4 dark:border-slate-700/60">
        <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Daftar Kategori</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Gunakan aksi edit atau hapus pada setiap baris untuk memodifikasi data.</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
            <?= htmlspecialchars((string) count($categories), ENT_QUOTES, 'UTF-8') ?> kategori
        </span>
    </div>
    <div class="overflow-x-auto">
        <?php if (empty($categories)): ?>
            <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                Belum ada kategori tagihan. Silakan tambah kategori baru terlebih dahulu.
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">Kategori</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Tipe</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-3 py-3 font-semibold text-right">Urutan</th>
                        <th scope="col" class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($categories as $category): ?>
                        <?php
                            if (!is_array($category)) {
                                continue;
                            }

                            $categoryId = (int) ($category['id'] ?? 0);
                            $isEditingRow = $editingCategoryId > 0 && $categoryId === $editingCategoryId;
                            $type = (string) ($category['tipe'] ?? 'rutin');
                            $status = (string) ($category['status'] ?? 'aktif');
                            $updatedAtLabel = null;
                            $updatedAtRaw = (string) ($category['updated_at'] ?? '');
                            if ($updatedAtRaw !== '') {
                                $parsed = strtotime($updatedAtRaw);
                                if ($parsed !== false) {
                                    $updatedAtLabel = date('d M Y H:i', $parsed);
                                }
                            }
                        ?>
                        <tr class="<?= $isEditingRow ? 'bg-sky-50/70 dark:bg-sky-900/40' : 'bg-transparent' ?>">
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-semibold text-slate-800 dark:text-white">
                                    <?= htmlspecialchars((string) ($category['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="mt-1 text-xs font-mono uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    <?= htmlspecialchars((string) ($category['kode'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <?php if ($updatedAtLabel !== null): ?>
                                    <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                                        Diperbarui <?= htmlspecialchars($updatedAtLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 align-top">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $typeBadgeTone($type) ?>">
                                    <?= htmlspecialchars($typeLabel($type), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 align-top">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $statusBadgeTone($status) ?>">
                                    <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-3 py-4 text-right align-top text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <?= htmlspecialchars($orderDisplay($category['urutan'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori?edit=' . $categoryId), ENT_QUOTES, 'UTF-8') ?>"
                                       class="inline-flex items-center rounded-lg border border-sky-200 bg-white px-3 py-1.5 text-xs font-semibold text-sky-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:border-sky-500/40 dark:bg-slate-900 dark:text-sky-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                                        <i class="ri-pencil-line mr-1 text-sm"></i>
                                        Edit
                                    </a>
                                    <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/kategori/' . $categoryId . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus kategori ini? Tindakan tidak dapat dibatalkan.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 shadow-sm hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:border-rose-500/40 dark:bg-slate-900 dark:text-rose-200 dark:hover:bg-rose-500/10 dark:focus:ring-offset-slate-900">
                                            <i class="ri-delete-bin-line mr-1 text-sm"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
