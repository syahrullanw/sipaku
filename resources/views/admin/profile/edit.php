<?php
    $role = $role ?? 'admin';
    $profileUser = $profileUser ?? [];
    $editingTeacher = $editingTeacher ?? [];
    $student = $student ?? [];
    $genderOptions = $genderOptions ?? [];
    $religionOptions = $religionOptions ?? [];
    $maritalStatusOptions = $maritalStatusOptions ?? [];
    $gtkTypeOptions = $gtkTypeOptions ?? [];
    $employmentStatusOptions = $employmentStatusOptions ?? [];
    $educationOptions = $educationOptions ?? [];
    $studyStatusOptions = $studyStatusOptions ?? [];
    $demoModeEnabled = isset($demoModeEnabled) ? (bool) $demoModeEnabled : false;
    $schoolIndukValue = old('sekolah_induk', $schoolIndukValue ?? ($editingTeacher['sekolah_induk'] ?? ''));
    $selectedGender = old('jenis_kelamin', $selectedGender ?? ($editingTeacher['jenis_kelamin'] ?? ''));
    $selectedReligion = old('agama', $selectedReligion ?? ($editingTeacher['agama'] ?? ''));
    $selectedMaritalStatus = old('status_perkawinan', $selectedMaritalStatus ?? ($editingTeacher['status_perkawinan'] ?? ''));
    $selectedGtkType = old('jenis_gtk', $selectedGtkType ?? ($editingTeacher['jenis_gtk'] ?? ''));
    $selectedEmploymentStatus = old('status_kepegawaian', $selectedEmploymentStatus ?? ($editingTeacher['status_kepegawaian'] ?? ''));
    $selectedEducation = old('pendidikan_terakhir', $selectedEducation ?? ($editingTeacher['pendidikan_terakhir'] ?? ''));
    $selectedStudyStatus = old('status_kuliah', $selectedStudyStatus ?? ($editingTeacher['status_kuliah'] ?? ''));

    $studentContact = [
        'email' => old('email', $student['email'] ?? ''),
        'telepon' => old('telepon', $student['telepon'] ?? ''),
        'hp' => old('hp', $student['hp'] ?? ''),
        'alamat' => old('alamat', $student['alamat'] ?? ''),
        'dusun' => old('dusun', $student['dusun'] ?? ''),
        'kelurahan' => old('kelurahan', $student['kelurahan'] ?? ''),
        'kecamatan' => old('kecamatan', $student['kecamatan'] ?? ''),
        'kode_pos' => old('kode_pos', $student['kode_pos'] ?? ''),
        'jenis_tinggal' => old('jenis_tinggal', $student['jenis_tinggal'] ?? ''),
        'alat_transportasi' => old('alat_transportasi', $student['alat_transportasi'] ?? ''),
    ];

    $studentResidenceOptions = [
        'Orang Tua' => 'Bersama Orang Tua',
        'Wali' => 'Bersama Wali',
        'Kos' => 'Kos / Kontrakan',
        'Asrama' => 'Asrama',
        'Lainnya' => 'Lainnya',
    ];

    $studentTransportOptions = [
        'Jalan Kaki' => 'Jalan Kaki',
        'Sepeda' => 'Sepeda',
        'Sepeda Motor' => 'Sepeda Motor',
        'Mobil' => 'Mobil',
        'Angkutan Umum' => 'Angkutan Umum',
        'Lainnya' => 'Lainnya',
    ];
