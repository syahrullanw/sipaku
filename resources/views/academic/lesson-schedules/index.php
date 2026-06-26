<?php
    $selectedYear = (int) ($selectedYearId ?? 0);
    $editing = is_array($editingSchedule) ? $editingSchedule : null;
    $isEditing = $editing !== null;
    $assignmentOptions = is_array($assignmentOptions ?? null) ? $assignmentOptions : [];
    $assignmentClassMap = is_array($assignmentClassMap ?? null) ? $assignmentClassMap : [];
    $dayOptions = is_array($dayOptions ?? null) ? $dayOptions : [];
    $yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
    $selectedAssignmentId = (int) old('guru_mata_pelajaran_id', $editing['guru_mata_pelajaran_id'] ?? 0);
    $selectedClassId = (int) old('kelas_id', $editing['kelas_id'] ?? 0);
    $selectedDay = (string) old('hari', $editing['hari'] ?? '');
    $startTime = (string) old('waktu_mulai', isset($editing['waktu_mulai']) ? substr((string) $editing['waktu_mulai'], 0, 5) : '');
    $endTime = (string) old('waktu_selesai', isset($editing['waktu_selesai']) ? substr((string) $editing['waktu_selesai'], 0, 5) : '');
    $lessonCountRaw = old('jumlah_jam', $editing['jumlah_jam'] ?? '');
    $lessonCount = is_array($lessonCountRaw) ? '' : (string) $lessonCountRaw;
    $formAction = $isEditing ? base_url('akademik/jadwal/' . urlencode((string) $editing['id']) . '/update') : base_url('akademik/jadwal');
    $formButtonLabel = $isEditing ? 'Perbarui Jadwal' : 'Tambah Jadwal';
    $formHeading = $isEditing ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran';
    $disableForm = (bool) ($disableForm ?? false);
    $dayLabel = static fn (string $value): string => ucfirst($value);
    $copyScheduleSourceYear = is_array($copyScheduleSourceYear ?? null) ? $copyScheduleSourceYear : null;
    $copyScheduleSourceCount = (int) ($copyScheduleSourceCount ?? 0);
    $canCopyLessonSchedules = (bool) ($canCopyLessonSchedules ?? false);
    $copyScheduleSourceLabel = null;
    if ($copyScheduleSourceYear !== null) {
        $sourceSemester = (int) ($copyScheduleSourceYear['semester_aktif'] ?? 1);
        $copyScheduleSourceLabel = sprintf(
            '%s - %s',
            $copyScheduleSourceYear['nama'] ?? 'Tahun Ajaran',
            $sourceSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }
    $copyScheduleDisabledReason = !empty($schedules)
        ? 'Jadwal sudah tersedia'
        : ($copyScheduleSourceYear === null ? 'Tidak ada sumber jadwal' : '');
?>
<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4 space-y-6">
        <div class="rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800"><?= htmlspecialchars($formHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if ($isEditing): ?>
                    <a
                        href="<?= htmlspecialchars(base_url('akademik/jadwal?tahun_ajaran_id=' . urlencode((string) $selectedYear)), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                    >
                        Batal
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($disableForm): ?>
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Tambahkan data guru pengampu beserta kelasnya terlebih dahulu sebelum membuat jadwal pelajaran.
                </p>
            <?php else: ?>
                <form action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" method="post" class="mt-4 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="filter_tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="space-y-1">
                        <label for="schedule-assignment" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guru Pengampu</label>
                        <select
                            id="schedule-assignment"
                            name="guru_mata_pelajaran_id"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:opacity-60"
                            data-assignment-select
                            required
                        >
                            <option value="">Pilih Guru Pengampu</option>
                            <?php foreach ($assignmentOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAssignmentId === (int) $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="schedule-class" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</label>
                        <select
                            id="schedule-class"
                            name="kelas_id"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:opacity-60"
                            data-class-select
                            required
                        >
                            <option value="">Pilih Kelas</option>
                        </select>
                        <p class="text-xs text-slate-400" data-class-helper>Daftar kelas mengikuti guru pengampu yang dipilih.</p>
                    </div>
                    <div class="space-y-1">
                        <label for="schedule-day" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hari</label>
                        <select
                            id="schedule-day"
                            name="hari"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring disabled:cursor-not-allowed disabled:opacity-60"
                            required
                        >
                            <option value="">Pilih Hari</option>
                            <?php foreach ($dayOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedDay === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="schedule-start" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waktu Mulai</label>
                            <input
                                type="time"
                                id="schedule-start"
                                name="waktu_mulai"
                                value="<?= htmlspecialchars($startTime, ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div class="space-y-1">
                            <label for="schedule-end" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waktu Selesai</label>
                            <input
                                type="time"
                                id="schedule-end"
                                name="waktu_selesai"
                                value="<?= htmlspecialchars($endTime, ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label for="schedule-hours" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Jam Pelajaran</label>
                        <input
                            type="number"
                            min="1"
                            max="10"
                            id="schedule-hours"
                            name="jumlah_jam"
                            value="<?= htmlspecialchars((string) $lessonCount, ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-300 focus:ring-offset-1"
                        >
                            <i class="ri-save-3-line text-base"></i>
                            <span><?= htmlspecialchars($formButtonLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700">Petunjuk</h3>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-xs text-slate-500">
                <li>Jadwal hanya dapat dibuat untuk guru pengampu yang sudah memiliki mapping kelas.</li>
                <li>Jumlah jam pelajaran dihitung dalam satuan JP.</li>
                <li>Untuk mengubah jadwal, pilih tombol edit pada daftar jadwal.</li>
            </ul>
        </div>
    </div>
    <div class="lg:col-span-8 space-y-6">
        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Daftar Jadwal Pelajaran</h2>
                <p class="text-xs text-slate-400">
                    <?php if ($copyScheduleSourceLabel !== null): ?>
                        Sumber salin: <?= htmlspecialchars($copyScheduleSourceLabel, ENT_QUOTES, 'UTF-8') ?> (<?= number_format($copyScheduleSourceCount) ?> jadwal).
                    <?php else: ?>
                        Filter berdasarkan tahun ajaran.
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <form
                    action="<?= htmlspecialchars(base_url('akademik/jadwal/salin'), ENT_QUOTES, 'UTF-8') ?>"
                    method="post"
                    onsubmit="return confirm('Salin jadwal pelajaran dari tahun ajaran sumber?');"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tahun_ajaran_sumber_id" value="<?= htmlspecialchars((string) ($copyScheduleSourceYear['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400 disabled:hover:bg-transparent"
                        <?= $canCopyLessonSchedules ? '' : 'disabled' ?>
                        title="<?= htmlspecialchars($canCopyLessonSchedules ? 'Salin jadwal pelajaran dari sumber' : $copyScheduleDisabledReason, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <i class="ri-file-copy-line text-base"></i>
                        Salin Jadwal
                    </button>
                </form>
                <div class="flex items-center gap-2">
                    <label for="schedule-year-filter" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tahun Ajaran</label>
                    <select
                        id="schedule-year-filter"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        data-year-filter
                    >
                        <?php foreach ($yearOptions as $id => $label): ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedYear === (int) $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-6 py-3">Guru</th>
                            <th class="px-6 py-3">Mata Pelajaran</th>
                            <th class="px-6 py-3">Kelas</th>
                            <th class="px-6 py-3">Hari</th>
                            <th class="px-6 py-3">Waktu</th>
                            <th class="px-6 py-3 text-center">JP</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <?php
                                    $start = !empty($schedule['waktu_mulai']) ? date('H:i', strtotime((string) $schedule['waktu_mulai'])) : '-';
                                    $end = !empty($schedule['waktu_selesai']) ? date('H:i', strtotime((string) $schedule['waktu_selesai'])) : '-';
                                    $dayName = $dayOptions[$schedule['hari']] ?? ucfirst((string) $schedule['hari']);
                                    $className = sprintf('Kelas %s %s', $schedule['kelas_tingkat'] ?? '-', $schedule['kelas_nama'] ?? '-');
                                    if (!empty($schedule['jurusan_nama'])) {
                                        $className .= sprintf(' (%s)', $schedule['jurusan_nama']);
                                    }
                                ?>
                                <tr class="text-slate-600">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($schedule['guru_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($schedule['guru_nip'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-800">
                                            <?= htmlspecialchars(($schedule['mata_pelajaran_kode'] ?? '-') . ' - ' . ($schedule['mata_pelajaran_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-xs text-slate-400">TA: <?= htmlspecialchars($schedule['tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($start . ' - ' . $end, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-slate-800"><?= htmlspecialchars((string) ($schedule['jumlah_jam'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a
                                                href="<?= htmlspecialchars(base_url('akademik/jadwal?edit=' . urlencode((string) $schedule['id']) . '&tahun_ajaran_id=' . urlencode((string) $selectedYear)), ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                            >
                                                Edit
                                            </a>
                                            <form
                                                action="<?= htmlspecialchars(base_url('akademik/jadwal/' . urlencode((string) $schedule['id']) . '/delete'), ENT_QUOTES, 'UTF-8') ?>"
                                                method="post"
                                                onsubmit="return confirm('Hapus jadwal ini?');"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="filter_tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYear, ENT_QUOTES, 'UTF-8') ?>">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                                >
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-400">
                                    Belum ada jadwal pelajaran untuk tahun ajaran ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const assignmentSelect = document.querySelector('[data-assignment-select]');
        const classSelect = document.querySelector('[data-class-select]');
        const classHelper = document.querySelector('[data-class-helper]');
        const yearFilter = document.querySelector('[data-year-filter]');
        const classMap = <?= json_encode($assignmentClassMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let preselectedClass = Number(<?= json_encode($selectedClassId) ?>);

        const renderClasses = (assignmentId) => {
            if (!classSelect) {
                return;
            }

            const options = classSelect.querySelectorAll('option');
            options.forEach((option, index) => {
                if (index !== 0) {
                    option.remove();
                }
            });

            const classes = assignmentId && Object.prototype.hasOwnProperty.call(classMap, assignmentId)
                ? classMap[assignmentId]
                : {};

            if (classHelper) {
                classHelper.classList.toggle('hidden', Object.keys(classes).length !== 0);
            }

            Object.keys(classes).forEach((classId) => {
                const option = document.createElement('option');
                option.value = classId;
                option.textContent = classes[classId];
                if (preselectedClass !== 0 && parseInt(classId, 10) === preselectedClass) {
                    option.selected = true;
                }
                classSelect.appendChild(option);
            });

            if (classSelect.options.length === 1) {
                classSelect.value = '';
            }
        };

        if (assignmentSelect) {
            renderClasses(assignmentSelect.value);

            assignmentSelect.addEventListener('change', () => {
                preselectedClass = 0;
                if (classSelect) {
                    classSelect.value = '';
                }
                renderClasses(assignmentSelect.value);
            });
        }

        if (yearFilter) {
            yearFilter.addEventListener('change', () => {
                const value = yearFilter.value;
                const baseUrl = '<?= htmlspecialchars(base_url('akademik/jadwal'), ENT_QUOTES, 'UTF-8') ?>';
                const target = value ? `${baseUrl}?tahun_ajaran_id=${encodeURIComponent(value)}` : baseUrl;
                window.location.href = target;
            });
        }
    });
</script>
