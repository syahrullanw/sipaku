<?php
    $assignment = isset($assignment) && is_array($assignment) ? $assignment : [];
    $classOptions = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $students = isset($students) && is_array($students) ? $students : [];
    $learningObjectives = isset($learningObjectives) && is_array($learningObjectives) ? $learningObjectives : [];
    $assessmentMap = isset($assessmentMap) && is_array($assessmentMap) ? $assessmentMap : [];
    $summaryMap = isset($summaryMap) && is_array($summaryMap) ? $summaryMap : [];
    $classSummary = $classSummary ?? null;
    $assignmentId = (int) ($assignment['id'] ?? 0);
    $kurmerLevels = ['BB' => 'BB (Belum Berkembang)', 'MB' => 'MB (Mulai Berkembang)', 'BSH' => 'BSH (Berkembang Sesuai Harapan)', 'SB' => 'SB (Sangat Berkembang)'];

    $learningObjectiveMap = [];
    foreach ($learningObjectives as $tp) {
        $tpId = (int) ($tp['id'] ?? 0);
        if ($tpId <= 0) {
            continue;
        }

        $elemen = (string) ($tp['elemen'] ?? '');
        $subElemen = (string) ($tp['sub_elemen'] ?? '');
        $deskripsi = (string) ($tp['deskripsi'] ?? '');
        $learningObjectiveMap[$tpId] = [
            'id' => $tpId,
            'kode' => (string) ($tp['kode_tp'] ?? ''),
            'deskripsi' => $deskripsi,
            'elemen' => $elemen,
            'sub_elemen' => $subElemen,
            'urutan' => isset($tp['urutan']) ? (int) $tp['urutan'] : null,
            'judul' => $deskripsi !== '' ? $deskripsi : ($elemen !== '' ? $elemen : $subElemen),
        ];
    }

    $studentTpSnapshot = [];
    foreach ($assessmentMap as $studentId => $tpRows) {
        $studentId = (int) $studentId;
        if ($studentId <= 0 || !is_array($tpRows)) {
            continue;
        }

        foreach ($tpRows as $tpId => $row) {
            $tpId = (int) $tpId;
            if ($tpId <= 0 || !isset($learningObjectiveMap[$tpId])) {
                continue;
            }

            $capaian = strtoupper(trim((string) ($row['capaian_enum'] ?? '')));
            if ($capaian === '') {
                continue;
            }

            $tpMeta = $learningObjectiveMap[$tpId];
            $studentTpSnapshot[$studentId][] = [
                'tp_id' => $tpId,
                'capaian' => $capaian,
                'kode' => $tpMeta['kode'],
                'judul' => $tpMeta['judul'],
                'deskripsi' => $tpMeta['deskripsi'],
                'elemen' => $tpMeta['elemen'],
                'sub_elemen' => $tpMeta['sub_elemen'],
                'urutan' => $tpMeta['urutan'],
            ];
        }
    }
    $tpCatalog = array_values($learningObjectiveMap);
?>

