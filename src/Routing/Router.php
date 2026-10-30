<?php

namespace Dr2g\ErpLibs\Routing;

use Dr2g\ErpLibs\Exceptions\ValidationException;
use Dr2g\ErpLibs\Routing\Exception\NotFoundException;
use Dr2g\ErpLibs\Routing\Exception\RoutingException;
use Dr2g\ErpLibs\Routing\Exception\UnprocessableEntityException;

/**
 * Router HTTP del ERP: rutas con parámetros, grupos con middleware, recursos
 * RESTful y despacho tolerante a errores (siempre devuelve una Response).
 */
class Router
{
    private RouteCollection $routes;

    /** @var array<string, Route> */
    private array $named = [];

    /** @var list<callable(Request, callable): mixed> */
    private array $middleware = [];

    /** @var null|\Closure(\Throwable, Request): Response */
    private ?\Closure $errorHandler = null;

    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * @param list<string> $methods
     */
    public function add(array $methods, string $pattern, mixed $handler): Route
    {
        return $this->routes->add($methods, $pattern, $handler);
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->routes->get($pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->routes->post($pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->routes->put($pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->routes->patch($pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->routes->delete($pattern, $handler);
    }

    /**
     * Registra un recurso RESTful (index, show, store, update, destroy).
     *
     * @param class-string $controller
     */
    public function resource(string $prefix, string $controller, ?callable $factory = null): self
    {
        $this->routes->resource($prefix, $controller, $factory);
        $this->indexNamed();

        return $this;
    }

    /**
     * @param callable(RouteCollection): void $callback
     * @param list<callable(Request, callable): mixed> $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $this->routes->group($prefix, $callback, $middleware);
        $this->indexNamed();

        return $this;
    }

    /**
     * Middleware global: corre antes que el de los grupos.
     *
     * @param callable(Request, callable): mixed $middleware
     */
    public function use(callable $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * @param callable(\Throwable, Request): Response $handler
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = \Closure::fromCallable($handler);

        return $this;
    }

    /**
     * @throws NotFoundException si no hay ruta o el método no está permitido
     */
    public function match(string $method, string $path): RouteMatch
    {
        $method = strtoupper($method);
        $path = Route::normalizePattern($path);
        $allowed = [];

        foreach ($this->sortedRoutes() as $route) {
            $params = $route->matchPath($path);

            if ($params === null) {
                continue;
            }

            foreach ($route->getMethods() as $candidate) {
                $allowed[$candidate] = true;
            }

            if (in_array($method, $route->getMethods(), true)) {
                return new RouteMatch($route, $params);
            }

            if ($method === 'HEAD' && in_array('GET', $route->getMethods(), true)) {
                return new RouteMatch($route, $params, true);
            }
        }

        if ($allowed === []) {
            throw new NotFoundException("No route matches {$method} {$path}.");
        }

        $allowedMethods = array_values(array_diff(array_keys($allowed), ['HEAD']));
        sort($allowedMethods);

        throw new NotFoundException("Method {$method} not allowed for {$path}.", $allowedMethods);
    }

    /**
     * @param list<callable(Request, callable): mixed> $middleware
     */
    public function dispatch(Request $request, array $middleware = []): Response
    {
        try {
            $match = $this->match($request->method, $request->path);
        } catch (\Throwable $exception) {
            return $this->renderError($exception, $request);
        }

        $request = $request->withRouteParams($match->params);

        $stack = array_merge(
            $this->middleware,
            $middleware,
            $this->routes->middlewareFor($match->route)
        );
        $pipeline = $this->buildPipeline($stack, $match);

        try {
            return $this->toResponse(self::normalise($pipeline($request)));
        } catch (\Throwable $exception) {
            return $this->renderError($exception, $request);
        }
    }

    public function handle(): Response
    {
        return $this->dispatch(Request::fromGlobals());
    }

    public function run(): void
    {
        $this->handle()->send();
    }

    /**
     * URL de una ruta con nombre.
     *
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Named route not found: {$name}.");
        }

        return $this->named[$name]->url($params);
    }

    /**
     * @return list<Route>
     */
    private function sortedRoutes(): array
    {
        $routes = $this->routes->all();

        usort(
            $routes,
            static fn (Route $left, Route $right): int => $right->score() <=> $left->score()
                ?: strcmp($left->getPattern(), $right->getPattern())
        );

        return $routes;
    }

    private function indexNamed(): void
    {
        foreach ($this->routes->all() as $route) {
            if ($route->name !== null) {
                $this->named[$route->name] = $route;
            }
        }
    }

    /**
     * @param list<callable(Request, callable): mixed> $stack
     */
    private function buildPipeline(array $stack, RouteMatch $match): callable
    {
        $core = fn (Request $current): mixed => $this->invoke($match->route, $current);

        foreach (array_reverse($stack) as $middleware) {
            $next = $core;
            $core = static fn (Request $current): mixed => $middleware($current, $next);
        }

        return $core;
    }

    /**
     * Ejecuta el handler de una ruta (lo usan `dispatch()` y `Pipeline`).
     *
     * Las acciones de controlador reciben los parámetros de ruta por nombre:
     * `show(Request $request, string $id)`.
     */
    public function invoke(Route $route, Request $request): mixed
    {
        $handler = $route->getHandler();

        if (is_array($handler) && count($handler) === 2 && is_callable($handler)) {
            $instance = is_object($handler[0]) ? $handler[0] : self::withCache(new $handler[0](), $request);

            return self::callAction($instance, (string) $handler[1], $request);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);

            if (!class_exists($class)) {
                throw new \RuntimeException("Controller not found: {$class}.");
            }

            $controller = self::withCache(new $class(), $request);

            return self::callAction($controller, $method, $request);
        }

        if (is_callable($handler)) {
            return $handler($request);
        }

        throw new \RuntimeException('Route handler is not callable.');
    }

    /**
     * Propaga el CacheManager del request a los controllers que lo aceptan
     * (`@method static` con `useCache()` o trait `CachesResponses`).
     */
    private static function withCache(object $controller, Request $request): object
    {
        $cache = $request->attribute('cache');

        if ($cache instanceof \Dr2g\ErpLibs\Cache\CacheManager && is_callable([$controller, 'useCache'])) {
            $controller->useCache($cache);
        }

        return $controller;
    }

    /**
     * Invoca una acción de controlador pasando la Request y, si la acción los
     * declara, los parámetros de ruta por nombre.
     */
    private static function callAction(object $controller, string $method, Request $request): mixed
    {
        if (!is_callable([$controller, $method])) {
            throw new \RuntimeException('Action not found: ' . $controller::class . "@{$method}.");
        }

        $reflection = new \ReflectionMethod($controller, $method);
        $arguments = [$request];

        foreach (array_slice($reflection->getParameters(), 1) as $parameter) {
            $arguments[] = $request->routeParams[$parameter->getName()] ?? $parameter->getDefaultValue();
        }

        return $reflection->invokeArgs($controller, $arguments);
    }

    /**
     * Reglas: Response → tal cual, array → JSON, string → texto,
     * null/true → 204, false → 404.
     */
    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_array($result) => Response::json($result),
            is_string($result) => Response::text($result),
            $result === null, $result === true => Response::empty(204),
            $result === false => Response::empty(404),
            default => Response::json(['data' => $result]),
        };
    }

    /**
     * Normaliza el resultado de un middleware al mismo contrato que un handler.
     */
    private static function normalise(mixed $result): mixed
    {
        return $result instanceof Response || is_array($result) || is_string($result) || $result === null
            ? $result
            : (is_scalar($result) ? (string) $result : $result);
    }

    private function renderError(\Throwable $exception, Request $request): Response
    {
        if ($this->errorHandler !== null) {
            return ($this->errorHandler)($exception, $request);
        }

        $status = match (true) {
            $exception instanceof RoutingException => $exception->statusCode,
            $exception instanceof ValidationException => 422,
            default => 500,
        };
        $body = ['error' => $status, 'message' => $exception->getMessage()];

        if ($exception instanceof UnprocessableEntityException) {
            $body['errors'] = $exception->getErrors();
        }

        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors;
        }

        $response = Response::json($body, $status);

        if ($exception instanceof NotFoundException && $exception->getAllowedMethods() !== []) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        return $response;
    }
}
