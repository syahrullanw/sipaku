<?php
/**
 * @var bool $hasActiveYear
 * @var string $startDate
 * @var string $endDate
 * @var array<int, string> $teacherOptions
 * @var int|null $selectedTeacherId
 * @var array<int, array<string, mixed>> $teacherSummaries
 * @var array<int, array<string, mixed>> $sessions
 * @var array<int, string> $statusKeys
 * @var array<string, string> $statusLabels
 * @var array<string, mixed> $globalTotals
 */

$teacherSelectOptions = ['0' => 'Semua Guru'];
foreach ($teacherOptions as $id => $label) {
    $teacherSelectOptions[(string) $id] = $label;
}

$totalHours = isset($globalTotals['hours']) ? (float) $globalTotals['hours'] : 0.0;
$statusTotals = isset($globalTotals['statuses']) && is_array($globalTotals['statuses'])
    ? $globalTotals['statuses']
    : array_fill_keys($statusKeys, 0);
?>

<div class="space-y-8">
    <section class="rounded-2xl border border-slate-200 bg-white/80 p-6 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/50">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Filter Rekap Presensi Guru</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Sesuaikan rentang tanggal dan guru untuk melihat pertemuan yang akan dihitung pada honor.</p>
            </div>
        </div>

        <form action="<?= htmlspecialchars(base_url('keuangan/bendahara/presensi-guru'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="mt-5 grid gap-5 md:grid-cols-12" novalidate>
            <div class="md:col-span-3">
                <label for="teacher_id" class="text-sm font-medium text-slate-600 dark:text-slate-300">Guru</label>
                <select id="teacher_id" name="teacher_id" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <?php foreach ($teacherSelectOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($selectedTeacherId ?? 0)) === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="start_date" class="text-sm font-medium text-slate-600 dark:text-slate-300">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
            </div>
            <div class="md:col-span-3">
                <label for="end_date" class="text-sm font-medium text-slate-600 dark:text-slate-300">Tanggal Selesai</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" />
            </div>
            <div class="flex items-end md:col-span-3">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-1">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </section>

    <?php if (!$hasActiveYear): ?>
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-700 shadow-sm dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            Tahun ajaran aktif belum ditetapkan. Tentukan tahun ajaran pada menu pengaturan akademik agar rekap presensi guru dapat ditampilkan.
        </section>
    <?php else: ?>
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200/60 bg-white/70 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/50">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Total Guru</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format(count($teacherSummaries)) ?></p>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Guru dengan pertemuan pada rentang tanggal.</p>
            </div>
            <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-4 shadow-sm shadow-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10">
                <p class="text-xs uppercase tracking-wide text-sky-700 dark:text-sky-200">Total Pertemuan</p>
                <p class="mt-2 text-2xl font-semibold text-sky-800 dark:text-sky-100"><?= number_format((int) ($globalTotals['sessions'] ?? 0)) ?></p>
                <p class="mt-1 text-xs text-sky-700/80 dark:text-sky-200/80">
                    <?= number_format((int) ($globalTotals['replacement_sessions'] ?? 0)) ?> sesi guru pengganti.
                </p>
            </div>
            <div class="rounded-xl border border-emerald-200/60 bg-emerald-50 p-4 shadow-sm shadow-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10">
                <p class="text-xs uppercase tracking-wide text-emerald-600 dark:text-emerald-200">Total Jam Ajar</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-100"><?= number_format($totalHours, 2, ',', '.') ?> JP</p>
                <p class="mt-1 text-xs text-emerald-600/80 dark:text-emerald-200/70">Mengacu jumlah jam pada jadwal.</p>
            </div>
            <div class="rounded-xl border border-slate-200/60 bg-white/70 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/50">
                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Rekap Status Kehadiran</p>
                <dl class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <?php foreach ($statusKeys as $statusKey): ?>
                        <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 text-center shadow-sm dark:border-slate-600/60 dark:bg-slate-900/60">
                            <dt class="font-medium"><?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey), ENT_QUOTES, 'UTF-8') ?></dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-white"><?= number_format((int) ($statusTotals[$statusKey] ?? 0)) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-6 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/50">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Ringkasan Per Guru</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gunakan ringkasan ini untuk menghitung honor jam mengajar.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800/70 dark:text-slate-300">
                    Rentang <?= htmlspecialchars(date('d M Y', strtotime($startDate)), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars(date('d M Y', strtotime($endDate)), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <?php if (empty($teacherSummaries)): ?>
                <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-800/40 dark:text-slate-400">
                    Belum ada sesi presensi guru pada rentang tanggal ini.
                </p>
            <?php else: ?>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($teacherSummaries as $summary): ?>
                        <?php
                            $subjectBadges = array_map(static function (array $subject): string {
                                $name = $subject['name'] ?? 'Mata Pelajaran';
                                $code = $subject['code'] ?? '';
                                return trim($name . ($code !== '' ? ' (' . $code . ')' : ''));
                            }, $summary['subjects']);
                        ?>
                        <article class="flex flex-col rounded-xl border border-slate-200 bg-white/90 p-5 shadow-sm transition hover:shadow-md dark:border-slate-700/70 dark:bg-slate-900/60">
                            <header>
                                <h4 class="text-base font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($summary['name'] ?? 'Guru', ENT_QUOTES, 'UTF-8') ?></h4>
                                <?php if (!empty($summary['nip'])): ?>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">NIP: <?= htmlspecialchars($summary['nip'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </header>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg border border-emerald-200/70 bg-emerald-50 px-3 py-2 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                    <dt class="text-xs uppercase tracking-wide">Total Jam</dt>
                                    <dd class="mt-1 text-base font-semibold"><?= number_format((float) ($summary['total_hours'] ?? 0), 2, ',', '.') ?> JP</dd>
                                </div>
                                <div class="rounded-lg border border-sky-200/70 bg-sky-50 px-3 py-2 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-200">
                                    <dt class="text-xs uppercase tracking-wide">Pertemuan</dt>
                                    <dd class="mt-1 text-base font-semibold"><?= number_format((int) ($summary['sessions'] ?? 0)) ?></dd>
                                </div>
                            </dl>
                            <?php if ((int) ($summary['replacement_sessions'] ?? 0) > 0): ?>
                                <p class="mt-3 inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                    <?= number_format((int) ($summary['replacement_sessions'] ?? 0)) ?> sesi guru pengganti
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($subjectBadges)): ?>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <?php foreach ($subjectBadges as $subjectLabel): ?>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600 dark:bg-slate-800/60 dark:text-slate-200"><?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($summary['classes'])): ?>
                                <div class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                                    <p class="font-semibold text-slate-600 dark:text-slate-200">Kelas:</p>
                                    <ul class="mt-1 list-disc space-y-1 pl-5">
                                        <?php foreach ($summary['classes'] as $classLabel): ?>
                                            <li><?= htmlspecialchars($classLabel, ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
                                <p class="font-semibold">Rekap Kehadiran Siswa:</p>
                                <dl class="mt-2 grid grid-cols-3 gap-2">
                                    <?php foreach ($statusKeys as $statusKey): ?>
                                        <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 text-center shadow-sm dark:border-slate-600/60 dark:bg-slate-900/60">
                                            <dt class="text-[11px] font-medium"><?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey), ENT_QUOTES, 'UTF-8') ?></dt>
                                            <dd class="mt-1 text-sm font-semibold"><?= number_format((int) ($summary['attendance'][$statusKey] ?? 0)) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white/80 p-6 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/50">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Detail Sesi Presensi</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Daftar pertemuan lengkap dengan agenda dan status kehadiran siswa.</p>
                </div>
            </div>

            <?php if (empty($sessions)): ?>
                <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-800/40 dark:text-slate-400">
                    Tidak ada sesi presensi sesuai filter.
                </p>
            <?php else: ?>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left">Tanggal</th>
                                <th class="px-4 py-3 text-left">Guru</th>
                                <th class="px-4 py-3 text-left">Mata Pelajaran</th>
                                <th class="px-4 py-3 text-left">Agenda</th>
                                <th class="px-4 py-3 text-left">Status Jadwal</th>
                                <th class="px-4 py-3 text-left">Jam Jadwal</th>
                                <?php foreach ($statusKeys as $statusKey): ?>
                                    <th class="px-4 py-3 text-left"><?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey), ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900/40">
                            <?php foreach ($sessions as $session): ?>
                                <?php
                                    $sessionDate = isset($session['tanggal']) ? date('d M Y', strtotime((string) $session['tanggal'])) : '-';
                                    $subjectName = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                                    $subjectCode = (string) ($session['mata_pelajaran_kode'] ?? '');
                                    $agenda = trim((string) ($session['agenda'] ?? ''));
                                    if (function_exists('mb_strlen') ? mb_strlen($agenda) > 160 : strlen($agenda) > 160) {
                                        $agenda = (function_exists('mb_substr') ? mb_substr($agenda, 0, 160) : substr($agenda, 0, 160)) . '…';
                                    }
                                    $plannedHours = isset($session['jumlah_jam']) ? (float) $session['jumlah_jam'] : 0.0;
                                    $isReplacement = (string) ($session['tipe_sesi'] ?? 'jadwal') === 'pengganti';
                                    $replacementNote = trim((string) ($session['catatan_pengganti'] ?? ''));
                                    $scheduledTeacherName = trim((string) ($session['guru_jadwal_nama'] ?? ''));
                                ?>
                                <tr class="text-slate-600 dark:text-slate-200">
                                    <td class="px-4 py-3 align-top"><?= htmlspecialchars($sessionDate, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-semibold text-slate-800 dark:text-white"><?= htmlspecialchars((string) ($session['guru_nama'] ?? 'Guru'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($session['kelas_nama'])): ?>
                                            <div class="text-xs text-slate-400">Kelas <?= htmlspecialchars((string) ($session['kelas_tingkat'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($session['kelas_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-semibold text-slate-800 dark:text-white"><?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ($subjectCode !== ''): ?>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($subjectCode, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 align-top"><?= htmlspecialchars($agenda, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 align-top">
                                        <?php if ($isReplacement): ?>
                                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">Guru Pengganti</span>
                                            <?php if ($scheduledTeacherName !== ''): ?>
                                                <div class="mt-1 text-xs text-slate-400">Jadwal asli: <?= htmlspecialchars($scheduledTeacherName, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php if ($replacementNote !== ''): ?>
                                                <div class="mt-1 max-w-xs text-xs text-slate-500 dark:text-slate-300"><?= htmlspecialchars($replacementNote, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">Sesuai Jadwal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 align-top"><?= number_format($plannedHours, 2, ',', '.') ?> JP</td>
                                    <?php foreach ($statusKeys as $statusKey): ?>
                                        <td class="px-4 py-3 align-top font-semibold"><?= number_format((int) ($session['total_' . $statusKey] ?? 0)) ?></td>
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
