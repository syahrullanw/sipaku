<?php
    $students = is_array($students ?? null) ? $students : [];
    $studentsWithoutPhoto = is_array($studentsWithoutPhoto ?? null) ? $studentsWithoutPhoto : [];
    $returnTo = trim((string) ($returnTo ?? 'master/siswa/foto/massal'));
    $totalStudents = count($students);
    $missingPhotoTotal = count($studentsWithoutPhoto);
    $withPhotoTotal = max(0, $totalStudents - $missingPhotoTotal);
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Upload Foto Massal</h2>
                <p class="mt-1 text-sm text-slate-500">Gunakan ZIP berisi file JPG atau PNG. Nama file dicocokkan dengan NISN atau NIPD siswa.</p>
            </div>
            <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                <i class="ri-arrow-left-line"></i>
                Daftar Siswa
            </a>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Siswa</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800"><?= $totalStudents ?></p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Sudah Foto</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-800"><?= $withPhotoTotal ?></p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Belum Foto</p>
                <p class="mt-1 text-2xl font-semibold text-amber-800"><?= $missingPhotoTotal ?></p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-800">Unggah ZIP Foto</h3>
                <form action="<?= htmlspecialchars(base_url('master/siswa/foto/bulk'), ENT_QUOTES, 'UTF-8') ?>" method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <label for="foto_zip" class="block text-sm font-semibold text-slate-700">File ZIP</label>
                        <input
                            type="file"
                            id="foto_zip"
                            name="foto_zip"
                            accept=".zip"
                            required
                            class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-600 hover:file:bg-indigo-100"
                        />
                        <p class="mt-2 text-xs text-slate-500">Maksimal tiap foto 1 MB. File selain JPG/PNG akan dilewati.</p>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                        <i class="ri-upload-cloud-line"></i>
                        Upload Foto Massal
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Siswa Belum Foto</h3>
                    <p class="mt-1 text-sm text-slate-500">Nama file bisa menggunakan NISN atau NIPD pada daftar ini.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Siswa</th>
                                <th class="px-6 py-4">Kelas</th>
                                <th class="px-6 py-4">Nama File</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (empty($studentsWithoutPhoto)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-400">Semua siswa pada cakupan akses ini sudah memiliki foto.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($studentsWithoutPhoto, 0, 50) as $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $nisn = trim((string) ($student['nisn'] ?? ''));
                                        $nipd = trim((string) ($student['nipd'] ?? ''));
                                        $fileHint = $nisn !== '' ? $nisn . '.jpg' : ($nipd !== '' ? $nipd . '.jpg' : 'nisn-atau-nipd.jpg');
                                    ?>
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-6 py-4">
                                            <a href="<?= htmlspecialchars(base_url('master/siswa/' . $studentId . '/profil'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-slate-800 hover:text-indigo-600">
                                                <?= htmlspecialchars((string) ($student['nama'] ?? 'Tanpa Nama'), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            <p class="mt-1 text-xs text-slate-500">
                                                NIPD: <?= htmlspecialchars($nipd !== '' ? $nipd : '-', ENT_QUOTES, 'UTF-8') ?> /
                                                NISN: <?= htmlspecialchars($nisn !== '' ? $nisn : '-', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500">
                                            <?= htmlspecialchars((string) ($student['kelas_nama'] ?? 'Belum ditempatkan'), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <code class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?= htmlspecialchars($fileHint, ENT_QUOTES, 'UTF-8') ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($missingPhotoTotal > 50): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-center text-xs text-slate-400">Menampilkan 50 siswa pertama dari <?= $missingPhotoTotal ?> siswa tanpa foto.</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
