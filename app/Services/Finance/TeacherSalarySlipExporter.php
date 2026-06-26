<?php

namespace App\Services\Finance;

use function base_path;

class TeacherSalarySlipExporter
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $teacher
     * @param array<int, array<string, mixed>> $components
     */
    public static function generate(array $record, ?array $teacher, array $components): string
    {
        static::bootstrap();

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle('Slip Gaji Guru');
        $pdf->AddPage();
        $pdf->SetMargins(18, 20, 18);

        $teacherName = (string) ($teacher['nama'] ?? 'Guru');
        $period = (string) ($record['periode'] ?? '-');
        $generatedAt = date('d M Y H:i');

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'Slip Gaji Guru', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Diterbitkan oleh ' . config('app.name', 'Aplikasi Sekolah') . ' pada ' . $generatedAt, 0, 1, 'C');
        $pdf->Ln(4);

        $infoRows = [
            'Nama Guru' => $teacherName,
            'Periode' => $period,
            'Total Jam Mengajar' => static::formatNumber((float) ($record['teaching_hours'] ?? 0.0)) . ' jam',
            'Honor Mengajar' => static::formatCurrency((float) ($record['total_teaching'] ?? 0.0)),
            'Honor Khusus' => static::formatCurrency((float) ($record['total_special'] ?? 0.0)),
            'Honor Akademik' => static::formatCurrency((float) ($record['total_academic'] ?? 0.0)),
            'Honor Kegiatan' => static::formatCurrency((float) ($record['total_activity'] ?? 0.0)),
            'Penyesuaian' => static::formatCurrency((float) ($record['total_adjustment'] ?? 0.0)),
            'Potongan' => static::formatCurrency((float) ($record['total_deduction'] ?? 0.0)),
            'Total Bruto' => static::formatCurrency((float) ($record['total_bruto'] ?? 0.0)),
            'Total Diterima' => static::formatCurrency((float) ($record['total_net'] ?? 0.0)),
        ];

        $pdf->SetFont('Arial', '', 10);
        foreach ($infoRows as $label => $value) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(60, 6, $label, 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, ': ' . $value, 0, 1);
        }

        $pdf->Ln(6);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, 'Rincian Komponen Gaji', 0, 1);
        $pdf->Ln(1);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 243, 248);
        $pdf->Cell(70, 7, 'Komponen', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Jenis', 1, 0, 'L', true);
        $pdf->Cell(25, 7, 'Kuantitas', 1, 0, 'L', true);
        $pdf->Cell(30, 7, 'Tarif', 1, 0, 'L', true);
        $pdf->Cell(0, 7, 'Jumlah', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 9);

        if (empty($components)) {
            $pdf->Cell(0, 8, 'Belum ada rincian komponen gaji.', 1, 1, 'L');
        } else {
            foreach ($components as $component) {
                $label = (string) ($component['label'] ?? '-');
                $typeLabel = static::resolveComponentType((string) ($component['type'] ?? ''));
                $quantity = $component['quantity'] !== null
                    ? static::formatNumber((float) $component['quantity'])
                    : '-';
                $rate = $component['rate'] !== null
                    ? static::formatCurrency((float) $component['rate'])
                    : '-';
                $amount = static::formatCurrency((float) ($component['amount'] ?? 0.0));

                $pdf->Cell(70, 7, $label, 1, 0, 'L');
                $pdf->Cell(30, 7, $typeLabel, 1, 0, 'L');
                $pdf->Cell(25, 7, $quantity, 1, 0, 'L');
                $pdf->Cell(30, 7, $rate, 1, 0, 'L');
                $pdf->Cell(0, 7, $amount, 1, 1, 'R');
            }
        }

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(
            0,
            5,
            "Slip ini diterbitkan otomatis oleh sistem dan berlaku sebagai bukti pencairan gaji sah tanpa tanda tangan.\nSimpan salinan ini atau cetak untuk arsip Anda."
        );

        return $pdf->Output('S');
    }

    private static function bootstrap(): void
    {
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', base_path('app/Libraries/Fpdf/font/'));
        }

        if (!class_exists(\FPDF::class)) {
            require_once base_path('app/Libraries/Fpdf/fpdf.php');
        }
    }

    private static function formatCurrency(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private static function resolveComponentType(string $type): string
    {
        return match ($type) {
            'teaching' => 'Honor Mengajar',
            'special' => 'Honor Khusus',
            'academic' => 'Honor Akademik',
            'activity' => 'Honor Kegiatan',
            'adjustment' => 'Penyesuaian',
            'deduction' => 'Potongan',
            default => ucfirst($type !== '' ? str_replace('_', ' ', $type) : 'Lainnya'),
        };
    }
}
