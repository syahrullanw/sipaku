<?php
    $students = $students ?? [];
    $classOptions = $classOptions ?? [];
    $selectedClassId = (int) ($selectedClassId ?? 0);
    $selectedClass = $selectedClass ?? null;
    $statusOptions = $statusOptions ?? [];
    $statusFilter = (string) ($statusFilter ?? 'aktif');
    $keyword = trim((string) ($keyword ?? ''));
    $defaultClassOptionLabel = $defaultClassOptionLabel ?? 'Semua Kelas';
    $totalStudents = (int) ($totalStudents ?? count($students));
    $classCount = (int) ($classCount ?? count($classOptions));
    $isAdmin = (bool) ($isAdmin ?? false);
    $academicHistory = $academicHistory ?? [
        'promotions' => [],
        'graduations' => [],
        'achievements' => [],
        'extracurriculars' => [],
        'attendance' => [],
        'attitudes' => [],
        'notes' => [],
        'prakerin' => [],
    ];
    $selectedStudent = $selectedStudent ?? null;
    $selectedStudentId = (int) ($selectedStudentId ?? 0);
    $genderMap = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];
    $attitudeLabels = [
        'spiritual' => 'Sikap Spiritual',
        'sosial' => 'Sikap Sosial',
    ];
    $valueOrDash = static function (mixed $value): string {
        if ($value === null) {
            return '-';
        }
        $stringValue = trim((string) $value);

        return $stringValue === '' ? '-' : $stringValue;
    };
    $formatDate = static function (?string $date, bool $withTime = false) use ($valueOrDash): string {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $valueOrDash($date);
        }

        return $withTime
            ? date('d M Y H:i', $timestamp)
            : date('d M Y', $timestamp);
    };
    $formatCoordinate = static function (?string $latitude, ?string $longitude) use ($valueOrDash): string {
        $lat = $latitude !== null ? trim((string) $latitude) : '';
        $lng = $longitude !== null ? trim((string) $longitude) : '';

        if ($lat === '' && $lng === '') {
            return '-';
        }

        $latFormatted = $lat !== '' ? rtrim(rtrim(number_format((float) $lat, 6, '.', ''), '0'), '.') : '-';
        $lngFormatted = $lng !== '' ? rtrim(rtrim(number_format((float) $lng, 6, '.', ''), '0'), '.') : '-';

        return sprintf('%s / %s', $latFormatted, $lngFormatted);
    };
    $buildAddress = static function (array $student) use ($valueOrDash): string {
        $segments = [];
        $address = trim((string) ($student['alamat'] ?? ''));
        if ($address !== '') {
            $segments[] = $address;
        }

        $rt = $valueOrDash($student['rt'] ?? null);
        $rw = $valueOrDash($student['rw'] ?? null);
        if ($rt !== '-' || $rw !== '-') {
            $segments[] = sprintf('RT %s / RW %s', $rt, $rw);
        }

        foreach (['dusun' => 'Dusun', 'kelurahan' => 'Kelurahan', 'kecamatan' => 'Kecamatan'] as $key => $label) {
            $fieldValue = $valueOrDash($student[$key] ?? null);
            if ($fieldValue !== '-') {
                $segments[] = $label . ': ' . $fieldValue;
            }
        }

        $postal = $valueOrDash($student['kode_pos'] ?? null);
        if ($postal !== '-') {
            $segments[] = 'Kode Pos: ' . $postal;
        }

        return empty($segments) ? '-' : implode("\n", $segments);
    };
    $parentFields = static function (array $student, string $prefix) use ($valueOrDash): array {
        return [
            'Nama' => $student[$prefix . '_nama'] ?? null,
            'Tahun Lahir' => $student[$prefix . '_tahun_lahir'] ?? null,
            'Pendidikan' => $student[$prefix . '_jenjang_pendidikan'] ?? null,
            'Pekerjaan' => $student[$prefix . '_pekerjaan'] ?? null,
            'Penghasilan' => $student[$prefix . '_penghasilan'] ?? null,
            'NIK' => $student[$prefix . '_nik'] ?? null,
        ];
    };
    $hasParentData = static function (array $fields): bool {
        foreach ($fields as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    };
    $statusBadges = [
        'aktif' => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
        'nonaktif' => 'bg-rose-50 text-rose-700 border border-rose-100',
        'belum_masuk' => 'bg-amber-50 text-amber-700 border border-amber-100',
        'mutasi' => 'bg-sky-50 text-sky-700 border border-sky-100',
        'pindah' => 'bg-amber-50 text-amber-700 border border-amber-100',
        'residu' => 'bg-slate-100 text-slate-700 border border-slate-200',
    ];
    $dapodikLabels = [
        'aktif' => 'Aktif',
        'belum_masuk' => 'Belum Masuk Dapodik',
        'mutasi' => 'Mutasi',
        'pindah' => 'Pindah',
        'residu' => 'Residu',
    ];
    $documentDefinitions = \App\Support\StudentDocumentFields::all();
    $buildStudentLink = static function (array $params, int $studentId): string {
        $query = array_filter([
            'kelas_id' => $params['kelas_id'] ?? null,
            'status' => $params['status'] ?? null,
            'q' => $params['q'] ?? null,
            'siswa_id' => $studentId,
        ], static fn ($value) => $value !== null && $value !== '');

        $queryString = http_build_query($query);

        return base_url('buku-induk') . ($queryString !== '' ? '?' . $queryString : '');
    };
    $buildPrintLink = static function (array $params, int $studentId, array $extra = []): string {
        $query = array_filter([
            'kelas_id' => $params['kelas_id'] ?? null,
            'status' => $params['status'] ?? null,
            'q' => $params['q'] ?? null,
            'siswa_id' => $studentId,
        ], static fn ($value) => $value !== null && $value !== '');

        foreach ($extra as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query[$key] = $value;
        }

        $queryString = http_build_query($query);

        return base_url('buku-induk/cetak') . ($queryString !== '' ? '?' . $queryString : '');
    };
    $buildExportLink = static function (array $params, int $studentId): string {
        $query = array_filter([
            'kelas_id' => $params['kelas_id'] ?? null,
            'status' => $params['status'] ?? null,
            'q' => $params['q'] ?? null,
            'siswa_id' => $studentId,
        ], static fn ($value) => $value !== null && $value !== '');

        $queryString = http_build_query($query);

        return base_url('buku-induk/export') . ($queryString !== '' ? '?' . $queryString : '');
    };
    $filterParams = [
        'kelas_id' => $selectedClassId > 0 ? (string) $selectedClassId : null,
        'status' => $statusFilter,
        'q' => $keyword,
    ];
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-sm font-semibold text-slate-800">Filter Buku Induk</p>
                <p class="text-xs text-slate-500">
                    Menampilkan data rinci setiap siswa yang tersimpan di sistem.
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Data</p>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($totalStudents) ?></p>
            </div>
        </div>
        <form
            action="<?= htmlspecialchars(base_url('buku-induk'), ENT_QUOTES, 'UTF-8') ?>"
            method="get"
            class="grid gap-4 px-6 py-5 lg:grid-cols-4"
        >
            <div>
                <label for="kelas_id" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Kelas
                </label>
                <select
                    id="kelas_id"
                    name="kelas_id"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="0"><?= htmlspecialchars($defaultClassOptionLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php foreach ($classOptions as $classId => $label): ?>
                        <option
                            value="<?= (int) $classId ?>"
                            <?= $selectedClassId === (int) $classId ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Status
                </label>
                <select
                    id="status"
                    name="status"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label for="q" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Kata Kunci
                </label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Cari nama, NISN, NIPD, NIK, atau nama kelas"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            <div class="lg:col-span-4 flex flex-wrap gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                >
                    <i class="ri-filter-3-line text-base"></i>
                    Terapkan Filter
                </button>
                <a
                    href="<?= htmlspecialchars(base_url('buku-induk'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-800"
                >
                    <i class="ri-refresh-line text-base"></i>
                    Atur Ulang
                </a>
            </div>
        </form>
    </div>

    <?php if (empty($students)): ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
            <i class="ri-folders-line text-4xl text-slate-400"></i>
            <p class="mt-3 text-base font-semibold text-slate-800">Belum ada data siswa.</p>
            <p class="text-sm text-slate-500">
                <?= $isAdmin ? 'Tambahkan data siswa pada menu Master Siswa untuk memulai.' : 'Tidak ada siswa pada kelas yang Anda pilih.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">Daftar Siswa</p>
                <p class="text-xs text-slate-500">Pilih siswa untuk melihat buku induk lengkap.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Nama</th>
                            <th class="px-4 py-2 text-left">Kelas</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($students as $listStudent): ?>
                            <tr class="<?= $selectedStudentId === (int) ($listStudent['id'] ?? 0) ? 'bg-indigo-50/50' : '' ?>">
                                <td class="px-4 py-3 font-medium text-slate-800">
                                    <?= htmlspecialchars($listStudent['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    <?= student_status_badge($listStudent, 'ml-1 align-middle') ?>
                                    <?= student_dapodik_badge($listStudent, 'ml-1 align-middle') ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?= htmlspecialchars($listStudent['kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?= htmlspecialchars($statusBadges[strtolower((string) ($listStudent['status'] ?? ''))] ?? 'bg-slate-100 text-slate-700 border border-slate-200', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($listStudent['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        href="<?= htmlspecialchars($buildStudentLink($filterParams, (int) ($listStudent['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
                                        class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:border-indigo-300 hover:text-indigo-800"
                                    >
                                        <i class="ri-book-open-line text-sm"></i>
                                        Lihat Buku
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($selectedStudent === null): ?>
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center">
                <i class="ri-book-3-line text-4xl text-slate-400"></i>
                <p class="mt-3 text-base font-semibold text-slate-800">Pilih siswa untuk melihat buku induk.</p>
                <p class="text-sm text-slate-500">
                    Gunakan tombol "Lihat Buku" pada daftar siswa di atas untuk membuka detail.
                </p>
            </div>
        <?php else: ?>
            <?php
                $student = $selectedStudent;
                $studentId = $selectedStudentId;
                $photoPath = trim((string) ($student['foto_path'] ?? ''));
                $photoUrl = $photoPath !== '' ? asset($photoPath) : null;
                $genderLabel = $genderMap[$student['jenis_kelamin'] ?? ''] ?? '-';
                $studentStatus = strtolower((string) ($student['status'] ?? ''));
                $dapodikStatus = strtolower((string) ($student['status_dapodik'] ?? ''));
                $dapodikStatusLabel = $dapodikLabels[$dapodikStatus] ?? ($student['status_dapodik'] ?? '-');
                $studentStatusClass = $statusBadges[$studentStatus] ?? 'bg-slate-100 text-slate-700 border border-slate-200';
                $dapodikStatusClass = $statusBadges[$dapodikStatus] ?? 'bg-slate-100 text-slate-700 border border-slate-200';
                $addressText = $buildAddress($student);
                $ayahFields = $parentFields($student, 'ayah');
                $ibuFields = $parentFields($student, 'ibu');
                $waliFields = $parentFields($student, 'wali');
                $documentStatuses = [];
                foreach ($documentDefinitions as $definition) {
                    $column = $definition['column'];
                    $path = trim((string) ($student[$column] ?? ''));
                    $documentStatuses[] = [
                        'label' => $definition['label'],
                        'path' => $path,
                    ];
                }
                $studentPromotions = $academicHistory['promotions'][$studentId] ?? [];
                $studentGraduations = $academicHistory['graduations'][$studentId] ?? [];
                $studentAttendance = $academicHistory['attendance'][$studentId] ?? [];
                $studentAchievements = $academicHistory['achievements'][$studentId] ?? [];
                $studentExtracurriculars = $academicHistory['extracurriculars'][$studentId] ?? [];
                $studentAttitudes = $academicHistory['attitudes'][$studentId] ?? [];
                $studentNotes = $academicHistory['notes'][$studentId] ?? [];
                $studentPrakerin = $academicHistory['prakerin'][$studentId] ?? [];
                $studentSubjectHistory = $academicHistory['subjects'][$studentId] ?? [];
                $subjectHistoryEntries = array_values($studentSubjectHistory);
                if (!empty($subjectHistoryEntries)) {
                    usort(
                        $subjectHistoryEntries,
                        static function (array $a, array $b): int {
                            $left = $a['sort_key'] ?? null;
                            $right = $b['sort_key'] ?? null;
                            if ($left === $right) {
                                return 0;
                            }
                            if ($left === null) {
                                return 1;
                            }
                            if ($right === null) {
                                return -1;
                            }

                            return strcmp((string) $left, (string) $right);
                        }
                    );
                }
                $printLink = $buildPrintLink($filterParams, $studentId);
                $exportLink = $buildExportLink($filterParams, $studentId);
            ?>
            <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start gap-6 border-b border-slate-100 px-6 py-5">
                    <?php if ($photoUrl !== null): ?>
                        <div class="h-20 w-20 overflow-hidden rounded-xl border border-slate-100">
                                <img
                                    src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Foto <?= htmlspecialchars($student['nama'] ?? 'Siswa', ENT_QUOTES, 'UTF-8') ?>"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        <?php else: ?>
                            <div class="flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-slate-400">
                                <i class="ri-user-line text-3xl"></i>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-slate-800">
                                        <?= htmlspecialchars((string) ($student['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                    </h3>
                                    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold <?= htmlspecialchars($studentStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                        Status: <?= htmlspecialchars($student['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold <?= htmlspecialchars($dapodikStatusClass, ENT_QUOTES, 'UTF-8') ?>">
                                        Dapodik: <?= htmlspecialchars($dapodikStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        href="<?= htmlspecialchars($printLink, ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank"
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-900"
                                    >
                                        <i class="ri-printer-line text-sm"></i>
                                Cetak
                            </a>
                            <a
                                href="<?= htmlspecialchars($exportLink, ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                            >
                                <i class="ri-file-download-line text-sm"></i>
                                Export PDF
                            </a>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-500">
                                <span>NIPD: <?= htmlspecialchars($valueOrDash($student['nipd'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                <span>NISN: <?= htmlspecialchars($valueOrDash($student['nisn'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                <span>Kelas: <?= htmlspecialchars($valueOrDash($student['kelas_nama'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                <span>Tahun Ajaran: <?= htmlspecialchars($valueOrDash($student['tahun_ajaran_nama'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="text-sm text-slate-500">
                                Jurusan: <?= htmlspecialchars($valueOrDash($student['jurusan_nama'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-2xl border border-slate-200/60 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Identitas Peserta Didik</h4>
                                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                                    <?php
                                        $identityRows = [
                                            'Nama Lengkap' => $student['nama'] ?? null,
                                            'NIK' => $student['nik'] ?? null,
                                            'Nomor KK' => $student['nomor_kk'] ?? null,
                                            'Tempat Lahir' => $student['tempat_lahir'] ?? null,
                                            'Tanggal Lahir' => $formatDate($student['tanggal_lahir'] ?? null),
                                            'Jenis Kelamin' => $genderLabel,
                                            'Agama' => $student['agama'] ?? null,
                                            'Kebutuhan Khusus' => $student['kebutuhan_khusus'] ?? null,
                                            'Anak Ke' => $student['anak_ke'] ?? null,
                                            'Jumlah Saudara Kandung' => $student['jumlah_saudara_kandung'] ?? null,
                                        ];
                                    ?>
                                    <?php foreach ($identityRows as $label => $value): ?>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                            <dd class="text-sm text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>

                            <section class="rounded-2xl border border-slate-200/60 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Alamat & Kontak</h4>
                                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat Lengkap</dt>
                                        <dd class="whitespace-pre-line text-sm text-slate-700"><?= htmlspecialchars($addressText, ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <?php
                                        $contactRows = [
                                            'Jenis Tinggal' => $student['jenis_tinggal'] ?? null,
                                            'Alat Transportasi' => $student['alat_transportasi'] ?? null,
                                            'Telepon Rumah' => $student['telepon'] ?? null,
                                            'HP' => $student['hp'] ?? null,
                                            'Email' => $student['email'] ?? null,
                                            'Jarak ke Sekolah (km)' => $student['jarak_rumah_ke_sekolah_km'] ?? null,
                                            'Koordinat (Lintang/Bujur)' => $formatCoordinate($student['lintang'] ?? null, $student['bujur'] ?? null),
                                        ];
                                    ?>
                                    <?php foreach ($contactRows as $label => $value): ?>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                            <dd class="text-sm text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>

                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <section class="rounded-2xl border border-slate-200/60 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Bantuan Sosial & Rekening</h4>
                                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                                    <?php
                                        $assistanceRows = [
                                            'Penerima KPS' => (int) ($student['penerima_kps'] ?? 0) === 1
                                                ? 'Ya (' . $valueOrDash($student['nomor_kps'] ?? null) . ')'
                                                : 'Tidak',
                                            'Penerima KIP' => (int) ($student['penerima_kip'] ?? 0) === 1
                                                ? 'Ya (' . $valueOrDash($student['nomor_kip'] ?? null) . '; Nama: ' . $valueOrDash($student['nama_di_kip'] ?? null) . ')'
                                                : 'Tidak',
                                            'Nomor KKS' => $student['nomor_kks'] ?? null,
                                            'Layak PIP' => (int) ($student['layak_pip'] ?? 0) === 1 ? 'Ya' : 'Tidak',
                                            'Alasan Layak PIP' => $student['alasan_layak_pip'] ?? null,
                                            'Bank Penyalur' => $student['bank'] ?? null,
                                            'Nomor Rekening' => $student['nomor_rekening_bank'] ?? null,
                                            'Rekening Atas Nama' => $student['rekening_atas_nama'] ?? null,
                                        ];
                                    ?>
                                    <?php foreach ($assistanceRows as $label => $value): ?>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                            <dd class="text-sm text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>

                            <section class="rounded-2xl border border-slate-200/60 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Kondisi Fisik</h4>
                                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                                    <?php
                                        $physicalRows = [
                                            'Berat Badan (kg)' => $student['berat_badan'] ?? null,
                                            'Tinggi Badan (cm)' => $student['tinggi_badan'] ?? null,
                                            'Lingkar Kepala (cm)' => $student['lingkar_kepala'] ?? null,
                                        ];
                                    ?>
                                    <?php foreach ($physicalRows as $label => $value): ?>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                            <dd class="text-sm text-slate-700"><?= htmlspecialchars($valueOrDash($value), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </section>
                        </div>

                        <section class="rounded-2xl border border-slate-200/60 p-4">
                            <h4 class="text-sm font-semibold text-slate-700">Data Orang Tua & Wali</h4>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <?php foreach (['ayah' => $ayahFields, 'ibu' => $ibuFields, 'wali' => $waliFields] as $label => $fields): ?>
                                    <div class="rounded-2xl border border-slate-200/60 p-4">
                                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars(ucfirst($label), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($label === 'wali' && !$hasParentData($fields)): ?>
                                            <p class="mt-2 text-sm text-slate-500">Belum ada data wali.</p>
                                        <?php else: ?>
                                            <dl class="mt-3 space-y-2 text-sm text-slate-600">
                                                <?php foreach ($fields as $fieldLabel => $fieldValue): ?>
                                                    <div>
                                                        <dt class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></dt>
                                                        <dd class="text-sm text-slate-700"><?= htmlspecialchars($valueOrDash($fieldValue), ENT_QUOTES, 'UTF-8') ?></dd>
                                                    </div>
                                                <?php endforeach; ?>
                                            </dl>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200/60 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-700">Riwayat Akademik</h4>
                                <p class="text-xs text-slate-500">Status naik kelas, presensi, sikap, prestasi, ekskul, dan prakerin.</p>
                            </div>
                            <div class="mt-4 space-y-6">
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div class="rounded-xl border border-slate-200/80 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Naik Kelas</p>
                                        <?php if (!empty($studentPromotions)): ?>
                                            <div class="mt-2 overflow-x-auto">
                                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                    <thead class="bg-slate-50 text-slate-500">
                                                        <tr>
                                                            <th class="px-2 py-1 text-left font-semibold">Tahun Ajaran</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Status</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Catatan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 text-slate-600">
                                                        <?php foreach ($studentPromotions as $promotion): ?>
                                                            <tr>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($promotion['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($promotion['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1 capitalize"><?= htmlspecialchars($valueOrDash($promotion['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($promotion['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="mt-2 text-xs text-slate-500">Belum ada data status naik kelas.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rounded-xl border border-slate-200/80 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Kelulusan</p>
                                        <?php if (!empty($studentGraduations)): ?>
                                            <div class="mt-2 overflow-x-auto">
                                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                    <thead class="bg-slate-50 text-slate-500">
                                                        <tr>
                                                            <th class="px-2 py-1 text-left font-semibold">Tahun Ajaran</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Status</th>
                                                            <th class="px-2 py-1 text-left font-semibold">Catatan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 text-slate-600">
                                                        <?php foreach ($studentGraduations as $graduation): ?>
                                                            <tr>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($graduation['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($graduation['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1 capitalize"><?= htmlspecialchars($valueOrDash($graduation['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($graduation['note'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="mt-2 text-xs text-slate-500">Belum ada data kelulusan.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat Presensi</p>
                                    <?php if (!empty($studentAttendance)): ?>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-semibold">Tahun Ajaran</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Sakit</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Izin</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Bolos</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Alpa</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                                    <?php foreach ($studentAttendance as $record): ?>
                                                        <tr>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($record['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($record['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars((string) ($record['sick'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars((string) ($record['permit'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars((string) ($record['truant'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars((string) ($record['absent'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada data presensi yang disimpan.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nilai Sikap</p>
                                    <?php if (!empty($studentAttitudes)): ?>
                                        <div class="mt-3 space-y-3">
                                            <?php foreach ($studentAttitudes as $attitude): ?>
                                                <div class="rounded-lg border border-slate-200/70 bg-slate-50/40 p-3 text-xs text-slate-600">
                                                    <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                        <span><?= htmlspecialchars($attitudeLabels[$attitude['type'] ?? ''] ?? 'Penilaian Sikap', ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span><?= htmlspecialchars($valueOrDash($attitude['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                    <p class="mt-1 text-[11px] text-slate-500"><?= htmlspecialchars($valueOrDash($attitude['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php if (!empty($attitude['always'])): ?>
                                                        <p class="mt-2 text-[13px] text-slate-700">
                                                            <span class="font-semibold">Menonjol:</span>
                                                            <?= htmlspecialchars(implode(', ', $attitude['always']), ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($attitude['improving'])): ?>
                                                        <p class="mt-1 text-[13px] text-slate-700">
                                                            <span class="font-semibold">Perlu Peningkatan:</span>
                                                            <?= htmlspecialchars($attitude['improving'], ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($attitude['note'])): ?>
                                                        <p class="mt-1 text-[13px] text-slate-700">
                                                            <span class="font-semibold">Catatan Guru:</span>
                                                            <?= htmlspecialchars($attitude['note'], ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada penilaian sikap.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prestasi Siswa</p>
                                    <?php if (!empty($studentAchievements)): ?>
                                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                            <?php foreach ($studentAchievements as $achievement): ?>
                                                <li class="rounded-lg border border-slate-200/70 bg-slate-50/40 p-3">
                                                    <p class="text-xs font-semibold text-slate-500">
                                                        <?= htmlspecialchars($valueOrDash($achievement['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                                        · <?= htmlspecialchars($valueOrDash($achievement['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($achievement['type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="text-sm text-slate-600"><?= htmlspecialchars($achievement['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada data prestasi siswa.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ekskul & Pengembangan Diri</p>
                                    <?php if (!empty($studentExtracurriculars)): ?>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-semibold">Tahun</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Ekskul</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Nilai Akhir</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Predikat</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                                    <?php foreach ($studentExtracurriculars as $extracurricular): ?>
                                                        <tr>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($extracurricular['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($extracurricular['activity_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash(isset($extracurricular['scores']['final']) ? number_format((float) $extracurricular['scores']['final'], 2) : null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($extracurricular['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada penilaian ekskul.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Wali Kelas</p>
                                    <?php if (!empty($studentNotes)): ?>
                                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                            <?php foreach ($studentNotes as $note): ?>
                                                <li class="rounded-lg border border-slate-200/70 bg-slate-50/40 p-3">
                                                    <p class="text-xs font-semibold text-slate-500">
                                                        <?= htmlspecialchars($valueOrDash($note['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                                        · <?= htmlspecialchars($valueOrDash($note['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <p class="mt-1 text-sm text-slate-700"><?= htmlspecialchars($note['note'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada catatan wali kelas.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat Prakerin</p>
                                    <?php if (!empty($studentPrakerin)): ?>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left font-semibold">Tahun</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Tempat</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Nilai Akhir</th>
                                                        <th class="px-2 py-1 text-left font-semibold">Predikat</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 text-slate-600">
                                                    <?php foreach ($studentPrakerin as $prakerin): ?>
                                                        <tr>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($prakerin['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($prakerin['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($prakerin['place_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash(isset($prakerin['scores']['final']) ? number_format((float) $prakerin['scores']['final'], 2) : null), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($prakerin['scores']['predicate'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada riwayat prakerin.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="rounded-xl border border-slate-200/80 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nilai Mapel per Semester</p>
                                    <?php if (!empty($subjectHistoryEntries)): ?>
                                        <div class="mt-3 space-y-4">
                                            <?php foreach ($subjectHistoryEntries as $historyEntry): ?>
                                                <div class="rounded-lg border border-slate-200/70 bg-slate-50/40 p-3">
                                                    <p class="text-xs font-semibold text-slate-500">
                                                        <?= htmlspecialchars($valueOrDash($historyEntry['school_year_name'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                                        · Semester
                                                        <?= htmlspecialchars(((int) ($historyEntry['semester'] ?? 1)) === 2 ? 'Genap' : 'Ganjil', ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                    <?php
                                                        $subjects = isset($historyEntry['subjects']) && is_array($historyEntry['subjects']) ? $historyEntry['subjects'] : [];
                                                        $kurmerSubjects = array_values(array_filter($subjects, static fn ($subject) => is_array($subject) && strtolower((string) ($subject['curriculum'] ?? '')) === 'kurmer'));
                                                        $k13Subjects = array_values(array_filter($subjects, static fn ($subject) => !is_array($subject) ? false : strtolower((string) ($subject['curriculum'] ?? '')) !== 'kurmer'));
                                                        $kurmerLevelLabels = [
                                                            'BB' => 'Belum Berkembang',
                                                            'MB' => 'Mulai Berkembang',
                                                            'BSH' => 'Berkembang Sesuai Harapan',
                                                            'SB' => 'Sangat Berkembang',
                                                        ];
                                                    ?>
                                                    <?php if (!empty($k13Subjects)): ?>
                                                        <div class="mt-2 overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                                <thead class="bg-slate-100 text-slate-600">
                                                                    <tr>
                                                                        <th class="px-2 py-1 text-left font-semibold">Mata Pelajaran</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Pengetahuan</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Keterampilan</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Rata-rata</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                                                    <?php foreach ($k13Subjects as $subject): ?>
                                                                        <tr>
                                                                            <td class="px-2 py-1">
                                                                                <?= htmlspecialchars($subject['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                                            </td>
                                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td class="px-2 py-1">
                                                                                <?= htmlspecialchars($subject['knowledge_score'] !== null ? number_format((float) $subject['knowledge_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?>
                                                                                <?php if (!empty($subject['knowledge_predicate'])): ?>
                                                                                    <span class="text-[11px] text-slate-500">(
                                                                                        <?= htmlspecialchars($subject['knowledge_predicate'], ENT_QUOTES, 'UTF-8') ?>
                                                                                    )</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="px-2 py-1">
                                                                                <?= htmlspecialchars($subject['skill_score'] !== null ? number_format((float) $subject['skill_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?>
                                                                                <?php if (!empty($subject['skill_predicate'])): ?>
                                                                                    <span class="text-[11px] text-slate-500">(
                                                                                        <?= htmlspecialchars($subject['skill_predicate'], ENT_QUOTES, 'UTF-8') ?>
                                                                                    )</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="px-2 py-1"><?= htmlspecialchars($subject['average_score'] !== null ? number_format((float) $subject['average_score'], 2) : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($kurmerSubjects)): ?>
                                                        <div class="mt-3 overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                                <thead class="bg-emerald-50 text-emerald-700">
                                                                    <tr>
                                                                        <th class="px-2 py-1 text-left font-semibold">Mata Pelajaran (KurMer)</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Kelas</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Capaian</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Nilai Opsional</th>
                                                                        <th class="px-2 py-1 text-left font-semibold">Narasi / Tindak Lanjut</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                                                    <?php foreach ($kurmerSubjects as $subject): ?>
                                                                        <?php
                                                                            $capaianCode = strtoupper(trim((string) ($subject['kurmer_capaian'] ?? '')));
                                                                            $capaianLabel = $capaianCode !== '' ? ($kurmerLevelLabels[$capaianCode] ?? $capaianCode) : '-';
                                                                            $kurmerScore = isset($subject['kurmer_score']) ? number_format((float) $subject['kurmer_score'], 2) : '-';
                                                                            $description = trim((string) ($subject['kurmer_description'] ?? ''));
                                                                            $tindakLanjut = trim((string) ($subject['kurmer_tindak_lanjut'] ?? ''));
                                                                            $tpSourcesRaw = $subject['kurmer_tp_sources'] ?? null;
                                                                            if (is_string($tpSourcesRaw)) {
                                                                                $decoded = json_decode($tpSourcesRaw, true);
                                                                                $tpSourcesRaw = is_array($decoded) ? $decoded : [];
                                                                            }
                                                                            $tpSources = is_array($tpSourcesRaw) ? $tpSourcesRaw : [];
                                                                            $tpSummary = '';
                                                                            if (!empty($tpSources)) {
                                                                                $tpParts = [];
                                                                                $used = 0;
                                                                                foreach (array_slice($tpSources, 0, 2) as $tp) {
                                                                                    $used++;
                                                                                    $code = trim((string) ($tp['kode_tp'] ?? $tp['kode'] ?? ''));
                                                                                    $tpDesc = trim((string) ($tp['deskripsi'] ?? $tp['description'] ?? $tp['tujuan'] ?? ''));
                                                                                    $label = $code !== '' ? $code : 'TP';
                                                                                    $tpParts[] = $tpDesc !== '' ? ($label . ' - ' . $tpDesc) : $label;
                                                                                }
                                                                                $remaining = count($tpSources) - $used;
                                                                                if ($remaining > 0) {
                                                                                    $tpParts[] = $remaining . ' TP lain';
                                                                                }
                                                                                $tpSummary = implode('; ', array_filter($tpParts));
                                                                            }
                                                                        ?>
                                                                        <tr>
                                                                            <td class="px-2 py-1">
                                                                                <?= htmlspecialchars($subject['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                                            </td>
                                                                            <td class="px-2 py-1"><?= htmlspecialchars($valueOrDash($subject['class_name'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td class="px-2 py-1 font-semibold text-emerald-700">
                                                                                <?= htmlspecialchars($capaianCode !== '' ? $capaianCode : '-', ENT_QUOTES, 'UTF-8') ?>
                                                                                <?php if ($capaianLabel !== '' && $capaianLabel !== $capaianCode): ?>
                                                                                    <span class="text-[11px] text-emerald-600">(
                                                                                        <?= htmlspecialchars($capaianLabel, ENT_QUOTES, 'UTF-8') ?>
                                                                                    )</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="px-2 py-1"><?= htmlspecialchars($kurmerScore, ENT_QUOTES, 'UTF-8') ?></td>
                                                                            <td class="px-2 py-1">
                                                                                <?php if ($description !== ''): ?>
                                                                                    <div><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></div>
                                                                                <?php endif; ?>
                                                                                <?php if ($tindakLanjut !== ''): ?>
                                                                                    <div class="mt-1 text-[11px] text-amber-600">Tindak lanjut: <?= nl2br(htmlspecialchars($tindakLanjut, ENT_QUOTES, 'UTF-8')) ?></div>
                                                                                <?php endif; ?>
                                                                                <?php if ($tpSummary !== ''): ?>
                                                                                    <div class="mt-1 text-[10px] text-slate-500">TP: <?= htmlspecialchars($tpSummary, ENT_QUOTES, 'UTF-8') ?></div>
                                                                                <?php endif; ?>
                                                                                <?php if ($description === '' && $tindakLanjut === '' && $tpSummary === ''): ?>
                                                                                    <span class="text-[11px] text-slate-400">Belum ada narasi.</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (empty($k13Subjects) && empty($kurmerSubjects)): ?>
                                                        <p class="mt-2 text-xs text-slate-500">Belum ada nilai mata pelajaran untuk semester ini.</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">Belum ada data nilai mata pelajaran.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200/60 p-4">
                            <h4 class="text-sm font-semibold text-slate-700">Dokumen Digital</h4>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <?php foreach ($documentStatuses as $document): ?>
                                    <div class="rounded-xl border border-slate-200/60 p-3 text-sm text-slate-600">
                                        <p class="font-semibold text-slate-700"><?= htmlspecialchars($document['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($document['path'] !== ''): ?>
                                            <p class="mt-1 text-xs text-emerald-600">Tersedia</p>
                                            <a
                                                href="<?= htmlspecialchars(asset($document['path']), ENT_QUOTES, 'UTF-8') ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                                            >
                                                <i class="ri-external-link-line text-sm"></i>
                                                Lihat Dokumen
                                            </a>
                                        <?php else: ?>
                                            <p class="mt-1 text-xs text-slate-500">Belum diunggah</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200/60 p-4">
                            <h4 class="text-sm font-semibold text-slate-700">Riwayat Data</h4>
                            <dl class="mt-4 grid gap-4 md:grid-cols-2 text-sm text-slate-600">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dibuat Pada</dt>
                                    <dd class="text-sm text-slate-700"><?= htmlspecialchars($formatDate($student['created_at'] ?? null, true), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diperbarui Pada</dt>
                                    <dd class="text-sm text-slate-700"><?= htmlspecialchars($formatDate($student['updated_at'] ?? null, true), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            </dl>
                        </section>
                    </div>
            </article>
        <?php endif; ?>
    <?php endif; ?>
</div>
