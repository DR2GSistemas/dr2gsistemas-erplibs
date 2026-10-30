<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Controlador base: un método público por acción y convención RESTful opcional.
 *
 * Convención (`resources()`): index, show, store, update, destroy. Sobrescribir
 * solo las acciones necesarias; las demás responden 404 o 405.
 */
abstract class Controller
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Acciones REST estándar. Si tu controlador necesita el id, no heredes de
     * Controller: declara `show(Request $request, int $id)` y registra la ruta
     * con `{id}` (el router inyecta los parámetros por nombre).
     */
    public function index(Request $request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function show(Request $request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function store(Request $request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function update(Request $request): mixed
    {
        return Response::text('Not implemented.', 501);
    }

    public function destroy(Request $request): mixed
    {
        return Response::text('Not implemented.', 501);
    }
}
