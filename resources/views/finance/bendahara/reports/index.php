<?php
/** @var array<int, int> $availableYears */
/** @var int $selectedYear */
/** @var array<string, mixed> $annualSummary */
/** @var array<int, array<string, mixed>> $monthlyReports */
/** @var array<string, array<string, float|int>> $yearlySource */

$pageHeading = 'Rekap Arus Kas';
$pageDescription = 'Laporan pemasukan, pengeluaran, dan saldo gabungan seluruh kas untuk setiap bulan.';
$actionUrl = base_url('keuangan/bendahara/laporan');

require __DIR__ . '/../../partials/cashflow-report.php';
