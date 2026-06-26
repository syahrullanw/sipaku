<?php
$shortcuts = [
    [
        'icon' => 'ri-user-star-line',
        'title' => 'Kelola Guru Pengampu',
        'description' => 'Pastikan setiap mata pelajaran memiliki guru pengampu sebelum menyusun SK mengajar.',
        'url' => base_url('akademik/guru-pengampu'),
        'action' => 'Kelola Penugasan',
    ],
    [
        'icon' => 'ri-calendar-schedule-line',
        'title' => 'Kelola Jadwal Mengajar',
        'description' => 'Susun jadwal mengajar guru sebagai lampiran utama SK mengajar.',
        'url' => base_url('akademik/jadwal'),
        'action' => 'Buka Jadwal',
    ],
    [
        'icon' => 'ri-draft-line',
        'title' => 'Rekap Penugasan Akademik',
        'description' => 'Lihat daftar jabatan akademik untuk memastikan SK penugasan sudah sesuai.',
        'url' => base_url('master/jabatan-akademik'),
        'action' => 'Lihat Jabatan',
        'requires_admin' => true,
    ],
];

$templates = [
    [
        'title' => 'SK Mengajar Guru',
        'description' => 'Mengompilasi informasi guru pengampu, jadwal pelajaran, dan data kelas sebagai lampiran SK.',
        'steps' => [
            'Pastikan mata pelajaran dan guru pengampu telah lengkap.',
            'Unduh jadwal terbaru dalam format Excel/PDF dari modul Jadwal Mengajar.',
            'Gunakan format SK mengajar sekolah dan sisipkan lampiran jadwal mengajar.',
        ],
    ],
    [
        'title' => 'SK Penugasan Pembimbing PKL',
        'description' => 'Gunakan data penempatan PKL untuk menetapkan pembimbing dari pihak sekolah.',
        'steps' => [
            'Perbarui data tempat dan pembimbing PKL di modul Prakerin.',
            'Susun rekap siswa per tempat PKL untuk dilampirkan.',
            'Lengkapi naskah SK dengan daftar pembimbing dan masa tugas.',
        ],
    ],
    [
        'title' => 'SK Penugasan Ekskul & Jabatan Akademik',
        'description' => 'Menetapkan guru sebagai pembina ekstrakurikuler atau jabatan akademik khusus.',
        'steps' => [
            'Pastikan jabatan akademik sudah ditetapkan pada tahun ajaran aktif.',
            'Jika terkait ekskul, periksa pembina di modul Ekstrakurikuler.',
            'Gunakan format SK penugasan dan lampirkan daftar tugas/ruang lingkup.',
        ],
    ],
];

$user = auth() ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';

$yearOptions = is_array($yearOptions ?? null) ? $yearOptions : [];
$teacherOptions = is_array($teacherOptions ?? null) ? $teacherOptions : [];
$selectedYearId = (int) ($selectedYearId ?? 0);
$selectedTeacherId = (int) ($selectedTeacherId ?? 0);
$teachers = is_array($teachers ?? null) ? $teachers : [];
$metrics = is_array($metrics ?? null) ? $metrics : [];
$issues = is_array($issues ?? null) ? $issues : [];
$period = is_array($period ?? null) ? $period : [];
$letter = is_array($letter ?? null) ? $letter : [];
$headmaster = is_array($headmaster ?? null) ? $headmaster : null;
$schoolYear = is_array($schoolYear ?? null) ? $schoolYear : null;
$schoolProfile = is_array($schoolProfile ?? null) ? $schoolProfile : null;
$printParams = is_array($printParams ?? null) ? $printParams : [];
$printQuery = http_build_query($printParams);
$printUrl = base_url('tata-usaha/sk-penugasan/cetak' . ($printQuery !== '' ? '?' . $printQuery : ''));

