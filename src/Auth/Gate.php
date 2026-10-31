<?php

namespace Dr2g\ErpLibs\Auth;

/**
 * Comprueba permisos sobre el usuario autenticado.
 *
 * Un usuario sin sesión no tiene permisos. `can()` acepta además comodines:
 * `users.*` cubre cualquier permiso del recurso y `*` cubre todo (rol superadmin).
 */
final class Gate
{
    public function can(?Authorizable $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $granted = $user->permissionNames();

        if (in_array('*', $granted, true)) {
            return true;
        }

        if (in_array($permission, $granted, true)) {
            return true;
        }

        $resource = str_contains($permission, '.') ? strstr($permission, '.', true) : null;

        return $resource !== null && in_array($resource . '.*', $granted, true);
    }

    /**
     * Devuelve true si concede al menos uno de los permisos.
     *
     * @param list<string> $permissions
     */
    public function any(?Authorizable $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission)) {
                return true;
            }
        }

        return false;
    }
}