<div class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:p-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-3">
            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                Kurikulum Merdeka
            </span>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
                Penilaian Tujuan Pembelajaran · <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="max-w-3xl text-sm text-slate-500 dark:text-slate-300">
                Kelola Tujuan Pembelajaran (TP), input capaian BB/MB/BSH/SB per siswa, dan tulis ringkasan deskripsi mata pelajaran untuk rapor Kurikulum Merdeka.
            </p>
        </div>
        <a
            href="<?= htmlspecialchars(base_url('guru/nilai?focus=' . $assignmentId), ENT_QUOTES, 'UTF-8') ?>"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition duration-200 hover:border-emerald-300 hover:text-emerald-600 hover:shadow-md dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-emerald-400 dark:hover:text-emerald-200"
        >
            <i class="ri-arrow-go-back-line text-lg text-emerald-500"></i>
            Kembali ke Daftar Mapel
        </a>
    </div>

    <section class="space-y-6 rounded-2xl border border-slate-200 bg-slate-50 p-6 text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 sm:p-8">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Pilih Kelas</h2>
                <p class="text-sm text-slate-500 dark:text-slate-300">
                    Hanya kelas berstatus Kurikulum Merdeka yang dapat memakai form ini.
                </p>
            </div>
            <form method="get" class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-4 lg:w-auto">
                <div class="relative w-full sm:w-64">
                    <select
                        name="kelas_id"
                        class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/40"
                    >
                        <option value="">-- Pilih kelas KurMer --</option>
                        <?php foreach ($classOptions as $class): ?>
                            <?php $classId = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-') . ' · ' . strtoupper((string) ($class['kurikulum'] ?? 'k13')), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-emerald-400">
                        <i class="ri-arrow-down-s-line text-lg"></i>
                    </span>
                </div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-500 hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 sm:w-auto"
                >
                    <i class="ri-filter-3-line text-base"></i>
                    Tampilkan
                </button>
            </form>
        </header>

        <?php if ($selectedClassId === null): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
                Pilih kelas KurMer terlebih dahulu untuk mulai menginput TP dan capaian.
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
                            <span class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">
                                <i class="ri-team-line text-base text-emerald-500"></i>
                                <?= count($students) ?> siswa
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                <i class="ri-list-check text-base text-emerald-500"></i>
                                <?= count($learningObjectives) ?> TP aktif
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-6 px-6 py-6 lg:grid-cols-3">
                        <div class="space-y-4 lg:col-span-1">
                            <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-800">Daftar Tujuan Pembelajaran</h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Tujuan Pembelajaran digunakan sebagai dasar penilaian capaian Kurikulum Merdeka.
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <?php if (empty($learningObjectives)): ?>
                                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                            Belum ada Tujuan Pembelajaran yang ditambahkan untuk kelas ini.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($learningObjectives as $tp): ?>
                                            <div class="flex items-start justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                                <div class="pr-3">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600"><?= htmlspecialchars($tp['kode_tp'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php if (!empty($tp['elemen'])): ?>
                                                        <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($tp['elemen'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($tp['deskripsi'])): ?>
                                                        <p class="mt-2 text-xs text-slate-500"><?= htmlspecialchars($tp['deskripsi'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <form action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/kurmer/tp/' . (int) ($tp['id'] ?? 0) . '/hapus'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus TP ini?');">
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
                                <h3 class="text-sm font-semibold text-slate-800">Tambah Tujuan Pembelajaran</h3>
                                <p class="text-xs text-slate-500">Gunakan kode unik agar tidak duplikasi.</p>
                            </div>
                            <form
                                action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/kurmer/tp'), ENT_QUOTES, 'UTF-8') ?>"
                                method="post"
                                class="grid gap-4 sm:grid-cols-2"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="space-y-2">
                                    <label for="kode-tp" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Kode Tujuan Pembelajaran</label>
                                    <input
                                        type="text"
                                        id="kode-tp"
                                        name="kode_tp"
                                        maxlength="100"
                                        required
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-emerald-300 dark:focus:ring-emerald-500/40"
                                    >
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label for="elemen" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Elemen/Sub Elemen (opsional)</label>
                                    <input
                                        type="text"
                                        id="elemen"
                                        name="elemen"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-emerald-300 dark:focus:ring-emerald-500/40"
                                    >
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <label for="deskripsi-tp" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Deskripsi (opsional)</label>
                                    <textarea
                                        id="deskripsi-tp"
                                        name="deskripsi"
                                        rows="3"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-emerald-300 dark:focus:ring-emerald-500/40"
                                    ></textarea>
                                </div>
                                <div class="space-y-2">
                                    <label for="urutan" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Urutan (opsional)</label>
                                    <input
                                        type="number"
                                        id="urutan"
                                        name="urutan"
                                        min="1"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-emerald-300 dark:focus:ring-emerald-500/40"
                                    >
                                </div>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                                >
                                    <i class="ri-add-line text-base"></i>
                                    Tambah Tujuan Pembelajaran
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <?php if (empty($learningObjectives)): ?>
                        <p class="text-sm text-slate-500">
                            Tambahkan Tujuan Pembelajaran terlebih dahulu sebelum menginput capaian.
                        </p>
                    <?php else: ?>
                        <form
                            action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/kurmer/tp/simpan'), ENT_QUOTES, 'UTF-8') ?>"
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
                                            <?php foreach ($learningObjectives as $tp): ?>
                                                <th class="px-4 py-3 text-left font-semibold">
                                                    <?= htmlspecialchars($tp['kode_tp'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    <span class="block text-[10px] font-normal text-slate-400 dark:text-slate-400">Capaian Tujuan Pembelajaran</span>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $index => $student): ?>
                                            <?php
                                                $studentId = (int) ($student['id'] ?? 0);
                                                $studentInactive = student_is_inactive($student);
                                                $inactiveTitle = 'Siswa nonaktif; data tidak dapat diinput.';
                                            ?>
                                            <tr class="border-t border-slate-200 odd:bg-white even:bg-slate-50 hover:bg-emerald-50 dark:border-slate-700 dark:odd:bg-slate-900 dark:even:bg-slate-900/70 dark:hover:bg-emerald-900/30">
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
                                                <?php foreach ($learningObjectives as $tp): ?>
                                                    <?php
                                                        $tpId = (int) ($tp['id'] ?? 0);
                                                        $existing = $assessmentMap[$studentId][$tpId] ?? [];
                                                        $selectedLevel = $existing['capaian_enum'] ?? '';
                                                        $nilaiValue = $existing['nilai_opsional'] ?? null;
                                                        $catatanValue = $existing['catatan'] ?? '';
                                                    ?>
                                                    <td class="px-4 py-3">
                                                        <div class="space-y-2">
                                                            <select
                                                                name="capaian[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $tpId, ENT_QUOTES, 'UTF-8') ?>]"
                                                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                            >
                                                                <option value="">-</option>
                                                                <?php foreach ($kurmerLevels as $level => $label): ?>
                                                                    <option value="<?= $level ?>" <?= $selectedLevel === $level ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                max="100"
                                                                name="nilai[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $tpId, ENT_QUOTES, 'UTF-8') ?>]"
                                                                value="<?= htmlspecialchars($nilaiValue !== null ? (string) $nilaiValue : '', ENT_QUOTES, 'UTF-8') ?>"
                                                                placeholder="Nilai (opsional)"
                                                                class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                            />
                                                            <input
                                                                type="text"
                                                                name="catatan[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars((string) $tpId, ENT_QUOTES, 'UTF-8') ?>]"
                                                                value="<?= htmlspecialchars((string) $catatanValue, ENT_QUOTES, 'UTF-8') ?>"
                                                                placeholder="Catatan singkat"
                                                                class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                                <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                            />
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                            >
                                <i class="ri-save-line text-base"></i>
                                Simpan Capaian Tujuan Pembelajaran
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Ringkasan Mapel untuk Rapor</h3>
                            <p class="text-xs text-slate-500">Isi capaian akhir BB/MB/BSH/SB dan deskripsi utama per siswa.</p>
                        </div>
                    </div>
                    <form
                        action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/kurmer/ringkasan/simpan'), ENT_QUOTES, 'UTF-8') ?>"
                        method="post"
                        class="mt-4 space-y-4"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="-mx-4 overflow-x-auto rounded-xl border border-slate-200 md:mx-0 dark:border-slate-700">
                            <table class="min-w-[900px] border-collapse text-sm text-slate-700 dark:text-slate-200 sm:min-w-full">
                                <thead class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">No</th>
                                        <th class="px-4 py-3 text-left font-semibold">Nama Siswa</th>
                                        <th class="px-4 py-3 text-left font-semibold">Capaian Akhir</th>
                                        <th class="px-4 py-3 text-left font-semibold">Deskripsi Umum</th>
                                        <th class="px-4 py-3 text-left font-semibold">Tindak Lanjut (opsional)</th>
                                        <th class="px-4 py-3 text-left font-semibold">Nilai Angka (opsional)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $studentId = (int) ($student['id'] ?? 0);
                                            $summary = $summaryMap[$studentId] ?? [];
                                            $cap = $summary['capaian_akhir_enum'] ?? '';
                                            $studentInactive = student_is_inactive($student);
                                            $inactiveTitle = 'Siswa nonaktif; data tidak dapat diinput.';
                                        ?>
                                        <tr
                                            class="border-t border-slate-200 odd:bg-white even:bg-slate-50 hover:bg-emerald-50 dark:border-slate-700 dark:odd:bg-slate-900 dark:even:bg-slate-900/70 dark:hover:bg-emerald-900/30"
                                            data-kurmer-summary-row
                                            data-student-id="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= $index + 1 ?></td>
                                            <td class="px-4 py-3">
                                                <p class="font-semibold text-slate-800 dark:text-slate-100">
                                                    <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <select
                                                    name="capaian_akhir[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                    class="js-kurmer-capaian w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                    data-student-id="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                >
                                                    <option value="">-</option>
                                                    <?php foreach ($kurmerLevels as $level => $label): ?>
                                                        <option value="<?= $level ?>" <?= $cap === $level ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="px-4 py-3">
                                                <textarea
                                                    name="deskripsi_umum[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                    rows="3"
                                                    class="js-kurmer-deskripsi w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                    data-student-id="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                ><?= htmlspecialchars($summary['deskripsi_umum'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </td>
                                            <td class="px-4 py-3">
                                                <textarea
                                                    name="tindak_lanjut[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                    rows="3"
                                                    class="js-kurmer-tindak w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                    data-student-id="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                ><?= htmlspecialchars($summary['tindak_lanjut'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="nilai_opsional[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                    value="<?= htmlspecialchars($summary['nilai_opsional'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : 'required' ?>
                                                    class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-400 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/60 dark:disabled:text-slate-500"
                                                />
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                        >
                            <i class="ri-save-line text-base"></i>
                            Simpan Ringkasan Mapel
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if (!empty($students) && !empty($learningObjectiveMap)): ?>
<script>
(function () {
    const studentTpMap = <?= json_encode($studentTpSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const tpCatalog = <?= json_encode($tpCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const levelOrder = {SB: 4, BSH: 3, MB: 2, BB: 1};
    const strengthLevels = new Set(['SB', 'BSH']);
    const followUpLevels = new Set(['MB', 'BB']);

    const rows = document.querySelectorAll('[data-kurmer-summary-row]');
    if (!rows.length) {
        return;
    }

    rows.forEach((row) => {
        const studentId = row.getAttribute('data-student-id');
        if (!studentId) {
            return;
        }

        const capaianField = row.querySelector('.js-kurmer-capaian');
        const deskripsiField = row.querySelector('.js-kurmer-deskripsi');
        const tindakField = row.querySelector('.js-kurmer-tindak');

        if (deskripsiField && deskripsiField.value.trim() !== '') {
            deskripsiField.dataset.userEdited = '1';
        }
        if (tindakField && tindakField.value.trim() !== '') {
            tindakField.dataset.userEdited = '1';
        }

        const markManual = (field) => {
            if (!field) {
                return;
            }
            field.addEventListener('input', () => {
                field.dataset.userEdited = field.value.trim() !== '' ? '1' : '';
                field.dataset.autofilled = '';
            });
        };

        markManual(deskripsiField);
        markManual(tindakField);

        const handleAutoFill = () => {
            const level = (capaianField ? capaianField.value : '').toUpperCase();
            if (!level) {
                return;
            }

            const { description, followUp } = buildSummary(studentId, level);

            if (deskripsiField) {
                const allowReplace = deskripsiField.dataset.userEdited !== '1' || deskripsiField.dataset.autofilled === '1' || deskripsiField.value.trim() === '';
                if (description && allowReplace) {
                    deskripsiField.value = description;
                    deskripsiField.dataset.autofilled = '1';
                    deskripsiField.dataset.userEdited = '';
                }
            }

            if (tindakField) {
                const allowReplace = tindakField.dataset.userEdited !== '1' || tindakField.dataset.autofilled === '1' || tindakField.value.trim() === '';
                if (followUp && allowReplace) {
                    tindakField.value = followUp;
                    tindakField.dataset.autofilled = '1';
                    tindakField.dataset.userEdited = '';
                }
            }
        };

        if (capaianField) {
            capaianField.addEventListener('change', handleAutoFill);
            if (capaianField.value && (!deskripsiField || deskripsiField.value.trim() === '') && (!tindakField || tindakField.value.trim() === '')) {
                handleAutoFill();
            }
        }
    });

    function buildSummary(studentId, capaianAkhir) {
        const assessments = studentTpMap[studentId] || [];
        const hasAssessment = assessments.length > 0;
        const baseList = hasAssessment ? assessments : tpCatalog;

        if (!baseList.length) {
            return { description: '', followUp: defaultFollowUp(capaianAkhir) };
        }

        const sorted = [...baseList].sort((a, b) => {
            const orderDiff = (levelOrder[b.capaian] ?? 0) - (levelOrder[a.capaian] ?? 0);
            if (orderDiff !== 0) {
                return orderDiff;
            }
            const urutanA = a.urutan ?? 9999;
            const urutanB = b.urutan ?? 9999;
            if (urutanA !== urutanB) {
                return urutanA - urutanB;
            }
            return (a.kode || '').localeCompare(b.kode || '');
        });

        const strengths = hasAssessment ? sorted.filter((item) => strengthLevels.has(item.capaian)).slice(0, 2) : sorted.slice(0, 2);
        const needs = hasAssessment ? sorted.filter((item) => followUpLevels.has(item.capaian)).slice(0, 3) : sorted.slice(0, 3);

        const descriptionParts = [];
        if (strengths.length) {
            descriptionParts.push('Kekuatan: ' + formatTpList(strengths));
        } else if (sorted.length) {
            descriptionParts.push('Capaian utama: ' + formatTpList(sorted.slice(0, 2)));
        }

        const followUpParts = [];
        if (needs.length) {
            followUpParts.push('Fokus penguatan: ' + formatTpList(needs));
        } else {
            followUpParts.push(defaultFollowUp(capaianAkhir));
        }

        return {
            description: descriptionParts.join(' ').trim(),
            followUp: followUpParts.join(' ').trim(),
        };
    }

    function formatTpList(items) {
        return items
            .map((item) => {
                const kode = item.kode || 'TP';
                const elemen = item.elemen || '';
                const sub = item.sub_elemen || '';
                const desc = item.deskripsi || item.judul || '';
                const metaParts = [];
                if (elemen) metaParts.push(elemen);
                if (sub) metaParts.push(sub);
                const meta = metaParts.length ? ` (${metaParts.join(' · ')})` : '';
                const detail = desc ? `: ${truncate(desc)}` : '';
                return `${kode}${meta}${detail}`;
            })
            .join('; ');
    }

    function truncate(text, limit = 110) {
        if (!text) {
            return '';
        }
        return text.length > limit ? text.slice(0, limit).trim() + '…' : text;
    }

    function defaultFollowUp(level) {
        switch (level) {
            case 'SB':
                return 'Pertahankan capaian tinggi dan beri tantangan lanjutan sesuai TP.';
            case 'BSH':
                return 'Konsolidasi pemahaman dengan latihan rutin pada seluruh TP.';
            case 'MB':
                return 'Perbanyak pendampingan pada TP yang belum konsisten hingga sesuai harapan.';
            case 'BB':
                return 'Berikan remedi bertahap pada TP dasar sampai mencapai perkembangan awal.';
            default:
                return '';
        }
    }
})();
</script>
<?php endif; ?>
