<?php
    /** @var array<int, array<string, mixed>> $classes */
    $hasClasses = !empty($classes ?? []);
    $hasStudents = !empty($students ?? []);
    $paperSize = strtolower((string) ($paperSize ?? 'f4'));
    if (!in_array($paperSize, ['f4', 'a4'], true)) {
        $paperSize = 'f4';
    }
    $paperOptions = $paperOptions ?? [
        'f4' => 'F4 / Folio (33 x 21,5 cm)',
        'a4' => 'A4 (29,7 x 21 cm)',
    ];
    $printParams = [
        'kelas_id' => $selectedClassId ?? 0,
        'siswa_id' => $selectedStudentId ?? 0,
        'semester' => $semester ?? 1,
        'paper' => $paperSize,
    ];
    $printQuery = http_build_query($printParams);
    $printUrl = base_url('raport/tengah-semester/cetak') . '?' . $printQuery;
    $semesterLabel = $semesterOptions[$semester] ?? ($semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)');
    $schoolName = $schoolProfile['nama'] ?? 'Nama Sekolah';
    $midtermDateRaw = $schoolProfile['tanggal_raport_tengah_semester'] ?? null;

    /**
     * @param string|null $date
     */
    $formatDate = static function ($date): string {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '-';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);

        return sprintf('%d %s %s', (int) date('j', $timestamp), $month, date('Y', $timestamp));
    };

    $midtermDateLabel = $formatDate($midtermDateRaw);
    $selectedClassData = is_array($selectedClass ?? null) ? $selectedClass : [];
    $selectedStudentData = is_array($selectedStudent ?? null) ? $selectedStudent : [];
    $selectedStudentName = $selectedStudentData['nama'] ?? '-';
    $selectedStudentNisn = $selectedStudentData['nisn'] ?? '-';
    $selectedStudentNipd = $selectedStudentData['nipd'] ?? '-';
    $selectedClassLabel = !empty($selectedClassData)
        ? (($selectedClassData['tingkat'] ?? '-') . ' ' . ($selectedClassData['nama'] ?? '-'))
        : '-';
    $schoolYearLabel = '-';
    if (isset($selectedClassData['tahun_ajaran_nama']) && $selectedClassData['tahun_ajaran_nama'] !== '') {
        $schoolYearLabel = $selectedClassData['tahun_ajaran_nama'];
    } elseif (isset($selectedStudentData['tahun_ajaran_nama']) && $selectedStudentData['tahun_ajaran_nama'] !== '') {
        $schoolYearLabel = $selectedStudentData['tahun_ajaran_nama'];
    }

    $isHomeroomUser = (bool) ($isHomeroom ?? false);
    $signatureEnabled = (bool) ($digitalSignatureEnabled ?? false);
    $canRequestSignature = (bool) ($canRequestDigitalSignature ?? $signatureEnabled);
    $signatureSummary = $digitalSignatureSummary ?? ['total' => 0, 'requested' => 0, 'pending' => 0, 'approved' => 0, 'revoked' => 0, 'not_requested' => 0];
    $studentSignature = $selectedStudentSignature ?? null;
    $studentSignatureStatus = is_array($studentSignature) ? ($studentSignature['status'] ?? null) : null;
    $studentStatusLabel = 'Belum diajukan';
    $studentStatusClass = 'border-slate-200 bg-slate-50 text-slate-600';
    $studentStatusMessage = 'Ajukan TTD digital agar kepala sekolah dapat menyetujui laporan tengah semester siswa ini.';

    if ($studentSignatureStatus === 'pending') {
        $studentStatusLabel = 'Menunggu persetujuan';
        $studentStatusClass = 'border-amber-200 bg-amber-50 text-amber-600';
        $studentStatusMessage = 'Dokumen telah diajukan dan sedang menunggu persetujuan kepala sekolah.';
    } elseif ($studentSignatureStatus === 'approved') {
        $studentStatusLabel = 'Telah disetujui';
        $studentStatusClass = 'border-emerald-200 bg-emerald-50 text-emerald-600';
        $studentStatusMessage = 'TTD digital tengah semester sudah disetujui. Pastikan QR tampil saat mencetak.';
    } elseif ($studentSignatureStatus === 'revoked') {
        $studentStatusLabel = 'Dicabut';
        $studentStatusClass = 'border-rose-200 bg-rose-50 text-rose-600';
        $studentStatusMessage = 'Persetujuan sebelumnya dicabut. Ajukan ulang setelah perbaikan.';
    } elseif (is_string($studentSignatureStatus) && $studentSignatureStatus !== '') {
        $studentStatusLabel = ucfirst($studentSignatureStatus);
    }

    $canSubmitStudent = $isHomeroomUser
        && $canRequestSignature
        && $hasStudents
        && (int) ($selectedStudentId ?? 0) > 0
        && ($studentSignatureStatus === null || $studentSignatureStatus === 'revoked');

    $canSubmitClass = $isHomeroomUser
        && $canRequestSignature
        && $hasStudents
        && (($signatureSummary['not_requested'] ?? 0) > 0 || ($signatureSummary['revoked'] ?? 0) > 0);

    $documentType = $digitalSignatureDocumentType ?? 'midterm_report';
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Laporan Tengah Semester</h2>
        <p class="mt-2 text-sm text-slate-500">
            Gunakan halaman ini untuk menyiapkan dan mencetak dokumen laporan tengah semester bagi siswa terpilih.
            Pilih kelas, siswa, serta semester terlebih dahulu sebelum mencetak.
        </p>
    </div>

    <?php if (!$hasClasses): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Belum ada data kelas yang tersedia untuk akun ini. Tambahkan kelas terlebih dahulu atau pastikan Anda tercatat sebagai wali kelas pada tahun ajaran aktif.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Parameter Laporan</h3>
                    <form method="get" class="mt-5 space-y-4">
                        <div>
                            <label for="kelas_id" class="block text-sm font-medium text-slate-600">Kelas</label>
                            <select
                                id="kelas_id"
                                name="kelas_id"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                required
                            >
                                <?php foreach ($classes as $class): ?>
                                    <?php $classId = (int) ($class['id'] ?? 0); ?>
                                    <option
                                        value="<?= htmlspecialchars((string) $classId, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $classId === (int) ($selectedClassId ?? 0) ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' • ' . ($class['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="siswa_id" class="block text-sm font-medium text-slate-600">Siswa</label>
                            <?php if (!$hasStudents): ?>
                                <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                    Belum ada siswa pada kelas ini.
                                </div>
                            <?php else: ?>
                                <select
                                    id="siswa_id"
                                    name="siswa_id"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    required
                                >
                                    <?php foreach ($students as $student): ?>
                                        <?php $studentId = (int) ($student['id'] ?? 0); ?>
                                        <option
                                            value="<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $studentId === (int) ($selectedStudentId ?? 0) ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(($student['nama'] ?? '-') . ' — ' . ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-600">Semester</label>
                            <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                <i class="ri-information-line mr-1 text-slate-400"></i>
                                <?= htmlspecialchars($semesterOptions[$semester] ?? 'Semester 1 (Ganjil)', ENT_QUOTES, 'UTF-8') ?>
                                <span class="ml-1 text-xs text-slate-400">(semester aktif)</span>
                            </div>
                        </div>

                        <div>
                            <label for="paper" class="block text-sm font-medium text-slate-600">Ukuran Kertas</label>
                            <select
                                id="paper"
                                name="paper"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($paperOptions as $value => $label): ?>
                                    <option
                                        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $value === $paperSize ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a
                                href="<?= htmlspecialchars(base_url('raport/tengah-semester'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors duration-200 hover:bg-slate-100"
                            >
                                Reset
                            </a>
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/60"
                            >
                                Terapkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Ringkasan</h3>
                    <div class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase text-slate-400">Sekolah</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">Tanggal Raport Tengah Semester</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($midtermDateLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">Siswa</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($selectedStudentName . ' — ' . $selectedStudentNisn, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">NIPD / NISN</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($selectedStudentNipd . ' / ' . $selectedStudentNisn, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">Kelas</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($selectedClassLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">Semester</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase text-slate-400">Tahun Pelajaran</p>
                            <p class="mt-1 font-semibold text-slate-700"><?= htmlspecialchars($schoolYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <?php if ($isHomeroomUser): ?>
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-800">TTD Digital Tengah Semester</h4>
                                    <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($studentStatusMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $studentStatusClass ?>">
                                    <?= htmlspecialchars($studentStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <?php if (!$signatureEnabled): ?>
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                    TTD digital belum diaktifkan oleh admin pada tahun ajaran aktif.
                                </div>
                            <?php elseif (!$hasStudents): ?>
                                <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                    Tambahkan siswa ke kelas ini sebelum mengajukan TTD digital.
                                </div>
                            <?php elseif (!$canRequestSignature): ?>
                                <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                    TTD digital semester lampau ditampilkan sebagai riwayat. Pengajuan baru hanya tersedia pada tahun ajaran aktif.
                                </div>
                            <?php else: ?>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Siswa</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-700"><?= htmlspecialchars((string) ($signatureSummary['total'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                                        <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Disetujui</p>
                                        <p class="mt-1 text-lg font-semibold text-emerald-700"><?= htmlspecialchars((string) ($signatureSummary['approved'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                                        <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Menunggu</p>
                                        <p class="mt-1 text-lg font-semibold text-amber-700"><?= htmlspecialchars((string) ($signatureSummary['pending'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Belum diajukan</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-700"><?= htmlspecialchars((string) ($signatureSummary['not_requested'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                    <form
                                        method="post"
                                        action="<?= htmlspecialchars(base_url('raport/ttd-digital/request'), ENT_QUOTES, 'UTF-8') ?>"
                                        class="flex-1"
                                        onsubmit="return confirm('Ajukan TTD digital tengah semester untuk siswa ini?');"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) ($selectedStudentId ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="semester" value="<?= htmlspecialchars((string) ($semester ?? 1), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="document_type" value="<?= htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8') ?>">
                                        <button
                                            type="submit"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200 disabled:cursor-not-allowed disabled:bg-slate-300"
                                            <?= $canSubmitStudent ? '' : 'disabled' ?>
                                        >
                                            <i class="ri-checkbox-circle-line text-lg"></i>
                                            Ajukan TTD Siswa Ini
                                        </button>
                                    </form>
                                    <form
                                        method="post"
                                        action="<?= htmlspecialchars(base_url('raport/ttd-digital/request-class'), ENT_QUOTES, 'UTF-8') ?>"
                                        class="flex-1"
                                        onsubmit="return confirm('Ajukan TTD digital tengah semester untuk seluruh siswa di kelas ini?');"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="class_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="semester" value="<?= htmlspecialchars((string) ($semester ?? 1), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="document_type" value="<?= htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8') ?>">
                                        <button
                                            type="submit"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 focus:outline-none focus:ring focus:ring-indigo-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                            <?= $canSubmitClass ? '' : 'disabled' ?>
                                        >
                                            <i class="ri-team-line text-lg"></i>
                                            Ajukan TTD Semua Siswa
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6">
                        <?php if (!$canPrint): ?>
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                                Pilih kelas dan siswa terlebih dahulu untuk mengaktifkan tombol cetak laporan tengah semester.
                            </div>
                        <?php else: ?>
                            <a
                                href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-500 px-5 py-3 text-sm font-semibold text-white transition-colors duration-200 hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400/60"
                            >
                                <i class="ri-printer-line text-lg"></i>
                                Cetak Laporan Tengah Semester
                            </a>
                            <p class="mt-2 text-xs text-slate-400">
                                Tautan akan membuka tab baru dengan tampilan siap cetak. Gunakan tombol <em>Cetak / Simpan PDF</em> pada halaman tersebut.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
