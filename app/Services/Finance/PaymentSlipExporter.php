<?php

namespace App\Services\Finance;

use function base_path;

class PaymentSlipExporter
{
    /**
     * @param array<string, mixed> $payment
     */
    public static function generate(array $payment): string
    {
        static::bootstrap();

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle('Slip Pembayaran');
        $pdf->AddPage();
        $pdf->SetMargins(20, 20, 20);

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'Bukti Pembayaran Resmi', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $generatedAt = date('d M Y H:i');
        $pdf->Cell(0, 6, 'Diterbitkan oleh ' . config('app.name', 'Aplikasi Sekolah') . ' pada ' . $generatedAt, 0, 1, 'C');
        $code = (string) ($payment['kode_transaksi'] ?? '');
        if ($code !== '') {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, 'Kode Transaksi: ' . $code, 0, 1, 'C');
        }

        $pdf->Ln(4);
        $pdf->SetFont('Arial', '', 10);

        $rows = [
            'Nama Siswa' => (string) ($payment['siswa_nama'] ?? '-'),
            'NIS' => (string) ($payment['siswa_nis'] ?? '-'),
            'Tagihan' => trim(($payment['tagihan_judul'] ?? '-') . ' (' . ($payment['tagihan_kode'] ?? '-') . ')'),
            'Kategori' => (string) ($payment['kategori_nama'] ?? '-'),
            'Metode Pembayaran' => static::resolveMethod((string) ($payment['metode'] ?? '')),
            'Nominal Dibayarkan' => static::formatCurrency((float) ($payment['nominal'] ?? 0)),
            'Total Tagihan' => isset($payment['tagihan_total']) ? static::formatCurrency((float) $payment['tagihan_total']) : '-',
            'Sisa Tagihan' => isset($payment['sisa_nominal']) ? static::formatCurrency((float) $payment['sisa_nominal']) : '-',
            'Saldo Kas Tagihan' => static::formatCurrency((float) ($payment['kas_saldo'] ?? 0)),
            'Status' => strtoupper(str_replace('_', ' ', (string) ($payment['status'] ?? '-'))),
            'Tanggal Pembayaran' => isset($payment['tanggal_bayar']) ? date('d M Y H:i', strtotime((string) $payment['tanggal_bayar'])) : '-',
            'Diverifikasi Oleh' => trim(($payment['diverifikasi_oleh_nama'] ?? '-') . ' (' . (isset($payment['diverifikasi_pada']) ? date('d M Y H:i', strtotime((string) $payment['diverifikasi_pada'])) : '-') . ')'),
        ];

        $note = trim((string) ($payment['catatan'] ?? ''));
        if ($note !== '') {
            $rows['Catatan'] = $note;
        }

        foreach ($rows as $label => $value) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(60, 7, $label, 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 7, $value, 0, 'L');
        }

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(
            0,
            5,
            "Slip ini diterbitkan otomatis oleh sistem dan berlaku sebagai bukti pembayaran sah tanpa tanda tangan.\nSimpan salinan ini atau cetak untuk arsip."
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

    private static function resolveMethod(string $methodRaw): string
    {
        return match ($methodRaw) {
            'tunai' => 'Tunai',
            'transfer' => 'Transfer',
            'tabungan' => 'Saldo Tabungan',
            default => ucfirst($methodRaw !== '' ? $methodRaw : '-'),
        };
    }
}
