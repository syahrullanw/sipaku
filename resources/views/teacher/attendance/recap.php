<?php
    $sessionsData = isset($sessions) && is_array($sessions) ? $sessions : [];
    $classOptionsData = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $subjectOptionsData = isset($subjectOptions) && is_array($subjectOptions) ? $subjectOptions : [];
    $selectedClassIdValue = isset($selectedClassId) ? $selectedClassId : null;
    $selectedSubjectIdValue = isset($selectedSubjectId) ? $selectedSubjectId : null;
    $startDateValue = isset($startDate) ? (string) $startDate : date('Y-m-d', strtotime('monday this week'));
    $endDateValue = isset($endDate) ? (string) $endDate : date('Y-m-d');
    $totalsData = isset($totals) && is_array($totals) ? $totals : [];
    $activeYearData = isset($activeYear) && is_array($activeYear) ? $activeYear : null;
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $infoMessage = session_flash('info');
    $statusLabels = [
        'hadir' => 'Hadir',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'bolos' => 'Bolos',
        'alpa' => 'Tanpa Keterangan',
    ];
?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Rekap Presensi Semua Mapel</h2>
            <p class="text-sm text-slate-500">
                Pantau seluruh sesi presensi yang telah Anda buat di berbagai kelas dan mata pelajaran.
            </p>
        </div>
        <?php if ($activeYearData !== null): ?>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                <p class="font-semibold text-indigo-800">Tahun Ajaran Aktif</p>
                <p><?= htmlspecialchars($activeYearData['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($infoMessage)): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <?= htmlspecialchars($infoMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form action="<?= htmlspecialchars(base_url('guru/presensi/rekap'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="filterStartDate" class="block text-sm font-medium text-slate-700">Mulai</label>
                <input
                    type="date"
                    id="filterStartDate"
                    name="start_date"
                    value="<?= htmlspecialchars($startDateValue, ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="filterEndDate" class="block text-sm font-medium text-slate-700">Selesai</label>
                <input
                    type="date"
                    id="filterEndDate"
                    name="end_date"
                    value="<?= htmlspecialchars($endDateValue, ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="filterClass" class="block text-sm font-medium text-slate-700">Kelas</label>
                <select
                    id="filterClass"
                    name="kelas_id"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Semua Kelas</option>
                    <?php foreach ($classOptionsData as $classId => $label): ?>
                        <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassIdValue === $classId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filterSubject" class="block text-sm font-medium text-slate-700">Mata Pelajaran</label>
                <select
                    id="filterSubject"
                    name="mapel_id"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Semua Mapel</option>
                    <?php foreach ($subjectOptionsData as $subjectId => $label): ?>
                        <option value="<?= htmlspecialchars((string) $subjectId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedSubjectIdValue === $subjectId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-3">
                <a
                    href="<?= htmlspecialchars(base_url('guru/presensi/rekap'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-slate-300"
                >
                    Reset
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Terapkan Filter
                </button>
                <a
                    href="<?= htmlspecialchars(base_url('guru/presensi/rekap/export/pdf') . '?' . http_build_query([
                        'start_date' => $startDateValue,
                        'end_date' => $endDateValue,
                        'kelas_id' => $selectedClassIdValue,
                        'mapel_id' => $selectedSubjectIdValue,
                    ]), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 shadow-sm hover:bg-indigo-100"
                >
                    <i class="ri-file-download-line mr-2 text-base"></i>
                    Export PDF
                </a>
            </div>
        </form>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($totalsData as $statusKey => $count): ?>
            <?php
                $label = $statusLabels[$statusKey] ?? ucfirst($statusKey);
                $badgeClass = match ($statusKey) {
                    'hadir' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'izin' => 'border-sky-200 bg-sky-50 text-sky-700',
                    'sakit' => 'border-sky-200 bg-sky-50 text-sky-700',
                    'bolos' => 'border-amber-200 bg-amber-50 text-amber-700',
                    'alpa' => 'border-rose-200 bg-rose-50 text-rose-700',
                    default => 'border-slate-200 bg-slate-50 text-slate-600',
                };
            ?>
            <div class="rounded-2xl border <?= $badgeClass ?> px-5 py-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-2xl font-semibold"><?= number_format((int) $count) ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Daftar Sesi Presensi</h3>
                <p class="text-xs text-slate-500">
                    Menampilkan <?= number_format(count($sessionsData)) ?> sesi sesuai filter yang dipilih.
                </p>
            </div>
        </div>

        <?php if (empty($sessionsData)): ?>
            <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                Tidak ditemukan sesi presensi untuk kriteria yang dipilih.
            </p>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Mata Pelajaran</th>
                            <th class="px-4 py-3 text-left">Kelas</th>
                            <th class="px-4 py-3 text-left">Status Jadwal</th>
                            <th class="px-4 py-3 text-left">Agenda</th>
                            <th class="px-4 py-3 text-center">H</th>
                            <th class="px-4 py-3 text-center">I</th>
                            <th class="px-4 py-3 text-center">S</th>
                            <th class="px-4 py-3 text-center">B</th>
                            <th class="px-4 py-3 text-center">A</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($sessionsData as $session): ?>
                            <?php
                                $sessionId = (int) ($session['id'] ?? 0);
                                $dateLabel = isset($session['tanggal']) ? date('d M Y', strtotime((string) $session['tanggal'])) : '-';
                                $subject = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                                $code = (string) ($session['mata_pelajaran_kode'] ?? '');
                                $classLabel = trim(sprintf('%s %s', (string) ($session['kelas_tingkat'] ?? ''), (string) ($session['kelas_nama'] ?? '')));
                                if (!empty($session['jurusan_nama'])) {
                                    $classLabel .= ' (' . $session['jurusan_nama'] . ')';
                                }
                                $parallelLabel = trim(sprintf('%s %s', (string) ($session['kelas_paralel_tingkat'] ?? ''), (string) ($session['kelas_paralel_nama'] ?? '')));
                                if (!empty($session['jurusan_paralel_nama'])) {
                                    $parallelLabel .= ' (' . $session['jurusan_paralel_nama'] . ')';
                                }
                                if ($parallelLabel !== '' && $parallelLabel !== $classLabel) {
                                    if ($classLabel === '') {
                                        $classLabel = $parallelLabel;
                                    } else {
                                        $classLabel .= ' + ' . $parallelLabel;
                                    }
                                }
                                $isReplacement = (string) ($session['tipe_sesi'] ?? 'jadwal') === 'pengganti';
                                $replacementNote = trim((string) ($session['catatan_pengganti'] ?? ''));
                                $scheduledTeacherName = trim((string) ($session['guru_jadwal_nama'] ?? ''));
                            ?>
                            <tr>
                                <td class="px-4 py-3 align-top text-slate-600"><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold text-slate-700">
                                        <?php if ($sessionId > 0): ?>
                                            <a
                                                href="<?= htmlspecialchars(base_url('guru/presensi/' . $sessionId), ENT_QUOTES, 'UTF-8') ?>"
                                                class="hover:text-indigo-600"
                                            >
                                                <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($code !== ''): ?>
                                        <p class="text-xs text-slate-400">
                                            <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-slate-600">
                                    <?= htmlspecialchars($classLabel !== '' ? $classLabel : '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-slate-600">
                                    <?php if ($isReplacement): ?>
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Guru Pengganti</span>
                                        <?php if ($scheduledTeacherName !== ''): ?>
                                            <p class="mt-1 text-xs text-slate-400">Jadwal asli: <?= htmlspecialchars($scheduledTeacherName, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($replacementNote !== ''): ?>
                                            <p class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($replacementNote, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Sesuai Jadwal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-slate-600">
                                    <?= nl2br(htmlspecialchars((string) ($session['agenda'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center font-semibold text-slate-700">
                                    <?= number_format((int) ($session['total_hadir'] ?? 0)) ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center font-semibold text-slate-700">
                                    <?= number_format((int) ($session['total_izin'] ?? 0)) ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center font-semibold text-slate-700">
                                    <?= number_format((int) ($session['total_sakit'] ?? 0)) ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center font-semibold text-slate-700">
                                    <?= number_format((int) ($session['total_bolos'] ?? 0)) ?>
                                </td>
                                <td class="px-4 py-3 align-top text-center font-semibold text-slate-700">
                                    <?= number_format((int) ($session['total_alpa'] ?? 0)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
