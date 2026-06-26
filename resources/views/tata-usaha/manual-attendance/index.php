<?php
$attendanceType = (string) ($attendanceType ?? 'kelas');
$attendanceTypeLabel = (string) ($attendanceTypeLabel ?? 'Presensi Kelas');
$yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
$classes = is_array($classes ?? null) ? $classes : [];
$levelOptions = is_array($levelOptions ?? null) ? $levelOptions : [];
$classesForSelectedLevel = is_array($classesForSelectedLevel ?? null) ? $classesForSelectedLevel : [];
$selectedClassIds = is_array($selectedClassIds ?? null) ? array_map('intval', $selectedClassIds) : [];
$selectedClassId = (int) ($selectedClassId ?? 0);
$selectedClass = is_array($selectedClass ?? null) ? $selectedClass : null;
$selectedClasses = is_array($selectedClasses ?? null) ? $selectedClasses : [];
$selectedLevel = trim((string) ($selectedLevel ?? ''));
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : null;
$selectedYearId = (int) ($selectedYearId ?? 0);
$students = is_array($students ?? null) ? $students : [];
$studentsByClass = is_array($studentsByClass ?? null) ? $studentsByClass : [];
$subjectAssignments = is_array($subjectAssignments ?? null) ? $subjectAssignments : [];
$selectedAssignmentId = (int) ($selectedAssignmentId ?? 0);
$selectedSubjectAssignment = is_array($selectedSubjectAssignment ?? null) ? $selectedSubjectAssignment : null;
$sheetPrintUrl = $sheetPrintUrl ?? null;
$coverPrintUrl = $coverPrintUrl ?? null;
$totalStudentCount = (int) ($totalStudentCount ?? count($students));
$schoolYearName = trim((string) ($schoolYear['nama'] ?? '-'));
$selectedClassIdMap = array_fill_keys($selectedClassIds, true);
$classSummary = $attendanceType === 'kelas'
    ? count($selectedClasses) . ' kelas dipilih'
    : ($selectedClass !== null ? '1 rombel dipilih' : 'Belum ada rombel');
