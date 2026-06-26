<?php
    $teacher = is_array($teacher ?? null) ? $teacher : [];
    $teacherAccount = is_array($teacherAccount ?? null) ? $teacherAccount : [];
    $teachingHistoryGroups = is_array($teachingHistoryGroups ?? null) ? $teachingHistoryGroups : [];
    $teachingHistorySummary = is_array($teachingHistorySummary ?? null) ? $teachingHistorySummary : [
        'assignments' => 0,
        'subjects' => 0,
        'classes' => 0,
        'semesters' => 0,
    ];
    $genderOptions = is_array($genderOptions ?? null) ? $genderOptions : ['L' => 'Laki-laki', 'P' => 'Perempuan'];
    $religionOptions = is_array($religionOptions ?? null) ? $religionOptions : [];
    $maritalStatusOptions = is_array($maritalStatusOptions ?? null) ? $maritalStatusOptions : [];
    $employmentStatusOptions = is_array($employmentStatusOptions ?? null) ? $employmentStatusOptions : [];
    $educationOptions = is_array($educationOptions ?? null) ? $educationOptions : [];
    $studyStatusOptions = is_array($studyStatusOptions ?? null) ? $studyStatusOptions : [];
    $activeSchoolYear = is_array($activeSchoolYear ?? null) ? $activeSchoolYear : null;
    $demoModeEnabled = (bool) ($demoModeEnabled ?? false);
    $teacherId = (int) ($teacher['id'] ?? 0);
    $teacherName = (string) ($teacher['nama'] ?? 'Guru');
    $isActive = (string) ($teacher['status'] ?? 'aktif') === 'aktif';
    $returnUrl = trim((string) ($returnUrl ?? 'master/guru'));
    $returnLabel = trim((string) ($returnLabel ?? 'Daftar Guru'));
    $editUrl = trim((string) ($editUrl ?? ('master/guru?edit=' . urlencode((string) $teacherId))));
    $editLabel = trim((string) ($editLabel ?? 'Edit Data'));
    $showEditAction = (bool) ($showEditAction ?? true);

    $formatDate = static function (mixed $value): string {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return $raw;
        }

        return date('d M Y', $timestamp);
    };
    $valueOrDash = static function (mixed $value): string {
        $text = trim((string) $value);

        return $text !== '' ? $text : '-';
    };
    $optionLabel = static function (array $options, mixed $value) use ($valueOrDash): string {
        $key = trim((string) $value);
        if ($key === '') {
            return '-';
        }

        return (string) ($options[$key] ?? $valueOrDash($key));
    };
    $classLabel = static function (array $classroom): string {
        $parts = [];
        $level = trim((string) ($classroom['tingkat'] ?? ''));
        $name = trim((string) ($classroom['nama'] ?? ''));
        $major = trim((string) ($classroom['jurusan_nama'] ?? ''));

        if ($level !== '') {
            $parts[] = $level;
        }
        if ($name !== '') {
            $parts[] = $name;
        }

        $label = trim(implode(' ', $parts));
        if ($label === '') {
            $label = 'Kelas';
        }
        if ($major !== '') {
            $label .= ' - ' . $major;
        }

        return $label;
    };

    $birthLabel = trim($valueOrDash($teacher['tempat_lahir'] ?? '') . ', ' . $formatDate($teacher['tanggal_lahir'] ?? null), ' ,-');
    $taskTokens = array_values(array_filter(
        array_map('trim', preg_split('/[,;\n]+/', (string) ($teacher['tugas_tambahan'] ?? '')) ?: []),
        static fn (string $value): bool => $value !== ''
    ));
    $activeSemesterLabel = null;
    if ($activeSchoolYear !== null) {
        $semester = (int) ($activeSchoolYear['semester_aktif'] ?? 1);
        $activeSemesterLabel = sprintf(
            '%s - %s',
            (string) ($activeSchoolYear['nama'] ?? 'Tahun Ajaran Aktif'),
            $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }
?>

<div class="space-y-6">
    <?php if ($demoModeEnabled): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
            <p class="font-semibold">Mode demo aktif</p>
            <p class="mt-1 text-xs text-amber-800/90">Data pribadi guru disamarkan.</p>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="break-words text-xl font-semibold text-slate-900"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                        <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    <?= htmlspecialchars($optionLabel($employmentStatusOptions, $teacher['status_kepegawaian'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    &middot;
                    <?= htmlspecialchars($optionLabel($genderOptions, $teacher['jenis_kelamin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    &middot;
                    <?= htmlspecialchars($birthLabel !== '' ? $birthLabel : '-', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($taskTokens as $task): ?>
                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                            <?= htmlspecialchars($task, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($taskTokens)): ?>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Tidak ada tugas tambahan</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars(base_url($returnUrl), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                    <i class="ri-arrow-left-line"></i>
                    <?= htmlspecialchars($returnLabel !== '' ? $returnLabel : 'Kembali', ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php if ($showEditAction && !$demoModeEnabled && $editUrl !== ''): ?>
                    <a href="<?= htmlspecialchars(base_url($editUrl), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        <i class="ri-pencil-line"></i>
                        <?= htmlspecialchars($editLabel !== '' ? $editLabel : 'Edit Data', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Data Guru</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Username</dt>
                        <dd class="mt-1 font-mono text-indigo-700"><?= htmlspecialchars($valueOrDash($teacherAccount['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">NIP</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['nip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">NUPTK</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['nuptk'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">NIK</dt>
                        <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Agama</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($optionLabel($religionOptions, $teacher['agama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status Kawin</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($optionLabel($maritalStatusOptions, $teacher['status_perkawinan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Ibu Kandung</dt>
                        <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['nama_ibu_kandung'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                </dl>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Kepegawaian</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis GTK</dt>
                        <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['jenis_gtk'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sekolah Induk</dt>
                        <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['sekolah_induk'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Surat Tugas</dt>
                        <dd class="mt-1 text-slate-700">
                            <?= htmlspecialchars($valueOrDash($teacher['nomor_surat_tugas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            &middot;
                            <?= htmlspecialchars($formatDate($teacher['tanggal_surat_tugas'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">SK / TMT</dt>
                        <dd class="mt-1 text-slate-700">
                            <?= htmlspecialchars($valueOrDash($teacher['sk_pengangkatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            &middot;
                            <?= htmlspecialchars($formatDate($teacher['tmt_pengangkatan'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pendidikan</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($optionLabel($educationOptions, $teacher['pendidikan_terakhir'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status Kuliah</dt>
                            <dd class="mt-1 text-slate-700"><?= htmlspecialchars($optionLabel($studyStatusOptions, $teacher['status_kuliah'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </div>
                </dl>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Kontak</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</dt>
                        <dd class="mt-1 break-all text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Telepon</dt>
                        <dd class="mt-1 text-slate-700"><?= htmlspecialchars($valueOrDash($teacher['telepon'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Alamat</dt>
                        <dd class="mt-1 text-slate-700"><?= nl2br(htmlspecialchars($valueOrDash($teacher['alamat'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Semester Lampau</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) ($teachingHistorySummary['semesters'] ?? 0) ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penugasan</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) ($teachingHistorySummary['assignments'] ?? 0) ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mapel</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) ($teachingHistorySummary['subjects'] ?? 0) ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) ($teachingHistorySummary['classes'] ?? 0) ?></p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Riwayat Mengajar Semester Lampau</h3>
                    <?php if ($activeSemesterLabel !== null): ?>
                        <p class="mt-1 text-xs text-slate-500">Semester aktif saat ini tidak ditampilkan: <?= htmlspecialchars($activeSemesterLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
                    <?php endif; ?>
                </div>
                <?php if (empty($teachingHistoryGroups)): ?>
                    <div class="px-6 py-10 text-center text-sm text-slate-400">
                        Belum ada riwayat mengajar pada semester lampau.
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($teachingHistoryGroups as $group): ?>
                            <?php $assignments = is_array($group['assignments'] ?? null) ? $group['assignments'] : []; ?>
                            <section class="px-6 py-5">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-800">
                                            <?= htmlspecialchars((string) ($group['school_year_name'] ?? 'Tahun Ajaran'), ENT_QUOTES, 'UTF-8') ?>
                                        </h4>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($group['semester_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                        <?= count($assignments) ?> penugasan
                                    </span>
                                </div>
                                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100">
                                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-4 py-3">Mata Pelajaran</th>
                                                <th class="px-4 py-3">Kategori</th>
                                                <th class="px-4 py-3">Kelas</th>
                                                <th class="px-4 py-3">Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <?php foreach ($assignments as $assignment): ?>
                                                <?php
                                                    $classes = is_array($assignment['classes'] ?? null) ? $assignment['classes'] : [];
                                                    $subjectCode = trim((string) ($assignment['mata_pelajaran_kode'] ?? ''));
                                                    $subjectName = trim((string) ($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran'));
                                                    $subjectLabel = $subjectCode !== '' ? $subjectCode . ' - ' . $subjectName : $subjectName;
                                                    $majorLabel = trim((string) ($assignment['mata_pelajaran_jurusan_nama'] ?? ''));
                                                ?>
                                                <tr class="align-top">
                                                    <td class="px-4 py-3">
                                                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php if ($majorLabel !== ''): ?>
                                                            <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($majorLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-500">
                                                        <?= htmlspecialchars($valueOrDash($assignment['mata_pelajaran_jenis'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <?php if (empty($classes)): ?>
                                                            <span class="text-xs text-slate-400">Belum ada kelas tercatat</span>
                                                        <?php else: ?>
                                                            <div class="flex flex-wrap gap-1.5">
                                                                <?php foreach ($classes as $classroom): ?>
                                                                    <?php if (is_array($classroom)): ?>
                                                                        <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                                                            <?= htmlspecialchars($classLabel($classroom), ENT_QUOTES, 'UTF-8') ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-500">
                                                        <?= htmlspecialchars($valueOrDash($assignment['catatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
