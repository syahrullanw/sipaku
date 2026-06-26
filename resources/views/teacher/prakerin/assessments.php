<?php
    $activeYear = $activeYear ?? null;
    $placeBundles = isset($placeBundles) && is_array($placeBundles) ? $placeBundles : [];
    $anchorPlaceId = isset($anchorPlaceId) ? (int) $anchorPlaceId : null;
    $oldScores = old('scores', []);
    if (!is_array($oldScores)) {
        $oldScores = [];
    }
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Input Nilai Prakerin</h2>
            <p class="text-sm text-slate-500">
                Lengkapi nilai keaktifan, jurnal, dan laporan untuk setiap siswa pada industri binaan Anda di semester aktif.
            </p>
        </div>
        <?php if (!empty($activeYear)): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700 shadow-sm">
                <p class="font-semibold text-emerald-800">Semester Aktif</p>
                <p><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($activeYear)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Belum ada tahun ajaran yang ditetapkan sebagai semester aktif. Hubungi admin untuk mengaktifkan semester terlebih dahulu.
        </div>
    <?php elseif (empty($placeBundles)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            Anda belum memiliki siswa yang ditempatkan pada industri binaan di semester aktif. Penilaian dapat dilakukan setelah wali kelas memetakan siswa.
        </div>
    <?php else: ?>
        <?php foreach ($placeBundles as $bundle): ?>
            <?php
                $place = isset($bundle['place']) && is_array($bundle['place']) ? $bundle['place'] : [];
                $students = isset($bundle['students']) && is_array($bundle['students']) ? $bundle['students'] : [];
                $assessments = isset($bundle['assessments']) && is_array($bundle['assessments']) ? $bundle['assessments'] : [];
                $placeId = isset($place['id']) ? (int) $place['id'] : 0;
                $shouldHighlight = $anchorPlaceId !== null && $anchorPlaceId === $placeId;
            ?>

            <section
                id="tempat-<?= htmlspecialchars((string) $placeId, ENT_QUOTES, 'UTF-8') ?>"
                class="rounded-2xl border <?= $shouldHighlight ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-slate-200' ?> bg-white p-6 shadow-sm space-y-5"
            >
                <header class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">
                            <?= htmlspecialchars($place['nama'] ?? 'Tempat Prakerin', ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <?php if (!empty($place['deskripsi'])): ?>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                <?= nl2br(htmlspecialchars($place['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <div class="rounded-lg bg-slate-100 px-3 py-2 font-medium text-slate-600">
                            <?= htmlspecialchars((string) count($students), ENT_QUOTES, 'UTF-8') ?> siswa
                        </div>
                    </div>
                </header>

                <?php if (empty($students)): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        Belum ada siswa yang ditempatkan pada industri ini untuk semester aktif.
                    </div>
                <?php else: ?>
                    <form
                        action="<?= htmlspecialchars(base_url('guru/prakerin/nilai'), ENT_QUOTES, 'UTF-8') ?>"
                        method="post"
                        class="space-y-5"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="tempat_prakerin_id" value="<?= htmlspecialchars((string) $placeId, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Keaktifan</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Jurnal</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Laporan</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Akhir</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Predikat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $studentId = isset($student['siswa_id']) ? (int) $student['siswa_id'] : 0;
                                            $existing = $assessments[$studentId] ?? [];
                                            $input = $oldScores[$studentId] ?? [];

                                            $scoreKeaktifan = $input['nilai_keaktifan'] ?? ($existing['nilai_keaktifan'] ?? '');
                                            $scoreJurnal = $input['nilai_jurnal'] ?? ($existing['nilai_jurnal'] ?? '');
                                            $scoreLaporan = $input['nilai_laporan'] ?? ($existing['nilai_laporan'] ?? '');

                                            $finalScore = '';
                                            $gradeLabel = '';

                                            $numericValues = array_filter([
                                                is_numeric($scoreKeaktifan) ? (float) $scoreKeaktifan : null,
                                                is_numeric($scoreJurnal) ? (float) $scoreJurnal : null,
                                                is_numeric($scoreLaporan) ? (float) $scoreLaporan : null,
                                            ], static fn ($value) => $value !== null);

                                            if (count($numericValues) === 3) {
                                                $finalScore = number_format(array_sum($numericValues) / 3, 2, '.', '');

                                                if ($finalScore >= 86) {
                                                    $gradeLabel = 'Amat Baik';
                                                } elseif ($finalScore >= 70) {
                                                    $gradeLabel = 'Baik';
                                                } else {
                                                    $gradeLabel = 'Kurang';
                                                }
                                            } elseif (!empty($existing)) {
                                                $finalScore = isset($existing['nilai_akhir']) ? number_format((float) $existing['nilai_akhir'], 2, '.', '') : '';
                                                $gradeLabel = $existing['predikat'] ?? '';
                                            }
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-4 py-3 font-medium text-slate-700">
                                                <?= htmlspecialchars($student['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                <?php if (!empty($student['nisn'])): ?>
                                                    <span class="block text-xs text-slate-400">NISN: <?= htmlspecialchars((string) $student['nisn'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($student['kelas_nama'])): ?>
                                                    <span class="block text-xs text-slate-400">Kelas: <?= htmlspecialchars(($student['kelas_tingkat'] ?? '-') . ' ' . ($student['kelas_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_keaktifan]"
                                                    value="<?= htmlspecialchars((string) $scoreKeaktifan, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_jurnal]"
                                                    value="<?= htmlspecialchars((string) $scoreJurnal, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_laporan]"
                                                    value="<?= htmlspecialchars((string) $scoreLaporan, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                >
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($finalScore !== ''): ?>
                                                    <span class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                        <?= htmlspecialchars($finalScore, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($gradeLabel !== ''): ?>
                                                    <span class="text-sm font-semibold"><?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between">
                            <p class="text-xs text-slate-400">
                                Pastikan seluruh nilai berada pada rentang 0 - 100. Nilai akhir dihitung otomatis sebagai rata-rata.
                            </p>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                                <i class="ri-save-3-line text-lg"></i>
                                Simpan Nilai
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
