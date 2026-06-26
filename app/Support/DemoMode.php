<?php

namespace App\Support;

use function array_key_exists;
use function config;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function hash_equals;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function preg_replace;
use function str_repeat;
use function storage_path;
use function strlen;
use function trim;

class DemoMode
{
    private const STORAGE_FILE = 'settings/demo-mode.json';
    private const DEFAULT_PLACEHOLDER = 'Disembunyikan (Mode Demo)';

    private static ?bool $cachedEnabled = null;

    public static function isEnabled(): bool
    {
        if (self::$cachedEnabled !== null) {
            return self::$cachedEnabled;
        }

        $enabled = (bool) config('demo.enabled', false);
        $path = self::path();

        if (is_file($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && array_key_exists('enabled', $data)) {
                    $enabled = (bool) $data['enabled'];
                }
            }
        }

        self::$cachedEnabled = $enabled;

        return $enabled;
    }

    public static function setEnabled(bool $enabled): void
    {
        $payload = [
            'enabled' => $enabled,
            'updated_at' => date('c'),
        ];

        $path = self::path();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori penyimpanan mode demo.');
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menyimpan status mode demo.');
        }

        self::$cachedEnabled = $enabled;
    }

    public static function password(): string
    {
        return trim((string) config('demo.password', ''));
    }

    public static function validatePassword(string $input): bool
    {
        $expected = self::password();
        $candidate = trim($input);

        if ($expected === '') {
            return $candidate === '';
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * @param array<int, array<string, mixed>> $teachers
     *
     * @return array<int, array<string, mixed>>
     */
    public static function maskTeachers(array $teachers): array
    {
        if (!self::isEnabled()) {
            return $teachers;
        }

        return array_map(
            static fn ($teacher) => is_array($teacher) ? self::maskTeacher($teacher) : $teacher,
            $teachers
        );
    }

    /**
     * @param array<string, mixed>|null $teacher
     *
     * @return array<string, mixed>|null
     */
    public static function maskTeacher(?array $teacher): ?array
    {
        if (!self::isEnabled() || $teacher === null) {
            return $teacher;
        }

        $masked = $teacher;

        $masked['nip'] = self::maskIdentifier($teacher['nip'] ?? null);
        $masked['nik'] = self::maskIdentifier($teacher['nik'] ?? null);
        $masked['nuptk'] = self::maskIdentifier($teacher['nuptk'] ?? null);
        $masked['nomor_surat_tugas'] = self::maskIdentifier($teacher['nomor_surat_tugas'] ?? null);
        $masked['sk_pengangkatan'] = self::maskIdentifier($teacher['sk_pengangkatan'] ?? null);
        $masked['npwp'] = self::maskIdentifier($teacher['npwp'] ?? null);
        $masked['nama_wp'] = self::placeholderIfFilled($teacher['nama_wp'] ?? null);
        $masked['nama_ibu_kandung'] = self::placeholderIfFilled($teacher['nama_ibu_kandung'] ?? null);
        $masked['nama_pasangan'] = self::placeholderIfFilled($teacher['nama_pasangan'] ?? null);
        $masked['pekerjaan_pasangan'] = self::placeholderIfFilled($teacher['pekerjaan_pasangan'] ?? null);
        $masked['tempat_lahir'] = self::placeholderIfFilled($teacher['tempat_lahir'] ?? null);
        $masked['tanggal_lahir'] = self::maskDate($teacher['tanggal_lahir'] ?? null);
        $masked['tanggal_surat_tugas'] = self::maskDate($teacher['tanggal_surat_tugas'] ?? null);
        $masked['tmt_pengangkatan'] = self::maskDate($teacher['tmt_pengangkatan'] ?? null);

        $masked['email'] = self::maskEmail($teacher['email'] ?? null);
        $masked['telepon'] = self::maskPhone($teacher['telepon'] ?? null);

        $masked['alamat'] = self::maskAddress($teacher['alamat'] ?? null);
        $masked['alamat_jalan'] = self::maskAddress($teacher['alamat_jalan'] ?? ($teacher['alamat'] ?? null));
        $masked['dusun'] = self::maskAddress($teacher['dusun'] ?? null);
        $masked['desa'] = self::maskAddress($teacher['desa'] ?? null);
        $masked['kelurahan'] = self::maskAddress($teacher['kelurahan'] ?? null);
        $masked['kecamatan'] = self::maskAddress($teacher['kecamatan'] ?? null);
        $masked['kabupaten'] = self::maskAddress($teacher['kabupaten'] ?? null);
        $masked['provinsi'] = self::maskAddress($teacher['provinsi'] ?? null);
        $masked['kode_pos'] = self::maskIdentifier($teacher['kode_pos'] ?? null);

        $masked['rekening_bank'] = self::placeholderIfFilled($teacher['rekening_bank'] ?? null);
        $masked['rekening_nomor'] = self::maskIdentifier($teacher['rekening_nomor'] ?? null);
        $masked['rekening_nama'] = self::placeholderIfFilled($teacher['rekening_nama'] ?? null);

        return $masked;
    }

    public static function maskEmail(mixed $value): string
    {
        $email = trim((string) ($value ?? ''));

        if ($email === '') {
            return '-';
        }

        if (!self::isEnabled()) {
            return $email;
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return self::placeholder();
        }

        [$local, $domain] = $parts;
        $visible = mb_substr($local, 0, 1);
        $suffix = mb_substr($local, -1);
        $obscured = $visible . str_repeat('*', max(3, mb_strlen($local) - 2)) . $suffix;

        return $obscured . '@' . $domain;
    }

    public static function maskPhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));

        if ($digits === '') {
            return '-';
        }

        if (!self::isEnabled()) {
            return $digits;
        }

        $visible = substr($digits, -3);
        $prefix = str_repeat('*', max(5, strlen($digits) - 3));

        return $prefix . $visible;
    }

    public static function maskIdentifier(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '-';
        }

        if (!self::isEnabled()) {
            return $text;
        }

        $visible = substr($text, -3);
        $maskLength = max(6, strlen($text) - 3);

        return str_repeat('*', $maskLength) . $visible;
    }

    public static function maskAddress(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '-';
        }

        if (!self::isEnabled()) {
            return $text;
        }

        return self::placeholder();
    }

    public static function maskDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '-';
        }

        if (!self::isEnabled()) {
            return $text;
        }

        return '****-**-**';
    }

    public static function placeholder(): string
    {
        $label = trim((string) config('demo.mask_label', self::DEFAULT_PLACEHOLDER));

        return $label !== '' ? $label : self::DEFAULT_PLACEHOLDER;
    }

    private static function placeholderIfFilled(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '-';
        }

        return self::placeholder();
    }

    private static function path(): string
    {
        return storage_path(self::STORAGE_FILE);
    }
}
