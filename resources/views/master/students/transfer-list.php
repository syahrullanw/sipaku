<?php
    $students = is_array($students ?? null) ? $students : [];
    $keyword = trim((string) ($keyword ?? ''));
    $canManageStudentMaster = (bool) ($canManageStudentMaster ?? false);
    $canEditTransferStudents = (bool) ($canEditTransferStudents ?? false);
    $formatDate = static function (?string $date): string {
        if ($date === null || $date === '') {
            return '-';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d M Y', $timestamp);
    };
    $statusDapodikOptions = [
        'aktif' => 'Aktif',
        'belum_masuk' => 'Belum Masuk Dapodik',
        'mutasi' => 'Mutasi',
        'pindah' => 'Pindah',
        'residu' => 'Residu',
    ];
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Master Siswa</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">Daftar Siswa Pindahan</h2>
            <p class="mt-1 text-sm text-slate-500">Total siswa pindahan: <span class="font-semibold text-slate-700"><?= count($students) ?></span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars(base_url('master/siswa/pindahan'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <i class="ri-user-add-line text-base"></i>
                <span>Input Pindahan</span>
            </a>
            <?php if ($canManageStudentMaster): ?>
                <a href="<?= htmlspecialchars(base_url('master/siswa'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    <i class="ri-list-check text-base"></i>
                    <span>Daftar Siswa</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <form method="get" action="<?= htmlspecialchars(base_url('master/siswa/pindahan/daftar'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <i class="ri-search-line pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nama, NIPD, NISN, kelas, atau sekolah asal..." class="w-full rounded-lg border border-slate-200 px-3 py-2 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    <i class="ri-search-2-line text-base"></i>
                    <span>Cari</span>
                </button>
                <?php if ($keyword !== ''): ?>
                    <a href="<?= htmlspecialchars(base_url('master/siswa/pindahan/daftar'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                        <i class="ri-close-circle-line text-base"></i>
                        <span>Reset</span>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-4">Siswa</th>
                        <th class="px-6 py-4">Sekolah Asal</th>
                        <th class="px-6 py-4">Kelas Tujuan</th>
                        <th class="px-6 py-4">Status Dapodik</th>
                        <th class="px-6 py-4">Tanggal Input</th>
                        <?php if ($canEditTransferStudents): ?>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="<?= $canEditTransferStudents ? 6 : 5 ?>" class="px-6 py-8 text-center text-sm text-slate-400">
                                <?= $keyword !== '' ? 'Tidak ada siswa pindahan yang cocok dengan pencarian.' : 'Belum ada siswa pindahan yang diinputkan.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                                $dapodikStatus = (string) ($student['status_dapodik'] ?? '');
                                $dapodikLabel = $statusDapodikOptions[$dapodikStatus] ?? ucfirst($dapodikStatus ?: '-');
                                $dapodikStyle = match ($dapodikStatus) {
                                    'aktif' => 'bg-emerald-100 text-emerald-700',
                                    'belum_masuk' => 'bg-amber-100 text-amber-700',
                                    'mutasi', 'pindah' => 'bg-amber-100 text-amber-700',
                                    'residu' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                $className = trim((string) ($student['kelas_nama'] ?? ''));
                                $yearName = trim((string) ($student['tahun_ajaran_nama'] ?? ''));
                            ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800">
                                        <?= htmlspecialchars((string) ($student['nama'] ?? 'Tanpa Nama'), ENT_QUOTES, 'UTF-8') ?>
                                        <?= student_status_badge($student, 'ml-1 align-middle') ?>
                                        <?= student_dapodik_badge($student, 'ml-1 align-middle') ?>
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        NIPD: <?= htmlspecialchars((string) ($student['nipd'] ?? '-'), ENT_QUOTES, 'UTF-8') ?> /
                                        NISN: <?= htmlspecialchars((string) ($student['nisn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <?= htmlspecialchars((string) ($student['sekolah_asal'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <p><?= htmlspecialchars($className !== '' ? $className : 'Belum ditempatkan', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($yearName !== '' ? $yearName : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $dapodikStyle ?>">
                                        <?= htmlspecialchars($dapodikLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?= htmlspecialchars($formatDate((string) ($student['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <?php if ($canEditTransferStudents): ?>
                                    <td class="px-6 py-4 text-right">
                                        <a href="<?= htmlspecialchars(base_url('master/siswa/' . (int) ($student['id'] ?? 0) . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500">
                                            <i class="ri-edit-2-line text-sm"></i>
                                            <span>Edit</span>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