$classLabel = trim((string) ($classLabel ?? '-'));
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Cetak Absensi Siswa Manual</h2>
                <p class="mt-2 text-sm text-slate-500">Siapkan template presensi kelas atau presensi mata pelajaran dengan ukuran cetak F4.</p>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                <span class="font-semibold"><?= htmlspecialchars($attendanceTypeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="ml-2 text-indigo-500"><?= htmlspecialchars($classSummary, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>

    <?php if (empty($yearOptions)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Tahun ajaran belum tersedia. Tambahkan tahun ajaran terlebih dahulu pada master data.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Parameter Cetak</h3>
                    <form method="get" action="<?= htmlspecialchars(base_url('tata-usaha/presensi-manual'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4">
                        <div>
                            <label for="jenis" class="block text-sm font-medium text-slate-600">Jenis Presensi</label>
                            <select
                                id="jenis"
                                name="jenis"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                onchange="this.form.submit()"
                            >
                                <option value="kelas" <?= $attendanceType === 'kelas' ? 'selected' : '' ?>>Presensi Kelas</option>
                                <option value="mapel" <?= $attendanceType === 'mapel' ? 'selected' : '' ?>>Presensi Mapel</option>
                            </select>
                        </div>

                        <div>
                            <label for="tahun_ajaran_id" class="block text-sm font-medium text-slate-600">Tahun Ajaran</label>
                            <select
                                id="tahun_ajaran_id"
                                name="tahun_ajaran_id"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                onchange="this.form.submit()"
                            >
                                <?php foreach ($yearOptions as $yearId => $yearLabel): ?>
                                    <option value="<?= htmlspecialchars((string) $yearId, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $yearId === $selectedYearId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $yearLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($attendanceType === 'kelas'): ?>
                            <div>
                                <label for="tingkat" class="block text-sm font-medium text-slate-600">Tingkat</label>
                                <?php if (empty($levelOptions)): ?>
                                    <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                        Belum ada kelas pada tahun ajaran ini.
                                    </div>
                                <?php else: ?>
                                    <select
                                        id="tingkat"
                                        name="tingkat"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        onchange="this.form.submit()"
                                    >
                                        <?php foreach ($levelOptions as $level => $label): ?>
                                            <option value="<?= htmlspecialchars((string) $level, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $level === $selectedLevel ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="block text-sm font-medium text-slate-600">Kelas pada Tingkat Ini</label>
                                    <?php if (!empty($classesForSelectedLevel)): ?>
                                        <span class="text-xs text-slate-400"><?= count($selectedClassIds) ?> dipilih</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($classesForSelectedLevel)): ?>
                                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                        Tidak ada kelas pada tingkat terpilih.
                                    </div>
                                <?php else: ?>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <?php foreach ($classesForSelectedLevel as $class): ?>
                                            <?php
                                                $classId = (int) ($class['id'] ?? 0);
                                                $level = trim((string) ($class['tingkat'] ?? ''));
                                                $name = trim((string) ($class['nama'] ?? ''));
                                                $major = trim((string) ($class['jurusan_nama'] ?? ''));
                                                $label = trim(($level !== '' ? $level . ' ' : '') . $name);
                                                if ($major !== '') {
                                                    $label .= ' - ' . $major;
                                                }
                                            ?>
                                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    name="kelas_ids[]"
                                                    value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    <?= isset($selectedClassIdMap[$classId]) ? 'checked' : '' ?>
                                                />
                                                <span>
                                                    <span class="font-semibold"><?= htmlspecialchars($label !== '' ? $label : '-', ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="block text-xs text-slate-400"><?= htmlspecialchars((string) ($class['wali_kelas_nama'] ?? 'Wali kelas belum diatur'), ENT_QUOTES, 'UTF-8') ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-400">Presensi kelas hanya dapat mencetak kelas dari satu tingkat yang sama.</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div>
                                <label for="kelas_id" class="block text-sm font-medium text-slate-600">Rombel</label>
                                <?php if (empty($classes)): ?>
                                    <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                        Belum ada rombel pada tahun ajaran ini.
                                    </div>
                                <?php else: ?>
                                    <select
                                        id="kelas_id"
                                        name="kelas_id"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        onchange="this.form.submit()"
                                    >
                                        <?php foreach ($classes as $class): ?>
                                            <?php
                                                $classId = (int) ($class['id'] ?? 0);
                                                $level = trim((string) ($class['tingkat'] ?? ''));
                                                $name = trim((string) ($class['nama'] ?? ''));
                                                $major = trim((string) ($class['jurusan_nama'] ?? ''));
                                                $label = trim(($level !== '' ? $level . ' ' : '') . $name);
                                                if ($major !== '') {
                                                    $label .= ' - ' . $major;
                                                }
                                            ?>
                                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $classId === $selectedClassId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label !== '' ? $label : '-', ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="pengampu_id" class="block text-sm font-medium text-slate-600">Mapel dan Guru Pengampu</label>
                                <?php if (empty($subjectAssignments)): ?>
                                    <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                        Belum ada guru pengampu mapel untuk rombel terpilih.
                                    </div>
                                <?php else: ?>
                                    <select
                                        id="pengampu_id"
                                        name="pengampu_id"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    >
                                        <?php foreach ($subjectAssignments as $assignment): ?>
                                            <?php $assignmentId = (int) ($assignment['id'] ?? 0); ?>
                                            <option value="<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" <?= $assignmentId === $selectedAssignmentId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) ($assignment['label'] ?? 'Mapel'), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 focus:outline-none focus:ring focus:ring-slate-300"
                            >
                                <i class="ri-refresh-line text-base"></i>
                                Terapkan
                            </button>
                            <?php if ($coverPrintUrl !== null): ?>
                                <button
                                    type="submit"
                                    formaction="<?= htmlspecialchars(base_url('tata-usaha/presensi-manual/sampul'), ENT_QUOTES, 'UTF-8') ?>"
                                    formmethod="get"
                                    formtarget="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-300"
                                >
                                    <i class="ri-file-paper-2-line text-base"></i>
                                    Cetak Sampul
                                </button>
                            <?php endif; ?>
                            <?php if ($sheetPrintUrl !== null): ?>
                                <button
                                    type="submit"
                                    formaction="<?= htmlspecialchars(base_url('tata-usaha/presensi-manual/cetak'), ENT_QUOTES, 'UTF-8') ?>"
                                    formmethod="get"
                                    formtarget="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring focus:ring-emerald-300"
                                >
                                    <i class="ri-printer-line text-base"></i>
                                    Cetak Absensi
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Ringkasan Cetak</h3>
                    <?php if ($attendanceType === 'kelas' && empty($selectedClasses)): ?>
                        <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                            Pilih minimal satu kelas pada tingkat terpilih.
                        </div>
                    <?php elseif ($attendanceType === 'mapel' && ($selectedClass === null || $selectedSubjectAssignment === null)): ?>
                        <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                            Pilih rombel dan guru pengampu mapel terlebih dahulu.
                        </div>
                    <?php else: ?>
                        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($attendanceTypeLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahun Ajaran</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($schoolYearName, ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= $attendanceType === 'kelas' ? 'Tingkat' : 'Rombel' ?></dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($attendanceType === 'kelas' ? ($selectedLevel !== '' ? $selectedLevel : '-') : $classLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jumlah</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars($classSummary, ENT_QUOTES, 'UTF-8') ?>, <?= $totalStudentCount ?> siswa</dd>
                            </div>
                            <?php if ($attendanceType === 'mapel' && $selectedSubjectAssignment !== null): ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mapel dan Guru</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-700"><?= htmlspecialchars((string) ($selectedSubjectAssignment['label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($attendanceType === 'kelas'): ?>
                            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Kelas</th>
                                            <th class="px-4 py-3 text-left">Wali Kelas</th>
                                            <th class="px-4 py-3 text-left">Siswa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                        <?php foreach ($selectedClasses as $class): ?>
                                            <?php
                                                $classId = (int) ($class['id'] ?? 0);
                                                $classStudents = is_array($studentsByClass[$classId] ?? null) ? $studentsByClass[$classId] : [];
                                                $level = trim((string) ($class['tingkat'] ?? ''));
                                                $name = trim((string) ($class['nama'] ?? ''));
                                                $major = trim((string) ($class['jurusan_nama'] ?? ''));
                                                $label = trim(($level !== '' ? $level . ' ' : '') . $name);
                                                if ($major !== '') {
                                                    $label .= ' - ' . $major;
                                                }
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($label !== '' ? $label : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars((string) ($class['wali_kelas_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-4 py-3"><?= count($classStudents) ?> siswa</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif (!empty($students)): ?>
                            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3 text-left">No</th>
                                            <th class="px-4 py-3 text-left">Nama Siswa</th>
                                            <th class="px-4 py-3 text-left">NIPD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                        <?php foreach (array_slice($students, 0, 8) as $index => $student): ?>
                                            <tr>
                                                <td class="px-4 py-3"><?= $index + 1 ?></td>
                                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars((string) ($student['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="px-4 py-3"><?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($students) > 8): ?>
                                <p class="mt-3 text-xs text-slate-400">Menampilkan 8 dari <?= count($students) ?> siswa.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
