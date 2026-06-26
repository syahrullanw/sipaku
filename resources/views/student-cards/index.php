<?php
    /** @var array<int, array<string, mixed>> $classes */
    /** @var array<int, array<string, mixed>> $students */
    $hasClasses = !empty($classes ?? []);
    $hasStudents = !empty($students ?? []);
    $selectedStudent = $selectedStudent ?? null;

    $formatDate = static function (?string $date): string {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
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

    $selectedClass = $selectedClass ?? null;
    $singlePrintUrl = $singlePrintUrl ?? null;
    $classPrintUrl = $classPrintUrl ?? null;
    $allPrintUrl = $allPrintUrl ?? null;
    $availableClasses = $classes ?? [];
    $hasMultipleClasses = count($availableClasses) > 1;

    $photoPath = $selectedStudent['foto_path'] ?? null;
    $photoUrl = $photoPath ? asset($photoPath) : null;
    $birthLabel = $selectedStudent !== null
        ? trim(($selectedStudent['tempat_lahir'] ?? '') . ', ' . $formatDate($selectedStudent['tanggal_lahir'] ?? null), ', ')
        : null;
    $contactNumber = '';
    if ($selectedStudent !== null) {
        $telepon = trim((string) ($selectedStudent['telepon'] ?? ''));
        $hp = trim((string) ($selectedStudent['hp'] ?? ''));
        $contactNumber = $telepon !== '' ? $telepon : $hp;
    }
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Cetak Kartu Pelajar</h2>
        <p class="mt-2 text-sm text-slate-500">
            Pilih kelas dan siswa untuk menyiapkan kartu pelajar. Kartu memuat identitas utama, foto siswa,
            serta kode QR verifikasi. Setelah memilih data, Anda dapat mencetak untuk siswa tertentu, seluruh
            siswa dalam kelas, atau semua kelas yang tersedia.
        </p>
    </div>

    <?php if (!$hasClasses): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Belum ada kelas yang dapat diakses. Pastikan tahun ajaran aktif telah memiliki data kelas
            dan Anda terdaftar sebagai wali kelas.
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Parameter Cetak</h3>
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
                                        <?= htmlspecialchars(($class['tingkat'] ?? '-') . ' • ' . ($class['nama'] ?? '-') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="siswa_id" class="block text-sm font-medium text-slate-600">Siswa</label>
                            <?php if (!$hasStudents): ?>
                                <div class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                                    Belum ada siswa pada kelas terpilih.
                                </div>
                            <?php else: ?>
                                <select
                                    id="siswa_id"
                                    name="siswa_id"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    required
                                >
                                    <?php foreach ($students as $studentOption): ?>
                                        <?php $studentOptionId = (int) ($studentOption['id'] ?? 0); ?>
                                        <option
                                            value="<?= htmlspecialchars((string) $studentOptionId, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $studentOptionId === (int) ($selectedStudentId ?? 0) ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(($studentOption['nama'] ?? '-') . ' — ' . ($studentOption['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 focus:outline-none focus:ring focus:ring-slate-300"
                            >
                                <i class="ri-refresh-line text-base"></i>
                                Perbarui
                            </button>
                            <?php if ($singlePrintUrl !== null): ?>
                                <a
                                    href="<?= htmlspecialchars($singlePrintUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring focus:ring-indigo-300"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="ri-user-line text-base"></i>
                                    Cetak Siswa Ini
                                </a>
                            <?php endif; ?>
                            <?php if ($classPrintUrl !== null): ?>
                                <a
                                    href="<?= htmlspecialchars($classPrintUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring focus:ring-emerald-300"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="ri-group-line text-base"></i>
                                    Cetak Kelas Ini
                                </a>
                            <?php endif; ?>
                            <?php if ($allPrintUrl !== null): ?>
                                <a
                                    href="<?= htmlspecialchars($allPrintUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900 focus:outline-none focus:ring focus:ring-slate-400"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="ri-printer-line text-base"></i>
                                    Cetak Semua Kelas
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-800">Pratinjau Kartu</h3>
                    <?php if ($selectedStudent === null): ?>
                        <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                            Pilih siswa terlebih dahulu untuk menampilkan pratinjau kartu pelajar.
                        </div>
                    <?php else: ?>
                        <div class="mt-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50 p-6 text-slate-700 md:flex-row">
                            <div class="flex flex-col items-center gap-3 md:w-40">
                                <div class="h-40 w-32 overflow-hidden rounded-lg border border-slate-300 bg-white">
                                    <?php if ($photoUrl !== null): ?>
                                        <img
                                            src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Foto <?= htmlspecialchars($selectedStudent['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            class="h-full w-full object-cover"
                                        />
                                    <?php else: ?>
                                        <div class="flex h-full w-full items-center justify-center text-xs font-semibold uppercase text-slate-400">
                                            Tidak ada foto
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($validationUrl ?? '')): ?>
                                    <div class="w-full rounded-lg border border-dashed border-slate-300 bg-white px-2 py-3 text-center">
                                        <p class="text-[10px] font-semibold uppercase text-slate-500">QR Verifikasi</p>
                                        <p class="mt-1 truncate text-[10px] text-slate-400">
                                            <?= htmlspecialchars(parse_url($validationUrl, PHP_URL_QUERY) ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 space-y-3">
                                <div>
                                    <p class="text-sm text-slate-500">Nama Lengkap</p>
                                    <p class="text-lg font-semibold text-slate-800">
                                        <?= htmlspecialchars($selectedStudent['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($selectedStudent, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($selectedStudent, 'ml-1 align-middle') ?>
                                    </p>
                                </div>
                                <div class="grid grid-cols-1 gap-3 text-sm text-slate-600 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs uppercase text-slate-400">NIPD</p>
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars($selectedStudent['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase text-slate-400">Kelas</p>
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars($selectedStudent['kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase text-slate-400">Tempat / Tanggal Lahir</p>
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars($birthLabel ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase text-slate-400">Alamat</p>
                                        <p class="font-semibold text-slate-700">
                                            <?= htmlspecialchars($selectedStudent['alamat'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase text-slate-400">Telepon</p>
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars($contactNumber !== '' ? $contactNumber : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($validationUrl ?? '')): ?>
                                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-700">
                                        <p class="font-semibold uppercase tracking-wide text-indigo-800">Validasi QR</p>
                                        <p class="mt-1 break-all"><?= htmlspecialchars($validationUrl, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
