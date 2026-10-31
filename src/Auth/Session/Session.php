<?php

namespace Dr2g\ErpLibs\Auth\Session;

/**
 * Sesión sobre `$_SESSION` nativo de PHP.
 *
 * Cookies: `httponly` y `samesite=Lax` siempre; `secure` cuando se pide (en
 * producción). El nombre de la cookie se puede cambiar para no chocar con otras
 * apps del mismo dominio.
 *
 * En CLI no hay cabeceras ni cookies: si `session_start()` no es posible (CLI o
 * cabeceras ya enviadas) se delega en una sesión en memoria, para que seeders,
 * scripts y tests funcionen igual.
 */
final class Session implements SessionInterface
{
    private ?ArraySession $fallback = null;

    public function __construct(
        private readonly string $name = 'dr2g_session',
        private readonly bool $secure = false,
        private readonly int $lifetime = 0
    ) {
    }

    public function start(): void
    {
        if ($this->fallback !== null || $this->isStarted()) {
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            $this->fallback = new ArraySession();
            $this->fallback->start();

            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => '/',
            'httponly' => true,
            'secure' => $this->secure,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function getId(): string
    {
        $this->start();

        return $this->fallback?->getId() ?? (string) session_id();
    }

    public function regenerate(bool $deleteOldData = false): bool
    {
        $this->start();

        if ($this->fallback !== null) {
            return $this->fallback->regenerate($deleteOldData);
        }

        return session_regenerate_id($deleteOldData);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        if ($this->fallback !== null) {
            return $this->fallback->get($key, $default);
        }

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        if ($this->fallback !== null) {
            $this->fallback->set($key, $value);

            return;
        }

        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();

        if ($this->fallback !== null) {
            return $this->fallback->has($key);
        }

        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        $this->start();

        if ($this->fallback !== null) {
            $this->fallback->forget($key);

            return;
        }

        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function flush(): void
    {
        $this->start();

        if ($this->fallback !== null) {
            $this->fallback->flush();

            return;
        }

        $_SESSION = [];
    }

    public function isStarted(): bool
    {
        return $this->fallback !== null || session_status() === PHP_SESSION_ACTIVE;
    }
}
