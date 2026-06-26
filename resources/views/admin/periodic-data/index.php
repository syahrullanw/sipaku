<?php
    $activeYearId = (int) ($activeYear['id'] ?? 0);
    $activeSemester = (int) ($activeYear['semester_aktif'] ?? 1);
    $activeYearLabel = null;
    if ($activeYearId > 0) {
        $activeYearLabel = sprintf(
            '%s - %s',
            $activeYear['nama'] ?? 'Tahun Ajaran',
            $activeSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }

    $sourceYearName = null;
    $sourceSemester = (int) ($sourceYear['semester_aktif'] ?? 1);
    if (!empty($sourceYear)) {
        $sourceYearName = sprintf(
            '%s - %s',
            $sourceYear['nama'] ?? 'Tahun Ajaran',
            $sourceSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
        );
    }

    $canCopy = $activeYearId > 0 && !empty($sourceYear);
    $datasetLabels = $datasetLabels ?? [];
    $sourceCounts = $sourceCounts ?? [];
    $targetCounts = $targetCounts ?? [];
    $availableSources = $availableSources ?? [];
    $copyReport = is_array($copyReport ?? null) ? $copyReport : [];
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Salin Data Periodik</h2>
            <p class="mt-2 text-sm text-slate-500">
                Duplikasikan konfigurasi akademik dari semester sebelumnya ke semester aktif. Nilai siswa tidak akan ikut tersalin.
            </p>

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <p class="font-semibold text-slate-700">Semester Aktif</p>
                <?php if ($activeYearLabel !== null): ?>
                    <p class="mt-1"><?= htmlspecialchars($activeYearLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="mt-1 text-rose-600">Belum ada tahun ajaran yang aktif. Aktifkan terlebih dahulu sebelum menyalin data.</p>
                <?php endif; ?>
            </div>

            <form
                action="<?= htmlspecialchars(base_url('admin/salin-data-periodik'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="tahun_ajaran_sumber" class="block text-sm font-medium text-slate-600">Salin dari Tahun Ajaran</label>
                    <select
                        id="tahun_ajaran_sumber"
                        name="tahun_ajaran_sumber"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                        <?= empty($availableSources) ? 'disabled' : '' ?>
                    >
                        <?php if (empty($availableSources)): ?>
                            <option value="">Tidak ada pilihan tersedia</option>
                        <?php else: ?>
                            <?php foreach ($availableSources as $option): ?>
                                <?php
                                    $optionId = (int) ($option['id'] ?? 0);
                                    $optionSemester = (int) ($option['semester_aktif'] ?? 1);
                                    $optionLabel = sprintf(
                                        '%s - %s',
                                        $option['nama'] ?? 'Tahun Ajaran',
                                        $optionSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)'
                                    );
                                ?>
                                <option value="<?= $optionId ?>" <?= $optionId === (int) ($sourceYearId ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="flex flex-col gap-2 text-xs text-slate-500">
                    <p>Data yang disalin mencakup kelas, penempatan siswa, mata pelajaran, guru pengampu, jadwal pelajaran, pengaturan penilaian, KD, ekstrakurikuler, serta jabatan akademik.</p>
                    <p class="text-amber-600">Nilai siswa, presensi, dan catatan wali kelas tidak ikut disalin.</p>
                </div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    <?= $canCopy ? '' : 'disabled' ?>
                >
                    <i class="ri-file-copy-line text-lg"></i>
                    Salin Sekarang
                </button>
            </form>

            <?php if (empty($availableSources)): ?>
                <p class="mt-4 text-xs text-slate-500">
                    Tidak ada tahun ajaran lain yang tersedia sebagai sumber. Tambahkan data tahun ajaran baru atau aktifkan semester lain terlebih dahulu.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-800">Ringkasan Data</h3>
                <?php if ($sourceYearName !== null): ?>
                    <p class="mt-1 text-sm text-slate-500">
                        Menyalin dari <span class="font-semibold text-slate-700"><?= htmlspecialchars($sourceYearName, ENT_QUOTES, 'UTF-8') ?></span>
                        ke <span class="font-semibold text-slate-700"><?= htmlspecialchars($activeYearLabel ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4">Jumlah di Sumber</th>
                            <th class="px-6 py-4">Jumlah di Semester Aktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($datasetLabels as $key => $label): ?>
                            <tr>
                                <td class="px-6 py-4 text-slate-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= number_format((int) ($sourceCounts[$key] ?? 0)) ?></td>
                                <td class="px-6 py-4 text-slate-600"><?= number_format((int) ($targetCounts[$key] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($datasetLabels)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-6 text-center text-sm text-slate-400">Tidak ada data periodik yang terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($copyReport)): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-emerald-700">Hasil Salin Terakhir</h3>
                <p class="mt-1 text-sm text-emerald-600">Detail jumlah entri yang berhasil disalin dan yang dilewati.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-emerald-100 text-sm">
                        <thead class="bg-emerald-100/60 text-left text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            <tr>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3">Disalin</th>
                                <th class="px-4 py-3">Dilewati</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-100 bg-white/80">
                            <?php foreach ($copyReport as $key => $stats): ?>
                                <tr>
                                    <td class="px-4 py-3 text-emerald-700"><?= htmlspecialchars($datasetLabels[$key] ?? ucfirst((string) $key), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3 text-emerald-600"><?= number_format((int) ($stats['copied'] ?? 0)) ?></td>
                                    <td class="px-4 py-3 text-emerald-600"><?= number_format((int) ($stats['skipped'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
