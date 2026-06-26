<?php
    /** @var array<int, string> $periodOptions */
    /** @var array<int, string> $schoolYearOptions */
    /** @var array<int, string> $classOptions */
    /** @var array<int, array<string, mixed>> $registrants */

    $successMessage = session_flash('success');
    $errorMessage = session_flash('error');
    $periodId = $selectedPeriodId ?? 0;
    $schoolYearId = $selectedSchoolYearId ?? 0;
    $classId = $selectedClassId ?? 0;
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-700">
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100">Migrasi Calon Siswa PPDB</h1>
        <p class="text-sm text-slate-500 dark:text-gray-400">
            Pilih periode PPDB dan tentukan tahun ajaran/kelas tujuan untuk memindahkan calon siswa yang sudah dinyatakan diterima menjadi data siswa aktif.
        </p>
        <form action="<?= htmlspecialchars(base_url('ppdb/admin/migrasi'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-medium text-slate-600 dark:text-gray-200">
                Periode PPDB
                <select name="periode_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                    <option value="">Pilih periode</option>
                    <?php foreach ($periodOptions as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= (int) $periodId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-600 dark:text-gray-200">
                Tahun Ajaran Tujuan
                <select name="target_school_year_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                    <option value="">Pilih tahun ajaran</option>
                    <?php foreach ($schoolYearOptions as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= (int) $schoolYearId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-600 dark:text-gray-200">
                Kelas Tujuan (opsional)
                <select name="target_class_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                    <option value="">Tanpa kelas</option>
                    <?php foreach ($classOptions as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= (int) $classId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($periodId)): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-300">
            Pilih periode PPDB untuk menampilkan calon siswa yang siap dimigrasikan.
        </div>
    <?php elseif (empty($registrants)): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-300">
            Belum ada calon siswa dengan status diterima yang siap dimigrasikan atau seluruhnya sudah berpindah menjadi siswa aktif.
        </div>
    <?php else: ?>
        <form action="<?= htmlspecialchars(base_url('ppdb/admin/migrasi'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="periode_id" value="<?= (int) $periodId ?>" />
            <input type="hidden" name="target_school_year_id" value="<?= (int) $schoolYearId ?>" />
            <input type="hidden" name="target_class_id" value="<?= (int) $classId ?>" />

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Calon Siswa yang Diterima</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Centang calon siswa yang ingin dipindahkan lalu sesuaikan data jika diperlukan.</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-gray-200">
                        <input type="checkbox" id="selectAllMigrants" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Pilih semua
                    </label>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Migrasi</th>
                                <th class="px-4 py-3 text-left">Calon Siswa</th>
                                <th class="px-4 py-3 text-left">Kontak</th>
                                <th class="px-4 py-3 text-left">Data Wajib</th>
                                <th class="px-4 py-3 text-left">Orang Tua</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <?php foreach ($registrants as $registrant): ?>
                                <?php
                                    $id = (int) ($registrant['id'] ?? 0);
                                    $defaultNisn = $registrant['nisn'] ?? '';
                                    $defaultNik = $registrant['nik'] ?? '';
                                    $defaultBirthDate = $registrant['tanggal_lahir'] ?? '';
                                    $defaultBirthPlace = $registrant['tempat_lahir'] ?? '';
                                ?>
                                <tr class="align-top hover:bg-slate-50/80 dark:hover:bg-slate-700/40">
                                    <td class="px-4 py-4">
                                        <input type="checkbox" name="registrants[<?= $id ?>][migrate]" value="1" class="migrate-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($registrant['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-gray-400">
                                            <?= htmlspecialchars(($registrant['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-gray-400">
                                            Asal Sekolah: <?= htmlspecialchars($registrant['asal_sekolah'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="mt-2 text-xs text-slate-500 dark:text-gray-400">
                                            Alamat: <?= htmlspecialchars($registrant['alamat'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-gray-300">
                                        <?php if (!empty($registrant['telepon'])): ?>
                                            <div>HP: <?= htmlspecialchars($registrant['telepon'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($registrant['email'])): ?>
                                            <div>Email: <?= htmlspecialchars($registrant['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($registrant['telepon_wali'])): ?>
                                            <div>Wali: <?= htmlspecialchars($registrant['telepon_wali'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <div class="mt-3 space-y-2">
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Email</span>
                                                <input type="text" name="registrants[<?= $id ?>][email]" value="<?= htmlspecialchars($registrant['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Telepon</span>
                                                <input type="text" name="registrants[<?= $id ?>][telepon]" value="<?= htmlspecialchars($registrant['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-gray-300">
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <div class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">NIPD</span>
                                                <div class="mt-1 w-full rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-300">Otomatis saat migrasi</div>
                                                <p class="mt-1 text-[11px] text-slate-400">Format: tahun ajaran + kode 1 + nomor urut.</p>
                                            </div>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">NISN</span>
                                                <input type="text" name="registrants[<?= $id ?>][nisn]" value="<?= htmlspecialchars($defaultNisn, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">NIK</span>
                                                <input type="text" name="registrants[<?= $id ?>][nik]" value="<?= htmlspecialchars($defaultNik, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Tempat Lahir</span>
                                                <input type="text" name="registrants[<?= $id ?>][tempat_lahir]" value="<?= htmlspecialchars($defaultBirthPlace, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Tanggal Lahir</span>
                                                <input type="date" name="registrants[<?= $id ?>][tanggal_lahir]" value="<?= htmlspecialchars($defaultBirthDate, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block sm:col-span-2">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Alamat</span>
                                                <textarea name="registrants[<?= $id ?>][alamat]" rows="2" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100"><?= htmlspecialchars($registrant['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-gray-300">
                                        <div class="space-y-3">
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Ayah</span>
                                                <input type="text" name="registrants[<?= $id ?>][ayah_nama]" value="<?= htmlspecialchars($registrant['ayah_nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nama ayah/wali" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase tracking-wide text-slate-400">Ibu</span>
                                                <input type="text" name="registrants[<?= $id ?>][ibu_nama]" value="<?= htmlspecialchars($registrant['ibu_nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nama ibu/wali" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" />
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                    Pindahkan ke Data Siswa
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    (function () {
        const master = document.getElementById('selectAllMigrants');
        if (!master) {
            return;
        }
        master.addEventListener('change', function () {
            document.querySelectorAll('.migrate-checkbox').forEach(function (checkbox) {
                checkbox.checked = master.checked;
            });
        });
    })();
</script>
