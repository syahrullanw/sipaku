<?php
    $assignment = isset($assignment) && is_array($assignment) ? $assignment : [];
    $setting = isset($setting) && is_array($setting) ? $setting : [];
    $classOptions = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $students = isset($students) && is_array($students) ? $students : [];
    $competencies = isset($competencies) && is_array($competencies) ? $competencies : [];
    $scoreMap = isset($scoreMap) && is_array($scoreMap) ? $scoreMap : [];
    $skillMap = isset($skillMap) && is_array($skillMap) ? $skillMap : [];
    $assignmentId = (int) ($assignment['id'] ?? 0);
    $enableKkm = (int) ($setting['enable_kkm'] ?? 0) === 1;
    $kkmValue = $setting['nilai_kkm'] ?? null;
?>

<div class="space-y-8 bg-gray-50 pb-12">
    <div class="sticky top-0 z-30 border-b border-slate-200 bg-gray-50/95 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-gray-800">Input Nilai Keterampilan</h1>
                <p class="text-sm text-gray-500">
                    Penilaian Keterampilan · <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <a
                href="<?= htmlspecialchars(base_url('guru/nilai?focus=' . $assignmentId), ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition-colors duration-300 hover:border-indigo-300 hover:text-indigo-600"
            >
                <i class="ri-arrow-go-back-line text-base"></i>
                Kembali ke Daftar Mapel
            </a>
        </div>
    </div>

    <div class="space-y-8 px-4 sm:px-6 lg:px-8">
        <section class="space-y-6 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-3 lg:col-span-2">
                    <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">
                        <?= htmlspecialchars($assignment['mata_pelajaran_kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <h2 class="text-lg font-semibold text-gray-800">
                        Penilaian Keterampilan · <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="text-sm text-gray-500 max-w-3xl">
                        Masukkan nilai keterampilan berdasarkan KD yang tersedia dan lengkapi deskripsi capaian siswa. Nilai akhir dihitung otomatis sebagai rata-rata seluruh KD.
                    </p>
                </div>
                <div class="space-y-3">
                    <div class="rounded-xl border border-slate-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status KKM</p>
                        <p class="mt-2 text-sm text-gray-700">
                            <?php if ($enableKkm): ?>
                                Aktif · Nilai KKM <span class="font-semibold text-indigo-600"><?= htmlspecialchars((string) ($kkmValue ?? 75), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                Nonaktif · Menggunakan rentang standar
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Jumlah KD Aktif</p>
                        <p class="mt-1 text-lg font-semibold text-gray-700"><?= count($competencies) ?></p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Pilih Kelas</h3>
                    <p class="text-xs text-gray-500">Sistem menampilkan siswa sesuai kelas dan tahun ajaran untuk mata pelajaran ini.</p>
                </div>
                <form method="get" class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-64">
                        <select
                            name="kelas_id"
                            class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-gray-700 transition-colors duration-300 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option value="">-- Pilih kelas --</option>
                            <?php foreach ($classOptions as $class): ?>
                                <?php $classId = (int) ($class['id'] ?? 0); ?>
                                <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-300">
                            <i class="ri-arrow-down-s-line text-lg"></i>
                        </span>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition-colors duration-300 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        <i class="ri-filter-3-line text-base"></i>
                        Tampilkan
                    </button>
                </form>
            </div>

            <?php if ($selectedClassId === null): ?>
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-6 text-sm text-gray-600 shadow-sm">
                    Pilih kelas terlebih dahulu untuk menampilkan daftar siswa dan menginput nilai keterampilan.
                </div>
            <?php else: ?>
                <div class="grid gap-6 lg:grid-cols-12">
                    <div class="space-y-5 lg:col-span-3">
                        <div class="space-y-4 rounded-2xl border border-slate-200 bg-gray-50 px-5 py-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-700">Daftar KD Keterampilan</h4>
                                <span class="text-[11px] font-medium text-gray-400">Nilai akhir = rata-rata</span>
                            </div>
                            <div class="space-y-3">
                                <?php if (empty($competencies)): ?>
                                    <div class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-gray-500">
                                        Belum ada KD yang ditambahkan untuk kelas ini.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($competencies as $kd): ?>
                                        <div class="space-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3 transition-colors duration-300 hover:border-indigo-200">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="space-y-1">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600"><?= htmlspecialchars($kd['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="text-sm font-semibold text-gray-700">
                                                        <?= htmlspecialchars($kd['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <?php if (!empty($kd['deskripsi'])): ?>
                                                        <p class="text-xs text-gray-500">
                                                            <?= htmlspecialchars($kd['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <form action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/keterampilan/kd/' . (int) ($kd['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus KD ini?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 transition-colors duration-300 hover:border-rose-300 hover:bg-rose-100"
                                                    >
                                                        <i class="ri-delete-bin-line text-sm"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
                            <form
                                action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/keterampilan/kd'), ENT_QUOTES, 'UTF-8') ?>"
                                method="post"
                                class="space-y-4"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-700">Tambah KD Keterampilan</p>
                                    <p class="text-xs text-gray-500">Gunakan kode unik untuk menghindari duplikasi KD.</p>
                                </div>
                                <div class="space-y-2">
                                    <label for="kode-kd" class="text-xs font-semibold text-gray-500">Kode KD</label>
                                    <input
                                        type="text"
                                        id="kode-kd"
                                        name="kode"
                                        maxlength="50"
                                        required
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-gray-700 transition-colors duration-300 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    >
                                </div>
                                <div class="space-y-2">
                                    <label for="deskripsi-kd" class="text-xs font-semibold text-gray-500">Deskripsi (opsional)</label>
                                    <textarea
                                        id="deskripsi-kd"
                                        name="deskripsi"
                                        rows="3"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-gray-700 transition-colors duration-300 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    ></textarea>
                                </div>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors duration-300 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                >
                                    <i class="ri-add-line text-base"></i>
                                    Tambah KD
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-5 rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm lg:col-span-9">
                        <?php if (empty($competencies)): ?>
                            <p class="text-sm text-gray-500">
                                Tambahkan KD keterampilan terlebih dahulu sebelum menginput nilai.
                            </p>
                        <?php else: ?>
                            <form
                                action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/keterampilan/simpan'), ENT_QUOTES, 'UTF-8') ?>"
                                method="post"
                                class="space-y-5"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="-mx-4 overflow-x-auto rounded-2xl border border-slate-200 md:mx-0">
                                    <table class="min-w-[1100px] border-collapse text-sm text-gray-700 sm:min-w-full">
                                        <thead class="bg-gray-100 text-gray-600">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-semibold">No</th>
                                                <th class="px-4 py-3 text-left font-semibold">Nama Siswa</th>
                                                <?php foreach ($competencies as $kd): ?>
                                                    <th class="px-4 py-3 text-left font-semibold">
                                                        <?= htmlspecialchars($kd['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                        <span class="block text-[10px] font-normal text-gray-400">Nilai KD</span>
                                                    </th>
                                                <?php endforeach; ?>
                                                <th class="px-4 py-3 text-left font-semibold">Nilai Akhir</th>
                                                <th class="px-4 py-3 text-left font-semibold">Predikat</th>
                                                <th class="px-4 py-3 text-left font-semibold">Deskripsi Umum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $index => $student): ?>
                                                <?php
                                                    $studentId = (int) ($student['id'] ?? 0);
                                                    $existing = $skillMap[$studentId] ?? [];
                                                    $rowScores = $scoreMap[$studentId] ?? [];
                                                    $finalScore = $existing['nilai_akhir'] ?? null;
                                                    $finalPredicate = $existing['predikat'] ?? '';
                                                    $studentInactive = student_is_inactive($student);
                                                    $inactiveTitle = 'Siswa nonaktif; nilai tidak dapat diinput.';
                                                ?>
                                                <tr class="border-t border-slate-200 odd:bg-white even:bg-gray-50 hover:bg-indigo-50 transition-colors duration-300">
                                                    <td class="px-4 py-3 text-xs text-gray-500"><?= $index + 1 ?></td>
                                                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                                                        <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                        <span class="block text-xs font-normal text-gray-400">
                                                            NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <?php foreach ($competencies as $kd): ?>
                                                        <?php
                                                            $kdId = (int) ($kd['id'] ?? 0);
                                                            $scoreValue = $rowScores[$kdId]['nilai'] ?? '';
                                                        ?>
                                                        <td class="px-4 py-3">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                max="100"
                                                                name="nilai_kd[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $kdId, ENT_QUOTES, 'UTF-8') ?>]"
                                                                value="<?= htmlspecialchars((string) $scoreValue, ENT_QUOTES, 'UTF-8') ?>"
                                                                class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-gray-700 transition-colors duration-300 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 sm:w-28"
                                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                            >
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">
                                                        <?= $finalScore !== null ? htmlspecialchars(number_format((float) $finalScore, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '-' ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm font-semibold <?= $finalPredicate === 'Perlu Bimbingan' ? 'text-rose-600' : 'text-emerald-600' ?>">
                                                        <?= htmlspecialchars($finalPredicate ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-xs text-gray-600">
                                                        <?php if (!empty($existing['deskripsi'])): ?>
                                                            <p class="whitespace-pre-line rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-gray-600 shadow-sm"><?= htmlspecialchars((string) $existing['deskripsi'], ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php else: ?>
                                                            <p class="rounded-lg border border-dashed border-slate-200 bg-gray-50 px-3 py-2 text-xs text-gray-400">
                                                                Deskripsi umum akan dihasilkan otomatis setelah nilai disimpan.
                                                            </p>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                                    <p>Kosongkan KD yang belum dinilai; sistem akan menyimpan 0 secara otomatis dan menyusun deskripsi dari maksimal 3 KD.</p>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition-colors duration-300 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                    >
                                        <i class="ri-save-3-line text-lg"></i>
                                        Simpan Nilai Keterampilan
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