?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-800">Profil Pengguna</h2>
                <p class="text-sm text-slate-500">
                    Perbarui informasi dasar akun Anda untuk memastikan data tetap akurat.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 uppercase">
                <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
    </div>

    <?php if ($demoModeEnabled): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                    <i class="ri-eye-close-line text-lg"></i>
                </span>
                <div>
                    <p class="font-semibold">Mode demo aktif</p>
                    <p class="text-xs text-amber-800/90">Data pribadi disamarkan dan formulir dinonaktifkan sementara.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?php if ($role === 'guru'): ?>
            <form action="<?= htmlspecialchars(base_url('profile'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                <?= csrf_field() ?>
                <fieldset <?= $demoModeEnabled ? 'disabled aria-disabled="true" class="opacity-60 pointer-events-none"' : '' ?>>
                    <?php include resource_path('views/master/teachers/_form-fields.php'); ?>
                    <div class="flex items-center justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 <?= $demoModeEnabled ? 'cursor-not-allowed opacity-80' : '' ?>"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </fieldset>
                <?php if ($demoModeEnabled): ?>
                    <p class="mt-3 rounded-lg bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700">Perubahan profil guru dinonaktifkan pada mode demo.</p>
                <?php endif; ?>
            </form>
        <?php elseif ($role === 'siswa'): ?>
            <form action="<?= htmlspecialchars(base_url('profile'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
                <?= csrf_field() ?>
                <fieldset <?= $demoModeEnabled ? 'disabled aria-disabled="true" class="opacity-60 pointer-events-none"' : '' ?>>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars((string) $studentContact['email'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="siswa@sekolah.sch.id"
                            />
                        </div>
                        <div>
                            <label for="telepon" class="block text-sm font-medium text-slate-600">Telepon Rumah</label>
                            <input
                                type="text"
                                id="telepon"
                                name="telepon"
                                value="<?= htmlspecialchars((string) $studentContact['telepon'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="0264..."
                            />
                        </div>
                        <div>
                            <label for="hp" class="block text-sm font-medium text-slate-600">Nomor HP</label>
                            <input
                                type="text"
                                id="hp"
                                name="hp"
                                value="<?= htmlspecialchars((string) $studentContact['hp'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="+62..."
                            />
                        </div>
                        <div>
                            <label for="kode_pos" class="block text-sm font-medium text-slate-600">Kode Pos</label>
                            <input
                                type="text"
                                id="kode_pos"
                                name="kode_pos"
                                value="<?= htmlspecialchars((string) $studentContact['kode_pos'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="40211"
                            />
                        </div>
                    </div>
                    <div>
                        <label for="alamat" class="block text-sm font-medium text-slate-600">Alamat Lengkap</label>
                        <textarea
                            id="alamat"
                            name="alamat"
                            rows="3"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="Nama jalan, RT/RW, kelurahan, kecamatan"
                        ><?= htmlspecialchars((string) $studentContact['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="dusun" class="block text-sm font-medium text-slate-600">Dusun</label>
                            <input
                                type="text"
                                id="dusun"
                                name="dusun"
                                value="<?= htmlspecialchars((string) $studentContact['dusun'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="kelurahan" class="block text-sm font-medium text-slate-600">Kelurahan</label>
                            <input
                                type="text"
                                id="kelurahan"
                                name="kelurahan"
                                value="<?= htmlspecialchars((string) $studentContact['kelurahan'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="kecamatan" class="block text-sm font-medium text-slate-600">Kecamatan</label>
                            <input
                                type="text"
                                id="kecamatan"
                                name="kecamatan"
                                value="<?= htmlspecialchars((string) $studentContact['kecamatan'], ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            />
                        </div>
                        <div>
                            <label for="jenis_tinggal" class="block text-sm font-medium text-slate-600">Jenis Tinggal</label>
                            <select
                                id="jenis_tinggal"
                                name="jenis_tinggal"
                                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <option value="">Pilih jenis tinggal</option>
                                <?php foreach ($studentResidenceOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $studentContact['jenis_tinggal'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="alat_transportasi" class="block text-sm font-medium text-slate-600">Alat Transportasi</label>
                            <select
                                id="alat_transportasi"
                                name="alat_transportasi"
                                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
                            >
                                <option value="">Pilih alat transportasi</option>
                                <?php foreach ($studentTransportOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $studentContact['alat_transportasi'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 <?= $demoModeEnabled ? 'cursor-not-allowed opacity-80' : '' ?>"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </fieldset>
                <?php if ($demoModeEnabled): ?>
                    <p class="mt-3 rounded-lg bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700">Perubahan profil siswa dinonaktifkan pada mode demo.</p>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <form action="<?= htmlspecialchars(base_url('profile'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6 max-w-xl">
                <?= csrf_field() ?>
                <fieldset <?= $demoModeEnabled ? 'disabled aria-disabled="true" class="opacity-60 pointer-events-none"' : '' ?>>
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-600">Nama Lengkap</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= htmlspecialchars((string) old('name', $profileUser['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars((string) old('email', $profileUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="admin@sekolah.sch.id"
                        />
                        <p class="mt-1 text-xs text-slate-400">Kosongkan jika tidak ingin menampilkan email pada akun.</p>
                    </div>
                    <div class="flex items-center justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400/60 <?= $demoModeEnabled ? 'cursor-not-allowed opacity-80' : '' ?>"
                        >
                            <i class="ri-save-3-line text-lg"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </fieldset>
                <?php if ($demoModeEnabled): ?>
                    <p class="mt-3 rounded-lg bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700">Perubahan profil pengguna dinonaktifkan pada mode demo.</p>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
