<?php
    $selectedYearId = (int) ($selectedYearId ?? 0);
    $semester = (int) ($semester ?? 1);
    $selectedLevel = isset($selectedLevel) ? (int) $selectedLevel : 0;
    $draft = is_array($draft ?? null) ? $draft : null;
    $draftId = (int) ($draft['id'] ?? 0);
    $draftItems = is_array($draftItems ?? null) ? $draftItems : [];
    $classes = is_array($classes ?? null) ? $classes : [];
    $availableClasses = is_array($availableClasses ?? null) ? $availableClasses : $classes;
    $selectedClassIds = array_values(array_unique(array_filter(array_map('intval', is_array($selectedClassIds ?? null) ? $selectedClassIds : []))));
    $gridRows = is_array($gridRows ?? null) ? $gridRows : [];
    $teacherRecap = is_array($teacherRecap ?? null) ? $teacherRecap : [];
    $conflicts = is_array($conflicts ?? null) ? $conflicts : [];
    $targets = is_array($targets ?? null) ? $targets : [];
    $preferences = is_array($preferences ?? null) ? $preferences : [];
    $timePreferences = is_array($timePreferences ?? null) ? $timePreferences : [];
    $parallelGroups = is_array($parallelGroups ?? null) ? $parallelGroups : [];
    $assignmentOptions = is_array($assignmentOptions ?? null) ? $assignmentOptions : [];
    $assignmentClassMap = is_array($assignmentClassMap ?? null) ? $assignmentClassMap : [];
    $classOptions = is_array($classOptions ?? null) ? $classOptions : [];
    $dayOptions = is_array($dayOptions ?? null) ? $dayOptions : [];
    $roomOptions = is_array($roomOptions ?? null) ? $roomOptions : [];
    $yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
    $levelOptions = is_array($levelOptions ?? null) ? $levelOptions : [];
    $schedulePreviewUrl = (string) ($schedulePreviewUrl ?? '');
    $schoolName = trim((string) ($schoolName ?? config('app.name', 'Sekolah')));
    $selectedYearLabel = $yearOptions[$selectedYearId] ?? '-';
    $semesterLabel = $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
    $levelLabel = $selectedLevel > 0 ? 'Tingkat ' . $selectedLevel : 'Semua Tingkat';
    $draftStatus = (string) ($draft['status'] ?? '');
    $placedCount = count(array_filter($draftItems, static fn (array $item): bool => ($item['status'] ?? '') !== 'failed'));
    $failedCount = count(array_filter($draftItems, static fn (array $item): bool => ($item['status'] ?? '') === 'failed'));
    $lockedCount = count(array_filter($draftItems, static fn (array $item): bool => !empty($item['is_locked'])));
    $conflictCount = array_reduce($conflicts, static fn (int $carry, array $messages): int => $carry + count($messages), 0);
    $parallelRows = $parallelGroups;
    for ($blankParallelIndex = 0; $blankParallelIndex < 3; $blankParallelIndex++) {
        $parallelRows[] = ['guru_mata_pelajaran_id' => 0, 'nama' => '', 'kelas_ids' => []];
    }
    $pref = static fn (string $key, int $default): int => (int) ($preferences[$key] ?? $default);
    $timePref = static fn (string $key, mixed $default): mixed => $timePreferences[$key] ?? $default;
    $timeInput = static function (mixed $value): string {
        $text = trim((string) $value);
        return $text !== '' ? substr($text, 0, 5) : '';
    };
    $teacherOptions = [];
    foreach ($draftItems as $optionItem) {
        $teacherId = (int) ($optionItem['guru_id'] ?? 0);
        if ($teacherId > 0 && !isset($teacherOptions[$teacherId])) {
            $teacherOptions[$teacherId] = (string) ($optionItem['guru_nama'] ?? 'Guru #' . $teacherId);
        }
    }
    asort($teacherOptions);
    $baseQuery = [
        'tahun_ajaran_id' => $selectedYearId,
        'semester' => $semester,
    ];
    if ($selectedLevel > 0) {
        $baseQuery['tingkat'] = $selectedLevel;
    }
    if ($draftId > 0) {
        $baseQuery['draft_id'] = $draftId;
    }
    if (!empty($selectedClassIds)) {
        $baseQuery['class_ids'] = $selectedClassIds;
    }
    $currentQuery = http_build_query($baseQuery);
    $currentUrl = base_url('akademik/jadwal/generate' . ($currentQuery !== '' ? '?' . $currentQuery : ''));
    $availableClassIds = array_values(array_filter(array_map(static fn (array $classroom): int => (int) ($classroom['id'] ?? 0), $availableClasses)));
    $selectedClassSet = array_fill_keys($selectedClassIds, true);
    $allClassesChecked = empty($selectedClassIds) || (!empty($availableClassIds) && count(array_intersect($availableClassIds, $selectedClassIds)) === count($availableClassIds));
    $selectedClassCount = !empty($selectedClassIds) ? count($selectedClassIds) : count($availableClassIds);
    $classLabel = static function (array $row): string {
        $label = trim('Kelas ' . (string) ($row['tingkat'] ?? $row['kelas_tingkat'] ?? '-') . ' ' . (string) ($row['nama'] ?? $row['kelas_nama'] ?? '-'));
        if (!empty($row['jurusan_nama'])) {
            $label .= ' (' . $row['jurusan_nama'] . ')';
        }
        return $label;
    };
    $conflictLabels = [
        'teacher_conflicts' => 'Guru Bentrok',
        'class_conflicts' => 'Kelas Bentrok',
        'room_conflicts' => 'Ruang Bentrok',
        'blocked_slots' => 'Slot Terblokir',
        'unavailable_teachers' => 'Guru Tidak Tersedia',
        'missing_hours' => 'Jam Kurang',
        'teacher_overloads' => 'Beban Guru',
        'empty_slots' => 'Slot Kosong',
        'failed_items' => 'Gagal Dijadwalkan',
    ];
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex-1 space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tahun_ajaran_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun Pelajaran</label>
                        <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                            <?php foreach ($yearOptions as $id => $label): ?>
                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedYearId === (int) $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="semester" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Semester</label>
                        <select id="semester" name="semester" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                            <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                            <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
                        </select>
                    </div>
                    <div>
                        <label for="tingkat" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Tingkat</label>
                        <select id="tingkat" name="tingkat" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                            <option value="">Semua Tingkat</option>
                            <?php foreach ($levelOptions as $level => $label): ?>
                                <option value="<?= htmlspecialchars((string) $level, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedLevel === (int) $level ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="ri-filter-3-line text-base"></i>
                            Tampilkan
                        </button>
                    </div>
                </div>
                <?php if (!empty($availableClasses)): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas Diikutkan Generate</div>
                                <div class="text-xs text-slate-500"><?= number_format($selectedClassCount) ?> dari <?= number_format(count($availableClassIds)) ?> kelas</div>
                            </div>
                            <label class="inline-flex w-fit items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" data-class-filter-all <?= $allClassesChecked ? 'checked' : '' ?>>
                                Semua Kelas
                            </label>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <?php foreach ($availableClasses as $classroom): ?>
                                <?php
                                    $classId = (int) ($classroom['id'] ?? 0);
                                    if ($classId <= 0) {
                                        continue;
                                    }
                                    $checked = empty($selectedClassIds) || isset($selectedClassSet[$classId]);
                                ?>
                                <label class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                    <input type="checkbox" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" data-class-filter-checkbox <?= $checked ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($classLabel($classroom), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
            <div class="flex flex-wrap gap-2">
                <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tingkat" value="<?= $selectedLevel > 0 ? htmlspecialchars((string) $selectedLevel, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <input type="hidden" name="preserve_draft_id" value="<?= htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') ?>">
                    <div data-class-hidden-fields>
                        <?php foreach ($selectedClassIds as $classId): ?>
                            <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60" data-requires-class-selection <?= $selectedYearId > 0 ? '' : 'disabled' ?>>
                        <i class="ri-magic-line text-base"></i>
                        Generate
                    </button>
                </form>
                <?php if ($draftId > 0): ?>
                    <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/' . $draftId . '/validate'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                        <?= csrf_field() ?>
                        <div data-class-hidden-fields>
                            <?php foreach ($selectedClassIds as $classId): ?>
                                <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50">
                            <i class="ri-shield-check-line text-base"></i>
                            Validasi
                        </button>
                    </form>
                    <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/' . $draftId . '/activate'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Tetapkan draft ini sebagai jadwal aktif? Jadwal aktif kelas terkait akan diarsipkan.');">
                        <?= csrf_field() ?>
                        <div data-class-hidden-fields>
                            <?php foreach ($selectedClassIds as $classId): ?>
                                <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                            <i class="ri-checkbox-circle-line text-base"></i>
                            Tetapkan Aktif
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Preferensi Jam</h2>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Membentuk Slot Jam</span>
        </div>
        <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/time-preferences'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="tingkat" value="<?= $selectedLevel > 0 ? htmlspecialchars((string) $selectedLevel, ENT_QUOTES, 'UTF-8') : '' ?>">
            <input type="hidden" name="draft_id" value="<?= htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') ?>">
            <div data-class-hidden-fields>
                <?php foreach ($selectedClassIds as $classId): ?>
                    <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="jam_masuk" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Jam Masuk</label>
                    <input type="time" id="jam_masuk" name="jam_masuk" value="<?= htmlspecialchars($timeInput($timePref('jam_masuk', '07:00')), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="durasi_jp_menit" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi JP</label>
                    <input type="number" min="20" max="90" id="durasi_jp_menit" name="durasi_jp_menit" value="<?= htmlspecialchars((string) $timePref('durasi_jp_menit', 45), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="jeda_jp_menit" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Jeda Antar JP</label>
                    <input type="number" min="0" max="20" id="jeda_jp_menit" name="jeda_jp_menit" value="<?= htmlspecialchars((string) $timePref('jeda_jp_menit', 0), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="jumlah_jp_per_hari" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">JP Per Hari</label>
                    <input type="number" min="1" max="14" id="jumlah_jp_per_hari" name="jumlah_jp_per_hari" value="<?= htmlspecialchars((string) $timePref('jumlah_jp_per_hari', 8), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label for="istirahat_pertama_setelah_jp" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Istirahat Setelah JP</label>
                    <input type="number" min="0" max="14" id="istirahat_pertama_setelah_jp" name="istirahat_pertama_setelah_jp" value="<?= htmlspecialchars((string) $timePref('istirahat_pertama_setelah_jp', 4), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="durasi_istirahat_pertama_menit" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi Istirahat</label>
                    <input type="number" min="0" max="120" id="durasi_istirahat_pertama_menit" name="durasi_istirahat_pertama_menit" value="<?= htmlspecialchars((string) $timePref('durasi_istirahat_pertama_menit', 15), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="istirahat_dzuhur_setelah_jp" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Dzuhur Setelah JP</label>
                    <input type="number" min="0" max="14" id="istirahat_dzuhur_setelah_jp" name="istirahat_dzuhur_setelah_jp" value="<?= htmlspecialchars((string) $timePref('istirahat_dzuhur_setelah_jp', 6), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="durasi_istirahat_dzuhur_menit" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi Dzuhur</label>
                    <input type="number" min="0" max="150" id="durasi_istirahat_dzuhur_menit" name="durasi_istirahat_dzuhur_menit" value="<?= htmlspecialchars((string) $timePref('durasi_istirahat_dzuhur_menit', 45), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="durasi_istirahat_jumat_menit" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi Jumat</label>
                    <input type="number" min="0" max="180" id="durasi_istirahat_jumat_menit" name="durasi_istirahat_jumat_menit" value="<?= htmlspecialchars((string) $timePref('durasi_istirahat_jumat_menit', 75), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-60" <?= $selectedYearId > 0 ? '' : 'disabled' ?>>
                    <i class="ri-time-line text-base"></i>
                    Simpan Preferensi Jam
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Preferensi Generate</h2>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">Dibaca saat Generate</span>
        </div>
        <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/preferences'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="tingkat" value="<?= $selectedLevel > 0 ? htmlspecialchars((string) $selectedLevel, ENT_QUOTES, 'UTF-8') : '' ?>">
            <input type="hidden" name="draft_id" value="<?= htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') ?>">
            <div data-class-hidden-fields>
                <?php foreach ($selectedClassIds as $classId): ?>
                    <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
            </div>
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label for="blok_produktif_min" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Blok Produktif Min</label>
                    <input type="number" min="1" max="4" id="blok_produktif_min" name="blok_produktif_min" value="<?= htmlspecialchars((string) $pref('blok_produktif_min', 2), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="blok_produktif_maks" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Blok Produktif Maks</label>
                    <input type="number" min="2" max="4" id="blok_produktif_maks" name="blok_produktif_maks" value="<?= htmlspecialchars((string) $pref('blok_produktif_maks', 4), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="blok_umum_maks" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Blok Umum Maks</label>
                    <input type="number" min="1" max="2" id="blok_umum_maks" name="blok_umum_maks" value="<?= htmlspecialchars((string) $pref('blok_umum_maks', 2), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
                <div>
                    <label for="maks_mapel_berat_berurutan" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Maks Mapel Berat</label>
                    <input type="number" min="1" max="4" id="maks_mapel_berat_berurutan" name="maks_mapel_berat_berurutan" value="<?= htmlspecialchars((string) $pref('maks_mapel_berat_berurutan', 2), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-4">
                <?php foreach ([
                    'prioritas_praktik_pagi' => 'Praktik Pagi',
                    'hindari_mapel_sama_per_hari' => 'Mapel Sama/Hari',
                    'sebar_beban_guru' => 'Sebar Beban Guru',
                    'rapatkan_jadwal_kelas' => 'Rapatkan Kelas',
                ] as $key => $label): ?>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="hidden" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="0">
                        <input type="checkbox" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= $pref($key, 1) === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </label>
                <?php endforeach; ?>
            </div>
            <details class="rounded-xl border border-slate-200 px-4 py-3">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Bobot dan Penalti</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <?php foreach ([
                        'bobot_jam_guru_harian' => ['Bobot Guru/Hari', 7],
                        'bobot_jam_kelas_harian' => ['Bobot Kelas/Hari', 3],
                        'penalti_slot_sore_produktif' => ['Penalti Praktik Sore', 25],
                        'penalti_mapel_sama_hari' => ['Penalti Mapel Sama', 30],
                        'penalti_jam_kosong_guru' => ['Penalti Kosong Guru', 18],
                        'penalti_jam_kosong_kelas' => ['Penalti Kosong Kelas', 15],
                        'penalti_mapel_berat_berurutan' => ['Penalti Berat Berurutan', 22],
                    ] as $key => [$label, $default]): ?>
                        <div>
                            <label for="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="block text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                            <input type="number" min="0" max="99" id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $pref($key, (int) $default), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60" <?= $selectedYearId > 0 ? '' : 'disabled' ?>>
                    <i class="ri-save-3-line text-base"></i>
                    Simpan Preferensi
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Preferensi Kelas Paralel</h2>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700"><?= number_format(count($parallelGroups)) ?> grup aktif</span>
        </div>
        <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/parallel-preferences'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="tingkat" value="<?= $selectedLevel > 0 ? htmlspecialchars((string) $selectedLevel, ENT_QUOTES, 'UTF-8') : '' ?>">
            <input type="hidden" name="draft_id" value="<?= htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') ?>">
            <div data-class-hidden-fields>
                <?php foreach ($selectedClassIds as $classId): ?>
                    <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <?php foreach ($parallelRows as $rowIndex => $group): ?>
                    <?php
                        $parallelAssignmentId = (int) ($group['guru_mata_pelajaran_id'] ?? 0);
                        $parallelClassIds = array_values(array_unique(array_filter(array_map('intval', is_array($group['kelas_ids'] ?? null) ? $group['kelas_ids'] : []))));
                    ?>
                    <div class="rounded-xl border border-slate-200 p-4" data-parallel-group-row>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="parallel_assignment_<?= (int) $rowIndex ?>" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Mapel/Guru</label>
                                <select id="parallel_assignment_<?= (int) $rowIndex ?>" name="parallel_groups[<?= (int) $rowIndex ?>][guru_mata_pelajaran_id]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" data-parallel-assignment-select>
                                    <option value="">-</option>
                                    <?php foreach ($assignmentOptions as $assignmentId => $label): ?>
                                        <option value="<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" <?= $parallelAssignmentId === (int) $assignmentId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="parallel_name_<?= (int) $rowIndex ?>" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Grup</label>
                                <input id="parallel_name_<?= (int) $rowIndex ?>" type="text" name="parallel_groups[<?= (int) $rowIndex ?>][nama]" value="<?= htmlspecialchars((string) ($group['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2" data-parallel-class-options data-group-index="<?= (int) $rowIndex ?>" data-selected-classes="<?= htmlspecialchars(implode(',', $parallelClassIds), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($parallelAssignmentId > 0 && isset($assignmentClassMap[$parallelAssignmentId])): ?>
                                <?php foreach ($assignmentClassMap[$parallelAssignmentId] as $classId => $label): ?>
                                    <label class="flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">
                                        <input type="checkbox" name="parallel_groups[<?= (int) $rowIndex ?>][kelas_ids][]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= in_array((int) $classId, $parallelClassIds, true) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600 disabled:cursor-not-allowed disabled:opacity-60" <?= $selectedYearId > 0 ? '' : 'disabled' ?>>
                    <i class="ri-git-merge-line text-base"></i>
                    Simpan Kelas Paralel
                </button>
            </div>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Konteks</p>
            <p class="mt-2 text-sm font-semibold text-slate-800"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($semesterLabel . ' - ' . $levelLabel, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Draft</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900"><?= $draftId > 0 ? '#' . htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') : '-' ?></p>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($draftStatus !== '' ? ucfirst($draftStatus) : 'Belum ada draft', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Terjadwal</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700"><?= number_format($placedCount) ?></p>
            <p class="text-xs text-slate-500"><?= number_format($lockedCount) ?> terkunci</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Gagal</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700"><?= number_format($failedCount) ?></p>
            <p class="text-xs text-slate-500">Perlu penempatan manual</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catatan Validasi</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700"><?= number_format($conflictCount) ?></p>
            <p class="text-xs text-slate-500"><?= count($targets) ?> target mapel-kelas</p>
        </div>
    </div>

    <?php if ($draftId > 0): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-base font-semibold text-slate-800"><?= htmlspecialchars((string) ($draft['nama'] ?? 'Draft Jadwal'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-xs text-slate-500">Dibuat <?= htmlspecialchars($draft['created_at'] ? date('d/m/Y H:i', strtotime((string) $draft['created_at'])) : '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/' . $draftId . '/export'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex flex-wrap items-center gap-2">
                    <?php foreach ($selectedClassIds as $classId): ?>
                        <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                    <select name="scope_value" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring">
                        <option value="all:0">Semua Jadwal</option>
                        <?php if (!empty($classOptions)): ?>
                            <optgroup label="Per Kelas">
                                <?php foreach ($classOptions as $classId => $label): ?>
                                    <option value="kelas:<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($teacherOptions)): ?>
                            <optgroup label="Per Guru">
                                <?php foreach ($teacherOptions as $teacherOptionId => $label): ?>
                                    <option value="guru:<?= htmlspecialchars((string) $teacherOptionId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <button type="submit" name="format" value="xlsx" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                        <i class="ri-file-excel-2-line text-base"></i>
                        Excel
                    </button>
                    <button type="submit" name="format" value="pdf" class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                        <i class="ri-file-pdf-2-line text-base"></i>
                        PDF
                    </button>
                    <button type="submit" name="format" value="print" formtarget="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">
                        <i class="ri-printer-line text-base"></i>
                        Cetak F4
                    </button>
                </form>
                <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="semester" value="<?= htmlspecialchars((string) $semester, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tingkat" value="<?= $selectedLevel > 0 ? htmlspecialchars((string) $selectedLevel, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <input type="hidden" name="preserve_draft_id" value="<?= htmlspecialchars((string) $draftId, ENT_QUOTES, 'UTF-8') ?>">
                    <div data-class-hidden-fields>
                        <?php foreach ($selectedClassIds as $classId): ?>
                            <input type="hidden" name="class_ids[]" value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60" data-requires-class-selection>
                        <i class="ri-restart-line text-base"></i>
                        Generate Ulang
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-800">Output Jadwal</h2>
            <a href="<?= htmlspecialchars(base_url('akademik/jadwal'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                <i class="ri-calendar-line text-base"></i>
                Jadwal Manual
            </a>
        </div>
        <div class="overflow-x-auto bg-slate-50 p-4">
            <?php if (trim($schedulePreviewUrl) !== ''): ?>
                <div class="min-w-[1260px] rounded-lg border border-slate-200 bg-white shadow-sm">
                    <iframe
                        title="Preview Output Jadwal"
                        src="<?= htmlspecialchars($schedulePreviewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        class="h-[760px] w-full border-0 bg-white"
                    ></iframe>
                </div>
            <?php else: ?>
                <div class="px-4 py-10 text-center text-sm text-slate-500">Belum ada draft jadwal untuk konteks ini.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-800">Rekap Guru</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Guru</th>
                            <th class="px-5 py-3 text-center">JP</th>
                            <th class="px-5 py-3">Kelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($teacherRecap)): ?>
                            <?php foreach ($teacherRecap as $row): ?>
                                <tr>
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars((string) ($row['teacher_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars((string) ($row['teacher_code'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-5 py-3 text-center font-semibold text-slate-800"><?= htmlspecialchars((string) ($row['hours'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-5 py-3 text-xs text-slate-500"><?= htmlspecialchars(implode(', ', $row['classes'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">Belum ada rekap guru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-800">Validasi Konflik</h2>
            </div>
            <div class="max-h-[420px] overflow-y-auto p-5">
                <?php if ($conflictCount > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($conflictLabels as $key => $label): ?>
                            <?php $messages = $conflicts[$key] ?? []; ?>
                            <?php if (empty($messages)) { continue; } ?>
                            <div>
                                <div class="mb-2 inline-flex rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> · <?= count($messages) ?></div>
                                <ul class="space-y-1 text-xs text-slate-600">
                                    <?php foreach ($messages as $message): ?>
                                        <li class="rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">Tidak ada konflik pada validasi terakhir.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-800">Edit Draft</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Mapel/Guru</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Hari</th>
                        <th class="px-4 py-3">Jam</th>
                        <th class="px-4 py-3">JP</th>
                        <th class="px-4 py-3">Ruang</th>
                        <th class="px-4 py-3 text-center">Lock</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($draftItems)): ?>
                        <?php foreach ($draftItems as $item): ?>
                            <?php
                                $itemId = (int) ($item['id'] ?? 0);
                                $assignmentId = (int) ($item['guru_mata_pelajaran_id'] ?? 0);
	                                $selectedClassId = (int) ($item['kelas_id'] ?? 0);
	                                $selectedDay = (string) ($item['hari'] ?? '');
	                                $status = (string) ($item['status'] ?? '');
	                                $statusBadge = match ($status) {
	                                    'manual' => ['Manual', 'border-amber-200 bg-amber-50 text-amber-700'],
	                                    'fixed' => ['Fixed', 'border-indigo-200 bg-indigo-50 text-indigo-700'],
	                                    'failed' => ['Gagal', 'border-rose-200 bg-rose-50 text-rose-700'],
	                                    default => ['Generate', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
	                                };
	                                $itemFormId = 'schedule-item-form-' . $itemId;
	                            ?>
	                            <tr class="<?= $status === 'failed' ? 'bg-rose-50/40' : '' ?>">
	                                    <td class="min-w-[260px] px-4 py-3">
	                                        <form id="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/' . $draftId . '/items/' . $itemId . '/update'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="hidden">
	                                            <?= csrf_field() ?>
	                                        </form>
	                                        <select form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" name="guru_mata_pelajaran_id" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring" data-assignment-select>
	                                            <?php foreach ($assignmentOptions as $id => $label): ?>
	                                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $assignmentId === (int) $id ? 'selected' : '' ?>>
	                                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
	                                            <?php endforeach; ?>
	                                        </select>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold <?= htmlspecialchars($statusBadge[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusBadge[0], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($item['parallel_group_id'])): ?>
                                                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Paralel</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php if ($status === 'failed'): ?>
                                            <div class="mt-1 text-[11px] font-semibold text-rose-600"><?= htmlspecialchars((string) ($item['catatan'] ?? 'Gagal ditempatkan'), ENT_QUOTES, 'UTF-8') ?></div>
	                                        <?php endif; ?>
	                                    </td>
	                                    <td class="min-w-[180px] px-4 py-3">
	                                        <select form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" name="kelas_id" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring" data-class-select data-selected-class="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">
	                                            <?php foreach ($classOptions as $id => $label): ?>
	                                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === (int) $id ? 'selected' : '' ?>>
	                                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
	                                        </select>
	                                    </td>
	                                    <td class="min-w-[120px] px-4 py-3">
	                                        <select form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" name="hari" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring">
	                                            <?php foreach ($dayOptions as $day => $label): ?>
	                                                <option value="<?= htmlspecialchars((string) $day, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedDay === (string) $day ? 'selected' : '' ?>>
	                                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
	                                        </select>
	                                    </td>
	                                    <td class="min-w-[80px] px-4 py-3">
	                                        <input form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" type="number" min="1" max="12" name="jam_ke_mulai" value="<?= htmlspecialchars((string) ($item['jam_ke_mulai'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring">
	                                    </td>
	                                    <td class="min-w-[80px] px-4 py-3">
	                                        <input form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" type="number" min="1" max="10" name="jumlah_jam" value="<?= htmlspecialchars((string) ($item['jumlah_jam'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" class="w-20 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring">
	                                    </td>
	                                    <td class="min-w-[150px] px-4 py-3">
	                                        <select form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" name="ruangan_id" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring">
	                                            <option value="">-</option>
	                                            <?php foreach ($roomOptions as $id => $label): ?>
	                                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) ($item['ruangan_id'] ?? 0) === (int) $id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
	                                    </td>
	                                    <td class="px-4 py-3 text-center">
	                                        <label class="inline-flex items-center justify-center">
	                                            <input form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="is_locked" value="0">
	                                            <input form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" type="checkbox" name="is_locked" value="1" <?= !empty($item['is_locked']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
	                                        </label>
	                                    </td>
	                                    <td class="px-4 py-3">
	                                        <div class="flex items-center justify-end gap-2">
	                                            <button form="<?= htmlspecialchars($itemFormId, ENT_QUOTES, 'UTF-8') ?>" type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Simpan</button>
	                                            <form action="<?= htmlspecialchars(base_url('akademik/jadwal/generate/' . $draftId . '/items/' . $itemId . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus item jadwal ini?');">
	                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">Belum ada item draft.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const classMap = <?= json_encode($assignmentClassMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const classCheckboxes = Array.from(document.querySelectorAll('[data-class-filter-checkbox]'));
        const classAllCheckbox = document.querySelector('[data-class-filter-all]');
        const classHiddenContainers = Array.from(document.querySelectorAll('[data-class-hidden-fields]'));
        const classRequiredButtons = Array.from(document.querySelectorAll('[data-requires-class-selection]'));

        classRequiredButtons.forEach((button) => {
            button.dataset.initialDisabled = button.disabled ? '1' : '0';
        });

        const syncClassFields = () => {
            const selectedValues = classCheckboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);

            classHiddenContainers.forEach((container) => {
                container.innerHTML = '';
                selectedValues.forEach((value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'class_ids[]';
                    input.value = value;
                    container.appendChild(input);
                });
            });

            if (classAllCheckbox instanceof HTMLInputElement) {
                classAllCheckbox.checked = classCheckboxes.length > 0 && selectedValues.length === classCheckboxes.length;
                classAllCheckbox.indeterminate = selectedValues.length > 0 && selectedValues.length < classCheckboxes.length;
            }

            classRequiredButtons.forEach((button) => {
                button.disabled = button.dataset.initialDisabled === '1' || (classCheckboxes.length > 0 && selectedValues.length === 0);
            });
        };

        if (classAllCheckbox instanceof HTMLInputElement) {
            classAllCheckbox.addEventListener('change', () => {
                classCheckboxes.forEach((checkbox) => {
                    checkbox.checked = classAllCheckbox.checked;
                });
                syncClassFields();
            });
        }

        classCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncClassFields);
        });

        syncClassFields();

        document.querySelectorAll('[data-parallel-group-row]').forEach((parallelRow) => {
            const assignmentSelect = parallelRow.querySelector('[data-parallel-assignment-select]');
            const classContainer = parallelRow.querySelector('[data-parallel-class-options]');
            if (!(assignmentSelect instanceof HTMLSelectElement) || !(classContainer instanceof HTMLElement)) {
                return;
            }

            const selectedClasses = new Set((classContainer.dataset.selectedClasses || '').split(',').filter(Boolean));
            const groupIndex = classContainer.dataset.groupIndex || '0';

            const rebuildParallelClasses = (resetSelection = false) => {
                const options = classMap[assignmentSelect.value] || {};
                if (resetSelection) {
                    selectedClasses.clear();
                    classContainer.dataset.selectedClasses = '';
                }
                classContainer.innerHTML = '';

                Object.entries(options).forEach(([id, label]) => {
                    const wrapper = document.createElement('label');
                    wrapper.className = 'flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = `parallel_groups[${groupIndex}][kelas_ids][]`;
                    checkbox.value = id;
                    checkbox.checked = selectedClasses.has(String(id));
                    checkbox.className = 'h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked) {
                            selectedClasses.add(String(id));
                        } else {
                            selectedClasses.delete(String(id));
                        }
                        classContainer.dataset.selectedClasses = Array.from(selectedClasses).join(',');
                    });

                    const text = document.createElement('span');
                    text.textContent = label;

                    wrapper.appendChild(checkbox);
                    wrapper.appendChild(text);
                    classContainer.appendChild(wrapper);
                });
            };

            assignmentSelect.addEventListener('change', () => rebuildParallelClasses(true));
            rebuildParallelClasses(false);
        });

        document.querySelectorAll('[data-assignment-select]').forEach((assignmentSelect) => {
            const row = assignmentSelect.closest('tr');
            if (!row) {
                return;
            }

            const classSelect = row.querySelector('[data-class-select]');
            if (!(classSelect instanceof HTMLSelectElement)) {
                return;
            }

            const rebuildClasses = () => {
                const selectedAssignment = assignmentSelect.value;
                const selectedClass = classSelect.dataset.selectedClass || classSelect.value;
                const options = classMap[selectedAssignment] || {};
                classSelect.innerHTML = '';

                Object.entries(options).forEach(([id, label]) => {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = label;
                    option.selected = String(id) === String(selectedClass);
                    classSelect.appendChild(option);
                });

                if (!classSelect.value && classSelect.options.length > 0) {
                    classSelect.options[0].selected = true;
                }
            };

            assignmentSelect.addEventListener('change', () => {
                classSelect.dataset.selectedClass = '';
                rebuildClasses();
            });
            rebuildClasses();
        });
    });
</script>
