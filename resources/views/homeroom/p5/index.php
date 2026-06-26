<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $selectedClass = isset($selectedClass) && is_array($selectedClass) ? $selectedClass : null;
    $projects = isset($projects) && is_array($projects) ? $projects : [];
    $selectedProjectId = isset($selectedProjectId) ? (int) $selectedProjectId : null;
    $elements = isset($elements) && is_array($elements) ? $elements : [];
    $students = isset($students) && is_array($students) ? $students : [];
    $assessments = isset($assessments) && is_array($assessments) ? $assessments : [];
    $summaries = isset($summaries) && is_array($summaries) ? $summaries : [];
    $dimensions = isset($dimensions) && is_array($dimensions) ? $dimensions : [];
    $kurmerLevels = [
        'BB' => 'BB - Belum Berkembang',
        'MB' => 'MB - Mulai Berkembang',
        'BSH' => 'BSH - Berkembang Sesuai Harapan',
        'SB' => 'SB - Sangat Berkembang',
    ];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Projek Penguatan Profil Pelajar Pancasila</p>
            <h2 class="text-xl font-semibold text-slate-800">Penilaian Projek P5</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                Kelola projek P5 per kelas: buat projek, tambahkan elemen/TP, input capaian BB/MB/BSH/SB per siswa, dan catat ringkasan untuk rapor.
            </p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
        <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas</h3>
                <p class="text-sm text-slate-500">
                    Hanya kelas Kurikulum Merdeka yang memiliki projek P5.
                </p>
            </div>
            <form method="get" class="flex items-center gap-3">
                <select
                    name="kelas_id"
                    onchange="this.form.submit()"
                    class="block w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                >
                    <option value="">-- Pilih kelas KurMer --</option>
                    <?php foreach ($classes as $class): ?>
                        <?php $classId = (int) ($class['id'] ?? 0); ?>
                        <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-') . ' · ' . strtoupper((string) ($class['kurikulum'] ?? 'k13')), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <?php if ($selectedClass === null): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                Pilih kelas untuk mulai mengelola projek P5.
            </div>
        <?php elseif (($selectedClass['kurikulum'] ?? 'k13') !== 'kurmer'): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-6 text-sm text-amber-700">
                Kelas ini bukan Kurikulum Merdeka. Projek P5 hanya tersedia untuk kelas KurMer.
            </div>
        <?php else: ?>
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-4 space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-800">Tambah Projek</h4>
                        <form action="<?= htmlspecialchars(base_url('walikelas/p5'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-3 space-y-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Tema</label>
                                <input type="text" name="tema" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Judul Projek</label>
                                <input type="text" name="judul" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Deskripsi (opsional)</label>
                                <textarea name="deskripsi" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-slate-600">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_mulai" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600">Tanggal Selesai</label>
                                    <input type="date" name="tanggal_selesai" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                <i class="ri-add-line text-base"></i> Simpan Projek
                            </button>
                        </form>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-800">Daftar Projek</h4>
                        <div class="mt-3 space-y-3">
                            <?php if (empty($projects)): ?>
                                <p class="text-xs text-slate-500">Belum ada projek.</p>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                    <?php $pid = (int) ($project['id'] ?? 0); ?>
                                    <a href="<?= htmlspecialchars(base_url('walikelas/p5?kelas_id=' . $selectedClassId . '&projek_id=' . $pid), ENT_QUOTES, 'UTF-8') ?>"
                                       class="block rounded-lg border <?= $selectedProjectId === $pid ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50' ?> px-3 py-2 text-sm">
                                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($project['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="block text-xs text-slate-500">Tema: <?= htmlspecialchars($project['tema'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-6">
                    <?php if ($selectedProjectId === null): ?>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            Pilih projek untuk mengelola elemen dan penilaian.
                        </div>
                    <?php else: ?>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Elemen / TP Projek</h4>
                                    <p class="text-xs text-slate-500">Pilih elemen P5 atau tulis TP khusus projek ini.</p>
                                </div>
                            </div>
                            <form action="<?= htmlspecialchars(base_url('walikelas/p5/elemen/' . $selectedProjectId), ENT_QUOTES, 'UTF-8') ?>" method="post" class="grid gap-3 md:grid-cols-3">
                                <?= csrf_field() ?>
                                <div class="md:col-span-1">
                                    <label class="text-xs font-semibold text-slate-600">Elemen P5 (opsional)</label>
                                    <select name="elemen_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <option value="">-- Pilih elemen --</option>
                                        <?php foreach ($dimensions as $dimension): ?>
                                            <optgroup label="<?= htmlspecialchars($dimension['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($dimension['elements'] ?? [] as $el): ?>
                                                    <option value="<?= htmlspecialchars((string) $el['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars(($el['kode'] ?? '') . ' - ' . ($el['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs font-semibold text-slate-600">Deskripsi TP (opsional)</label>
                                    <textarea name="tp_deskripsi" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Tuliskan TP khusus projek ini jika diperlukan"></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-600">Urutan</label>
                                    <input type="number" name="urutan" min="1" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <div class="md:col-span-2 flex items-end">
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                        <i class="ri-add-line text-base"></i> Tambah Elemen
                                    </button>
                                </div>
                            </form>

                            <div class="space-y-2">
                                <?php if (empty($elements)): ?>
                                    <p class="text-xs text-slate-500">Belum ada elemen ditambahkan.</p>
                                <?php else: ?>
                                    <?php foreach ($elements as $el): ?>
                                        <div class="flex items-start justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <div class="pr-3">
                                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($el['elemen_kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if (!empty($el['elemen_nama'])): ?>
                                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($el['elemen_nama'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($el['tp_deskripsi'])): ?>
                                                    <div class="mt-1 text-xs text-slate-600 whitespace-pre-line"><?= htmlspecialchars($el['tp_deskripsi'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <form action="<?= htmlspecialchars(base_url('walikelas/p5/elemen/' . (int) $selectedProjectId . '/' . (int) ($el['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus elemen ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center justify-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-100">
                                                    <i class="ri-delete-bin-line text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Penilaian Elemen per Siswa</h4>
                                    <p class="text-xs text-slate-500">Pilih capaian BB/MB/BSH/SB, nilai opsional, dan catatan singkat.</p>
                                </div>
                            </div>
                            <?php if (empty($elements)): ?>
                                <p class="text-sm text-slate-500">Tambahkan elemen terlebih dahulu.</p>
                            <?php else: ?>
                                <form action="<?= htmlspecialchars(base_url('walikelas/p5/penilaian/' . $selectedProjectId), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                    <?= csrf_field() ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-[1100px] border-collapse text-xs text-slate-700">
                                            <thead class="bg-slate-100 text-slate-600">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-semibold">No</th>
                                                    <th class="px-3 py-2 text-left font-semibold">Nama Siswa</th>
                                                    <?php foreach ($elements as $el): ?>
                                                        <th class="px-3 py-2 text-left font-semibold">
                                                            <?= htmlspecialchars($el['elemen_kode'] ?? 'EL', ENT_QUOTES, 'UTF-8') ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $index => $student): ?>
                                                    <?php
                                                        $sid = (int) ($student['id'] ?? 0);
                                                        $studentInactive = student_is_inactive($student);
                                                        $inactiveTitle = 'Siswa nonaktif; penilaian P5 tidak dapat diinput.';
                                                    ?>
                                                    <tr class="border-t border-slate-200 odd:bg-white even:bg-slate-50">
                                                        <td class="px-3 py-2"><?= $index + 1 ?></td>
                                                        <td class="px-3 py-2">
                                                            <div class="font-semibold text-slate-800">
                                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                            </div>
                                                            <div class="text-[11px] text-slate-500">NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                        </td>
                                                        <?php foreach ($elements as $el): ?>
                                                            <?php
                                                                $eid = (int) ($el['id'] ?? 0);
                                                                $existing = $assessments[$sid][$eid] ?? [];
                                                                $cap = $existing['capaian_enum'] ?? '';
                                                                $nilai = $existing['nilai_opsional'] ?? null;
                                                                $cat = $existing['catatan'] ?? '';
                                                            ?>
                                                            <td class="px-3 py-2 space-y-2">
                                                                <select name="capaian[<?= $sid ?>][<?= $eid ?>]" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                                    <option value="">-</option>
                                                                    <?php foreach ($kurmerLevels as $key => $label): ?>
                                                                        <option value="<?= $key ?>" <?= $cap === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <input type="number" step="0.01" min="0" max="100" name="nilai[<?= $sid ?>][<?= $eid ?>]" value="<?= htmlspecialchars($nilai !== null ? (string) $nilai : '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" placeholder="Nilai" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                                <input type="text" name="catatan[<?= $sid ?>][<?= $eid ?>]" value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" placeholder="Catatan" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                        <i class="ri-save-line text-base"></i> Simpan Penilaian
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Ringkasan Projek per Siswa</h4>
                                    <p class="text-xs text-slate-500">Isi capaian akhir dan deskripsi utama untuk rapor.</p>
                                </div>
                            </div>
                            <form action="<?= htmlspecialchars(base_url('walikelas/p5/ringkasan/' . $selectedProjectId), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                <?= csrf_field() ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-[900px] border-collapse text-xs text-slate-700">
                                        <thead class="bg-slate-100 text-slate-600">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold">No</th>
                                                <th class="px-3 py-2 text-left font-semibold">Nama Siswa</th>
                                                <th class="px-3 py-2 text-left font-semibold">Capaian Akhir</th>
                                                <th class="px-3 py-2 text-left font-semibold">Deskripsi Umum</th>
                                                <th class="px-3 py-2 text-left font-semibold">Tindak Lanjut</th>
                                                <th class="px-3 py-2 text-left font-semibold">Nilai Opsional</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $index => $student): ?>
                                                <?php
                                                    $sid = (int) ($student['id'] ?? 0);
                                                    $summary = $summaries[$sid] ?? [];
                                                    $cap = $summary['capaian_akhir_enum'] ?? '';
                                                    $studentInactive = student_is_inactive($student);
                                                    $inactiveTitle = 'Siswa nonaktif; ringkasan P5 tidak dapat diinput.';
                                                ?>
                                                <tr class="border-t border-slate-200 odd:bg-white even:bg-slate-50">
                                                    <td class="px-3 py-2"><?= $index + 1 ?></td>
                                                    <td class="px-3 py-2">
                                                        <div class="font-semibold text-slate-800">
                                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                        </div>
                                                        <div class="text-[11px] text-slate-500">NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <select name="capaian_akhir[<?= $sid ?>]" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                            <option value="">-</option>
                                                            <?php foreach ($kurmerLevels as $key => $label): ?>
                                                                <option value="<?= $key ?>" <?= $cap === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <textarea name="deskripsi_umum[<?= $sid ?>]" rows="3" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($summary['deskripsi_umum'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <textarea name="tindak_lanjut[<?= $sid ?>]" rows="3" class="w-full rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($summary['tindak_lanjut'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="number" step="0.01" min="0" max="100" name="nilai_opsional[<?= $sid ?>]" value="<?= htmlspecialchars($summary['nilai_opsional'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-24 rounded border border-slate-200 px-2 py-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                    <i class="ri-save-line text-base"></i> Simpan Ringkasan
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
