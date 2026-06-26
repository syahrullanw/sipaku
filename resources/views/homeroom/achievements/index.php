<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $students = isset($students) && is_array($students) ? $students : [];
    $achievements = isset($achievements) && is_array($achievements) ? $achievements : [];
    $activeYearInfo = isset($activeYear) && is_array($activeYear) ? $activeYear : null;
    $isActiveMismatch = isset($isActiveMismatch) ? (bool) $isActiveMismatch : false;
    $oldStudentId = isset($oldStudentId) ? (int) $oldStudentId : 0;
    $oldType = isset($oldType) ? (string) $oldType : '';
    $oldNotes = isset($oldNotes) ? (string) $oldNotes : '';
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Prestasi Siswa</h2>
            <p class="text-sm text-slate-500">
                Catat prestasi yang diraih siswa di kelas Anda selama semester aktif sebagai bahan penilaian dan laporan.
            </p>
        </div>
        <?php if (!empty($activeYearInfo)): ?>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                <p class="font-semibold text-indigo-800">Semester Aktif</p>
                <p><?= htmlspecialchars($activeYearInfo['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
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
                    Kelas yang dipilih tidak berada pada semester aktif. Pencatatan prestasi hanya dapat dilakukan pada semester aktif.
                </div>
            <?php endif; ?>

            <?php if (empty($students)): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4 <?= $isActiveMismatch ? 'opacity-70' : '' ?>">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">Tambah Prestasi Siswa</h3>
                            <p class="text-xs text-slate-400">
                                Pilih siswa yang berprestasi, isi jenis prestasi yang diraih, dan tambahkan keterangan jika diperlukan.
                            </p>
                        </div>
                    </div>

                    <form action="<?= htmlspecialchars(base_url('walikelas/prestasi'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1">
                                <label for="siswa_id" class="text-sm font-semibold text-slate-700">Siswa</label>
                                <select
                                    id="siswa_id"
                                    name="siswa_id"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 <?= $isActiveMismatch ? 'bg-slate-100' : '' ?>"
                                    <?= $isActiveMismatch ? 'disabled' : '' ?>
                                >
                                    <option value="">-- Pilih siswa --</option>
                                    <?php foreach ($students as $student): ?>
                                        <?php $studentId = (int) ($student['id'] ?? 0); ?>
                                        <option value="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>" <?= $studentId === $oldStudentId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($student['nama'] ?? 'Tanpa Nama') . (!empty($student['nisn']) ? ' - ' . $student['nisn'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label for="jenis" class="text-sm font-semibold text-slate-700">Jenis Prestasi</label>
                                <input
                                    type="text"
                                    id="jenis"
                                    name="jenis"
                                    value="<?= htmlspecialchars($oldType, ENT_QUOTES, 'UTF-8') ?>"
                                    maxlength="150"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 <?= $isActiveMismatch ? 'bg-slate-100' : '' ?>"
                                    placeholder="Contoh: Juara 1 Lomba Kompetensi Siswa"
                                    <?= $isActiveMismatch ? 'disabled' : '' ?>
                                >
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="keterangan" class="text-sm font-semibold text-slate-700">Keterangan (Opsional)</label>
                            <textarea
                                id="keterangan"
                                name="keterangan"
                                rows="3"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 <?= $isActiveMismatch ? 'bg-slate-100' : '' ?>"
                                placeholder="Tuliskan detail prestasi atau informasi tambahan"
                                <?= $isActiveMismatch ? 'disabled' : '' ?>
                            ><?= htmlspecialchars($oldNotes, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <p class="text-xs text-slate-400">
                                Data prestasi akan tersimpan dengan penanda waktu ketika Anda menekan tombol simpan.
                            </p>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 <?= $isActiveMismatch ? 'cursor-not-allowed opacity-60' : '' ?>"
                                <?= $isActiveMismatch ? 'disabled' : '' ?>
                            >
                                <i class="ri-add-circle-line text-lg"></i>
                                Simpan Prestasi
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-base font-semibold text-slate-800">Daftar Prestasi Tercatat</h3>
                        <span class="text-xs text-slate-400">
                            Total prestasi: <?= count($achievements) ?>
                        </span>
                    </div>

                    <?php if (empty($achievements)): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                            Belum ada prestasi yang dicatat untuk kelas ini.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Jenis Prestasi</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Keterangan</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Dicatat Pada</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <?php $index = 1; ?>
                                    <?php foreach ($achievements as $achievement): ?>
                                        <?php
                                            $achievementId = (int) ($achievement['id'] ?? 0);
                                            $studentName = $achievement['siswa_nama'] ?? 'Tanpa Nama';
                                            $studentNisn = $achievement['siswa_nisn'] ?? '';
                                            $type = $achievement['jenis'] ?? '';
                                            $notes = $achievement['keterangan'] ?? '';
                                            $recordedAt = $achievement['created_at'] ?? null;
                                            $recordedLabel = '-';
                                            if (!empty($recordedAt)) {
                                                $timestamp = strtotime((string) $recordedAt);
                                                if ($timestamp !== false) {
                                                    $recordedLabel = date('d M Y H:i', $timestamp);
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 text-slate-600"><?= $index++ ?></td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-slate-800">
                                                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
                                                    <?= student_status_badge($achievement, 'ml-1 align-middle') ?>
                                                    <?= student_dapodik_badge($achievement, 'ml-1 align-middle') ?>
                                                </div>
                                                <?php if (!empty($studentNisn)): ?>
                                                    <div class="text-xs text-slate-400">NISN: <?= htmlspecialchars($studentNisn, ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-700">
                                                <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($notes !== ''): ?>
                                                    <?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">Tidak ada keterangan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?= htmlspecialchars($recordedLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <form
                                                    action="<?= htmlspecialchars(base_url('walikelas/prestasi/' . $achievementId . '/delete') . ($selectedClassId > 0 ? '?kelas_id=' . urlencode((string) $selectedClassId) : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    method="post"
                                                    onsubmit="return confirm('Hapus prestasi ini?');"
                                                >
                                                    <?= csrf_field() ?>
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-200"
                                                    >
                                                        <i class="ri-delete-bin-6-line text-sm"></i>
                                                        Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
