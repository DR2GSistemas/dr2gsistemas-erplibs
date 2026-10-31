<?php

namespace Dr2g\ErpLibs\Auth\Middleware;

use Dr2g\ErpLibs\Auth\Gate;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\View\Renderer;

/**
 * Exige un permiso concreto sobre el usuario autenticado.
 *
 * El usuario lo deja `Authenticate` en el atributo `auth_user`; si no está,
 * se considera no autorizado (403). Con `Accept: application/json` responde un
 * JSON de error en vez de la vista.
 */
final class RequirePermission
{
    /**
     * @param Renderer|null $renderer para pintar la vista 403 (opcional)
     */
    public function __construct(
        private readonly Gate $gate,
        private readonly string $permission,
        private readonly ?Renderer $renderer = null
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $user = $request->attribute('auth_user');

        if ($user instanceof \Dr2g\ErpLibs\Auth\Authorizable && $this->gate->can($user, $this->permission)) {
            return $next($request);
        }

        return $this->forbidden($request);
    }

    private function forbidden(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['error' => ['code' => 'forbidden', 'message' => 'Permission denied.']], 403);
        }

        if ($this->renderer !== null && $this->renderer->locate('errors.403') !== null) {
            return $this->renderer->layout('errors.403', 'layout', [
                'title' => 'Acceso denegado',
                'permission' => $this->permission,
            ], 403);
        }

        return Response::text('403 Acceso denegado', 403);
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower($request->header('Accept') ?? '');

        return str_contains($accept, 'application/json');
    }
}
