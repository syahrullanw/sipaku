<?php
    $classes = isset($classes) && is_array($classes) ? $classes : [];
    $assignments = isset($assignments) && is_array($assignments) ? $assignments : [];
    $ledgerRows = isset($ledgerRows) && is_array($ledgerRows) ? $ledgerRows : [];
    $students = isset($students) && is_array($students) ? $students : [];
    $selectedClassId = isset($selectedClassId) ? (int) $selectedClassId : null;
    $selectedClass = isset($selectedClass) && is_array($selectedClass) ? $selectedClass : null;
    $hasSkillData = isset($hasSkillData) ? (bool) $hasSkillData : false;
    $isKurmer = isset($isKurmer) ? (bool) $isKurmer : false;

    $formatScore = static function (?float $value): string {
        if ($value === null) {
            return '-';
        }

        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.01) {
            return number_format($rounded, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($rounded, 2, ',', '.'), '0'), ',');
    };

    $formatSmallScore = static function (?float $value) use ($formatScore): string {
        if ($value === null) {
            return '-';
        }

        return $formatScore($value);
    };

    $selectedClassLabel = '-';
    $selectedClassYear = '-';
    $selectedClassMajor = null;
    if ($selectedClass !== null) {
        $parts = [];
        if (!empty($selectedClass['tingkat'])) {
            $parts[] = (string) $selectedClass['tingkat'];
        }
        if (!empty($selectedClass['nama'])) {
            $parts[] = (string) $selectedClass['nama'];
        }
        $selectedClassLabel = trim(implode(' ', $parts));
        if ($selectedClassLabel === '') {
            $selectedClassLabel = $selectedClass['nama'] ?? '-';
        }
        $selectedClassYear = $selectedClass['tahun_ajaran_nama'] ?? '-';
        $selectedClassMajor = $selectedClass['jurusan_nama'] ?? null;
    }

    $assignmentCount = count($assignments);
    $studentCount = count($students);
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                Rekap Nilai Kelas
            </p>
            <h2 class="text-xl font-semibold text-slate-800">
                Legger Nilai Kelas
            </h2>
            <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                Ringkasan nilai semua mata pelajaran dalam satu kelas untuk membantu wali kelas memonitor capaian siswa.
                Nilai yang berada di bawah standar KKM ditandai warna merah sebagai perhatian khusus.
            </p>
        </div>
        <?php if ($selectedClass !== null): ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                    <p class="font-semibold text-slate-700">Kelas</p>
                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($selectedClassLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-slate-500"><?= htmlspecialchars($selectedClassYear, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($selectedClassMajor)): ?>
                        <p class="mt-1 text-slate-500">Jurusan: <?= htmlspecialchars($selectedClassMajor, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-slate-500">Kurikulum: <span class="font-semibold <?= $isKurmer ? 'text-emerald-600' : 'text-indigo-600' ?>"><?= $isKurmer ? 'KurMer' : 'K13' ?></span></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                    <p class="font-semibold text-slate-700">Ringkasan</p>
                    <p class="mt-1 text-slate-500">
                        Siswa: <span class="font-semibold text-slate-800"><?= $studentCount ?></span><br>
                        Mata Pelajaran: <span class="font-semibold text-slate-800"><?= $assignmentCount ?></span>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($classes)): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Anda belum terdaftar sebagai wali kelas pada tahun ajaran aktif. Hubungi admin untuk memastikan penugasan wali kelas sudah diperbarui.
        </div>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Pilih Kelas</h3>
                    <p class="text-sm text-slate-500">
                        Legger menampilkan seluruh mapel yang terdaftar dalam tahun ajaran dan jurusan kelas terpilih.
                    </p>
                </div>
                <form method="get" class="flex items-center gap-3">
                    <select
                        name="kelas_id"
                        onchange="this.form.submit()"
                        class="block w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                    >
                        <option value="">-- Pilih kelas --</option>
                        <?php foreach ($classes as $class): ?>
                            <?php $classId = (int) ($class['id'] ?? 0); ?>
                            <?php
                                $labelParts = [];
                                if (!empty($class['tingkat'])) {
                                    $labelParts[] = (string) $class['tingkat'];
                                }
                                if (!empty($class['nama'])) {
                                    $labelParts[] = (string) $class['nama'];
                                }
                                $classLabel = trim(implode(' ', $labelParts));
                                if ($classLabel === '') {
                                    $classLabel = $class['nama'] ?? '-';
                                }
                            ?>
                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $classId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </header>

            <?php if ($selectedClass === null): ?>
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                    Pilih kelas terlebih dahulu untuk menampilkan legger nilai.
                </div>
            <?php elseif ($assignmentCount === 0): ?>
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                    Belum ada mata pelajaran yang terdaftar pada tahun ajaran kelas ini.
                </div>
            <?php elseif ($studentCount === 0): ?>
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                Belum ada siswa terdaftar pada kelas ini.
            </div>
            <?php else: ?>
                <?php if (!$isKurmer): ?>
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-3 w-3 items-center justify-center rounded-full bg-red-100 ring-2 ring-red-300"></span>
                            <span>Nilai pengetahuan di bawah KKM guru mapel.</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            Nilai total merupakan jumlah nilai pengetahuan akhir yang tersedia untuk setiap siswa.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        Mode Kurikulum Merdeka: menampilkan capaian TP (BB/MB/BSH/SB) dan deskripsi mapel per siswa. Nilai total memakai angka opsional jika diisi.
                    </div>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 w-12">No</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[220px]">Nama Siswa</th>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php
                                        $assignmentId = (int) ($assignment['id'] ?? 0);
                                        $kkmEnabled = !$isKurmer && (bool) ($assignment['kkm_enabled'] ?? false);
                                        $kkmValue = !$isKurmer ? ($assignment['kkm_value'] ?? null) : null;
                                    ?>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[140px]">
                                        <span class="block text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                            <?= htmlspecialchars($assignment['code'] !== '' ? $assignment['code'] : $assignmentId, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <span class="block text-sm font-semibold text-slate-700">
                                            <?= htmlspecialchars($assignment['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <?php if ($kkmEnabled && $kkmValue !== null): ?>
                                            <span class="mt-1 block text-[11px] font-semibold text-amber-600">
                                                KKM: <?= htmlspecialchars($formatSmallScore((float) $kkmValue), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[160px]">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php foreach ($ledgerRows as $index => $row): ?>
                                <?php
                                    $student = isset($row['student']) && is_array($row['student']) ? $row['student'] : [];
                                    $subjects = isset($row['subjects']) && is_array($row['subjects']) ? $row['subjects'] : [];
                                    $totalScore = array_key_exists('total_score', $row) ? $row['total_score'] : null;
                                    $scoreCount = (int) ($row['score_count'] ?? 0);
                                    $averageScore = array_key_exists('average_score', $row) ? $row['average_score'] : null;
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
                                    <?php foreach ($assignments as $assignment): ?>
                                        <?php
                                            $assignmentId = (int) ($assignment['id'] ?? 0);
                                            $subjectData = $subjects[$assignmentId] ?? null;
                                            $knowledgeScore = $subjectData['knowledge_score'] ?? null;
                                            $knowledgePredicate = trim((string) ($subjectData['knowledge_predicate'] ?? ''));
                                            $skillScore = $subjectData['skill_score'] ?? null;
                                            $skillPredicate = trim((string) ($subjectData['skill_predicate'] ?? ''));
                                            $belowStandard = !$isKurmer && (bool) ($subjectData['below_standard'] ?? false);

                                            $cellClasses = 'px-4 py-3 align-top text-slate-700';
                                            if ($belowStandard) {
                                                $cellClasses = 'px-4 py-3 align-top bg-red-50 text-red-600 font-semibold';
                                            }
                                            $kurmerSummary = $subjectData['kurmer_summary'] ?? null;
                                        ?>
                                        <td class="<?= $cellClasses ?>">
                                            <?php if ($isKurmer): ?>
                                                <?php if (!empty($kurmerSummary['capaian_akhir_enum'] ?? $kurmerSummary['capaian'] ?? null)): ?>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                        <?= htmlspecialchars(strtoupper((string) ($kurmerSummary['capaian_akhir_enum'] ?? $kurmerSummary['capaian'])), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">Belum ada capaian</span>
                                                <?php endif; ?>
                                                <?php if (!empty($kurmerSummary['deskripsi_umum'] ?? $kurmerSummary['description'] ?? null)): ?>
                                                    <p class="mt-2 text-xs text-slate-600 whitespace-pre-line">
                                                        <?= htmlspecialchars($kurmerSummary['deskripsi_umum'] ?? $kurmerSummary['description'], ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($kurmerSummary['nilai_opsional'] ?? $kurmerSummary['score'] ?? null)): ?>
                                                    <p class="mt-2 text-xs text-slate-500">Nilai: <span class="font-semibold"><?= htmlspecialchars($formatSmallScore((float) ($kurmerSummary['nilai_opsional'] ?? $kurmerSummary['score'])), ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($knowledgeScore !== null): ?>
                                                    <span class="block text-sm font-semibold">
                                                        <?= htmlspecialchars($formatScore((float) $knowledgeScore), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <?php if ($knowledgePredicate !== ''): ?>
                                                        <span class="mt-0.5 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">
                                                            <?= htmlspecialchars($knowledgePredicate, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-xs <?= $belowStandard ? 'text-red-500/80' : 'text-slate-400' ?>">Belum ada nilai</span>
                                                <?php endif; ?>

                                                <?php if ($hasSkillData): ?>
                                                    <span class="mt-2 block text-xs <?= $belowStandard ? 'text-red-600/70' : 'text-slate-500' ?>">
                                                        Keterampilan:
                                                        <span class="font-semibold">
                                                            <?= htmlspecialchars($formatSmallScore($skillScore !== null ? (float) $skillScore : null), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <?php if ($skillPredicate !== ''): ?>
                                                            (<span><?= htmlspecialchars($skillPredicate, ENT_QUOTES, 'UTF-8') ?></span>)
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?php if ($totalScore !== null): ?>
                                            <p class="text-base font-semibold text-slate-800">
                                                <?= htmlspecialchars($formatScore((float) $totalScore), ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                Mapel dinilai: <?= $scoreCount ?>
                                            </p>
                                            <?php if ($averageScore !== null): ?>
                                                <p class="text-xs text-slate-500">
                                                    Rata-rata: <?= htmlspecialchars($formatSmallScore((float) $averageScore), ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Belum ada nilai</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
