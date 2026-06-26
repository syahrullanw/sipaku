<?php

namespace Core;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = [],
        protected ?string $filePath = null,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function file(string $path, int $status = 200, array $headers = []): self
    {
        return new self('', $status, $headers, $path);
    }

    /**
     * @param mixed $data
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);

        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}", true, $this->status);
        }

        if ($this->filePath !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $handle = @fopen($this->filePath, 'rb');
            if ($handle === false) {
                return;
            }

            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    break;
                }

                echo $chunk;
            }

            fclose($handle);

            return;
        }

        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
