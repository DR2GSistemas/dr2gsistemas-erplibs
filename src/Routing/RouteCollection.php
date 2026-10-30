<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Registro de rutas con agrupación por prefijo y middleware acumulado por grupo.
 *
 * Cada ruta conserva el middleware vigente en el momento de registrarse, de modo
 * que dos grupos distintos pueden tener pilas diferentes.
 */
class RouteCollection
{
    /** @var list<array{route: Route, middleware: list<callable(Request, callable): mixed>}> */
    private array $registrations = [];

    /** @var list<callable(Request, callable): mixed> */
    private array $groupMiddleware = [];

    private string $prefix = '';

    /**
     * @param list<string> $methods
     */
    public function add(string|array $methods, string $pattern, mixed $handler): Route
    {
        $methods = array_values(array_unique(array_map(
            static fn ($method): string => strtoupper((string) $method),
            (array) $methods
        )));

        $route = new Route($methods, $this->prefix . Route::normalizePattern($pattern), $handler);
        $this->registrations[] = ['route' => $route, 'middleware' => $this->groupMiddleware];

        return $route;
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    /**
     * Convención RESTful: index, show, store, update y destroy sobre `$prefix`.
     *
     * @param class-string $controller
     * @param null|callable(): object $factory evita instanciar el controlador al registrar
     */
    public function resource(string $prefix, string $controller, ?callable $factory = null): void
    {
        $make = $factory ?? static fn (): object => new $controller();
        $name = str_replace('/', '.', trim(Route::normalizePattern($prefix), '/'));

        $this->add('GET', $prefix, $this->controllerHandler($make, 'index'));
        $this->nameLast("{$name}.index");
        $this->add('GET', "{$prefix}/{id}", $this->controllerHandler($make, 'show'));
        $this->nameLast("{$name}.show");
        $this->add('POST', $prefix, $this->controllerHandler($make, 'store'));
        $this->nameLast("{$name}.store");
        $this->add('PUT', "{$prefix}/{id}", $this->controllerHandler($make, 'update'));
        $this->nameLast("{$name}.update");
        $this->add('DELETE', "{$prefix}/{id}", $this->controllerHandler($make, 'destroy'));
        $this->nameLast("{$name}.destroy");
    }

    /**
     * Envuelve la factory para instanciar el controlador al despachar, no al registrar.
     *
     * @param callable(): object $make
     * @return callable(Request): mixed
     */
    private function controllerHandler(callable $make, string $action): callable
    {
        return static function (Request $request) use ($make, $action): mixed {
            $controller = $make();

            if (!is_callable([$controller, $action])) {
                throw new \RuntimeException('Action not found: ' . $controller::class . '@' . $action . '.');
            }

            return $controller->{$action}($request);
        };
    }

    /**
     * Agrupa rutas bajo un prefijo y acumula middleware para el grupo.
     *
     * @param callable(self): void $callback
     * @param list<callable(Request, callable): mixed> $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = $previousPrefix . Route::normalizePattern($prefix);
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        try {
            $callback($this);
        } finally {
            $this->prefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return array_map(static fn (array $entry): Route => $entry['route'], $this->registrations);
    }

    /**
     * Middleware acumulado para una ruta concreta.
     *
     * @return list<callable(Request, callable): mixed>
     */
    public function middlewareFor(Route $route): array
    {
        foreach ($this->registrations as $entry) {
            if ($entry['route']->getPattern() === $route->getPattern()
                && $entry['route']->getMethods() === $route->getMethods()) {
                return $entry['middleware'];
            }
        }

        return [];
    }

    /**
     * Asigna nombre a la última ruta registrada a través de `resource()`.
     */
    private function nameLast(string $name): void
    {
        $index = count($this->registrations) - 1;
        $entry = $this->registrations[$index];

        $this->registrations[$index] = [
            'route' => $entry['route']->named($name),
            'middleware' => $entry['middleware'],
        ];
    }
}
