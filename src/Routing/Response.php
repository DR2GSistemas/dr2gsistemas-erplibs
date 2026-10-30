<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Respuesta inmutable. Cualquier tipo de retorno de un handler se puede enviar:
 * las acciones simplemente devuelven `Response` cuando necesitan control total.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): self
    {
        return new self('', $status, ['Location' => $url] + $headers);
    }

    public static function empty(int $status = 204): self
    {
        return new self('', $status);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [$name => $value] + $this->headers);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}", true);
            }
        }

        echo $this->body;
    }
}
