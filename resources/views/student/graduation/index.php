<?php
    $student = $student ?? null;
    $classLevel = (int) ($classLevel ?? 0);
    $signatureRecord = $signatureRecord ?? null;
    $payload = $payload ?? [];
    $verificationUrl = $verificationUrl ?? null;
    $eligibility = isset($eligibility) && is_array($eligibility) ? $eligibility : null;

    $status = $signatureRecord['status'] ?? null;
    $canPrint = $eligibility !== null ? (bool) ($eligibility['can_print'] ?? false) : $status === 'approved';
    $printUrl = $canPrint ? base_url('kelulusan/skl/' . urlencode((string) ($signatureRecord['id'] ?? 0)) . '/cetak') : null;
    $subjectsCount = isset($payload['subjects']) && is_array($payload['subjects']) ? count($payload['subjects']) : 0;
    $averageScore = $payload['average'] ?? null;
?>

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Informasi Kelulusan</h2>
        <p class="mt-1 text-sm text-slate-500">Cek status SKL dan cetak jika sudah disetujui kepala sekolah.</p>
    </div>

    <?php if ($classLevel !== 12): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800">
            Menu ini hanya tersedia untuk siswa tingkat 12.
        </div>
    <?php else: ?>
        <?php if ($signatureRecord === null): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-600">SKL Anda belum diajukan. Hubungi wali kelas untuk pengajuan TTD digital.</p>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">
                            <?= htmlspecialchars($student['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            <?= student_status_badge($student, 'ml-1 align-middle') ?>
                            <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                        </p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars(($student['nisn'] ?? '-') . ' · ' . ($student['kelas_nama'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php
                        $statusClass = 'border-slate-200 bg-slate-50 text-slate-600';
                        $statusLabel = 'Belum diajukan';
                        if ($status === 'approved') {
                            $statusClass = 'border-emerald-200 bg-emerald-50 text-emerald-600';
                            $statusLabel = 'Disetujui';
                        } elseif ($status === 'pending') {
                            $statusClass = 'border-amber-200 bg-amber-50 text-amber-700';
                            $statusLabel = 'Menunggu persetujuan';
                        } elseif ($status === 'revoked') {
                            $statusClass = 'border-rose-200 bg-rose-50 text-rose-600';
                            $statusLabel = 'Dicabut';
                        }
                    ?>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs text-slate-500">Mata pelajaran</p>
                        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars((string) $subjectsCount, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs text-slate-500">Rata-rata</p>
                        <p class="text-lg font-semibold text-slate-800">
                            <?= $averageScore !== null ? htmlspecialchars((string) $averageScore, ENT_QUOTES, 'UTF-8') : '-' ?>
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs text-slate-500">Verifikasi</p>
                        <?php if ($verificationUrl !== null && $status === 'approved'): ?>
                            <a href="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="text-sm font-semibold text-indigo-600 hover:underline">
                                Lihat tautan
                            </a>
                        <?php else: ?>
                            <span class="text-sm text-slate-600">Belum tersedia</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">SKL dapat dicetak setelah disetujui kepala sekolah.</p>
                    <?php if ($printUrl !== null): ?>
                        <a
                            href="<?= htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') ?>"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        >
                            <i class="ri-printer-line text-base"></i>
                            Cetak SKL
                        </a>
                    <?php else: ?>
                        <span class="text-xs text-slate-400">Menunggu persetujuan kepala sekolah.</span>
                    <?php endif; ?>
                </div>

                <?php if ($eligibility !== null && !$canPrint): ?>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <?php
                            $messages = [];
                            foreach (($eligibility['criteria'] ?? []) as $criterion) {
                                if (!is_array($criterion) || (bool) ($criterion['passed'] ?? false)) {
                                    continue;
                                }
                                $message = trim((string) ($criterion['message'] ?? ''));
                                if ($message !== '') {
                                    $messages[] = $message;
                                }
                            }
                            echo htmlspecialchars(implode(' ', array_unique($messages)), ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
