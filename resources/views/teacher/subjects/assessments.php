<?php
    $activeYear = $activeYear ?? null;
    $assignmentBundles = isset($assignmentBundles) && is_array($assignmentBundles) ? $assignmentBundles : [];
    $focusAssignment = isset($focusAssignment) ? (int) $focusAssignment : null;
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Input Nilai Pengetahuan &amp; Keterampilan</h2>
            <p class="text-sm text-slate-500">
                Pilih mata pelajaran yang Anda ampu untuk mengatur pengambilan nilai pengetahuan maupun keterampilan.
                Sesuaikan pengaturan KKM, bobot penilaian, dan akses nilai keterampilan sesuai kebutuhan.
            </p>
        </div>
        <div class="flex flex-col items-stretch gap-2 md:items-end">
            <?php if (!empty($activeYear)): ?>
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-700 shadow-sm">
                    <p class="font-semibold text-indigo-800">Semester Aktif</p>
                    <p><?= htmlspecialchars($activeYear['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>
            <a
                href="<?= htmlspecialchars(base_url('guru/nilai/riwayat'), ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-indigo-300 hover:text-indigo-600"
            >
                <i class="ri-time-line mr-2 text-base"></i>
                Lihat Riwayat Legger Mengajar
            </a>
        </div>
    </div>

    <?php if (empty($assignmentBundles)): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            Anda belum tercatat mengampu mata pelajaran pada semester aktif. Hubungi admin untuk memastikan penugasan guru pengampu sudah diperbarui.
        </div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($assignmentBundles as $bundle): ?>
                <?php
                    $assignment = isset($bundle['assignment']) && is_array($bundle['assignment']) ? $bundle['assignment'] : [];
                    $setting = isset($bundle['setting']) && is_array($bundle['setting']) ? $bundle['setting'] : [];
                    $assignmentId = isset($assignment['id']) ? (int) $assignment['id'] : 0;
                    $highlight = $focusAssignment !== null && $focusAssignment === $assignmentId;
                    $enableSkill = (int) ($setting['enable_keterampilan'] ?? 1) === 1;
                    $manualWeight = (int) ($setting['bobot_manual'] ?? 0) === 1;
                    $enableKkm = (int) ($setting['enable_kkm'] ?? 0) === 1;
                ?>
                <section
                    id="assignment-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>"
                    class="rounded-2xl border <?= $highlight ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-slate-200' ?> bg-white p-6 shadow-sm transition"
                >
                    <header class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">
                                <?= htmlspecialchars($assignment['mata_pelajaran_kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <h3 class="text-lg font-semibold text-slate-800">
                                <?= htmlspecialchars($assignment['mata_pelajaran_nama'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Tahun Ajaran:
                                <?= htmlspecialchars($assignment['mata_pelajaran_tahun_ajaran_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                &middot;
                                Jenis:
                                <?= htmlspecialchars($assignment['mata_pelajaran_jenis'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($assignment['mata_pelajaran_jurusan_nama'])): ?>
                                    &middot; <?= htmlspecialchars($assignment['mata_pelajaran_jurusan_nama'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($assignment['catatan'])): ?>
                                <p class="mt-2 text-sm text-slate-500 max-w-3xl">
                                    <?= nl2br(htmlspecialchars($assignment['catatan'], ENT_QUOTES, 'UTF-8')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col gap-2 md:items-end">
                            <a
                                href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/legger'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-indigo-300 hover:text-indigo-600"
                            >
                                <i class="ri-table-line mr-2 text-base"></i>
                                Legger Nilai
                            </a>
                            <a
                                href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/pengetahuan'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-100"
                            >
                                <i class="ri-lightbulb-flash-line mr-2 text-base"></i>
                                Input Nilai Pengetahuan
                            </a>
                            <a
                                href="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/keterampilan'), ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center justify-center rounded-lg border border-teal-200 px-4 py-2 text-sm font-semibold <?= $enableSkill ? 'bg-teal-50 text-teal-600 hover:bg-teal-100' : 'bg-slate-100 text-slate-400 cursor-not-allowed' ?>"
                                <?= $enableSkill ? '' : 'tabindex="-1" aria-disabled="true"' ?>
                            >
                                <i class="ri-brain-line mr-2 text-base"></i>
                                Input Nilai Keterampilan
                            </a>
                            <?php if (!$enableSkill): ?>
                                <p class="text-xs text-slate-400 max-w-xs text-right">
                                    Nilai keterampilan dinonaktifkan. Aktifkan kembali melalui pengaturan di bawah bila diperlukan.
                                </p>
                            <?php endif; ?>
                        </div>
                    </header>

                    <form
                        action="<?= htmlspecialchars(base_url('guru/nilai/' . $assignmentId . '/pengaturan'), ENT_QUOTES, 'UTF-8') ?>"
                        method="post"
                        class="mt-5 space-y-5"
                    >
                        <?= csrf_field() ?>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="enable_keterampilan"
                                    value="1"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                    <?= $enableSkill ? 'checked' : '' ?>
                                >
                                <span>
                                    <span class="font-semibold text-slate-700">Aktifkan Penilaian Keterampilan</span>
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Saat nonaktif, guru hanya menginput nilai pengetahuan. Nilai keterampilan tidak ditampilkan di rapor.
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="enable_kkm"
                                    value="1"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 toggle-kkm"
                                    data-target="#kkm-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $enableKkm ? 'checked' : '' ?>
                                >
                                <span>
                                    <span class="font-semibold text-slate-700">Gunakan KKM (Passing Grade)</span>
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Nilai di bawah KKM otomatis mendapat predikat Perlu Bimbingan. Rentang nilai lainnya mengikuti aturan standar.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div id="kkm-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" class="<?= $enableKkm ? '' : 'opacity-60 pointer-events-none' ?>">
                                <label for="kkm-input-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-between text-sm font-semibold text-slate-700">
                                    Nilai KKM
                                    <span class="text-xs font-medium text-slate-400">0 - 100</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    id="kkm-input-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>"
                                    name="nilai_kkm"
                                    value="<?= htmlspecialchars((string) ($setting['nilai_kkm'] ?? 75), ENT_QUOTES, 'UTF-8') ?>"
                                    class="mt-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                >
                            </div>

                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    name="bobot_manual"
                                    value="1"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 toggle-weight"
                                    data-target="#weight-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $manualWeight ? 'checked' : '' ?>
                                >
                                <span>
                                    <span class="font-semibold text-slate-700">Atur Bobot Secara Manual</span>
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Default bobot: KD 25%, UTS 35%, UAS 40%. Aktifkan untuk menyesuaikan bobot penilaian (total harus 100%).
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div
                            id="weight-<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 <?= $manualWeight ? '' : 'opacity-60 pointer-events-none' ?>"
                        >
                            <p class="text-sm font-semibold text-slate-700">Bobot Penilaian Manual</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <?php
                                    $weights = [
                                        ['label' => 'Bobot Nilai KD (%)', 'name' => 'bobot_kd', 'value' => $setting['bobot_kd'] ?? 25],
                                        ['label' => 'Bobot Nilai UTS (%)', 'name' => 'bobot_uts', 'value' => $setting['bobot_uts'] ?? 35],
                                        ['label' => 'Bobot Nilai UAS (%)', 'name' => 'bobot_uas', 'value' => $setting['bobot_uas'] ?? 40],
                                    ];
                                ?>
                                <?php foreach ($weights as $weight): ?>
                                    <div>
                                        <label class="text-xs font-semibold text-slate-500">
                                            <?= htmlspecialchars($weight['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                        min="0"
                                            name="<?= htmlspecialchars($weight['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            value="<?= htmlspecialchars((string) $weight['value'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                        >
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-3 text-xs text-slate-400">
                                Pastikan total bobot berjumlah 100%.
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                                <i class="ri-save-3-line text-base"></i>
                                Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleGroups = document.querySelectorAll('.toggle-kkm, .toggle-weight');
        toggleGroups.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const targetSelector = checkbox.getAttribute('data-target');
                if (!targetSelector) {
                    return;
                }
                const target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }
                if (checkbox.checked) {
                    target.classList.remove('opacity-60', 'pointer-events-none');
                } else {
                    target.classList.add('opacity-60', 'pointer-events-none');
                }
            });
        });
    });
</script>
