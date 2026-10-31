<?php

namespace Dr2g\ErpLibs\Auth\Session;

/**
 * Almacén clave-valor de la sesión del usuario.
 *
 * La abstracción permite sustituir `$_SESSION` por una implementación en
 * memoria en los tests, sin arrancar la sesión de PHP.
 */
interface SessionInterface
{
    /**
     * Arranca la sesión. Debe ser idempotente: llamarla dos veces no falla ni
     * duplica cabeceras.
     */
    public function start(): void;

    public function getId(): string;

    /**
     * Invalida la sesión actual y genera un id nuevo. Se usa tras el login para
     * evitar la fijación de sesión.
     */
    public function regenerate(bool $deleteOldData = false): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /**
     * Devuelve el valor y lo borra (útil para mensajes de un solo uso).
     */
    public function pull(string $key, mixed $default = null): mixed;

    public function flush(): void;

    public function isStarted(): bool;
}
