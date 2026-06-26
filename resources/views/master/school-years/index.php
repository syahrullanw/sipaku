<?php
$isEditing = isset($editingYear) && $editingYear !== null;
$teacherOptions = $teacherOptions ?? [];
$teachersById = $teachersById ?? [];

if (!function_exists('format_school_year_date')) {
    function format_school_year_date(?string $date): string
    {
        if ($date === null || $date === '') {
            return '-';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        return date('d/m/Y', $timestamp);
    }
}
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">
                <?= $isEditing ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran' ?>
            </h2>
            <form
                action="<?= htmlspecialchars($isEditing ? base_url('master/tahun-ajaran/' . $editingYear['id'] . '/update') : base_url('master/tahun-ajaran'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="kode" class="block text-sm font-medium text-slate-600">Kode</label>
                    <input
                        type="text"
                        id="kode"
                        name="kode"
                        value="<?= htmlspecialchars((string) old('kode', $editingYear['kode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="2024/2025"
                    />
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-600">Nama</label>
                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= htmlspecialchars((string) old('nama', $editingYear['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        placeholder="Tahun Ajaran 2024/2025"
                    />
                </div>
                <div>
                    <label for="semester_aktif" class="block text-sm font-medium text-slate-600">Semester Aktif</label>
                    <select
                        id="semester_aktif"
                        name="semester_aktif"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php $semesterAktif = (int) old('semester_aktif', $editingYear['semester_aktif'] ?? 1); ?>
                        <option value="1" <?= $semesterAktif === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                        <option value="2" <?= $semesterAktif === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tanggal_mulai" class="block text-sm font-medium text-slate-600">Mulai</label>
                        <input
                            type="date"
                            id="tanggal_mulai"
                            name="tanggal_mulai"
                            value="<?= htmlspecialchars((string) old('tanggal_mulai', $editingYear['tanggal_mulai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="tanggal_selesai" class="block text-sm font-medium text-slate-600">Selesai</label>
                        <input
                            type="date"
                            id="tanggal_selesai"
                            name="tanggal_selesai"
                            value="<?= htmlspecialchars((string) old('tanggal_selesai', $editingYear['tanggal_selesai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-600">Status</label>
                    <select
                        id="status"
                        name="status"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php $status = old('status', $editingYear['status'] ?? 'nonaktif'); ?>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div>
                    <label for="digital_signature_enabled" class="block text-sm font-medium text-slate-600">TTD Digital</label>
                    <?php $digitalSignatureValue = (int) old('digital_signature_enabled', $editingYear['digital_signature_enabled'] ?? 0); ?>
                    <select
                        id="digital_signature_enabled"
                        name="digital_signature_enabled"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <option value="0" <?= $digitalSignatureValue === 0 ? 'selected' : '' ?>>Nonaktif</option>
                        <option value="1" <?= $digitalSignatureValue === 1 ? 'selected' : '' ?>>Aktif</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Aktifkan agar kepala sekolah dapat menyetujui dokumen secara digital.</p>
                </div>
                <div class="pt-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pengaturan Semester Aktif</p>
                    <p class="mt-1 text-xs text-slate-400">
                        Data berikut digunakan untuk raport pada tahun ajaran yang berstatus aktif.
                    </p>
                </div>
                <div>
                    <label for="kepala_sekolah_id" class="block text-sm font-medium text-slate-600">Nama Kepala Sekolah</label>
                    <select
                        id="kepala_sekolah_id"
                        name="kepala_sekolah_id"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    >
                        <?php $selectedHeadmaster = (int) old('kepala_sekolah_id', $editingYear['kepala_sekolah_id'] ?? 0); ?>
                        <option value="">Pilih Kepala Sekolah</option>
                        <?php foreach ($teacherOptions as $teacherId => $teacherName): ?>
                            <option value="<?= (int) $teacherId ?>" <?= $selectedHeadmaster === (int) $teacherId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tanggal_raport_tingkat_10_11" class="block text-sm font-medium text-slate-600">Tanggal Raport Tingkat 10-11</label>
                        <input
                            type="date"
                            id="tanggal_raport_tingkat_10_11"
                            name="tanggal_raport_tingkat_10_11"
                            value="<?= htmlspecialchars((string) old('tanggal_raport_tingkat_10_11', $editingYear['tanggal_raport_tingkat_10_11'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                    <div>
                        <label for="tanggal_raport_tingkat_12" class="block text-sm font-medium text-slate-600">Tanggal Raport Tingkat 12</label>
                        <input
                            type="date"
                            id="tanggal_raport_tingkat_12"
                            name="tanggal_raport_tingkat_12"
                            value="<?= htmlspecialchars((string) old('tanggal_raport_tingkat_12', $editingYear['tanggal_raport_tingkat_12'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        />
                    </div>
                </div>
                <div>
                    <label for="tanggal_raport_tengah_semester" class="block text-sm font-medium text-slate-600">Tanggal Raport Tengah Semester</label>
                    <input
                        type="date"
                        id="tanggal_raport_tengah_semester"
                        name="tanggal_raport_tengah_semester"
                        value="<?= htmlspecialchars((string) old('tanggal_raport_tengah_semester', $editingYear['tanggal_raport_tengah_semester'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Pengaturan SKL dan Transkrip</h3>
                    <p class="mt-1 text-xs text-slate-500">Dipakai otomatis sesuai tahun ajaran saat cetak SKL dan transkrip nilai.</p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="skl_nomor_surat" class="block text-sm font-medium text-slate-600">Nomor Surat SKL</label>
                            <input
                                type="text"
                                id="skl_nomor_surat"
                                name="skl_nomor_surat"
                                maxlength="190"
                                value="<?= htmlspecialchars((string) old('skl_nomor_surat', $editingYear['skl_nomor_surat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="421.5/SMK/2026"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="skl_tanggal_rapat_pleno" class="block text-sm font-medium text-slate-600">Tanggal Rapat Pleno</label>
                                <input
                                    type="date"
                                    id="skl_tanggal_rapat_pleno"
                                    name="skl_tanggal_rapat_pleno"
                                    value="<?= htmlspecialchars((string) old('skl_tanggal_rapat_pleno', $editingYear['skl_tanggal_rapat_pleno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                            <div>
                                <label for="skl_titimangsa" class="block text-sm font-medium text-slate-600">Titimangsa SKL</label>
                                <input
                                    type="date"
                                    id="skl_titimangsa"
                                    name="skl_titimangsa"
                                    value="<?= htmlspecialchars((string) old('skl_titimangsa', $editingYear['skl_titimangsa'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                />
                            </div>
                        </div>
                        <div>
                            <label for="transkrip_nomor_prefix" class="block text-sm font-medium text-slate-600">Custom Prefix Nomor Transkrip</label>
                            <input
                                type="text"
                                id="transkrip_nomor_prefix"
                                name="transkrip_nomor_prefix"
                                maxlength="80"
                                value="<?= htmlspecialchars((string) old('transkrip_nomor_prefix', $editingYear['transkrip_nomor_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                                placeholder="Kosongkan untuk format otomatis"
                            />
                            <p class="mt-1 text-xs text-slate-400">Default otomatis: Nomor Urut/Kode Sekolah/Kode Bidang/Bulan/Tahun.</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <?= $isEditing ? 'Simpan Perubahan' : 'Simpan' ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="<?= htmlspecialchars(base_url('master/tahun-ajaran'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            Batal
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-800">Daftar Tahun Ajaran</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Kode</th>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">Periode</th>
                            <th class="px-6 py-4">Semester</th>
                            <th class="px-6 py-4">Raport, SKL &amp; Transkrip</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($years as $year): ?>
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-700">
                                    <?= htmlspecialchars($year['kode'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?= htmlspecialchars($year['nama'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($year['tanggal_mulai'], ENT_QUOTES, 'UTF-8') ?>
                                    &ndash;
                                    <?= htmlspecialchars($year['tanggal_selesai'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= ((int) ($year['semester_aktif'] ?? 1)) === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)' ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php
                                    $headmasterId = (int) ($year['kepala_sekolah_id'] ?? 0);
                                    $headmasterName = $headmasterId > 0 ? ($teachersById[$headmasterId] ?? '-') : '-';
                                    ?>
                                    <p class="text-xs text-slate-400"><span class="font-semibold text-slate-500">Kepala Sekolah:</span> <?= htmlspecialchars($headmasterName ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        <span class="font-semibold text-slate-500">Raport 10-11:</span>
                                        <?= htmlspecialchars(format_school_year_date($year['tanggal_raport_tingkat_10_11'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        <span class="font-semibold text-slate-500">Raport 12:</span>
                                        <?= htmlspecialchars(format_school_year_date($year['tanggal_raport_tingkat_12'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        <span class="font-semibold text-slate-500">Tengah Semester:</span>
                                        <?= htmlspecialchars(format_school_year_date($year['tanggal_raport_tengah_semester'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <div class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs text-slate-400">
                                            <span class="font-semibold text-slate-500">Nomor SKL:</span>
                                            <?= htmlspecialchars((string) ($year['skl_nomor_surat'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            <span class="font-semibold text-slate-500">Rapat Pleno:</span>
                                            <?= htmlspecialchars(format_school_year_date($year['skl_tanggal_rapat_pleno'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            <span class="font-semibold text-slate-500">Titimangsa SKL:</span>
                                            <?= htmlspecialchars(format_school_year_date($year['skl_titimangsa'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            <span class="font-semibold text-slate-500">Prefix Transkrip:</span>
                                            <?php
                                            $transcriptPrefix = trim((string) ($year['transkrip_nomor_prefix'] ?? ''));
                                            ?>
                                            <?= htmlspecialchars($transcriptPrefix !== '' ? $transcriptPrefix : 'Otomatis', ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>
                                    <?php
                                        $digitalSignatureEnabled = (int) ($year['digital_signature_enabled'] ?? 0) === 1;
                                        $digitalSignatureEnabledAt = $year['digital_signature_enabled_at'] ?? null;
                                        $digitalSignatureTimestamp = $digitalSignatureEnabledAt !== null ? strtotime($digitalSignatureEnabledAt) : false;
                                        $digitalSignatureLabel = $digitalSignatureTimestamp ? date('d/m/Y H:i', $digitalSignatureTimestamp) : null;
                                    ?>
                                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p class="text-xs font-semibold <?= $digitalSignatureEnabled ? 'text-emerald-600' : 'text-slate-500' ?>">
                                            TTD Digital: <?= $digitalSignatureEnabled ? 'Aktif' : 'Nonaktif' ?>
                                        </p>
                                        <?php if ($digitalSignatureEnabled && $digitalSignatureLabel !== null): ?>
                                            <p class="mt-1 text-xs text-slate-400">Diaktifkan <?= htmlspecialchars($digitalSignatureLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php else: ?>
                                            <p class="mt-1 text-xs text-slate-400">Belum diaktifkan untuk tahun ajaran ini.</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium <?= ($year['status'] ?? '') === 'aktif' ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                                        <?= htmlspecialchars(strtoupper($year['status'] ?? 'nonaktif'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a
                                            href="<?= htmlspecialchars(base_url('master/tahun-ajaran?edit=' . urlencode((string) $year['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                            class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                        >
                                            Edit
                                        </a>
                                        <form action="<?= htmlspecialchars(base_url('master/tahun-ajaran/' . $year['id'] . '/delete'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Hapus tahun ajaran ini?');">
                                            <?= csrf_field() ?>
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($years)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-400">Belum ada data tahun ajaran.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
