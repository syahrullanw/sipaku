<?php
/** @var array<int, int> $availableYears */
/** @var int $selectedYear */
/** @var array<string, mixed> $annualSummary */
/** @var array<int, array<string, mixed>> $monthlyReports */
/** @var array<string, array<string, float|int>> $yearlySource */
/** @var string $pageHeading */
/** @var string $pageDescription */
/** @var string $actionUrl */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$formatNumber = static fn (int $value): string => number_format($value, 0, ',', '.');

$yearOptions = $availableYears;
sort($yearOptions, SORT_NUMERIC);

$hasTransactions = false;
foreach ($monthlyReports as $monthReport) {
    if (($monthReport['transaction_count'] ?? 0) > 0) {
        $hasTransactions = true;
        break;
    }
}
?>

<div class="space-y-8 px-4 sm:px-0">
    <div class="flex flex-col gap-4 rounded-xl border border-slate-200/60 bg-white/80 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
            <h1 class="text-lg font-semibold text-slate-900 sm:text-xl dark:text-white"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($annualSummary['latest_update'])): ?>
                <p class="text-xs text-slate-400 dark:text-slate-500">Terakhir diperbarui: <?= htmlspecialchars((string) $annualSummary['latest_update'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <form method="get" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <label for="year" class="text-sm font-medium text-slate-600 dark:text-slate-300">Tahun Anggaran</label>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-2">
                <select id="year" name="year" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 sm:w-auto dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    <?php foreach ($yearOptions as $year): ?>
                        <option value="<?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $year === (int) $selectedYear ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-1 sm:w-auto dark:bg-sky-600 dark:hover:bg-sky-500">
                    Tampilkan
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Awal Tahun</p>
            <p class="mt-2 text-lg font-semibold text-slate-900 sm:text-xl dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($annualSummary['opening_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50 p-4 shadow-sm shadow-emerald-100 sm:p-6 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:shadow-none">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300/80">Total Pemasukan</p>
            <p class="mt-2 text-lg font-semibold text-emerald-700 sm:text-xl dark:text-emerald-200"><?= htmlspecialchars($formatCurrency((float) ($annualSummary['total_income'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-rose-200/60 bg-rose-50 p-4 shadow-sm shadow-rose-100 sm:p-6 dark:border-rose-500/40 dark:bg-rose-500/10 dark:shadow-none">
            <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300/80">Total Pengeluaran</p>
            <p class="mt-2 text-lg font-semibold text-rose-700 sm:text-xl dark:text-rose-200"><?= htmlspecialchars($formatCurrency((float) ($annualSummary['total_expense'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-sky-200/60 bg-sky-50 p-4 shadow-sm shadow-sky-100 sm:p-6 dark:border-sky-500/40 dark:bg-sky-500/10 dark:shadow-none">
            <p class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300/80">Perubahan Bersih</p>
            <?php
                $netChange = (float) ($annualSummary['net_change'] ?? 0);
                $netClass = $netChange >= 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-700 dark:text-rose-200';
                $netDisplay = $formatCurrency(abs($netChange));
                if ($netChange > 0) {
                    $netDisplay = '+' . $netDisplay;
                } elseif ($netChange < 0) {
                    $netDisplay = '-' . $netDisplay;
                }
            ?>
            <p class="mt-2 text-lg font-semibold <?= $netClass ?> sm:text-xl"><?= htmlspecialchars($netDisplay, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="rounded-xl border border-slate-200/60 bg-white/80 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Saldo Akhir Tahun</p>
            <p class="mt-2 text-lg font-semibold text-slate-900 sm:text-xl dark:text-white"><?= htmlspecialchars($formatCurrency((float) ($annualSummary['closing_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <section class="rounded-xl border border-slate-200/60 bg-white/90 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Ringkasan Bulanan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Total pemasukan, pengeluaran, dan saldo gabungan setiap bulan.</p>
            </div>
            <?php if (!$hasTransactions): ?>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-200">
                    Belum ada transaksi pada tahun ini
                </span>
            <?php endif; ?>
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            <?php foreach ($monthlyReports as $report): ?>
                <?php
                    $change = (float) ($report['net_change'] ?? 0);
                    $changeClass = $change > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($change < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                    $changeDisplay = $formatCurrency(abs($change));
                    if ($change > 0) {
                        $changeDisplay = '+' . $changeDisplay;
                    } elseif ($change < 0) {
                        $changeDisplay = '-' . $changeDisplay;
                    }
                ?>
                <article class="rounded-lg border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
                    <header class="flex flex-col gap-2">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"><?= htmlspecialchars((string) ($report['month_label'] ?? $report['period']), ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="inline-flex w-max items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <?= htmlspecialchars($formatNumber((int) ($report['transaction_count'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> transaksi
                        </span>
                    </header>
                    <dl class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Saldo awal</dt>
                            <dd><?= htmlspecialchars($formatCurrency((float) ($report['opening_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-300">
                            <dt class="font-medium">Pemasukan</dt>
                            <dd><?= htmlspecialchars($formatCurrency((float) ($report['total_income'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="flex items-center justify-between text-rose-600 dark:text-rose-300">
                            <dt class="font-medium">Pengeluaran</dt>
                            <dd><?= htmlspecialchars($formatCurrency((float) ($report['total_expense'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="flex items-center justify-between <?= $changeClass ?>">
                            <dt class="font-medium">Perubahan</dt>
                            <dd><?= htmlspecialchars($changeDisplay, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium">Saldo akhir</dt>
                            <dd><?= htmlspecialchars($formatCurrency((float) ($report['closing_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 hidden md:block">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-xs text-slate-700 sm:text-sm dark:text-slate-200">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Bulan</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center">Transaksi</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Saldo Awal</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Pemasukan</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Pengeluaran</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Perubahan</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70">
                        <?php foreach ($monthlyReports as $report): ?>
                            <?php
                                $change = (float) ($report['net_change'] ?? 0);
                                $changeClass = $change > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($change < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                                $changeDisplay = $formatCurrency(abs($change));
                                if ($change > 0) {
                                    $changeDisplay = '+' . $changeDisplay;
                                } elseif ($change < 0) {
                                    $changeDisplay = '-' . $changeDisplay;
                                }
                            ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100"><?= htmlspecialchars((string) ($report['month_label'] ?? $report['period']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-center"><?= htmlspecialchars($formatNumber((int) ($report['transaction_count'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right"><?= htmlspecialchars($formatCurrency((float) ($report['opening_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency((float) ($report['total_income'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-300"><?= htmlspecialchars($formatCurrency((float) ($report['total_expense'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right font-semibold <?= $changeClass ?>"><?= htmlspecialchars($changeDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-4 py-3 text-right"><?= htmlspecialchars($formatCurrency((float) ($report['closing_balance'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200/60 bg-white/90 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Rincian Sumber Dana (Tahunan)</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Total pemasukan dan pengeluaran berdasarkan sumber transaksi sepanjang tahun.</p>

            <?php if (empty($yearlySource)): ?>
                <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                    Belum ada data sumber dana pada tahun ini.
                </p>
            <?php else: ?>
                <div class="mt-4 space-y-3 md:hidden">
                    <?php foreach ($yearlySource as $source => $summary): ?>
                        <?php
                            $sourceIncome = (float) ($summary['income'] ?? 0);
                            $sourceExpense = (float) ($summary['expense'] ?? 0);
                            $sourceNet = $sourceIncome - $sourceExpense;
                            $sourceClass = $sourceNet > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($sourceNet < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                            $sourceChangeDisplay = $formatCurrency(abs($sourceNet));
                            if ($sourceNet > 0) {
                                $sourceChangeDisplay = '+' . $sourceChangeDisplay;
                            } elseif ($sourceNet < 0) {
                                $sourceChangeDisplay = '-' . $sourceChangeDisplay;
                            }
                        ?>
                        <article class="rounded-lg border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
                            <h3 class="text-sm font-semibold capitalize text-slate-800 dark:text-slate-100"><?= htmlspecialchars(str_replace('_', ' ', (string) $source), ENT_QUOTES, 'UTF-8') ?></h3>
                            <dl class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                                <div class="flex items-center justify-between">
                                    <dt class="font-medium">Transaksi</dt>
                                    <dd><?= htmlspecialchars($formatNumber((int) ($summary['transactions'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                                <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-300">
                                    <dt class="font-medium">Pemasukan</dt>
                                    <dd><?= htmlspecialchars($formatCurrency($sourceIncome), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                                <div class="flex items-center justify-between text-rose-600 dark:text-rose-300">
                                    <dt class="font-medium">Pengeluaran</dt>
                                    <dd><?= htmlspecialchars($formatCurrency($sourceExpense), ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                                <div class="flex items-center justify-between <?= $sourceClass ?>">
                                    <dt class="font-medium">Netto</dt>
                                    <dd><?= htmlspecialchars($sourceNet === 0.0 ? $formatCurrency(0.0) : $sourceChangeDisplay, ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs text-slate-700 sm:text-sm dark:text-slate-200">
                            <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3">Sumber</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-center">Transaksi</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Pemasukan</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Pengeluaran</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Netto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70">
                                <?php foreach ($yearlySource as $source => $summary): ?>
                                    <?php
                                        $sourceIncome = (float) ($summary['income'] ?? 0);
                                        $sourceExpense = (float) ($summary['expense'] ?? 0);
                                        $sourceNet = $sourceIncome - $sourceExpense;
                                        $sourceClass = $sourceNet > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($sourceNet < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                                        $sourceChangeDisplay = $formatCurrency(abs($sourceNet));
                                        if ($sourceNet > 0) {
                                            $sourceChangeDisplay = '+' . $sourceChangeDisplay;
                                        } elseif ($sourceNet < 0) {
                                            $sourceChangeDisplay = '-' . $sourceChangeDisplay;
                                        }
                                    ?>
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                        <td class="px-4 py-3 font-medium capitalize text-slate-800 dark:text-slate-100"><?= htmlspecialchars(str_replace('_', ' ', (string) $source), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 text-center"><?= htmlspecialchars($formatNumber((int) ($summary['transactions'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($sourceIncome), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 text-right text-rose-600 dark:text-rose-300"><?= htmlspecialchars($formatCurrency($sourceExpense), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="px-4 py-3 text-right font-semibold <?= $sourceClass ?>"><?= htmlspecialchars($sourceNet === 0.0 ? $formatCurrency(0.0) : $sourceChangeDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border border-slate-200/60 bg-white/90 p-4 shadow-sm shadow-slate-100 sm:p-6 dark:border-slate-700/70 dark:bg-slate-900/60 dark:shadow-none">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Detail Sumber per Bulan</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Lihat kontribusi pemasukan dan pengeluaran tiap sumber pada bulan yang aktif.</p>

            <?php
                $monthsWithDetails = array_filter(
                    $monthlyReports,
                    static fn ($report): bool => !empty($report['sources']) && is_array($report['sources'])
                );
            ?>

            <?php if (empty($monthsWithDetails)): ?>
                <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-slate-800/40 dark:text-slate-400">
                    Belum ada rincian sumber per bulan untuk tahun ini.
                </p>
            <?php else: ?>
                <div class="mt-4 space-y-4">
                    <?php foreach ($monthsWithDetails as $report): ?>
                        <article class="rounded-lg border border-slate-200/70 bg-white/80 p-4 shadow-sm dark:border-slate-700/60 dark:bg-slate-900/50">
                            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars((string) ($report['month_label'] ?? $report['period']), ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="inline-flex w-max items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <?= htmlspecialchars($formatNumber((int) ($report['transaction_count'] ?? 0)), ENT_QUOTES, 'UTF-8') ?> transaksi
                                </span>
                            </header>

                            <div class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300 md:hidden">
                                <?php foreach ($report['sources'] as $source => $detail): ?>
                                    <?php
                                        $sourceIncome = (float) ($detail['income'] ?? 0);
                                        $sourceExpense = (float) ($detail['expense'] ?? 0);
                                        $sourceNet = $sourceIncome - $sourceExpense;
                                        $sourceClass = $sourceNet > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($sourceNet < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                                        $detailChangeDisplay = $formatCurrency(abs($sourceNet));
                                        if ($sourceNet > 0) {
                                            $detailChangeDisplay = '+' . $detailChangeDisplay;
                                        } elseif ($sourceNet < 0) {
                                            $detailChangeDisplay = '-' . $detailChangeDisplay;
                                        }
                                    ?>
                                    <div class="rounded-lg border border-slate-200 bg-white/70 p-3 dark:border-slate-700/70 dark:bg-slate-900/60">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= htmlspecialchars(str_replace('_', ' ', (string) $source), ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="mt-2 space-y-1">
                                            <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-300">
                                                <span class="font-medium">Pemasukan</span>
                                                <span><?= htmlspecialchars($formatCurrency($sourceIncome), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="flex items-center justify-between text-rose-600 dark:text-rose-300">
                                                <span class="font-medium">Pengeluaran</span>
                                                <span><?= htmlspecialchars($formatCurrency($sourceExpense), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="flex items-center justify-between <?= $sourceClass ?>">
                                                <span class="font-medium">Netto</span>
                                                <span><?= htmlspecialchars($sourceNet === 0.0 ? $formatCurrency(0.0) : $detailChangeDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-3 hidden md:block">
                                <div class="overflow-x-auto">
                                    <table class="w-full border-collapse text-xs text-slate-700 sm:text-sm dark:text-slate-200">
                                        <thead class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                            <tr>
                                                <th class="whitespace-nowrap px-2 py-2">Sumber</th>
                                                <th class="whitespace-nowrap px-2 py-2 text-right">Pemasukan</th>
                                                <th class="whitespace-nowrap px-2 py-2 text-right">Pengeluaran</th>
                                                <th class="whitespace-nowrap px-2 py-2 text-right">Netto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/70">
                                            <?php foreach ($report['sources'] as $source => $detail): ?>
                                                <?php
                                                    $sourceIncome = (float) ($detail['income'] ?? 0);
                                                    $sourceExpense = (float) ($detail['expense'] ?? 0);
                                                    $sourceNet = $sourceIncome - $sourceExpense;
                                                    $sourceClass = $sourceNet > 0 ? 'text-emerald-600 dark:text-emerald-300' : ($sourceNet < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300');
                                                    $detailChangeDisplay = $formatCurrency(abs($sourceNet));
                                                    if ($sourceNet > 0) {
                                                        $detailChangeDisplay = '+' . $detailChangeDisplay;
                                                    } elseif ($sourceNet < 0) {
                                                        $detailChangeDisplay = '-' . $detailChangeDisplay;
                                                    }
                                                ?>
                                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                                    <td class="px-2 py-2 font-medium capitalize text-slate-800 dark:text-slate-100"><?= htmlspecialchars(str_replace('_', ' ', (string) $source), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="px-2 py-2 text-right text-emerald-600 dark:text-emerald-300"><?= htmlspecialchars($formatCurrency($sourceIncome), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="px-2 py-2 text-right text-rose-600 dark:text-rose-300"><?= htmlspecialchars($formatCurrency($sourceExpense), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="px-2 py-2 text-right font-semibold <?= $sourceClass ?>"><?= htmlspecialchars($sourceNet === 0.0 ? $formatCurrency(0.0) : $detailChangeDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
