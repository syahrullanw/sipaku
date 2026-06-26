<?php
    $isEditing = isset($editingTeacher) && $editingTeacher !== null;
    $editingTeacher = $isEditing ? $editingTeacher : [];
    $genderOptions = isset($genderOptions) && is_array($genderOptions) ? $genderOptions : [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];
    $religionOptions = isset($religionOptions) && is_array($religionOptions) ? $religionOptions : [
        'Islam' => 'Islam',
        'Kristen Protestan' => 'Kristen Protestan',
        'Kristen Katolik' => 'Kristen Katolik',
        'Hindu' => 'Hindu',
        'Buddha' => 'Buddha',
        'Konghucu' => 'Konghucu',
        'Kepercayaan' => 'Kepercayaan Lainnya',
    ];
    $maritalStatusOptions = isset($maritalStatusOptions) && is_array($maritalStatusOptions) ? $maritalStatusOptions : [
        'Belum Menikah' => 'Belum Menikah',
        'Menikah' => 'Menikah',
        'Cerai Hidup' => 'Cerai Hidup',
        'Cerai Mati' => 'Cerai Mati',
    ];
    $gtkTypeOptions = isset($gtkTypeOptions) && is_array($gtkTypeOptions) ? $gtkTypeOptions : [
        'Guru Mata Pelajaran' => 'Guru Mata Pelajaran',
        'Guru BK' => 'Guru BK',
        'Kepala Sekolah' => 'Kepala Sekolah',
        'Wakil Kepala Sekolah' => 'Wakil Kepala Sekolah',
        'Wali Kelas' => 'Wali Kelas',
        'Pembina Ekskul' => 'Pembina Ekskul',
        'Tenaga Kependidikan' => 'Tenaga Kependidikan',
    ];
    $employmentStatusOptions = isset($employmentStatusOptions) && is_array($employmentStatusOptions) ? $employmentStatusOptions : [
        'PNS' => 'PNS',
        'PPPK' => 'PPPK',
        'Honorer' => 'Honorer',
        'GTT' => 'Guru Tidak Tetap',
        'GTY' => 'Guru Tetap Yayasan',
        'Lainnya' => 'Lainnya',
    ];
    $educationOptions = isset($educationOptions) && is_array($educationOptions) ? $educationOptions : [
        'SMA/SMK' => 'SMA / SMK',
        'D3' => 'Diploma 3 (D3)',
        'S1' => 'Sarjana (S1)',
        'S2' => 'Magister (S2)',
        'S3' => 'Doktor (S3)',
        'Lainnya' => 'Lainnya',
    ];
    $studyStatusOptions = isset($studyStatusOptions) && is_array($studyStatusOptions) ? $studyStatusOptions : [
        'Tidak Kuliah' => 'Tidak Kuliah',
        'Sedang Kuliah' => 'Sedang Kuliah',
        'Lulus' => 'Sudah Lulus',
    ];
    $unexpectedExpenseHistory = isset($unexpectedExpenseHistory) && is_array($unexpectedExpenseHistory) ? $unexpectedExpenseHistory : [];
    $defaultSchoolPreset = isset($defaultSchoolName) ? (string) $defaultSchoolName : '';
    $schoolIndukValue = old('sekolah_induk', $editingTeacher['sekolah_induk'] ?? $defaultSchoolPreset);
    $selectedGender = old('jenis_kelamin', $editingTeacher['jenis_kelamin'] ?? '');
    $selectedReligion = old('agama', $editingTeacher['agama'] ?? '');
    $selectedMaritalStatus = old('status_perkawinan', $editingTeacher['status_perkawinan'] ?? '');
    $selectedGtkType = old('jenis_gtk', $editingTeacher['jenis_gtk'] ?? '');
    $selectedEmploymentStatus = old('status_kepegawaian', $editingTeacher['status_kepegawaian'] ?? '');
    $selectedEducation = old('pendidikan_terakhir', $editingTeacher['pendidikan_terakhir'] ?? '');
    $selectedStudyStatus = old('status_kuliah', $editingTeacher['status_kuliah'] ?? '');
    $demoModeEnabled = isset($demoModeEnabled) ? (bool) $demoModeEnabled : false;
?>

