<?php
    $cachedStatuses = isset($records) && is_array($records) ? $records : [];
    $oldStatusValues = isset($oldStatuses) && is_array($oldStatuses) ? $oldStatuses : [];
    $oldNoteValues = isset($oldNotes) && is_array($oldNotes) ? $oldNotes : [];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Status Naik Kelas</h2>
            <p class="text-sm text-slate-500">
                Tetapkan status kenaikan kelas untuk siswa tingkat 10 dan 11 pada semester genap.
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
                                <?= htmlspecialchars('Tingkat ' . ($class['tingkat'] ?? '-') . ' · ' . ($class['nama'] ?? 'Kelas') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
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
                <form action="<?= htmlspecialchars(base_url('walikelas/status-naik-kelas'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $record = isset($cachedStatuses[$studentId]) ? $cachedStatuses[$studentId] : ['status' => null, 'catatan' => null];
                                        $statusValue = $oldStatusValues[$studentId] ?? ($record['status'] ?? '');
                                        $noteValue = $oldNoteValues[$studentId] ?? ($record['catatan'] ?? '');
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; status kenaikan tidak dapat diinput.';
                                    ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-700">
                                            <p>
                                                <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select
                                                name="status[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            >
                                                <option value="">Belum ditentukan</option>
                                                <option value="naik" <?= $statusValue === 'naik' ? 'selected' : '' ?>>Naik Kelas</option>
                                                <option value="tinggal" <?= $statusValue === 'tinggal' ? 'selected' : '' ?>>Tinggal Kelas</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <textarea
                                                name="catatan[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                rows="2"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                placeholder="Catatan tambahan (opsional)"
                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                            ><?= htmlspecialchars((string) $noteValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">Pilih status kenaikan kelas atau biarkan kosong jika belum ditentukan.</p>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Status
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
