<?php
    $items = array_values(is_array($items ?? null) ? $items : []);
    $filters = is_array($filters ?? null) ? $filters : [];
    $totals = is_array($totals ?? null) ? $totals : [];
    $formatSize = static function (int|float $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
    };
    $labels = [
        'data-siswa' => 'Data Siswa',
        'foto-siswa' => 'Foto Siswa',
        'dokumen-fisik' => 'Dokumen Fisik',
        'akademik' => 'Akademik',
        'keuangan' => 'Keuangan',
        'persuratan' => 'Persuratan',
        'profil-sekolah' => 'Profil Sekolah',
        'dokumen-lainnya' => 'Dokumen Lainnya',
    ];
    $label = static fn (string $value): string => $labels[$value] ?? ucwords(str_replace('-', ' ', $value));
    $categoryOptions = [];
    $periodOptions = [];
    $subcategoryOptions = [];
    foreach ($items as $item) {
        $categoryOptions[(string) ($item['category'] ?? '')] = true;
        $periodOptions[(string) ($item['school_period'] ?? '')] = true;
        $subcategoryOptions[(string) ($item['subcategory'] ?? '')] = true;
    }
    ksort($categoryOptions);
    ksort($periodOptions);
    ksort($subcategoryOptions);
?>

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total File Terindeks</p>
            <p class="mt-2 text-3xl font-bold text-slate-800"><?= number_format((int) ($totals['total_files'] ?? count($items))) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kapasitas Terpakai</p>
            <p class="mt-2 text-3xl font-bold text-slate-800"><?= htmlspecialchars($formatSize((int) ($totals['total_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Struktur Baru</p>
            <p class="mt-2 text-sm font-semibold text-slate-700">uploads/arsip/tahun-semester/kategori/subkategori</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="get" action="<?= htmlspecialchars(base_url('admin/file-manager'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="q" class="block text-sm font-medium text-slate-600">Cari File</label>
                <input id="q" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="nama file, kategori, path" />
            </div>
            <div>
                <label for="school_period" class="block text-sm font-medium text-slate-600">Periode</label>
                <select id="school_period" name="school_period" class="mt-2 w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Semua</option>
                    <?php foreach (array_keys($periodOptions) as $value): ?>
                        <?php if ($value === '') continue; ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['school_period'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="category" class="block text-sm font-medium text-slate-600">Kategori</label>
                <select id="category" name="category" class="mt-2 w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Semua</option>
                    <?php foreach (array_keys($categoryOptions) as $value): ?>
                        <?php if ($value === '') continue; ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['category'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label($value), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="file_type" class="block text-sm font-medium text-slate-600">Tipe</label>
                <select id="file_type" name="file_type" class="mt-2 w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Semua</option>
                    <?php foreach (['image' => 'Foto', 'pdf' => 'PDF', 'excel' => 'Excel', 'word' => 'Word', 'archive' => 'Arsip', 'other' => 'Lainnya'] as $value => $text): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['file_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500" type="submit">
                    <i class="ri-search-line"></i>
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-semibold text-slate-800">Daftar File</h2>
            <p class="mt-1 text-sm text-slate-500">File baru otomatis masuk struktur per tahun ajaran dan semester. File lama tetap ditampilkan dari hasil scan folder.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">File</th>
                        <th class="px-6 py-3">Periode</th>
                        <th class="px-6 py-3">Kategori</th>
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3">Ukuran</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">Tidak ada file sesuai filter.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $id = (int) ($item['id'] ?? 0);
                            $type = (string) ($item['file_type'] ?? 'other');
                            $icon = match ($type) {
                                'image' => 'ri-image-line',
                                'pdf' => 'ri-file-pdf-2-line',
                                'excel' => 'ri-file-excel-2-line',
                                'word' => 'ri-file-word-2-line',
                                'archive' => 'ri-folder-zip-line',
                                default => 'ri-file-line',
                            };
                        ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> text-lg"></i></span>
                                    <div>
                                        <p class="font-medium text-slate-800"><?= htmlspecialchars((string) ($item['original_name'] ?: $item['stored_name']), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="mt-1 max-w-xl truncate font-mono text-xs text-slate-400"><?= htmlspecialchars((string) ($item['relative_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars((string) ($item['school_period'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-slate-600">
                                <?= htmlspecialchars($label((string) ($item['category'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($label((string) ($item['subcategory'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars(strtoupper($type), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($formatSize((int) ($item['size_bytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($id > 0): ?>
                                    <a href="<?= htmlspecialchars(base_url('admin/file-manager/' . $id . '/download'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        <i class="ri-download-2-line"></i>
                                        Unduh
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
