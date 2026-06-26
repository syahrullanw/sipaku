<?php
    $activeYear = $activeYear ?? null;
    $activityBundles = isset($activityBundles) && is_array($activityBundles) ? $activityBundles : [];
    $anchorActivityId = isset($anchorActivityId) ? (int) $anchorActivityId : null;
    $oldScores = old('scores', []);
    if (!is_array($oldScores)) {
        $oldScores = [];
    }

    $determinePredicate = static function (?float $score): string {
        if ($score === null) {
            return '';
        }

        if ($score >= 86) {
            return 'Amat Baik';
        }

        if ($score >= 70) {
            return 'Baik';
        }

        return 'Kurang';
    };

    $generateDescription = static function (string $predicate): string {
        return match ($predicate) {
            'Amat Baik' => 'Menunjukkan keaktifan tinggi, kemampuan teknis matang, dan kehadiran sangat konsisten.',
            'Baik' => 'Menunjukkan keterlibatan baik, kemampuan teknis cukup kuat, dan kehadiran stabil.',
            'Kurang' => 'Perlu meningkatkan keaktifan, kemampuan teknis, serta konsistensi kehadiran pada kegiatan.',
            default => '',
        };
    };

    $computeAverage = static function ($a, $b, $c): ?float {
        if ($a === '' || $b === '' || $c === '') {
            return null;
        }

        if (!is_numeric($a) || !is_numeric($b) || !is_numeric($c)) {
            return null;
        }

        $average = ((float) $a + (float) $b + (float) $c) / 3;

        return round($average, 2);
    };
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Input Nilai Ekstrakurikuler</h2>
            <p class="text-sm text-slate-500">
                Lengkapi nilai keaktifan, kemampuan teknis, dan kehadiran untuk setiap siswa pada ekskul binaan. Nilai akhir dan deskripsi dihasilkan otomatis.
            </p>
        </div>
        <?php if (!empty($activeYear)): ?>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                <p class="font-semibold text-indigo-800">Semester Aktif</p>
                <p><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($activeYear)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Belum ada tahun ajaran yang ditetapkan sebagai semester aktif. Hubungi admin untuk mengaktifkan semester terlebih dahulu.
        </div>
    <?php elseif (empty($activityBundles)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            Anda belum tercatat sebagai pembina ekskul pada semester aktif atau belum ada siswa yang terdaftar di ekskul binaan.
        </div>
    <?php else: ?>
        <?php foreach ($activityBundles as $bundle): ?>
            <?php
                $activity = isset($bundle['activity']) && is_array($bundle['activity']) ? $bundle['activity'] : [];
                $students = isset($bundle['students']) && is_array($bundle['students']) ? $bundle['students'] : [];
                $activityId = isset($activity['id']) ? (int) $activity['id'] : 0;
                $shouldHighlight = $anchorActivityId !== null && $anchorActivityId === $activityId;
            ?>

            <section
                id="ekskul-<?= htmlspecialchars((string) $activityId, ENT_QUOTES, 'UTF-8') ?>"
                class="rounded-2xl border <?= $shouldHighlight ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-slate-200' ?> bg-white p-6 shadow-sm space-y-5"
            >
                <header class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800">
                            <?= htmlspecialchars($activity['nama'] ?? 'Ekstrakurikuler', ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <?php if (!empty($activity['deskripsi'])): ?>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                <?= nl2br(htmlspecialchars($activity['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($activity['jadwal'])): ?>
                            <p class="mt-1 text-xs font-semibold text-indigo-600">
                                Jadwal: <?= htmlspecialchars($activity['jadwal'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <div class="rounded-lg bg-slate-100 px-3 py-2 font-medium text-slate-600">
                            <?= htmlspecialchars((string) ($activity['total_peserta'] ?? count($students)), ENT_QUOTES, 'UTF-8') ?> siswa
                        </div>
                    </div>
                </header>

                <?php if (empty($students)): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        Belum ada siswa yang terdaftar pada ekskul ini untuk semester aktif.
                    </div>
                <?php else: ?>
                    <form
                        action="<?= htmlspecialchars(base_url('guru/ekskul/nilai'), ENT_QUOTES, 'UTF-8') ?>"
                        method="post"
                        class="space-y-5"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="ekstrakurikuler_id" value="<?= htmlspecialchars((string) $activityId, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nama Siswa</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Keaktifan</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Kemampuan Teknis</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Kehadiran</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Nilai Akhir</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Predikat</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $studentId = (int) ($student['siswa_id'] ?? 0);
                                            $oldStudentScores = $oldScores[$studentId] ?? [];
                                            if (!is_array($oldStudentScores)) {
                                                $oldStudentScores = [];
                                            }

                                            $scoreKeaktifan = $oldStudentScores['nilai_keaktifan'] ?? ($student['nilai_keaktifan'] ?? '');
                                            $scoreKemampuan = $oldStudentScores['nilai_kemampuan_teknis'] ?? ($student['nilai_kemampuan_teknis'] ?? '');
                                            $scoreKehadiran = $oldStudentScores['nilai_kehadiran'] ?? ($student['nilai_kehadiran'] ?? '');

                                            $computedFinal = $computeAverage($scoreKeaktifan, $scoreKemampuan, $scoreKehadiran);
                                            $finalScore = $oldStudentScores === [] ? ($student['nilai_akhir'] ?? null) : $computedFinal;

                                            if ($finalScore !== null && $finalScore !== '') {
                                                $finalScore = is_numeric($finalScore) ? number_format((float) $finalScore, 2, '.', '') : $finalScore;
                                            }

                                            $predicate = '';
                                            $description = '';

                                            if ($oldStudentScores !== []) {
                                                $predicate = $determinePredicate($computedFinal);
                                                $description = $predicate !== '' ? $generateDescription($predicate) : '';
                                            } else {
                                                if (!empty($student['predikat'])) {
                                                    $predicate = $student['predikat'];
                                                }
                                                if (!empty($student['deskripsi'])) {
                                                    $description = $student['deskripsi'];
                                                } elseif ($predicate !== '') {
                                                    $description = $generateDescription($predicate);
                                                }
                                            }

                                            $studentInactive = student_is_inactive($student);
                                            $inactiveTitle = 'Siswa nonaktif; nilai ekskul tidak dapat diinput.';
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 text-slate-500">
                                                <?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-slate-700">
                                                    <?= htmlspecialchars($student['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                </div>
                                                <div class="text-xs text-slate-400">
                                                    <?= htmlspecialchars($student['siswa_nisn'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($student['kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_keaktifan]"
                                                    value="<?= htmlspecialchars((string) $scoreKeaktifan, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : 'required' ?>
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_kemampuan_teknis]"
                                                    value="<?= htmlspecialchars((string) $scoreKemampuan, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : 'required' ?>
                                                >
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    name="scores[<?= htmlspecialchars((string) $studentId, ENT_QUOTES, 'UTF-8') ?>][nilai_kehadiran]"
                                                    value="<?= htmlspecialchars((string) $scoreKehadiran, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                    <?= $studentInactive ? 'disabled title="' . htmlspecialchars($inactiveTitle, ENT_QUOTES, 'UTF-8') . '"' : 'required' ?>
                                                >
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($finalScore !== null && $finalScore !== ''): ?>
                                                    <span class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                        <?= htmlspecialchars((string) $finalScore, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($predicate !== ''): ?>
                                                    <span class="text-sm font-semibold"><?= htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?php if ($description !== ''): ?>
                                                    <span class="text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-slate-400">
                                Pastikan seluruh nilai berada pada rentang 0 - 100. Nilai akhir, predikat, dan deskripsi akan dihitung otomatis dengan bobot rata.
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
