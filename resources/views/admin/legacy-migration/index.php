<?php
    $defaultSqlPath = (string) ($defaultSqlPath ?? '');
    $sqlExists = (bool) ($sqlExists ?? false);
    $legacyTables = is_array($legacyTables ?? null) ? $legacyTables : [];
    $legacyCounts = is_array($legacyCounts ?? null) ? $legacyCounts : [];
    $datasetLabels = is_array($datasetLabels ?? null) ? $datasetLabels : [];
    $importReport = is_array($importReport ?? null) ? $importReport : null;
    $migrationReport = is_array($migrationReport ?? null) ? $migrationReport : null;

    $presentTables = [];
    foreach ($legacyTables as $table) {
        $presentTables[preg_replace('/^legacy_/', '', (string) $table)] = true;
    }

    $legacyTableLabels = [
        'm_jurusan' => 'Jurusan',
        'm_kelas' => 'Kelas',
        'm_mapel' => 'Mata Pelajaran',
        'm_guru' => 'Guru',
        'm_siswa' => 'Siswa',
        'm_ekstra' => 'Ekstrakurikuler',
        't_guru_mapel' => 'Guru Mapel',
        't_kelas_siswa' => 'Penempatan Siswa',
        't_kkm' => 'KKM',
        't_mapel_kd' => 'Kompetensi Dasar',
        't_nilai' => 'Nilai Akademik',
        't_nilai_ket' => 'Catatan Wali',
        't_nilai_sikap_sp' => 'Nilai Sikap Spiritual',
        't_nilai_sikap_so' => 'Nilai Sikap Sosial',
        't_nilai_absensi' => 'Rekap Absensi',
        't_nilai_ekstra' => 'Nilai Ekskul',
        't_prestasi' => 'Prestasi',
        't_raport_siswa' => 'Cetak Raport',
        't_walikelas' => 'Penugasan Wali',
        'nilaiprakerin' => 'Nilai Prakerin',
        'prakerin' => 'Penempatan Prakerin',
        'pengaturan' => 'Pengaturan Aplikasi',
        'tahun' => 'Tahun Ajaran',
        'ttd_digital_signatures' => 'TTD Digital',
    ];

    $legacyRows = [];
    foreach ($legacyCounts as $table => $count) {
        $legacyRows[] = [
            'table' => (string) $table,
            'label' => $legacyTableLabels[(string) $table] ?? ucwords(str_replace('_', ' ', (string) $table)),
            'count' => (int) $count,
            'exists' => isset($presentTables[(string) $table]),
        ];
    }

    foreach ($presentTables as $table => $_) {
        if (array_key_exists($table, $legacyCounts)) {
            continue;
        }

        $legacyRows[] = [
            'table' => (string) $table,
            'label' => $legacyTableLabels[(string) $table] ?? ucwords(str_replace('_', ' ', (string) $table)),
            'count' => 0,
            'exists' => true,
        ];
    }

    usort($legacyRows, static function (array $a, array $b): int {
        return strcmp($a['table'], $b['table']);
    });

    $hasLegacyData = !empty($legacyTables);
    if (!$hasLegacyData) {
        $hasLegacyData = array_reduce($legacyRows, static function (bool $carry, array $row): bool {
            return $carry || $row['count'] > 0 || $row['exists'];
        }, false);
    }

    $lastSqlPath = $importReport['path'] ?? ($sqlExists ? $defaultSqlPath : 'smkisnus_rapor.sql');
    $importResult = $importReport['result'] ?? null;
    $migrationDatasets = is_array($migrationReport['datasets'] ?? null) ? $migrationReport['datasets'] : [];
    $selectedDatasets = array_values(array_filter(array_map('strval', $migrationReport['selected'] ?? array_keys($datasetLabels))));
    if (empty($selectedDatasets)) {
        $selectedDatasets = array_keys($datasetLabels);
    }
    $migrationDryRun = (bool) ($migrationReport['dry_run'] ?? false);
?>

