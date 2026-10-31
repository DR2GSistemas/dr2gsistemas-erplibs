<?php

namespace Dr2g\ErpLibs\Auth;

use Dr2g\ErpLibs\Auth\Session\SessionInterface;

/**
 * Gestiona la sesión autenticada.
 *
 * Guarda el identificador del usuario en la sesión y lo recupera con el
 * resolvedor que se le pase (normalmente la búsqueda por id en el repositorio).
 * No conoce el modelo `User`: así la librería no depende del scaffold.
 */
final class AuthManager
{
    public const SESSION_KEY = 'auth_user_id';

    /**
     * @param \Closure(int|string): ?Authorizable $resolver
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly \Closure $resolver
    ) {
    }

    /**
     * Registra al usuario: regenera el id de sesión (evita fijación) y guarda
     * su identificador.
     */
    public function login(Authorizable $user): void
    {
        $this->session->regenerate();

        $this->session->set(self::SESSION_KEY, $user->getAuthIdentifier());
    }

    /**
     * Cierra la sesión por completo, incluido el id.
     */
    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate(true);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?Authorizable
    {
        $id = $this->session->get(self::SESSION_KEY);

        if ($id === null || $id === '') {
            return null;
        }

        return ($this->resolver)($id);
    }

    public function id(): int|string|null
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id === null || $id === '' ? null : $id;
    }
}
