<?php
    $assignment = isset($assignment) && is_array($assignment) ? $assignment : [];
    $setting = isset($setting) && is_array($setting) ? $setting : [];
    $classOptions = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $students = isset($students) && is_array($students) ? $students : [];
    $competencies = isset($competencies) && is_array($competencies) ? $competencies : [];
    $scoreMap = isset($scoreMap) && is_array($scoreMap) ? $scoreMap : [];
    $knowledgeMap = isset($knowledgeMap) && is_array($knowledgeMap) ? $knowledgeMap : [];
    $weights = \App\Models\SubjectAssessmentSetting::resolveWeights($setting);
    $assignmentId = (int) ($assignment['id'] ?? 0);
    $manualWeight = (int) ($setting['bobot_manual'] ?? 0) === 1;
    $enableKkm = (int) ($setting['enable_kkm'] ?? 0) === 1;
    $kkmValue = $setting['nilai_kkm'] ?? null;
    $classSummary = $classSummary ?? null;
?>

<div class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:p-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-3">
            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-200">
                <?= htmlspecialchars($assignment['mata_pelajaran_kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                Penilaian Pengetahuan · <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="max-w-3xl text-sm text-slate-500 dark:text-slate-300">
                Input nilai harian (KD), UTS, dan UAS untuk setiap siswa. Nilai akhir serta predikat dihitung otomatis mengikuti bobot penilaian yang berlaku.
            </p>
        </div>
        <div class="grid gap-4 text-sm text-slate-600 dark:text-slate-300 sm:grid-cols-2 lg:w-96 lg:grid-cols-1">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800/80">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Status KKM</p>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                    <?php if ($enableKkm): ?>
                        Aktif · Nilai KKM <span class="font-semibold text-indigo-600 dark:text-indigo-200"><?= htmlspecialchars((string) ($kkmValue ?? 75), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        Nonaktif · Menggunakan rentang standar
                    <?php endif; ?>
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800/80">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Bobot Penilaian</p>
                <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                    KD <?= number_format($weights['weight_kd'] * 100, 0) ?>% · UTS <?= number_format($weights['weight_uts'] * 100, 0) ?>% · UAS <?= number_format($weights['weight_uas'] * 100, 0) ?>%
                    <?php if ($manualWeight): ?>
                        <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-200">Manual</span>
                    <?php endif; ?>
                </p>
            </div>
            <a
                href="<?= htmlspecialchars(base_url('guru/nilai?focus=' . $assignmentId), ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition duration-200 hover:border-indigo-300 hover:text-indigo-600 hover:shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-400 dark:hover:text-indigo-200"
            >
                <i class="ri-arrow-go-back-line text-lg text-indigo-500"></i>
                Kembali ke Daftar Mapel
            </a>
        </div>
    </div>

    <section class="space-y-6 rounded-2xl border border-slate-200 bg-slate-50 p-6 text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 sm:p-8">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Pilih Kelas</h2>
                <p class="text-sm text-slate-500 dark:text-slate-300">
                    Data siswa yang muncul menyesuaikan kelas dan tahun ajaran dari mata pelajaran ini.
                </p>
            </div>
            <form method="get" class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-4 lg:w-auto">
                <div class="relative z-200 w-full sm:w-64" >
                    <select
                        name="kelas_id"
                        class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/40"
                    >
                        <option value="">-- Pilih kelas --</option>
                        <?php foreach ($classOptions as $class): ?>
                            <?php $classId = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-indigo-400">
                        <i class="ri-arrow-down-s-line text-lg"></i>
                    </span>
                </div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-indigo-500 hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 sm:w-auto"
                >
                    <i class="ri-filter-3-line text-base"></i>
                    Tampilkan
                </button>
            </form>
        </header>

        <?php if ($selectedClassId === null): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
                Pilih kelas terlebih dahulu untuk mulai menginput nilai pengetahuan.
            </div>
        <?php else: ?>
            <div class="space-y-8">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <div class="flex flex-col gap-6 border-b border-slate-200 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Informasi Kelas</span>
                            <p class="text-lg font-semibold text-slate-800">
                                <?= htmlspecialchars($classSummary ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-slate-600">
                            <span class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1 font-semibold text-indigo-600">
                                <i class="ri-team-line text-base text-indigo-500"></i>
                                <?= count($students) ?> siswa
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                <i class="ri-function-line text-base text-indigo-500"></i>
                                <?= count($competencies) ?> KD aktif
                            </span>
                        </div>
</div>

                    <div class="grid gap-6 px-6 py-6 lg:grid-cols-3">
                        <div class="space-y-4 lg:col-span-1">
                            <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-800">Daftar KD Pengetahuan</h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        KD digunakan sebagai dasar penilaian harian. Anda dapat menghapus KD yang tidak relevan.
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <?php if (empty($competencies)): ?>
                                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                            Belum ada KD yang ditambahkan untuk kelas ini.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($competencies as $kd): ?>
                                            <div class="flex items-start justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                                <div class="pr-3">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600"><?= htmlspecialchars($kd['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="text-sm font-semibold text-slate-800">
                                                        <?= htmlspecialchars($kd['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <?php if (!empty($kd['deskripsi'])): ?>
                                                        <p class="mt-2 text-xs text-slate-500">
                                                            <?= htmlspecialchars($kd['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <form action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/pengetahuan/kd/' . (int) ($kd['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus KD ini?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
                                                    >
                                                        <i class="ri-delete-bin-line text-sm"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 lg:col-span-2">
                            <div class="flex flex-col gap-2">
                                <h3 class="text-sm font-semibold text-slate-800">Tambah KD Pengetahuan</h3>
                                <p class="text-xs text-slate-500">Gunakan kode unik agar tidak terjadi duplikasi KD.</p>
                            </div>
                            <form
                                action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/pengetahuan/kd'), ENT_QUOTES, 'UTF-8') ?>"
                                method="post"
                                class="grid gap-4 sm:grid-cols-2"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="space-y-2">
                                    <label for="kode-kd" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Kode KD</label>
                                    <input
                                        type="text"
                                        id="kode-kd"
                                        name="kode"
                                        maxlength="50"
                                        required
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40"
                                    >
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label for="deskripsi-kd" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Deskripsi (opsional)</label>
                                    <textarea
                                        id="deskripsi-kd"
                                        name="deskripsi"
                                        rows="3"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40"
                                    ></textarea>
                                </div>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                >
                                    <i class="ri-add-line text-base"></i>
                                    Tambah KD
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <?php if (empty($competencies)): ?>
                        <p class="text-sm text-slate-500">
                            Tambahkan KD terlebih dahulu sebelum menginput nilai pengetahuan.
                        </p>
                    <?php else: ?>
                        <form
                            action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/pengetahuan/simpan'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            class="space-y-6"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="-mx-4 overflow-x-auto rounded-xl border border-slate-200 md:mx-0 dark:border-slate-700">
                                <table class="min-w-[1100px] border-collapse text-sm text-slate-700 dark:text-slate-200 sm:min-w-full">
                                    <thead class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">No</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nama Siswa</th>
                                            <?php foreach ($competencies as $kd): ?>
                                                <th class="px-4 py-3 text-left font-semibold">
                                                    <?= htmlspecialchars($kd['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="block text-[10px] font-normal text-slate-400 dark:text-slate-400">Nilai KD</span>
                                                </th>
                                            <?php endforeach; ?>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai UTS</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai UAS</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai Akhir</th>
                                            <th class="px-4 py-3 text-left font-semibold">Predikat</th>
                                            <th class="px-4 py-3 text-left font-semibold">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $index => $student): ?>
                                            <?php
                                                $studentId = (int) ($student['id'] ?? 0);
                                                $existing = $knowledgeMap[$studentId] ?? [];
                                                $rowScores = $scoreMap[$studentId] ?? [];
                                                $finalScore = $existing['nilai_akhir'] ?? null;
                                                $finalPredicate = $existing['predikat'] ?? '';
                                                $studentInactive = student_is_inactive($student);
                                                $inactiveTitle = 'Siswa nonaktif; nilai tidak dapat diinput.';
                                            ?>
                                            <tr class="border-t border-slate-200 odd:bg-white even:bg-slate-50 hover:bg-indigo-50 dark:border-slate-700 dark:odd:bg-slate-900 dark:even:bg-slate-900/70 dark:hover:bg-indigo-900/40">
                                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= $index + 1 ?></td>
                                                <td class="px-4 py-3">
                                                    <p class="font-semibold text-slate-800 dark:text-slate-100">
                                                        <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                    </p>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                                        NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                </td>
                                                <?php foreach ($competencies as $kd): ?>
                                                    <?php $kdId = (int) ($kd['id'] ?? 0); ?>
                                                    <?php $scoreValue = $rowScores[$kdId]['nilai'] ?? ''; ?>
                                                    <td class="px-4 py-3">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="100"
                                                            name="nilai_kd[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $kdId, ENT_QUOTES, 'UTF-8') ?>]"
                                                            value="<?= htmlspecialchars((string) $scoreValue, ENT_QUOTES, 'UTF-8') ?>"
                                                            class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40 sm:w-28"
                                                            <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                        >
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        name="nilai_uts[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        value="<?= htmlspecialchars((string) ($existing['nilai_uts'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40 sm:w-28"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        name="nilai_uas[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        value="<?= htmlspecialchars((string) ($existing['nilai_uas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40 sm:w-28"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                    <?= $finalScore !== null ? htmlspecialchars(number_format((float) $finalScore, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '-' ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold <?= $finalPredicate === 'Perlu Bimbingan' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                                                    <?= htmlspecialchars($finalPredicate ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <textarea
                                                        name="deskripsi[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        rows="2"
                                                        class="min-h-[64px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500 dark:focus:border-indigo-300 dark:focus:ring-indigo-500/40"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    ><?= htmlspecialchars((string) ($existing['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-300 sm:flex-row sm:items-center sm:justify-between">
                                <p>Biarkan kosong apabila nilai belum tersedia; sistem otomatis menyimpan 0 sehingga tombol simpan tetap aktif.</p>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                                >
                                    <i class="ri-save-3-line text-lg"></i>
                                    Simpan Nilai Pengetahuan
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
