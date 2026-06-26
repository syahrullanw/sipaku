<?php
/** @var array<string, mixed> $stats */
/** @var array<int, array<string, mixed>> $pendingPayments */
/** @var array<int, array<string, mixed>> $pendingLoans */
/** @var array<int, array<string, mixed>> $pendingActivities */
/** @var array<int, array<string, mixed>> $pendingHonors */
/** @var array<int, array<string, mixed>> $recentCashflow */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
?>

<div class="space-y-8">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Saldo Kas Sekarang</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($stats['cash_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50 p-5 shadow-sm shadow-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-300">Saldo Kas Tagihan Aktif</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-200"><?= htmlspecialchars($formatCurrency((float) ($stats['active_billing_cash'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/70">Rincian per kas tersedia di bawah.</p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/70 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Tagihan Aktif</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><?= number_format((int) ($stats['total_billings'] ?? 0), 0, ',', '.') ?></p>
        </div>
        <div class="rounded-xl border border-amber-200/60 bg-amber-50 p-5 shadow-sm shadow-amber-100 dark:border-amber-500/40 dark:bg-amber-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-amber-600 dark:text-amber-300">Total Piutang</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-200"><?= htmlspecialchars($formatCurrency((float) ($stats['outstanding_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-5 shadow-sm shadow-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10 dark:shadow-none">
            <p class="text-sm font-medium text-sky-600 dark:text-sky-300">Total Tabungan Aktif</p>
            <p class="mt-2 text-2xl font-semibold text-sky-700 dark:text-sky-200"><?= htmlspecialchars($formatCurrency((float) ($stats['active_savings'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <?php
        $weeklyLabels = is_array($stats['weekly_payment_labels'] ?? null) ? $stats['weekly_payment_labels'] : [];
        $weeklyValues = is_array($stats['weekly_payment_values'] ?? null) ? $stats['weekly_payment_values'] : [];

        if (count($weeklyLabels) !== 7 || count($weeklyValues) !== 7) {
            $trendStart = (new DateTimeImmutable('today'))->sub(new DateInterval('P6D'));
            $weeklyLabels = [];
            $weeklyValues = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $trendStart->add(new DateInterval('P' . $i . 'D'));
                $weeklyLabels[] = $day->format('D, d M');
                $weeklyValues[] = 0.0;
            }
        }
    ?>
    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pembayaran Per Hari (7 hari terakhir)</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Jumlah nominal pembayaran yang disetujui untuk minggu terakhir.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d M Y'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="mt-5 h-40">
            <canvas
                id="weekly-payments-chart"
                class="h-full w-full"
                data-labels="<?= htmlspecialchars(json_encode($weeklyLabels), ENT_QUOTES, 'UTF-8') ?>"
                data-values="<?= htmlspecialchars(json_encode(array_map(static fn ($value) => (float) $value, $weeklyValues)), ENT_QUOTES, 'UTF-8') ?>"
            ></canvas>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Menu Pembelian Perlengkapan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Catat pembelian atribut, seragam, atau perlengkapan lain dan pilih metode pembayaran yang sesuai.</p>
            </div>
            <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembelian'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:border-sky-300 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-white">Kelola Pembelian</a>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">Bayar tunai siswa</span>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">Potong saldo tabungan</span>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">Kas sekolah</span>
        </div>
        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Setiap siswa bisa memiliki lebih dari satu pembelian. Bendahara bisa merekam pembelian baru dan menentukan siapa yang menutupnya.</p>
    </div>

    <div class="rounded-xl border border-slate-200/60 bg-white/80 p-6 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <?php $billingCashes = isset($stats['billing_cashes']) && is_array($stats['billing_cashes']) ? $stats['billing_cashes'] : []; ?>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Rincian Saldo Kas Tagihan Aktif</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pantau saldo masing-masing kas untuk memastikan dana mencukupi.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                <?= number_format(is_countable($billingCashes) ? count($billingCashes) : 0, 0, ',', '.') ?> kas aktif
            </span>
        </div>
        <?php if (empty($billingCashes)): ?>
            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                Belum ada kas tagihan aktif untuk tahun ajaran ini.
            </p>
        <?php else: ?>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($billingCashes as $cash): ?>
                    <?php
                        $balance = (float) ($cash['saldo_akhir'] ?? 0);
                        $balanceClass = $balance > 0
                            ? 'text-emerald-600 dark:text-emerald-300'
                            : 'text-rose-600 dark:text-rose-300';
                        $updatedLabel = !empty($cash['updated_at'])
                            ? date('d M Y H:i', strtotime((string) $cash['updated_at']))
                            : null;
                    ?>
                    <div class="rounded-xl border border-slate-200/60 bg-white/90 p-4 shadow-sm shadow-slate-100 transition hover:shadow-md dark:border-slate-700/70 dark:bg-slate-900/50 dark:shadow-none">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($cash['judul'] ?? 'Tagihan', ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Saldo saat ini</p>
                        <p class="mt-1 text-xl font-semibold <?= $balanceClass ?>">
                            <?= htmlspecialchars($formatCurrency($balance), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <dl class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                            <div class="flex justify-between">
                                <dt>Saldo awal</dt>
                                <dd class="font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($formatCurrency((float) ($cash['saldo_awal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Total masuk</dt>
                                <dd class="font-semibold text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($cash['saldo_masuk'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Total keluar</dt>
                                <dd class="font-semibold text-rose-600 dark:text-rose-300">-<?= htmlspecialchars($formatCurrency((float) ($cash['saldo_keluar'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                        </dl>
                        <?php if ($updatedLabel !== null): ?>
                            <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Terakhir diperbarui <?= htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200/60 bg-white/80 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <div class="border-b border-slate-200/60 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pembayaran Menunggu Verifikasi (<?= number_format((int) ($stats['pending_payments'] ?? 0), 0, ',', '.') ?>)</h2>
            </div>
            <div class="overflow-x-auto px-6 py-4">
                <?php if (empty($pendingPayments)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada pembayaran yang menunggu verifikasi.</p>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="pb-3 pr-4">Tanggal</th>
                                <th class="pb-3 pr-4">Siswa</th>
                                <th class="pb-3 pr-4">Tagihan</th>
                                <th class="pb-3 pr-4 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 dark:divide-slate-800 dark:text-slate-200">
                            <?php foreach ($pendingPayments as $payment): ?>
                                <tr>
                                    <td class="py-2 pr-4"><?= htmlspecialchars(date('d M Y', strtotime((string) ($payment['tanggal_bayar'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2 pr-4">
                                        <p class="font-medium">
                                            <?= htmlspecialchars($payment['siswa_nama'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <?= student_status_badge($payment, 'ml-1 align-middle') ?>
                                            <?= student_dapodik_badge($payment, 'ml-1 align-middle') ?>
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($payment['kode_transaksi'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-2 pr-4"><?= htmlspecialchars($payment['tagihan_judul'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-2 pr-0 text-right font-semibold"><?= htmlspecialchars($formatCurrency((float) ($payment['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="mt-4 text-right">
                        <a href="<?= htmlspecialchars(base_url('keuangan/bendahara/pembayaran'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-sky-600 hover:text-sky-500 dark:text-sky-300">Kelola Pembayaran</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Approval Menunggu</h3>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:bg-amber-500/20 dark:text-amber-300"><?= number_format((int) ($stats['pending_approvals'] ?? 0), 0, ',', '.') ?></span>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Kasbon</p>
                        <?php if (empty($pendingLoans)): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada kasbon menunggu.</p>
                        <?php else: ?>
                            <ul class="mt-2 space-y-2 text-sm">
                                <?php foreach ($pendingLoans as $loan): ?>
                                    <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                        <p class="font-medium">#<?= htmlspecialchars($loan['kode'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Nominal: <?= htmlspecialchars($formatCurrency((float) ($loan['nominal_diminta'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Dana Kegiatan</p>
                        <?php if (empty($pendingActivities)): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada pengajuan.</p>
                        <?php else: ?>
                            <ul class="mt-2 space-y-2 text-sm">
                                <?php foreach ($pendingActivities as $activity): ?>
                                    <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                        <p class="font-medium"><?= htmlspecialchars($activity['judul'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Estimasi: <?= htmlspecialchars($formatCurrency((float) ($activity['estimasi_biaya'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Honor Guru</p>
                        <?php if (empty($pendingHonors)): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada honor menunggu.</p>
                        <?php else: ?>
                            <ul class="mt-2 space-y-2 text-sm">
                                <?php foreach ($pendingHonors as $honor): ?>
                                    <li class="rounded-lg border border-slate-200/70 px-3 py-2 dark:border-slate-700/70">
                                        <p class="font-medium"><?= htmlspecialchars($honor['judul'] ?? 'Honor Guru', ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($formatCurrency((float) ($honor['nominal_diterima'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($honor['periode'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200/60 bg-white/80 p-5 shadow-sm shadow-slate-100 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Arus Kas Terbaru</h3>
                <?php if (empty($recentCashflow)): ?>
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Belum ada transaksi kas.</p>
                <?php else: ?>
                    <ul class="mt-3 space-y-3 text-sm">
                        <?php foreach ($recentCashflow as $cash): ?>
                            <li class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-slate-200"><?= htmlspecialchars($cash['keterangan'] ?? ucfirst((string) ($cash['sumber'] ?? '-')) , ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) ($cash['tanggal'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="<?= ($cash['tipe'] ?? '') === 'masuk' ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?> font-semibold">
                                        <?= ($cash['tipe'] ?? '') === 'masuk' ? '+' : '-' ?><?= htmlspecialchars($formatCurrency((float) ($cash['nominal'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">Saldo: <?= htmlspecialchars($formatCurrency((float) ($cash['saldo_setelah'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var canvas = document.getElementById('weekly-payments-chart');
        if (!canvas) {
            return;
        }

        var labels = [];
        var values = [];

        try {
            labels = JSON.parse(canvas.dataset.labels || '[]');
        } catch (error) {
            labels = [];
        }

        try {
            values = JSON.parse(canvas.dataset.values || '[]');
            values = values.map(function (value) {
                var number = Number(value);
                return Number.isFinite(number) ? number : 0;
            });
        } catch (error) {
            values = [];
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pembayaran hari ini',
                    data: values,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.25)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#22c55e',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#475569',
                        },
                    },
                    y: {
                        grid: {
                            color: '#e2e8f0',
                        },
                        ticks: {
                            color: '#475569',
                            callback: function (value) {
                                if (typeof value === 'number') {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                                return value;
                            },
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var label = context.dataset.label || '';
                                var value = typeof context.parsed === 'number' ? context.parsed : context.parsed?.y;
                                var amount = Number.isFinite(value) ? value : 0;
                                return label ? label + ': Rp ' + amount.toLocaleString('id-ID') : 'Rp ' + amount.toLocaleString('id-ID');
                            },
                        },
                    },
                },
            },
        });
    })();
</script>
