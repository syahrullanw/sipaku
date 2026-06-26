<?php

namespace App\Services\Finance;

use Core\Database;

class CashflowReportService
{
    /**
     * @return array{
     *     available_years: array<int>,
     *     selected_year: int,
     *     annual_summary: array<string, mixed>,
     *     monthly_reports: array<int, array<string, mixed>>,
     *     yearly_source: array<string, array<string, float|int>>
     * }
     */
    public static function yearlyRecap(?int $year = null): array
    {
        $connection = Database::connection();

        $yearsStatement = $connection->query(
            'SELECT DISTINCT YEAR(tanggal) AS tahun FROM arus_kas ORDER BY tahun DESC'
        );

        $availableYears = [];
        if ($yearsStatement !== false) {
            $fetchedYears = $yearsStatement->fetchAll(\PDO::FETCH_COLUMN);
            if ($fetchedYears !== false) {
                foreach ($fetchedYears as $entry) {
                    $entry = (int) $entry;
                    if ($entry > 0) {
                        $availableYears[] = $entry;
                    }
                }
            }
        }

        if (empty($availableYears)) {
            $availableYears[] = (int) date('Y');
        }

        $availableYears = array_values(array_unique($availableYears));
        rsort($availableYears);

        $selectedYear = $year !== null && $year >= 2000
            ? $year
            : ($availableYears[0] ?? (int) date('Y'));

        if (!in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            $availableYears = array_values(array_unique($availableYears));
            rsort($availableYears);
        }

        $startOfYear = sprintf('%04d-01-01 00:00:00', $selectedYear);
        $endOfYear = sprintf('%04d-12-31 23:59:59', $selectedYear);

        $initialBalanceStatement = $connection->prepare(
            'SELECT saldo_setelah FROM arus_kas WHERE tanggal < :start ORDER BY tanggal DESC, id DESC LIMIT 1'
        );

        $initialBalance = 0.0;
        if ($initialBalanceStatement !== false) {
            $initialBalanceStatement->bindValue(':start', $startOfYear);
            $initialBalanceStatement->execute();
            $previousBalance = $initialBalanceStatement->fetchColumn();
            if ($previousBalance !== false) {
                $initialBalance = (float) $previousBalance;
            }
        }

        $cashflowStatement = $connection->prepare(
            'SELECT id, tanggal, tipe, sumber, nominal, saldo_setelah
             FROM arus_kas
             WHERE tanggal BETWEEN :start AND :end
             ORDER BY tanggal ASC, id ASC'
        );

        $monthlyAggregates = [];
        $sourceBreakdown = [];
        $yearlySource = [];
        $latestUpdate = null;

        if ($cashflowStatement !== false) {
            $cashflowStatement->bindValue(':start', $startOfYear);
            $cashflowStatement->bindValue(':end', $endOfYear);
            $cashflowStatement->execute();
            $entries = $cashflowStatement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($entries as $entry) {
                $timestamp = strtotime((string) ($entry['tanggal'] ?? 'now'));
                $periodKey = date('Y-m', $timestamp);
                $source = (string) ($entry['sumber'] ?? 'lainnya');
                $type = (string) ($entry['tipe'] ?? '');
                $amount = (float) ($entry['nominal'] ?? 0);
                $balanceAfter = (float) ($entry['saldo_setelah'] ?? 0);

                if (!isset($monthlyAggregates[$periodKey])) {
                    $monthlyAggregates[$periodKey] = [
                        'income' => 0.0,
                        'expense' => 0.0,
                        'transactions' => 0,
                        'closing_balance' => $balanceAfter,
                    ];
                }

                $monthlyAggregates[$periodKey]['transactions']++;
                if ($type === 'masuk') {
                    $monthlyAggregates[$periodKey]['income'] += $amount;
                } elseif ($type === 'keluar') {
                    $monthlyAggregates[$periodKey]['expense'] += $amount;
                }

                $monthlyAggregates[$periodKey]['closing_balance'] = $balanceAfter;

                if (!isset($sourceBreakdown[$periodKey])) {
                    $sourceBreakdown[$periodKey] = [];
                }

                if (!isset($sourceBreakdown[$periodKey][$source])) {
                    $sourceBreakdown[$periodKey][$source] = [
                        'income' => 0.0,
                        'expense' => 0.0,
                        'transactions' => 0,
                    ];
                }

                if ($type === 'masuk') {
                    $sourceBreakdown[$periodKey][$source]['income'] += $amount;
                } elseif ($type === 'keluar') {
                    $sourceBreakdown[$periodKey][$source]['expense'] += $amount;
                }
                $sourceBreakdown[$periodKey][$source]['transactions']++;

                if (!isset($yearlySource[$source])) {
                    $yearlySource[$source] = [
                        'income' => 0.0,
                        'expense' => 0.0,
                        'transactions' => 0,
                    ];
                }

                if ($type === 'masuk') {
                    $yearlySource[$source]['income'] += $amount;
                } elseif ($type === 'keluar') {
                    $yearlySource[$source]['expense'] += $amount;
                }
                $yearlySource[$source]['transactions']++;

                if ($latestUpdate === null || $timestamp > $latestUpdate) {
                    $latestUpdate = $timestamp;
                }
            }
        }

        $monthlyReports = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;
        $runningBalance = round($initialBalance, 2);

        for ($month = 1; $month <= 12; $month++) {
            $periodKey = sprintf('%04d-%02d', $selectedYear, $month);
            $aggregate = $monthlyAggregates[$periodKey] ?? [
                'income' => 0.0,
                'expense' => 0.0,
                'transactions' => 0,
                'closing_balance' => $runningBalance,
            ];

            $income = round((float) ($aggregate['income'] ?? 0), 2);
            $expense = round((float) ($aggregate['expense'] ?? 0), 2);
            $transactions = (int) ($aggregate['transactions'] ?? 0);
            $netChange = $income - $expense;

            $openingBalance = $runningBalance;
            if ($transactions > 0) {
                $closingBalance = round((float) ($aggregate['closing_balance'] ?? $openingBalance + $netChange), 2);
                $calculatedOpening = round($closingBalance - $netChange, 2);

                if (abs($calculatedOpening - $openingBalance) > 0.01) {
                    $closingBalance = round($openingBalance + $netChange, 2);
                }
            } else {
                $closingBalance = $openingBalance;
            }

            $runningBalance = $closingBalance;
            $totalIncome += $income;
            $totalExpense += $expense;

            $monthlyReports[] = [
                'period' => $periodKey,
                'month_label' => self::monthLabel($month) . ' ' . $selectedYear,
                'opening_balance' => $openingBalance,
                'total_income' => $income,
                'total_expense' => $expense,
                'net_change' => $netChange,
                'closing_balance' => $closingBalance,
                'transaction_count' => $transactions,
                'sources' => $sourceBreakdown[$periodKey] ?? [],
            ];
        }

        $annualSummary = [
            'opening_balance' => round($initialBalance, 2),
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_change' => round($totalIncome - $totalExpense, 2),
            'closing_balance' => round($runningBalance, 2),
            'latest_update' => $latestUpdate ? date('d M Y H:i', $latestUpdate) : null,
        ];

        ksort($yearlySource);

        return [
            'available_years' => $availableYears,
            'selected_year' => $selectedYear,
            'annual_summary' => $annualSummary,
            'monthly_reports' => $monthlyReports,
            'yearly_source' => $yearlySource,
        ];
    }

    private static function monthLabel(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$month] ?? 'Periode';
    }
}
