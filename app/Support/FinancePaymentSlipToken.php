<?php

declare(strict_types=1);

namespace App\Support;

use function absolute_url;
use function config;
use function hash_equals;

class FinancePaymentSlipToken
{
    public static function buildPublicUrl(array $payment): ?string
    {
        $token = static::tokenForPayment($payment);

        if ($token === null) {
            return null;
        }

        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            return null;
        }

        return absolute_url(sprintf('p/s/%d/%s', $paymentId, $token));
    }

    public static function tokenForPayment(array $payment): ?string
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        $code = trim((string) ($payment['kode_transaksi'] ?? ''));
        $paidAt = static::resolvePaidAt($payment);

        if ($paymentId <= 0 || $code === '' || $paidAt === null) {
            return null;
        }

        return static::sign($paymentId, $code, $paidAt);
    }

    public static function isValid(string $token, array $payment): bool
    {
        $expected = static::tokenForPayment($payment);

        if ($expected !== null && hash_equals($expected, $token)) {
            return true;
        }

        // Accept legacy hex-encoded tokens so previously terkirim links tetap valid.
        $legacy = static::legacyToken($payment);

        return $legacy !== null && hash_equals($legacy, $token);
    }

    private static function resolvePaidAt(array $payment): ?string
    {
        $candidates = [
            $payment['tanggal_bayar'] ?? null,
            $payment['diverifikasi_pada'] ?? null,
            $payment['created_at'] ?? null,
            $payment['updated_at'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalized = static::normalizeDate($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private static function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $timestamp = strtotime($trimmed);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function sign(int $paymentId, string $code, string $paidAt): string
    {
        $secret = static::secret();
        $payload = $paymentId . '|' . $code . '|' . $paidAt . '|' . $secret;

        $raw = hash_hmac('sha256', $payload, $secret, true);

        return static::base64url($raw);
    }

    private static function legacyToken(array $payment): ?string
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        $code = trim((string) ($payment['kode_transaksi'] ?? ''));
        $paidAt = static::resolvePaidAt($payment);

        if ($paymentId <= 0 || $code === '' || $paidAt === null) {
            return null;
        }

        $secret = static::secret();
        $payload = $paymentId . '|' . $code . '|' . $paidAt . '|' . $secret;

        return hash_hmac('sha256', $payload, $secret);
    }

    private static function secret(): string
    {
        $seed = (string) config('app.url', 'siakad-smk');

        if ($seed === '') {
            $seed = 'siakad-smk';
        }

        return hash('sha256', $seed);
    }

    private static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
