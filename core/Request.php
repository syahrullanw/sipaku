<?php

namespace Core;

class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        protected array $query,
        protected array $post,
        protected array $server,
        protected array $files,
        protected ?string $rawBody = null,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, null);
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = str_replace('\\', '/', parse_url($uri, PHP_URL_PATH) ?: '/');
        $script = str_replace('\\', '/', $this->server['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $projectDir = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');

        foreach ([$scriptDir, $projectDir] as $basePath) {
            if ($basePath === '' || $basePath === '/') {
                continue;
            }

            if (str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath)) ?: '/';
                break;
            }
        }

        return '/' . ltrim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function contentLength(): int
    {
        $length = $this->server['CONTENT_LENGTH'] ?? $this->server['HTTP_CONTENT_LENGTH'] ?? null;

        return is_numeric($length) ? max(0, (int) $length) : 0;
    }

    public function isPostSizeExceeded(): bool
    {
        if ($this->getMethod() !== 'POST') {
            return false;
        }

        $contentLength = $this->contentLength();
        if ($contentLength <= 0) {
            return false;
        }

        $maxBytes = self::iniSizeToBytes((string) ini_get('post_max_size'));
        if ($maxBytes <= 0 || $contentLength <= $maxBytes) {
            return false;
        }

        return empty($this->post) && empty($this->files);
    }

    public function postMaxSize(): string
    {
        $value = trim((string) ini_get('post_max_size'));

        return $value !== '' ? $value : 'unknown';
    }

    /**
     * Return JSON-decoded body if possible.
     */
    public function json(): mixed
    {
        if ($this->rawBody === null) {
            $contentType = (string) ($this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? '');
            if ($contentType !== '' && !str_contains(strtolower($contentType), 'json')) {
                return null;
            }

            $rawBody = file_get_contents('php://input');
            $this->rawBody = $rawBody === false ? '' : $rawBody;
        }

        if ($this->rawBody === '') {
            return null;
        }

        $decoded = json_decode($this->rawBody, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $this->server[$normalized] ?? $default;
    }

    public function requestId(): ?string
    {
        $headerValue = $this->header('X-Request-Id');
        if (is_string($headerValue)) {
            $trimmed = trim($headerValue);
            if ($trimmed !== '') {
                return substr($trimmed, 0, 100);
            }
        }

        $serverValue = $this->server['UNIQUE_ID'] ?? null;
        if (is_string($serverValue)) {
            $trimmed = trim($serverValue);
            if ($trimmed !== '') {
                return substr($trimmed, 0, 100);
            }
        }

        return null;
    }

    public function ip(): ?string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!isset($this->server[$key]) || $this->server[$key] === '') {
                continue;
            }

            $value = $this->server[$key];
            if (is_string($value) && str_contains($value, ',')) {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }

            return is_string($value) ? trim($value) : null;
        }

        return null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->server['HTTP_USER_AGENT'] ?? null;

        return is_string($agent) && $agent !== '' ? $agent : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Retrieve a single uploaded file entry.
     *
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    private static function iniSizeToBytes(string $value): int
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return 0;
        }

        $unit = strtolower(substr($normalized, -1));
        $number = (float) $normalized;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }
}
