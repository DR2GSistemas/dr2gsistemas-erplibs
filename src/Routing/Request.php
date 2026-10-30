<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Petición normalizada: método, path, query, cuerpo y cabeceras.
 */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $attributes
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly string $body = '',
        public readonly array $headers = [],
        public readonly array $routeParams = [],
        public readonly array $attributes = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        // Array-copy: conserva subclaves (filter[done]) sin forzar el tipo.
        /** @var array<string, mixed> $query */
        $query = $_GET;

        return new self(
            method: $method,
            path: $path,
            query: $query,
            body: (string) file_get_contents('php://input'),
            headers: $headers
        );
    }

    /**
     * Petición de prueba, sin depender de superglobales.
     *
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        array $headers = []
    ): self {
        return new self(
            method: strtoupper($method),
            path: $path,
            query: $query,
            body: $body,
            headers: array_change_key_case($headers, CASE_LOWER)
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        $decoded = json_decode($this->body, true);

        if (!is_array($decoded)) {
            throw new \Dr2g\ErpLibs\Routing\Exception\UnprocessableEntityException('Invalid JSON body.');
        }

        return $decoded;
    }

    /**
     * Entrada del formulario o del cuerpo JSON, con la misma forma.
     *
     * @return array<string, mixed>
     */
    public function input(): array
    {
        $contentType = $this->header('content-type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            return $this->json();
        }

        if (trim($this->body) === '') {
            return $this->query;
        }

        parse_str($this->body, $form);

        /** @var array<string, mixed> $form */
        return $form;
    }

    public function param(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * Copia con los parámetros de ruta (lo usa el router al casar una ruta).
     *
     * @param array<string, string> $routeParams
     */
    public function withRouteParams(array $routeParams): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $routeParams,
            $this->attributes
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $this->routeParams,
            array_merge($this->attributes, $attributes)
        );
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
}
