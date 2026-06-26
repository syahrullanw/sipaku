<?php
/** @var array<int, array<string, mixed>> $loans */
/** @var array<int, array<string, mixed>> $installments */
/** @var array<int, array<string, mixed>> $activities */
/** @var array<string, array<string, mixed>> $activityReports */
/** @var array<int, array<string, mixed>> $unexpectedExpenses */
/** @var array<string, array<string, mixed>> $unexpectedReports */
/** @var array<int, array<string, mixed>> $honors */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>

<div class="space-y-8">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Kasbon / Pinjaman</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Pantau status kasbon dan jadwal cicilan Anda.</p>
                    </div>
                    <a
                        href="<?= htmlspecialchars(base_url('keuangan/guru/kasbon'), ENT_QUOTES, 'UTF-8') ?>"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                    >
                        <span class="ri-add-circle-line text-base"></span>
                        Pengajuan Kasbon
                    </a>
                </div>
            </div>
            <div class="px-6 py-4 space-y-4">
                <?php if (empty($loans)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada kasbon terdaftar.</p>
                <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        $loanId = (int) ($loan['id'] ?? 0);
                        $loanInstallments = array_filter(
                            $installments,
                            static fn (array $item): bool => (int) ($item['kasbon_id'] ?? 0) === $loanId
                        );
                        $status = (string) ($loan['status'] ?? '');
                        $statusClass = match ($status) {
                            'disetujui' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                            'lunas' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                            'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                            default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                        };
                        ?>
                        <div class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700/70">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($loan['kode'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Pengajuan: <?= htmlspecialchars(date('d M Y', strtotime((string) ($loan['tanggal_pengajuan'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="mt-3 grid gap-3 text-sm md:grid-cols-3">
                                <div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Nominal Diminta</p>
                                    <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($loan['nominal_diminta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Saldo Terhutang</p>
                                    <p class="font-semibold text-amber-600 dark:text-amber-300"><?= htmlspecialchars($formatCurrency((float) ($loan['saldo_terhutang'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Tenor</p>
                                    <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($loan['tenor_bulan'] !== null ? $loan['tenor_bulan'] . ' bulan' : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                            <?php if (!empty($loanInstallments)): ?>
                                <div class="mt-4 rounded-lg border border-slate-200/70 bg-slate-50 p-3 dark:border-slate-700/70 dark:bg-slate-800/60">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Jadwal Cicilan</p>
                                    <ul class="mt-2 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                                        <?php foreach ($loanInstallments as $installment): ?>
                                            <li class="flex items-center justify-between">
                                                <span><?= htmlspecialchars(date('d M Y', strtotime((string) ($installment['jatuh_tempo'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span><?= htmlspecialchars($formatCurrency((float) ($installment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> (Terbayar: <?= htmlspecialchars($formatCurrency((float) ($installment['nominal_terbayar'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Honor Guru</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Rincian honor bulanan yang telah diverifikasi.</p>
            </div>
            <div class="px-6 py-4">
                <?php if (empty($honors)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada slip honor.</p>
                <?php else: ?>
                    <ul class="space-y-3 text-sm">
                        <?php foreach ($honors as $honor): ?>
                            <?php
                            $status = (string) ($honor['status'] ?? '');
                            $statusClass = match ($status) {
                                'disetujui', 'terbayar' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                'menunggu_acc', 'menunggu_verifikasi' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
                            };
                            ?>
                            <li class="rounded-xl border border-slate-200/70 p-4 dark:border-slate-700/70">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($honor['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($honor['periode'] ?? '-', ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($honor['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="mt-3 grid gap-3 text-xs md:grid-cols-3">
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Bruto</p>
                                        <p class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($honor['nominal_bruto'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Potongan</p>
                                        <p class="font-semibold text-rose-600 dark:text-rose-300"><?= htmlspecialchars($formatCurrency((float) ($honor['nominal_potongan'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 dark:text-slate-400">Diterima</p>
                                        <p class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($honor['nominal_diterima'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pengajuan Dana Kegiatan</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Status pengajuan dana kegiatan yang Anda ajukan.</p>
                </div>
                <a
                    href="<?= htmlspecialchars(base_url('keuangan/guru/dana-kegiatan'), ENT_QUOTES, 'UTF-8') ?>"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                >
                    <span class="ri-add-circle-line text-base"></span>
                    Ajukan Dana
                </a>
            </div>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($activities)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pengajuan dana kegiatan.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Kegiatan</th>
                                <th class="py-3 pr-4 font-semibold">Kategori</th>
                                <th class="py-3 pr-4 text-right font-semibold">Estimasi</th>
                                <th class="py-3 pr-4 font-semibold">Status</th>
                                <th class="py-3 pr-0 font-semibold">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                $activityId = (int) ($activity['id'] ?? 0);
                                $status = (string) ($activity['status'] ?? '');
                                $badgeClass = match ($status) {
                                    'diverifikasi_bendahara', 'menunggu_acc' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
                                    'disetujui' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
                                    'ditolak' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
                                    'selesai' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
                                    default => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                                };
                                $lpj = $activityReports[(string) $activityId] ?? null;
                                $lpjAllowed = in_array($status, ['disetujui', 'selesai'], true);
                                $lpjRoute = base_url('keuangan/guru/dana-kegiatan/' . $activityId . '/lpj');
                                $lpjUpdatedAt = $lpj['updated_at'] ?? $lpj['created_at'] ?? null;
                                ?>
                                <tr>
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($activity['judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($activity['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 pr-4"><?= htmlspecialchars($activity['kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4 text-right font-semibold"><?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= $badgeClass ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="py-3 pr-0 text-sm"><?= htmlspecialchars(date('d M Y', strtotime((string) ($activity['tanggal_pengajuan'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pengeluaran Tak Terduga</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Dana darurat yang pernah Anda ajukan atau terima.</p>
        </div>
        <div class="px-6 py-4">
            <?php if (empty($unexpectedExpenses)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pengeluaran tak terduga atas nama Anda.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4 font-semibold">Tanggal</th>
                                <th class="py-3 pr-4 font-semibold">Keterangan</th>
                                <th class="py-3 pr-4 text-right font-semibold">Nominal</th>
                                <th class="py-3 pr-0 font-semibold">LPJ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($unexpectedExpenses as $expense): ?>
                                <?php
                                    $expenseId = (int) ($expense['id'] ?? 0);
                                    $lpj = $unexpectedReports[(string) $expenseId] ?? null;
                                    $lpjRoute = base_url('keuangan/guru/pengeluaran-tak-terduga/' . $expenseId . '/lpj');
                                    $lpjUpdatedAt = $lpj['updated_at'] ?? $lpj['created_at'] ?? null;
                                ?>
                                <tr>
                                    <td class="py-3 pr-4 align-top">
                                        <p class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($expense['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars((string) ($expense['kode_transaksi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 pr-4 align-top">
                                        <?php if (!empty($expense['deskripsi'])): ?>
                                            <p><?= nl2br(htmlspecialchars((string) $expense['deskripsi'], ENT_QUOTES, 'UTF-8')) ?></p>
                                        <?php else: ?>
                                            <p class="text-slate-400 dark:text-slate-500">Tidak ada keterangan.</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right align-top font-semibold text-rose-600 dark:text-rose-300">
                                        <?= htmlspecialchars($formatCurrency((float) ($expense['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3 pr-0 align-top">
                                        <?php if ($lpj !== null): ?>
                                            <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">Sudah dikumpulkan</p>
                                            <?php if ($lpjUpdatedAt !== null): ?>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Terakhir: <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $lpjUpdatedAt)), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-xs font-semibold text-amber-600 dark:text-amber-300">Belum dikumpulkan</p>
                                        <?php endif; ?>
                                        <?php if ($expenseId > 0): ?>
                                            <a href="<?= htmlspecialchars($lpjRoute, ENT_QUOTES, 'UTF-8') ?>" class="mt-2 inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                                <span class="ri-edit-line text-sm"></span>
                                                Kelola LPJ
                                            </a>
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
</div>
