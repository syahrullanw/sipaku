<?php

namespace App\Support;

class UserPasswordMessageSetting
{
    private const STORAGE_FILE = 'settings/user-password-messages.json';

    private const DEFAULT_PASSWORD_TEMPLATE = <<<TXT
Halo {{nama}}, berikut akses akun {{sekolah}}:
- Login: {{login_url}}
- Username: {{username}}
- Password default: {{password_default}}

Segera masuk dan ganti password melalui {{reset_url}}.
TXT;

    private const RESET_PASSWORD_TEMPLATE = <<<TXT
Halo {{nama}}, password akun {{sekolah}} sudah direset ke {{password_default}}.
Login: {{login_url}}
Username: {{username}}

Setelah berhasil masuk, silakan ganti password di {{reset_url}}.
TXT;

    /**
     * @return array{default_password_template: string, reset_password_template: string}
     */
    public static function get(): array
    {
        $defaults = [
            'default_password_template' => self::DEFAULT_PASSWORD_TEMPLATE,
            'reset_password_template' => self::RESET_PASSWORD_TEMPLATE,
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
            'default_password_template' => is_string($data['default_password_template'] ?? null) && trim((string) $data['default_password_template']) !== ''
                ? (string) $data['default_password_template']
                : self::DEFAULT_PASSWORD_TEMPLATE,
            'reset_password_template' => is_string($data['reset_password_template'] ?? null) && trim((string) $data['reset_password_template']) !== ''
                ? (string) $data['reset_password_template']
                : self::RESET_PASSWORD_TEMPLATE,
        ];
    }

    public static function save(string $defaultTemplate, string $resetTemplate): void
    {
        $payload = [
            'default_password_template' => trim($defaultTemplate) !== '' ? trim($defaultTemplate) : self::DEFAULT_PASSWORD_TEMPLATE,
            'reset_password_template' => trim($resetTemplate) !== '' ? trim($resetTemplate) : self::RESET_PASSWORD_TEMPLATE,
            'updated_at' => date('c'),
        ];

        $path = storage_path(self::STORAGE_FILE);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan template WhatsApp.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan template WhatsApp pengguna.');
        }
    }

    /**
     * @return array<int, string>
     */
    public static function placeholders(): array
    {
        return [
            '{{nama}}',
            '{{username}}',
            '{{password_default}}',
            '{{login_url}}',
            '{{reset_url}}',
            '{{peran}}',
            '{{sekolah}}',
            '{{telepon}}',
        ];
    }
}
