<?php
$isEditing = isset($editingPosition) && $editingPosition !== null;
$yearOptions = isset($yearOptions) && is_array($yearOptions) ? $yearOptions : [];
$selectedYearId = isset($selectedYearId) ? (int) $selectedYearId : 0;
$selectedYearLabel = $yearOptions[$selectedYearId] ?? null;
$teacherOptions = isset($teacherOptions) && is_array($teacherOptions) ? $teacherOptions : [];
$studentOptions = isset($studentOptions) && is_array($studentOptions) ? $studentOptions : [];
$teacherAssignments = isset($teacherAssignments) && is_array($teacherAssignments) ? $teacherAssignments : [];
$studentAssignments = isset($studentAssignments) && is_array($studentAssignments) ? $studentAssignments : [];
$majorOptions = isset($majorOptions) && is_array($majorOptions) ? $majorOptions : [];
$listBasePath = 'master/jabatan-akademik';
$baseQuery = $selectedYearId > 0 ? 'tahun_ajaran_id=' . urlencode((string) $selectedYearId) : '';
$listPathWithYear = $baseQuery !== '' ? $listBasePath . '?' . $baseQuery : $listBasePath;
$canAssignTeachers = $selectedYearId > 0 && !empty($teacherOptions);
$canAssignStudents = $selectedYearId > 0 && !empty($studentOptions);
$isSystemEditing = $isEditing && (int) ($editingPosition['is_system'] ?? 0) === 1;
$editingPositionCode = $isEditing ? ($editingPosition['level'] ?? null) : null;
$currentCategory = strtolower((string) old('kategori', $isEditing ? ($editingPosition['kategori'] ?? 'guru') : 'guru'));
if (!in_array($currentCategory, ['guru', 'siswa'], true)) {
    $currentCategory = 'guru';
}
$requiresMajorRaw = old('requires_major', $isEditing ? ((int) ($editingPosition['requires_major'] ?? 0)) : 0);
$currentRequiresMajor = in_array((string) $requiresMajorRaw, ['1', 'true', 'on'], true) || (int) $requiresMajorRaw === 1;
if ($currentCategory !== 'guru') {
    $currentRequiresMajor = false;
}
$currentFormTeacherId = 0;
$currentFormStudentId = 0;
$currentFormMajorId = (int) old('jurusan_id', 0);
$editingId = $isEditing ? (int) ($editingPosition['id'] ?? 0) : 0;
if ($isEditing && $selectedYearId > 0 && $editingId > 0) {
    if ($currentCategory === 'guru' && isset($teacherAssignments[$editingId])) {
        $firstAssignment = $teacherAssignments[$editingId][0] ?? null;
        if ($firstAssignment !== null) {
            if (!$currentRequiresMajor) {
                $currentFormTeacherId = (int) ($firstAssignment['guru_id'] ?? 0);
            }
            if ($currentRequiresMajor && $currentFormMajorId <= 0) {
                $currentFormMajorId = (int) ($firstAssignment['jurusan_id'] ?? 0);
            }
        }
    } elseif ($currentCategory === 'siswa' && isset($studentAssignments[$editingId])) {
        $firstAssignment = $studentAssignments[$editingId][0] ?? null;
        if ($firstAssignment !== null) {
            $currentFormStudentId = (int) ($firstAssignment['siswa_id'] ?? 0);
        }
    }
}
$oldTeacher = old('guru_id', null);
if ($oldTeacher !== null && $oldTeacher !== '') {
    $currentFormTeacherId = (int) $oldTeacher;
}
$oldMajor = old('jurusan_id', null);
if ($oldMajor !== null && $oldMajor !== '') {
    $currentFormMajorId = (int) $oldMajor;
}
$oldStudent = old('siswa_id', null);
if ($oldStudent !== null && $oldStudent !== '') {
    $currentFormStudentId = (int) $oldStudent;
}
$hasGuruPositions = false;
$hasSiswaPositions = false;
foreach ($positions as $position) {
    $category = (string) ($position['kategori'] ?? 'guru');
    if ($category === 'guru') {
        $hasGuruPositions = true;
    } elseif ($category === 'siswa') {
        $hasSiswaPositions = true;
    }
}
$hasMajorOptions = !empty($majorOptions);
$teacherAssignmentsByMajor = [];
foreach ($teacherAssignments as $positionId => $assignmentRows) {
    foreach ($assignmentRows as $assignmentRow) {
        $majorId = (int) ($assignmentRow['jurusan_id'] ?? 0);
        if ($majorId <= 0) {
            continue;
        }

        if (!isset($teacherAssignmentsByMajor[$positionId])) {
            $teacherAssignmentsByMajor[$positionId] = [];
        }

        $teacherAssignmentsByMajor[$positionId][$majorId] = $assignmentRow;
    }
}
$editingMajorAssignments = $editingId > 0 ? ($teacherAssignmentsByMajor[$editingId] ?? []) : [];
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Jabatan Akademik' : 'Tambah Jabatan Akademik' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/jabatan-akademik/' . $editingPosition['id'] . '/update') : base_url('master/jabatan-akademik'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <?php if ($isSystemEditing): ?>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700">
                        Jabatan ini merupakan jabatan bawaan sistem dan tidak dapat dihapus.
                    </div>
                <?php endif; ?>
                <input type="hidden" name="context_year_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama Jabatan</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingPosition['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Waka Kurikulum"
                    />
                </div>
                <?php if ($isEditing): ?>
                    <div>
                        <label for="kode-jabatan" class="block text-sm font-medium text-slate-600">Kode Jabatan</label>
                        <input
                            type="text"
                            id="kode-jabatan"
                            value="<?= htmlspecialchars($editingPositionCode !== null ? (string) $editingPositionCode : '-', ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-500 focus:outline-none"
                            readonly
                        />
                        <p class="mt-2 text-xs text-slate-400">Kode jabatan disusun otomatis oleh sistem.</p>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                        Kode jabatan akan dibuat otomatis setelah data disimpan.
                    </div>
                <?php endif; ?>
                <div>
                    <label for="deskripsi" class="block text-sm font-medium text-slate-600">Deskripsi</label>
                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="4"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Uraian tugas dan tanggung jawab"
                    ><?= htmlspecialchars((string) old('deskripsi', $editingPosition['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="kategori-penanggung-jawab" class="block text-sm font-medium text-slate-600">Kategori Penanggung Jawab</label>
                    <select
                        id="kategori-penanggung-jawab"
                        name="kategori"
                        class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= $isSystemEditing ? 'disabled' : '' ?>
                    >
                        <option value="guru" <?= $currentCategory === 'guru' ? 'selected' : '' ?>>Guru</option>
                        <option value="siswa" <?= $currentCategory === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                    </select>
                    <?php if ($isSystemEditing): ?>
                        <input type="hidden" name="kategori" value="<?= htmlspecialchars($currentCategory, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <p class="mt-2 text-xs text-slate-400">
                        Tentukan apakah jabatan ini ditujukan untuk guru atau siswa. Sistem akan menampilkan daftar sesuai pilihan.
                    </p>
                </div>
                <div data-category-section="guru" class="<?= $currentCategory === 'guru' ? '' : 'hidden' ?> space-y-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-slate-700">Penugasan Per Jurusan</p>
                                <p class="text-xs text-slate-500">Aktifkan jika jabatan ini harus diisi untuk tiap jurusan/program studi.</p>
                            </div>
                            <div>
                                <?php if ($isSystemEditing): ?>
                                    <input type="hidden" name="requires_major" value="<?= $currentRequiresMajor ? '1' : '0' ?>">
                                <?php else: ?>
                                    <input type="hidden" name="requires_major" value="0">
                                <?php endif; ?>
                                <label class="inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        id="requires-major-toggle"
                                        <?= $isSystemEditing ? '' : 'name="requires_major"' ?>
                                        value="1"
                                        class="peer sr-only"
                                        <?= $currentRequiresMajor ? 'checked' : '' ?>
                                        <?= $isSystemEditing ? 'disabled' : '' ?>
                                    >
                                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-indigo-600 peer-focus:ring-2 peer-focus:ring-indigo-200"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label for="guru-penanggung-jawab" class="text-sm font-medium text-slate-600">Guru Penanggung Jawab</label>
                        <?php if ($selectedYearLabel !== null): ?>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedYearId <= 0): ?>
                        <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                            Pilih tahun ajaran terlebih dahulu untuk menetapkan guru pada jabatan ini.
                        </p>
                    <?php elseif (empty($teacherOptions)): ?>
                        <p class="rounded-lg border border-dashed border-slate-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                            Belum ada data guru aktif. Tambahkan guru di menu Master &rarr; Guru terlebih dahulu.
                        </p>
                    <?php else: ?>
                        <div data-requires-major-section class="<?= $currentRequiresMajor ? '' : 'hidden' ?> space-y-2">
                            <label for="jurusan-penanggung-jawab" class="text-sm font-medium text-slate-600">Jurusan</label>
                            <?php if (empty($majorOptions)): ?>
                                <p class="rounded-lg border border-dashed border-slate-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                                    Data jurusan belum tersedia. Tambahkan jurusan di menu Master &rarr; Jurusan agar dapat menetapkan kepala prodi.
                                </p>
                            <?php else: ?>
                                <select
                                    id="jurusan-penanggung-jawab"
                                    name="jurusan_id"
                                    class="block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    data-major-select
                                    data-teacher-select="guru-penanggung-jawab"
                                    <?= $currentRequiresMajor ? '' : 'disabled' ?>
                                >
                                    <option value="">-- Pilih Jurusan --</option>
                                    <?php foreach ($majorOptions as $majorId => $label): ?>
                                        <?php
                                            $mId = (int) $majorId;
                                            $assignment = $editingMajorAssignments[$mId] ?? null;
                                            $assignedTeacherId = $assignment !== null ? (int) ($assignment['guru_id'] ?? 0) : 0;
                                            $assignedTeacherName = $assignment !== null ? trim((string) ($assignment['guru_nama'] ?? '')) : '';
                                            $optionLabel = $assignedTeacherName !== ''
                                                ? sprintf('%s - %s', $label, $assignedTeacherName)
                                                : $label;
                                        ?>
                                        <option
                                            value="<?= htmlspecialchars((string) $mId, ENT_QUOTES, 'UTF-8') ?>"
                                            data-assigned-teacher-id="<?= $assignedTeacherId > 0 ? htmlspecialchars((string) $assignedTeacherId, ENT_QUOTES, 'UTF-8') : '' ?>"
                                            <?= $currentFormMajorId === $mId ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <input
                            type="search"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring quick-search"
                            placeholder="Cari guru berdasarkan nama"
                            data-search-target="guru-penanggung-jawab"
                            autocomplete="off"
                        >
                        <select
                            id="guru-penanggung-jawab"
                            name="guru_id"
                            class="block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            <?= $currentRequiresMajor && empty($majorOptions) ? 'disabled' : '' ?>
                        >
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($teacherOptions as $teacherId => $label): ?>
                                <?php $optionId = (int) $teacherId; ?>
                                <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $currentFormTeacherId === $optionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400">
                            Penetapan guru berlaku untuk tahun ajaran terpilih dan dapat diubah sewaktu-waktu. Gunakan kolom pencarian untuk mempercepat pemilihan.
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($currentCategory !== 'guru'): ?>
                    <input type="hidden" name="requires_major" value="0">
                <?php endif; ?>
                <div data-category-section="siswa" class="<?= $currentCategory === 'siswa' ? '' : 'hidden' ?> space-y-3">
                    <div class="flex items-center justify-between">
                        <label for="siswa-penanggung-jawab" class="text-sm font-medium text-slate-600">Siswa Penanggung Jawab</label>
                        <?php if ($selectedYearLabel !== null): ?>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedYearId <= 0): ?>
                        <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                            Pilih tahun ajaran terlebih dahulu untuk menetapkan siswa pada jabatan ini.
                        </p>
                    <?php elseif (empty($studentOptions)): ?>
                        <p class="rounded-lg border border-dashed border-slate-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
                            Belum ada data siswa pada tahun ajaran ini. Pastikan data siswa sudah diperbarui.
                        </p>
                    <?php else: ?>
                        <input
                            type="search"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring quick-search"
                            placeholder="Cari siswa berdasarkan nama"
                            data-search-target="siswa-penanggung-jawab"
                            autocomplete="off"
                        >
                        <select
                            id="siswa-penanggung-jawab"
                            name="siswa_id"
                            class="block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        >
                            <option value="">-- Pilih Siswa --</option>
                            <?php foreach ($studentOptions as $studentId => $label): ?>
                                <?php $optionId = (int) $studentId; ?>
                                <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $currentFormStudentId === $optionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400">
                            Penetapan siswa berlaku untuk tahun ajaran terpilih. Gunakan kolom pencarian untuk menemukan nama lebih cepat.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url($listPathWithYear), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Daftar Jabatan Akademik</h2>
                    <?php if ($selectedYearLabel !== null): ?>
                        <p class="mt-1 text-xs text-slate-500">
                            Tahun Ajaran: <span class="font-semibold text-slate-700"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($yearOptions)): ?>
                    <form method="get" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <?php if ($isEditing && isset($editingPosition['id'])): ?>
                            <input type="hidden" name="edit" value="<?= htmlspecialchars((string) $editingPosition['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <label for="tahun-ajaran" class="hidden sm:block">Tahun Ajaran</label>
                        <select
                            id="tahun-ajaran"
                            name="tahun_ajaran_id"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring"
                            onchange="this.form.submit()"
                        >
                            <option value="">Pilih Tahun Ajaran</option>
                            <?php foreach ($yearOptions as $yearId => $label): ?>
                                <option value="<?= htmlspecialchars((string) $yearId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedYearId === (int) $yearId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($selectedYearId <= 0): ?>
                <div class="border-b border-slate-100 bg-slate-50 px-6 py-3 text-sm text-slate-600">
                    Pilih tahun ajaran untuk melihat dan menetapkan penanggung jawab pada setiap jabatan.
                </div>
            <?php else: ?>
                <?php if ($hasGuruPositions && empty($teacherOptions)): ?>
                    <div class="border-b border-amber-100 bg-amber-50 px-6 py-3 text-sm text-amber-700">
                        Tambahkan data guru terlebih dahulu untuk menetapkan jabatan akademik kategori guru pada tahun ajaran ini.
                    </div>
                <?php endif; ?>
                <?php if ($hasSiswaPositions && empty($studentOptions)): ?>
                    <div class="border-b border-sky-100 bg-sky-50 px-6 py-3 text-sm text-sky-700">
                        Data siswa untuk tahun ajaran ini belum tersedia. Lengkapi data siswa sebelum menetapkan jabatan kategori siswa.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Penanggung Jawab</th>
                            <th class="px-6 py-4">Deskripsi</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($positions as $position): ?>
                            <?php
                                $positionId = (int) ($position['id'] ?? 0);
                                $category = (string) ($position['kategori'] ?? 'guru');
                                $assignedRecords = $category === 'siswa'
                                    ? ($studentAssignments[$positionId] ?? [])
                                    : ($teacherAssignments[$positionId] ?? []);
                                $primaryAssignment = $assignedRecords[0] ?? null;
                                $currentTeacherId = ($category === 'guru' && $primaryAssignment !== null)
                                    ? (int) ($primaryAssignment['guru_id'] ?? 0)
                                    : 0;
                                $requiresMajor = (int) ($position['requires_major'] ?? 0) === 1;
                                if ($requiresMajor) {
                                    $currentTeacherId = 0;
                                }
                                $majorAssignments = $requiresMajor ? ($teacherAssignmentsByMajor[$positionId] ?? []) : [];
                                $currentStudentId = ($category === 'siswa' && $primaryAssignment !== null)
                                    ? (int) ($primaryAssignment['siswa_id'] ?? 0)
                                    : 0;
                                $isSystemPosition = (int) ($position['is_system'] ?? 0) === 1;
                                $roleCode = (string) ($position['assigns_user_role'] ?? '');
                                $roleLabelMap = [
                                    'bendahara' => 'Akses Bendahara',
                                    'tata_usaha' => 'Akses Tata Usaha',
                                    'kepala_sekolah' => 'Akses Kepala Sekolah',
                                    'waka_kurikulum' => 'Akses Waka Kurikulum',
                                    'kepala_prodi' => 'Akses Kepala Prodi',
                                ];
                                $roleLabel = $roleLabelMap[$roleCode] ?? '';
                                $entityLabel = $category === 'siswa' ? 'Siswa' : 'Guru';
                                $canAssign = $category === 'siswa' ? $canAssignStudents : $canAssignTeachers;
                                $canAssignPosition = $canAssign && (!$requiresMajor || !empty($majorOptions));
                            ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($position['nama'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($isSystemPosition): ?>
                                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Default</span>
                                        <?php endif; ?>
                                        <?php if ($requiresMajor): ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Per Jurusan</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($roleLabel !== ''): ?>
                                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-400"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($position['level'] !== null ? (string) $position['level'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500">
                                    <div class="space-y-3">
                                        <?php if ($selectedYearId > 0): ?>
                                            <?php if (!empty($assignedRecords)): ?>
                                                <div class="space-y-2">
                                                    <?php foreach ($assignedRecords as $assignment): ?>
                                                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                                            <?php if ($category === 'guru'): ?>
                                                                <p class="font-semibold text-slate-700">
                                                                    <?= htmlspecialchars((string) ($assignment['guru_nama'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?>
                                                                </p>
                                                                <?php if ($requiresMajor): ?>
                                                                    <p class="text-xs font-semibold text-indigo-600">
                                                                        Jurusan: <?= htmlspecialchars((string) ($assignment['jurusan_nama'] ?? 'Belum diatur'), ENT_QUOTES, 'UTF-8') ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($assignment['guru_nip'])): ?>
                                                                    <p class="text-xs text-slate-500">NIP: <?= htmlspecialchars((string) $assignment['guru_nip'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($assignment['guru_email'])): ?>
                                                                    <p class="text-xs text-slate-500">Email: <?= htmlspecialchars((string) $assignment['guru_email'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($assignment['guru_telepon'])): ?>
                                                                    <p class="text-xs text-slate-500">Telepon: <?= htmlspecialchars((string) $assignment['guru_telepon'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <p class="font-semibold text-slate-700">
                                                                    <?= htmlspecialchars((string) ($assignment['siswa_nama'] ?? 'Siswa'), ENT_QUOTES, 'UTF-8') ?>
                                                                    <?= student_status_badge($assignment, 'ml-1 align-middle') ?>
                                                                    <?= student_dapodik_badge($assignment, 'ml-1 align-middle') ?>
                                                                </p>
                                                                <?php if (!empty($assignment['siswa_nipd'])): ?>
                                                                    <p class="text-xs text-slate-500">NIPD: <?= htmlspecialchars((string) $assignment['siswa_nipd'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($assignment['siswa_nisn'])): ?>
                                                                    <p class="text-xs text-slate-500">NISN: <?= htmlspecialchars((string) $assignment['siswa_nisn'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-xs text-slate-400">Belum ada <?= strtolower($entityLabel) ?> yang ditetapkan.</p>
                                            <?php endif; ?>
                                            <form
                                                action="<?= htmlspecialchars(base_url('master/jabatan-akademik/' . $positionId . '/assign'), ENT_QUOTES, 'UTF-8') ?>"
                                                method="post"
                                                class="flex flex-col gap-3 sm:flex-row sm:items-center"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php if ($category === 'guru'): ?>
                                                    <?php if ($requiresMajor): ?>
                                                        <?php if (empty($majorOptions)): ?>
                                                            <p class="w-full rounded-lg border border-dashed border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-700">
                                                                Tambahkan data jurusan pada menu Master &rarr; Jurusan sebelum menetapkan kepala prodi.
                                                            </p>
                                                        <?php else: ?>
                                                            <label for="jurusan-<?= $positionId ?>" class="sr-only">Pilih Jurusan</label>
                                                            <select
                                                                id="jurusan-<?= $positionId ?>"
                                                                name="jurusan_id"
                                                                class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                                                data-major-select
                                                                data-teacher-select="guru-<?= $positionId ?>"
                                                            >
                                                                <option value="">-- Pilih Jurusan --</option>
                                                                <?php foreach ($majorOptions as $majorId => $label): ?>
                                                                    <?php
                                                                        $optionMajorId = (int) $majorId;
                                                                        $assignment = $majorAssignments[$optionMajorId] ?? null;
                                                                        $assignedTeacherId = $assignment !== null ? (int) ($assignment['guru_id'] ?? 0) : 0;
                                                                        $assignedTeacherName = $assignment !== null ? trim((string) ($assignment['guru_nama'] ?? '')) : '';
                                                                        $optionLabel = $assignedTeacherName !== ''
                                                                            ? sprintf('%s - %s', $label, $assignedTeacherName)
                                                                            : $label;
                                                                    ?>
                                                                    <option
                                                                        value="<?= htmlspecialchars((string) $optionMajorId, ENT_QUOTES, 'UTF-8') ?>"
                                                                        data-assigned-teacher-id="<?= $assignedTeacherId > 0 ? htmlspecialchars((string) $assignedTeacherId, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                                    >
                                                                        <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <input type="hidden" name="jurusan_id" value="0">
                                                    <?php endif; ?>
                                                    <label for="guru-<?= $positionId ?>" class="sr-only">Pilih Guru</label>
                                                    <?php if (!empty($teacherOptions)): ?>
                                                        <input
                                                            type="search"
                                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring quick-search"
                                                            placeholder="Cari guru"
                                                            data-search-target="guru-<?= $positionId ?>"
                                                            autocomplete="off"
                                                        >
                                                    <?php endif; ?>
                                                    <select
                                                        id="guru-<?= $positionId ?>"
                                                        name="guru_id"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                                        <?= $canAssignPosition ? '' : 'disabled' ?>
                                                    >
                                                        <option value="">-- Pilih Guru --</option>
                                                        <?php foreach ($teacherOptions as $teacherId => $label): ?>
                                                            <?php $optionId = (int) $teacherId; ?>
                                                            <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $currentTeacherId === $optionId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <label for="siswa-<?= $positionId ?>" class="sr-only">Pilih Siswa</label>
                                                    <?php if (!empty($studentOptions)): ?>
                                                        <input
                                                            type="search"
                                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring quick-search"
                                                            placeholder="Cari siswa"
                                                            data-search-target="siswa-<?= $positionId ?>"
                                                            autocomplete="off"
                                                        >
                                                    <?php endif; ?>
                                                    <select
                                                        id="siswa-<?= $positionId ?>"
                                                        name="siswa_id"
                                                        class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                                        <?= $canAssign ? '' : 'disabled' ?>
                                                    >
                                                        <option value="">-- Pilih Siswa --</option>
                                                        <?php foreach ($studentOptions as $studentId => $label): ?>
                                                            <?php $optionId = (int) $studentId; ?>
                                                            <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $currentStudentId === $optionId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="submit"
                                                        name="action"
                                                        value="save"
                                                        class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300"
                                                        <?= $canAssignPosition ? '' : 'disabled' ?>
                                                    >
                                                        Tetapkan
                                                    </button>
                                                    <?php if (!empty($assignedRecords)): ?>
                                                        <button
                                                            type="submit"
                                                            name="action"
                                                            value="clear"
                                                            class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                                            onclick="return confirm('Kosongkan penetapan <?= strtolower($entityLabel) ?><?= $requiresMajor ? ' untuk jurusan ini' : '' ?>?');"
                                                            <?= $requiresMajor && empty($majorOptions) ? 'disabled' : '' ?>
                                                        >
                                                            Kosongkan
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-xs text-slate-400">Pilih tahun ajaran untuk menetapkan <?= strtolower($entityLabel) ?>.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?= htmlspecialchars($position['deskripsi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-right">
                                    <?php
                                        $editQuery = 'edit=' . urlencode((string) $positionId);
                                        if ($baseQuery !== '') {
                                            $editQuery = $baseQuery . '&' . $editQuery;
                                        }
                                        $editUrl = base_url($listBasePath . '?' . $editQuery);
                                    ?>
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <?php if (!$isSystemPosition): ?>
                                            <form action="<?= htmlspecialchars(base_url('master/jabatan-akademik/' . $position['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus jabatan akademik ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-400">Tidak bisa dihapus</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($positions)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data jabatan akademik.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.querySelector('#kategori-penanggung-jawab');
    if (categorySelect) {
        const sections = document.querySelectorAll('[data-category-section]');
        const toggleSections = () => {
            const value = categorySelect.value;
            sections.forEach((section) => {
                const sectionCategory = section.getAttribute('data-category-section');
                if (sectionCategory === value) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            });
        };
        categorySelect.addEventListener('change', toggleSections);
        toggleSections();
    }

    document.querySelectorAll('[data-major-select]').forEach((majorSelect) => {
        const teacherSelectId = majorSelect.getAttribute('data-teacher-select');
        if (!teacherSelectId) {
            return;
        }

        const teacherSelect = document.getElementById(teacherSelectId);
        if (!teacherSelect) {
            return;
        }

        const syncTeacherSelection = () => {
            const selectedOption = majorSelect.options[majorSelect.selectedIndex] || null;
            if (!selectedOption) {
                teacherSelect.value = '';
                return;
            }

            const assignedTeacherId = selectedOption.getAttribute('data-assigned-teacher-id') || '';
            if (assignedTeacherId !== '') {
                teacherSelect.value = assignedTeacherId;
            } else {
                teacherSelect.value = '';
            }
        };

        majorSelect.addEventListener('change', syncTeacherSelection);

        if (majorSelect.value !== '') {
            syncTeacherSelection();
        }
    });

    document.querySelectorAll('.quick-search').forEach((input) => {
        const targetId = input.getAttribute('data-search-target');
        if (!targetId) {
            return;
        }

        const select = document.getElementById(targetId);
        if (!select) {
            return;
        }

        const options = Array.from(select.options);

        const filterOptions = () => {
            const term = input.value.trim().toLowerCase();

            options.forEach((option, index) => {
                if (index === 0 || option.value === '') {
                    option.hidden = false;
                    return;
                }

                const text = option.textContent.toLowerCase();
                const match = term === '' || text.includes(term);

                if (!match && option.selected) {
                    option.hidden = false;
                } else {
                    option.hidden = !match;
                }
            });
        };

        input.addEventListener('input', filterOptions);
        input.addEventListener('blur', () => {
            if (input.value.trim() === '') {
                options.forEach((option) => {
                    option.hidden = false;
                });
            }
        });
    });

    const requiresMajorToggle = document.querySelector('#requires-major-toggle');
    if (requiresMajorToggle) {
        const majorSections = document.querySelectorAll('[data-requires-major-section]');
        const toggleMajorSections = () => {
            const active = requiresMajorToggle.checked;
            majorSections.forEach((section) => {
                if (active) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }

                section.querySelectorAll('select').forEach((select) => {
                    if (active) {
                        select.removeAttribute('disabled');
                    } else {
                        select.setAttribute('disabled', 'disabled');
                        select.selectedIndex = 0;
                    }
                });
            });
        };
        requiresMajorToggle.addEventListener('change', toggleMajorSections);
        toggleMajorSections();
    }
});
</script>
