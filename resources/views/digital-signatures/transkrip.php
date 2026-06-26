<?php
$records = $records ?? [];
$allRecords = $allRecords ?? [];
$statusFilter = $statusFilter ?? 'pending';
$statusLabels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'revoked' => 'Dicabut',
    'all' => 'Semua',
];
$digitalSignatureEnabled = $digitalSignatureEnabled ?? false;
$activeYear = $activeYear ?? [];
$headmasterName = $headmasterName ?? '';
$activeYearName = $activeYear['nama'] ?? '-';
$disabledMessage = 'TTD digital belum diaktifkan oleh admin.';
$classSummaries = $classSummaries ?? [];
$statusSummary = $statusSummary ?? [];
?>
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Persetujuan Transkrip Nilai</h2>
                <p class="mt-1 text-sm text-slate-500">Tahun Ajaran: <?= htmlspecialchars($activeYearName, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-1 text-sm text-slate-500">
                    Kepala Sekolah:
                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($headmasterName !== '' ? $headmasterName : '-', ENT_QUOTES, 'UTF-8') ?></span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $digitalSignatureEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-500' ?>">
                    <?= $digitalSignatureEnabled ? 'TTD Digital Aktif' : 'TTD Digital Nonaktif' ?>
                </span>
            </div>
        </div>
        <?php if (!$digitalSignatureEnabled): ?>
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <p>TTD digital belum diaktifkan oleh admin. Hubungi admin untuk mengaktifkan fitur ini pada tahun ajaran aktif.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($statusSummary)): ?>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Menunggu</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700"><?= (int) ($statusSummary['pending'] ?? 0) ?></p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Disetujui</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700"><?= (int) ($statusSummary['approved'] ?? 0) ?></p>
                </div>
                <div class="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3">
                    <p class="text-xs font-semibold text-rose-600 uppercase tracking-wide">Dicabut</p>
                    <p class="mt-1 text-2xl font-bold text-rose-700"><?= (int) ($statusSummary['revoked'] ?? 0) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($classSummaries)): ?>
            <div class="mt-6 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ringkasan Kelas</h3>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($classSummaries as $summary): ?>
                        <?php
                            $classLabel = trim(($summary['class_level'] ?? '') . ' ' . ($summary['class_name'] ?? '-'));
                            $pendingCount = (int) ($summary['pending'] ?? 0);
                            $totalCount = (int) ($summary['total'] ?? 0);
                            $approvedCount = (int) ($summary['approved'] ?? 0);
                            $notApproved = max(0, $totalCount - $approvedCount);
                            $canApproveClass = $digitalSignatureEnabled && $pendingCount > 0;
                        ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($classLabel !== '' ? $classLabel : 'Kelas', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500">Total siswa: <?= htmlspecialchars((string) $totalCount, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-600">
                                    Disetujui <?= htmlspecialchars((string) $approvedCount, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600">
                                <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
                                    <p class="font-semibold text-amber-700">Menunggu</p>
                                    <p><?= htmlspecialchars((string) $pendingCount, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-white px-3 py-2">
                                    <p class="font-semibold text-slate-700">Belum Disetujui</p>
                                    <p><?= htmlspecialchars((string) $notApproved, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <?php $revokedCount = (int) ($summary['revoked'] ?? 0); ?>
                            <?php if ($revokedCount > 0): ?>
                                <p class="mt-2 text-xs text-rose-600">Dicabut: <?= htmlspecialchars((string) $revokedCount, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <form method="post" action="<?= htmlspecialchars(base_url('kepala-sekolah/ttd-digital/kelas/' . urlencode((string) ($summary['class_id'] ?? 0)) . '/approve'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_type" value="student_transcript">
                                <input type="hidden" name="redirect_to" value="kepala-sekolah/ttd-digital/transkrip?status=<?= htmlspecialchars(urlencode((string) $statusFilter), ENT_QUOTES, 'UTF-8') ?>">
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50 focus:outline-none focus:ring focus:ring-indigo-200 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400"
                                    <?= $canApproveClass ? '' : 'disabled' ?>
                                >
                                    <i class="ri-shield-check-line text-base"></i>
                                    Setujui Semua Pending
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-800">Daftar Pengajuan Transkrip</h3>
                <p class="text-sm text-slate-500">Transkrip nilai yang membutuhkan persetujuan kepala sekolah untuk menampilkan TTD digital.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php foreach ($statusLabels as $key => $label): ?>
                    <?php $isActive = $statusFilter === $key; ?>
                    <a
                        href="<?= htmlspecialchars(base_url('kepala-sekolah/ttd-digital/transkrip?status=' . urlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $isActive ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50' ?>"
                    >
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($records)): ?>
            <div class="px-6 py-10 text-center text-sm text-slate-400">
                Tidak ada pengajuan transkrip pada kategori ini.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Dokumen</th>
                            <th class="px-6 py-3">Siswa</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($records as $record): ?>
                            <?php
                                $payload = $record['payload_data'] ?? [];
                                $studentName = $record['student_name'] ?? '-';
                                $nipd = $record['student_nipd'] ?? '-';
                                $nisn = $record['student_nisn'] ?? '-';
                                $classLevel = $record['class_level'] ?? '';
                                $className = $record['class_name'] ?? '';
                                $status = (string) ($record['status'] ?? 'pending');
                                switch ($status) {
                                    case 'approved':
                                        $statusBadgeClass = 'border-emerald-200 bg-emerald-50 text-emerald-600';
                                        break;
                                    case 'revoked':
                                        $statusBadgeClass = 'border-rose-200 bg-rose-50 text-rose-600';
                                        break;
                                    default:
                                        $statusBadgeClass = 'border-amber-200 bg-amber-50 text-amber-600';
                                }
                                $updatedAt = $record['updated_at'] ?? null;
                                $updatedLabel = '-';
                                if (is_string($updatedAt) && $updatedAt !== '') {
                                    $timestamp = strtotime($updatedAt);
                                    $updatedLabel = $timestamp ? date('d/m/Y H:i', $timestamp) : $updatedAt;
                                }
                                $verificationUrl = $record['signature_token'] ? absolute_url('dokumen/validasi/' . $record['signature_token']) : null;
                                $transcriptScope = $payload['scope'] ?? null;
                            ?>
                            <tr>
                                <td class="px-6 py-4 align-top">
                                    <p class="font-semibold text-slate-700"><?= htmlspecialchars($record['document_title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">Terakhir diperbarui: <?= htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($transcriptScope !== null): ?>
                                        <p class="mt-1 text-xs text-slate-400">Cakupan: <?= htmlspecialchars((string) $transcriptScope, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($verificationUrl !== null && $status === 'approved'): ?>
                                        <p class="mt-2 text-xs text-indigo-600">
                                            <a href="<?= htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Lihat tautan verifikasi</a>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-slate-600">
                                    <p class="font-medium text-slate-700"><?= htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">NIPD / NISN: <?= htmlspecialchars($nipd, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($nisn, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">Kelas: <?= htmlspecialchars(trim($classLevel . ' ' . $className), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= $statusBadgeClass ?>">
                                        <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if ($record['approval_note'] ?? null): ?>
                                        <p class="mt-2 text-xs text-slate-400">Catatan: <?= htmlspecialchars($record['approval_note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 align-top text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if ($status === 'pending'): ?>
                                            <form action="<?= htmlspecialchars(base_url('kepala-sekolah/ttd-digital/' . $record['id'] . '/approve'), ENT_QUOTES, 'UTF-8') ?>" method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="kepala-sekolah/ttd-digital/transkrip?status=<?= htmlspecialchars(urlencode((string) $statusFilter), ENT_QUOTES, 'UTF-8') ?>">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-60"
                                                    <?= $digitalSignatureEnabled ? '' : 'disabled' ?>
                                                >
                                                    Setujui
                                                </button>
                                            </form>
                                        <?php elseif ($status === 'approved'): ?>
                                            <form action="<?= htmlspecialchars(base_url('kepala-sekolah/ttd-digital/' . $record['id'] . '/reset'), ENT_QUOTES, 'UTF-8') ?>" method="post" onsubmit="return confirm('Cabut persetujuan TTD digital untuk dokumen ini?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="redirect_to" value="kepala-sekolah/ttd-digital/transkrip?status=<?= htmlspecialchars(urlencode((string) $statusFilter), ENT_QUOTES, 'UTF-8') ?>">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 focus:outline-none focus:ring focus:ring-rose-200"
                                                >
                                                    Cabut Persetujuan
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Tidak ada aksi.</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$digitalSignatureEnabled && $status === 'pending'): ?>
                                        <p class="mt-2 text-xs text-slate-400"><?= htmlspecialchars($disabledMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
