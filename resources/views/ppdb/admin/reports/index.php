<?php
    use App\Models\PpdbRegistrant;

    /** @var array<int, string> $periodOptions */
    /** @var array<string, mixed> $summary */
    /** @var array<int, array<string, mixed>> $registrants */
    /** @var array<string, string> $finalStatusOptions */

    $periodId = $selectedPeriodId ?? 0;
    $currentPeriod = $period ?? null;
    $summary = $summary ?? [];
    $registrants = $registrants ?? [];
    $finalStatusOptions = $finalStatusOptions ?? [];
    $selectedFinalStatus = $selectedFinalStatus ?? '';

    $formatStatus = static function (?string $value, array $map, string $fallback = 'Tidak Diketahui'): string {
        if ($value === null || $value === '') {
            return 'Belum Diisi';
        }
        return $map[$value] ?? $fallback;
    };
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-700">
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-gray-100">Laporan Akhir PPDB</h1>
        <p class="text-sm text-slate-500 dark:text-gray-400">
            Ringkasan pendaftar, progres tiap tahapan, dan daftar rinci calon siswa untuk periode PPDB terpilih.
        </p>
        <form action="<?= htmlspecialchars(base_url('ppdb/admin/laporan'), ENT_QUOTES, 'UTF-8') ?>" method="get" class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-medium text-slate-600 dark:text-gray-200">
                Periode PPDB
                <select name="periode_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                    <option value="">Pilih periode</option>
                    <?php foreach ($periodOptions as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= (int) $periodId === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-600 dark:text-gray-200">
                Status Akhir
                <select name="status_final" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/70 dark:text-gray-100" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    <?php foreach ($finalStatusOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedFinalStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php if ($periodId <= 0 || empty($summary)): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-gray-300">
            Pilih periode PPDB untuk melihat ringkasan laporan.
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-gray-500">Total Pendaftar</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600 dark:text-indigo-300"><?= number_format((int) ($summary['total'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-gray-500">Diterima & Migrasi</p>
                <p class="mt-2 text-lg text-slate-700 dark:text-gray-200">Migrasi: <span class="font-semibold"><?= number_format((int) ($summary['accepted_migrated'] ?? 0)) ?></span></p>
                <p class="text-sm text-slate-500 dark:text-gray-400">Belum migrasi: <?= number_format((int) ($summary['accepted_pending'] ?? 0)) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-gray-500">Pembayaran Lunas</p>
                <p class="mt-2 text-lg text-slate-700 dark:text-gray-200">Jumlah: <span class="font-semibold"><?= number_format((float) ($summary['payment_nominal'] ?? 0), 0, ',', '.') ?></span></p>
                <p class="text-sm text-slate-500 dark:text-gray-400">Status lunas: <?= number_format((int) (($summary['payment']['lunas'] ?? 0))) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-gray-500">Periode</p>
                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-gray-200"><?= htmlspecialchars($currentPeriod['nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($currentPeriod['pendaftaran_mulai'])): ?>
                    <p class="text-xs text-slate-500 dark:text-gray-400">
                        Pendaftaran: <?= htmlspecialchars(date('d M Y', strtotime($currentPeriod['pendaftaran_mulai'])), ENT_QUOTES, 'UTF-8') ?> -
                        <?= htmlspecialchars(date('d M Y', strtotime($currentPeriod['pendaftaran_selesai'] ?? $currentPeriod['pendaftaran_mulai'])), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <?php
                $summaryTables = [
                    'gender' => 'Komposisi Gender',
                    'selection' => 'Tahap Seleksi',
                    'announcement' => 'Status Pengumuman',
                    're_registration' => 'Status Daftar Ulang',
                    'payment' => 'Status Pembayaran',
                    'final' => 'Status Akhir',
                ];
                $statusMaps = [
                    'gender' => [
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        null => 'Belum Diisi',
                    ],
                    'selection' => PpdbRegistrant::selectionStatusOptions(),
                    'announcement' => PpdbRegistrant::announcementStatusOptions(),
                    're_registration' => PpdbRegistrant::reRegistrationStatusOptions(),
                    'payment' => PpdbRegistrant::paymentStatusOptions(),
                    'final' => PpdbRegistrant::statusFinalOptions(),
                ];
            ?>
            <?php foreach ($summaryTables as $key => $label): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-gray-200"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php $data = $summary[$key] ?? []; ?>
                    <?php if (empty($data)): ?>
                        <p class="mt-4 text-xs text-slate-500 dark:text-gray-400">Belum ada data.</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-2 text-sm">
                            <?php foreach ($data as $status => $count): ?>
                                <?php
                                    $map = $statusMaps[$key] ?? [];
                                    $labelStatus = $status === null ? ($map[null] ?? 'Belum Diisi') : ($map[$status] ?? ucfirst(str_replace('_', ' ', (string) $status)));
                                ?>
                                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700/60">
                                    <span class="text-slate-600 dark:text-gray-200"><?= htmlspecialchars($labelStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-sm font-semibold text-slate-700 dark:text-gray-100"><?= number_format((int) $count) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white pb-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800 dark:text-gray-100">Rincian Pendaftar</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-gray-400">Menampilkan calon siswa sesuai filter status akhir.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-slate-700">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Nama</th>
                            <th class="px-4 py-3 text-left">Kontak</th>
                            <th class="px-4 py-3 text-left">Tahapan</th>
                            <th class="px-4 py-3 text-left">Pembayaran</th>
                            <th class="px-4 py-3 text-left">Status Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php if (empty($registrants)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-gray-400">Tidak ada data pendaftar untuk filter yang dipilih.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrants as $record): ?>
                                <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-slate-700/40">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-800 dark:text-gray-100"><?= htmlspecialchars($record['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="mt-1 text-[11px] text-slate-500 dark:text-gray-400">
                                            <?= htmlspecialchars(($record['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <?php if (!empty($record['asal_sekolah'])): ?>
                                            <div class="mt-1 text-[11px] text-slate-500 dark:text-gray-400">Asal: <?= htmlspecialchars($record['asal_sekolah'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-[11px] text-slate-600 dark:text-gray-300">
                                        <?php if (!empty($record['telepon'])): ?>
                                            <div>HP: <?= htmlspecialchars($record['telepon'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['email'])): ?>
                                            <div>Email: <?= htmlspecialchars($record['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['telepon_wali'])): ?>
                                            <div>Wali: <?= htmlspecialchars($record['telepon_wali'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-[11px] text-slate-600 dark:text-gray-300 space-y-1">
                                        <div>Seleksi: <?= htmlspecialchars($formatStatus($record['status_seleksi'] ?? '', PpdbRegistrant::selectionStatusOptions()), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div>Pengumuman: <?= htmlspecialchars($formatStatus($record['status_pengumuman'] ?? '', PpdbRegistrant::announcementStatusOptions()), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div>Daftar Ulang: <?= htmlspecialchars($formatStatus($record['status_daftar_ulang'] ?? '', PpdbRegistrant::reRegistrationStatusOptions()), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-[11px] text-slate-600 dark:text-gray-300">
                                        <div><?= htmlspecialchars($formatStatus($record['status_pembayaran'] ?? '', PpdbRegistrant::paymentStatusOptions()), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($record['nominal_pembayaran'])): ?>
                                            <div>Nominal: Rp<?= htmlspecialchars(number_format((float) $record['nominal_pembayaran'], 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($record['tanggal_pembayaran'])): ?>
                                            <div><?= htmlspecialchars(date('d M Y H:i', strtotime($record['tanggal_pembayaran'])), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-[11px] text-slate-600 dark:text-gray-300">
                                        <div class="font-semibold text-indigo-600 dark:text-indigo-300">
                                            <?= htmlspecialchars($formatStatus($record['status_final'] ?? '', PpdbRegistrant::statusFinalOptions()), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <?php if (!empty($record['siswa_id'])): ?>
                                            <div class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-300">Sudah menjadi siswa (#<?= (int) $record['siswa_id'] ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
