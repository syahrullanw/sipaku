<div class="space-y-4">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identitas Guru</h3>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="nama" class="block text-sm font-medium text-slate-600">Nama Lengkap<span class="text-rose-500">*</span></label>
            <input
                type="text"
                id="nama"
                name="nama"
                value="<?= htmlspecialchars((string) old('nama', $editingTeacher['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="Nama lengkap sesuai dokumen"
            />
        </div>
        <div>
            <label for="nip" class="block text-sm font-medium text-slate-600">NIP</label>
            <input
                type="text"
                id="nip"
                name="nip"
                value="<?= htmlspecialchars((string) old('nip', $editingTeacher['nip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="1987..."
            />
        </div>
        <div>
            <label for="nik" class="block text-sm font-medium text-slate-600">NIK<span class="text-rose-500">*</span></label>
            <input
                type="text"
                id="nik"
                name="nik"
                value="<?= htmlspecialchars((string) old('nik', $editingTeacher['nik'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                required
                maxlength="16"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="16 digit NIK"
            />
        </div>
        <div>
            <label for="nuptk" class="block text-sm font-medium text-slate-600">NUPTK</label>
            <input
                type="text"
                id="nuptk"
                name="nuptk"
                value="<?= htmlspecialchars((string) old('nuptk', $editingTeacher['nuptk'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <span class="block text-sm font-medium text-slate-600">Jenis Kelamin<span class="text-rose-500">*</span></span>
            <div class="mt-2 flex flex-wrap gap-3">
                <?php foreach ($genderOptions as $value => $label): ?>
                    <?php $isSelected = (string) $selectedGender === (string) $value; ?>
                    <label class="inline-flex items-center gap-2 rounded-lg border <?= $isSelected ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white' ?> px-3 py-2 text-sm text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50">
                        <input
                            type="radio"
                            name="jenis_kelamin"
                            value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"
                            class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            <?= $isSelected ? 'checked' : '' ?>
                            required
                        />
                        <span><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label for="tempat_lahir" class="block text-sm font-medium text-slate-600">Tempat Lahir<span class="text-rose-500">*</span></label>
            <input
                type="text"
                id="tempat_lahir"
                name="tempat_lahir"
                value="<?= htmlspecialchars((string) old('tempat_lahir', $editingTeacher['tempat_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
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
                value="<?= htmlspecialchars((string) old('tanggal_lahir', $editingTeacher['tanggal_lahir'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="nama_ibu_kandung" class="block text-sm font-medium text-slate-600">Nama Ibu Kandung<span class="text-rose-500">*</span></label>
            <input
                type="text"
                id="nama_ibu_kandung"
                name="nama_ibu_kandung"
                value="<?= htmlspecialchars((string) old('nama_ibu_kandung', $editingTeacher['nama_ibu_kandung'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="agama" class="block text-sm font-medium text-slate-600">Agama<span class="text-rose-500">*</span></label>
            <select
                id="agama"
                name="agama"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedReligion === '' ? 'selected' : '' ?> disabled>Pilih agama</option>
                <?php foreach ($religionOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedReligion === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status_perkawinan" class="block text-sm font-medium text-slate-600">Status Perkawinan</label>
            <select
                id="status_perkawinan"
                name="status_perkawinan"
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedMaritalStatus === '' ? 'selected' : '' ?>>Pilih status</option>
                <?php foreach ($maritalStatusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedMaritalStatus === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="nama_pasangan" class="block text-sm font-medium text-slate-600">Nama Suami/Istri</label>
            <input
                type="text"
                id="nama_pasangan"
                name="nama_pasangan"
                value="<?= htmlspecialchars((string) old('nama_pasangan', $editingTeacher['nama_pasangan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="pekerjaan_pasangan" class="block text-sm font-medium text-slate-600">Pekerjaan Suami/Istri</label>
            <input
                type="text"
                id="pekerjaan_pasangan"
                name="pekerjaan_pasangan"
                value="<?= htmlspecialchars((string) old('pekerjaan_pasangan', $editingTeacher['pekerjaan_pasangan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="kartu_pasangan" class="block text-sm font-medium text-slate-600">Kartu Suami/Istri</label>
            <input
                type="text"
                id="kartu_pasangan"
                name="kartu_pasangan"
                value="<?= htmlspecialchars((string) old('kartu_pasangan', $editingTeacher['kartu_pasangan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="Nomor kartu keluarga / lainnya"
            />
        </div>
    </div>
</div>

<div class="space-y-4">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Informasi Penugasan</h3>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="nomor_surat_tugas" class="block text-sm font-medium text-slate-600">Nomor Surat Tugas</label>
            <input
                type="text"
                id="nomor_surat_tugas"
                name="nomor_surat_tugas"
                value="<?= htmlspecialchars((string) old('nomor_surat_tugas', $editingTeacher['nomor_surat_tugas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="tanggal_surat_tugas" class="block text-sm font-medium text-slate-600">Tanggal Surat Tugas</label>
            <input
                type="date"
                id="tanggal_surat_tugas"
                name="tanggal_surat_tugas"
                value="<?= htmlspecialchars((string) old('tanggal_surat_tugas', $editingTeacher['tanggal_surat_tugas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div class="md:col-span-2">
            <label for="sekolah_induk" class="block text-sm font-medium text-slate-600">Sekolah Induk</label>
            <input
                type="text"
                id="sekolah_induk"
                name="sekolah_induk"
                value="<?= htmlspecialchars((string) $schoolIndukValue, ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="Nama sekolah induk"
            />
            <p class="mt-1 text-xs text-slate-400">Kosongkan untuk menggunakan nama sekolah yang tersimpan di pengaturan profil sekolah.</p>
        </div>
        <div>
            <label for="jenis_gtk" class="block text-sm font-medium text-slate-600">Jenis GTK (Level Pengguna)<span class="text-rose-500">*</span></label>
            <select
                id="jenis_gtk"
                name="jenis_gtk"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedGtkType === '' ? 'selected' : '' ?> disabled>Pilih jenis GTK</option>
                <?php foreach ($gtkTypeOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedGtkType === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status_kepegawaian" class="block text-sm font-medium text-slate-600">Status Kepegawaian<span class="text-rose-500">*</span></label>
            <select
                id="status_kepegawaian"
                name="status_kepegawaian"
                required
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedEmploymentStatus === '' ? 'selected' : '' ?> disabled>Pilih status</option>
                <?php foreach ($employmentStatusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedEmploymentStatus === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="sk_pengangkatan" class="block text-sm font-medium text-slate-600">Nomor SK Pengangkatan</label>
            <input
                type="text"
                id="sk_pengangkatan"
                name="sk_pengangkatan"
                value="<?= htmlspecialchars((string) old('sk_pengangkatan', $editingTeacher['sk_pengangkatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="tmt_pengangkatan" class="block text-sm font-medium text-slate-600">TMT Pengangkatan</label>
            <input
                type="date"
                id="tmt_pengangkatan"
                name="tmt_pengangkatan"
                value="<?= htmlspecialchars((string) old('tmt_pengangkatan', $editingTeacher['tmt_pengangkatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="lembaga_pengangkat" class="block text-sm font-medium text-slate-600">Lembaga Pengangkat</label>
            <input
                type="text"
                id="lembaga_pengangkat"
                name="lembaga_pengangkat"
                value="<?= htmlspecialchars((string) old('lembaga_pengangkat', $editingTeacher['lembaga_pengangkat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="Kemendikbud, Yayasan, dll."
            />
        </div>
    </div>
    <div>
        <label for="tugas_tambahan" class="block text-sm font-medium text-slate-600">Tugas Tambahan</label>
        <textarea
            id="tugas_tambahan"
            name="tugas_tambahan"
            rows="3"
            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            placeholder="Contoh: Wali Kelas X AKL 1, Pembina Pramuka"
        ><?= htmlspecialchars((string) old('tugas_tambahan', $editingTeacher['tugas_tambahan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="mt-1 text-xs text-slate-400">Pisahkan dengan koma untuk lebih dari satu tugas tambahan.</p>
    </div>
</div>

<div class="space-y-4">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Data Pajak & Pengembangan Karier</h3>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="npwp" class="block text-sm font-medium text-slate-600">NPWP</label>
            <input
                type="text"
                id="npwp"
                name="npwp"
                inputmode="numeric"
                value="<?= htmlspecialchars((string) old('npwp', $editingTeacher['npwp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="15 digit NPWP"
            />
        </div>
        <div>
            <label for="nama_wp" class="block text-sm font-medium text-slate-600">Nama Wajib Pajak</label>
            <input
                type="text"
                id="nama_wp"
                name="nama_wp"
                value="<?= htmlspecialchars((string) old('nama_wp', $editingTeacher['nama_wp'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
        <div>
            <label for="pendidikan_terakhir" class="block text-sm font-medium text-slate-600">Pendidikan Terakhir</label>
            <select
                id="pendidikan_terakhir"
                name="pendidikan_terakhir"
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedEducation === '' ? 'selected' : '' ?>>Pilih pendidikan terakhir</option>
                <?php foreach ($educationOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedEducation === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status_kuliah" class="block text-sm font-medium text-slate-600">Status Kuliah</label>
            <select
                id="status_kuliah"
                name="status_kuliah"
                class="mt-2 block w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring"
            >
                <option value="" <?= $selectedStudyStatus === '' ? 'selected' : '' ?>>Pilih status</option>
                <?php foreach ($studyStatusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $selectedStudyStatus === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="tahun_pensiun" class="block text-sm font-medium text-slate-600">Perkiraan Tahun Pensiun</label>
            <input
                type="number"
                id="tahun_pensiun"
                name="tahun_pensiun"
                min="1950"
                max="2100"
                value="<?= htmlspecialchars((string) old('tahun_pensiun', $editingTeacher['tahun_pensiun'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
            />
        </div>
    </div>
</div>

<div class="space-y-4">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Kontak & Alamat</h3>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars((string) old('email', $editingTeacher['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="guru@smk.sch.id"
            />
        </div>
        <div>
            <label for="telepon" class="block text-sm font-medium text-slate-600">Telepon</label>
            <input
                type="text"
                id="telepon"
                name="telepon"
                value="<?= htmlspecialchars((string) old('telepon', $editingTeacher['telepon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                placeholder="+62..."
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
        ><?= htmlspecialchars((string) old('alamat', $editingTeacher['alamat'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>
