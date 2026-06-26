<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">
                <?= htmlspecialchars($typeLabel ?? 'Nilai Sikap', ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="text-sm text-slate-500">
                Input nilai sikap berdasarkan indikator yang disiapkan admin. Pilih kelas yang Anda ampu sebagai wali kelas.
            </p>
        </div>
        <div class="inline-flex flex-wrap rounded-lg border border-slate-200 bg-white p-1 text-sm font-medium text-slate-600">
            <a
                href="<?= htmlspecialchars(base_url('walikelas/nilai-sikap/spiritual') . ($selectedClassId ? '?kelas_id=' . urlencode((string) $selectedClassId) : ''), ENT_QUOTES, 'UTF-8') ?>"
                class="px-3 py-1.5 rounded-md <?= ($type ?? '') === 'spiritual' ? 'bg-indigo-600 text-white shadow-sm' : 'hover:text-indigo-600' ?>"
            >
                Sikap Spiritual
            </a>
            <a
                href="<?= htmlspecialchars(base_url('walikelas/nilai-sikap/sosial') . ($selectedClassId ? '?kelas_id=' . urlencode((string) $selectedClassId) : ''), ENT_QUOTES, 'UTF-8') ?>"
                class="px-3 py-1.5 rounded-md <?= ($type ?? '') === 'sosial' ? 'bg-indigo-600 text-white shadow-sm' : 'hover:text-indigo-600' ?>"
            >
                Sikap Sosial
            </a>
        </div>
    </div>

    <?php if (empty($classes ?? [])): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Anda belum tercatat sebagai wali kelas pada data kelas manapun. Hubungi admin untuk menugaskan Anda sebagai wali kelas.
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <form method="get" class="flex flex-col gap-3 md:flex-row md:items-center">
                <label for="kelas_id" class="text-sm font-medium text-slate-600">Pilih Kelas</label>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <select
                        id="kelas_id"
                        name="kelas_id"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
                    >
                        <?php foreach ($classes as $class): ?>
                            <?php $id = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === (int) ($selectedClassId ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($class['nama'] ?? 'Kelas') . ' · ' . ($class['jurusan_nama'] ?? '-') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 sm:w-auto"
                    >
                        Tampilkan
                    </button>
                </div>
            </form>

            <?php if (!empty($isKurmerClass ?? false)): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-700">
                    Penilaian sikap tidak digunakan pada kelas Kurikulum Merdeka.
                </div>
            <?php elseif (empty($students ?? [])): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                    Belum ada siswa yang terdaftar pada kelas ini.
                </div>
            <?php elseif (empty($attitudeOptions ?? [])): ?>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
                    Data indikator sikap untuk jenis ini belum diinput oleh admin. Silakan minta admin menambahkan data sikap terlebih dahulu.
                </div>
            <?php else: ?>
                <form
                    action="<?= htmlspecialchars(base_url('walikelas/nilai-sikap/' . ($type ?? 'spiritual')), ENT_QUOTES, 'UTF-8') ?>"
                    method="post"
                    class="space-y-6"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) ($selectedClassId ?? 0), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Indikator Sikap</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php foreach ($students as $index => $student): ?>
                                    <?php
                                        $studentId = (int) ($student['id'] ?? 0);
                                        $score = $scores[$studentId] ?? null;
                                        $selaluValues = $oldSelalu[$studentId] ?? [
                                            $score['data_sikap_selalu_1_id'] ?? null,
                                            $score['data_sikap_selalu_2_id'] ?? null,
                                        ];

                                        if (!is_array($selaluValues)) {
                                            $selaluValues = [$selaluValues];
                                        }

                                        $selaluValues = array_values(array_map(static fn ($value) => (int) $value, $selaluValues));
                                        $selaluValues[0] = $selaluValues[0] ?? null;
                                        $selaluValues[1] = $selaluValues[1] ?? null;

                                        $meningkatValue = $oldMeningkat[$studentId] ?? ($score['data_sikap_meningkat_id'] ?? null);
                                        $meningkatValue = $meningkatValue !== null ? (int) $meningkatValue : null;

                                        $noteValue = $oldNotes[$studentId] ?? ($score['catatan'] ?? '');
                                        $studentInactive = student_is_inactive($student);
                                        $inactiveTitle = 'Siswa nonaktif; nilai sikap tidak dapat diinput.';
                                    ?>
                                    <tr class="border-b border-slate-100 last:border-0">
                                        <td class="px-4 py-4 text-sm font-medium text-slate-500 align-top"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-4 align-top">
                                            <p class="font-medium text-slate-800">
                                                <?= htmlspecialchars($student['nama'] ?? 'Siswa', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                            </p>
                                            <p class="text-xs text-slate-400">
                                                NISN: <?= htmlspecialchars($student['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </td>
                                        <td class="px-4 py-4 align-top">
                                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 xl:gap-4">
                                                <div class="space-y-1">
                                                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Selalu Dilakukan #1</span>
                                                    <select
                                                        name="selalu[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][0]"
                                                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    >
                                                        <option value="">-- Pilih indikator --</option>
                                                        <?php foreach ($attitudeOptions as $optionId => $optionName): ?>
                                                            <?php $optionIdInt = (int) $optionId; ?>
                                                            <option value="<?= htmlspecialchars((string) $optionIdInt, ENT_QUOTES, 'UTF-8') ?>" <?= $optionIdInt === ($selaluValues[0] ?? null) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="space-y-1">
                                                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Selalu Dilakukan #2</span>
                                                    <select
                                                        name="selalu[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][1]"
                                                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    >
                                                        <option value="">-- Pilih indikator --</option>
                                                        <?php foreach ($attitudeOptions as $optionId => $optionName): ?>
                                                            <?php $optionIdInt = (int) $optionId; ?>
                                                            <option value="<?= htmlspecialchars((string) $optionIdInt, ENT_QUOTES, 'UTF-8') ?>" <?= $optionIdInt === ($selaluValues[1] ?? null) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="space-y-1 sm:col-span-2 xl:col-span-1">
                                                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mulai Meningkat</span>
                                                    <select
                                                        name="meningkat[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]"
                                                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                        <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                                                    >
                                                        <option value="">-- Pilih indikator --</option>
                                                        <?php foreach ($attitudeOptions as $optionId => $optionName): ?>
                                                            <?php $optionIdInt = (int) $optionId; ?>
                                                            <option value="<?= htmlspecialchars((string) $optionIdInt, ENT_QUOTES, 'UTF-8') ?>" <?= $optionIdInt === $meningkatValue ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 align-top">
                                            <textarea name="notes[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>]" rows="3" class="min-h-[92px] w-full resize-none rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars((string) $noteValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">
                            Setiap siswa harus memiliki dua indikator yang selalu dilakukan dan satu indikator yang mulai meningkat.
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="btn-autofill"
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                            >
                                <i class="ri-sparkling-2-line text-lg"></i>
                                Isi Otomatis
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                                <i class="ri-save-3-line text-lg"></i>
                                Simpan Nilai Sikap
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-800">
                Referensi Indikator <?= htmlspecialchars($typeLabel ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Berikut daftar indikator sikap yang disediakan oleh admin. Pastikan memilih indikator yang paling sesuai dengan perkembangan siswa.
            </p>
            <?php if (empty($attitudeList ?? [])): ?>
                <p class="mt-4 text-sm text-slate-500">Belum ada indikator sikap untuk jenis ini.</p>
            <?php else: ?>
                <ul class="mt-4 grid gap-3 md:grid-cols-2">
                    <?php foreach ($attitudeList as $item): ?>
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">
                                <?= htmlspecialchars(($item['kode'] ?? '') !== '' ? ($item['kode'] . ' · ' . ($item['nama'] ?? '')) : ($item['nama'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <?php if (!empty($item['deskripsi'])): ?>
                                <p class="mt-1 text-xs text-slate-500">
                                    <?= htmlspecialchars($item['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('btn-autofill')?.addEventListener('click', function () {
    const rows = document.querySelectorAll('tbody tr');
    if (!rows.length) return;

    rows.forEach(function (row) {
        const selects = row.querySelectorAll('select');
        const textarea = row.querySelector('textarea');
        if (!selects.length) return;

        const isDisabled = selects[0].disabled;
        if (isDisabled) return;

        const optionValues = [];
        selects[0].querySelectorAll('option').forEach(function (opt) {
            const val = opt.value;
            if (val !== '') optionValues.push(val);
        });

        if (optionValues.length < 2) return;

        const shuffled = [...optionValues].sort(() => Math.random() - 0.5);
        const selalu1 = shuffled[0];
        const selalu2 = shuffled[1];
        let meningkat = shuffled[2] ?? shuffled[0];

        if (selects.length >= 1) selects[0].value = selalu1;
        if (selects.length >= 2) selects[1].value = selalu2;
        if (selects.length >= 3) selects[2].value = meningkat;
        if (textarea) textarea.value = '';
    });
});
</script>
