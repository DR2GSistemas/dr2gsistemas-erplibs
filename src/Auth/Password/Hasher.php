<?php

namespace Dr2g\ErpLibs\Auth\Password;

/**
 * Hash de contraseñas con el algoritmo por defecto de PHP (`password_hash`),
 * que hoy es bcrypt y migra solo cuando PHP cambie su valor por defecto.
 */
final class Hasher
{
    public function __construct(private readonly ?string $algorithm = null)
    {
    }

    public function hash(string $plain): string
    {
        return password_hash($plain, $this->algorithm ?? PASSWORD_DEFAULT);
    }

    /**
     * Verifica la contraseña. Usa `password_verify`, que compara en tiempo
     * constante.
     */
    public function verify(string $plain, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($plain, $hash);
    }

    /**
     * Indica si el hash guardado usa un algoritmo o coste obsoleto.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm ?? PASSWORD_DEFAULT);
    }
}