$letterNumber = $letter['number'] ?? '';
$letterSubject = $letter['subject'] ?? '';
$letterPlace = $letter['place'] ?? '';
$letterSignDate = $letter['sign_date'] ?? '';
$letterSignDateFormatted = $letter['sign_date_formatted'] ?? null;
$letterEffectiveStart = $letter['effective_start'] ?? '';
$letterEffectiveStartFormatted = $letter['effective_start_formatted'] ?? null;
$letterEffectiveEnd = $letter['effective_end'] ?? '';
$letterEffectiveEndFormatted = $letter['effective_end_formatted'] ?? null;
$menimbangText = $letter['menimbang_text'] ?? implode("\n", $letter['menimbang'] ?? []);
$mengingatText = $letter['mengingat_text'] ?? implode("\n", $letter['mengingat'] ?? []);
$menetapkanText = $letter['menetapkan_text'] ?? implode("\n", $letter['menetapkan'] ?? []);
$tembusanText = $letter['tembusan_text'] ?? implode("\n", $letter['tembusan'] ?? []);
$positionSummary = is_array($positionSummary ?? null) ? $positionSummary : [];
$signature = is_array($signature ?? null) ? $signature : [];
$signatureAvailable = (bool) ($signature['available'] ?? false);
$signatureStatus = (string) ($signature['status'] ?? 'inactive');
$signatureStatusLabel = (string) ($signature['status_label'] ?? 'Tidak tersedia');
$signatureStatusClass = (string) ($signature['status_class'] ?? 'text-slate-500');
$signatureMessage = $signature['status_message'] ?? ($signatureAvailable ? '' : ($signature['reason'] ?? ''));
$signatureVerificationUrl = $signature['verification_url'] ?? null;
$signatureRequestedAt = $signature['requested_at_formatted'] ?? null;
$signatureApprovedAt = $signature['approved_at_formatted'] ?? null;
$signatureRequestable = (bool) ($signature['requestable'] ?? false);
if ($signatureStatus === 'approved') {
    $signatureActionLabel = 'Perbarui Data & Ajukan Ulang';
} elseif ($signatureStatus === 'pending') {
    $signatureActionLabel = 'Kirim Ulang Permintaan';
} elseif ($signatureStatus === 'revoked') {
    $signatureActionLabel = 'Ajukan Ulang Persetujuan';
} else {
    $signatureActionLabel = 'Ajukan Persetujuan Kepala Sekolah';
}

$hasTeachers = !empty($teachers);
$missingClasses = $issues['missing_classes'] ?? [];
$missingSchedules = $issues['missing_schedules'] ?? [];
$hasWarnings = !empty($missingClasses) || !empty($missingSchedules);

$teacherSelectOptions = ['' => 'Semua guru'];
foreach ($teacherOptions as $id => $label) {
    $teacherSelectOptions[(string) $id] = (string) $label;
}

