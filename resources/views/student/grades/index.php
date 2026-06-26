<?php
    use App\Models\Subject;

    $reportData = isset($report) && is_array($report) ? $report : [];

    $studentData = isset($reportData['student']) && is_array($reportData['student']) ? $reportData['student'] : null;
    $classData = isset($reportData['class']) && is_array($reportData['class']) ? $reportData['class'] : null;
    $schoolYearData = isset($reportData['school_year']) && is_array($reportData['school_year']) ? $reportData['school_year'] : null;

    $subjectsData = isset($reportData['subjects']) && is_array($reportData['subjects']) ? $reportData['subjects'] : [];
    $summaryData = isset($reportData['summary']) && is_array($reportData['summary']) ? $reportData['summary'] : null;

    $attitudesData = isset($reportData['attitudes']) && is_array($reportData['attitudes']) ? $reportData['attitudes'] : [];
    $attendanceData = isset($reportData['attendance']) && is_array($reportData['attendance']) ? $reportData['attendance'] : null;
    $achievementsData = isset($reportData['achievements']) && is_array($reportData['achievements']) ? $reportData['achievements'] : [];
    $extracurricularData = isset($reportData['extracurriculars']) && is_array($reportData['extracurriculars']) ? $reportData['extracurriculars'] : [];
    $prakerinData = isset($reportData['prakerin']) && is_array($reportData['prakerin']) ? $reportData['prakerin'] : null;
    $homeroomNote = isset($reportData['homeroom_note']) ? trim((string) $reportData['homeroom_note']) : '';

    $studentName = $studentData !== null ? (string) ($studentData['nama'] ?? '') : '';
    $studentNisn = $studentData !== null ? (string) ($studentData['nisn'] ?? '') : '';
    $studentNipd = $studentData !== null ? (string) ($studentData['nipd'] ?? '') : '';

    $classLabel = null;
    if ($classData !== null) {
        $grade = (string) ($classData['tingkat'] ?? '');
        $name = (string) ($classData['nama'] ?? '');
        $major = (string) ($classData['jurusan_nama'] ?? '');
        $classLabel = trim(sprintf('Kelas %s %s', $grade, $name));
        if ($major !== '') {
            $classLabel = trim($classLabel . ' • ' . $major);
        }
    }

    $schoolYearLabel = 'Tidak diketahui';
    if ($schoolYearData !== null) {
        $schoolYearName = trim((string) ($schoolYearData['nama'] ?? ''));
        $semesterActive = (int) ($schoolYearData['semester_aktif'] ?? 0);
        $semesterLabel = $semesterActive === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
        $schoolYearLabel = $schoolYearName !== ''
            ? sprintf('%s • %s', $schoolYearName, $semesterLabel)
            : $semesterLabel;
    }

    $knowledgeAverage = $summaryData !== null && $summaryData['knowledge_average'] !== null
        ? (float) $summaryData['knowledge_average']
        : null;
    $skillAverage = $summaryData !== null && $summaryData['skill_average'] !== null
        ? (float) $summaryData['skill_average']
        : null;
    $overallAverage = $summaryData !== null && $summaryData['overall_average'] !== null
        ? (float) $summaryData['overall_average']
        : null;
    $subjectsTotal = $summaryData !== null ? (int) ($summaryData['total_subjects'] ?? 0) : 0;
    $subjectsCompleted = $summaryData !== null ? (int) ($summaryData['completed_subjects'] ?? 0) : 0;
    $subjectsFull = $summaryData !== null ? (int) ($summaryData['subjects_with_full_scores'] ?? 0) : 0;
    $subjectsPending = $summaryData !== null ? (int) ($summaryData['pending_subjects'] ?? max(0, $subjectsTotal - $subjectsCompleted)) : max(0, $subjectsTotal - $subjectsCompleted);
    $lastUpdatedRaw = $summaryData !== null ? (string) ($summaryData['last_updated_at'] ?? '') : '';
    $lastUpdatedLabel = $lastUpdatedRaw !== '' && strtotime($lastUpdatedRaw) !== false
        ? date('d M Y H:i', strtotime($lastUpdatedRaw))
        : null;
    $recentEntriesData = $summaryData !== null && isset($summaryData['recent_entries']) && is_array($summaryData['recent_entries'])
        ? array_values(array_filter($summaryData['recent_entries'], static fn ($entry) => is_array($entry)))
        : [];
    $recentEntriesCount = count($recentEntriesData);

    $curriculumRaw = isset($reportData['curriculum']) ? (string) $reportData['curriculum'] : 'k13';
    $curriculum = strtolower($curriculumRaw) === 'kurmer' ? 'kurmer' : 'k13';
    $isKurmer = $curriculum === 'kurmer';
    $curriculumLabel = $isKurmer ? 'Kurikulum Merdeka' : 'Kurikulum 2013';
    $curriculumBadgeClasses = $isKurmer
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'
        : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200';

    $kurmerLevelLabels = [
        'BB' => 'Belum Berkembang',
        'MB' => 'Mulai Berkembang',
        'BSH' => 'Berkembang Sesuai Harapan',
        'SB' => 'Sangat Berkembang',
    ];

    $formatScore = static function ($value): string {
        if ($value === null) {
            return '—';
        }

        if (!is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    };

    $subjectGroupLabels = [];
    foreach (Subject::GROUPS as $group) {
        $subjectGroupLabels[$group['code']] = $group['label'];
    }

    $groupedSubjects = [];
    foreach ($subjectsData as $subject) {
        if (!is_array($subject)) {
            continue;
        }
        $groupCode = (string) ($subject['subject_group'] ?? '');
        if ($groupCode === '') {
            $groupCode = 'other';
        }

        if (!isset($groupedSubjects[$groupCode])) {
            $groupedSubjects[$groupCode] = [
                'code' => $groupCode,
                'label' => $subjectGroupLabels[$groupCode] ?? 'Kelompok Lainnya',
                'subjects' => [],
            ];
        }

        $groupedSubjects[$groupCode]['subjects'][] = $subject;
    }

    $selectedYearId = isset($selectedSchoolYearId) ? (int) $selectedSchoolYearId : 0;
    $schoolYearsOptions = isset($schoolYears) && is_array($schoolYears) ? $schoolYears : [];
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">Profil Siswa</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-800 dark:text-gray-100">
                    <?= htmlspecialchars($studentName !== '' ? $studentName : 'Siswa', ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-300">
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 font-medium <?= htmlspecialchars($curriculumBadgeClasses, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="ri-book-3-line text-sm"></i>
                        <?= htmlspecialchars($curriculumLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if ($classLabel !== null): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
                            <i class="ri-community-line text-sm"></i>
                            <?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($studentNipd !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
                            <i class="ri-id-card-line text-sm"></i>
                            NIPD <?= htmlspecialchars($studentNipd, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($studentNisn !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600 dark:bg-slate-800/60 dark:text-slate-200">
                            <i class="ri-fingerprint-line text-sm"></i>
                            NISN <?= htmlspecialchars($studentNisn, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="get" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300" for="school-year-select">
                    Tahun Ajaran
                </label>
                <div class="flex items-center gap-2">
                    <select
                        id="school-year-select"
                        name="school_year_id"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200 dark:focus:ring-indigo-500"
                    >
                        <option value="">Aktif</option>
                        <?php foreach ($schoolYearsOptions as $year): ?>
                            <?php
                                if (!is_array($year)) {
                                    continue;
                                }
                                $yearId = (int) ($year['id'] ?? 0);
                                $yearName = (string) ($year['nama'] ?? '');
                                $semesterActive = (int) ($year['semester_aktif'] ?? 0);
                                $semesterLabel = $semesterActive === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
                                $label = $yearName !== '' ? sprintf('%s • %s', $yearName, $semesterLabel) : $semesterLabel;
                            ?>
                            <option value="<?= $yearId ?>" <?= $selectedYearId === $yearId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        <i class="ri-refresh-line text-base"></i>
                        Tampilkan
                    </button>
                </div>
            </form>
        </div>
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-300">
            <?= htmlspecialchars($schoolYearLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <?php if ($isKurmer): ?>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Capaian Akhir</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900 dark:text-emerald-100">
                    <?= htmlspecialchars(number_format($subjectsCompleted), ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars(number_format($subjectsTotal), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-emerald-700 dark:text-emerald-200/80">Mapel sudah memiliki ringkasan capaian.</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 shadow-sm dark:border-sky-500/30 dark:bg-sky-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-500 dark:text-sky-200">Nilai Opsional</p>
                <p class="mt-2 text-2xl font-semibold text-sky-900 dark:text-sky-100">
                    <?= htmlspecialchars($formatScore($overallAverage), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-sky-600 dark:text-sky-200/80">Rata-rata jika guru mengisi nilai angka.</p>
            </div>
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-200">Aktivitas Terakhir</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-900 dark:text-indigo-100">
                    <?= htmlspecialchars($lastUpdatedLabel !== null ? $lastUpdatedLabel : 'Belum ada', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-indigo-600 dark:text-indigo-200/80">
                    <?= htmlspecialchars($recentEntriesCount > 0 ? ($recentEntriesCount . ' entri terbaru') : 'Menunggu penilaian terbaru', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-200">Rata-rata Nilai</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-900 dark:text-indigo-100">
                    <?= htmlspecialchars($overallAverage !== null ? number_format($overallAverage, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-indigo-600 dark:text-indigo-200/80">Gabungan nilai pengetahuan & keterampilan</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500 dark:text-emerald-200">Pengetahuan</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-900 dark:text-emerald-100">
                    <?= htmlspecialchars($knowledgeAverage !== null ? number_format($knowledgeAverage, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-emerald-600 dark:text-emerald-200/80"><?= number_format($subjectsCompleted) ?> dari <?= number_format($subjectsTotal) ?> mapel dinilai</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 shadow-sm dark:border-sky-500/30 dark:bg-sky-500/10">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-500 dark:text-sky-200">Keterampilan</p>
                <p class="mt-2 text-2xl font-semibold text-sky-900 dark:text-sky-100">
                    <?= htmlspecialchars($skillAverage !== null ? number_format($skillAverage, 2, ',', '.') : '—', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-xs text-sky-600 dark:text-sky-200/80">Nilai praktik & performa</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Daftar Nilai Mata Pelajaran</h2>
                <p class="text-xs text-slate-500 dark:text-slate-300">
                    <?= $isKurmer ? 'Ringkasan capaian Kurikulum Merdeka per mapel (BB/MB/BSH/SB).' : 'Ringkasan nilai pengetahuan dan keterampilan per mapel.' ?>
                </p>
            </div>
        </div>
        <?php if (empty($groupedSubjects)): ?>
            <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                <?= $isKurmer ? 'Belum ada ringkasan capaian Kurikulum Merdeka yang tersimpan untuk tahun ajaran ini.' : 'Belum ada nilai mata pelajaran yang tersedia untuk tahun ajaran ini.' ?>
            </p>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($groupedSubjects as $group): ?>
                    <?php
                        if (!is_array($group)) {
                            continue;
                        }
                        $groupLabel = (string) ($group['label'] ?? 'Kelompok Lainnya');
                        $groupSubjects = isset($group['subjects']) && is_array($group['subjects']) ? $group['subjects'] : [];
                    ?>
                    <div class="rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/30">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <div class="mt-3 overflow-x-auto">
                            <?php if ($isKurmer): ?>
                                <table class="min-w-full divide-y divide-slate-100 text-sm text-slate-600 dark:divide-slate-800 dark:text-slate-200">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">Kode</th>
                                            <th class="px-4 py-3 text-left font-semibold">Mata Pelajaran</th>
                                            <th class="px-4 py-3 text-left font-semibold">Capaian Akhir</th>
                                            <th class="px-4 py-3 text-left font-semibold">Deskripsi &amp; Tindak Lanjut</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai Opsional</th>
                                            <th class="px-4 py-3 text-left font-semibold">Guru Pengampu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <?php foreach ($groupSubjects as $subject): ?>
                                            <?php
                                                $subjectCode = (string) ($subject['subject_code'] ?? '');
                                                $subjectName = (string) ($subject['subject_name'] ?? 'Mata Pelajaran');
                                                $teacherName = (string) ($subject['teacher_name'] ?? '');
                                                $summary = isset($subject['kurmer_summary']) && is_array($subject['kurmer_summary']) ? $subject['kurmer_summary'] : [];

                                                $capaianCode = strtoupper(trim((string) ($summary['capaian_akhir_enum'] ?? $summary['capaian'] ?? '')));
                                                $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : null;
                                                $description = trim((string) ($summary['deskripsi_umum'] ?? $summary['description'] ?? ''));
                                                $tindakLanjut = trim((string) ($summary['tindak_lanjut'] ?? ''));
                                                $opsionalScore = $summary['nilai_opsional'] ?? $summary['score'] ?? null;

                                                $tpSourcesRaw = $summary['sumber_tp'] ?? $summary['tp_sources'] ?? [];
                                                if (is_string($tpSourcesRaw)) {
                                                    $decoded = json_decode($tpSourcesRaw, true);
                                                    $tpSourcesRaw = is_array($decoded) ? $decoded : [];
                                                }
                                                $tpSources = array_values(array_filter($tpSourcesRaw, static fn ($item) => is_array($item)));
                                                $tpSummary = '';
                                                if (!empty($tpSources)) {
                                                    $tpParts = [];
                                                    $usedCount = 0;
                                                    foreach (array_slice($tpSources, 0, 2) as $tp) {
                                                        $usedCount++;
                                                        $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? ''));
                                                        $tpDesc = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));
                                                        $label = $code !== '' ? $code : 'TP';
                                                        $tpParts[] = $tpDesc !== '' ? ($label !== '' ? $label . ' - ' . $tpDesc : $tpDesc) : $label;
                                                    }
                                                    $remaining = count($tpSources) - $usedCount;
                                                    if ($remaining > 0) {
                                                        $tpParts[] = $remaining . ' TP lain';
                                                    }
                                                    $tpParts = array_values(array_filter($tpParts, static fn ($item) => $item !== ''));
                                                    $tpSummary = implode('; ', $tpParts);
                                                }
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 font-semibold text-indigo-600 dark:text-indigo-300">
                                                    <?= htmlspecialchars($subjectCode !== '' ? $subjectCode : '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-100">
                                                    <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($capaianLabel !== null): ?>
                                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-100">
                                                            <?= htmlspecialchars($capaianCode !== '' ? $capaianCode : '-', ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if ($capaianLabel !== '' && $capaianLabel !== $capaianCode): ?>
                                                                <span class="ml-1 text-[11px] font-normal text-emerald-800 dark:text-emerald-100/90">(<?= htmlspecialchars($capaianLabel, ENT_QUOTES, 'UTF-8') ?>)</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-400 dark:text-slate-500">Belum dinilai</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($description !== '' || $tindakLanjut !== '' || $tpSummary !== ''): ?>
                                                        <?php if ($description !== ''): ?>
                                                            <p class="text-sm text-slate-700 dark:text-slate-100"><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($tindakLanjut !== ''): ?>
                                                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-200">Tindak lanjut: <?= nl2br(htmlspecialchars($tindakLanjut, ENT_QUOTES, 'UTF-8')) ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($tpSummary !== ''): ?>
                                                            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">TP: <?= htmlspecialchars($tpSummary, ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-400 dark:text-slate-500">Belum ada narasi.</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">
                                                    <?= htmlspecialchars($formatScore($opsionalScore), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3 text-slate-500 dark:text-slate-300">
                                                    <?= htmlspecialchars($teacherName !== '' ? $teacherName : '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <table class="min-w-full divide-y divide-slate-100 text-sm text-slate-600 dark:divide-slate-800 dark:text-slate-200">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">Kode</th>
                                            <th class="px-4 py-3 text-left font-semibold">Mata Pelajaran</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai Pengetahuan</th>
                                            <th class="px-4 py-3 text-left font-semibold">Nilai Keterampilan</th>
                                            <th class="px-4 py-3 text-left font-semibold">Deskripsi</th>
                                            <th class="px-4 py-3 text-left font-semibold">Guru Pengampu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <?php foreach ($groupSubjects as $subject): ?>
                                            <?php
                                                $subjectCode = (string) ($subject['subject_code'] ?? '');
                                                $subjectName = (string) ($subject['subject_name'] ?? 'Mata Pelajaran');
                                                $teacherName = (string) ($subject['teacher_name'] ?? '');

                                                $knowledgeScore = isset($subject['knowledge_score']) && $subject['knowledge_score'] !== null
                                                    ? number_format((float) $subject['knowledge_score'], 2, ',', '.')
                                                    : '—';
                                                $knowledgePredicate = isset($subject['knowledge_predicate']) ? (string) $subject['knowledge_predicate'] : '';
                                                $knowledgeDescription = trim((string) ($subject['knowledge_description'] ?? ''));

                                                $skillScore = isset($subject['skill_score']) && $subject['skill_score'] !== null
                                                    ? number_format((float) $subject['skill_score'], 2, ',', '.')
                                                    : '—';
                                                $skillPredicate = isset($subject['skill_predicate']) ? (string) $subject['skill_predicate'] : '';
                                                $skillDescription = trim((string) ($subject['skill_description'] ?? ''));
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 font-semibold text-indigo-600 dark:text-indigo-300">
                                                    <?= htmlspecialchars($subjectCode !== '' ? $subjectCode : '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-100">
                                                    <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <span class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($knowledgeScore, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php if ($knowledgePredicate !== ''): ?>
                                                            <span class="text-xs text-slate-500 dark:text-slate-300">Predikat <?= htmlspecialchars($knowledgePredicate, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <span class="font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars($skillScore, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php if ($skillPredicate !== ''): ?>
                                                            <span class="text-xs text-slate-500 dark:text-slate-300">Predikat <?= htmlspecialchars($skillPredicate, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <?php if ($knowledgeDescription !== '' || $skillDescription !== ''): ?>
                                                        <ul class="list-inside space-y-1 text-xs text-slate-500 dark:text-slate-300">
                                                            <?php if ($knowledgeDescription !== ''): ?>
                                                                <li><span class="font-semibold text-slate-600 dark:text-slate-200">Pengetahuan:</span> <?= htmlspecialchars($knowledgeDescription, ENT_QUOTES, 'UTF-8') ?></li>
                                                            <?php endif; ?>
                                                            <?php if ($skillDescription !== ''): ?>
                                                                <li><span class="font-semibold text-slate-600 dark:text-slate-200">Keterampilan:</span> <?= htmlspecialchars($skillDescription, ENT_QUOTES, 'UTF-8') ?></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-xs text-slate-400 dark:text-slate-500">Belum ada deskripsi.</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-slate-500 dark:text-slate-300">
                                                    <?= htmlspecialchars($teacherName !== '' ? $teacherName : '—', ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-200">Sikap Spiritual</h3>
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-100">
                    <?= htmlspecialchars(trim((string) ($attitudesData['spiritual'] ?? ''))) !== '' ? htmlspecialchars(trim((string) ($attitudesData['spiritual'] ?? '')), ENT_QUOTES, 'UTF-8') : 'Belum ada catatan sikap spiritual.' ?>
                </p>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm dark:border-blue-500/30 dark:bg-blue-500/10">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-200">Sikap Sosial</h3>
                <p class="mt-2 text-sm text-blue-700 dark:text-blue-100">
                    <?= htmlspecialchars(trim((string) ($attitudesData['social'] ?? ''))) !== '' ? htmlspecialchars(trim((string) ($attitudesData['social'] ?? '')), ENT_QUOTES, 'UTF-8') : 'Belum ada catatan sikap sosial.' ?>
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Catatan Wali Kelas</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-200">
                    <?= $homeroomNote !== '' ? htmlspecialchars($homeroomNote, ENT_QUOTES, 'UTF-8') : 'Belum ada catatan khusus dari wali kelas.' ?>
                </p>
            </div>
        </div>
        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">Absensi</h3>
                <?php if ($attendanceData === null): ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-300">Belum ada data absensi yang tercatat.</p>
                <?php else: ?>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm text-slate-600 dark:text-slate-200">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Sakit</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-700 dark:text-slate-100"><?= number_format((int) ($attendanceData['sakit'] ?? 0)) ?> hari</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Izin</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-700 dark:text-slate-100"><?= number_format((int) ($attendanceData['izin'] ?? 0)) ?> hari</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Alpha</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-700 dark:text-slate-100"><?= number_format((int) ($attendanceData['alpa'] ?? 0)) ?> hari</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Bolos</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-700 dark:text-slate-100"><?= number_format((int) ($attendanceData['bolos'] ?? 0)) ?> hari</dd>
                        </div>
                    </dl>
                <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-fuchsia-100 bg-fuchsia-50 p-5 shadow-sm dark:border-fuchsia-500/30 dark:bg-fuchsia-500/10">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-fuchsia-600 dark:text-fuchsia-200">Ekstrakurikuler</h3>
                <?php if (empty($extracurricularData)): ?>
                    <p class="mt-2 text-sm text-fuchsia-700 dark:text-fuchsia-100">Belum ada penilaian ekstrakurikuler.</p>
                <?php else: ?>
                    <ul class="mt-3 space-y-2 text-sm text-fuchsia-800 dark:text-fuchsia-100">
                        <?php foreach ($extracurricularData as $activity): ?>
                            <?php
                                if (!is_array($activity)) {
                                    continue;
                                }
                                $activityName = (string) ($activity['ekstrakurikuler_nama'] ?? 'Ekstrakurikuler');
                                $finalScore = isset($activity['nilai_akhir']) && $activity['nilai_akhir'] !== null
                                    ? number_format((float) $activity['nilai_akhir'], 2, ',', '.')
                                    : '—';
                                $predicate = (string) ($activity['predikat'] ?? '');
                                $description = trim((string) ($activity['deskripsi'] ?? ''));
                            ?>
                            <li class="rounded-xl border border-fuchsia-100 bg-white px-4 py-3 text-fuchsia-700 shadow-sm dark:border-fuchsia-400/40 dark:bg-fuchsia-950/30 dark:text-fuchsia-100">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-semibold"><?= htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-fuchsia-100 px-2.5 py-0.5 text-xs font-semibold text-fuchsia-700 dark:bg-fuchsia-500/30 dark:text-fuchsia-100">
                                        <i class="ri-star-smile-line"></i>
                                        <?= htmlspecialchars($finalScore, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($predicate !== ''): ?>
                                            <span>(<?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($description !== ''): ?>
                                    <p class="mt-2 text-xs text-fuchsia-600 dark:text-fuchsia-200/80"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-teal-100 bg-teal-50 p-5 shadow-sm dark:border-teal-500/30 dark:bg-teal-500/10">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-teal-600 dark:text-teal-200">Praktik Kerja Lapangan (Prakerin)</h3>
            <?php if ($prakerinData === null): ?>
                <p class="mt-2 text-sm text-teal-700 dark:text-teal-100">Belum ada penilaian prakerin yang tercatat.</p>
            <?php else: ?>
                <dl class="mt-3 space-y-2 text-sm text-teal-700 dark:text-teal-100">
                    <?php if (!empty($prakerinData['place_name'] ?? '')): ?>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-teal-500 dark:text-teal-200">Tempat</dt>
                            <dd class="mt-1"><?= htmlspecialchars((string) $prakerinData['place_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($prakerinData['start_date'] ?? '') || !empty($prakerinData['end_date'] ?? '')): ?>
                        <?php
                            $startLabel = !empty($prakerinData['start_date']) ? date('d M Y', strtotime((string) $prakerinData['start_date'])) : '-';
                            $endLabel = !empty($prakerinData['end_date']) ? date('d M Y', strtotime((string) $prakerinData['end_date'])) : '-';
                        ?>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-teal-500 dark:text-teal-200">Periode</dt>
                            <dd class="mt-1"><?= htmlspecialchars($startLabel, ENT_QUOTES, 'UTF-8') ?> &mdash; <?= htmlspecialchars($endLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php
                        $scores = isset($prakerinData['scores']) && is_array($prakerinData['scores']) ? $prakerinData['scores'] : [];
                        $finalScore = isset($scores['final']) && $scores['final'] !== null ? number_format((float) $scores['final'], 2, ',', '.') : '—';
                        $predicate = isset($prakerinData['predicate']) ? (string) $prakerinData['predicate'] : '';
                    ?>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-teal-500 dark:text-teal-200">Nilai Akhir</dt>
                        <dd class="mt-1 font-semibold text-teal-800 dark:text-teal-100">
                            <?= htmlspecialchars($finalScore, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($predicate !== ''): ?>
                                <span class="text-xs text-teal-500 dark:text-teal-200/80">(<?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="grid gap-2 text-xs text-teal-600 dark:text-teal-200/80 sm:grid-cols-2">
                        <span>Keaktifan: <?= isset($scores['activity']) && $scores['activity'] !== null ? htmlspecialchars(number_format((float) $scores['activity'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') : '—' ?></span>
                        <span>Jurnal: <?= isset($scores['journal']) && $scores['journal'] !== null ? htmlspecialchars(number_format((float) $scores['journal'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') : '—' ?></span>
                        <span>Laporan: <?= isset($scores['report']) && $scores['report'] !== null ? htmlspecialchars(number_format((float) $scores['report'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') : '—' ?></span>
                    </div>
                </dl>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-purple-100 bg-purple-50 p-5 shadow-sm dark:border-purple-500/30 dark:bg-purple-500/10">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-purple-600 dark:text-purple-200">Prestasi</h3>
            <?php if (empty($achievementsData)): ?>
                <p class="mt-2 text-sm text-purple-700 dark:text-purple-100">Belum ada prestasi yang tercatat.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm text-purple-800 dark:text-purple-100">
                    <?php foreach ($achievementsData as $achievement): ?>
                        <?php
                            if (!is_array($achievement)) {
                                continue;
                            }
                            $title = (string) ($achievement['judul'] ?? 'Prestasi');
                            $description = trim((string) ($achievement['keterangan'] ?? ''));
                        ?>
                        <li class="rounded-xl border border-purple-100 bg-white px-4 py-3 shadow-sm dark:border-purple-400/40 dark:bg-purple-950/40">
                            <p class="font-semibold"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($description !== ''): ?>
                                <p class="mt-1 text-xs text-purple-600 dark:text-purple-200/80"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