<div class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-4 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Import Data Legacy</h2>
            <p class="mt-2 text-sm text-slate-500">
                Unggah struktur dan data dari aplikasi raport lama melalui berkas SQL. Seluruh tabel akan dibuat ulang dengan awalan
                <span class="font-mono text-slate-700">legacy_</span> sehingga tidak mengganggu data <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?> aktif.
            </p>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <p class="font-semibold text-slate-700">Status Berkas</p>
                <p class="mt-1 text-slate-600">
                    <?= $sqlExists
                        ? 'Berkas default ditemukan dan siap diimport.'
                        : 'Berkas default belum ditemukan. Pastikan path di bawah sesuai.' ?>
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Path default: <span class="font-mono text-slate-600"><?= htmlspecialchars($defaultSqlPath, ENT_QUOTES, 'UTF-8') ?></span>
                </p>
            </div>

            <form
                action="<?= htmlspecialchars(base_url('admin/migrasi-rapor/import'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <div>
                    <label for="sql_path" class="block text-sm font-medium text-slate-600">Lokasi Berkas SQL</label>
                    <input
                        type="text"
                        id="sql_path"
                        name="sql_path"
                        value="<?= htmlspecialchars((string) $lastSqlPath, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="smkisnus_rapor.sql"
                        class="mt-2 block w-full rounded-lg border border-slate-200 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring"
                    />
                    <p class="mt-2 text-xs text-slate-500">
                        Kosongkan untuk memakai path default. Gunakan path absolut jika berkas disimpan di lokasi lain.
                    </p>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        name="force"
                        value="1"
                        class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span>
                        Timpa tabel legacy lama bila sudah ada. Seluruh tabel dengan awalan
                        <span class="font-mono text-slate-700">legacy_</span> akan dihapus sebelum import.
                    </span>
                </label>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    <i class="ri-upload-cloud-2-line text-lg"></i>
                    Import SQL Legacy
                </button>
            </form>

            <?php if ($hasLegacyData): ?>
                <form
                    action="<?= htmlspecialchars(base_url('admin/migrasi-rapor/hapus-legacy'), ENT_QUOTES, 'UTF-8') ?>"
                    method="post"
                    class="mt-4"
                    onsubmit="return confirm('Hapus seluruh tabel legacy? Tindakan ini tidak dapat dibatalkan.');"
                >
                    <?= csrf_field() ?>
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-100"
                    >
                        <i class="ri-delete-bin-line text-lg"></i>
                        Hapus Tabel Legacy
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Migrasi ke <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-2 text-sm text-slate-500">
                Setelah data legacy tersedia, jalankan migrasi agar data diubah ke format <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?>. Mulai dari master data guru, jurusan,
                hingga tahun ajaran. Dataset lain akan ditambahkan bertahap.
            </p>
            <form
                action="<?= htmlspecialchars(base_url('admin/migrasi-rapor/migrate'), ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                class="mt-6 space-y-4"
            >
                <?= csrf_field() ?>
                <fieldset class="space-y-3">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dataset Legacy</legend>
                    <?php foreach ($datasetLabels as $key => $label): ?>
                        <?php $checked = in_array((string) $key, $selectedDatasets, true); ?>
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600 hover:border-indigo-200">
                            <input
                                type="checkbox"
                                name="datasets[]"
                                value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $checked ? 'checked' : '' ?>
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="flex-1">
                                <span class="font-semibold text-slate-700"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ((string) $key === 'school_years'): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Mengisi tanggal raport, status aktif, dan referensi kepala sekolah.</span>
                                <?php elseif ((string) $key === 'teachers'): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Membuat data guru beserta status aktif/nonaktif.</span>
                                <?php elseif ((string) $key === 'majors'): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Menambahkan kode dan nama jurusan tanpa duplikasi.</span>
                                <?php elseif ((string) $key === 'classes'): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Membangun daftar kelas pada tahun ajaran aktif sesuai kombinasi tingkat dan jurusan.</span>
                                <?php elseif ((string) $key === 'subjects'): ?>
                                    <span class="mt-1 block text-xs text-slate-500">Mengimpor mata pelajaran utama ke tahun ajaran aktif dengan kelompok dan jurusan yang sesuai.</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($datasetLabels)): ?>
                        <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            Belum ada dataset legacy yang tersedia untuk dimigrasikan.
                        </p>
                    <?php endif; ?>
                </fieldset>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        name="dry_run"
                        value="1"
                        <?= $migrationDryRun ? 'checked' : '' ?>
                        class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span>
                        Jalankan simulasi saja (dry run). Sistem menghitung data yang akan diproses tanpa menyimpan perubahan.
                    </span>
                </label>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500"
                >
                    <i class="ri-refresh-line text-lg"></i>
                    Jalankan Migrasi
                </button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-8 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-base font-semibold text-slate-800">Status Tabel Legacy</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Data di bawah mengambil isi tabel berawalan <span class="font-mono text-slate-700">legacy_</span> pada database <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?>.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Tabel Legacy</th>
                            <th class="px-6 py-3">Jumlah Data</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($legacyRows)): ?>
                            <?php foreach ($legacyRows as $row): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-700"><?= htmlspecialchars($row['label'] ?? $row['table'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 text-xs font-mono text-slate-400"><?= htmlspecialchars((string) $row['table'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600"><?= number_format((int) $row['count']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($row['exists'])): ?>
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                <i class="ri-checkbox-circle-line text-base"></i>
                                                Tabel tersedia
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">
                                                <i class="ri-time-line text-base"></i>
                                                Belum diimport
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-6 text-center text-sm text-slate-400">
                                    Belum ada tabel legacy yang terdeteksi. Import berkas SQL terlebih dahulu.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($importReport)): ?>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-6 shadow-sm">
                <h3 class="text-base font-semibold text-indigo-700">Riwayat Import Terakhir</h3>
                <p class="mt-1 text-sm text-indigo-600">
                    Dilaksanakan pada <?= htmlspecialchars((string) ($importReport['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>.
                </p>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl bg-white/70 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Path Berkas</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-700 break-all"><?= htmlspecialchars((string) ($importReport['path'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="rounded-xl bg-white/70 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mode</dt>
                        <dd class="mt-1 text-sm text-slate-700">
                            <?= !empty($importReport['force']) ? 'Timpa tabel legacy lama' : 'Tanpa menghapus tabel legacy sebelumnya' ?>
                        </dd>
                    </div>
                </dl>
                <?php if (is_array($importResult)): ?>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-indigo-100 text-sm">
                            <thead class="bg-indigo-100/70 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                <tr>
                                    <th class="px-4 py-3">Total Statement</th>
                                    <th class="px-4 py-3">Dieksekusi</th>
                                    <th class="px-4 py-3">Dilewati</th>
                                    <th class="px-4 py-3">Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-indigo-100 bg-white/80">
                                <tr>
                                    <td class="px-4 py-3 text-indigo-700"><?= number_format((int) ($importResult['statements'] ?? 0)) ?></td>
                                    <td class="px-4 py-3 text-indigo-700"><?= number_format((int) ($importResult['executed'] ?? 0)) ?></td>
                                    <td class="px-4 py-3 text-indigo-700"><?= number_format((int) ($importResult['skipped'] ?? 0)) ?></td>
                                    <td class="px-4 py-3 text-indigo-700"><?= number_format(is_countable($importResult['errors'] ?? null) ? count($importResult['errors']) : 0) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($importResult['errors']) && is_array($importResult['errors'])): ?>
                        <div class="mt-4 rounded-xl border border-rose-200 bg-white/90 px-4 py-3 text-sm">
                            <p class="font-semibold text-rose-600">Rincian Error</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-rose-500">
                                <?php foreach ($importResult['errors'] as $message): ?>
                                    <li><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($migrationDatasets)): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 shadow-sm">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-emerald-700">Ringkasan Migrasi</h3>
                        <p class="text-sm text-emerald-600">
                            Dijalankan pada <?= htmlspecialchars((string) ($migrationReport['timestamp'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            <?= $migrationDryRun ? '(Simulasi)' : '' ?>
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/80 px-4 py-1 text-xs font-semibold text-emerald-700">
                        <i class="ri-database-2-line text-base"></i>
                        <?= $migrationDryRun ? 'Dry Run' : 'Commit ke Database' ?>
                    </span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-emerald-100 text-sm">
                        <thead class="bg-emerald-100/60 text-left text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            <tr>
                                <th class="px-4 py-3">Dataset</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-100 bg-white/80">
                            <?php foreach ($migrationDatasets as $key => $dataset): ?>
                                <?php
                                    $status = (string) ($dataset['status'] ?? 'skipped');
                                    $label = $datasetLabels[$key] ?? ucfirst((string) $key);
                                    $badgeClass = [
                                        'success' => 'bg-emerald-100 text-emerald-700',
                                        'error' => 'bg-rose-100 text-rose-700',
                                        'skipped' => 'bg-slate-100 text-slate-600',
                                    ][$status] ?? 'bg-slate-100 text-slate-600';
                                    $badgeIcon = [
                                        'success' => 'ri-checkbox-circle-line',
                                        'error' => 'ri-close-circle-line',
                                        'skipped' => 'ri-information-line',
                                    ][$status] ?? 'ri-information-line';
                                    $details = [];
                                    if (isset($dataset['imported'])) {
                                        $details[] = sprintf('Impor: %s', number_format((int) $dataset['imported']));
                                    }
                                    if (isset($dataset['skipped'])) {
                                        $details[] = sprintf('Lewat: %s', number_format((int) $dataset['skipped']));
                                    }
                                    if (!empty($dataset['target_year'])) {
                                        $details[] = sprintf('Tahun ajaran: %s', (string) $dataset['target_year']);
                                    }
                                    if (isset($dataset['message']) && $dataset['message'] !== '') {
                                        $details[] = (string) $dataset['message'];
                                    }
                                ?>
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-emerald-700"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                                            <i class="<?= $badgeIcon ?> text-base"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-emerald-700">
                                        <?php if (!empty($details)): ?>
                                            <ul class="list-disc space-y-1 pl-5">
                                                <?php foreach ($details as $detail): ?>
                                                    <li><?= htmlspecialchars((string) $detail, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-slate-500">Tidak ada rincian tambahan.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