$periodLabel = $period['label'] ?? ($letter['period_label'] ?? null);
$periodDateLabel = $letter['effective_start_formatted'] && $letter['effective_end_formatted']
    ? sprintf(
        '%s s.d. %s',
        htmlspecialchars($letterEffectiveStartFormatted ?? '', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($letterEffectiveEndFormatted ?? '', ENT_QUOTES, 'UTF-8')
    )
    : ($letter['period_date_label'] ?? null);

$statCards = [
    [
        'icon' => 'ri-user-3-line',
        'label' => 'Guru Ditugaskan',
        'value' => number_format((int) ($metrics['teacher_count'] ?? 0)),
    ],
    [
        'icon' => 'ri-book-open-line',
        'label' => 'Total Mapel',
        'value' => number_format((int) ($metrics['unique_subject_count'] ?? 0)),
    ],
    [
        'icon' => 'ri-door-open-line',
        'label' => 'Kelas Terlibat',
        'value' => number_format((int) ($metrics['unique_class_count'] ?? 0)),
    ],
    [
        'icon' => 'ri-time-line',
        'label' => 'Jumlah Jam/Minggu',
        'value' => number_format((int) ($metrics['total_hours'] ?? 0)),
    ],
];
?>

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-800">Parameter SK</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Sesuaikan data di bawah ini untuk mengatur isi naskah SK dan lampiran penugasan guru. Setiap perubahan akan diperbarui otomatis pada pratinjau dan halaman cetak.
                </p>
                <form method="get" class="mt-6 space-y-4">
                    <div>
                        <label for="tahun_ajaran_id" class="block text-sm font-medium text-slate-600">Tahun Ajaran</label>
                        <select
                            id="tahun_ajaran_id"
                            name="tahun_ajaran_id"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        >
                            <?php foreach ($yearOptions as $id => $label): ?>
                                <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $id === $selectedYearId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="guru_id" class="block text-sm font-medium text-slate-600">Filter Guru</label>
                        <select
                            id="guru_id"
                            name="guru_id"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        >
                            <?php foreach ($teacherSelectOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $value === (string) ($selectedTeacherId > 0 ? (string) $selectedTeacherId : '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Biarkan kosong untuk menampilkan seluruh guru.</p>
                    </div>

                    <div>
                        <label for="nomor_sk" class="block text-sm font-medium text-slate-600">Nomor SK</label>
                        <input
                            id="nomor_sk"
                            name="nomor_sk"
                            type="text"
                            value="<?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Contoh: 800/___/SMK/2025"
                        />
                    </div>

                    <div>
                        <label for="perihal" class="block text-sm font-medium text-slate-600">Perihal / Tentang</label>
                        <input
                            id="perihal"
                            name="perihal"
                            type="text"
                            value="<?= htmlspecialchars($letterSubject, ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Penugasan Guru Mata Pelajaran"
                            required
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="tempat" class="block text-sm font-medium text-slate-600">Tempat Penetapan</label>
                            <input
                                id="tempat"
                                name="tempat"
                                type="text"
                                value="<?= htmlspecialchars($letterPlace, ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                required
                            />
                        </div>
                        <div>
                            <label for="tanggal_sk" class="block text-sm font-medium text-slate-600">Tanggal SK</label>
                            <input
                                id="tanggal_sk"
                                name="tanggal_sk"
                                type="date"
                                value="<?= htmlspecialchars($letterSignDate, ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="berlaku_mulai" class="block text-sm font-medium text-slate-600">Berlaku Mulai</label>
                            <input
                                id="berlaku_mulai"
                                name="berlaku_mulai"
                                type="date"
                                value="<?= htmlspecialchars($letterEffectiveStart, ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="berlaku_sampai" class="block text-sm font-medium text-slate-600">Berlaku Sampai</label>
                            <input
                                id="berlaku_sampai"
                                name="berlaku_sampai"
                                type="date"
                                value="<?= htmlspecialchars($letterEffectiveEnd, ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="menimbang" class="block text-sm font-medium text-slate-600">Menimbang</label>
                        <textarea
                            id="menimbang"
                            name="menimbang"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Gunakan satu baris untuk setiap poin."
                        ><?= htmlspecialchars($menimbangText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div>
                        <label for="mengingat" class="block text-sm font-medium text-slate-600">Mengingat</label>
                        <textarea
                            id="mengingat"
                            name="mengingat"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Gunakan satu baris untuk setiap dasar hukum."
                        ><?= htmlspecialchars($mengingatText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div>
                        <label for="menetapkan" class="block text-sm font-medium text-slate-600">Menetapkan</label>
                        <textarea
                            id="menetapkan"
                            name="menetapkan"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Setiap baris akan menjadi poin PERTAMA, KEDUA, dst."
                        ><?= htmlspecialchars($menetapkanText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div>
                        <label for="tembusan" class="block text-sm font-medium text-slate-600">Tembusan</label>
                        <textarea
                            id="tembusan"
                            name="tembusan"
                            rows="2"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Opsional. Gunakan satu baris per tembusan."
                        ><?= htmlspecialchars($tembusanText, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="flex flex-col gap-3 lg:flex-row">
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Terapkan Parameter
                        </button>
                        <a
                            href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-100 px-4 py-2.5 text-sm font-semibold text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 <?= !$hasTeachers ? 'pointer-events-none opacity-50' : '' ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="ri-printer-line text-base"></i>
                            Cetak SK
                        </a>
                    </div>
                    <?php if (!$hasTeachers): ?>
                        <p class="text-xs text-amber-600">Belum ada data penugasan pada filter saat ini. Lengkapi guru pengampu dan jadwal terlebih dahulu.</p>
                    <?php else: ?>
                        <p class="text-xs text-slate-400">TTD kepala sekolah pada dokumen cetak akan digantikan oleh QR code digital.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Penandatangan</h3>
                <?php if ($headmaster !== null): ?>
                    <div class="mt-3 space-y-1 text-sm text-slate-600">
                        <p class="text-base font-semibold text-slate-800"><?= htmlspecialchars($headmaster['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                        <p><?= htmlspecialchars($headmaster['position'] ?? 'Kepala Sekolah', ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($headmaster['nip'])): ?>
                            <p>NIP: <?= htmlspecialchars($headmaster['nip'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="mt-3 text-sm text-amber-600">Penandatangan belum ditentukan. Atur kepala sekolah pada data tahun ajaran atau jabatan akademik.</p>
                <?php endif; ?>
                <?php if ($letterSignDateFormatted !== null): ?>
                    <p class="mt-4 text-xs text-slate-400">Tanggal penetapan: <?= htmlspecialchars($letterSignDateFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">TTD Digital Kepala Sekolah</h3>
                        <p class="mt-2 text-sm text-slate-500">
                            <?= htmlspecialchars($signatureMessage !== '' ? $signatureMessage : 'Ajukan persetujuan kepala sekolah agar QR dapat diverifikasi melalui portal dokumen.', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= htmlspecialchars($signatureStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($signatureStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <?php if ($signatureApprovedAt !== null): ?>
                    <p class="mt-3 text-xs text-emerald-600">Disetujui pada <?= htmlspecialchars($signatureApprovedAt, ENT_QUOTES, 'UTF-8') ?></p>
                <?php elseif ($signatureRequestedAt !== null): ?>
                    <p class="mt-3 text-xs text-slate-400">Diajuakan pada <?= htmlspecialchars($signatureRequestedAt, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if (!$signatureAvailable): ?>
                    <p class="mt-3 text-xs text-amber-600">
                        <?= htmlspecialchars($signature['reason'] ?? 'TTD digital belum tersedia.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <?php if ($signatureVerificationUrl): ?>
                    <a
                        href="<?= htmlspecialchars($signatureVerificationUrl, ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                    >
                        <i class="ri-external-link-line mr-1"></i>
                        Buka halaman verifikasi
                    </a>
                <?php endif; ?>

                <?php if ($signatureAvailable && $signatureRequestable && $hasTeachers): ?>
                    <form method="post" action="<?= htmlspecialchars(base_url('tata-usaha/sk-penugasan/ttd'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tahun_ajaran_id" value="<?= htmlspecialchars((string) $selectedYearId, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="guru_id" value="<?= htmlspecialchars((string) $selectedTeacherId, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="nomor_sk" value="<?= htmlspecialchars($letterNumber, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="perihal" value="<?= htmlspecialchars($letterSubject, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="tempat" value="<?= htmlspecialchars($letterPlace, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="tanggal_sk" value="<?= htmlspecialchars($letterSignDate, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="berlaku_mulai" value="<?= htmlspecialchars($letterEffectiveStart, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="berlaku_sampai" value="<?= htmlspecialchars($letterEffectiveEnd, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="menimbang" value="<?= htmlspecialchars($menimbangText, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="mengingat" value="<?= htmlspecialchars($mengingatText, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="menetapkan" value="<?= htmlspecialchars($menetapkanText, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="tembusan" value="<?= htmlspecialchars($tembusanText, ENT_QUOTES, 'UTF-8') ?>" />
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50"
                        >
                            <?= htmlspecialchars($signatureActionLabel, ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-800">Ruang Kerja Tata Usaha</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Kelola SK penugasan guru berdasarkan data guru pengampu dan jadwal mengajar yang sudah tercatat. Pastikan seluruh mata pelajaran, kelas, dan jam mengajar telah terisi sebelum mencetak.
                        </p>
                    </div>
                    <?php if (!empty($periodLabel)): ?>
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                            <p class="font-semibold"><?= htmlspecialchars((string) $periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($letterEffectiveStartFormatted) && !empty($letterEffectiveEndFormatted)): ?>
                                <p><?= htmlspecialchars($letterEffectiveStartFormatted, ENT_QUOTES, 'UTF-8') ?> s.d. <?= htmlspecialchars($letterEffectiveEndFormatted, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif (!empty($period['start_date_formatted']) && !empty($period['end_date_formatted'])): ?>
                                <p><?= htmlspecialchars($period['start_date_formatted'], ENT_QUOTES, 'UTF-8') ?> s.d. <?= htmlspecialchars($period['end_date_formatted'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($statCards as $stat): ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-5 text-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-indigo-600">
                                    <i class="<?= htmlspecialchars($stat['icon'], ENT_QUOTES, 'UTF-8') ?> text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($stat['value'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($hasWarnings): ?>
                    <div class="mt-6 space-y-3">
                        <?php if (!empty($missingClasses)): ?>
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                <p class="font-semibold">Beberapa penugasan belum memiliki kelas terhubung:</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <?php foreach ($missingClasses as $item): ?>
                                        <li><?= htmlspecialchars(($item['teacher_name'] ?? 'Guru') . ' — ' . ($item['subject_name'] ?? 'Mata Pelajaran'), ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($missingSchedules)): ?>
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                                <p class="font-semibold">Penjadwalan belum lengkap untuk:</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <?php foreach ($missingSchedules as $item): ?>
                                        <li><?= htmlspecialchars(($item['teacher_name'] ?? 'Guru') . ' — ' . ($item['subject_name'] ?? 'Mata Pelajaran'), ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="mt-2 text-xs">Tambahkan jadwal di menu <a href="<?= htmlspecialchars(base_url('akademik/jadwal'), ENT_QUOTES, 'UTF-8') ?>" class="underline">Jadwal Mengajar</a>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($hasTeachers): ?>
                <div class="space-y-5">
    <?php foreach ($teachers as $index => $teacher): ?>
        <?php
            $teacherName = (string) ($teacher['name'] ?? 'Guru');
            $teacherNip = trim((string) ($teacher['nip'] ?? ''));
            $assignments = is_array($teacher['assignments'] ?? null) ? $teacher['assignments'] : [];
            $totalHours = (int) ($teacher['total_hours'] ?? 0);
            $classCount = (int) ($teacher['class_count'] ?? 0);
            $subjectCount = (int) ($teacher['subject_count'] ?? 0);
            $positions = is_array($teacher['positions'] ?? null) ? $teacher['positions'] : [];
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Guru #<?= $index + 1 ?></p>
                                    <h3 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if ($teacherNip !== ''): ?>
                                        <p class="text-sm text-slate-500">NIP: <?= htmlspecialchars($teacherNip, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5">
                                        <i class="ri-book-open-line text-base text-indigo-500"></i>
                                        <?= number_format($subjectCount) ?> mapel
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5">
                                        <i class="ri-door-open-line text-base text-emerald-500"></i>
                                        <?= number_format($classCount) ?> kelas
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5">
                                    <i class="ri-time-line text-base text-amber-500"></i>
                                    <?= number_format($totalHours) ?> jam/minggu
                                </span>
                                <?php if (!empty($positions)): ?>
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5">
                                        <i class="ri-medal-line text-base text-purple-500"></i>
                                        <?= number_format(count($positions)) ?> jabatan
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($positions)): ?>
                            <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jabatan Akademik</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                    <?php foreach ($positions as $position): ?>
                                        <?php
                                            $positionName = trim((string) ($position['name'] ?? 'Jabatan Akademik'));
                                            $startFormatted = $position['start_date_formatted'] ?? null;
                                            $endFormatted = $position['end_date_formatted'] ?? null;
                                            $rangeLabel = null;

                                            if ($startFormatted !== null && $endFormatted !== null) {
                                                $rangeLabel = $startFormatted . ' s.d. ' . $endFormatted;
                                            } elseif ($startFormatted !== null) {
                                                $rangeLabel = 'Mulai ' . $startFormatted;
                                            } elseif ($endFormatted !== null) {
                                                $rangeLabel = 'Hingga ' . $endFormatted;
                                            }
                                        ?>
                                        <li>
                                            <span class="font-semibold text-slate-700"><?= htmlspecialchars($positionName, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($rangeLabel !== null): ?>
                                                <span class="text-xs text-slate-400"> — <?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Mata Pelajaran</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Kelas</th>
                                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Jadwal</th>
                                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Jam</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($assignments as $assignment): ?>
                                            <?php
                                                $subjectCode = trim((string) ($assignment['subject_code'] ?? ''));
                                                $subjectName = trim((string) ($assignment['subject_name'] ?? 'Mata Pelajaran'));
                                                $classLabels = is_array($assignment['class_labels'] ?? null) ? $assignment['class_labels'] : [];
                                                $scheduleLabels = is_array($assignment['schedule_labels'] ?? null) ? $assignment['schedule_labels'] : [];
                                                $assignmentHours = (int) ($assignment['total_hours'] ?? 0);
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 align-top">
                                                    <div class="font-semibold text-slate-700">
                                                        <?= htmlspecialchars($subjectName !== '' ? $subjectName : 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                    <?php if ($subjectCode !== ''): ?>
                                                        <div class="text-xs text-slate-400">Kode: <?= htmlspecialchars($subjectCode, ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <?php if (!empty($classLabels)): ?>
                                                        <ul class="space-y-1 text-slate-600">
                                                            <?php foreach ($classLabels as $label): ?>
                                                                <li><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-xs font-medium text-amber-600">Belum diatur</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <?php if (!empty($scheduleLabels)): ?>
                                                        <ul class="space-y-1 text-slate-600">
                                                            <?php foreach ($scheduleLabels as $label): ?>
                                                                <li><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-xs font-medium text-rose-600">Belum ada jadwal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-right align-top font-semibold text-slate-700">
                                                    <?= number_format($assignmentHours) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                    Data penugasan belum tersedia untuk filter yang dipilih. Tambahkan guru pengampu dan jadwal mengajar terlebih dahulu.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($positionSummary)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3">
                <h2 class="text-base font-semibold text-slate-800">Rekap Jabatan Akademik</h2>
                <p class="text-sm text-slate-500">
                    Lampiran jabatan akademik seperti wali kelas, pembina, dan wakil kepala sekolah ditampilkan terpisah dari penugasan mengajar untuk mempermudah tata letak SK.
                </p>
            </div>
            <div class="mt-5 space-y-5">
                <?php foreach ($positionSummary as $positionName => $rows): ?>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-700">
                            <?= htmlspecialchars((string) $positionName, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ol class="mt-3 space-y-2 text-sm text-slate-600">
                            <?php foreach ($rows as $index => $row): ?>
                                <?php
                                    $teacherName = (string) ($row['teacher_name'] ?? 'Guru');
                                    $teacherNip = trim((string) ($row['teacher_nip'] ?? ''));
                                    $startLabel = $row['start_date_formatted'] ?? null;
                                    $endLabel = $row['end_date_formatted'] ?? null;
                                    $periodLabel = null;

                                    if ($startLabel !== null && $endLabel !== null) {
                                        $periodLabel = $startLabel . ' s.d. ' . $endLabel;
                                    } elseif ($startLabel !== null) {
                                        $periodLabel = 'Mulai ' . $startLabel;
                                    } elseif ($endLabel !== null) {
                                        $periodLabel = 'Hingga ' . $endLabel;
                                    }
                                ?>
                                <li class="flex flex-col gap-1">
                                    <span class="font-semibold text-slate-700">
                                        <?= htmlspecialchars(($index + 1) . '. ' . $teacherName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div class="text-xs text-slate-400">
                                        <?php if ($teacherNip !== ''): ?>
                                            <span>NIP: <?= htmlspecialchars($teacherNip, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if ($periodLabel !== null): ?>
                                            <span<?= $teacherNip !== '' ? ' class="ml-2"' : '' ?>>Periode: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($row['note'])): ?>
                                        <span class="text-xs text-slate-400">Keterangan: <?= htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Akses Cepat</h2>
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Modul utama tata usaha
            </span>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($shortcuts as $shortcut): ?>
                <?php
                    $requiresAdmin = $shortcut['requires_admin'] ?? false;
                    if ($requiresAdmin && !$isAdmin) {
                        continue;
                    }
                ?>
                <a
                    href="<?= htmlspecialchars($shortcut['url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="group flex h-full flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md"
                >
                    <div class="space-y-3">
                        <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 group-hover:bg-indigo-200">
                            <i class="<?= htmlspecialchars($shortcut['icon'], ENT_QUOTES, 'UTF-8') ?> text-lg"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-base font-semibold text-slate-800">
                                <?= htmlspecialchars($shortcut['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="text-sm text-slate-600">
                                <?= htmlspecialchars($shortcut['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 transition group-hover:text-indigo-500">
                        <?= htmlspecialchars($shortcut['action'], ENT_QUOTES, 'UTF-8') ?>
                        <i class="ri-arrow-right-line text-base"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Panduan SK Penugasan</h2>
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Ikuti alur berikut sebelum mencetak SK
            </span>
        </div>
        <div class="space-y-4">
            <?php foreach ($templates as $template): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">
                                    <?= htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    <?= htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                        </div>
                        <ol class="space-y-2 text-sm text-slate-600">
                            <?php foreach ($template['steps'] as $index => $step): ?>
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-600"><?= $index + 1 ?></span>
                                    <span><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
