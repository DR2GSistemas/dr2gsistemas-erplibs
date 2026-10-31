<?php

namespace Dr2g\ErpLibs\Auth\Csrf;

use Dr2g\ErpLibs\Auth\Session\SessionInterface;

/**
 * Token CSRF por sesión.
 *
 * El token vive en la sesión y se compara con `hash_equals()` para evitar
 * ataques de temporización. Los formularios lo incluyen con `field()`.
 */
final class CsrfTokenManager
{
    public const SESSION_KEY = '_csrf_token';

    public const FIELD_NAME = '_token';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $key = self::SESSION_KEY
    ) {
    }

    /**
     * Devuelve el token de la sesión y lo crea si no existe.
     */
    public function token(): string
    {
        $token = $this->session->get($this->key);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set($this->key, $token);
        }

        return $token;
    }

    /**
     * Compara con la sesión sin filtrar el valor esperado.
     */
    public function isValid(?string $candidate): bool
    {
        $expected = $this->session->get($this->key);

        if (!is_string($expected) || $expected === '' || $candidate === null || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * Campo oculto listo para insertar en un formulario.
     */
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            htmlspecialchars($this->token(), ENT_QUOTES)
        );
    }

    /**
     * Regenera el token (se hace tras un login exitoso).
     */
    public function rotate(): string
    {
        $this->session->forget($this->key);

        return $this->token();
    }
}
