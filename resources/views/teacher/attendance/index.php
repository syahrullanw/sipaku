<?php
    $schedulesData = isset($schedules) && is_array($schedules) ? $schedules : [];
    $replacementSchedulesData = isset($replacementSchedules) && is_array($replacementSchedules) ? $replacementSchedules : [];
    $sessionsData = isset($sessions) && is_array($sessions) ? $sessions : [];
    $activeYearData = isset($activeYear) && is_array($activeYear) ? $activeYear : null;
    $focusSessionIdValue = isset($focusSessionId) ? (int) $focusSessionId : null;
    $statusOptionsData = isset($statusOptions) && is_array($statusOptions) ? $statusOptions : [];
    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $infoMessage = session_flash('info');
    $requiresLocation = (bool) ($requiresLocation ?? false);
    $schoolLocationData = isset($schoolLocation) && is_array($schoolLocation) ? $schoolLocation : null;
    $dayLabels = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];
    $selectedSchedule = (int) old('jadwal_id', 0);
    $selectedReplacementSchedule = (int) old('jadwal_pengganti_id', 0);
    $selectedParallelSchedule = (int) old('jadwal_paralel_id', 0);
    $selectedSessionType = (string) old('tipe_sesi', empty($schedulesData) ? 'pengganti' : 'jadwal');
    $buildScheduleMeta = static function (array $schedule, array $dayLabels): ?array {
        $scheduleId = (int) ($schedule['id'] ?? 0);
        if ($scheduleId <= 0) {
            return null;
        }

        $dayKey = (string) ($schedule['hari'] ?? '');
        $dayLabel = $dayLabels[$dayKey] ?? ucfirst($dayKey ?: '-');
        $startRaw = trim((string) ($schedule['waktu_mulai'] ?? ''));
        $endRaw = trim((string) ($schedule['waktu_selesai'] ?? ''));
        $startLabel = $startRaw !== '' ? date('H:i', strtotime($startRaw)) : '-';
        $endLabel = $endRaw !== '' ? date('H:i', strtotime($endRaw)) : '-';
        $subjectName = (string) ($schedule['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
        $subjectCode = (string) ($schedule['mata_pelajaran_kode'] ?? '');
        $classLabel = sprintf(
            'Kelas %s %s',
            (string) ($schedule['kelas_tingkat'] ?? '-'),
            (string) ($schedule['kelas_nama'] ?? '-')
        );
        if (!empty($schedule['jurusan_nama'])) {
            $classLabel .= sprintf(' (%s)', (string) $schedule['jurusan_nama']);
        }

        $label = sprintf('%s • %s-%s • %s', $dayLabel, $startLabel, $endLabel, $subjectName);
        if ($subjectCode !== '') {
            $label .= sprintf(' (%s)', $subjectCode);
        }
        $label .= ' • ' . $classLabel;
        $teacherName = trim((string) ($schedule['guru_nama'] ?? ''));
        if ($teacherName !== '') {
            $label .= ' • Guru: ' . $teacherName;
        }

        return [
            'id' => $scheduleId,
            'label' => $label,
            'day' => $dayKey,
            'start' => $startRaw,
            'end' => $endRaw,
            'tingkat' => (string) ($schedule['kelas_tingkat'] ?? ''),
        ];
    };
?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Presensi Siswa via QR</h2>
            <p class="mt-1 text-sm text-slate-500">
                Buat sesi presensi baru dengan agenda harian dan bagikan QR kepada siswa. Anda tetap dapat memperbarui
                status kehadiran secara manual apabila ada siswa yang tidak dapat memindai QR.
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
        <h3 class="text-lg font-semibold text-slate-800">Buat Sesi Presensi</h3>

        <?php if (empty($schedulesData)): ?>
            <p class="mt-3 text-sm text-slate-500">
                Jadwal mengajar Anda belum tersedia pada tahun ajaran aktif. Anda tetap dapat membuat presensi sebagai guru pengganti apabila jadwal kelas sudah tersedia.
            </p>
        <?php endif; ?>
        <?php if (empty($schedulesData) && empty($replacementSchedulesData)): ?>
            <p class="mt-3 text-sm text-slate-500">
                Belum ada jadwal pelajaran pada tahun ajaran aktif.
            </p>
        <?php else: ?>
            <form
                action="<?= htmlspecialchars(base_url('guru/presensi'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-4 space-y-4"
                id="create-attendance-session-form"
                data-requires-location="<?= $requiresLocation ? '1' : '0' ?>"
            >
                <?= csrf_field() ?>
                <input
                    type="hidden"
                    name="latitude"
                    id="attendance-latitude"
                    value="<?= htmlspecialchars((string) old('latitude', ''), ENT_QUOTES, 'UTF-8') ?>"
                />
                <input
                    type="hidden"
                    name="longitude"
                    id="attendance-longitude"
                    value="<?= htmlspecialchars((string) old('longitude', ''), ENT_QUOTES, 'UTF-8') ?>"
                />
                <?php if ($requiresLocation): ?>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700">
                        <p class="font-semibold text-indigo-800">Aktifkan lokasi perangkat</p>
                        <p class="mt-1 text-indigo-600">
                            Sesi presensi hanya dapat dibuat ketika Anda berada dalam radius yang ditentukan dari lokasi sekolah.
                        </p>
                        <?php if ($schoolLocationData !== null): ?>
                            <p class="mt-1 text-[11px] text-indigo-500">
                                Radius diizinkan: <?= htmlspecialchars(number_format((float) ($schoolLocationData['radius'] ?? 0), 0), ENT_QUOTES, 'UTF-8') ?> meter dari titik sekolah.
                            </p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span
                                id="attendance-location-status"
                                class="text-[11px] font-semibold uppercase tracking-wide text-indigo-500"
                            >
                                Mengambil lokasi perangkat...
                            </span>
                            <button
                                type="button"
                                id="attendance-refresh-location"
                                class="inline-flex items-center justify-center gap-1 rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                            >
                                <i class="ri-map-pin-line text-sm"></i>
                                Perbarui Lokasi
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="sessionType" class="block text-sm font-medium text-slate-700">Jenis Sesi</label>
                    <select
                        name="tipe_sesi"
                        id="sessionType"
                        required
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="jadwal" <?= $selectedSessionType !== 'pengganti' ? 'selected' : '' ?>>Sesuai Jadwal Saya</option>
                        <option value="pengganti" <?= $selectedSessionType === 'pengganti' ? 'selected' : '' ?>>Guru Pengganti</option>
                    </select>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2" id="regularScheduleGroup">
                        <label for="scheduleId" class="block text-sm font-medium text-slate-700">
                            Pilih Jadwal Mengajar
                        </label>
                        <select
                            name="jadwal_id"
                            id="scheduleId"
                            required
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Pilih salah satu jadwal</option>
                            <?php
                                foreach ($schedulesData as $schedule) {
                                    if (!is_array($schedule)) {
                                        continue;
                                    }
                                    $meta = $buildScheduleMeta($schedule, $dayLabels);
                                    if ($meta === null) {
                                        continue;
                                    }
                            ?>
                                    <option
                                        value="<?= htmlspecialchars((string) $meta['id'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-tingkat="<?= htmlspecialchars($meta['tingkat'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-hari="<?= htmlspecialchars($meta['day'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-mulai="<?= htmlspecialchars($meta['start'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-selesai="<?= htmlspecialchars($meta['end'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $selectedSchedule === $meta['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                        </select>
                    </div>
                    <div class="md:col-span-2" id="replacementScheduleGroup">
                        <label for="replacementScheduleId" class="block text-sm font-medium text-slate-700">
                            Pilih Jadwal yang Digantikan
                        </label>
                        <select
                            name="jadwal_pengganti_id"
                            id="replacementScheduleId"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Pilih jadwal kelas/guru yang digantikan</option>
                            <?php
                                foreach ($replacementSchedulesData as $schedule) {
                                    if (!is_array($schedule)) {
                                        continue;
                                    }
                                    $meta = $buildScheduleMeta($schedule, $dayLabels);
                                    if ($meta === null) {
                                        continue;
                                    }
                            ?>
                                    <option
                                        value="<?= htmlspecialchars((string) $meta['id'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-hari="<?= htmlspecialchars($meta['day'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $selectedReplacementSchedule === $meta['id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="md:col-span-2" id="parallelScheduleGroup">
                        <label for="parallelScheduleId" class="block text-sm font-medium text-slate-700">
                            Kelas Paralel (opsional)
                        </label>
                        <select
                            name="jadwal_paralel_id"
                            id="parallelScheduleId"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Tidak, gunakan 1 kelas</option>
                            <?php
                                foreach ($schedulesData as $schedule) {
                                    if (!is_array($schedule)) {
                                        continue;
                                    }
                                    $meta = $buildScheduleMeta($schedule, $dayLabels);
                                    if ($meta === null) {
                                        continue;
                                    }
                            ?>
                                <option
                                    value="<?= htmlspecialchars((string) $meta['id'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-tingkat="<?= htmlspecialchars($meta['tingkat'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-hari="<?= htmlspecialchars($meta['day'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-mulai="<?= htmlspecialchars($meta['start'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-selesai="<?= htmlspecialchars($meta['end'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $selectedParallelSchedule === $meta['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php } ?>
                        </select>
                        <p id="parallel-schedule-hint" class="mt-1 text-xs text-slate-400">
                            Pilih jadwal utama terlebih dahulu untuk melihat opsi paralel.
                        </p>
                    </div>
                    <div class="md:col-span-2" id="replacementNoteGroup">
                        <label for="replacementNote" class="block text-sm font-medium text-slate-700">Catatan Guru Pengganti</label>
                        <textarea
                            id="replacementNote"
                            name="catatan_pengganti"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Contoh: Menggantikan Bapak/Ibu ... karena berhalangan, materi tetap melanjutkan jadwal kelas."
                        ><?= htmlspecialchars((string) old('catatan_pengganti', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div>
                        <label for="attendanceDate" class="block text-sm font-medium text-slate-700">Tanggal Pelaksanaan</label>
                        <input
                            type="date"
                            id="attendanceDate"
                            name="tanggal"
                            value="<?= htmlspecialchars((string) old('tanggal', date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label for="durationMinutes" class="block text-sm font-medium text-slate-700">Durasi QR Aktif (menit)</label>
                        <input
                            type="number"
                            id="durationMinutes"
                            name="durasi"
                            min="5"
                            max="360"
                            step="5"
                            value="<?= htmlspecialchars((string) old('durasi', 60), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p class="mt-1 text-xs text-slate-400">
                            Default 60 menit. Sesuaikan apabila sesi lebih singkat atau lebih panjang.
                        </p>
                    </div>
                </div>
                <div>
                    <label for="dailyAgenda" class="block text-sm font-medium text-slate-700">Agenda Harian</label>
                    <textarea
                        id="dailyAgenda"
                        name="agenda"
                        rows="4"
                        required
                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ><?= htmlspecialchars((string) old('agenda', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">
                        Contoh: "Pembahasan Bab 3 - Persamaan Kuadrat, latihan soal nomor 1-10."
                    </p>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <i class="ri-qr-code-line mr-2 text-base"></i>
                        Generate QR Presensi
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">Riwayat Sesi Presensi</h3>
            <p class="text-xs text-slate-400">
                Menampilkan maksimal 30 sesi terakhir.
            </p>
        </div>

        <?php if (empty($sessionsData)): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                Belum ada sesi presensi yang dibuat. Gunakan formulir di atas untuk membuat sesi pertama.
            </div>
        <?php else: ?>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($sessionsData as $session): ?>
                    <?php
                        if (!is_array($session)) {
                            continue;
                        }
                        $sessionId = (int) ($session['id'] ?? 0);
                        if ($sessionId <= 0) {
                            continue;
                        }
                        $sessionAnchorId = 'session-' . $sessionId;
                        $sessionDate = isset($session['tanggal']) ? date('d M Y', strtotime((string) $session['tanggal'])) : '-';
                        $subject = (string) ($session['mata_pelajaran_nama'] ?? 'Mata Pelajaran');
                        $subjectCode = (string) ($session['mata_pelajaran_kode'] ?? '');
                        $className = sprintf(
                            'Kelas %s %s',
                            htmlspecialchars((string) ($session['kelas_tingkat'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars((string) ($session['kelas_nama'] ?? '-'), ENT_QUOTES, 'UTF-8')
                        );
                        if (!empty($session['jurusan_nama'])) {
                            $className .= sprintf(' (%s)', htmlspecialchars((string) $session['jurusan_nama'], ENT_QUOTES, 'UTF-8'));
                        }
                        $parallelClassName = '';
                        if (!empty($session['kelas_paralel_id'])) {
                            $parallelClassName = sprintf(
                                'Kelas %s %s',
                                htmlspecialchars((string) ($session['kelas_paralel_tingkat'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                                htmlspecialchars((string) ($session['kelas_paralel_nama'] ?? '-'), ENT_QUOTES, 'UTF-8')
                            );
                            if (!empty($session['jurusan_paralel_nama'])) {
                                $parallelClassName .= sprintf(' (%s)', htmlspecialchars((string) $session['jurusan_paralel_nama'], ENT_QUOTES, 'UTF-8'));
                            }
                        }
                        if ($parallelClassName !== '') {
                            $className .= ' + ' . $parallelClassName;
                        }
                        $sessionType = (string) ($session['tipe_sesi'] ?? 'jadwal');
                        $isReplacementSession = $sessionType === 'pengganti';
                        $replacementNote = trim((string) ($session['catatan_pengganti'] ?? ''));
                        $scheduledTeacherName = trim((string) ($session['guru_jadwal_nama'] ?? ''));
                        $expiresAt = isset($session['valid_sampai']) ? strtotime((string) $session['valid_sampai']) : false;
                        $statusKey = (string) ($session['status'] ?? 'ditutup');
                        $isStillActive = $statusKey === 'aktif' && ($expiresAt === false || $expiresAt >= time());
                        $statusBadge = $isStillActive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200';
                        $statusLabel = $isStillActive ? 'Aktif' : 'Ditutup';
                        if ($expiresAt !== false && $expiresAt < time()) {
                            $statusLabel = 'Kedaluwarsa';
                            $statusBadge = 'bg-amber-50 text-amber-700 border-amber-200';
                        }
                            $agendaSnippet = trim((string) ($session['agenda'] ?? ''));
                            $lengthFunction = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
                            $substrFunction = function_exists('mb_substr') ? 'mb_substr' : 'substr';
                            if ($lengthFunction($agendaSnippet) > 140) {
                                $agendaSnippet = $substrFunction($agendaSnippet, 0, 140) . '…';
                            }
                    ?>
                    <article
                        id="<?= htmlspecialchars($sessionAnchorId, ENT_QUOTES, 'UTF-8') ?>"
                        class="flex h-full flex-col justify-between rounded-2xl border <?= $focusSessionIdValue === $sessionId ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-slate-200' ?> bg-white p-5 shadow-sm transition"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center rounded-full border <?= $statusBadge ?> px-3 py-1 font-semibold">
                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="inline-flex items-center rounded-full border <?= $isReplacementSession ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' ?> px-3 py-1 font-semibold">
                                        <?= $isReplacementSession ? 'Guru Pengganti' : 'Sesuai Jadwal' ?>
                                    </span>
                                </div>
                                <span class="text-slate-400"><?= htmlspecialchars($sessionDate, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                    <?= htmlspecialchars($subjectCode !== '' ? $subjectCode : 'Mapel', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <h4 class="text-base font-semibold text-slate-800">
                                    <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>
                                </h4>
                                <p class="text-xs text-slate-500"><?= $className ?></p>
                                <?php if ($isReplacementSession && $scheduledTeacherName !== ''): ?>
                                    <p class="mt-1 text-xs text-amber-600">
                                        Jadwal asli: <?= htmlspecialchars($scheduledTeacherName, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if ($isReplacementSession && $replacementNote !== ''): ?>
                                <p class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                    <?= htmlspecialchars($replacementNote, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($agendaSnippet !== ''): ?>
                                <p class="text-sm text-slate-600">
                                    <?= htmlspecialchars($agendaSnippet, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                <p>Valid s.d.</p>
                                <p class="font-semibold text-slate-700">
                                    <?= isset($session['valid_sampai']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $session['valid_sampai'])), ENT_QUOTES, 'UTF-8') : '-' ?>
                                </p>
                            </div>
                            <a
                                href="<?= htmlspecialchars(base_url('guru/presensi/' . $sessionId), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-semibold text-indigo-600 hover:bg-indigo-100"
                            >
                                Detail
                                <i class="ri-arrow-right-line text-sm"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($requiresLocation): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('create-attendance-session-form');
    if (!form || form.dataset.requiresLocation !== '1') {
        return;
    }

    const latField = document.getElementById('attendance-latitude');
    const lngField = document.getElementById('attendance-longitude');
    const statusLabel = document.getElementById('attendance-location-status');
    const refreshButton = document.getElementById('attendance-refresh-location');

    const setStatus = (message, tone = 'info') => {
        if (!statusLabel) {
            return;
        }

        let toneClass = 'text-indigo-500';
        if (tone === 'success') {
            toneClass = 'text-emerald-600';
        } else if (tone === 'error') {
            toneClass = 'text-rose-600';
        } else if (tone === 'warning') {
            toneClass = 'text-amber-600';
        }

        statusLabel.textContent = message;
        statusLabel.className = 'text-[11px] font-semibold uppercase tracking-wide ' + toneClass;
    };

    const disableRefresh = () => {
        if (refreshButton) {
            refreshButton.disabled = true;
            refreshButton.classList.add('opacity-60', 'cursor-not-allowed');
        }
    };

    const enableRefresh = () => {
        if (refreshButton) {
            refreshButton.disabled = false;
            refreshButton.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    };

    const requestLocation = () => {
        if (!navigator.geolocation) {
            setStatus('Perangkat tidak mendukung layanan lokasi.', 'error');
            disableRefresh();
            return;
        }

        setStatus('Mengambil lokasi perangkat...', 'info');
        enableRefresh();

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (latField) {
                    latField.value = pos.coords.latitude.toFixed(6);
                }
                if (lngField) {
                    lngField.value = pos.coords.longitude.toFixed(6);
                }
                setStatus('Lokasi perangkat terdeteksi.', 'success');
            },
            (error) => {
                let message = 'Tidak dapat membaca lokasi perangkat.';
                if (error.code === error.PERMISSION_DENIED) {
                    message = 'Akses lokasi ditolak. Izinkan akses lokasi lalu coba lagi.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    message = 'Lokasi tidak tersedia. Pastikan GPS aktif dan coba lagi.';
                } else if (error.code === error.TIMEOUT) {
                    message = 'Pengambilan lokasi melebihi batas waktu. Coba lagi.';
                }
                setStatus(message, 'error');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 30000,
            }
        );
    };

    requestLocation();

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            requestLocation();
        });
    }

    form.addEventListener('submit', (event) => {
        if (!latField || !lngField) {
            return;
        }

        if (latField.value === '' || lngField.value === '') {
            event.preventDefault();
            setStatus('Lokasi belum terdeteksi. Pastikan layanan lokasi aktif lalu coba lagi.', 'error');
            requestLocation();
        }
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sessionTypeSelect = document.getElementById('sessionType');
    const scheduleSelect = document.getElementById('scheduleId');
    const replacementScheduleSelect = document.getElementById('replacementScheduleId');
    const parallelSelect = document.getElementById('parallelScheduleId');
    const hint = document.getElementById('parallel-schedule-hint');
    const regularScheduleGroup = document.getElementById('regularScheduleGroup');
    const replacementScheduleGroup = document.getElementById('replacementScheduleGroup');
    const parallelScheduleGroup = document.getElementById('parallelScheduleGroup');
    const replacementNoteGroup = document.getElementById('replacementNoteGroup');
    const replacementNote = document.getElementById('replacementNote');

    if (!scheduleSelect || !parallelSelect) {
        return;
    }

    const parallelOptions = Array.from(parallelSelect.options).slice(1);

    const updateParallelOptions = () => {
        const selectedOption = scheduleSelect.options[scheduleSelect.selectedIndex];
        const targetId = scheduleSelect.value;
        const targetLevel = selectedOption?.dataset?.tingkat ?? '';
        const targetDay = selectedOption?.dataset?.hari ?? '';
        const targetStart = selectedOption?.dataset?.mulai ?? '';
        const targetEnd = selectedOption?.dataset?.selesai ?? '';
        let available = 0;

        parallelOptions.forEach((option) => {
            const isMatch = targetId !== ''
                && option.value !== targetId
                && option.dataset.tingkat === targetLevel
                && option.dataset.hari === targetDay
                && option.dataset.mulai === targetStart
                && option.dataset.selesai === targetEnd;
            option.disabled = !isMatch;
            option.hidden = !isMatch;
            if (isMatch) {
                available++;
            }
        });

        if (parallelSelect.value !== '' && parallelSelect.selectedOptions.length > 0 && parallelSelect.selectedOptions[0].disabled) {
            parallelSelect.value = '';
        }

        if (hint) {
            if (targetId === '') {
                hint.textContent = 'Pilih jadwal utama terlebih dahulu untuk melihat opsi paralel.';
            } else if (available > 0) {
                hint.textContent = 'Pilih kelas paralel dengan tingkat dan waktu yang sama (mapel bisa berbeda).';
            } else {
                hint.textContent = 'Tidak ada jadwal paralel yang sesuai.';
            }
        }
    };

    updateParallelOptions();
    scheduleSelect.addEventListener('change', updateParallelOptions);

    const updateSessionType = () => {
        const isReplacement = sessionTypeSelect?.value === 'pengganti';

        if (regularScheduleGroup) {
            regularScheduleGroup.classList.toggle('hidden', isReplacement);
        }
        if (parallelScheduleGroup) {
            parallelScheduleGroup.classList.toggle('hidden', isReplacement);
        }
        if (replacementScheduleGroup) {
            replacementScheduleGroup.classList.toggle('hidden', !isReplacement);
        }
        if (replacementNoteGroup) {
            replacementNoteGroup.classList.toggle('hidden', !isReplacement);
        }

        scheduleSelect.required = !isReplacement;
        scheduleSelect.disabled = isReplacement;
        parallelSelect.disabled = isReplacement;
        if (replacementScheduleSelect) {
            replacementScheduleSelect.required = isReplacement;
            replacementScheduleSelect.disabled = !isReplacement;
        }
        if (replacementNote) {
            replacementNote.required = isReplacement;
        }
    };

    updateSessionType();
    if (sessionTypeSelect) {
        sessionTypeSelect.addEventListener('change', updateSessionType);
    }
});
</script>
