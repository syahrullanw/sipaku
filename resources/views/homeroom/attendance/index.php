<?php
    $oldSickValues = isset($oldSick) && is_array($oldSick) ? $oldSick : [];
    $oldPermitValues = isset($oldPermit) && is_array($oldPermit) ? $oldPermit : [];
    $oldTruantValues = isset($oldTruant) && is_array($oldTruant) ? $oldTruant : [];
    $oldAbsentValues = isset($oldAbsent) && is_array($oldAbsent) ? $oldAbsent : [];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Input Presensi Siswa</h2>
            <p class="text-sm text-slate-500">
                Catat jumlah kehadiran siswa berupa sakit, izin, bolos, dan tanpa keterangan untuk kelas yang Anda ampu.
            </p>
        </div>
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
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === (int) ($selectedClassId ?? 0) ? 'selected' : '' ?>>
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

            <?php if (empty($students ?? [])): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php else: ?>
                <form action="<?= htmlspecialchars(base_url('walikelas/presensi'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Sakit</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Izin</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Bolos</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Tanpa Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $record = isset($records[$studentId]) ? $records[$studentId] : ['sakit' => 0, 'izin' => 0, 'bolos' => 0, 'alpa' => 0];
                                        $sickValue = $oldSickValues[$studentId] ?? ($record['sakit'] ?? 0);
                                        $permitValue = $oldPermitValues[$studentId] ?? ($record['izin'] ?? 0);
                                        $truantValue = $oldTruantValues[$studentId] ?? ($record['bolos'] ?? 0);
                                        $absentValue = $oldAbsentValues[$studentId] ?? ($record['alpa'] ?? 0);
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; presensi tidak dapat diinput.';
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 align-top">
                                            <p class="font-medium text-slate-700">
                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </p>
                                            <?php if (!empty($student['nisn'])): ?>
                                                <p class="text-xs text-slate-400">NISN: <?= htmlspecialchars((string) $student['nisn'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <input
                                                type="number"
                                                min="0"
                                                max="366"
                                                name="sakit[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $sickValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <input
                                                type="number"
                                                min="0"
                                                max="366"
                                                name="izin[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $permitValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <input
                                                type="number"
                                                min="0"
                                                max="366"
                                                name="bolos[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $truantValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <input
                                                type="number"
                                                min="0"
                                                max="366"
                                                name="alpa[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                value="<?= htmlspecialchars((string) $absentValue, ENT_QUOTES, 'UTF-8') ?>"
                                                class="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">
                            Input angka 0 jika tidak terdapat ketidakhadiran pada kategori tertentu.
                        </p>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Presensi
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
