<?php
    $classOptionsData = isset($classOptions) && is_array($classOptions) ? $classOptions : [];
    $assignmentOptionsData = isset($assignmentOptions) && is_array($assignmentOptions) ? $assignmentOptions : [];
    $selectedClassIdValue = isset($selectedClassId) ? (int) $selectedClassId : 0;
    $selectedAssignmentIdValue = isset($selectedAssignmentId) ? (int) $selectedAssignmentId : 0;
    $startDateValue = isset($startDate) ? (string) $startDate : date('Y-m-d', strtotime('monday this week'));
    $endDateValue = isset($endDate) ? (string) $endDate : date('Y-m-d');
    $sessionsData = isset($sessions) && is_array($sessions) ? $sessions : [];
    $statusLabelsData = isset($statusLabels) && is_array($statusLabels) ? $statusLabels : [];
    $totalsData = isset($totals) && is_array($totals) ? $totals : [];
    $subjectSummariesData = isset($subjectSummaries) && is_array($subjectSummaries) ? $subjectSummaries : [];
    $activeYearData = isset($activeYear) && is_array($activeYear) ? $activeYear : null;
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $infoMessage = session_flash('info');
    $statusKeys = array_keys($statusLabelsData);
?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Rekap Presensi Siswa</h2>
            <p class="text-sm text-slate-500">
                Pilih kelas, mata pelajaran, dan rentang tanggal untuk melihat rekap absensi siswa beserta rangkumannya.
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
        <form action="<?= htmlspecialchars(base_url('akademik/presensi/rekap'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="grid gap-4 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="classId" class="block text-sm font-medium text-slate-700">Kelas</label>
                <select
                    name="kelas_id"
                    id="classId"
                    required
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <?php if (empty($classOptionsData)): ?>
                        <option value="">Tidak ada kelas tersedia</option>
                    <?php else: ?>
                        <?php foreach ($classOptionsData as $classId => $label): ?>
                            <option value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassIdValue === (int) $classId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="assignmentId" class="block text-sm font-medium text-slate-700">Mata Pelajaran</label>
                <select
                    name="assignment_id"
                    id="assignmentId"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <?php foreach ($assignmentOptionsData as $assignmentId => $label): ?>
                        <option value="<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAssignmentIdValue === (int) $assignmentId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="startDate" class="block text-sm font-medium text-slate-700">Mulai</label>
                <input
                    type="date"
                    name="start_date"
                    id="startDate"
                    value="<?= htmlspecialchars($startDateValue, ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div>
                <label for="endDate" class="block text-sm font-medium text-slate-700">Selesai</label>
                <input
                    type="date"
                    name="end_date"
                    id="endDate"
                    value="<?= htmlspecialchars($endDateValue, ENT_QUOTES, 'UTF-8') ?>"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div class="md:col-span-4 flex items-center justify-end gap-3">
                <a
                    href="<?= htmlspecialchars(base_url('akademik/presensi/rekap'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-slate-300"
                >
                    Reset
                </a>
                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Tampilkan Rekap
                </button>
            </div>
        </form>
    </section>

    <?php if ($selectedClassIdValue <= 0): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
            Pilih kelas terlebih dahulu untuk melihat rekap presensi.
        </div>
    <?php else: ?>
        <section class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Ringkasan per Mata Pelajaran</h3>
                    <p class="text-xs text-slate-500">
                        Total sesi dan status kehadiran dalam rentang tanggal yang dipilih.
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                    <p>Total Sesi: <span class="font-semibold text-slate-800"><?= number_format(count($sessionsData)) ?></span></p>
                    <p>
                        Total Hadir: <span class="font-semibold text-emerald-600"><?= number_format($totalsData['hadir'] ?? 0) ?></span>
                    </p>
                </div>
            </div>

            <?php if (empty($subjectSummariesData)): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                    Belum ada sesi presensi untuk kriteria yang dipilih.
                </div>
            <?php else: ?>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($subjectSummariesData as $summary): ?>
                        <?php
                            $subjectName = (string) ($summary['mata_pelajaran'] ?? 'Mata Pelajaran');
                            $subjectCode = (string) ($summary['kode'] ?? '');
                            $teacherName = (string) ($summary['guru'] ?? '');
                            $counts = isset($summary['counts']) && is_array($summary['counts']) ? $summary['counts'] : [];
                            $sessionCount = (int) ($summary['sessions'] ?? 0);
                        ?>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <header>
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                    <?= htmlspecialchars($subjectCode !== '' ? $subjectCode : 'Mapel', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <h4 class="text-lg font-semibold text-slate-800">
                                    <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?>
                                </h4>
                                <?php if ($teacherName !== ''): ?>
                                    <p class="text-xs text-slate-500">Guru: <?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </header>
                            <dl class="mt-4 grid gap-3">
                                <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs">
                                    <dt class="text-slate-500">Jumlah Sesi</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-800"><?= number_format($sessionCount) ?></dd>
                                </div>
                                <?php foreach ($statusKeys as $statusKey): ?>
                                    <?php
                                        $label = $statusLabelsData[$statusKey] ?? ucfirst($statusKey);
                                        $value = isset($counts[$statusKey]) ? (int) $counts[$statusKey] : 0;
                                    ?>
                                    <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 text-xs">
                                        <dt class="text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-800"><?= number_format($value) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Detail Sesi Presensi</h3>
                    <p class="text-xs text-slate-500">
                        Daftar sesi presensi lengkap beserta rekap status kehadiran setiap pertemuan.
                    </p>
                </div>
            </div>

            <?php if (empty($sessionsData)): ?>
                <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    Tidak ditemukan sesi presensi sesuai kriteria.
                </p>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Tanggal</th>
                                <th class="px-4 py-3 text-left">Mata Pelajaran</th>
                                <th class="px-4 py-3 text-left">Agenda</th>
                                <th class="px-4 py-3 text-left">Durasi</th>
                                <?php foreach ($statusKeys as $statusKey): ?>
                                    <th class="px-4 py-3 text-left"><?= htmlspecialchars($statusLabelsData[$statusKey] ?? ucfirst($statusKey), ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php foreach ($sessionsData as $session): ?>
                                <?php
                                    $sessionDate = isset($session['tanggal']) ? date('d M Y', strtotime((string) $session['tanggal'])) : '-';
                                    $subjectName = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                                    $subjectCode = (string) ($session['mata_pelajaran_kode'] ?? '');
                                    $agenda = trim((string) ($session['agenda'] ?? ''));
                                    $agendaLimit = function_exists('mb_substr') ? mb_substr($agenda, 0, 120) : substr($agenda, 0, 120);
                                    if ($agenda !== '' && (function_exists('mb_strlen') ? mb_strlen($agenda) : strlen($agenda)) > 120) {
                                        $agendaLimit .= '…';
                                    }
                                    $duration = (int) ($session['durasi_menit'] ?? 0);
                                ?>
                                <tr>
                                    <td class="px-4 py-3 align-top text-slate-600"><?= htmlspecialchars($sessionDate, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <div class="font-semibold text-slate-800">
                                            <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <?php if ($subjectCode !== ''): ?>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($subjectCode, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <div class="mt-1 text-xs text-slate-400">
                                            Guru: <?= htmlspecialchars((string) ($session['guru_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-600">
                                        <?= htmlspecialchars($agendaLimit, ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="px-4 py-3 align-top text-slate-600"><?= number_format($duration) ?> menit</td>
                                    <?php foreach ($statusKeys as $statusKey): ?>
                                        <td class="px-4 py-3 align-top text-slate-700 font-semibold">
                                            <?= number_format((int) ($session['total_' . $statusKey] ?? 0)) ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
