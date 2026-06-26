<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $selectedClass = isset($selectedClass) && is_array($selectedClass) ? $selectedClass : null;
    $activities = isset($activities) && is_array($activities) ? $activities : [];
    $selectedActivityId = isset($selectedActivityId) ? (int) $selectedActivityId : null;
    $elements = isset($elements) && is_array($elements) ? $elements : [];
    $students = isset($students) && is_array($students) ? $students : [];
    $assessments = isset($assessments) && is_array($assessments) ? $assessments : [];
    $summaries = isset($summaries) && is_array($summaries) ? $summaries : [];
    $dimensions = isset($dimensions) && is_array($dimensions) ? $dimensions : [];
    $teacherOptions = isset($teacherOptions) && is_array($teacherOptions) ? $teacherOptions : [];
    $activeYear = isset($activeYear) && is_array($activeYear) ? $activeYear : null;
    $semester = isset($semester) ? (int) $semester : 1;
    $isActiveMismatch = isset($isActiveMismatch) ? (bool) $isActiveMismatch : false;
    $activityLimitReached = isset($activityLimitReached) ? (bool) $activityLimitReached : false;
    $kurmerLevels = [
        'BB' => 'BB - Belum Berkembang',
        'MB' => 'MB - Mulai Berkembang',
        'BSH' => 'BSH - Berkembang Sesuai Harapan',
        'SB' => 'SB - Sangat Berkembang',
    ];
    $activeYearLabel = $activeYear !== null ? ($activeYear['nama'] ?? '') : '';
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Kurikulum Merdeka</p>
            <h2 class="text-xl font-semibold text-slate-800">Kokurikuler - Penilaian Wali Kelas</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                Kelola kegiatan kokurikuler kelas KurMer: buat kegiatan per semester, pilih elemen Profil Pelajar Pancasila, input capaian BB/MB/BSH/SB per siswa, lalu susun ringkasan wajib sebelum cetak rapor.
            </p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
        <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas</h3>
                <p class="text-sm text-slate-500">
                    Hanya kelas Kurikulum Merdeka pada tahun ajaran aktif yang dapat mengelola kokurikuler.
                </p>
            </div>
            <form method="get" class="flex items-center gap-3">
                <select
                    name="kelas_id"
                    onchange="this.form.submit()"
                    class="block w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                >
                    <option value="">-- Pilih kelas --</option>
                    <?php foreach ($classes as $class): ?>
                        <?php $classId = (int) ($class['id'] ?? 0); ?>
                        <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-') . ' · ' . strtoupper((string) ($class['kurikulum'] ?? 'k13')), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
            </form>
        </header>

        <?php if ($selectedClass === null): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                Pilih kelas terlebih dahulu untuk mulai mengelola kegiatan kokurikuler.
            </div>
        <?php elseif (($selectedClass['kurikulum'] ?? 'k13') !== 'kurmer'): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-6 text-sm text-amber-700">
                Kelas ini masih menggunakan K13. Kokurikuler hanya tersedia untuk kelas Kurikulum Merdeka.
            </div>
        <?php else: ?>
            <?php if ($isActiveMismatch): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Perhatian: kelas ini tidak berada di tahun ajaran aktif. Pembuatan dan penilaian kokurikuler disarankan pada semester aktif.
                </div>
            <?php endif; ?>
            <div class="grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-4 space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-800">Tambah Kegiatan Kokurikuler</h4>
                        <p class="text-xs text-slate-500 mt-1">Maksimal 3 kegiatan per kelas per semester.</p>
                        <form action="<?= htmlspecialchars(base_url('walikelas/kokurikuler'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-3 space-y-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Tema</label>
                                <input type="text" name="tema" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Nama Kegiatan</label>
                                <input type="text" name="nama" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Deskripsi Umum (opsional)</label>
                                <textarea name="deskripsi" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600">Guru Koordinator</label>
                                <select name="guru_koordinator_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <?php foreach ($teacherOptions as $id => $label): ?>
                                        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-slate-500">
                                    Semester: <?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($activeYearLabel !== ''): ?>
                                        • <?= htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                                    <?= $activityLimitReached ? 'disabled' : '' ?>
                                >
                                    <i class="ri-add-line text-base"></i> Simpan Kegiatan
                                </button>
                            </div>
                            <?php if ($activityLimitReached): ?>
                                <p class="text-xs text-amber-600">Batas 3 kegiatan per semester tercapai.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-800">Daftar Kegiatan</h4>
                        <div class="mt-3 space-y-3">
                            <?php if (empty($activities)): ?>
                                <p class="text-xs text-slate-500">Belum ada kegiatan.</p>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <?php $aid = (int) ($activity['id'] ?? 0); ?>
                                    <a
                                        href="<?= htmlspecialchars(base_url('walikelas/kokurikuler?kelas_id=' . $selectedClassId . '&kegiatan_id=' . $aid), ENT_QUOTES, 'UTF-8') ?>"
                                        class="block rounded-lg border <?= $selectedActivityId === $aid ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50' ?> px-3 py-2 text-sm"
                                    >
                                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($activity['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="block text-xs text-slate-500">Tema: <?= htmlspecialchars($activity['tema'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($activity['guru_koordinator_nama'])): ?>
                                            <span class="block text-xs text-slate-500">Koordinator: <?= htmlspecialchars($activity['guru_koordinator_nama'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-6">
                    <?php if ($selectedActivityId === null): ?>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            Pilih kegiatan untuk mengelola elemen dan penilaian.
                        </div>
                    <?php else: ?>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Elemen Profil Pelajar Pancasila</h4>
                                    <p class="text-xs text-slate-500">Setiap kegiatan terhubung ke elemen/sub-elemen. Ini yang akan dinilai.</p>
                                </div>
                            </div>
                            <form action="<?= htmlspecialchars(base_url('walikelas/kokurikuler/kegiatan/' . $selectedActivityId . '/elemen'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="grid gap-3 md:grid-cols-3">
                                <?= csrf_field() ?>
                                <div class="md:col-span-1">
                                    <label class="text-xs font-semibold text-slate-600">Elemen P5</label>
                                    <select name="elemen_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
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
                                    <label class="text-xs font-semibold text-slate-600">Sub-elemen / deskripsi fokus (opsional)</label>
                                    <textarea name="sub_elemen" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Tuliskan sub-elemen atau fokus capaian"></textarea>
                                </div>
                                <div class="md:col-span-3 flex items-end">
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
                                                <div class="font-semibold text-slate-800">
                                                    <?= htmlspecialchars(($el['elemen_kode'] ?? '-') . ' ' . ($el['elemen_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <?php if (!empty($el['dimensi_nama'])): ?>
                                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($el['dimensi_nama'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($el['sub_elemen'])): ?>
                                                    <div class="mt-1 text-xs text-slate-600 whitespace-pre-line"><?= htmlspecialchars($el['sub_elemen'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <form action="<?= htmlspecialchars(base_url('walikelas/kokurikuler/kegiatan/' . (int) $selectedActivityId . '/elemen/' . (int) ($el['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus elemen ini?');">
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
                                    <h4 class="text-sm font-semibold text-slate-800">Penilaian per Elemen & Siswa</h4>
                                    <p class="text-xs text-slate-500">Sistem otomatis menarik seluruh siswa kelas. Pilih capaian BB/MB/BSH/SB dan catatan singkat.</p>
                                </div>
                            </div>
                            <?php if (empty($elements)): ?>
                                <p class="text-sm text-slate-500">Tambahkan elemen terlebih dahulu.</p>
                            <?php elseif (empty($students)): ?>
                                <p class="text-sm text-slate-500">Belum ada siswa di kelas ini.</p>
                            <?php else: ?>
                                <form action="<?= htmlspecialchars(base_url('walikelas/kokurikuler/kegiatan/' . $selectedActivityId . '/penilaian'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                    <?= csrf_field() ?>
                                    <div class="overflow-auto">
                                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                                            <thead>
                                                <tr class="bg-slate-50">
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">Siswa</th>
                                                    <?php foreach ($elements as $el): ?>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-600">
                                                            <?= htmlspecialchars(($el['elemen_kode'] ?? '') . ' ' . ($el['elemen_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php foreach ($students as $student): ?>
                                                    <?php
                                                        $sid = (int) ($student['id'] ?? 0);
                                                        $studentInactive = student_is_inactive($student);
                                                        $inactiveTitle = 'Siswa nonaktif; penilaian kokurikuler tidak dapat diinput.';
                                                    ?>
                                                    <tr>
                                                        <td class="px-3 py-2 align-top">
                                                            <div class="font-semibold text-slate-800">
                                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                            </div>
                                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                        </td>
                                                        <?php foreach ($elements as $el): ?>
                                                            <?php
                                                                $eid = (int) ($el['id'] ?? 0);
                                                                $existing = $assessments[$sid][$eid] ?? null;
                                                                $currentCapaian = $existing['capaian_enum'] ?? '';
                                                                $currentNote = $existing['catatan'] ?? '';
                                                            ?>
                                                            <td class="px-3 py-2">
                                                                <select name="capaian[<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $eid, ENT_QUOTES, 'UTF-8') ?>]" class="block w-full rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                                                    <option value="">-</option>
                                                                    <?php foreach ($kurmerLevels as $code => $label): ?>
                                                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?= $currentCapaian === $code ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <textarea
                                                                    name="catatan[<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $eid, ENT_QUOTES, 'UTF-8') ?>]"
                                                                    rows="2"
                                                                    class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                                    placeholder="Catatan (opsional)"
                                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                                ><?= htmlspecialchars((string) $currentNote, ENT_QUOTES, 'UTF-8') ?></textarea>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="flex items-center justify-between pt-2">
                                        <p class="text-xs text-slate-500">Catatan per elemen disarankan untuk bahan ringkasan.</p>
                                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                            <i class="ri-save-3-line text-base"></i> Simpan Penilaian
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Ringkasan per Siswa</h4>
                                    <p class="text-xs text-slate-500">Wajib sebelum cetak rapor. Kosongkan deskripsi/tindak lanjut untuk digenerasi otomatis dari capaian tertinggi & terendah.</p>
                                </div>
                            </div>
                            <?php if (empty($students)): ?>
                                <p class="text-sm text-slate-500">Belum ada siswa di kelas ini.</p>
                            <?php else: ?>
                                <form action="<?= htmlspecialchars(base_url('walikelas/kokurikuler/kegiatan/' . $selectedActivityId . '/ringkasan'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-3">
                                    <?= csrf_field() ?>
                                    <div class="space-y-3">
                                        <?php foreach ($students as $student): ?>
                                            <?php
                                                $sid = (int) ($student['id'] ?? 0);
                                                $summary = $summaries[$sid] ?? null;
                                                $descValue = $summary['deskripsi_umum'] ?? '';
                                                $followUpValue = $summary['tindak_lanjut'] ?? '';
                                                $studentInactive = student_is_inactive($student);
                                                $inactiveTitle = 'Siswa nonaktif; ringkasan kokurikuler tidak dapat diinput.';
                                            ?>
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-800">
                                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                        </p>
                                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    </div>
                                                </div>
                                                <div class="mt-2 space-y-2">
                                                    <div>
                                                        <label class="text-xs font-semibold text-slate-600">Deskripsi capaian</label>
                                                        <textarea
                                                            name="deskripsi_umum[<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>]"
                                                            rows="3"
                                                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                            placeholder="Biarkan kosong untuk deskripsi otomatis dari capaian tertinggi & terendah"
                                                            <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                        ><?= htmlspecialchars((string) $descValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="text-xs font-semibold text-slate-600">Tindak lanjut (opsional)</label>
                                                        <textarea
                                                            name="tindak_lanjut[<?= htmlspecialchars((string) $sid, ENT_QUOTES, 'UTF-8') ?>]"
                                                            rows="2"
                                                            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                            placeholder="Biarkan kosong untuk rekomendasi otomatis"
                                                            <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                        ><?= htmlspecialchars((string) $followUpValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="flex items-center justify-between pt-2">
                                        <p class="text-xs text-slate-500">Ringkasan akan dipakai di rapor Kurmer (tanpa angka).</p>
                                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                            <i class="ri-check-double-line text-base"></i> Simpan Ringkasan
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
