<?php

namespace Dr2g\ErpLibs\Auth;

use Dr2g\ErpLibs\Log\Logger;

/**
 * Auditoría de los eventos de autenticación.
 *
 * Escribe en el logger de la librería con el evento como mensaje y email/IP
 * como contexto. Nunca registra contraseñas, hashes ni tokens.
 */
final class AuthAudit
{
    public function __construct(private readonly ?Logger $logger = null)
    {
    }

    public static function withDefaults(): self
    {
        return new self(new Logger());
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $event, string $email, string $ip = '', array $context = []): void
    {
        $this->logger?->info('auth.' . $event, $context + [
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    public function login(string $email, string $ip = ''): void
    {
        $this->record('login', $email, $ip);
    }

    public function loginFailed(string $email, string $ip = ''): void
    {
        $this->record('login_failed', $email, $ip);
    }

    public function throttled(string $email, int $retryAfter, string $ip = ''): void
    {
        $this->record('login_throttled', $email, $ip, ['retry_after' => $retryAfter]);
    }

    public function logout(string $email, string $ip = ''): void
    {
        $this->record('logout', $email, $ip);
    }

    public function passwordChanged(string $email, string $ip = ''): void
    {
        $this->record('password_changed', $email, $ip);
    }
}
