<?php
    $assignment = isset($assignment) && is_array($assignment) ? $assignment : [];
    $setting = isset($setting) && is_array($setting) ? $setting : [];
    $classOptions = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $selectedClass = isset($selectedClass) && is_array($selectedClass) ? $selectedClass : null;
    $selectedClassLabel = '-';
    if ($selectedClass !== null) {
        $labelParts = [];
        if (!empty($selectedClass['tingkat'])) {
            $labelParts[] = (string) $selectedClass['tingkat'];
        }
        if (!empty($selectedClass['nama'])) {
            $labelParts[] = (string) $selectedClass['nama'];
        }
        $selectedClassLabel = trim(implode(' ', $labelParts));
        if ($selectedClassLabel === '') {
            $selectedClassLabel = $selectedClass['nama'] ?? '-';
        }
    }
    $students = isset($students) && is_array($students) ? $students : [];
    $ledgerRows = isset($ledgerRows) && is_array($ledgerRows) ? $ledgerRows : [];
    $skillEnabled = isset($skillEnabled) ? (bool) $skillEnabled : true;
    $isKurmer = isset($isKurmer) ? (bool) $isKurmer : false;
    $assignmentId = (int) ($assignment['id'] ?? 0);
    $kkmEnabled = (int) ($setting['enable_kkm'] ?? 0) === 1;
    $kkmValue = $setting['nilai_kkm'] ?? null;
    $hasSkillData = $skillEnabled;

    if (!$hasSkillData) {
        foreach ($ledgerRows as $row) {
            if (!empty($row['skill'])) {
                $hasSkillData = true;
                break;
            }
        }
    }

    $formatScore = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        $floatValue = (float) $value;

        if (abs($floatValue - round($floatValue)) < 0.01) {
            return number_format($floatValue, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($floatValue, 2, ',', '.'), '0'), ',');
    };

    $extractValue = static function (?array $record, string $key): mixed {
        return is_array($record) ? ($record[$key] ?? null) : null;
    };
?>

