<?php
/** @var array<string, mixed> $record */
/** @var array<string, mixed>|null $teacher */
/** @var array<int, array<string, mixed>> $components */

$formatCurrency = static fn (float $value): string => 'Rp ' . number_format($value, 0, ',', '.');
$formatNumber = static fn (float $value): string => number_format($value, 2, ',', '.');
$teacherName = (string) ($teacher['nama'] ?? 'Guru');
$teacherNip = (string) ($teacher['nip'] ?? '-');
$teacherEmail = (string) ($teacher['email'] ?? '-');
$period = (string) ($record['periode'] ?? '-');
$printedAt = date('d/m/Y H:i');

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji Guru - <?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>)</title>
    <style>
        @page {
            size: 152mm 90mm; /* Ukuran amplop C5 pendek */
            margin: 2mm;       /* Margin kecil supaya muat rapi */
        }

        html, body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            font-size: 11px;
            background-color: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        .wrapper {
            width: 217mm; /* 229 - (6mm×2) margin */
            min-height: 102mm;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            box-sizing: border-box;
            box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.25);
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .header h1 {
            font-size: 15px;
            margin: 0;
            color: #0c4a6e;
        }

        .muted {
            color: #64748b;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: #ecfeff;
            color: #0e7490;
        }

        .summary {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            background: #f8fafc;
            flex: 1 1 180px;
        }

        .summary-card h3 {
            font-size: 10px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
        }

        .summary-card .value {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #f1f5f9;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
        }

        td {
            font-size: 11px;
        }

        td.amount {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .totals {
            margin-top: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .totals-row strong {
            font-size: 12px;
        }

        .totals-row:last-child {
            margin-bottom: 0;
            font-size: 13px;
            font-weight: 700;
            color: #047857;
        }

        .footer {
            text-align: right;
            font-size: 10px;
            color: #94a3b8;
            margin-top: auto;
            padding-top: 8px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .print-button {
            border: 1px solid #0284c7;
            background: #0ea5e9;
            color: #ffffff;
            padding: 6px 14px;
            font-size: 11px;
            border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 6px 14px -10px rgba(14, 165, 233, 0.8);
        }

        .print-button:hover {
            background: #0284c7;
        }

        @media print {
            body {
                background: transparent;
            }
            .wrapper {
                border-radius: 0;
                box-shadow: none;
            }
            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="print-actions">
            <button type="button" class="print-button" onclick="window.print()">Cetak Slip</button>
        </div>

        <div class="header">
            <div>
                <h1>Slip Gaji Guru</h1>
                <div class="muted">Periode <?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="badge"><?= strtoupper((string) ($record['status'] ?? 'draft')) ?></div>
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>Nama Guru</h3>
                <div class="value"><?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="muted">NIP: <?= htmlspecialchars($teacherNip, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="summary-card">
                <h3>Jam Mengajar</h3>
                <div class="value"><?= $formatNumber((float) ($record['teaching_hours'] ?? 0.0)) ?> Jam</div>
                <div class="muted">@ <?= $formatCurrency((float) ($record['hourly_rate'] ?? 0.0)) ?></div>
            </div>
            <div class="summary-card">
                <h3>Total Diterima</h3>
                <div class="value" style="color:#047857;"><?= $formatCurrency((float) ($record['total_net'] ?? 0.0)) ?></div>
                <div class="muted">Bruto <?= $formatCurrency((float) ($record['total_bruto'] ?? 0.0)) ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 45%;">Komponen</th>
                    <th style="width: 15%; text-align: right;">Jumlah</th>
                    <th style="width: 15%; text-align: right;">Tarif</th>
                    <th style="width: 25%; text-align: right;">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Honor Mengajar</td>
                    <td class="amount"><?= $formatNumber((float) ($record['teaching_hours'] ?? 0.0)) ?> Jam</td>
                    <td class="amount"><?= $formatCurrency((float) ($record['hourly_rate'] ?? 0.0)) ?></td>
                    <td class="amount"><?= $formatCurrency((float) ($record['total_teaching'] ?? 0.0)) ?></td>
                </tr>
                <?php foreach ($components as $component): ?>
                    <?php
                    $type = (string) ($component['type'] ?? 'adjustment');
                    $label = (string) ($component['label'] ?? 'Komponen');
                    $quantity = $component['quantity'] !== null ? (float) $component['quantity'] : null;
                    $rate = $component['rate'] !== null ? (float) $component['rate'] : null;
                    $amount = (float) ($component['amount'] ?? 0.0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="amount"><?= $quantity !== null ? $formatNumber($quantity) : '-' ?></td>
                        <td class="amount"><?= $rate !== null ? $formatCurrency($rate) : '-' ?></td>
                        <td class="amount" style="<?= $type === 'deduction' ? 'color:#dc2626;' : '' ?>">
                            <?= $type === 'deduction' ? '- ' : '' ?><?= $formatCurrency($amount) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row"><span>Total Honor Mengajar</span><span><?= $formatCurrency((float) ($record['total_teaching'] ?? 0.0)) ?></span></div>
            <div class="totals-row"><span>Honor Tugas Khusus</span><span><?= $formatCurrency((float) ($record['total_special'] ?? 0.0)) ?></span></div>
            <div class="totals-row"><span>Honor Jabatan Akademik</span><span><?= $formatCurrency((float) ($record['total_academic'] ?? 0.0)) ?></span></div>
            <div class="totals-row"><span>Honor Kegiatan Sekolah</span><span><?= $formatCurrency((float) ($record['total_activity'] ?? 0.0)) ?></span></div>
            <div class="totals-row"><span>Penyesuaian (+)</span><span><?= $formatCurrency((float) ($record['total_adjustment'] ?? 0.0)) ?></span></div>
            <div class="totals-row" style="color:#dc2626;"><strong>Potongan</strong><strong>- <?= $formatCurrency((float) ($record['total_deduction'] ?? 0.0)) ?></strong></div>
            <div class="totals-row"><strong>Total Diterima</strong><strong><?= $formatCurrency((float) ($record['total_net'] ?? 0.0)) ?></strong></div>
        </div>

        <?php if (!empty($record['note'])): ?>
            <div style="margin-top: 14px; font-size: 12px;">
                <strong>Catatan:</strong>
                <div class="muted" style="margin-top: 4px; white-space: pre-wrap;"><?= nl2br(htmlspecialchars((string) $record['note'], ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
        <?php endif; ?>

        <div class="footer">
            Dicetak pada <?= htmlspecialchars($printedAt, ENT_QUOTES, 'UTF-8') ?> · Bendahara <?= htmlspecialchars(config('app.name'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</body>
</html>
