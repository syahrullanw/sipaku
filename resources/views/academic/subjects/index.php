<?php
    $canManageSubjects = (bool) ($canManageSubjects ?? false);
    $canImportSubjects = (bool) ($canImportSubjects ?? false);
    $viewContext = $viewContext ?? 'admin';
    $contextMessage = $contextMessage ?? null;
    $classroomInfo = is_array($classroomInfo ?? null) ? $classroomInfo : null;
    $selectedYearId = (int) ($selectedYearId ?? 0);
    $yearOptions = $yearOptions ?? [];
    $selectedYearLabel = $selectedYearLabel ?? ($yearOptions[$selectedYearId] ?? null);
    $majorTypes = $majorRequiredTypes ?? [];
    $majorVisibleTypes = ['C1', 'C2', 'C3'];
    $isEditing = $canManageSubjects && isset($editingSubject) && $editingSubject !== null;
    $selectedGroup = (string) old('jenis', $editingSubject['jenis'] ?? '');
    $selectedMajor = (int) old('jurusan_id', $editingSubject['jurusan_id'] ?? 0);
    $yearOptions = $yearOptions ?? [];
    $selectedYear = $selectedYearId;
    $selectedYearForForm = (int) old('tahun_ajaran_id', $editingSubject['tahun_ajaran_id'] ?? $selectedYear);
    $activeYear = $activeYear ?? null;
    $activeYearLabel = null;
    $activeYearId = (int) ($activeYear['id'] ?? 0);
    if ($activeYear !== null) {
        $activeYearLabel = sprintf('%s - %s', $activeYear['nama'], (int) ($activeYear['semester_aktif'] ?? 1) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
    }
    if (!$isEditing && $activeYearId > 0) {
        $selectedYearForForm = $activeYearId;
    }
    $dataYearLabel = $yearOptions[$selectedYearForForm] ?? $activeYearLabel;
    $disableForm = $activeYear === null && !$isEditing;
    $showYearFilter = $canManageSubjects || (!empty($yearOptions) && count($yearOptions) > 1);
?>

<div class="grid gap-6 lg:grid-cols-12">
    <?php if ($canManageSubjects): ?>
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('akademik/mata-pelajaran/' . $editingSubject['id'] . '/update') : base_url('akademik/mata-pelajaran'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    <?php if ($dataYearLabel !== null): ?>
                        Data tersimpan pada: <span class="font-semibold text-slate-700"><?= htmlspecialchars($dataYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="font-semibold text-rose-600">Belum ada tahun ajaran aktif. Silakan aktifkan tahun ajaran terlebih dahulu.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="kode" class="block text-sm font-medium text-slate-600">Kode</label>
                    <input
                        type="text"
                        id="kode"
                        name="kode"
                        value="<?= htmlspecialchars((string) old('kode', $editingSubject['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm uppercase tracking-wide focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="MP001"
                    />
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama Mata Pelajaran</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingSubject['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Matematika"
                    />
                </div>
                <div>
                    <label for="jenis" class="block text-sm font-medium text-slate-600">Jenis</label>
                    <select
                        id="jenis"
                        name="jenis"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <option value="">-- Pilih jenis mata pelajaran --</option>
                        <?php foreach ($groupOptions as $code => $label): ?>
                            <option value="<?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedGroup === (string) $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div data-subject-major-field class="<?= in_array($selectedGroup, $majorVisibleTypes, true) ? '' : 'hidden' ?>">
                    <label for="jurusan_id" class="block text-sm font-medium text-slate-600">Jurusan</label>
                    <select
                        id="jurusan_id"
                        name="jurusan_id"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= in_array($selectedGroup, ['C2', 'C3'], true) ? 'required' : '' ?>
                    >
                        <option value="">-- Pilih jurusan --</option>
                        <?php foreach ($majorOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedMajor === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-2 text-xs text-slate-400">Wajib diisi untuk mata pelajaran C2 atau C3 (Kompetensi Keahlian).</p>
                </div>
                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-slate-600">Deskripsi (Opsional)</label>
                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="3"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    ><?= htmlspecialchars((string) old('deskripsi', $editingSubject['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60" <?= $disableForm ? 'disabled' : '' ?> >
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('akademik/mata-pelajaran?tahun_ajaran_id=' . urlencode((string) $selectedYearForForm)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
    <?php else: ?>
    <div class="lg:col-span-12">
    <?php endif; ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-6 space-y-6">
                <div class="space-y-3">
                    <h2 class="text-base font-semibold text-slate-800">Daftar Mata Pelajaran</h2>
                    <?php if ($contextMessage !== null): ?>
                        <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($contextMessage, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($classroomInfo !== null && isset($classroomInfo['name'])): ?>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Kelas: <span class="text-slate-600"><?= htmlspecialchars($classroomInfo['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($classroomInfo['major'])): ?>
                                · Jurusan: <span class="text-slate-600"><?= htmlspecialchars($classroomInfo['major'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($selectedYearLabel !== null): ?>
                        <p class="mt-1 text-xs text-slate-400">Tahun Ajaran: <span class="font-semibold text-slate-600"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col gap-4">
                    <?php if ($showYearFilter): ?>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400" for="filter-tahun-ajaran">Tahun Ajaran</label>
                            <select
                                id="filter-tahun-ajaran"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                data-subject-year-filter
                            >
                                <?php foreach ($yearOptions as $id => $name): ?>
                                    <option value="<?= $id ?>" <?= $selectedYear === (int) $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400" for="subject-quick-search">Pencarian Cepat</label>
                        <div class="relative">
                            <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                            <input
                                type="search"
                                id="subject-quick-search"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Cari kode, nama, atau jurusan..."
                                autocomplete="off"
                                data-subject-table-search
                                aria-label="Cari cepat mata pelajaran"
                            />
                        </div>
                    </div>
                    <?php if ($canImportSubjects): ?>
                        <form
                            action="<?= htmlspecialchars(base_url('akademik/mata-pelajaran/import'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            enctype="multipart/form-data"
                            class="flex flex-col gap-3"
                        >
                            <?= csrf_field() ?>
                            <input
                                type="file"
                                name="import_file"
                                accept=".xls,.xlsx"
                                required
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500"
                            />
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                <i class="ri-upload-cloud-line text-base"></i>
                                <span>Import</span>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canImportSubjects): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
                            <p>Import XLS/XLSX dengan kolom:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><code class="font-mono text-[11px]">kode</code>, <code class="font-mono text-[11px]">nama</code>, <code class="font-mono text-[11px]">jenis</code></li>
                                <li><code class="font-mono text-[11px]">jurusan</code> (opsional), <code class="font-mono text-[11px]">deskripsi</code> (opsional)</li>
                            </ul>
                            <p class="mt-2">Nilai kolom <code class="font-mono text-[11px]">jenis</code> dapat berupa kode atau label berikut:</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($groupOptions as $code => $label): ?>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600">
                                        <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>
                                        <span class="ml-1 text-slate-400">· <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-3">Untuk kolom <code class="font-mono text-[11px]">jurusan</code>, gunakan nama jurusan persis seperti master jurusan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Jenis</th>
                            <th class="px-6 py-4">Tahun Ajaran</th>
                            <th class="px-6 py-4">Semester</th>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Jurusan</th>
                            <th class="px-6 py-4">Deskripsi</th>
                            <?php if ($canManageSubjects): ?>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white" data-subject-table-body>
                        <?php foreach ($subjects as $subject): ?>
                            <tr data-subject-row>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($subject['jenis_label'] ?? $subject['jenis'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($subject['tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($subject['tahun_ajaran_semester_label'] ?? 'Semester 1 (Ganjil)', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-700">
                                    <?= htmlspecialchars($subject['kode'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?= htmlspecialchars($subject['nama'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php if (!empty($subject['jurusan_nama'])): ?>
                                        <?= htmlspecialchars($subject['jurusan_nama'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php if ($subject['deskripsi'] !== null && $subject['deskripsi'] !== ''): ?>
                                        <?= nl2br(htmlspecialchars($subject['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canManageSubjects): ?>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="<?= htmlspecialchars(base_url('akademik/mata-pelajaran?edit=' . urlencode((string) $subject['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                            <form action="<?= htmlspecialchars(base_url('akademik/mata-pelajaran/' . $subject['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus mata pelajaran ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="<?= $canManageSubjects ? 8 : 7 ?>" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data mata pelajaran.</td>
                            </tr>
                        <?php else: ?>
                            <tr data-subject-empty-state class="hidden">
                                <td colspan="<?= $canManageSubjects ? 8 : 7 ?>" class="px-6 py-8 text-center text-sm text-slate-400">Tidak ada mata pelajaran yang cocok dengan pencarian.</td>
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
        const yearFilter = document.querySelector('[data-subject-year-filter]');
        if (yearFilter) {
            yearFilter.addEventListener('change', () => {
                const value = yearFilter.value;
                const baseUrl = '<?= htmlspecialchars(base_url('akademik/mata-pelajaran'), ENT_QUOTES, 'UTF-8') ?>';
                const target = value ? `${baseUrl}?tahun_ajaran_id=${encodeURIComponent(value)}` : baseUrl;
                window.location.href = target;
            });
        }

        const searchInput = document.querySelector('[data-subject-table-search]');
        const subjectTableBody = document.querySelector('[data-subject-table-body]');
        if (searchInput && subjectTableBody) {
            const subjectRows = Array.from(subjectTableBody.querySelectorAll('[data-subject-row]'));
            const emptyStateRow = subjectTableBody.querySelector('[data-subject-empty-state]');
            const normalize = (value) => value.toLowerCase().replace(/\s+/g, ' ').trim();

            subjectRows.forEach((row) => {
                row.dataset.searchText = normalize(row.textContent || '');
            });

            const applySearch = () => {
                const query = normalize(searchInput.value || '');
                let visibleCount = 0;

                subjectRows.forEach((row) => {
                    const text = row.dataset.searchText || '';
                    const matches = query === '' || text.includes(query);
                    row.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (emptyStateRow) {
                    emptyStateRow.classList.toggle('hidden', visibleCount !== 0);
                }
            };

            const handleSearch = () => window.requestAnimationFrame(applySearch);

            searchInput.addEventListener('input', handleSearch);
            searchInput.addEventListener('search', handleSearch);
            applySearch();
        }

        const jenisSelect = document.getElementById('jenis');
        const jurusanWrapper = document.querySelector('[data-subject-major-field]');
        const jurusanSelect = document.getElementById('jurusan_id');
        if (!jenisSelect || !jurusanWrapper || !jurusanSelect) {
            return;
        }

        const requiredTypes = ['C2', 'C3'];
        const visibleTypes = ['C1', 'C2', 'C3'];

        const toggleJurusanField = () => {
            const currentType = jenisSelect.value;
            const shouldShow = visibleTypes.includes(currentType);
            const needsMajor = requiredTypes.includes(currentType);

            jurusanWrapper.classList.toggle('hidden', !shouldShow);
            jurusanSelect.required = needsMajor;
            if (!shouldShow) {
                jurusanSelect.value = '';
            }
        };

        toggleJurusanField();
        jenisSelect.addEventListener('change', toggleJurusanField);
    });
</script>
