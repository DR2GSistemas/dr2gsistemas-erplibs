<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Ruta registrada: método(s), patrón con segmentos dinámicos y handler.
 *
 * Patrones soportados:
 *   /users/{id}        → segmento dinámico
 *   /posts/{slug?}     → segmento opcional (solo al final)
 *
 * Un `{param}` debe ocupar el segmento completo: `/users/id-{id}` es inválido.
 */
final class Route
{
    private const SEGMENT = '/^\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}$/';

    private readonly string $pattern;

    /** @var list<array{literal: string, name: string|null, optional: bool}> */
    private readonly array $segments;

    /**
     * @param list<string> $methods en mayúsculas
     * @param callable(Request): mixed|array{0: object|callable, 1: string}|string $handler
     */
    public function __construct(
        public readonly array $methods,
        string $pattern,
        private readonly mixed $handler,
        public readonly ?string $name = null
    ) {
        $this->pattern = self::normalizePattern($pattern);
        $this->segments = self::compile($this->pattern);
        $this->assertOptionalSegmentsAreTrailing();
    }

    public static function normalizePattern(string $pattern): string
    {
        $pattern = '/' . trim($pattern, '/');

        return $pattern === '//' ? '/' : $pattern;
    }

    /**
     * @return list<array{literal: string, name: string|null, optional: bool}>
     */
    private static function compile(string $pattern): array
    {
        if ($pattern === '/') {
            return [];
        }

        $segments = [];

        foreach (explode('/', trim($pattern, '/')) as $raw) {
            if (preg_match(self::SEGMENT, $raw, $match) === 1) {
                $segments[] = ['literal' => '', 'name' => $match[1], 'optional' => ($match[2] ?? '') === '?'];
                continue;
            }

            if (str_contains($raw, '{') || str_contains($raw, '}')) {
                throw new \InvalidArgumentException(
                    "Route {$pattern}: the parameter '{$raw}' must fill a whole segment."
                );
            }

            $segments[] = ['literal' => $raw, 'name' => null, 'optional' => false];
        }

        return $segments;
    }

    private function assertOptionalSegmentsAreTrailing(): void
    {
        $seenOptional = false;

        foreach ($this->segments as $segment) {
            if ($segment['optional']) {
                $seenOptional = true;
                continue;
            }

            if ($seenOptional) {
                throw new \InvalidArgumentException(
                    "Route {$this->pattern}: optional segments are only allowed at the end."
                );
            }
        }
    }

    public function named(string $name): self
    {
        return new self($this->methods, $this->pattern, $this->handler, $name);
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Especificidad: cada segmento estático suma; los dinámicos restan. Así
     * `/users/new` gana a `/users/{id}`.
     */
    public function score(): int
    {
        $score = 0;

        foreach ($this->segments as $segment) {
            $score += $segment['name'] === null ? 1000 : 0;
        }

        return $score - count($this->segments);
    }

    /**
     * @return array<string, string>|null parámetros capturados o null si no casa
     */
    public function matchPath(string $path): ?array
    {
        $target = self::normalizePattern($path);
        $parts = $target === '/' ? [] : explode('/', trim($target, '/'));

        $required = 0;
        $optional = 0;

        foreach ($this->segments as $segment) {
            if ($segment['optional']) {
                $optional++;
            } else {
                $required++;
            }
        }

        if (count($parts) < $required || count($parts) > $required + $optional) {
            return null;
        }

        $params = [];
        $index = 0;

        foreach ($this->segments as $segment) {
            if (!isset($parts[$index])) {
                break;
            }

            if ($segment['name'] !== null) {
                $params[$segment['name']] = rawurldecode($parts[$index]);
                $index++;
                continue;
            }

            if ($segment['literal'] !== $parts[$index]) {
                return null;
            }

            $index++;
        }

        return $params;
    }

    /**
     * URL concreta para esta ruta sustituyendo parámetros.
     *
     * @param array<string, string|int> $params
     */
    public function url(array $params = []): string
    {
        if ($this->segments === []) {
            return '/';
        }

        $parts = [];

        foreach ($this->segments as $segment) {
            if ($segment['name'] === null) {
                $parts[] = $segment['literal'];
                continue;
            }

            if (!isset($params[$segment['name']])) {
                if ($segment['optional']) {
                    break;
                }

                throw new \InvalidArgumentException(
                    "Missing parameter '{$segment['name']}' for route {$this->pattern}."
                );
            }

            $parts[] = rawurlencode((string) $params[$segment['name']]);
        }

        return '/' . implode('/', $parts);
    }
}
