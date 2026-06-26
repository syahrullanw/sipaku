<?php

namespace App\Services;

use App\Models\PpdbRegistrant;
use App\Models\WhatsappGatewaySetting;
use App\Support\PpdbMessageSetting;

class PpdbBroadcastService
{
    /**
     * @param array<string, mixed> $period
     * @return array{total:int,sent:int,queued:int,failed:int,skipped_no_phone:int}
     */
    public static function dispatchToPeriod(array $period, string $messageBody): array
    {
        $summary = [
            'total' => 0,
            'sent' => 0,
            'queued' => 0,
            'failed' => 0,
            'skipped_no_phone' => 0,
        ];

        $periodId = (int) ($period['id'] ?? 0);
        if ($periodId <= 0) {
            return $summary;
        }

        $settings = WhatsappGatewaySetting::first();
        if ($settings === null) {
            throw new \RuntimeException('Pengaturan WhatsApp Gateway belum tersedia.');
        }

        $templates = PpdbMessageSetting::get();
        $template = (string) ($templates['broadcast_template'] ?? '');

        $registrants = PpdbRegistrant::forPeriod($periodId);
        $summary['total'] = count($registrants);
        $schoolName = (string) config('app.name', 'Sekolah');
        $periodName = (string) ($period['nama'] ?? 'Periode PPDB');

        foreach ($registrants as $registrant) {
            $phone = trim((string) ($registrant['telepon'] ?? ''));
            if ($phone === '') {
                $summary['skipped_no_phone']++;
                continue;
            }

            $sequenceNumber = PpdbRegistrant::sequenceNumberInPeriod($periodId, (int) ($registrant['id'] ?? 0));

            $result = WhatsappGatewayService::sendDetailed([
                'phone' => $phone,
                'template' => $template,
                'variables' => [
                    'nama' => (string) ($registrant['nama_lengkap'] ?? 'Calon Siswa'),
                    'nama_lengkap' => (string) ($registrant['nama_lengkap'] ?? 'Calon Siswa'),
                    'sekolah' => $schoolName,
                    'periode' => $periodName,
                    'kode_pendaftaran' => (string) ($registrant['kode_pendaftaran'] ?? '-'),
                    'tanggal_daftar' => (string) ($registrant['tanggal_daftar'] ?? ''),
                    'urutan_pendaftar' => $sequenceNumber > 0 ? (string) $sequenceNumber : '-',
                    'urutan_input' => $sequenceNumber > 0 ? (string) $sequenceNumber : '-',
                    'pesan' => $messageBody,
                ],
            ], $settings);

            if (!$result['success']) {
                $summary['failed']++;
                continue;
            }

            if ($result['queued']) {
                $summary['queued']++;
            } else {
                $summary['sent']++;
            }
        }

        return $summary;
    }
}
