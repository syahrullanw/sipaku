<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $students = isset($students) && is_array($students) ? $students : [];
    $activities = isset($activities) && is_array($activities) ? $activities : [];
    $activityOptions = isset($activityOptions) && is_array($activityOptions) ? $activityOptions : [];
    $assignments = isset($assignments) && is_array($assignments) ? $assignments : [];
    $oldAssignments = isset($oldAssignments) && is_array($oldAssignments) ? $oldAssignments : [];
    $unassignedStudents = isset($unassignedStudents) && is_array($unassignedStudents) ? $unassignedStudents : [];
    $isActiveMismatch = isset($isActiveMismatch) ? (bool) $isActiveMismatch : false;
    $scoreDetails = isset($scoreDetails) && is_array($scoreDetails) ? $scoreDetails : [];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Pengelolaan Ekskul Siswa</h2>
            <p class="text-sm text-slate-500">
                Pastikan setiap siswa di kelas Anda terdaftar pada minimal satu kegiatan ekstrakurikuler di semester aktif.
            </p>
        </div>
        <?php if (!empty($activeYear)): ?>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                <p class="font-semibold text-indigo-800">Semester Aktif</p>
                <p><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($classes)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Anda belum tercatat sebagai wali kelas pada data kelas manapun. Hubungi admin untuk menugaskan Anda sebagai wali kelas.
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <form method="get" class="flex flex-col gap-3 md:flex-row md:items-center">
                <label for="kelas_id" class="text-sm font-medium text-slate-600">Pilih Kelas</label>
                <div class="flex gap-3">
                    <select
                        id="kelas_id"
                        name="kelas_id"
                        class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <?php foreach ($classes as $class): ?>
                            <?php $id = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === $selectedClassId ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($class['nama'] ?? 'Kelas') . ' · ' . ($class['jurusan_nama'] ?? '-') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                        Tampilkan
                    </button>
                </div>
            </form>

            <?php if ($isActiveMismatch): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Kelas yang dipilih tidak berada pada semester aktif. Penetapan ekskul hanya dapat dilakukan pada semester aktif.
                </div>
            <?php endif; ?>

            <?php if (empty($activityOptions)): ?>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    Belum ada data ekstrakurikuler pada semester aktif. Hubungi admin untuk menambahkan daftar ekskul terlebih dahulu.
                </div>
            <?php elseif (empty($students)): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php else: ?>
                <?php if (!empty($unassignedStudents) && !$isActiveMismatch): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <?= count($unassignedStudents) ?> siswa belum memiliki ekskul terdaftar. Lengkapi data sebelum menyimpan.
                    </div>
                <?php endif; ?>

                <?php if (!empty($activities)): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-700 mb-2">Daftar Ekskul Semester Aktif:</p>
                        <ul class="grid gap-2 md:grid-cols-2">
                            <?php foreach ($activities as $activity): ?>
                                <li class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($activity['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($activity['jadwal'])): ?>
                                        <span class="ml-2 text-slate-400">• <?= htmlspecialchars($activity['jadwal'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['pembina_nama'])): ?>
                                        <span class="block text-slate-400">Pembina: <?= htmlspecialchars($activity['pembina_nama'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= htmlspecialchars(base_url('walikelas/ekskul'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-slate-600">Ekskul Terpilih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $defaultAssignments = $assignments[$studentId] ?? [];
                                        $oldValues = $oldAssignments[$studentId] ?? null;
                                        if ($oldValues !== null) {
                                            if (!is_array($oldValues)) {
                                                $oldValues = [$oldValues];
                                            }
                                            $defaultAssignments = array_values(array_unique(array_filter(
                                                array_map(static fn ($value): int => (int) $value, $oldValues),
                                                static fn (int $id): bool => $id > 0
                                            )));
                                        }
                                        $selectedActivities = array_filter(
                                            $defaultAssignments,
                                            static fn (int $id): bool => array_key_exists($id, $activityOptions)
                                        );
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; data ekskul tidak dapat diinput.';
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-700">
                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </div>
                                            <?php if (!empty($student['nisn'])): ?>
                                                <div class="text-xs text-slate-400">NISN: <?= htmlspecialchars($student['nisn'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <?php foreach ($activityOptions as $activityId => $activityName): ?>
                                                    <?php
                                                        $activityId = (int) $activityId;
                                                        $checkboxId = 'student-' . $studentId . '-ekskul-' . $activityId;
                                                        $isChecked = in_array($activityId, $selectedActivities, true);
                                                    ?>
                                                    <label
                                                        for="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
                                                        class="flex items-center gap-2 rounded-lg border <?= $isChecked ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white' ?> px-3 py-2 text-sm text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            id="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
                                                            name="assignments[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][]"
                                                            value="<?= htmlspecialchars((string) $activityId, ENT_QUOTES, 'UTF-8') ?>"
                                                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                            <?= $isChecked ? 'checked' : '' ?>
                                                            <?= ($isActiveMismatch || $studentInactive) ? 'disabled title="' . htmlspecialchars($studentInactive ? $inactiveTitle : 'Semester aktif tidak sesuai.', ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                        >
                                                        <span class="flex-1"><?= htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8') ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="mt-2 text-xs text-slate-400">
                                                Centang minimal satu ekskul untuk setiap siswa.
                                            </p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">
                            Semua siswa wajib memiliki minimal satu ekskul pada semester aktif.
                        </p>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 <?= $isActiveMismatch ? 'cursor-not-allowed opacity-60' : '' ?>"
                            <?= $isActiveMismatch ? 'disabled' : '' ?>
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Data Ekskul
                        </button>
                    </div>
                </form>
                    <?php if (!$isActiveMismatch): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-base font-semibold text-slate-800">Progres Nilai Ekskul</h3>
                                <span class="text-xs text-slate-400">Data berasal dari pembina ekstrakurikuler.</span>
                            </div>
                            <?php if (empty($scoreDetails)): ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                    Belum ada nilai ekstrakurikuler yang diinput oleh pembina ekskul.
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Ekskul</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Akhir</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Predikat</th>
                                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Deskripsi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php foreach ($students as $student): ?>
                                                <?php
                                                    $studentId = (int) ($student['id'] ?? 0);
                                                    $details = $scoreDetails[$studentId] ?? [];
                                                    $studentName = $student['nama'] ?? '-';
                                                    $studentNisn = $student['nisn'] ?? '';
                                                ?>
                                                <?php if (empty($details)): ?>
                                                    <tr>
                                                        <td class="px-4 py-3">
                                                            <div class="font-semibold text-slate-700"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php if ($studentNisn !== ''): ?>
                                                                <div class="text-xs text-slate-400">NISN: <?= htmlspecialchars($studentNisn, ENT_QUOTES, 'UTF-8') ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td colspan="4" class="px-4 py-3 text-sm text-slate-400">
                                                            Belum ada nilai ekstrakurikuler yang diinput pembina.
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($details as $detail): ?>
                                                        <?php
                                                            $finalScore = $detail['nilai_akhir'] ?? '';
                                                            if ($finalScore !== '' && $finalScore !== null) {
                                                                $finalScore = number_format((float) $finalScore, 2, '.', '');
                                                            }
                                                            $predicate = $detail['predikat'] ?? '';
                                                            $description = $detail['deskripsi'] ?? '';
                                                        ?>
                                                        <tr>
                                                            <td class="px-4 py-3">
                                                                <div class="font-semibold text-slate-700"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php if ($studentNisn !== ''): ?>
                                                                    <div class="text-xs text-slate-400">NISN: <?= htmlspecialchars($studentNisn, ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-slate-600">
                                                                <?= htmlspecialchars($detail['ekstrakurikuler_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-slate-600">
                                                                <?php if ($finalScore !== '' && $finalScore !== null): ?>
                                                                    <span class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                                        <?= htmlspecialchars((string) $finalScore, ENT_QUOTES, 'UTF-8') ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="text-xs text-slate-400">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-slate-600">
                                                                <?php if ($predicate !== ''): ?>
                                                                    <?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?>
                                                                <?php else: ?>
                                                                    <span class="text-xs text-slate-400">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-4 py-3 text-slate-600">
                                                                <?php if ($description !== ''): ?>
                                                                    <span class="text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-xs text-slate-400">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
