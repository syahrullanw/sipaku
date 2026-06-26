<?php
/** @var array<int, int> $availableYears */
/** @var int $selectedYear */
/** @var array<string, mixed> $annualSummary */
/** @var array<int, array<string, mixed>> $monthlyReports */
/** @var array<string, array<string, float|int>> $yearlySource */

$pageHeading = 'Rekap Arus Kas Sekolah';
$pageDescription = 'Pantau pemasukan, pengeluaran, dan saldo semua kas sekolah per bulan.';
$actionUrl = base_url('keuangan/kepala-sekolah/laporan');

require __DIR__ . '/../../partials/cashflow-report.php';
