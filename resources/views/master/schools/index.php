<?php
$isEditing = isset($editingSchool) && $editingSchool !== null;
$limitReached = $limitReached ?? false;
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Profil Sekolah' : 'Tambah Profil Sekolah' ?>
            </h2>
            <?php if ($limitReached): ?>
                <p class="mt-1 text-xs text-slate-500">Hanya satu profil sekolah yang dapat disimpan. Silakan perbarui data yang sudah ada.</p>
            <?php endif; ?>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/sekolah/' . $editingSchool['id'] . '/update') : base_url('master/sekolah'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                enctype="multipart/form-data"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama Sekolah</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingSchool['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="SMK Negeri 1"
                    />
                </div>
                <div>
                    <label for="logo_sekolah" class="block text-sm font-medium text-slate-600">Logo Sekolah</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <?php if ($isEditing && !empty($editingSchool['logo_sekolah'])): ?>
                            <img
                                src="<?= htmlspecialchars(asset($editingSchool['logo_sekolah']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="Logo Sekolah"
                                class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white p-2"
                            />
                        <?php endif; ?>
                        <div class="flex-1">
                            <input
                                type="file"
                                id="logo_sekolah"
                                name="logo_sekolah"
                                accept=".jpg,.jpeg,.png,.webp,.svg"
                                <?= $isEditing ? '' : 'required' ?>
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                            <p class="mt-2 text-xs text-slate-400">Unggah logo sekolah berformat JPG, PNG, WEBP, atau SVG.</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="logo_dinas" class="block text-sm font-medium text-slate-600">Logo Dinas/Yayasan</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <?php if ($isEditing && !empty($editingSchool['logo_dinas'])): ?>
                            <img
                                src="<?= htmlspecialchars(asset($editingSchool['logo_dinas']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="Logo Dinas atau Yayasan"
                                class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white p-2"
                            />
                        <?php endif; ?>
                        <div class="flex-1">
                            <input
                                type="file"
                                id="logo_dinas"
                                name="logo_dinas"
                                accept=".jpg,.jpeg,.png,.webp,.svg"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                            <p class="mt-2 text-xs text-slate-400">Opsional. Unggah logo instansi pembina sekolah.</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="app_icon" class="block text-sm font-medium text-slate-600">Ikon Aplikasi</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <?php if ($isEditing && !empty($editingSchool['app_icon'])): ?>
                            <img
                                src="<?= htmlspecialchars(asset($editingSchool['app_icon']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="Ikon aplikasi"
                                class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white p-2"
                            />
                        <?php endif; ?>
                        <div class="flex-1">
                            <input
                                type="file"
                                id="app_icon"
                                name="app_icon"
                                accept=".jpg,.jpeg,.png,.webp,.svg"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                            <p class="mt-2 text-xs text-slate-400">
                                Digunakan sebagai favicon dan ikon aplikasi. Gunakan gambar persegi minimal 512×512 piksel.
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <label for="lambang_negara" class="block text-sm font-medium text-slate-600">Lambang Negara</label>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <?php if ($isEditing && !empty($editingSchool['lambang_negara'])): ?>
                            <img
                                src="<?= htmlspecialchars(asset($editingSchool['lambang_negara']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="Lambang Negara"
                                class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white p-2"
                            />
                        <?php endif; ?>
                        <div class="flex-1">
                            <input
                                type="file"
                                id="lambang_negara"
                                name="lambang_negara"
                                accept=".jpg,.jpeg,.png,.webp,.svg"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                            />
                            <p class="mt-2 text-xs text-slate-400">Opsional. Gambar akan tampil pada sampul raport (gunakan resolusi transparan jika tersedia).</p>
                        </div>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="npsn" class="block text-sm font-medium text-slate-600">NPSN</label>
                        <input
                            type="text"
                            id="npsn"
                            name="npsn"
                            value="<?= htmlspecialchars((string) old('npsn', $editingSchool['npsn'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="12345678"
                        />
                    </div>
                    <div>
                        <label for="nss" class="block text-sm font-medium text-slate-600">NSS</label>
                        <input
                            type="text"
                            id="nss"
                            name="nss"
                            value="<?= htmlspecialchars((string) old('nss', $editingSchool['nss'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="123456789012"
                        />
                    </div>
                </div>
                <div>
                    <label for="alamat" class="block text-sm font-medium text-slate-600">Alamat</label>
                    <textarea
                        id="alamat"
                        name="alamat"
                        rows="3"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Alamat lengkap sekolah"
                    ><?= htmlspecialchars((string) old('alamat', $editingSchool['alamat'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="desa" class="block text-sm font-medium text-slate-600">Desa/Kelurahan</label>
                        <input
                            type="text"
                            id="desa"
                            name="desa"
                            value="<?= htmlspecialchars((string) old('desa', $editingSchool['desa'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="kecamatan" class="block text-sm font-medium text-slate-600">Kecamatan</label>
                        <input
                            type="text"
                            id="kecamatan"
                            name="kecamatan"
                            value="<?= htmlspecialchars((string) old('kecamatan', $editingSchool['kecamatan'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="kabupaten" class="block text-sm font-medium text-slate-600">Kabupaten/Kota</label>
                        <input
                            type="text"
                            id="kabupaten"
                            name="kabupaten"
                            value="<?= htmlspecialchars((string) old('kabupaten', $editingSchool['kabupaten'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="provinsi" class="block text-sm font-medium text-slate-600">Provinsi</label>
                        <input
                            type="text"
                            id="provinsi"
                            name="provinsi"
                            value="<?= htmlspecialchars((string) old('provinsi', $editingSchool['provinsi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="kode_pos" class="block text-sm font-medium text-slate-600">Kode Pos</label>
                        <input
                            type="text"
                            id="kode_pos"
                            name="kode_pos"
                            value="<?= htmlspecialchars((string) old('kode_pos', $editingSchool['kode_pos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="telepon" class="block text-sm font-medium text-slate-600">Telepon</label>
                        <input
                            type="text"
                            id="telepon"
                            name="telepon"
                            value="<?= htmlspecialchars((string) old('telepon', $editingSchool['telepon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars((string) old('email', $editingSchool['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="website" class="block text-sm font-medium text-slate-600">Website</label>
                        <input
                            type="text"
                            id="website"
                            name="website"
                            value="<?= htmlspecialchars((string) old('website', $editingSchool['website'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="https://"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-slate-600">Latitude</label>
                        <input
                            type="text"
                            id="latitude"
                            name="latitude"
                            value="<?= htmlspecialchars((string) old('latitude', $editingSchool['latitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="-6.2000000"
                        />
                    </div>
                    <div>
                        <label for="longitude" class="block text-sm font-medium text-slate-600">Longitude</label>
                        <input
                            type="text"
                            id="longitude"
                            name="longitude"
                            value="<?= htmlspecialchars((string) old('longitude', $editingSchool['longitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="106.8000000"
                        />
                    </div>
                    <div>
                        <label for="presensi_radius_meter" class="block text-sm font-medium text-slate-600">Radius Presensi (m)</label>
                        <input
                            type="number"
                            id="presensi_radius_meter"
                            name="presensi_radius_meter"
                            min="0"
                            step="1"
                            value="<?= htmlspecialchars((string) old('presensi_radius_meter', $editingSchool['presensi_radius_meter'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="100"
                        />
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">
                    Jika diisi, siswa hanya dapat presensi dalam radius yang ditentukan dari lokasi sekolah.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="akreditasi" class="block text-sm font-medium text-slate-600">Akreditasi</label>
                        <input
                            type="text"
                            id="akreditasi"
                            name="akreditasi"
                            value="<?= htmlspecialchars((string) old('akreditasi', $editingSchool['akreditasi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                            placeholder="A, B, C, atau A+"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/sekolah'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-7">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Sekolah</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Lambang & Logo</th>
                            <th class="px-6 py-4">NPSN</th>
                            <th class="px-6 py-4">Kontak</th>
                            <th class="px-6 py-4">Akreditasi</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-700"><?= htmlspecialchars($school['nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-400">
                                        <?= htmlspecialchars($school['kabupaten'] ?? '-', ENT_QUOTES, 'UTF-8') ?>,
                                        <?= htmlspecialchars($school['provinsi'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($school['logo_sekolah'])): ?>
                                            <img src="<?= htmlspecialchars(asset($school['logo_sekolah']), ENT_QUOTES, 'UTF-8') ?>" alt="Logo Sekolah" class="h-12 w-12 rounded border border-slate-200 bg-white object-contain p-1" />
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Logo sekolah belum ada</span>
                                        <?php endif; ?>
                                        <?php if (!empty($school['logo_dinas'])): ?>
                                            <img src="<?= htmlspecialchars(asset($school['logo_dinas']), ENT_QUOTES, 'UTF-8') ?>" alt="Logo Dinas atau Yayasan" class="h-12 w-12 rounded border border-slate-200 bg-white object-contain p-1" />
                                        <?php endif; ?>
                                        <?php if (!empty($school['lambang_negara'])): ?>
                                            <img src="<?= htmlspecialchars(asset($school['lambang_negara']), ENT_QUOTES, 'UTF-8') ?>" alt="Lambang Negara" class="h-12 w-12 rounded border border-slate-200 bg-white object-contain p-1" />
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($school['npsn'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-500">
                                    <div><?= htmlspecialchars($school['telepon'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-xs text-slate-400"><?= htmlspecialchars($school['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($school['latitude']) && !empty($school['longitude'])): ?>
                                        <div class="text-xs text-slate-400">
                                            Koordinat: <?= htmlspecialchars((string) $school['latitude'], ENT_QUOTES, 'UTF-8') ?>,
                                            <?= htmlspecialchars((string) $school['longitude'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($school['presensi_radius_meter'])): ?>
                                                • Radius: <?= htmlspecialchars((string) $school['presensi_radius_meter'], ENT_QUOTES, 'UTF-8') ?> m
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($school['akreditasi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?= htmlspecialchars(base_url('master/sekolah?edit=' . urlencode((string) $school['id'])), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">Edit</a>
                                        <form action="<?= htmlspecialchars(base_url('master/sekolah/' . $school['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus profil sekolah ini?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($schools)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data sekolah.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
