<?php
    $placeOptions = isset($places) && is_array($places) ? $places : [];
    $oldPlacementValues = isset($oldPlacements) && is_array($oldPlacements) ? $oldPlacements : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $isActiveMismatch = isset($isActiveMismatch) ? (bool) $isActiveMismatch : false;
    $students = isset($students) && is_array($students) ? $students : [];
    $unassignedStudents = isset($unassignedStudents) && is_array($unassignedStudents) ? $unassignedStudents : [];
    $hasPlaces = !empty($placeOptions);
    $assessments = isset($assessments) && is_array($assessments) ? $assessments : [];
    $prakerinRequired = isset($prakerinRequired) ? (bool) $prakerinRequired : true;
    $prakerinFormDisabled = $isActiveMismatch || !$prakerinRequired;
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Penempatan Prakerin</h2>
            <p class="text-sm text-slate-500">
                Pastikan seluruh siswa di kelas Anda telah ditempatkan ke industri mitra pada semester aktif.
            </p>
        </div>
        <?php if (!empty($activeYear)): ?>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                <p class="font-semibold text-indigo-800">Semester Aktif</p>
                <p><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($classes ?? [])): ?>
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
                    Kelas yang dipilih tidak berada pada semester aktif. Penempatan prakerin hanya dapat dilakukan untuk semester aktif.
                </div>
            <?php endif; ?>

            <?php if (!$hasPlaces): ?>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    Belum ada data tempat prakerin yang tersedia. Hubungi admin untuk menambahkan daftar industri mitra terlebih dahulu.
                </div>
            <?php elseif (empty($students)): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php else: ?>
                <?php if (!$prakerinRequired): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Prakerin tidak dilaksanakan di kelas ini, jadi penempatan dinonaktifkan.
                    </div>
                <?php endif; ?>
                <?php if (!empty($unassignedStudents)): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <?= count($unassignedStudents) ?> siswa belum memiliki tempat prakerin. Lengkapi penempatan sebelum menyimpan.
                    </div>
                <?php endif; ?>

                <form action="<?= htmlspecialchars(base_url('walikelas/prakerin'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Tempat Prakerin</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Akhir</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Predikat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $selectedPlacement = $oldPlacementValues[$studentId] ?? ($placements[$studentId]['tempat_prakerin_id'] ?? '');
                                        $selectedPlacement = (int) $selectedPlacement;
                                        $assessment = $assessments[$studentId] ?? null;
                                        $finalScore = $assessment !== null ? number_format((float) ($assessment['nilai_akhir'] ?? 0), 2, '.', '') : null;
                                        $predicate = $assessment['predikat'] ?? null;
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; penempatan prakerin tidak dapat diinput.';

                                        if ($assessment === null && isset($placements[$studentId]['tempat_prakerin_id'])) {
                                            $finalScore = null;
                                            $predicate = null;
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-700">
                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            <?php if (!empty($student['nisn'])): ?>
                                                <span class="block text-xs text-slate-400">NISN: <?= htmlspecialchars((string) $student['nisn'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select
                                                name="placements[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 <?= ($prakerinFormDisabled || $studentInactive) ? 'cursor-not-allowed bg-slate-100 text-slate-400' : '' ?>"
                                                <?= ($prakerinFormDisabled || $studentInactive) ? 'disabled title="' . htmlspecialchars($studentInactive ? $inactiveTitle : 'Form prakerin sedang dinonaktifkan.', ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                                <option value=""><?= htmlspecialchars('Pilih Industri', ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php foreach ($placeOptions as $placeId => $placeName): ?>
                                                    <?php $placeIdInt = (int) $placeId; ?>
                                                    <option value="<?= htmlspecialchars((string) $placeIdInt, ENT_QUOTES, 'UTF-8') ?>" <?= $placeIdInt === $selectedPlacement ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($placeName, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php if ($assessment !== null): ?>
                                                <span class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                    <?= htmlspecialchars($finalScore ?? '0.00', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php elseif (!empty($placements[$studentId]['tempat_prakerin_id'])): ?>
                                                <span class="text-xs text-slate-400">Belum dinilai</span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php if (!empty($predicate)): ?>
                                                <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) $predicate, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php elseif (!empty($placements[$studentId]['tempat_prakerin_id'])): ?>
                                                <span class="text-xs text-slate-400">Menunggu nilai</span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">
                            Semua siswa wajib memiliki penempatan prakerin di semester aktif.
                        </p>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg <?= $prakerinFormDisabled ? 'bg-slate-300 text-slate-600 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-500' ?> px-5 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            <?= $prakerinFormDisabled ? 'disabled' : '' ?>
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Penempatan
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
