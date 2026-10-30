<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Routing;

use Dr2g\ErpLibs\Routing\Controller;
use Dr2g\ErpLibs\Routing\Request;

/**
 * Controlador RESTful de prueba: las acciones no sobrescritas heredan 501.
 */
final class TestResourceController extends Controller
{
    /**
     * @var list<string>
     */
    public array $visited = [];

    public function index(Request $request): mixed
    {
        return ['action' => 'index'];
    }

    public function show(Request $request): array
    {
        return ['id' => $request->param('id'), 'action' => 'show'];
    }

    public function store(Request $request): mixed
    {
        return ['created' => $request->json()];
    }

    public function update(Request $request): mixed
    {
        return ['updated' => $request->param('id')];
    }

    public function destroy(Request $request): mixed
    {
        return null;
    }
}
