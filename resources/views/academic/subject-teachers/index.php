<?php
    $isEditing = isset($editingAssignment) && $editingAssignment !== null;
    $selectedSubject = (int) old('mata_pelajaran_id', $editingAssignment['mata_pelajaran_id'] ?? 0);
    $selectedSubjectIds = $selectedSubjectIds ?? [];
    if (!is_array($selectedSubjectIds)) {
        $selectedSubjectIds = [$selectedSubjectIds];
    }
    $selectedSubjectIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int) $value, $selectedSubjectIds), static fn (int $id): bool => $id > 0)));
    if ($isEditing && empty($selectedSubjectIds) && $selectedSubject > 0) {
        $selectedSubjectIds = [$selectedSubject];
    }
    $selectedTeacher = (int) old('guru_id', $editingAssignment['guru_id'] ?? 0);
    $classList = $classList ?? [];
    $selectedClassIds = isset($selectedClassIds) && is_array($selectedClassIds)
        ? array_values(array_filter(array_map(static fn ($value): int => (int) $value, $selectedClassIds), static fn (int $id): bool => $id > 0))
        : [];
    $subjectMeta = $subjectMeta ?? [];
    $yearOptions = $yearOptions ?? [];
    $selectedYear = (int) ($selectedYearId ?? 0);
    $selectedYearForForm = (int) old('tahun_ajaran_id', $selectedYear);
    $formYearLabel = $yearOptions[$selectedYearForForm] ?? null;
    $disableAssignmentForm = ($formYearLabel === null) || empty($subjectOptions);
    $importDisabledReason = null;
    $canImportAssignments = true;

    if ($selectedYearForForm <= 0) {
        $importDisabledReason = 'Pilih tahun ajaran terlebih dahulu sebelum melakukan import.';
        $canImportAssignments = false;
    } elseif (empty($subjectOptions)) {
        $importDisabledReason = 'Belum ada mata pelajaran pada tahun ajaran ini. Tambahkan data mata pelajaran terlebih dahulu.';
        $canImportAssignments = false;
    } elseif (empty($teacherOptions)) {
        $importDisabledReason = 'Belum ada data guru aktif. Tambahkan data guru terlebih dahulu.';
        $canImportAssignments = false;
    } elseif (empty($classList)) {
        $importDisabledReason = 'Belum ada kelas pada tahun ajaran ini. Tambahkan data kelas terlebih dahulu.';
        $canImportAssignments = false;
    }
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Guru Pengampu' : 'Tambah Guru Pengampu' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('akademik/guru-pengampu/' . $editingAssignment['id'] . '/update') : base_url('akademik/guru-pengampu'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearForForm, ENT_QUOTES, 'UTF-8') ?>" />
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    <?php if ($formYearLabel !== null): ?>
                        Tahun ajaran terpilih: <span class="font-semibold text-slate-700"><?= htmlspecialchars($formYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="font-semibold text-rose-600">Tahun ajaran belum tersedia. Tambahkan atau aktifkan tahun ajaran terlebih dahulu.</span>
                    <?php endif; ?>
                </div>
                <?php if ($isEditing): ?>
                    <div>
                        <label for="mata_pelajaran_id" class="block text-sm font-medium text-slate-600">Mata Pelajaran</label>
                        <select
                            id="mata_pelajaran_id"
                            name="mata_pelajaran_id"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            <?= $disableAssignmentForm ? 'disabled' : '' ?>
                        >
                            <option value="">-- Pilih mata pelajaran --</option>
                            <?php foreach ($subjectOptions as $id => $label): ?>
                                <option value="<?= $id ?>" <?= $selectedSubject === (int) $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($subjectOptions)): ?>
                            <p class="mt-2 text-xs text-slate-400">Belum ada mata pelajaran pada tahun ajaran ini.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <span class="block text-sm font-medium text-slate-600">Mata Pelajaran</span>
                        <?php if (empty($subjectOptions)): ?>
                            <p class="mt-2 text-xs text-rose-600">Belum ada mata pelajaran pada tahun ajaran ini.</p>
                        <?php else: ?>
                            <div class="mt-3 space-y-3" data-subject-multi>
                                <div class="relative">
                                    <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                                    <input
                                        type="search"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        placeholder="Cari mata pelajaran..."
                                        autocomplete="off"
                                        data-assignment-subject-search
                                        <?= $disableAssignmentForm ? 'disabled' : '' ?>
                                    />
                                </div>
                                <div class="max-h-64 space-y-2 overflow-y-auto" data-subject-checkboxes>
                                    <?php foreach ($subjectOptions as $id => $label): ?>
                                        <?php $isChecked = in_array((int) $id, $selectedSubjectIds, true); ?>
                                        <label
                                            class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 transition hover:border-indigo-300"
                                            data-subject-option
                                            data-filter-text="<?= htmlspecialchars(strtolower($label), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <input
                                                type="checkbox"
                                                name="mata_pelajaran_ids[]"
                                                value="<?= $id ?>"
                                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                <?= $isChecked ? 'checked' : '' ?>
                                                <?= $disableAssignmentForm ? 'disabled' : '' ?>
                                            />
                                            <span class="leading-snug text-slate-700">
                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-xs text-slate-400" data-subject-helper>Pilih satu atau beberapa mata pelajaran yang akan diampu oleh guru ini.</p>
                                <p class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-600" data-subject-empty>Pencarian tidak menemukan mata pelajaran.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="guru_id" class="block text-sm font-medium text-slate-600">Guru Pengampu</label>
                    <select
                        id="guru_id"
                        name="guru_id"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $disableAssignmentForm ? 'disabled' : '' ?>
                    >
                        <option value="">-- Pilih guru --</option>
                        <?php foreach ($teacherOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedTeacher === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <span class="block text-sm font-medium text-slate-600">Kelas Diampu</span>
                    <?php if (empty($classList)): ?>
                        <p class="mt-2 text-xs text-rose-600">Belum ada data kelas pada tahun ajaran ini. Tambahkan kelas terlebih dahulu.</p>
                    <?php else: ?>
                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2" data-class-checkboxes>
                            <?php foreach ($classList as $class): ?>
                                <?php
                                    $classId = (int) ($class['id'] ?? 0);
                                    if ($classId <= 0) {
                                        continue;
                                    }
                                    $classLabel = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
                                    $classMajorId = isset($class['jurusan_id']) ? (int) $class['jurusan_id'] : null;
                                    $isChecked = in_array($classId, $selectedClassIds, true);
                                ?>
                                <label
                                    class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 transition hover:border-indigo-300"
                                    data-class-option
                                    data-major-id="<?= htmlspecialchars($classMajorId !== null ? (string) $classMajorId : '', ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        name="kelas_ids[]"
                                        value="<?= $classId ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                        <?= $disableAssignmentForm ? 'disabled' : '' ?>
                                    />
                                    <span class="flex flex-col">
                                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($class['jurusan_nama'])): ?>
                                            <span class="text-xs text-slate-400"><?= htmlspecialchars($class['jurusan_nama'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-2 text-xs text-slate-400" data-class-helper>Anda dapat memilih lebih dari satu kelas.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="catatan" class="block text-sm font-medium text-slate-600">Catatan (Opsional)</label>
                    <textarea
                        id="catatan"
                        name="catatan"
                        rows="3"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    ><?= htmlspecialchars((string) old('catatan', $editingAssignment['catatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60" <?= $disableAssignmentForm ? 'disabled' : '' ?> >
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('akademik/guru-pengampu?tahun_ajaran_id=' . urlencode((string) $selectedYearForForm)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="mt-6 rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">Import Guru Pengampu</h3>
            <?php if ($importDisabledReason !== null): ?>
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700"><?= htmlspecialchars($importDisabledReason, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form
                action="<?= htmlspecialchars(base_url('akademik/guru-pengampu/import'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                enctype="multipart/form-data"
                class="mt-4 space-y-3"
            >
                <?= csrf_field() ?>
                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearForForm, ENT_QUOTES, 'UTF-8') ?>">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Berkas Excel</label>
                <input
                    type="file"
                    name="import_file"
                    accept=".xls,.xlsx"
                    required
                    <?= $canImportAssignments ? '' : 'disabled' ?>
                    class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                />
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    <?= $canImportAssignments ? '' : 'disabled' ?>
                >
                    <i class="ri-upload-cloud-line text-base"></i>
                    <span>Import</span>
                </button>
            </form>
            <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
                <p>Gunakan format XLS/XLSX dengan kolom minimal:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li><code class="font-mono text-[11px]">mata_pelajaran_kode</code> &mdash; sesuai kode mapel pada tahun ajaran ini</li>
                    <li><code class="font-mono text-[11px]">guru_nip</code> atau <code class="font-mono text-[11px]">guru_email</code></li>
                    <li><code class="font-mono text-[11px]">kelas</code> &mdash; pisahkan lebih dari satu kelas dengan koma</li>
                </ul>
                <p class="mt-2">Opsional: <code class="font-mono text-[11px]">catatan</code> untuk menyimpan keterangan tambahan.</p>
                <p class="mt-2">Pastikan nama kelas ditulis sama persis seperti di master kelas (contoh: <code class="font-mono text-[11px]">XI AKL 1</code>).</p>
                <?php if (!$canImportAssignments): ?>
                    <p class="mt-3 text-[11px] font-semibold text-amber-600">Fitur import otomatis aktif begitu semua data pendukung tersedia.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h2 class="text-base font-semibold text-slate-800">Daftar Guru Pengampu</h2>
                <div class="flex items-center gap-2">
                    <label for="filter-assignment-year" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahun Ajaran</label>
                    <select
                        id="filter-assignment-year"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        data-assignment-year-filter
                    >
                        <?php foreach ($yearOptions as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedYear === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Mata Pelajaran</th>
                            <th class="px-6 py-4">Guru</th>
                            <th class="px-6 py-4">Kelas</th>
                            <th class="px-6 py-4">Catatan</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($assignments as $assignment): ?>
                            <?php $jenisLabel = $groupOptions[$assignment['mata_pelajaran_jenis']] ?? $assignment['mata_pelajaran_jenis']; ?>
                            <tr>
                                <td class="px-6 py-4 text-slate-600">
                                    <p class="font-semibold text-slate-700"><?= htmlspecialchars($assignment['mata_pelajaran_kode'] . ' - ' . $assignment['mata_pelajaran_nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($jenisLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php
                                        $semesterValue = (int) ($assignment['mata_pelajaran_tahun_ajaran_semester'] ?? 1);
                                        $semesterLabel = $semesterValue === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                                    ?>
                                    <p class="text-xs text-slate-400">
                                        TA: <?= htmlspecialchars($assignment['mata_pelajaran_tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <?php if (!empty($assignment['mata_pelajaran_jurusan_nama'])): ?>
                                        <p class="text-xs text-slate-400">Jurusan: <?= htmlspecialchars($assignment['mata_pelajaran_jurusan_nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <p class="font-semibold text-slate-700"><?= htmlspecialchars($assignment['guru_nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($assignment['guru_nip'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?php $assignmentClasses = $assignment['classes'] ?? []; ?>
                                    <?php if (!empty($assignmentClasses)): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($assignmentClasses as $class): ?>
                                                <?php
                                                    $label = trim((string) (($class['tingkat'] ?? '') . ' ' . ($class['nama'] ?? '')));
                                                    if ($label === '') {
                                                        $label = 'Kelas';
                                                    }
                                                ?>
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs font-medium text-amber-500">Belum dipilih</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php if ($assignment['catatan'] !== null && $assignment['catatan'] !== ''): ?>
                                        <?= nl2br(htmlspecialchars($assignment['catatan'], ENT_QUOTES, 'UTF-8')) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars(base_url('akademik/guru-pengampu?edit=' . urlencode((string) $assignment['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <form action="<?= htmlspecialchars(base_url('akademik/guru-pengampu/' . $assignment['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus guru pengampu ini?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data guru pengampu.</td>
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
        const yearFilter = document.querySelector('[data-assignment-year-filter]');
        if (yearFilter) {
            yearFilter.addEventListener('change', () => {
                const value = yearFilter.value;
                const baseUrl = '<?= htmlspecialchars(base_url('akademik/guru-pengampu'), ENT_QUOTES, 'UTF-8') ?>';
                const target = value ? `${baseUrl}?tahun_ajaran_id=${encodeURIComponent(value)}` : baseUrl;
                window.location.href = target;
            });
        }

        const subjectMultiContainer = document.querySelector('[data-subject-multi]');
        if (subjectMultiContainer) {
            const subjectSearchInput = subjectMultiContainer.querySelector('[data-assignment-subject-search]');
            const subjectOptions = Array.from(subjectMultiContainer.querySelectorAll('[data-subject-option]'));
            const emptyMessage = subjectMultiContainer.querySelector('[data-subject-empty]');
            const helperMessage = subjectMultiContainer.querySelector('[data-subject-helper]');

            const filterSubjects = () => {
                const query = (subjectSearchInput?.value || '').toLowerCase().trim();
                let visible = 0;

                subjectOptions.forEach((option) => {
                    const haystack = (option.getAttribute('data-filter-text') || '').toLowerCase();
                    const show = query === '' || haystack.includes(query);
                    option.classList.toggle('hidden', !show);
                    if (show) {
                        visible += 1;
                    }
                });

                if (emptyMessage) {
                    emptyMessage.classList.toggle('hidden', visible !== 0);
                }

                if (helperMessage) {
                    helperMessage.classList.toggle('hidden', visible === 0);
                }
            };

            if (subjectSearchInput) {
                subjectSearchInput.addEventListener('input', filterSubjects);
                subjectSearchInput.addEventListener('search', filterSubjects);
            }

            filterSubjects();
        }

        const subjectMeta = <?= json_encode($subjectMeta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_FORCE_OBJECT) ?>;
        const subjectSelect = document.querySelector('#mata_pelajaran_id');
        const classContainer = document.querySelector('[data-class-checkboxes]');
        const classOptions = classContainer ? Array.from(classContainer.querySelectorAll('[data-class-option]')) : [];
        const classHelper = document.querySelector('[data-class-helper]');
        const isFormDisabled = <?= json_encode($disableAssignmentForm) ?>;

        if (classContainer && classOptions.length > 0 && subjectSelect) {
            const applyClassFilter = () => {
                const selectedSubject = subjectSelect.value || null;
                const meta = selectedSubject && Object.prototype.hasOwnProperty.call(subjectMeta, selectedSubject)
                    ? subjectMeta[selectedSubject]
                    : null;
                const targetMajor = meta && meta.jurusan_id !== null ? parseInt(meta.jurusan_id, 10) : null;

                let visibleCount = 0;

                classOptions.forEach((option) => {
                    const majorAttr = option.getAttribute('data-major-id');
                    const optionMajor = majorAttr && majorAttr !== '' ? parseInt(majorAttr, 10) : null;
                    const matches = targetMajor === null || optionMajor === targetMajor;

                    option.classList.toggle('hidden', !matches);

                    const checkbox = option.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.disabled = Boolean(isFormDisabled);
                        if (!matches) {
                            checkbox.checked = false;
                        }
                    }

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                if (classHelper) {
                    if (visibleCount === 0) {
                        classHelper.textContent = targetMajor === null
                            ? 'Tidak ada kelas yang tersedia.'
                            : 'Tidak ada kelas yang sesuai dengan jurusan mata pelajaran ini.';
                    } else if (targetMajor === null) {
                        classHelper.textContent = 'Anda dapat memilih lebih dari satu kelas.';
                    } else {
                        classHelper.textContent = 'Pilih satu atau beberapa kelas pada jurusan ini.';
                    }
                }
            };

            applyClassFilter();
            subjectSelect.addEventListener('change', applyClassFilter);
        } else if (classHelper) {
            classHelper.textContent = classOptions.length === 0
                ? 'Belum ada kelas yang dapat dipilih.'
                : 'Anda dapat memilih lebih dari satu kelas.';
        }
    });
</script>