<div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                <?= htmlspecialchars($assignment['mata_pelajaran_kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <h2 class="text-xl font-semibold text-slate-800">
                Legger Nilai - <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                <?= $isKurmer
                    ? 'Rekap capaian Kurikulum Merdeka (TP, deskripsi mapel) untuk kelas terpilih.'
                    : 'Rekap nilai pengetahuan dan keterampilan yang telah diinput oleh guru mata pelajaran.' ?>
                Gunakan pilihan kelas untuk melihat daftar siswa lengkap beserta deskripsi penilaian.
            </p>
        </div>
        <div class="flex flex-col gap-3 md:items-end">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs text-slate-600 shadow-sm">
                <p class="font-semibold text-slate-700">Informasi Penilaian</p>
                <p class="mt-1">
                    Kurikulum: <span class="font-semibold <?= $isKurmer ? 'text-emerald-600' : 'text-indigo-600' ?>">
                        <?= $isKurmer ? 'KurMer' : 'K13' ?>
                    </span>
                </p>
                <?php if (!$isKurmer): ?>
                    <p>
                        Pengetahuan: <span class="font-semibold text-indigo-600">Aktif</span><br>
                        Keterampilan:
                        <?php if ($hasSkillData): ?>
                            <span class="font-semibold text-teal-600">Aktif</span>
                        <?php else: ?>
                            <span class="font-semibold text-slate-500">Tidak Aktif</span>
                        <?php endif; ?>
                    </p>
                    <p class="mt-1">
                        KKM:
                        <?php if ($kkmEnabled && $kkmValue !== null): ?>
                            <span class="font-semibold text-amber-600"><?= htmlspecialchars((string) $kkmValue, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="font-semibold text-slate-500">Tidak digunakan</span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-slate-600">Mode capaian TP (BB/MB/BSH/SB) dengan deskripsi mapel.</p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="<?= htmlspecialchars(base_url('guru/nilai?focus=' . $assignmentId), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                >
                    <i class="ri-arrow-go-back-line text-sm"></i> Kembali ke daftar mapel
                </a>
                <a
                    href="<?= htmlspecialchars(base_url('guru/nilai/riwayat'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-100"
                >
                    <i class="ri-time-line text-sm"></i> Riwayat Legger
                </a>
            </div>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
        <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas</h3>
                <p class="text-sm text-slate-500">
                    Pilih kelas sesuai penugasan Anda pada mata pelajaran ini untuk melihat rekap nilai yang tersimpan.
                </p>
            </div>
            <form method="get" class="flex items-center gap-3">
                <select
                    name="kelas_id"
                    onchange="this.form.submit()"
                    class="block w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                >
                    <option value="">-- Pilih kelas --</option>
                    <?php foreach ($classOptions as $class): ?>
                        <?php $classId = (int) ($class['id'] ?? 0); ?>
                        <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' ' . ($class['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <?php if ($selectedClassId === null): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                Pilih kelas terlebih dahulu untuk menampilkan legger nilai.
            </div>
        <?php elseif (empty($ledgerRows)): ?>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                Belum ada nilai pengetahuan maupun keterampilan yang tersimpan untuk kelas ini.
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan legger untuk
                    <span class="font-semibold text-slate-700">
                        <?= htmlspecialchars($selectedClassLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    (<?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>).
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/legger/export/pdf?kelas_id=' . $selectedClassId), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-indigo-300 hover:text-indigo-600"
                    >
                        <i class="ri-file-pdf-line text-base text-red-500"></i>
                        Ekspor PDF
                    </a>
                    <a
                        href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/legger/export/excel?kelas_id=' . $selectedClassId), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-600 shadow-sm hover:bg-emerald-100"
                    >
                        <i class="ri-file-excel-line text-base"></i>
                        Ekspor Excel
                    </a>
                </div>
            </div>

            <?php if ($isKurmer): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 w-12">No</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[220px]">Nama Siswa</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[140px]">Capaian Akhir</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[220px]">Deskripsi Utama</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[160px]">Tindak Lanjut</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[120px]">Nilai Opsional</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php foreach ($ledgerRows as $index => $row): ?>
                                <?php
                                    $student = isset($row['student']) && is_array($row['student']) ? $row['student'] : [];
                                    $summary = isset($row['kurmer_summary']) && is_array($row['kurmer_summary']) ? $row['kurmer_summary'] : null;
                                    $capaian = $summary['capaian_akhir_enum'] ?? $summary['capaian'] ?? null;
                                    $deskripsi = $summary['deskripsi_umum'] ?? $summary['description'] ?? null;
                                    $tindakLanjut = $summary['tindak_lanjut'] ?? null;
                                    $nilaiOpsional = $summary['nilai_opsional'] ?? $summary['score'] ?? null;
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600"><?= $index + 1 ?></td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800">
                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            NIPD: <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($student['nisn'])): ?>
                                                &middot; NISN: <?= htmlspecialchars($student['nisn'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?php if (!empty($capaian)): ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                                <?= htmlspecialchars(strtoupper((string) $capaian), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <p class="text-xs text-slate-600 whitespace-pre-line">
                                            <?= htmlspecialchars($deskripsi ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <p class="text-xs text-slate-600 whitespace-pre-line">
                                            <?= htmlspecialchars($tindakLanjut ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?= $formatScore($nilaiOpsional) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th rowspan="2" class="px-4 py-3 text-left font-semibold text-slate-600 w-12">No</th>
                                <th rowspan="2" class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[220px]">Nama Siswa</th>
                                <th colspan="4" class="px-4 py-3 text-center font-semibold text-indigo-600">Pengetahuan</th>
                                <?php if ($hasSkillData): ?>
                                    <th colspan="3" class="px-4 py-3 text-center font-semibold text-teal-600">Keterampilan</th>
                                <?php endif; ?>
                            </tr>
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-slate-600">Nilai KD</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-600">UTS</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-600">UAS</th>
                                <th class="px-4 py-2 text-left font-semibold text-slate-600">Nilai Akhir &amp; Predikat</th>
                                <?php if ($hasSkillData): ?>
                                    <th class="px-4 py-2 text-left font-semibold text-slate-600">Nilai Akhir</th>
                                    <th class="px-4 py-2 text-left font-semibold text-slate-600">Predikat</th>
                                    <th class="px-4 py-2 text-left font-semibold text-slate-600">Deskripsi Keterampilan</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php foreach ($ledgerRows as $index => $row): ?>
                                <?php
                                    $student = isset($row['student']) && is_array($row['student']) ? $row['student'] : [];
                                    $knowledge = isset($row['knowledge']) && is_array($row['knowledge']) ? $row['knowledge'] : null;
                                    $skill = isset($row['skill']) && is_array($row['skill']) ? $row['skill'] : null;
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600"><?= $index + 1 ?></td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-800">
                                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            NIPD: <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($student['nisn'])): ?>
                                                &middot; NISN: <?= htmlspecialchars($student['nisn'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?= $formatScore($extractValue($knowledge, 'nilai_kd')) ?>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?= $formatScore($extractValue($knowledge, 'nilai_uts')) ?>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?= $formatScore($extractValue($knowledge, 'nilai_uas')) ?>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <p class="font-semibold text-slate-800">
                                            <?= $formatScore($extractValue($knowledge, 'nilai_akhir')) ?>
                                            <?php if (is_array($knowledge) && !empty($knowledge['predikat'])): ?>
                                                <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600">
                                                    <?= htmlspecialchars($knowledge['predikat'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (is_array($knowledge) && !empty($knowledge['deskripsi'])): ?>
                                            <p class="mt-1 text-xs text-slate-500">
                                                <?= nl2br(htmlspecialchars($knowledge['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($hasSkillData): ?>
                                        <td class="px-4 py-3 align-top text-slate-700">
                                            <?= $formatScore($extractValue($skill, 'nilai_akhir')) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-slate-700">
                                            <?php if (is_array($skill) && !empty($skill['predikat'])): ?>
                                                <span class="inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-600">
                                                    <?= htmlspecialchars($skill['predikat'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-slate-700">
                                            <?php if (is_array($skill) && !empty($skill['deskripsi'])): ?>
                                                <p class="text-xs text-slate-500 whitespace-pre-line">
                                                    <?= htmlspecialchars($skill['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-500">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
