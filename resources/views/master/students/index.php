<?php
    $canManageStudents = (bool) ($canManageStudents ?? false);
    $canEditStudents = (bool) ($canEditStudents ?? false);
    $canUploadPhotos = (bool) ($canUploadPhotos ?? false);
    $canUploadDocuments = (bool) ($canUploadDocuments ?? false);
    $documentFieldDefinitions = $documentFieldDefinitions ?? [];
    $students = $students ?? [];
    $studentTableRows = $studentTableRows ?? $students;
    $studentTablePage = (int) ($studentTablePage ?? 1);
    $studentTablePages = (int) ($studentTablePages ?? 1);
    $studentTablePerPage = (int) ($studentTablePerPage ?? 20);
    $studentTableTotal = (int) ($studentTableTotal ?? count($students));
    $documentTableRows = $documentTableRows ?? [];
    $documentTablePage = (int) ($documentTablePage ?? 1);
    $documentTablePages = (int) ($documentTablePages ?? 1);
    $documentTablePerPage = (int) ($documentTablePerPage ?? 10);
    $documentTableTotal = (int) ($documentTableTotal ?? 0);
    $homeroomClasses = $homeroomClasses ?? [];
    $homeroomSummaries = array_values(array_filter(array_map(static function (array $class): ?string {
        $level = $class['tingkat'] ?? null;
        $name = trim((string) ($class['nama'] ?? ''));
        $yearName = trim((string) ($class['tahun_ajaran_nama'] ?? ''));

        $labelParts = [];
        if ($level !== null && $level !== '' && $level !== 0) {
            $labelParts[] = trim((string) $level);
        }
        if ($name !== '') {
            $labelParts[] = $name;
        }

        $label = trim(implode(' ', $labelParts));
        if ($label === '') {
            return null;
        }

        if ($yearName !== '') {
            $label .= sprintf(' (%s)', $yearName);
        }

        return $label;
    }, $homeroomClasses)));
    $isEditing = isset($editingStudent) && $editingStudent !== null;
    $editingStudent = $editingStudent ?? null;
    $selectedYearLabel = $selectedYearLabel ?? null;
    $currentPlacementLabel = null;
    if ($isEditing) {
        $className = trim((string) ($editingStudent['kelas_nama'] ?? ''));
        $yearName = trim((string) ($editingStudent['tahun_ajaran_nama'] ?? ''));
        if ($className !== '' && $yearName !== '') {
            $currentPlacementLabel = sprintf('%s (%s)', $className, $yearName);
        } elseif ($className !== '') {
            $currentPlacementLabel = $className;
        } elseif ($yearName !== '') {
            $currentPlacementLabel = $yearName;
        }
    }
    $statusOptions = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];
    $statusDapodikOptions = [
        'aktif' => 'Aktif',
        'belum_masuk' => 'Belum Masuk Dapodik',
        'mutasi' => 'Mutasi',
        'pindah' => 'Pindah',
        'residu' => 'Residu',
    ];
    $jenisKelaminOptions = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];
    $exportFormatOptions = [
        'pdf' => 'PDF',
        'excel' => 'Excel',
    ];
    $exportStatusOptions = array_merge(['all' => 'Semua Status'], $statusOptions);
    $checkboxChecked = static function (string $key) use ($editingStudent): bool {
        $default = ($editingStudent !== null && array_key_exists($key, $editingStudent)) ? $editingStudent[$key] : 0;
        $value = old($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower((string) $value);

        return in_array($normalized, ['1', 'true', 'on', 'ya', 'y', 'yes'], true);
    };
    $formatDate = static function (?string $date): string {
        if ($date === null || $date === '') {
            return '-';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d M Y', $timestamp);
    };
    $shortDocumentLabel = static function (string $label): string {
        $normalized = strtolower($label);

        if (str_contains($normalized, 'ijazah')) {
            return 'Ijazah';
        }
        if (str_contains($normalized, 'rapor') || str_contains($normalized, 'raport')) {
            return 'Rapor';
        }
        if (str_contains($normalized, 'kartu keluarga')) {
            return 'KK';
        }
        if (str_contains($normalized, 'akte') || str_contains($normalized, 'akta')) {
            return 'Akta';
        }
        if (str_contains($normalized, 'ayah')) {
            return 'KTP Ayah';
        }
        if (str_contains($normalized, 'ibu')) {
            return 'KTP Ibu';
        }

        return $label;
    };
    $buildStudentDocumentStatuses = static function (array $student) use ($documentFieldDefinitions): array {
        $statuses = [];

        foreach ($documentFieldDefinitions as $key => $definition) {
            $column = (string) ($definition['column'] ?? '');
            $path = $column !== '' ? trim((string) ($student[$column] ?? '')) : '';

            $statuses[(string) $key] = [
                'key' => (string) $key,
                'label' => (string) ($definition['label'] ?? $key),
                'input' => (string) ($definition['input'] ?? ''),
                'path' => $path,
                'is_complete' => $path !== '',
            ];
        }

        return $statuses;
    };
    $summarizeStudentDocuments = static function (array $statuses): array {
        $total = count($statuses);
        $complete = 0;
        $missingLabels = [];

        foreach ($statuses as $status) {
            if (!empty($status['is_complete'])) {
                $complete++;
                continue;
            }

            $missingLabels[] = (string) ($status['label'] ?? '');
        }

        return [
            'total' => $total,
            'complete' => $complete,
            'missing' => max(0, $total - $complete),
            'missing_labels' => array_values(array_filter($missingLabels, static fn ($label) => $label !== '')),
            'percent' => $total > 0 ? (int) round(($complete / $total) * 100) : 0,
        ];
    };
    $makePhotoOptionLabel = static function (array $student) use ($formatDate): string {
        $name = trim((string) ($student['nama'] ?? ''));
        $nipd = trim((string) ($student['nipd'] ?? ''));
        $nisn = trim((string) ($student['nisn'] ?? ''));
        $className = trim((string) ($student['kelas_nama'] ?? ''));
        $label = $name !== '' ? $name : 'Tanpa Nama';

        $identifiers = [];
        if ($nipd !== '') {
            $identifiers[] = $nipd;
        }
        if ($nisn !== '') {
            $identifiers[] = $nisn;
        }

        if (!empty($identifiers)) {
            $label .= ' / ' . implode(' / ', $identifiers);
        }

        if ($className !== '') {
            $label .= sprintf(' (%s)', $className);
        }

        return $label;
    };
    $buildStudentSearchKeywords = static function (array $student) use ($statusOptions, $statusDapodikOptions, $jenisKelaminOptions): string {
        $values = [
            $student['nama'] ?? '',
            $student['nipd'] ?? '',
            $student['nisn'] ?? '',
            $student['nik'] ?? '',
            $student['tempat_lahir'] ?? '',
            $student['alamat'] ?? '',
            $student['telepon'] ?? '',
            $student['hp'] ?? '',
            $student['email'] ?? '',
            $student['kelas_nama'] ?? '',
            $student['tahun_ajaran_nama'] ?? '',
        ];

        $genderKey = (string) ($student['jenis_kelamin'] ?? '');
        if ($genderKey !== '' && isset($jenisKelaminOptions[$genderKey])) {
            $values[] = $jenisKelaminOptions[$genderKey];
        }

        $statusKey = (string) ($student['status'] ?? '');
        if ($statusKey !== '' && isset($statusOptions[$statusKey])) {
            $values[] = $statusOptions[$statusKey];
        }

        $dapodikKey = (string) ($student['status_dapodik'] ?? '');
        if ($dapodikKey !== '' && isset($statusDapodikOptions[$dapodikKey])) {
            $values[] = $statusDapodikOptions[$dapodikKey];
        }

        if ((int) ($student['penerima_kps'] ?? 0) === 1) {
            $values[] = 'kps';
        }
        if ((int) ($student['penerima_kip'] ?? 0) === 1) {
            $values[] = 'kip';
        }
        if ((int) ($student['layak_pip'] ?? 0) === 1) {
            $values[] = 'layak pip';
        }

        $filtered = array_filter(array_map(static fn ($value) => trim((string) $value), $values), static fn ($value) => $value !== '');
        if (empty($filtered)) {
            return '';
        }

        $combined = implode(' ', $filtered);

        return function_exists('mb_strtolower') ? mb_strtolower($combined, 'UTF-8') : strtolower($combined);
    };
    $jenisKelaminValue = (string) old('jenis_kelamin', $editingStudent['jenis_kelamin'] ?? 'L');
    $statusValue = (string) old('status', $editingStudent['status'] ?? 'aktif');
    $statusDapodikValue = (string) old('status_dapodik', $editingStudent['status_dapodik'] ?? 'aktif');
    $regularNipdPreview = trim((string) ($regularNipdPreview ?? ''));
    $showStudentForm = (bool) ($showStudentForm ?? false);
    $showStudentEditor = ($canManageStudents || $canEditStudents) && $showStudentForm;
    $hasStudentActions = $canManageStudents || $canEditStudents;
    $showStudentListing = (bool) ($showStudentListing ?? true);
    $showStudentStatusSection = (bool) ($showStudentStatusSection ?? true);
    $showStudentPhotoUpload = (bool) ($showStudentPhotoUpload ?? false);
    $studentPhotoUploadAction = isset($studentPhotoUploadAction) && is_string($studentPhotoUploadAction)
        ? $studentPhotoUploadAction
        : base_url('master/siswa/foto');
    $studentPhotoPath = trim((string) ($editingStudent['foto_path'] ?? ''));
    $studentPhotoUrl = $studentPhotoPath !== '' ? asset($studentPhotoPath) : null;
    $studentFormAction = isset($studentFormAction) && is_string($studentFormAction)
        ? $studentFormAction
        : ($isEditing ? base_url('master/siswa/' . $editingStudent['id'] . '/update') : base_url('master/siswa'));
    $studentFormCancelUrl = isset($studentFormCancelUrl) && is_string($studentFormCancelUrl)
        ? $studentFormCancelUrl
        : base_url('master/siswa');
    $studentFormNotice = isset($studentFormNotice) && is_string($studentFormNotice) ? $studentFormNotice : null;
?>

<div class="grid gap-6 lg:grid-cols-12">
    <?php if ($showStudentEditor): ?>
    <div class="<?= $showStudentListing ? 'lg:col-span-5' : 'lg:col-span-12' ?>">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-800">
                    <?= $isEditing ? 'Ubah Data Siswa' : 'Tambah Siswa' ?>
                </h2>
                <a
                    href="<?= htmlspecialchars($studentFormCancelUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-700"
                >
                    <i class="ri-close-line text-sm"></i>
                    <span><?= $isEditing ? 'Batal' : 'Tutup' ?></span>
                </a>
            </div>
            <?php if ($showStudentPhotoUpload && $isEditing): ?>
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-28 w-24 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-400">
                            <?php if ($studentPhotoUrl !== null): ?>
                                <img src="<?= htmlspecialchars($studentPhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto <?= htmlspecialchars((string) ($editingStudent['nama'] ?? 'Siswa'), ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover" />
                            <?php else: ?>
                                <i class="ri-user-3-line text-4xl"></i>
                            <?php endif; ?>
                        </div>
                        <form action="<?= htmlspecialchars($studentPhotoUploadAction, ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="flex-1 space-y-3">
                            <?= csrf_field() ?>
                            <div>
                                <label for="foto_mandiri" class="block text-sm font-semibold text-slate-700">Foto Siswa</label>
                                <input
                                    type="file"
                                    id="foto_mandiri"
                                    name="foto"
                                    accept=".jpg,.png"
                                    required
                                    class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                                />
                                <p class="mt-1 text-xs text-slate-500">Format PNG/JPG, maksimal 1 MB. Upload ulang untuk mengganti foto lama.</p>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                <i class="ri-upload-cloud-line text-base"></i>
                                Upload Foto
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <form
                action="<?= htmlspecialchars($studentFormAction, ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-6"
            >
                <?= csrf_field() ?>
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    <p><?= htmlspecialchars($studentFormNotice ?? 'Penempatan kelas diatur melalui menu Penempatan Siswa.', ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($isEditing): ?>
                        <p class="mt-1">Kelas saat ini: <span class="font-semibold text-slate-700"><?= htmlspecialchars($currentPlacementLabel ?? 'Belum ditetapkan', ENT_QUOTES, 'UTF-8') ?></span></p>
                    <?php else: ?>
                        <p class="mt-1">Siswa baru akan ditandai sebagai belum ditempatkan sampai dipindahkan ke kelas aktif.</p>
                    <?php endif; ?>
                </div>

                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Pokok</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="nama" class="block text-sm font-medium text-slate-600">Nama Lengkap<span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="nama"
                                name="nama"
                                value="<?= htmlspecialchars((string) old('nama', $editingStudent['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <?php if ($isEditing): ?>
                                <label for="nipd" class="block text-sm font-medium text-slate-600">NIPD</label>
                                <input
                                    type="text"
                                    id="nipd"
                                    name="nipd"
                                    value="<?= htmlspecialchars((string) ($editingStudent['nipd'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    readonly
                                    class="mt-2 block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                                <p class="mt-1 text-xs text-slate-500">NIPD dibuat otomatis oleh sistem dan tidak diedit dari form ini.</p>
                            <?php else: ?>
                                <label class="block text-sm font-medium text-slate-600">NIPD</label>
                                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                                    <?= htmlspecialchars($regularNipdPreview !== '' ? $regularNipdPreview : 'Otomatis saat disimpan', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Format: tahun ajaran + kode 1 siswa reguler + nomor urut.</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="jenis_kelamin" class="block text-sm font-medium text-slate-600">Jenis Kelamin<span class="text-rose-500">*</span></label>
                            <select
                                id="jenis_kelamin"
                                name="jenis_kelamin"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($jenisKelaminOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $jenisKelaminValue === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nisn" class="block text-sm font-medium text-slate-600">NISN<span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="nisn"
                                name="nisn"
                                inputmode="numeric"
                                maxlength="10"
                                value="<?= htmlspecialchars((string) old('nisn', $editingStudent['nisn'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="0034..."
                            />
                        </div>
                        <div>
                            <label for="tempat_lahir" class="block text-sm font-medium text-slate-600">Tempat Lahir<span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="tempat_lahir"
                                name="tempat_lahir"
                                value="<?= htmlspecialchars((string) old('tempat_lahir', $editingStudent['tempat_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="tanggal_lahir" class="block text-sm font-medium text-slate-600">Tanggal Lahir<span class="text-rose-500">*</span></label>
                            <input
                                type="date"
                                id="tanggal_lahir"
                                name="tanggal_lahir"
                                value="<?= htmlspecialchars((string) old('tanggal_lahir', $editingStudent['tanggal_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="nik" class="block text-sm font-medium text-slate-600">NIK<span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="nik"
                                name="nik"
                                inputmode="numeric"
                                maxlength="16"
                                value="<?= htmlspecialchars((string) old('nik', $editingStudent['nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="16 digit angka"
                            />
                        </div>
                        <div>
                            <label for="agama" class="block text-sm font-medium text-slate-600">Agama</label>
                            <input
                                type="text"
                                id="agama"
                                name="agama"
                                value="<?= htmlspecialchars((string) old('agama', $editingStudent['agama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Islam / Kristen / ... "
                            />
                        </div>
                        <div>
                            <label for="rombel_saat_ini" class="block text-sm font-medium text-slate-600">Rombel Saat Ini</label>
                            <input
                                type="text"
                                id="rombel_saat_ini"
                                name="rombel_saat_ini"
                                value="<?= htmlspecialchars((string) old('rombel_saat_ini', $editingStudent['rombel_saat_ini'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Contoh: XII RPL 1"
                            />
                        </div>
                        <div>
                            <label for="nomor_peserta_ujian" class="block text-sm font-medium text-slate-600">Nomor Peserta Ujian Nasional</label>
                            <input
                                type="text"
                                id="nomor_peserta_ujian"
                                name="nomor_peserta_ujian"
                                value="<?= htmlspecialchars((string) old('nomor_peserta_ujian', $editingStudent['nomor_peserta_ujian'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="nomor_seri_ijazah" class="block text-sm font-medium text-slate-600">Nomor Seri Ijazah</label>
                            <input
                                type="text"
                                id="nomor_seri_ijazah"
                                name="nomor_seri_ijazah"
                                value="<?= htmlspecialchars((string) old('nomor_seri_ijazah', $editingStudent['nomor_seri_ijazah'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="skhun" class="block text-sm font-medium text-slate-600">SKHUN</label>
                            <input
                                type="text"
                                id="skhun"
                                name="skhun"
                                value="<?= htmlspecialchars((string) old('skhun', $editingStudent['skhun'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label for="sekolah_asal" class="block text-sm font-medium text-slate-600">Sekolah Asal</label>
                            <input
                                type="text"
                                id="sekolah_asal"
                                name="sekolah_asal"
                                value="<?= htmlspecialchars((string) old('sekolah_asal', $editingStudent['sekolah_asal'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Nama sekolah asal"
                            />
                        </div>
                        <div>
                            <label for="anak_ke" class="block text-sm font-medium text-slate-600">Anak ke-berapa</label>
                            <input
                                type="number"
                                min="1"
                                id="anak_ke"
                                name="anak_ke"
                                value="<?= htmlspecialchars((string) old('anak_ke', $editingStudent['anak_ke'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="kebutuhan_khusus" class="block text-sm font-medium text-slate-600">Kebutuhan Khusus</label>
                            <input
                                type="text"
                                id="kebutuhan_khusus"
                                name="kebutuhan_khusus"
                                value="<?= htmlspecialchars((string) old('kebutuhan_khusus', $editingStudent['kebutuhan_khusus'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Kosongkan jika tidak ada"
                            />
                        </div>
                        <div>
                            <label for="nomor_registrasi_akta_lahir" class="block text-sm font-medium text-slate-600">Nomor Registrasi Akta Lahir</label>
                            <input
                                type="text"
                                id="nomor_registrasi_akta_lahir"
                                name="nomor_registrasi_akta_lahir"
                                value="<?= htmlspecialchars((string) old('nomor_registrasi_akta_lahir', $editingStudent['nomor_registrasi_akta_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="nomor_kk" class="block text-sm font-medium text-slate-600">Nomor KK</label>
                            <input
                                type="text"
                                id="nomor_kk"
                                name="nomor_kk"
                                value="<?= htmlspecialchars((string) old('nomor_kk', $editingStudent['nomor_kk'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="16 digit angka"
                            />
</div>
</div>
</div>
                </section>

                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat & Kontak</h3>
                    <div class="mt-4 grid gap-4">
                        <div>
                            <label for="alamat" class="block text-sm font-medium text-slate-600">Alamat</label>
                            <textarea
                                id="alamat"
                                name="alamat"
                                rows="3"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            ><?= htmlspecialchars((string) old('alamat', $editingStudent['alamat'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label for="rt" class="block text-sm font-medium text-slate-600">RT</label>
                                <input
                                    type="text"
                                    id="rt"
                                    name="rt"
                                    value="<?= htmlspecialchars((string) old('rt', $editingStudent['rt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="rw" class="block text-sm font-medium text-slate-600">RW</label>
                                <input
                                    type="text"
                                    id="rw"
                                    name="rw"
                                    value="<?= htmlspecialchars((string) old('rw', $editingStudent['rw'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="dusun" class="block text-sm font-medium text-slate-600">Dusun</label>
                                <input
                                    type="text"
                                    id="dusun"
                                    name="dusun"
                                    value="<?= htmlspecialchars((string) old('dusun', $editingStudent['dusun'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="kelurahan" class="block text-sm font-medium text-slate-600">Kelurahan</label>
                                <input
                                    type="text"
                                    id="kelurahan"
                                    name="kelurahan"
                                    value="<?= htmlspecialchars((string) old('kelurahan', $editingStudent['kelurahan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-4">
                            <div>
                                <label for="kecamatan" class="block text-sm font-medium text-slate-600">Kecamatan</label>
                                <input
                                    type="text"
                                    id="kecamatan"
                                    name="kecamatan"
                                    value="<?= htmlspecialchars((string) old('kecamatan', $editingStudent['kecamatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="kode_pos" class="block text-sm font-medium text-slate-600">Kode Pos</label>
                                <input
                                    type="text"
                                    id="kode_pos"
                                    name="kode_pos"
                                    value="<?= htmlspecialchars((string) old('kode_pos', $editingStudent['kode_pos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="jenis_tinggal" class="block text-sm font-medium text-slate-600">Jenis Tinggal</label>
                                <input
                                    type="text"
                                    id="jenis_tinggal"
                                    name="jenis_tinggal"
                                    value="<?= htmlspecialchars((string) old('jenis_tinggal', $editingStudent['jenis_tinggal'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="Bersama orang tua / ..."
                                />
                            </div>
                            <div>
                                <label for="alat_transportasi" class="block text-sm font-medium text-slate-600">Alat Transportasi</label>
                                <input
                                    type="text"
                                    id="alat_transportasi"
                                    name="alat_transportasi"
                                    value="<?= htmlspecialchars((string) old('alat_transportasi', $editingStudent['alat_transportasi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="Jalan kaki / Motor / ..."
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="telepon" class="block text-sm font-medium text-slate-600">Telepon</label>
                                <input
                                    type="text"
                                    id="telepon"
                                    name="telepon"
                                    value="<?= htmlspecialchars((string) old('telepon', $editingStudent['telepon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="hp" class="block text-sm font-medium text-slate-600">HP</label>
                                <input
                                    type="text"
                                    id="hp"
                                    name="hp"
                                    value="<?= htmlspecialchars((string) old('hp', $editingStudent['hp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-600">E-mail</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars((string) old('email', $editingStudent['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="nama@example.com"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="lintang" class="block text-sm font-medium text-slate-600">Lintang</label>
                                <input
                                    type="text"
                                    id="lintang"
                                    name="lintang"
                                    value="<?= htmlspecialchars((string) old('lintang', $editingStudent['lintang'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="-6.87"
                                />
                            </div>
                            <div>
                                <label for="bujur" class="block text-sm font-medium text-slate-600">Bujur</label>
                                <input
                                    type="text"
                                    id="bujur"
                                    name="bujur"
                                    value="<?= htmlspecialchars((string) old('bujur', $editingStudent['bujur'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="107.53"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Orang Tua &amp; Wali</h3>
                    <div class="mt-4 grid gap-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg border border-slate-100 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Ayah<span class="text-rose-500">*</span></h4>
                                <div class="mt-3 space-y-3">
                                    <div>
                                        <label for="ayah_nama" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nama</label>
                                        <input
                                            type="text"
                                            id="ayah_nama"
                                            name="ayah_nama"
                                            value="<?= htmlspecialchars((string) old('ayah_nama', $editingStudent['ayah_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ayah_tahun_lahir" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Tahun Lahir</label>
                                        <input
                                            type="text"
                                            id="ayah_tahun_lahir"
                                            name="ayah_tahun_lahir"
                                            maxlength="4"
                                            value="<?= htmlspecialchars((string) old('ayah_tahun_lahir', $editingStudent['ayah_tahun_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ayah_jenjang_pendidikan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Jenjang Pendidikan</label>
                                        <input
                                            type="text"
                                            id="ayah_jenjang_pendidikan"
                                            name="ayah_jenjang_pendidikan"
                                            value="<?= htmlspecialchars((string) old('ayah_jenjang_pendidikan', $editingStudent['ayah_jenjang_pendidikan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ayah_pekerjaan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Pekerjaan</label>
                                        <input
                                            type="text"
                                            id="ayah_pekerjaan"
                                            name="ayah_pekerjaan"
                                            value="<?= htmlspecialchars((string) old('ayah_pekerjaan', $editingStudent['ayah_pekerjaan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ayah_penghasilan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Penghasilan</label>
                                        <input
                                            type="text"
                                            id="ayah_penghasilan"
                                            name="ayah_penghasilan"
                                            value="<?= htmlspecialchars((string) old('ayah_penghasilan', $editingStudent['ayah_penghasilan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                            placeholder="Contoh: 4-5 juta"
                                        />
                                    </div>
                                    <div>
                                        <label for="ayah_nik" class="block text-xs font-medium uppercase tracking-wide text-slate-500">NIK</label>
                                        <input
                                            type="text"
                                            id="ayah_nik"
                                            name="ayah_nik"
                                            value="<?= htmlspecialchars((string) old('ayah_nik', $editingStudent['ayah_nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 p-4">
                                <h4 class="text-sm font-semibold text-slate-700">Ibu<span class="text-rose-500">*</span></h4>
                                <div class="mt-3 space-y-3">
                                    <div>
                                        <label for="ibu_nama" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nama</label>
                                        <input
                                            type="text"
                                            id="ibu_nama"
                                            name="ibu_nama"
                                            value="<?= htmlspecialchars((string) old('ibu_nama', $editingStudent['ibu_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            required
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ibu_tahun_lahir" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Tahun Lahir</label>
                                        <input
                                            type="text"
                                            id="ibu_tahun_lahir"
                                            name="ibu_tahun_lahir"
                                            maxlength="4"
                                            value="<?= htmlspecialchars((string) old('ibu_tahun_lahir', $editingStudent['ibu_tahun_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ibu_jenjang_pendidikan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Jenjang Pendidikan</label>
                                        <input
                                            type="text"
                                            id="ibu_jenjang_pendidikan"
                                            name="ibu_jenjang_pendidikan"
                                            value="<?= htmlspecialchars((string) old('ibu_jenjang_pendidikan', $editingStudent['ibu_jenjang_pendidikan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ibu_pekerjaan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Pekerjaan</label>
                                        <input
                                            type="text"
                                            id="ibu_pekerjaan"
                                            name="ibu_pekerjaan"
                                            value="<?= htmlspecialchars((string) old('ibu_pekerjaan', $editingStudent['ibu_pekerjaan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ibu_penghasilan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Penghasilan</label>
                                        <input
                                            type="text"
                                            id="ibu_penghasilan"
                                            name="ibu_penghasilan"
                                            value="<?= htmlspecialchars((string) old('ibu_penghasilan', $editingStudent['ibu_penghasilan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                    <div>
                                        <label for="ibu_nik" class="block text-xs font-medium uppercase tracking-wide text-slate-500">NIK</label>
                                        <input
                                            type="text"
                                            id="ibu_nik"
                                            name="ibu_nik"
                                            value="<?= htmlspecialchars((string) old('ibu_nik', $editingStudent['ibu_nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-100 p-4">
                            <h4 class="text-sm font-semibold text-slate-700">Wali</h4>
                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                <div class="md:col-span-1">
                                    <label for="wali_nama" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nama</label>
                                    <input
                                        type="text"
                                        id="wali_nama"
                                        name="wali_nama"
                                        value="<?= htmlspecialchars((string) old('wali_nama', $editingStudent['wali_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                                <div>
                                    <label for="wali_tahun_lahir" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Tahun Lahir</label>
                                    <input
                                        type="text"
                                        id="wali_tahun_lahir"
                                        name="wali_tahun_lahir"
                                        maxlength="4"
                                        value="<?= htmlspecialchars((string) old('wali_tahun_lahir', $editingStudent['wali_tahun_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                                <div>
                                    <label for="wali_jenjang_pendidikan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Jenjang Pendidikan</label>
                                    <input
                                        type="text"
                                        id="wali_jenjang_pendidikan"
                                        name="wali_jenjang_pendidikan"
                                        value="<?= htmlspecialchars((string) old('wali_jenjang_pendidikan', $editingStudent['wali_jenjang_pendidikan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                                <div>
                                    <label for="wali_pekerjaan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Pekerjaan</label>
                                    <input
                                        type="text"
                                        id="wali_pekerjaan"
                                        name="wali_pekerjaan"
                                        value="<?= htmlspecialchars((string) old('wali_pekerjaan', $editingStudent['wali_pekerjaan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                                <div>
                                    <label for="wali_penghasilan" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Penghasilan</label>
                                    <input
                                        type="text"
                                        id="wali_penghasilan"
                                        name="wali_penghasilan"
                                        value="<?= htmlspecialchars((string) old('wali_penghasilan', $editingStudent['wali_penghasilan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                                <div>
                                    <label for="wali_nik" class="block text-xs font-medium uppercase tracking-wide text-slate-500">NIK</label>
                                    <input
                                        type="text"
                                        id="wali_nik"
                                        name="wali_nik"
                                        value="<?= htmlspecialchars((string) old('wali_nik', $editingStudent['wali_nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bantuan &amp; Rekening</h3>
                    <div class="mt-4 space-y-4">
                        <div class="grid gap-4 md:grid-cols-3 md:items-center">
                            <?php $penerimaKpsChecked = $checkboxChecked('penerima_kps'); ?>
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="penerima_kps"
                                    name="penerima_kps"
                                    value="1"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    <?= $penerimaKpsChecked ? 'checked' : '' ?>
                                />
                                <label for="penerima_kps" class="text-sm font-medium text-slate-600">Penerima KPS</label>
                            </div>
                            <div class="md:col-span-2">
                                <label for="nomor_kps" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nomor KPS</label>
                                <input
                                    type="text"
                                    id="nomor_kps"
                                    name="nomor_kps"
                                    value="<?= htmlspecialchars((string) old('nomor_kps', $editingStudent['nomor_kps'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3 md:items-center">
                            <?php $penerimaKipChecked = $checkboxChecked('penerima_kip'); ?>
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="penerima_kip"
                                    name="penerima_kip"
                                    value="1"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    <?= $penerimaKipChecked ? 'checked' : '' ?>
                                />
                                <label for="penerima_kip" class="text-sm font-medium text-slate-600">Penerima KIP</label>
                            </div>
                            <div>
                                <label for="nomor_kip" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nomor KIP</label>
                                <input
                                    type="text"
                                    id="nomor_kip"
                                    name="nomor_kip"
                                    value="<?= htmlspecialchars((string) old('nomor_kip', $editingStudent['nomor_kip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="nama_di_kip" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nama di KIP</label>
                                <input
                                    type="text"
                                    id="nama_di_kip"
                                    name="nama_di_kip"
                                    value="<?= htmlspecialchars((string) old('nama_di_kip', $editingStudent['nama_di_kip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="nomor_kks" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nomor KKS</label>
                                <input
                                    type="text"
                                    id="nomor_kks"
                                    name="nomor_kks"
                                    value="<?= htmlspecialchars((string) old('nomor_kks', $editingStudent['nomor_kks'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <?php $layakPipChecked = $checkboxChecked('layak_pip'); ?>
                            <div class="flex items-center gap-2 md:justify-end">
                                <input
                                    type="checkbox"
                                    id="layak_pip"
                                    name="layak_pip"
                                    value="1"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    <?= $layakPipChecked ? 'checked' : '' ?>
                                />
                                <label for="layak_pip" class="text-sm font-medium text-slate-600">Layak PIP (usulan sekolah)</label>
                            </div>
                        </div>
                        <div>
                            <label for="alasan_layak_pip" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Alasan Layak PIP</label>
                            <textarea
                                id="alasan_layak_pip"
                                name="alasan_layak_pip"
                                rows="2"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            ><?= htmlspecialchars((string) old('alasan_layak_pip', $editingStudent['alasan_layak_pip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="bank" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Bank</label>
                                <input
                                    type="text"
                                    id="bank"
                                    name="bank"
                                    value="<?= htmlspecialchars((string) old('bank', $editingStudent['bank'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="nomor_rekening_bank" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Nomor Rekening Bank</label>
                                <input
                                    type="text"
                                    id="nomor_rekening_bank"
                                    name="nomor_rekening_bank"
                                    value="<?= htmlspecialchars((string) old('nomor_rekening_bank', $editingStudent['nomor_rekening_bank'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="rekening_atas_nama" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Rekening Atas Nama</label>
                                <input
                                    type="text"
                                    id="rekening_atas_nama"
                                    name="rekening_atas_nama"
                                    value="<?= htmlspecialchars((string) old('rekening_atas_nama', $editingStudent['rekening_atas_nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Lainnya</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="berat_badan" class="block text-sm font-medium text-slate-600">Berat Badan (kg)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="berat_badan"
                                name="berat_badan"
                                value="<?= htmlspecialchars((string) old('berat_badan', $editingStudent['berat_badan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="tinggi_badan" class="block text-sm font-medium text-slate-600">Tinggi Badan (cm)</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                id="tinggi_badan"
                                name="tinggi_badan"
                                value="<?= htmlspecialchars((string) old('tinggi_badan', $editingStudent['tinggi_badan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="lingkar_kepala" class="block text-sm font-medium text-slate-600">Lingkar Kepala (cm)</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                id="lingkar_kepala"
                                name="lingkar_kepala"
                                value="<?= htmlspecialchars((string) old('lingkar_kepala', $editingStudent['lingkar_kepala'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="jumlah_saudara_kandung" class="block text-sm font-medium text-slate-600">Jumlah Saudara Kandung</label>
                            <input
                                type="number"
                                min="0"
                                id="jumlah_saudara_kandung"
                                name="jumlah_saudara_kandung"
                                value="<?= htmlspecialchars((string) old('jumlah_saudara_kandung', $editingStudent['jumlah_saudara_kandung'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="jarak_rumah_ke_sekolah_km" class="block text-sm font-medium text-slate-600">Jarak Rumah ke Sekolah (km)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="jarak_rumah_ke_sekolah_km"
                                name="jarak_rumah_ke_sekolah_km"
                                value="<?= htmlspecialchars((string) old('jarak_rumah_ke_sekolah_km', $editingStudent['jarak_rumah_ke_sekolah_km'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                    </div>
                </section>

                <?php if ($showStudentStatusSection): ?>
                <section class="rounded-xl border border-slate-200 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-600">Status Siswa</label>
                            <select
                                id="status"
                                name="status"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $statusValue === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status_dapodik" class="block text-sm font-medium text-slate-600">Status Dapodik</label>
                            <select
                                id="status_dapodik"
                                name="status_dapodik"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($statusDapodikOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $statusDapodikValue === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60">
                        <?php if ($isEditing): ?>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M4 13.5V16h2.5l7.36-7.36-2.5-2.5L4 13.5zm11.85-6.35a.5.5 0 0 0 0-.7l-2.3-2.3a.5.5 0 0 0-.7 0l-1.4 1.4 3 3 1.4-1.4z"/>
                            </svg>
                            <span>Simpan Perubahan</span>
                        <?php else: ?>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M4 3a2 2 0 0 0-2 2v1h16V5a2 2 0 0 0-2-2H4zm14 5H2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-5 1v6H7V9h6z"/>
                            </svg>
                            <span>Simpan</span>
                        <?php endif; ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l8 8M6 14l8-8"/>
                            </svg>
                            Batal
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showStudentListing): ?>
    <div class="<?= $showStudentEditor ? 'lg:col-span-7' : 'lg:col-span-12' ?>">
        <?php if (!$canManageStudents && !empty($homeroomSummaries)): ?>
            <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
                <p class="font-semibold text-slate-700">Data siswa yang ditampilkan sesuai kelas yang Anda wali:</p>
                <p class="mt-1 text-slate-500"><?= htmlspecialchars(implode(', ', $homeroomSummaries), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($selectedYearLabel)): ?>
                    <p class="mt-2 text-slate-500">Tahun ajaran aktif: <span class="font-semibold text-slate-700"><?= htmlspecialchars($selectedYearLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (false && $canUploadDocuments): ?>
            <div id="data-fisik-siswa" data-doc-section class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-800">Data Fisik Siswa</h2>
                    <p class="mt-1 text-xs text-slate-500">Unggah hasil scan ijazah, raport, kartu keluarga, akta kelahiran, dan KTP orang tua untuk keperluan administrasi.</p>
                </div>
                <form action="<?= htmlspecialchars(base_url('master/siswa/dokumen'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="space-y-5 px-6 py-5">
                    <?= csrf_field() ?>
                    <div>
                        <label for="student_id_documents" class="block text-sm font-semibold text-slate-600">Pilih Siswa</label>
                        <select
                            id="student_id_documents"
                            name="student_id"
                            class="student-select-visibility mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        >
                            <option value="">-- Pilih siswa --</option>
                            <?php foreach ($students as $optionStudent): ?>
                                <option value="<?= (int) ($optionStudent['id'] ?? 0) ?>">
                                    <?= htmlspecialchars($makePhotoOptionLabel($optionStudent), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="scan_ijazah" class="block text-sm font-semibold text-slate-600">Ijazah SMP/MTs Asal</label>
                            <input
                                type="file"
                                id="scan_ijazah"
                                name="scan_ijazah"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                        <div>
                            <label for="scan_rapor" class="block text-sm font-semibold text-slate-600">Raport SMP/MTs Asal</label>
                            <input
                                type="file"
                                id="scan_rapor"
                                name="scan_rapor"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                        <div>
                            <label for="scan_kartu_keluarga" class="block text-sm font-semibold text-slate-600">Kartu Keluarga</label>
                            <input
                                type="file"
                                id="scan_kartu_keluarga"
                                name="scan_kartu_keluarga"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                        <div>
                            <label for="scan_akta_lahir" class="block text-sm font-semibold text-slate-600">Akte Kelahiran</label>
                            <input
                                type="file"
                                id="scan_akta_lahir"
                                name="scan_akta_lahir"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                        <div>
                            <label for="scan_ktp_ayah" class="block text-sm font-semibold text-slate-600">KTP Orang Tua (Ayah)</label>
                            <input
                                type="file"
                                id="scan_ktp_ayah"
                                name="scan_ktp_ayah"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                        <div>
                            <label for="scan_ktp_ibu" class="block text-sm font-semibold text-slate-600">KTP Orang Tua (Ibu)</label>
                            <input
                                type="file"
                                id="scan_ktp_ibu"
                                name="scan_ktp_ibu"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Kosongkan field yang tidak ingin diganti. Format PDF/JPG/PNG/WEBP ¡¤ Maks 10 MB.</p>
                    <div>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                            Simpan Data Fisik
                        </button>
                    </div>
                </form>
            </div>
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-800">Kelengkapan Data Fisik</h2>
                    <p class="mt-1 text-xs text-slate-500">Pantau status dokumen semua siswa sekaligus. Gunakan pagination untuk melihat kelompok berikutnya (default 10 siswa per halaman).</p>
                    <p class="mt-2 text-xs text-slate-400">Klik baris siswa untuk memilih siswa pada form Data Fisik atau Unduh Dokumen.</p>
                </div>
                <div class="space-y-4 px-6 py-5">
                    <form action="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-slate-500">
                            Menampilkan
                            <span class="font-semibold text-slate-700"><?= min($documentTableTotal, ($documentTablePage - 1) * $documentTablePerPage + count($documentTableRows)) ?></span>
                            dari
                            <span class="font-semibold text-slate-700"><?= $documentTableTotal ?></span>
                            siswa
                        </div>
                        <div class="flex items-center gap-2 text-sm text-slate-600">
                            <label for="doc_per_page" class="font-medium">Per halaman</label>
                            <select
                                id="doc_per_page"
                                name="doc_per_page"
                                class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                onchange="this.form.submit()"
                            >
                                <?php foreach ([10, 20, 50, 100] as $perPageOption): ?>
                                    <option value="<?= $perPageOption ?>" <?= $documentTablePerPage === $perPageOption ? 'selected' : '' ?>><?= $perPageOption ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="doc_page" value="1" />
                        </div>
                    </form>
                    <div class="overflow-x-auto rounded-xl border border-slate-100">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Siswa</th>
                                    <?php foreach ($documentFieldDefinitions as $definition): ?>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <?php if (empty($documentTableRows)): ?>
                                    <tr>
                                        <td colspan="<?= 1 + count($documentFieldDefinitions) ?>" class="px-4 py-6 text-center text-sm text-slate-400">
                                            Belum ada data siswa untuk ditampilkan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documentTableRows as $row): ?>
                                        <?php $student = $row['student']; ?>
                                        <tr
                                            class="cursor-pointer transition hover:bg-slate-50"
                                            role="button"
                                            tabindex="0"
                                            data-doc-student-row
                                            data-doc-student-id="<?= (int) ($student['id'] ?? 0) ?>"
                                        >
                                            <td class="px-4 py-3 align-top">
                                                <div class="font-semibold text-slate-800">
                                                    <?= htmlspecialchars((string) ($student['nama'] ?? 'Tanpa Nama'), ENT_QUOTES, 'UTF-8') ?>
                                                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    <?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> /
                                                    <?= htmlspecialchars((string) ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="text-xs text-slate-400">
                                                    <?= htmlspecialchars((string) ($student['kelas_nama'] ?? 'Belum ditempatkan'), ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </td>
                                            <?php foreach ($documentFieldDefinitions as $key => $definition): ?>
                                                <?php $status = $row['statuses'][$key] ?? ['is_complete' => false]; ?>
                                                <td class="px-4 py-3 text-center">
                                                    <?php if (!empty($status['is_complete'])): ?>
                                                        <?php $docUrl = !empty($status['path']) ? asset($status['path']) : null; ?>
                                                        <?php if ($docUrl !== null): ?>
                                                            <a
                                                                href="<?= htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                                title="Lihat dokumen"
                                                            >
                                                                <i class="ri-eye-line"></i>
                                                                Ada
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                                <i class="ri-checkbox-circle-line"></i>
                                                                Ada
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">
                                                            <i class="ri-close-circle-line"></i>
                                                            Belum Ada
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($documentTablePages > 1): ?>
                        <?php
                            $docBaseUrl = base_url('master/siswa');
                            $prevPage = max(1, $documentTablePage - 1);
                            $nextPage = min($documentTablePages, $documentTablePage + 1);
                            $prevUrl = htmlspecialchars($docBaseUrl . '?doc_page=' . $prevPage . '&doc_per_page=' . $documentTablePerPage, ENT_QUOTES, 'UTF-8');
                            $nextUrl = htmlspecialchars($docBaseUrl . '?doc_page=' . $nextPage . '&doc_per_page=' . $documentTablePerPage, ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                            <p>Halaman <span class="font-semibold text-slate-800"><?= $documentTablePage ?></span> dari <span class="font-semibold text-slate-800"><?= $documentTablePages ?></span></p>
                            <div class="flex items-center gap-2">
                                <a
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 <?= $documentTablePage <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                                    href="<?= $prevUrl ?>"
                                >
                                    <i class="ri-arrow-left-line"></i>
                                    Sebelumnya
                                </a>
                                <a
                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 <?= $documentTablePage >= $documentTablePages ? 'pointer-events-none opacity-50' : '' ?>"
                                    href="<?= $nextUrl ?>"
                                >
                                    Berikutnya
                                    <i class="ri-arrow-right-line"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-800">Unduh Dokumen Fisik</h2>
                    <p class="mt-1 text-xs text-slate-500">Pilih siswa dan jenis dokumen untuk mengunduh hasil scan yang sudah tersimpan.</p>
                </div>
                <form action="<?= htmlspecialchars(base_url('master/siswa/dokumen/unduh'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5 px-6 py-5">
                    <?= csrf_field() ?>
                    <div>
                        <label for="download_student_id" class="block text-sm font-semibold text-slate-600">Pilih Siswa</label>
                        <select
                            id="download_student_id"
                            name="student_id"
                            class="student-select-visibility mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        >
                            <option value="">-- Pilih siswa --</option>
                            <?php foreach ($students as $optionStudent): ?>
                                <option value="<?= (int) ($optionStudent['id'] ?? 0) ?>">
                                    <?= htmlspecialchars($makePhotoOptionLabel($optionStudent), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="document_key" class="block text-sm font-semibold text-slate-600">Jenis Dokumen</label>
                        <select
                            id="document_key"
                            name="document_key"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            required
                        >
                            <option value="">-- Pilih dokumen --</option>
                            <option value="all">Semua dokumen (ZIP)</option>
                            <?php foreach ($documentFieldDefinitions as $key => $definition): ?>
                                <option value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                            Unduh Dokumen
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <?php if (false && $canUploadPhotos): ?>
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-slate-800">Upload Foto Siswa</h2>
                    <p class="mt-1 text-xs text-slate-500">Unggah foto per siswa atau sekaligus melalui berkas ZIP. Nama file pada ZIP sebaiknya menyesuaikan NISN atau NIPD.</p>
                </div>
                <div class="space-y-6 px-6 py-5">
                    <form action="<?= htmlspecialchars(base_url('master/siswa/foto'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-5">
                        <?= csrf_field() ?>
                        <div class="sm:col-span-3">
                            <label for="student_id" class="block text-sm font-semibold text-slate-600">Pilih Siswa</label>
                            <select
                                id="student_id"
                                name="student_id"
                                class="student-select-visibility mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                required
                            >
                                <option value="">-- Pilih siswa --</option>
                                <?php foreach ($students as $optionStudent): ?>
                                    <option value="<?= (int) ($optionStudent['id'] ?? 0) ?>">
                                        <?= htmlspecialchars($makePhotoOptionLabel($optionStudent), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="foto" class="block text-sm font-semibold text-slate-600">Foto Siswa</label>
                            <input
                                type="file"
                                id="foto"
                                name="foto"
                                accept=".jpg,.png"
                                required
                                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                            <p class="mt-1 text-xs text-slate-400">Format JPG atau PNG ¡¤ Maks 1 MB.</p>
                        </div>
                        <div class="sm:col-span-5">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                                Simpan Foto
                            </button>
                        </div>
                    </form>
                    <div class="border-t border-slate-100"></div>
                    <form action="<?= htmlspecialchars(base_url('master/siswa/foto/bulk'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="space-y-3" data-photo-bulk-form>
                        <?= csrf_field() ?>
                        <label for="foto_zip" class="block text-sm font-semibold text-slate-600">Upload Massal (ZIP)</label>
                        <input
                            type="file"
                            id="foto_zip"
                            name="foto_zip"
                            accept=".zip"
                            required
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                        />
                        <p class="text-xs text-slate-400">
                            Struktur ZIP bebas, setiap nama file akan dicocokkan dengan NISN atau NIPD siswa. Hanya file <code>.jpg</code> atau <code>.png</code> dengan ukuran maksimal 1 MB per file yang diproses.
                        </p>
                        <div
                            class="hidden rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800"
                            data-photo-bulk-inline-status
                            aria-live="polite"
                        >
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                                    <span class="photo-upload-spinner h-3.5 w-3.5 rounded-full border-2 border-sky-300 border-t-sky-700"></span>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold" data-photo-bulk-inline-title>Mengunggah ZIP...</p>
                                    <p class="mt-1 text-xs text-sky-700" data-photo-bulk-inline-message>Mohon tunggu, file sedang dikirim ke server.</p>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400" data-photo-bulk-submit>
                            <span class="photo-upload-spinner hidden h-4 w-4 rounded-full border-2 border-slate-300 border-t-white" data-photo-bulk-button-spinner></span>
                            <span data-photo-bulk-button-label>Unggah ZIP</span>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Daftar Siswa</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Menampilkan <span data-student-visible-count><?= count($studentTableRows) ?></span>
                        dari <?= $studentTableTotal ?> siswa
                    </p>
                    <?php if ($studentTablePages > 1): ?>
                        <p class="mt-1 text-xs text-slate-400">Halaman <?= $studentTablePage ?> dari <?= $studentTablePages ?></p>
                    <?php endif; ?>
                    <?php if ($canManageStudents): ?>
                        <p class="mt-1 text-xs text-slate-500">Unduh template dari sistem, isi sheet Data Siswa tanpa mengubah header, lalu unggah kembali. Detail format tersedia di sheet Petunjuk.</p>
                    <?php endif; ?>
                </div>
                <?php if ($canManageStudents || $canUploadPhotos): ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php if ($canUploadPhotos): ?>
                            <a
                                href="<?= htmlspecialchars(base_url('master/siswa/foto/massal'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 hover:border-sky-300 hover:bg-sky-100"
                            >
                                <i class="ri-image-add-line text-base"></i>
                                <span>Upload Foto Massal</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($canManageStudents): ?>
                        <?php if (!$showStudentForm): ?>
                            <a
                                href="<?= htmlspecialchars(base_url('master/siswa?form=create'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                <i class="ri-user-add-line text-base"></i>
                                <span>Tambah Siswa</span>
                            </a>
                        <?php endif; ?>
                        <a
                            href="<?= htmlspecialchars(base_url('master/siswa/import/template'), ENT_QUOTES, 'UTF-8') ?>"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100"
                        >
                            <i class="ri-download-2-line text-base"></i>
                            <span>Template Import</span>
                        </a>
                        <form
                            action="<?= htmlspecialchars(base_url('master/siswa/import'), ENT_QUOTES, 'UTF-8') ?>"
                            method="post"
                            enctype="multipart/form-data"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <?= csrf_field() ?>
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-500 transition hover:border-slate-300 focus-within:border-indigo-500" tabindex="0">
                                <i class="ri-file-upload-line text-base text-slate-400"></i>
                                <span>Pilih file</span>
                                <input
                                    type="file"
                                    name="import_file"
                                    accept=".xlsx"
                                    required
                                    class="sr-only"
                                />
                            </label>
                            <span class="text-xs text-slate-400 hidden sm:inline">.xlsx template sistem</span>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                <i class="ri-upload-cloud-line text-base"></i>
                                <span>Import</span>
                            </button>
                        </form>
                        <form
                            action="<?= htmlspecialchars(base_url('master/siswa/export'), ENT_QUOTES, 'UTF-8') ?>"
                            method="get"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <label class="sr-only" for="export-format">Format Ekspor</label>
                            <select
                                name="format"
                                id="export-format"
                                class="min-w-[105px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($exportFormatOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === 'pdf' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="sr-only" for="export-status">Status Siswa</label>
                            <select
                                name="status"
                                id="export-status"
                                class="min-w-[120px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <?php foreach ($exportStatusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === 'all' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-800">
                                <i class="ri-download-line text-base"></i>
                                <span>Ekspor</span>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($studentTableRows)): ?>
                <div class="border-b border-slate-100 px-6 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                        <div class="sm:flex-1">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400" for="student-quick-search">Pencarian Cepat</label>
                            <div class="relative mt-2">
                                <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                                <input
                                    type="search"
                                    id="student-quick-search"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                    placeholder="Cari nama, NIPD, NISN, kelas, atau kontak..."
                                    autocomplete="off"
                                    data-student-table-search
                                    aria-label="Cari cepat siswa"
                                />
                            </div>
                            <p class="mt-1 text-xs text-slate-400">Hasil pada halaman ini akan difilter tanpa memuat ulang halaman.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:border-slate-400 hover:text-slate-700"
                            data-student-search-reset
                            style="display: none;"
                        >
                            <i class="ri-close-circle-line text-base"></i>
                            Bersihkan
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
	                            <th class="px-6 py-4">Siswa</th>
	                            <th class="px-6 py-4">Identitas &amp; Kelas</th>
	                            <th class="min-w-[280px] px-6 py-4">Kelengkapan</th>
	                            <th class="px-6 py-4">Status</th>
                            <?php if ($hasStudentActions): ?>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white" data-student-table-body>
                        <?php foreach ($studentTableRows as $student): ?>
                            <?php
                                $studentId = (int) ($student['id'] ?? 0);
                                $studentName = (string) ($student['nama'] ?? 'Tanpa Nama');
                                $studentStatus = (string) ($student['status'] ?? '');
                                $studentStatusLabel = $statusOptions[$studentStatus] ?? ucfirst($studentStatus ?: '-');
                                $studentStatusStyle = $studentStatus === 'aktif'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600';
                                $dapodikStatus = (string) ($student['status_dapodik'] ?? '');
                                $dapodikStatusLabel = $statusDapodikOptions[$dapodikStatus] ?? ucfirst($dapodikStatus ?: '-');
                                $dapodikStatusStyle = match ($dapodikStatus) {
                                    'aktif' => 'bg-emerald-100 text-emerald-700',
                                    'belum_masuk' => 'bg-amber-100 text-amber-700',
                                    'mutasi', 'pindah' => 'bg-amber-100 text-amber-700',
                                    'residu' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                $genderLabel = $jenisKelaminOptions[$student['jenis_kelamin'] ?? ''] ?? '-';
                                $birthLabel = trim(($student['tempat_lahir'] ?? '') . ', ' . $formatDate($student['tanggal_lahir'] ?? null), ', ');
                                $className = trim((string) ($student['kelas_nama'] ?? ''));
                                $yearName = trim((string) ($student['tahun_ajaran_nama'] ?? ''));
                                $photoPath = trim((string) ($student['foto_path'] ?? ''));
                                $photoUrl = $photoPath !== '' ? asset($photoPath) : null;
	                                $documentStatuses = $buildStudentDocumentStatuses($student);
	                                $documentSummary = $summarizeStudentDocuments($documentStatuses);
	                                $missingDocumentLabels = array_map($shortDocumentLabel, $documentSummary['missing_labels']);
	                                $profileUrl = base_url('master/siswa/' . $studentId . '/profil');
                                $contactParts = array_values(array_filter([
                                    trim((string) ($student['hp'] ?? '')),
                                    trim((string) ($student['telepon'] ?? '')),
                                    trim((string) ($student['email'] ?? '')),
                                ], static fn ($value) => $value !== ''));
                                $contactLabel = !empty($contactParts) ? implode(' / ', array_slice($contactParts, 0, 2)) : '-';
                                $bantuanBadges = [];
                                if ((int) ($student['penerima_kps'] ?? 0) === 1) {
                                    $bantuanBadges[] = ['label' => 'KPS', 'class' => 'bg-amber-100 text-amber-700'];
                                }
                                if ((int) ($student['penerima_kip'] ?? 0) === 1) {
                                    $bantuanBadges[] = ['label' => 'KIP', 'class' => 'bg-sky-100 text-sky-700'];
                                }
                                if ((int) ($student['layak_pip'] ?? 0) === 1) {
                                    $bantuanBadges[] = ['label' => 'Layak PIP', 'class' => 'bg-indigo-100 text-indigo-700'];
                                }
                                $searchKeywords = $buildStudentSearchKeywords($student);
                            ?>
                            <tr class="align-top hover:bg-slate-50/60" data-student-row data-search-text="<?= htmlspecialchars($searchKeywords, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="px-6 py-5">
                                    <div class="flex min-w-[240px] gap-3">
                                        <div class="h-16 w-14 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                            <?php if ($photoUrl !== null): ?>
                                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover" />
                                            <?php else: ?>
                                                <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                    <i class="ri-user-3-line text-2xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
	                                            <p class="break-words text-sm font-semibold">
	                                                <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-slate-800 hover:text-indigo-600">
	                                                    <?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?>
	                                                </a>
	                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
	                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
	                                            </p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                <?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($birthLabel !== ''): ?>
                                                    &middot; <?= htmlspecialchars($birthLabel, ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="mt-2 break-all text-xs text-slate-400">
                                                Kontak: <?= htmlspecialchars($contactLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-xs text-slate-500">
                                    <div class="min-w-[190px] space-y-2">
                                        <div>
                                            <?php if ($className === '' && $yearName === ''): ?>
                                                <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Belum ditempatkan</span>
                                            <?php else: ?>
                                                <p class="font-semibold text-slate-700"><?= htmlspecialchars($className !== '' ? $className : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-slate-400"><?= htmlspecialchars($yearName !== '' ? $yearName : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="space-y-0.5">
                                            <p><span class="font-semibold text-slate-600">NIPD:</span> <?= htmlspecialchars($student['nipd'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p><span class="font-semibold text-slate-600">NISN:</span> <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="font-mono text-[11px] text-slate-400">NIK: <?= htmlspecialchars($student['nik'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="min-w-[280px] space-y-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <?php if ($photoUrl !== null): ?>
                                                <a
                                                    href="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                >
                                                    <i class="ri-image-line"></i>
                                                    Foto Ada
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">
                                                    <i class="ri-image-line"></i>
                                                    Belum Foto
                                                </span>
                                            <?php endif; ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                <i class="ri-folder-check-line"></i>
                                                Data fisik <?= (int) $documentSummary['total'] > 0 ? ((int) $documentSummary['complete'] . '/' . (int) $documentSummary['total']) : '-' ?>
                                            </span>
                                        </div>
                                        <?php if ((int) $documentSummary['total'] > 0): ?>
                                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full bg-emerald-500" style="width: <?= (int) $documentSummary['percent'] ?>%"></div>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5">
                                                <?php foreach ($documentStatuses as $documentStatus): ?>
                                                    <?php
                                                        $documentLabel = $shortDocumentLabel((string) ($documentStatus['label'] ?? 'Dokumen'));
                                                        $documentPath = trim((string) ($documentStatus['path'] ?? ''));
                                                        $documentUrl = $documentPath !== '' ? asset($documentPath) : null;
                                                    ?>
                                                    <?php if (!empty($documentStatus['is_complete'])): ?>
                                                        <?php if ($documentUrl !== null): ?>
                                                            <a
                                                                href="<?= htmlspecialchars($documentUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100"
                                                            >
                                                                <i class="ri-eye-line"></i>
                                                                <?= htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                                                <i class="ri-checkbox-circle-line"></i>
                                                                <?= htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">
                                                            <i class="ri-close-circle-line"></i>
                                                            <?= htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (!empty($missingDocumentLabels)): ?>
                                                <p class="text-[11px] text-amber-700">
                                                    Kurang: <?= htmlspecialchars(implode(', ', $missingDocumentLabels), ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-xs text-slate-400">Definisi dokumen fisik belum tersedia.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="min-w-[160px] space-y-2">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $studentStatusStyle ?>">
                                                <?= htmlspecialchars($studentStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $dapodikStatusStyle ?>">
                                                <?= htmlspecialchars($dapodikStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($bantuanBadges)): ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($bantuanBadges as $badge): ?>
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $badge['class'] ?>">
                                                        <?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Tidak ada bantuan tercatat</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php if ($hasStudentActions): ?>
	                                    <td class="px-6 py-5 text-right">
	                                        <div class="flex justify-end gap-2">
	                                            <a
	                                                href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"
	                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300 hover:text-slate-800"
	                                            >
	                                                <i class="ri-user-search-line text-sm"></i>
	                                                Profil
	                                            </a>
	                                            <?php if ($canEditStudents): ?>
	                                                <a
                                                    href="<?= htmlspecialchars(base_url('master/siswa/' . urlencode((string) $studentId) . '/edit'), ENT_QUOTES, 'UTF-8') ?>"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                                                >
                                                    <i class="ri-pencil-line text-sm"></i>
                                                    Edit
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($canManageStudents): ?>
                                                <form
                                                    action="<?= htmlspecialchars(base_url('master/siswa/' . $studentId . '/delete'), ENT_QUOTES, 'UTF-8') ?>"
                                                    method="post"
                                                    onsubmit="return confirm('Hapus data siswa ini?');"
                                                    class="inline-flex"
                                                >
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500">
                                                        <i class="ri-delete-bin-line text-sm"></i>
                                                        Hapus
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($studentTableRows)): ?>
                            <tr>
	                                <td colspan="<?= $hasStudentActions ? 5 : 4 ?>" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data siswa.</td>
                            </tr>
                        <?php else: ?>
                            <tr class="hidden" data-student-empty-message>
	                                <td colspan="<?= $hasStudentActions ? 5 : 4 ?>" class="px-6 py-8 text-center text-sm text-slate-400">Tidak ada siswa yang cocok dengan pencarian.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
	                </table>
	            </div>
	            <?php if ($studentTableTotal > 10): ?>
	                <?php
	                    $buildStudentPageUrl = static function (int $page) use ($studentTablePerPage): string {
	                        return base_url('master/siswa') . '?' . http_build_query([
	                            'page' => $page,
	                            'per_page' => $studentTablePerPage,
	                        ]);
	                    };
	                    $previousStudentPageUrl = htmlspecialchars($buildStudentPageUrl(max(1, $studentTablePage - 1)), ENT_QUOTES, 'UTF-8');
	                    $nextStudentPageUrl = htmlspecialchars($buildStudentPageUrl(min($studentTablePages, $studentTablePage + 1)), ENT_QUOTES, 'UTF-8');
	                ?>
	                <div class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
	                    <form action="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="flex items-center gap-2">
	                        <input type="hidden" name="page" value="1" />
	                        <label for="student_per_page" class="text-xs font-semibold text-slate-500">Per halaman</label>
	                        <select
	                            id="student_per_page"
	                            name="per_page"
	                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 focus:border-indigo-500 focus:outline-none focus:ring"
	                            onchange="this.form.submit()"
	                        >
	                            <?php foreach ([10, 20, 50, 100] as $perPageOption): ?>
	                                <option value="<?= $perPageOption ?>" <?= $studentTablePerPage === $perPageOption ? 'selected' : '' ?>><?= $perPageOption ?></option>
	                            <?php endforeach; ?>
	                        </select>
	                    </form>
	                    <div class="flex items-center gap-2">
	                        <a
	                            href="<?= $previousStudentPageUrl ?>"
	                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 <?= $studentTablePage <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
	                        >
	                            <i class="ri-arrow-left-line"></i>
	                            Sebelumnya
	                        </a>
	                        <span class="text-xs text-slate-400">Halaman <?= $studentTablePage ?>/<?= $studentTablePages ?></span>
	                        <a
	                            href="<?= $nextStudentPageUrl ?>"
	                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 <?= $studentTablePage >= $studentTablePages ? 'pointer-events-none opacity-50' : '' ?>"
	                        >
	                            Berikutnya
	                            <i class="ri-arrow-right-line"></i>
	                        </a>
	                    </div>
	                </div>
	            <?php endif; ?>
	        </div>
	    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('[data-student-table-search]');
    const tableBody = document.querySelector('[data-student-table-body]');

    if (!searchInput || !tableBody) {
        return;
    }

    const rows = Array.from(tableBody.querySelectorAll('[data-student-row]'));

    if (rows.length === 0) {
        return;
    }

    const emptyRow = tableBody.querySelector('[data-student-empty-message]');
    const resetButton = document.querySelector('[data-student-search-reset]');
    const visibleCountLabel = document.querySelector('[data-student-visible-count]');

    const updateResetButton = (term) => {
        if (!resetButton) {
            return;
        }
        resetButton.style.display = term === '' ? 'none' : '';
    };

    const filterRows = () => {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const keywords = row.dataset.searchText ?? '';
            const isMatch = term === '' || keywords.includes(term);
            row.classList.toggle('hidden', !isMatch);
            if (isMatch) {
                visibleCount += 1;
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('hidden', visibleCount !== 0);
        }

        if (visibleCountLabel) {
            visibleCountLabel.textContent = String(visibleCount);
        }

        updateResetButton(term);
    };

    searchInput.addEventListener('input', filterRows);

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            filterRows();
        });
    }

    updateResetButton('');
    filterRows();
});
</script>
