<?php

namespace App\Services;

use App\Models\UserActivityLog;
use App\Support\UserActivityLogSetting;
use Core\Auth;
use Core\Log;
use Core\Request;
use Core\Response;
use Throwable;

class UserActivityLogger
{
    /**
     * Keys that should always be redacted from persisted payloads.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        '_token',
        'remember',
    ];

    private const MAX_TEXT_LENGTH = 65000;
    private static ?string $customDescription = null;

    public static function describe(?string $description): void
    {
        static::$customDescription = $description !== null ? trim($description) : null;
    }

    public static function log(
        Request $request,
        ?Response $response = null,
        ?string $routeAction = null,
        ?Throwable $exception = null
    ): void {
        try {
            $user = Auth::user();
            $statusCode = $response?->getStatus() ?? ($exception !== null ? 500 : null);
            $actionDescription = static::resolveDescription($request, $routeAction);

            UserActivityLog::create([
                'user_id' => $user['id'] ?? null,
                'actor_name' => $user['name'] ?? null,
                'actor_username' => $user['username'] ?? null,
                'actor_role' => $user['role'] ?? null,
                'request_method' => $request->getMethod(),
                'request_path' => $request->getPath(),
                'route_action' => $routeAction,
                'action_description' => $actionDescription,
                'status_code' => $statusCode,
                'ip_address' => $request->ip(),
                'user_agent' => static::truncate($request->userAgent()),
                'payload' => static::buildPayloadSnapshot($request),
                'error_message' => $exception ? static::truncate($exception->getMessage()) : null,
            ]);

            $maxLogs = UserActivityLogSetting::getLimit();
            if ($maxLogs > 0) {
                UserActivityLog::enforceLimit($maxLogs);
            }
        } catch (Throwable $loggingError) {
            try {
                Log::channel('system')->error('Failed to record user activity log', [
                    'error' => $loggingError->getMessage(),
                ]);
            } catch (Throwable) {
                // Avoid infinite loops if logging also fails.
            }
        }
    }

    private static function resolveDescription(Request $request, ?string $routeAction): string
    {
        $custom = static::$customDescription;
        static::$customDescription = null;

        if ($custom !== null && $custom !== '') {
            return static::truncate($custom, 255) ?? '';
        }

        if ($routeAction !== null && $routeAction !== '') {
            $generated = static::generateDescriptionFromRoute($request, $routeAction);
            if ($generated !== '') {
                return $generated;
            }
        }

        return sprintf('%s %s', strtoupper($request->getMethod()), $request->getPath());
    }

    private static function generateDescriptionFromRoute(Request $request, string $routeAction): string
    {
        if ($routeAction === '' || $routeAction === 'callable') {
            return '';
        }

        $class = $routeAction;
        $method = '';

        if (str_contains($routeAction, '@')) {
            [$class, $method] = explode('@', $routeAction, 2);
        }

        $subject = static::humanizeClass($class);
        $verb = static::verbForMethod($method, $request->getMethod());

        if ($subject === '' && $verb === '') {
            return '';
        }

        $sentence = trim(sprintf('%s %s', $verb ?: 'Mengakses', strtolower($subject ?: $request->getPath())));

        return ucfirst($sentence);
    }

    private static function humanizeClass(string $class): string
    {
        if ($class === '') {
            return '';
        }

        if (str_contains($class, '\\')) {
            $class = substr($class, strrpos($class, '\\') + 1);
        }

        $class = preg_replace('/Controller$/', '', $class);
        $class = trim((string) $class);

        if ($class === '') {
            return '';
        }

        $withSpaces = preg_replace('/(?<!^)[A-Z]/', ' $0', $class);

        return trim((string) $withSpaces);
    }

    private static function verbForMethod(string $methodName, string $httpMethod): string
    {
        $method = strtolower($methodName);

        return match (true) {
            str_starts_with($method, 'store'),
            str_starts_with($method, 'create'),
            str_starts_with($method, 'submit'),
            $httpMethod === 'POST' => 'Membuat',

            str_starts_with($method, 'update'),
            str_starts_with($method, 'edit'),
            str_starts_with($method, 'patch'),
            $httpMethod === 'PUT',
            $httpMethod === 'PATCH' => 'Memperbarui',

            str_starts_with($method, 'destroy'),
            str_starts_with($method, 'delete'),
            str_starts_with($method, 'remove'),
            str_starts_with($method, 'hapus'),
            $httpMethod === 'DELETE' => 'Menghapus',

            str_starts_with($method, 'show'),
            str_starts_with($method, 'detail') => 'Melihat',

            str_starts_with($method, 'index'),
            str_starts_with($method, 'list'),
            str_starts_with($method, 'dashboard'),
            $httpMethod === 'GET' => 'Mengakses',

            str_starts_with($method, 'approve') => 'Menyetujui',
            str_starts_with($method, 'reject') => 'Menolak',
            str_starts_with($method, 'export') => 'Mengekspor',
            str_starts_with($method, 'import') => 'Mengimpor',
            str_starts_with($method, 'print') => 'Mencetak',
            str_starts_with($method, 'download') => 'Mengunduh',
            str_starts_with($method, 'upload') => 'Mengunggah',
            str_starts_with($method, 'login') => 'Masuk',
            str_starts_with($method, 'logout') => 'Keluar',
            str_starts_with($method, 'reset') => 'Mereset',
            str_starts_with($method, 'send') => 'Mengirim',
            default => 'Mengakses',
        };
    }

    private static function buildPayloadSnapshot(Request $request): ?string
    {
        $payload = $request->all();
        $jsonBody = $request->json();

        if (is_array($jsonBody) && $jsonBody !== []) {
            $payload['_json'] = $jsonBody;
        }

        $files = $request->files();

        if ($files !== []) {
            $payload['_files'] = static::summarizeFiles($files);
        }

        if ($payload === []) {
            return null;
        }

        $sanitized = static::sanitizePayload($payload);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return null;
        }

        return static::truncate($encoded);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $shouldRedact = in_array($normalizedKey, self::SENSITIVE_KEYS, true)
                || str_contains($normalizedKey, 'password');

            if ($shouldRedact) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = static::sanitizePayload($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    private static function summarizeFiles(array $files): array
    {
        $summary = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                if (array_key_exists('name', $file)) {
                    $summary[$key] = [
                        'name' => $file['name'],
                        'type' => $file['type'] ?? null,
                        'size' => $file['size'] ?? null,
                        'error' => $file['error'] ?? null,
                    ];
                } else {
                    $summary[$key] = static::summarizeFiles($file);
                }
            }
        }

        return $summary;
    }

    private static function truncate(?string $value, int $maxLength = self::MAX_TEXT_LENGTH): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($maxLength <= 0) {
            return '';
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}
