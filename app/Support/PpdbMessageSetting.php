<?php

namespace App\Support;

class PpdbMessageSetting
{
    private const STORAGE_FILE = 'settings/ppdb-messages.json';

    private const DEFAULT_REGISTRATION_TEMPLATE = <<<TXT
Yth. {{nama}},
Pendaftaran PPDB {{sekolah}} untuk {{periode}} sudah kami terima dan tersimpan di database sekolah.
Kode pendaftaran: {{kode_pendaftaran}}.
Panitia akan menghubungi Anda untuk informasi tahapan selanjutnya.
TXT;

    private const DEFAULT_BROADCAST_TEMPLATE = <<<TXT
Yth. {{nama}},
Informasi PPDB {{sekolah}} ({{periode}}):
{{pesan}}

Kode pendaftaran Anda: {{kode_pendaftaran}}.
TXT;

    /**
     * @return array{registration_template: string, broadcast_template: string}
     */
    public static function get(): array
    {
        $defaults = [
            'registration_template' => self::DEFAULT_REGISTRATION_TEMPLATE,
            'broadcast_template' => self::DEFAULT_BROADCAST_TEMPLATE,
        ];

        $path = storage_path(self::STORAGE_FILE);
        if (!is_file($path)) {
            return $defaults;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return $defaults;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $defaults;
        }

        return [
            'registration_template' => is_string($data['registration_template'] ?? null) && trim((string) $data['registration_template']) !== ''
                ? (string) $data['registration_template']
                : self::DEFAULT_REGISTRATION_TEMPLATE,
            'broadcast_template' => is_string($data['broadcast_template'] ?? null) && trim((string) $data['broadcast_template']) !== ''
                ? (string) $data['broadcast_template']
                : self::DEFAULT_BROADCAST_TEMPLATE,
        ];
    }

    public static function save(string $registrationTemplate, string $broadcastTemplate): void
    {
        $payload = [
            'registration_template' => trim($registrationTemplate) !== '' ? trim($registrationTemplate) : self::DEFAULT_REGISTRATION_TEMPLATE,
            'broadcast_template' => trim($broadcastTemplate) !== '' ? trim($broadcastTemplate) : self::DEFAULT_BROADCAST_TEMPLATE,
            'updated_at' => date('c'),
        ];

        $path = storage_path(self::STORAGE_FILE);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan template PPDB.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan template pesan PPDB.');
        }
    }

    /**
     * @return array<int, string>
     */
    public static function placeholders(): array
    {
        return [
            '{{nama}}',
            '{{nama_lengkap}}',
            '{{sekolah}}',
            '{{periode}}',
            '{{kode_pendaftaran}}',
            '{{tanggal_daftar}}',
            '{{urutan_pendaftar}}',
            '{{urutan_input}}',
            '{{pesan}}',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function placeholderDescriptions(): array
    {
        return [
            '{{nama}}' => 'Nama pendaftar. Contoh: Budi Santoso',
            '{{nama_lengkap}}' => 'Nama lengkap pendaftar. Contoh: Budi Santoso',
            '{{sekolah}}' => 'Nama sekolah dari pengaturan aplikasi. Contoh: SMK ISNU',
            '{{periode}}' => 'Nama periode PPDB aktif/terpilih. Contoh: Periode 2026-2027',
            '{{kode_pendaftaran}}' => 'Kode pendaftaran unik pendaftar. Contoh: SPMB-2026-0012',
            '{{tanggal_daftar}}' => 'Tanggal pendaftaran tersimpan di sistem. Contoh: 2026-05-01 08:30:00',
            '{{urutan_pendaftar}}' => 'Nomor urut pendaftar pada periode PPDB (berdasarkan urutan input). Contoh: 25',
            '{{urutan_input}}' => 'Alias dari {{urutan_pendaftar}}. Contoh: 25',
            '{{pesan}}' => 'Isi pesan broadcast yang Anda ketik pada form kirim pesan.',
        ];
    }
}
