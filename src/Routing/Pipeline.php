<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Construye la pipeline de un flujo HTTP: resuelve la ruta, inyecta los
 * parámetros y los atributos del kernel, y devuelve siempre una Response.
 */
final class Pipeline
{
    /** @var list<callable(Request, callable): mixed> */
    private array $middleware = [];

    /** @var null|\Closure(\Throwable, Request): Response */
    private ?\Closure $errorHandler = null;

    public function __construct(private readonly Router $router)
    {
    }

    /**
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
     * @param array<string, mixed> $attributes atributos inyectados en la petición
     */
    public function handle(Request $request, array $attributes = []): Response
    {
        $request = $attributes === [] ? $request : $request->withAttributes($attributes);

        try {
            $match = $this->router->match($request->method, $request->path);
        } catch (\Throwable $exception) {
            return $this->render($exception, $request);
        }

        $request = $request->withRouteParams($match->params);

        try {
            $result = $this->dispatch($match, $request);
        } catch (\Throwable $exception) {
            return $this->render($exception, $request);
        }

        return $this->toResponse($result);
    }

    private function dispatch(RouteMatch $match, Request $request): mixed
    {
        $core = fn (Request $current): mixed => $this->router->invoke($match->route, $current);

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = $core;
            $core = static fn (Request $current): mixed => $middleware($current, $next);
        }

        return $core($request);
    }

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

    private function render(\Throwable $exception, Request $request): Response
    {
        if ($this->errorHandler !== null) {
            return ($this->errorHandler)($exception, $request);
        }

        $status = match (true) {
            $exception instanceof Exception\RoutingException => $exception->statusCode,
            $exception instanceof \Dr2g\ErpLibs\Exceptions\ValidationException => 422,
            default => 500,
        };

        return Response::json(['error' => $status, 'message' => $exception->getMessage()], $status);
    }
}
