<?php

namespace Dr2g\ErpLibs\Auth;

use Dr2g\ErpLibs\Audit\RateLimiter;
use Dr2g\ErpLibs\Cache\ArrayCache;
use Dr2g\ErpLibs\Cache\CacheInterface;

/**
 * Límite de intentos de autenticación.
 *
 * Combina dos ventanas independientes:
 *
 * - **Por IP + email**: 5 intentos cada 15 minutos (fuerza bruta dirigida).
 * - **Por email**: 10 intentos cada hora (rociado desde muchas IPs).
 *
 * Al superar cualquiera, `attempt()` devuelve `allowed: false` y los segundos de
 * espera. `clear()` reinicia los contadores tras un login correcto.
 */
final class LoginThrottle
{
    private readonly RateLimiter $byIp;

    private readonly RateLimiter $byEmail;

    private readonly ?\Closure $clock;

    /**
     * @param array<string, int> $options per_ip, per_email, window, email_window
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly array $options = [],
        ?callable $clock = null
    ) {
        // Prefijos distintos en la clave para que las dos ventanas no colisionen.
        $this->byIp = new RateLimiter($cache, $clock);
        $this->byEmail = new RateLimiter($cache, $clock);
        $this->clock = $clock === null ? null : \Closure::fromCallable($clock);
    }

    public static function withDefaults(?CacheInterface $cache = null): self
    {
        return new self($cache ?? new ArrayCache());
    }

    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public function attempt(string $email, string $ip = ''): array
    {
        $normalized = strtolower(trim($email));

        $ipResult = $this->byIp->attempt(
            'login-ip:' . $this->key($normalized, $ip),
            (int) ($this->options['per_ip'] ?? 5),
            (int) ($this->options['window'] ?? 900)
        );

        $emailResult = $this->byEmail->attempt(
            'login-email:' . $normalized,
            (int) ($this->options['per_email'] ?? 10),
            (int) ($this->options['email_window'] ?? 3600)
        );

        $allowed = $ipResult['allowed'] && $emailResult['allowed'];

        return [
            'allowed' => $allowed,
            'retry_after' => $allowed ? 0 : max($ipResult['retry_after'], $emailResult['retry_after']),
        ];
    }

    /**
     * Reinicia los contadores del email e IP indicados.
     */
    public function clear(string $email, string $ip = ''): void
    {
        $normalized = strtolower(trim($email));

        $this->forget('login-ip:' . $this->key($normalized, $ip), (int) ($this->options['window'] ?? 900));
        $this->forget('login-email:' . $normalized, (int) ($this->options['email_window'] ?? 3600));
    }

    private function key(string $email, string $ip): string
    {
        return $email . '|' . ($ip !== '' ? $ip : 'desconocida');
    }

    /**
     * Borra los buckets de la ventana actual y la anterior (la ventana puede
     * haber rodado entre el intento y el login correcto).
     */
    private function forget(string $key, int $window): void
    {
        $now = $this->clock === null ? time() : ($this->clock)();

        foreach ([0, 1] as $offset) {
            $windowStart = intdiv($now - $offset * $window, $window) * $window;
            $this->cache->forget("rate:{$key}:{$windowStart}");
        }
    }
}
