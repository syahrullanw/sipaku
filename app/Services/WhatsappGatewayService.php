<?php

namespace App\Services;

use App\Models\WhatsappGatewaySetting;
use App\Models\WhatsappMessageQueue;
use DateTimeImmutable;

class WhatsappGatewayService
{
    private const MAX_ATTEMPTS = 3;

    /**
     * Normalize phone number into international format (defaulting to 62 for leading zero).
     */
    public static function normalizePhone(string $phone): string
    {
        $sanitized = preg_replace('/[^0-9+]/', '', trim($phone));

        if ($sanitized === null || $sanitized === '') {
            return '';
        }

        if (str_starts_with($sanitized, '+')) {
            $sanitized = substr($sanitized, 1) ?: '';
        }

        if ($sanitized === '') {
            return '';
        }

        if (str_starts_with($sanitized, '0')) {
            $sanitized = '62' . ltrim($sanitized, '0');
        }

        return $sanitized;
    }

    /**
     * Replace simple {{placeholder}} tokens with provided variables (case-insensitive).
     *
     * @param array<string, scalar|null> $variables
     */
    public static function renderTemplate(string $template, array $variables): string
    {
        if ($template === '' || empty($variables)) {
            return $template;
        }

        $rendered = $template;

        foreach ($variables as $key => $value) {
            $replacement = '';
            if (is_bool($value)) {
                $replacement = $value ? '1' : '0';
            } elseif ($value !== null) {
                $replacement = (string) $value;
            }

            $pattern = '/{{\s*' . preg_quote((string) $key, '/') . '\s*}}/i';
            $rendered = preg_replace($pattern, $replacement, $rendered) ?? $rendered;
        }

        return $rendered;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function send(array $options, ?array $settings = null): bool
    {
        $result = static::sendDetailed($options, $settings);

        return $result['success'];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, status: int|null, response: string|null, error: string|null, payload: array<string, string>|null}
     */
    /**
     * @param array<string, mixed> $options
     * @return array{
     *     success: bool,
     *     status: int|null,
     *     response: string|null,
     *     error: string|null,
     *     payload: array<string, string>|null,
     *     queued: bool,
     *     duplicate: bool
     * }
     */
    public static function sendDetailed(array $options, ?array $settings = null): array
    {
        $result = [
            'success' => false,
            'status' => null,
            'response' => null,
            'error' => null,
            'payload' => null,
            'queued' => false,
            'duplicate' => false,
        ];

        $settings ??= WhatsappGatewaySetting::first();

        if ($settings === null) {
            $result['error'] = 'Pengaturan gateway belum tersedia.';

            return $result;
        }

        $variables = $options['variables'] ?? [];
        if (!is_array($variables)) {
            $variables = [];
        }

        $phone = static::normalizePhone((string) ($options['phone'] ?? ''));
        if ($phone === '') {
            $result['error'] = 'Nomor WhatsApp tidak valid.';

            return $result;
        }

        $variables += [
            'nomor_hp_siswa' => $phone,
            'no_hp_siswa' => $phone,
            'nomor_hp' => $phone,
            'no_hp' => $phone,
            'whatsapp_target' => $phone,
            'target' => $phone,
            'phone' => $phone,
            'waha_chat_id' => $phone . '@c.us',
            'chat_id' => $phone . '@c.us',
            'chatId' => $phone . '@c.us',
        ];

        $template = (string) ($options['template'] ?? ($settings['default_message_value'] ?? ''));
        $message = static::renderTemplate($template, $variables);

        if (trim($message) === '') {
            $result['error'] = 'Pesan kosong sehingga tidak dapat dikirim.';

            return $result;
        }

        $payload = static::isWahaSettings($settings)
            ? static::buildWahaPayload($phone, $message, $variables, $settings)
            : static::buildGenericPayload($phone, $message, $variables, $settings);

        $result['payload'] = $payload;

        $sendImmediately = (bool) ($options['send_immediately'] ?? false);

        if ($sendImmediately) {
            $requestResult = static::performRequestDetailed($settings, $payload);

            return [
                'success' => $requestResult['success'],
                'status' => $requestResult['status'],
                'response' => $requestResult['response'],
                'error' => $requestResult['error'],
                'payload' => $payload,
                'queued' => false,
                'duplicate' => false,
            ];
        }

        $queueResult = static::queueOrSend($phone, $message, $payload, $settings);

        return [
            'success' => $queueResult['success'],
            'status' => $queueResult['status'],
            'response' => $queueResult['response'],
            'error' => $queueResult['error'],
            'payload' => $payload,
            'queued' => $queueResult['queued'],
            'duplicate' => $queueResult['duplicate'],
        ];
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $settings
     * @return array{success: bool, status: int|null, response: string|null, error: string|null, queued: bool, duplicate: bool}
     */
    protected static function queueOrSend(string $phone, string $message, array $payload, array $settings): array
    {
        $outcome = [
            'success' => false,
            'status' => null,
            'response' => null,
            'error' => null,
            'queued' => false,
            'duplicate' => false,
        ];

        $payloadJson = static::encodePayload($payload);
        if ($payloadJson === null) {
            $outcome['error'] = 'Gagal menyiapkan payload pesan.';

            return $outcome;
        }

        $messageHash = static::calculateMessageHash($phone, $message, $payloadJson);

        $existing = WhatsappMessageQueue::findActiveByHash($messageHash);
        if ($existing !== null) {
            $outcome['success'] = true;
            $outcome['queued'] = true;
            $outcome['duplicate'] = true;

            return $outcome;
        }

        $interval = static::resolveIntervalSeconds($settings);
        $now = new DateTimeImmutable('now');
        $nextAvailable = static::determineNextAvailableTime($interval, $now);

        if ($nextAvailable <= $now) {
            return static::sendImmediately($phone, $message, $payload, $payloadJson, $messageHash, $settings, $interval, $now);
        }

        $timestamp = $now->format('Y-m-d H:i:s');

        $queueId = WhatsappMessageQueue::createPending([
            'phone' => $phone,
            'message' => $message,
            'payload' => $payloadJson,
            'message_hash' => $messageHash,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $nextAvailable->format('Y-m-d H:i:s'),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($queueId === null) {
            $outcome['error'] = 'Gagal menambahkan pesan ke antrian.';

            return $outcome;
        }

        $outcome['success'] = true;
        $outcome['queued'] = true;

        return $outcome;
    }

    protected static function resolveIntervalSeconds(array $settings): int
    {
        $interval = (int) ($settings['send_interval_seconds'] ?? 30);

        if ($interval < 5) {
            $interval = 5;
        }

        if ($interval > 86400) {
            $interval = 86400;
        }

        return $interval;
    }

    /**
     * @param array<string, scalar|null> $variables
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    protected static function buildGenericPayload(string $phone, string $message, array $variables, array $settings): array
    {
        $payload = [];

        $targetKey = (string) ($settings['default_parameter_key'] ?? 'target');
        $targetTemplate = (string) ($settings['default_parameter_value'] ?? '');
        $targetValue = $targetTemplate !== ''
            ? static::renderTemplate($targetTemplate, $variables)
            : $phone;
        $targetValue = trim($targetValue) !== '' ? $targetValue : $phone;

        $messageKey = (string) ($settings['default_message_key'] ?? 'message');

        $payload[$targetKey] = $targetValue;
        $payload[$messageKey] = $message;

        foreach (['extra_parameter_one', 'extra_parameter_two'] as $extraKey) {
            $parameterKey = (string) ($settings[$extraKey . '_key'] ?? '');
            if ($parameterKey === '') {
                continue;
            }
            $parameterTemplate = (string) ($settings[$extraKey . '_value'] ?? '');
            $payload[$parameterKey] = $parameterTemplate !== ''
                ? static::renderTemplate($parameterTemplate, $variables)
                : '';
        }

        return $payload;
    }

    /**
     * @param array<string, scalar|null> $variables
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    protected static function buildWahaPayload(string $phone, string $message, array $variables, array $settings): array
    {
        $chatTemplate = (string) ($settings['default_parameter_value'] ?? '{{waha_chat_id}}');
        $chatId = $chatTemplate !== ''
            ? static::renderTemplate($chatTemplate, $variables)
            : $phone . '@c.us';
        $chatId = static::normalizeWahaChatId($chatId, $phone);

        $sessionTemplate = (string) ($settings['extra_parameter_one_value'] ?? 'default');
        $session = $sessionTemplate !== ''
            ? trim(static::renderTemplate($sessionTemplate, $variables))
            : 'default';
        $session = $session !== '' ? $session : 'default';

        $payload = [
            'session' => $session,
            'chatId' => $chatId,
            'text' => $message,
        ];

        $extraKey = trim((string) ($settings['extra_parameter_two_key'] ?? ''));
        if ($extraKey !== '' && !array_key_exists($extraKey, $payload)) {
            $extraValue = (string) ($settings['extra_parameter_two_value'] ?? '');
            $payload[$extraKey] = $extraValue !== ''
                ? static::renderTemplate($extraValue, $variables)
                : '';
        }

        return $payload;
    }

    protected static function normalizeWahaChatId(string $value, string $phone): string
    {
        $chatId = trim($value);

        if ($chatId === '') {
            return $phone . '@c.us';
        }

        if (str_contains($chatId, '@')) {
            return $chatId;
        }

        $number = preg_replace('/[^0-9]/', '', $chatId) ?? '';

        if ($number === '') {
            $number = $phone;
        }

        return $number . '@c.us';
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected static function isWahaSettings(array $settings): bool
    {
        return strtolower((string) ($settings['template'] ?? '')) === 'waha';
    }

    protected static function determineNextAvailableTime(int $interval, DateTimeImmutable $now): DateTimeImmutable
    {
        $next = $now;

        $latestSentRaw = WhatsappMessageQueue::latestSentAt();
        $latestSent = static::toDateTime($latestSentRaw);
        if ($latestSent !== null) {
            $candidate = $latestSent->modify('+' . $interval . ' seconds');
            if ($candidate > $next) {
                $next = $candidate;
            }
        }

        $latestPendingRaw = WhatsappMessageQueue::latestPendingAvailableAt();
        $latestPending = static::toDateTime($latestPendingRaw);
        if ($latestPending !== null) {
            if ($latestPending > $next) {
                $next = $latestPending;
            }

            $candidate = $latestPending->modify('+' . $interval . ' seconds');
            if ($candidate > $next) {
                $next = $candidate;
            }
        }

        return $next;
    }

    protected static function toDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function calculateMessageHash(string $phone, string $message, string $payloadJson): string
    {
        return hash('sha256', $phone . '|' . $message . '|' . $payloadJson);
    }

    /**
     * @param array<string, string> $payload
     */
    protected static function encodePayload(array $payload): ?string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, mixed> $settings
     * @return array{success: bool, status: int|null, response: string|null, error: string|null, queued: bool, duplicate: bool}
     */
    protected static function sendImmediately(
        string $phone,
        string $message,
        array $payload,
        string $payloadJson,
        string $messageHash,
        array $settings,
        int $interval,
        DateTimeImmutable $now
    ): array {
        $outcome = [
            'success' => false,
            'status' => null,
            'response' => null,
            'error' => null,
            'queued' => false,
            'duplicate' => false,
        ];

        $timestamp = $now->format('Y-m-d H:i:s');

        $queueId = WhatsappMessageQueue::createProcessing([
            'phone' => $phone,
            'message' => $message,
            'payload' => $payloadJson,
            'message_hash' => $messageHash,
            'status' => 'processing',
            'attempts' => 1,
            'available_at' => $timestamp,
            'last_attempt_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($queueId === null) {
            $outcome['error'] = 'Gagal menyiapkan pengiriman pesan.';

            return $outcome;
        }

        $requestResult = static::performRequestDetailed($settings, $payload);

        $outcome['success'] = $requestResult['success'];
        $outcome['status'] = $requestResult['status'];
        $outcome['response'] = $requestResult['response'];
        $outcome['error'] = $requestResult['error'];

        if ($requestResult['success']) {
            WhatsappMessageQueue::markSent($queueId, $requestResult['response'], $requestResult['status']);

            return $outcome;
        }

        $errorMessage = $requestResult['error'] ?? 'Gagal mengirim pesan.';

        if (self::MAX_ATTEMPTS <= 1) {
            WhatsappMessageQueue::markFailed($queueId, $errorMessage, $requestResult['response'], $requestResult['status']);

            return $outcome;
        }

        $retryAt = $now->modify('+' . $interval . ' seconds');
        WhatsappMessageQueue::markRetry($queueId, $retryAt, $errorMessage, $requestResult['response'], $requestResult['status']);

        $outcome['queued'] = true;

        return $outcome;
    }

    /**
     * @return array{processed: int, sent: int, failed: int, requeued: int}
     */
    public static function dispatchPending(?int $limit = null): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $requeued = 0;

        $settings = WhatsappGatewaySetting::first();

        if ($settings === null) {
            return [
                'processed' => $processed,
                'sent' => $sent,
                'failed' => $failed,
                'requeued' => $requeued,
            ];
        }

        $limit = $limit !== null ? max(1, $limit) : null;
        $interval = static::resolveIntervalSeconds($settings);

        while ($limit === null || $processed < $limit) {
            $job = WhatsappMessageQueue::claimDue();
            if ($job === null) {
                break;
            }

            $processed++;

            $payloadRaw = (string) ($job['payload'] ?? '');
            $payload = json_decode($payloadRaw, true);

            if (!is_array($payload)) {
                WhatsappMessageQueue::markFailed((int) $job['id'], 'Payload tidak valid.', null, null);
                $failed++;

                if ($interval > 0 && ($limit === null || $processed < $limit)) {
                    sleep($interval);
                }

                continue;
            }

            $sendResult = static::performRequestDetailed($settings, $payload);

            if ($sendResult['success']) {
                WhatsappMessageQueue::markSent((int) $job['id'], $sendResult['response'], $sendResult['status']);
                $sent++;
            } else {
                $errorMessage = $sendResult['error'] ?? 'Gagal mengirim pesan.';
                $attempts = (int) ($job['attempts'] ?? 1);

                if ($attempts >= self::MAX_ATTEMPTS) {
                    WhatsappMessageQueue::markFailed((int) $job['id'], $errorMessage, $sendResult['response'], $sendResult['status']);
                    $failed++;
                } else {
                    $nextAvailable = (new DateTimeImmutable('now'))->modify('+' . $interval . ' seconds');
                    WhatsappMessageQueue::markRetry((int) $job['id'], $nextAvailable, $errorMessage, $sendResult['response'], $sendResult['status']);
                    $requeued++;
                }
            }

            if ($interval > 0 && ($limit === null || $processed < $limit)) {
                sleep($interval);
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'requeued' => $requeued,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string> $payload
     * @return array{success: bool, status: int|null, response: string|null, error: string|null}
     */
    protected static function performRequestDetailed(array $settings, array $payload): array
    {
        $result = [
            'success' => false,
            'status' => null,
            'response' => null,
            'error' => null,
        ];

        $url = trim((string) ($settings['base_url'] ?? ''));

        if ($url === '') {
            $result['error'] = 'URL gateway kosong.';

            return $result;
        }

        $bodyType = (string) ($settings['body_type'] ?? 'json');
        $authorization = trim((string) ($settings['authorization_token'] ?? ''));
        $isWaha = static::isWahaSettings($settings);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $result['error'] = 'Tidak dapat menginisialisasi koneksi cURL.';

                return $result;
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            $headers = ['Accept: application/json'];

            switch ($bodyType) {
                case 'form-data':
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    break;
                case 'x-www-form-urlencoded':
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                    break;
                default:
                    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                    if ($jsonPayload === false) {
                        curl_close($ch);
                        $result['error'] = 'Gagal mengubah payload ke JSON.';

                        return $result;
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                    $headers[] = 'Content-Type: application/json';
                    break;
            }

            if ($authorization !== '') {
                if ($isWaha) {
                    $headers[] = 'X-Api-Key: ' . $authorization;
                } else {
                    $headers[] = 'Authorization: ' . $authorization;
                }
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = $response === false ? curl_error($ch) : null;
            curl_close($ch);

            $result['status'] = $status > 0 ? $status : null;
            $result['response'] = is_string($response) ? $response : null;
            $result['error'] = $error;
            $result['success'] = $response !== false && $status >= 200 && $status < 300;

            if (!$result['success'] && $result['error'] === null && $status > 0 && $status >= 400) {
                $result['error'] = 'Permintaan ditolak dengan status HTTP ' . $status . '.';
            }

            return $result;
        }

        $headers = ['Accept: application/json'];
        $content = '';

        switch ($bodyType) {
            case 'x-www-form-urlencoded':
            case 'form-data':
                $content = http_build_query($payload);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                break;
            default:
                $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if ($jsonPayload === false) {
                    $result['error'] = 'Gagal mengubah payload ke JSON.';

                    return $result;
                }
                $content = $jsonPayload;
                $headers[] = 'Content-Type: application/json';
                break;
        }

        if ($authorization !== '') {
            $headers[] = $isWaha
                ? 'X-Api-Key: ' . $authorization
                : 'Authorization: ' . $authorization;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 15,
                'header' => implode("\r\n", $headers),
                'content' => $content,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        $result['response'] = is_string($response) ? $response : null;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/i', $headerLine, $matches)) {
                    $result['status'] = (int) $matches[1];
                    break;
                }
            }
        }

        if ($response === false) {
            $result['error'] = 'Gagal mengirim permintaan ke gateway.';

            return $result;
        }

        if ($result['status'] !== null) {
            $result['success'] = $result['status'] >= 200 && $result['status'] < 300;
            if (!$result['success'] && $result['error'] === null) {
                $result['error'] = 'Permintaan ditolak dengan status HTTP ' . $result['status'] . '.';
            }
        } else {
            $result['success'] = true;
        }

        return $result;
    }
}
