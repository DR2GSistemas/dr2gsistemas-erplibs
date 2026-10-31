<?php

namespace Dr2g\ErpLibs\Auth;

/**
 * Contrato mínimo que debe cumplir el usuario autenticado para comprobar
 * permisos: un mapa de permisos, sin depender de un modelo concreto.
 */
interface Authorizable
{
    /**
     * Permisos efectivos del usuario (la unión de los de todos sus roles).
     *
     * @return list<string>
     */
    public function permissionNames(): array;

    public function getAuthIdentifier(): int|string;
}
