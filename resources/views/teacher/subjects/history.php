<?php
    $historyYears = isset($historyYears) && is_array($historyYears) ? $historyYears : [];
    $selectedYearId = isset($selectedYearId) ? (int) $selectedYearId : null;
    $selectedYear = isset($selectedYear) && is_array($selectedYear) ? $selectedYear : null;
    $assignments = isset($assignments) && is_array($assignments) ? $assignments : [];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Riwayat Legger Mengajar</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-3xl">
                Kumpulan legger dari mata pelajaran yang pernah Anda ampu pada semester sebelumnya.
                Gunakan riwayat ini untuk meninjau kembali nilai pengetahuan dan keterampilan yang telah disimpan.
            </p>
        </div>
        <a
            href="<?= htmlspecialchars(base_url('guru/nilai'), ENT_QUOTES, 'UTF-8') ?>"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100"
        >
            <i class="ri-arrow-go-back-line text-sm"></i> Kembali ke daftar mapel
        </a>
    </div>

    <?php if (empty($historyYears)): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
            Belum ada legger yang tersimpan pada semester sebelumnya. Nilai yang diinput akan muncul di sini setelah periode berganti.
        </div>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Pilih Tahun Ajaran</h3>
                    <p class="text-sm text-slate-500">
                        Riwayat tersusun berdasarkan tahun ajaran dan semester. Pilih periode untuk melihat daftar mapel yang pernah diajar.
                    </p>
                </div>
                <form method="get" class="flex items-center gap-3">
                    <select
                        name="tahun_ajaran_id"
                        onchange="this.form.submit()"
                        class="block w-72 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                    >
                        <?php foreach ($historyYears as $year): ?>
                            <?php $yearId = (int) ($year['year_id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $yearId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedYearId === $yearId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year['label'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </header>

            <?php if (empty($assignments)): ?>
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                    Belum ada legger yang tersimpan pada periode ini.
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold text-slate-600">
                        Periode terpilih:
                        <span class="text-indigo-600">
                            <?= htmlspecialchars($selectedYear['label'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 w-14">No</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[220px]">Mata Pelajaran</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 min-w-[160px]">Kelompok</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Pengetahuan (siswa)</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Keterampilan (siswa)</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 w-40">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <?php foreach ($assignments as $index => $assignment): ?>
                                    <?php
                                        $assignmentId = (int) ($assignment['id'] ?? 0);
                                        $knowledgeTotal = (int) ($assignment['knowledge_total'] ?? 0);
                                        $skillTotal = (int) ($assignment['skill_total'] ?? 0);
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-600"><?= $index + 1 ?></td>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-800">
                                                <?= htmlspecialchars($assignment['subject_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Kode: <?= htmlspecialchars($assignment['subject_code'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <?= htmlspecialchars($assignment['subject_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <?= $knowledgeTotal > 0 ? $knowledgeTotal . ' siswa' : '-' ?>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <?= $skillTotal > 0 ? $skillTotal . ' siswa' : '-' ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a
                                                href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/legger'), ENT_QUOTES, 'UTF-8') ?>"
                                                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-600 hover:border-indigo-300 hover:text-indigo-600"
                                            >
                                                <i class="ri-table-line text-sm"></i> Buka Legger
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

