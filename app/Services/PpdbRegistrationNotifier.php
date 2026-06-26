<?php

namespace App\Services;

use App\Models\PpdbRegistrant;
use App\Models\WhatsappGatewaySetting;
use App\Support\PpdbMessageSetting;

class PpdbRegistrationNotifier
{
    /**
     * @param array<string, mixed> $registrant
     * @param array<string, mixed> $period
     * @return array{success: bool, skipped: bool, message: string}
     */
    public static function notifyRegistrant(array $registrant, array $period = []): array
    {
        $phone = trim((string) ($registrant['telepon'] ?? ''));

        if ($phone === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Nomor WhatsApp pendaftar belum diisi.',
            ];
        }

        $settings = WhatsappGatewaySetting::first();

        if ($settings === null) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Pengaturan WhatsApp Gateway belum tersedia.',
            ];
        }

        $schoolName = (string) config('app.name', 'Sekolah');
        $periodName = (string) ($period['nama'] ?? 'Periode PPDB');
        $registrantName = (string) ($registrant['nama_lengkap'] ?? 'Calon Siswa');
        $code = (string) ($registrant['kode_pendaftaran'] ?? '-');
        $periodId = (int) ($registrant['periode_id'] ?? ($period['id'] ?? 0));
        $registrantId = (int) ($registrant['id'] ?? 0);
        $sequenceNumber = PpdbRegistrant::sequenceNumberInPeriod($periodId, $registrantId);

        $templates = PpdbMessageSetting::get();
        $template = (string) ($templates['registration_template'] ?? '');

        $result = WhatsappGatewayService::sendDetailed([
            'phone' => $phone,
            'template' => $template,
            'variables' => [
                'nama' => $registrantName,
                'nama_lengkap' => $registrantName,
                'sekolah' => $schoolName,
                'periode' => $periodName,
                'kode_pendaftaran' => $code,
                'tanggal_daftar' => (string) ($registrant['tanggal_daftar'] ?? date('Y-m-d H:i:s')),
                'urutan_pendaftar' => $sequenceNumber > 0 ? (string) $sequenceNumber : '-',
                'urutan_input' => $sequenceNumber > 0 ? (string) $sequenceNumber : '-',
            ],
        ], $settings);

        if (!$result['success']) {
            $statusInfo = $result['status'] !== null ? ' (HTTP ' . $result['status'] . ')' : '';

            return [
                'success' => false,
                'skipped' => false,
                'message' => ($result['error'] ?? 'Gagal mengirim notifikasi WhatsApp.') . $statusInfo,
            ];
        }

        return [
            'success' => true,
            'skipped' => false,
            'message' => $result['queued']
                ? 'Notifikasi WhatsApp masuk antrian pengiriman.'
                : 'Notifikasi WhatsApp berhasil dikirim.',
        ];
    }
}
