<?php

namespace Dr2g\ErpLibs\Auth\Middleware;

use Dr2g\ErpLibs\Auth\AuthManager;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;

/**
 * Exige una sesión autenticada.
 *
 * Sin sesión: redirige a `$loginPath` guardando la URL de retorno, o responde
 * `401` si el cliente pide JSON. Deja el usuario en el atributo `auth_user`
 * para que el controlador no tenga que volver a resolverlo.
 */
final class Authenticate
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly string $loginPath = '/login'
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->unauthenticated($request);
        }

        return $next($request->withAttributes(['auth_user' => $user]));
    }

    private function unauthenticated(Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.']], 401);
        }

        if ($request->method === 'GET') {
            $target = $this->loginPath . '?redirect=' . rawurlencode($request->path);
        } else {
            $target = $this->loginPath;
        }

        return Response::redirect($target, 302);
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower($request->header('Accept') ?? '');

        return str_contains($accept, 'application/json')
            || str_contains(strtolower($request->header('Content-Type') ?? ''), 'application/json');
    }
}