<?php if ($demoModeEnabled): ?>
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold">Mode demo aktif</p>
                <p class="text-xs text-amber-800/90">
                    Data pribadi disamarkan, dan perubahan data guru diblokir sementara.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Hanya baca</span>
        </div>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Data Guru' : 'Tambah Guru' ?>
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Setelah disimpan, sistem otomatis membuat akun login guru (username dari maksimal 6 huruf nama depan, password sementara akan ditampilkan pada notifikasi).
            </p>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/guru/' . $editingTeacher['id'] . '/update') : base_url('master/guru'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-6"
            >
                <?= csrf_field() ?>
                <fieldset <?= $demoModeEnabled ? 'disabled aria-disabled="true" class="opacity-60 pointer-events-none"' : '' ?>>
                    <?php include __DIR__ . '/_form-fields.php'; ?>

                    <div class="flex items-center justify-between gap-3">
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 <?= $demoModeEnabled ? 'cursor-not-allowed opacity-80' : '' ?>"
                        >
                            <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a href="<?= htmlspecialchars(base_url('master/guru'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                        <?php endif; ?>
                    </div>
                </fieldset>
                <?php if ($demoModeEnabled): ?>
                    <p class="rounded-lg bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700">Form tidak dapat disimpan saat mode demo aktif.</p>
                <?php endif; ?>
            </form>
        </div>
        <?php if ($isEditing): ?>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Riwayat Pengeluaran Tak Terduga</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Catatan pengeluaran tak terduga yang diajukan oleh <?= htmlspecialchars($editingTeacher['nama'] ?? 'Guru', ENT_QUOTES, 'UTF-8') ?>.
                </p>
                <?php if (empty($unexpectedExpenseHistory)): ?>
                    <p class="mt-4 text-sm text-slate-500">Belum ada pengeluaran tak terduga yang tercatat.</p>
                <?php else: ?>
                    <ul class="mt-4 space-y-3 text-sm">
                        <?php foreach ($unexpectedExpenseHistory as $expense): ?>
                            <?php
                                $description = trim((string) ($expense['deskripsi'] ?? ''));
                                $code = (string) ($expense['kode_transaksi'] ?? '');
                                $periodLabel = (string) ($expense['tahun_ajaran_nama'] ?? '');
                            ?>
                            <li class="rounded-lg border border-slate-200 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-800"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($expense['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($code !== ''): ?>
                                            <p class="text-xs text-slate-500"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                        <?php if ($periodLabel !== ''): ?>
                                            <p class="text-xs text-slate-400">Tahun ajaran: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-right text-sm font-semibold text-rose-600">
                                        <?= htmlspecialchars('Rp ' . number_format((float) ($expense['nominal'] ?? 0), 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                                <?php if ($description !== ''): ?>
                                    <p class="mt-2 text-xs text-slate-600"><?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800">Daftar Guru</h2>
                        <p class="mt-1 text-xs text-slate-500">Import XLS/XLSX dengan kolom minimal: nama, nip, email, telepon, alamat.</p>
                    </div>
                    <div class="relative">
                        <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                        <input
                            type="search"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Cari guru..."
                            autocomplete="off"
                            data-teacher-search
                        />
                    </div>
                </div>
                <form
                    action="<?= htmlspecialchars(base_url('master/guru/import'), ENT_QUOTES, 'UTF-8') ?>"
                    method="post"
                    enctype="multipart/form-data"
                    class="flex flex-col gap-2 md:flex-row md:items-center"
                >
                    <?= csrf_field() ?>
                    <input
                        type="file"
                        name="import_file"
                        accept=".xls,.xlsx"
                        required
                        <?= $demoModeEnabled ? 'disabled aria-disabled="true"' : '' ?>
                        class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500 focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 <?= $demoModeEnabled ? 'cursor-not-allowed opacity-70' : '' ?>"
                        <?= $demoModeEnabled ? 'disabled aria-disabled="true"' : '' ?>
                    >
                        <i class="ri-upload-cloud-line text-base"></i>
                        <span>Import</span>
                    </button>
                </form>
            </div>

            <div class="border-b border-slate-100 px-6 py-4">
                <form
                    action="<?= htmlspecialchars(base_url('master/guru/export'), ENT_QUOTES, 'UTF-8') ?>"
                    method="post"
                    class="grid gap-3 md:grid-cols-3"
                    data-teacher-export-form
                >
                    <?= csrf_field() ?>
                    <div class="space-y-1 text-xs text-slate-500">
                        <label class="font-semibold uppercase tracking-wide text-slate-500">Data</label>
                        <select
                            name="scope"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200"
                        >
                            <option value="all">Guru dan Tenaga Kependidikan</option>
                            <option value="teachers">Guru saja</option>
                            <option value="staff">Tenaga Kependidikan saja</option>
                        </select>
                    </div>
                    <div class="space-y-1 text-xs text-slate-500">
                        <label class="font-semibold uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            name="status"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring focus:ring-indigo-200"
                        >
                            <option value="all">Semua Status</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="font-semibold uppercase tracking-wide text-xs text-slate-500">Format</label>
                        <input type="hidden" name="format" value="pdf" data-export-format>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 md:w-auto"
                                data-export-button
                                data-format="pdf"
                            >
                                PDF
                            </button>
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:border-emerald-300 md:w-auto"
                                data-export-button
                                data-format="excel"
                            >
                                Excel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm" data-teacher-table>
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">No</th>
                            <th class="px-6 py-4">Guru</th>
                            <th class="px-6 py-4">Detail Profil</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white" data-teacher-tbody>
                        <?php foreach ($teachers as $index => $teacher): ?>
                            <?php
                                $isActive = (($teacher['status'] ?? 'aktif') === 'aktif');
                                $genderLabel = $genderOptions[$teacher['jenis_kelamin'] ?? ''] ?? '-';
                                $birthPlace = trim((string) ($teacher['tempat_lahir'] ?? ''));
                                $birthDateRaw = $teacher['tanggal_lahir'] ?? null;
                                $birthDateLabel = '-';
                                if (!empty($birthDateRaw)) {
                                    $birthTimestamp = strtotime((string) $birthDateRaw);
                                    if ($birthTimestamp !== false) {
                                        $birthDateLabel = date('d M Y', $birthTimestamp);
                                    }
                                }
                                if ($demoModeEnabled) {
                                    $birthDateLabel = \App\Support\DemoMode::maskDate($birthDateRaw);
                                }
                                $assignmentDateRaw = $teacher['tanggal_surat_tugas'] ?? null;
                                $assignmentDateLabel = '-';
                                if (!empty($assignmentDateRaw)) {
                                    $assignmentTimestamp = strtotime((string) $assignmentDateRaw);
                                    if ($assignmentTimestamp !== false) {
                                        $assignmentDateLabel = date('d M Y', $assignmentTimestamp);
                                    }
                                }
                                if ($demoModeEnabled) {
                                    $assignmentDateLabel = \App\Support\DemoMode::maskDate($assignmentDateRaw);
                                }
                                $tmtRaw = $teacher['tmt_pengangkatan'] ?? null;
                                $tmtLabel = '-';
                                if (!empty($tmtRaw)) {
                                    $tmtTimestamp = strtotime((string) $tmtRaw);
                                    if ($tmtTimestamp !== false) {
                                        $tmtLabel = date('d M Y', $tmtTimestamp);
                                    }
                                }
                                if ($demoModeEnabled) {
                                    $tmtLabel = \App\Support\DemoMode::maskDate($tmtRaw);
                                }
                                $taskTokens = array_values(array_filter(
                                    array_map('trim', preg_split('/[,;\n]+/', (string) ($teacher['tugas_tambahan'] ?? '')) ?: []),
                                    static fn (string $value): bool => $value !== ''
                                ));
                                $npwpRaw = (string) ($teacher['npwp'] ?? '');
                                if ($demoModeEnabled) {
                                    $npwpFormatted = \App\Support\DemoMode::maskIdentifier($npwpRaw);
                                } else {
                                    $npwpDigits = preg_replace('/\D+/', '', $npwpRaw);
                                    $npwpFormatted = $npwpDigits !== '' ? $npwpDigits : '-';
                                    if ($npwpDigits !== '' && strlen($npwpDigits) >= 15) {
                                        $digits = substr($npwpDigits, 0, 15);
                                        $npwpFormatted = substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '.' . substr($digits, 8, 1) . '-' . substr($digits, 9, 3) . '.' . substr($digits, 12, 3);
                                    }
                                }
                                $educationKey = $teacher['pendidikan_terakhir'] ?? '';
                                $educationLabel = $educationKey !== '' ? ($educationOptions[$educationKey] ?? $educationKey) : '-';
                                $studyStatusKey = $teacher['status_kuliah'] ?? '';
                                $studyStatusLabel = $studyStatusKey !== '' ? ($studyStatusOptions[$studyStatusKey] ?? $studyStatusKey) : '-';
                                $maritalKey = $teacher['status_perkawinan'] ?? '';
                                $maritalLabel = $maritalKey !== '' ? ($maritalStatusOptions[$maritalKey] ?? $maritalKey) : '-';
                                $statusKepegawaianLabel = (string) ($teacher['status_kepegawaian'] ?? '-');
                                $jenisGtkLabel = (string) ($teacher['jenis_gtk'] ?? '-');
                                $sekolahIndukLabel = (string) ($teacher['sekolah_induk'] ?? '-');
                                $tahunPensiunLabel = isset($teacher['tahun_pensiun']) && $teacher['tahun_pensiun'] !== null ? (string) $teacher['tahun_pensiun'] : '-';
                                $alamatLabel = trim((string) ($teacher['alamat'] ?? ''));
                                $alamatDisplay = $alamatLabel !== '' ? nl2br(htmlspecialchars($alamatLabel, ENT_QUOTES, 'UTF-8')) : '-';
                                $skPengangkatanLabel = (string) ($teacher['sk_pengangkatan'] ?? '');
                                $skPengangkatanLabel = $skPengangkatanLabel !== '' ? $skPengangkatanLabel : '-';
                                $penugasanSkLabel = $skPengangkatanLabel . ' · ' . $tmtLabel;
                                $religionLabel = $teacher['agama'] ?? '';
                                $religionLabel = $religionLabel !== '' ? ($religionOptions[$religionLabel] ?? $religionLabel) : '-';
                                $namaPasangan = trim((string) ($teacher['nama_pasangan'] ?? ''));
                                $pekerjaanPasangan = trim((string) ($teacher['pekerjaan_pasangan'] ?? ''));
                                $pasanganDisplay = trim($namaPasangan . ($pekerjaanPasangan !== '' ? ' (' . $pekerjaanPasangan . ')' : ''));
                                $kartuPasangan = trim((string) ($teacher['kartu_pasangan'] ?? ''));
                                $nuptkValue = trim((string) ($teacher['nuptk'] ?? ''));
                                $emailValue = trim((string) ($teacher['email'] ?? ''));
                                $phoneValue = trim((string) ($teacher['telepon'] ?? ''));
                                $namaWpValue = trim((string) ($teacher['nama_wp'] ?? ''));
                            ?>
                            <tr class="align-top hover:bg-slate-50/50" data-teacher-row>
                                <td class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= $index + 1 ?></td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold">
                                        <a href="<?= htmlspecialchars(base_url('master/guru/' . urlencode((string) $teacher['id']) . '/profil'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-900 hover:text-indigo-600">
                                            <?= htmlspecialchars((string) ($teacher['nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                    <dl class="mt-2 space-y-1 text-xs text-slate-500">
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Username:</dt>
                                            <dd class="inline ml-1"><span class="font-mono text-indigo-600"><?= htmlspecialchars((string) ($teacher['username'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">NIP:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars(($teacher['nip'] ?? '') !== '' ? $teacher['nip'] : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">NIK:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($teacher['nik'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">NUPTK:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($nuptkValue !== '' ? $nuptkValue : '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Kelamin:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Lahir:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($birthPlace !== '' ? $birthPlace . ', ' . $birthDateLabel : $birthDateLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Agama:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($religionLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Status Kawin:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars($maritalLabel, ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <div>
                                            <dt class="inline font-semibold text-slate-600">Ibu Kandung:</dt>
                                            <dd class="inline ml-1"><?= htmlspecialchars((string) ($teacher['nama_ibu_kandung'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </div>
                                        <?php if ($pasanganDisplay !== ''): ?>
                                            <div>
                                                <dt class="inline font-semibold text-slate-600">Pasangan:</dt>
                                                <dd class="inline ml-1"><?= htmlspecialchars($pasanganDisplay, ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($kartuPasangan !== ''): ?>
                                            <div>
                                                <dt class="inline font-semibold text-slate-600">Kartu:</dt>
                                                <dd class="inline ml-1"><?= htmlspecialchars($kartuPasangan, ENT_QUOTES, 'UTF-8') ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="space-y-3 text-xs text-slate-600">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penugasan</p>
                                            <div class="mt-1 space-y-1">
                                                <p>Nomor ST: <span class="font-medium text-slate-700"><?= htmlspecialchars(($teacher['nomor_surat_tugas'] ?? '') !== '' ? $teacher['nomor_surat_tugas'] : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Tanggal ST: <span class="font-medium text-slate-700"><?= htmlspecialchars($assignmentDateLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Sekolah Induk: <span class="font-medium text-slate-700"><?= htmlspecialchars($sekolahIndukLabel !== '' ? $sekolahIndukLabel : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Jenis GTK: <span class="font-medium text-slate-700"><?= htmlspecialchars($jenisGtkLabel !== '' ? $jenisGtkLabel : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Status Kepegawaian: <span class="font-medium text-slate-700"><?= htmlspecialchars($statusKepegawaianLabel !== '' ? $statusKepegawaianLabel : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>SK / TMT: <span class="font-medium text-slate-700"><?= htmlspecialchars($penugasanSkLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Lembaga: <span class="font-medium text-slate-700"><?= htmlspecialchars(($teacher['lembaga_pengangkat'] ?? '') !== '' ? $teacher['lembaga_pengangkat'] : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                            </div>
                                            <?php if (!empty($taskTokens)): ?>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <?php foreach ($taskTokens as $taskLabel): ?>
                                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-medium text-indigo-700">
                                                            <?= htmlspecialchars($taskLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontak & Lainnya</p>
                                            <div class="mt-1 space-y-1">
                                                <p>Email: <span class="font-medium text-slate-700"><?= htmlspecialchars($emailValue !== '' ? $emailValue : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Telepon: <span class="font-medium text-slate-700"><?= htmlspecialchars($phoneValue !== '' ? $phoneValue : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Alamat: <span class="font-medium text-slate-700"><?= $alamatDisplay ?></span></p>
                                                <p>NPWP: <span class="font-medium text-slate-700"><?= htmlspecialchars($npwpFormatted, ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Nama WP: <span class="font-medium text-slate-700"><?= htmlspecialchars($namaWpValue !== '' ? $namaWpValue : '-', ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Pendidikan: <span class="font-medium text-slate-700"><?= htmlspecialchars($educationLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Status Kuliah: <span class="font-medium text-slate-700"><?= htmlspecialchars($studyStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
                                                <p>Th. Pensiun: <span class="font-medium text-slate-700"><?= htmlspecialchars($tahunPensiunLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
</div>
</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.querySelector('[data-teacher-search]');
        const tableBody = document.querySelector('[data-teacher-tbody]');

        if (!searchInput || !tableBody) {
            return;
        }

        const rows = Array.from(tableBody.querySelectorAll('[data-teacher-row]'));

        rows.forEach((row) => {
            row.dataset.searchText = (row.textContent || '').toLowerCase().replace(/\s+/g, ' ').trim();
        });

        const applyFilter = () => {
            const query = (searchInput.value || '').toLowerCase().trim();
            let visible = 0;

            rows.forEach((row) => {
                const haystack = row.dataset.searchText || '';
                const show = query === '' || haystack.includes(query);
                row.classList.toggle('hidden', !show);
                if (show) {
                    visible += 1;
                }
            });

            const emptyRow = tableBody.querySelector('[data-teacher-empty]');
            if (emptyRow) {
                emptyRow.classList.toggle('hidden', visible !== 0);
            }
        };

        searchInput.addEventListener('input', () => window.requestAnimationFrame(applyFilter));
        searchInput.addEventListener('search', () => window.requestAnimationFrame(applyFilter));

        const exportForm = document.querySelector('[data-teacher-export-form]');
        if (exportForm) {
            const formatField = exportForm.querySelector('[data-export-format]');
            exportForm.querySelectorAll('[data-export-button]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (formatField) {
                        formatField.value = button.dataset.format ?? 'pdf';
                    }
                });
            });
        }

        applyFilter();
    });
</script>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                                            <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                            <?= htmlspecialchars($statusKepegawaianLabel !== '' ? $statusKepegawaianLabel : '-', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($demoModeEnabled): ?>
                                        <div class="text-xs font-semibold text-slate-400">Aksi dimatikan di mode demo</div>
	                                    <?php else: ?>
	                                        <div class="flex flex-wrap justify-end gap-2">
	                                            <a href="<?= htmlspecialchars(base_url('master/guru/' . urlencode((string) $teacher['id']) . '/profil'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Profil</a>
	                                            <form action="<?= htmlspecialchars(base_url('master/guru/' . $teacher['id'] . '/toggle-status'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?> guru ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="<?= $isActive ? 'nonaktif' : 'aktif' ?>">
                                                <button type="submit" class="inline-flex items-center rounded-lg border <?= $isActive ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' ?> px-3 py-1.5 text-xs font-semibold">
                                                    <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                            <form action="<?= htmlspecialchars(base_url('master/guru/' . $teacher['id'] . '/reset-password'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Reset password guru ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-50">Reset Password</button>
                                            </form>
                                            <a href="<?= htmlspecialchars(base_url('master/guru?edit=' . urlencode((string) $teacher['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                            <form action="<?= htmlspecialchars(base_url('master/guru/' . $teacher['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus data guru ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($teachers)): ?>
                            <tr data-teacher-empty>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data guru.</td>
                            </tr>
                        <?php else: ?>
                            <tr class="hidden" data-teacher-empty>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-400">Tidak ada guru yang cocok dengan pencarian.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
