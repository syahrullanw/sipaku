<?php
    $activeYear = $activeYear ?? null;
    $classes = $classes ?? [];
    $selectedClass = $selectedClass ?? null;
    $selectedClassId = (int) ($selectedClassId ?? 0);
    $subjects = $subjects ?? [];
    $selectedSubjectIds = $selectedSubjectIds ?? [];
    $students = $students ?? [];
    $signatureRecords = $signatureRecords ?? [];
    $signatureSummary = $signatureSummary ?? ['total' => 0, 'requested' => 0, 'pending' => 0, 'approved' => 0, 'revoked' => 0, 'not_requested' => 0];
    $graduationStatuses = $graduationStatuses ?? [];
    $digitalSignatureEnabled = $digitalSignatureEnabled ?? false;

    $activeYearName = $activeYear['nama'] ?? '-';
    $activeSemester = isset($activeYear['semester_aktif']) ? (int) $activeYear['semester_aktif'] : 1;
    $semesterLabel = $activeSemester === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)';
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Surat Keterangan Lulus</h2>
                <p class="mt-1 text-sm text-slate-500">Tahun ajaran aktif: <?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($semesterLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-sm text-slate-500">Menu ini hanya untuk kelas tingkat 12.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $digitalSignatureEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-amber-200 bg-amber-50 text-amber-700' ?>">
                    <?= $digitalSignatureEnabled ? 'TTD Digital Aktif' : 'TTD Digital Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (empty($classes)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Tidak ada kelas tingkat 12 pada tahun ajaran aktif. Pastikan data kelas sudah lengkap.
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <form method="get" class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="flex items-center gap-3">
                    <label for="kelas_id" class="text-sm font-medium text-slate-600">Pilih Kelas</label>
                    <select
                        id="kelas_id"
                        name="kelas_id"
                        class="w-64 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <?php foreach ($classes as $class): ?>
                            <?php $id = (int) ($class['id'] ?? 0); ?>
                            <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === $selectedClassId ? 'selected' : '' ?>>
                                <?= htmlspecialchars('Tingkat ' . ($class['tingkat'] ?? '-') . ' · ' . ($class['nama'] ?? 'Kelas') . ' (' . ($class['tahun_ajaran_nama'] ?? '-') . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                        Tampilkan
                    </button>
                </div>
            </form>

            <?php if ($selectedClass !== null): ?>
                <?php if (empty($subjects)): ?>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
                        Belum ada nilai mata pelajaran untuk kelas ini. Pastikan nilai sudah lengkap sebelum mengajukan SKL.
                    </div>
                <?php else: ?>
                    <form action="<?= htmlspecialchars(base_url('akademik/skl/ajukan'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="space-y-5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kelas_id" value="<?= htmlspecialchars((string) $selectedClassId, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-700">Mata Pelajaran SKL</h3>
                                    <p class="text-xs text-slate-500">SKL memakai seluruh mapel yang terhubung ke kelas.</p>
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <?php foreach ($subjects as $subject): ?>
                                        <?php
                                            $assignmentId = (int) ($subject['assignment_id'] ?? 0);
                                        ?>
                                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-indigo-200">
                                            <input type="hidden" name="subject_ids[]" value="<?= htmlspecialchars((string) $assignmentId, ENT_QUOTES, 'UTF-8') ?>">
                                            <input
                                                type="checkbox"
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600"
                                                checked
                                                disabled
                                            >
                                            <div>
                                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($subject['name'] ?? 'Mata Pelajaran', ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if (!empty($subject['code'])): ?>
                                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($subject['code'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-4">
                                <h3 class="text-sm font-semibold text-slate-700">Ringkasan</h3>
                                <dl class="mt-3 space-y-2 text-sm text-slate-600">
                                    <div class="flex items-center justify-between">
                                        <dt>Siswa</dt>
                                        <dd class="font-semibold"><?= htmlspecialchars((string) ($signatureSummary['total'] ?? 0), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt>Sudah diajukan</dt>
                                        <dd class="font-semibold text-amber-600"><?= htmlspecialchars((string) ($signatureSummary['requested'] ?? 0), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt>Disetujui</dt>
                                        <dd class="font-semibold text-emerald-600"><?= htmlspecialchars((string) ($signatureSummary['approved'] ?? 0), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt>Menunggu</dt>
                                        <dd class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($signatureSummary['pending'] ?? 0), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt>Belum diajukan</dt>
                                        <dd class="font-semibold text-slate-700"><?= htmlspecialchars((string) ($signatureSummary['not_requested'] ?? 0), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                </dl>
                                <?php if (!$digitalSignatureEnabled): ?>
                                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
                                        TTD digital belum aktif. Ajukan setelah admin mengaktifkan.
                                    </div>
                                <?php endif; ?>
                                <button
                                    type="submit"
                                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:cursor-not-allowed disabled:opacity-50"
                                    <?= $digitalSignatureEnabled ? '' : 'disabled' ?>
                                >
                                    <i class="ri-send-plane-line text-base"></i>
                                    Ajukan TTD Digital ke Kepala Sekolah
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">No</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Siswa</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Status</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $studentId = (int) ($student['id'] ?? 0);
                                            $signature = $signatureRecords[$studentId] ?? null;
                                            $status = $signature['status'] ?? 'belum';
                                            $statusLabel = 'Belum diajukan';
                                            $statusClass = 'border-slate-200 bg-slate-50 text-slate-600';
                                            if ($status === 'approved') {
                                                $statusLabel = 'Disetujui';
                                                $statusClass = 'border-emerald-200 bg-emerald-50 text-emerald-600';
                                            } elseif ($status === 'pending') {
                                                $statusLabel = 'Menunggu';
                                                $statusClass = 'border-amber-200 bg-amber-50 text-amber-700';
                                            } elseif ($status === 'revoked') {
                                                $statusLabel = 'Dicabut';
                                                $statusClass = 'border-rose-200 bg-rose-50 text-rose-600';
                                            }
                                            $printUrl = null;
                                            if ($signature !== null && ($signature['status'] ?? '') === 'approved') {
                                                $printUrl = base_url('kelulusan/skl/' . urlencode((string) $signature['id']) . '/cetak');
                                            }
                                            $graduationStatus = $graduationStatuses[$studentId]['status'] ?? null;
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-slate-800">
                                                    <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                                    <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                                    <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                                </p>
                                                <p class="text-xs text-slate-400"><?= htmlspecialchars(($student['nisn'] ?? '-') . ' · ' . ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php if ($graduationStatus !== null): ?>
                                                    <p class="text-xs text-emerald-600"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $graduationStatus)), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>">
                                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <?php if ($printUrl !== null): ?>
                                                    <a
                                                        href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank"
                                                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        <i class="ri-printer-line text-base"></i>
                                                        Cetak
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
