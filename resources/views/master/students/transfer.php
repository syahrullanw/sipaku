<?php
    $activeYear = is_array($activeYear ?? null) ? $activeYear : null;
    $classOptions = is_array($classOptions ?? null) ? $classOptions : [];
    $selectedClassId = (int) old('kelas_id', 0);
    $genderValue = (string) old('jenis_kelamin', 'L');
    $canManageStudentMaster = (bool) ($canManageStudentMaster ?? false);
    $nipdPreview = trim((string) ($nipdPreview ?? ''));
    $activeYearLabel = '-';

    if ($activeYear !== null) {
        $semester = (int) ($activeYear['semester_aktif'] ?? 1);
        $activeYearLabel = sprintf(
            '%s - %s',
            (string) ($activeYear['nama'] ?? '-'),
            $semester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }

    $canSubmit = $activeYear !== null && !empty($classOptions);
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Master Siswa</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">Input Siswa Pindahan</h2>
            <p class="mt-1 text-sm text-slate-500">Tahun ajaran aktif: <span class="font-semibold text-slate-700"><?= htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($canManageStudentMaster): ?>
                <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    <i class="ri-list-check text-base"></i>
                    <span>Daftar Siswa</span>
                </a>
                <a href="<?= htmlspecialchars(base_url('master/siswa/penempatan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                    <i class="ri-route-line text-base"></i>
                    <span>Penempatan</span>
                </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(base_url('master/siswa/pindahan/daftar'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">
                <i class="ri-user-search-line text-base"></i>
                <span>Daftar Pindahan</span>
            </a>
        </div>
    </div>

    <?php if (!$canSubmit): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <?= $activeYear === null ? 'Aktifkan tahun ajaran terlebih dahulu sebelum menginput siswa pindahan.' : 'Belum ada kelas pada tahun ajaran aktif. Tambahkan kelas terlebih dahulu.' ?>
        </div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars(base_url('master/siswa/pindahan'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="grid gap-6 xl:grid-cols-12">
        <?= csrf_field() ?>

        <div class="space-y-6 xl:col-span-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Data Pindahan</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="sekolah_asal" class="block text-sm font-medium text-slate-600">Sekolah Asal<span class="text-rose-500">*</span></label>
                        <input type="text" id="sekolah_asal" name="sekolah_asal" value="<?= htmlspecialchars((string) old('sekolah_asal', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" placeholder="Nama sekolah sebelumnya" />
                    </div>
                    <div>
                        <label for="kelas_id" class="block text-sm font-medium text-slate-600">Kelas Tujuan<span class="text-rose-500">*</span></label>
                        <select id="kelas_id" name="kelas_id" required <?= $canSubmit ? '' : 'disabled' ?> class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring disabled:bg-slate-100 disabled:text-slate-400">
                            <option value="">Pilih kelas</option>
                            <?php foreach ($classOptions as $id => $label): ?>
                                <?php $optionId = (int) $id; ?>
                                <option value="<?= htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedClassId === $optionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identitas Siswa</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="nama" class="block text-sm font-medium text-slate-600">Nama Lengkap<span class="text-rose-500">*</span></label>
                        <input type="text" id="nama" name="nama" value="<?= htmlspecialchars((string) old('nama', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600">NIPD</label>
                        <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                            <?= htmlspecialchars($nipdPreview !== '' ? $nipdPreview : 'Otomatis saat disimpan', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Format: tahun ajaran + kode 2 siswa pindahan + nomor urut.</p>
                    </div>
                    <div>
                        <label for="nisn" class="block text-sm font-medium text-slate-600">NISN<span class="text-rose-500">*</span></label>
                        <input type="text" id="nisn" name="nisn" inputmode="numeric" maxlength="10" value="<?= htmlspecialchars((string) old('nisn', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" placeholder="10 digit angka" />
                    </div>
                    <div>
                        <label for="nik" class="block text-sm font-medium text-slate-600">NIK<span class="text-rose-500">*</span></label>
                        <input type="text" id="nik" name="nik" inputmode="numeric" maxlength="16" value="<?= htmlspecialchars((string) old('nik', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" placeholder="16 digit angka" />
                    </div>
                    <div>
                        <label for="jenis_kelamin" class="block text-sm font-medium text-slate-600">Jenis Kelamin<span class="text-rose-500">*</span></label>
                        <select id="jenis_kelamin" name="jenis_kelamin" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring">
                            <option value="L" <?= $genderValue === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                            <option value="P" <?= $genderValue === 'P' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label for="tempat_lahir" class="block text-sm font-medium text-slate-600">Tempat Lahir<span class="text-rose-500">*</span></label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= htmlspecialchars((string) old('tempat_lahir', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div>
                        <label for="tanggal_lahir" class="block text-sm font-medium text-slate-600">Tanggal Lahir<span class="text-rose-500">*</span></label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= htmlspecialchars((string) old('tanggal_lahir', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div>
                        <label for="agama" class="block text-sm font-medium text-slate-600">Agama</label>
                        <input type="text" id="agama" name="agama" value="<?= htmlspecialchars((string) old('agama', ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Orang Tua &amp; Kontak</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="ayah_nama" class="block text-sm font-medium text-slate-600">Nama Ayah<span class="text-rose-500">*</span></label>
                        <input type="text" id="ayah_nama" name="ayah_nama" value="<?= htmlspecialchars((string) old('ayah_nama', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div>
                        <label for="ibu_nama" class="block text-sm font-medium text-slate-600">Nama Ibu<span class="text-rose-500">*</span></label>
                        <input type="text" id="ibu_nama" name="ibu_nama" value="<?= htmlspecialchars((string) old('ibu_nama', ''), ENT_QUOTES, 'UTF-8') ?>" required class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div>
                        <label for="hp" class="block text-sm font-medium text-slate-600">HP</label>
                        <input type="text" id="hp" name="hp" value="<?= htmlspecialchars((string) old('hp', ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" placeholder="08..." />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-600">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars((string) old('email', ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                    </div>
                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-sm font-medium text-slate-600">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3" class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"><?= htmlspecialchars((string) old('alamat', ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-4 xl:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Status Setelah Disimpan</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Status siswa</dt>
                        <dd class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Aktif</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Status Dapodik</dt>
                        <dd class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Belum masuk Dapodik</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Akun siswa</dt>
                        <dd class="text-right text-xs font-semibold text-slate-700">Dibuat otomatis</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Format Kunci</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li>NISN harus 10 digit angka.</li>
                    <li>NIK harus 16 digit angka.</li>
                    <li>Tanggal lahir tidak boleh melebihi hari ini.</li>
                    <li>NIPD dibuat otomatis oleh sistem dan tetap unik.</li>
                    <li>NISN dan NIK tidak boleh sama dengan siswa lain.</li>
                </ul>
            </div>

            <button type="submit" <?= $canSubmit ? '' : 'disabled' ?> class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300">
                <i class="ri-save-3-line text-base"></i>
                <span>Simpan Siswa Pindahan</span>
            </button>
        </aside>
    </form>
</div>
