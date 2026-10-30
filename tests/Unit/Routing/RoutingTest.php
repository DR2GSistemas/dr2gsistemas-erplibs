<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Routing;

use Dr2g\ErpLibs\Routing\Exception\NotFoundException;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\Routing\Route;
use Dr2g\ErpLibs\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase
{
    private function router(): Router
    {
        return new Router();
    }

    /**
     * @param array<string, string> $params
     */
    private function assertMatches(string $pattern, string $path, array $params): void
    {
        $route = new Route(['GET'], $pattern, static fn () => null);

        self::assertSame($params, $route->matchPath($path), "{$pattern} vs {$path}");
    }

    private function assertNoMatch(string $pattern, string $path): void
    {
        $route = new Route(['GET'], $pattern, static fn () => null);

        self::assertNull($route->matchPath($path), "{$pattern} no debe casar {$path}");
    }

    public function testDynamicSegmentIsCaptured(): void
    {
        $this->assertMatches('/users/{id}', '/users/42', ['id' => '42']);
        $this->assertMatches('/users/{id}/posts/{post}', '/users/42/posts/7', ['id' => '42', 'post' => '7']);
    }

    public function testEncodedParameterIsDecoded(): void
    {
        $this->assertMatches('/posts/{slug}', '/posts/hola%20mundo', ['slug' => 'hola mundo']);
    }

    public function testStaticSegmentsMustMatchExactly(): void
    {
        $this->assertNoMatch('/users/{id}', '/users');
        $this->assertNoMatch('/users/{id}', '/users/1/extra');
        $this->assertNoMatch('/users/{id}', '/clientes/1');
    }

    public function testRootAndTrailingSlashesAreNormalised(): void
    {
        $this->assertMatches('/', '/', []);
        $this->assertMatches('/users', '/users/', []);
        $this->assertMatches('/users', 'users', []);
    }

    public function testOptionalSegmentIsCapturedOrOmitted(): void
    {
        $this->assertMatches('/posts/{slug?}', '/posts', []);
        $this->assertMatches('/posts/{slug?}', '/posts/hello', ['slug' => 'hello']);
    }

    public function testOptionalSegmentsMustBeTrailing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Route(['GET'], '/users/{id?}/edit', static fn () => null);
    }

    public function testPartialSegmentParametersAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Route(['GET'], '/users/id-{id}', static fn () => null);
    }

    public function testStaticRoutesScoreHigherThanDynamicOnes(): void
    {
        $static = new Route(['GET'], '/users/new', static fn () => null);
        $dynamic = new Route(['GET'], '/users/{id}', static fn () => null);

        self::assertGreaterThan($dynamic->score(), $static->score());
    }

    public function testRouterPrefersStaticRouteOverDynamic(): void
    {
        $router = $this->router();
        $router->get('/users/{id}', static fn (Request $request) => 'dynamic:' . $request->param('id'));
        $router->get('/users/new', static fn () => 'static');

        self::assertSame('static', $router->dispatch(Request::create('GET', '/users/new'))->body);
    }

    public function testDispatchPassesRouteParameters(): void
    {
        $router = $this->router();
        $router->get('/users/{id}', static fn (Request $request) => ['id' => $request->param('id')]);

        $response = $router->dispatch(Request::create('GET', '/users/42'));

        self::assertSame(200, $response->status);
        self::assertSame('{"id":"42"}', $response->body);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testMethodNotAllowedReturnsAllowHeader(): void
    {
        $router = $this->router();
        $router->get('/users', static fn () => 'ok');
        $router->post('/users', static fn () => 'created');

        $response = $router->dispatch(Request::create('DELETE', '/users'));

        self::assertSame(405, $response->status);
        self::assertSame('GET, POST', $response->headers['Allow']);
    }

    public function testUnknownRouteReturns404(): void
    {
        self::assertSame(404, $this->router()->dispatch(Request::create('GET', '/nope'))->status);
    }

    public function testHeadFallsBackToGet(): void
    {
        $router = $this->router();
        $router->get('/ping', static fn () => Response::text('pong'));

        self::assertSame(200, $router->dispatch(Request::create('HEAD', '/ping'))->status);
    }

    public function testHandlerReturnTypesBecomeResponses(): void
    {
        $router = $this->router();
        $router->get('/array', static fn () => ['a' => 1]);
        $router->get('/string', static fn () => 'hola');
        $router->get('/null', static fn () => null);
        $router->get('/response', static fn () => Response::redirect('/home'));

        self::assertSame('{"a":1}', $router->dispatch(Request::create('GET', '/array'))->body);
        self::assertSame('hola', $router->dispatch(Request::create('GET', '/string'))->body);
        self::assertSame(204, $router->dispatch(Request::create('GET', '/null'))->status);
        self::assertSame('/home', $router->dispatch(Request::create('GET', '/response'))->headers['Location']);
    }

    public function testMiddlewareWrapsTheHandlerInOrder(): void
    {
        $router = $this->router();
        $order = [];

        $router->use(static function (Request $request, callable $next) use (&$order) {
            $order[] = 'global-before';
            $response = $next($request);
            $order[] = 'global-after';

            return $response;
        });

        $router->group('/api', static function ($routes) use (&$order): void {
            $routes->get('/ping', static function () use (&$order) {
                $order[] = 'handler';

                return 'pong';
            });
        }, [
            static function (Request $request, callable $next) use (&$order) {
                $order[] = 'group-before';
                $response = $next($request);
                $order[] = 'group-after';

                return $response;
            },
        ]);

        $response = $router->dispatch(Request::create('GET', '/api/ping'));

        self::assertSame('pong', $response->body);
        self::assertSame(['global-before', 'group-before', 'handler', 'group-after', 'global-after'], $order);
    }

    public function testGroupMiddlewareDoesNotLeakToOtherGroups(): void
    {
        $router = $this->router();
        $calls = [];

        $router->group('/admin', static function ($routes): void {
            $routes->get('/x', static fn () => 'x');
        }, [static function (Request $request, callable $next) use (&$calls) {
            $calls[] = 'admin';
            return $next($request);
        }]);

        $router->get('/public', static fn () => 'public');

        $router->dispatch(Request::create('GET', '/public'));

        self::assertSame([], $calls);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $router = $this->router();
        $router->use(static fn () => Response::json(['error' => 'unauthorized'], 401));
        $router->get('/secret', static fn () => 'never');

        self::assertSame(401, $router->dispatch(Request::create('GET', '/secret'))->status);
    }

    public function testControllerStringHandler(): void
    {
        $router = $this->router();
        $router->get('/hello/{name}', GreetingController::class . '@greet');

        self::assertSame('Hola Ada', $router->dispatch(Request::create('GET', '/hello/Ada'))->body);
    }

    public function testRestfulResourceConvention(): void
    {
        $router = $this->router();
        $router->group('/api', static function ($routes): void {
            $routes->resource('/users', TestResourceController::class);
        });

        self::assertSame('{"action":"index"}', $router->dispatch(Request::create('GET', '/api/users'))->body);
        self::assertSame('{"id":"5","action":"show"}', $router->dispatch(Request::create('GET', '/api/users/5'))->body);
        self::assertSame(
            '{"created":{"name":"Ada"}}',
            $router->dispatch(Request::create('POST', '/api/users', body: '{"name":"Ada"}'))->body
        );
        self::assertSame(
            '{"updated":"5"}',
            $router->dispatch(Request::create('PUT', '/api/users/5'))->body
        );
        self::assertSame(204, $router->dispatch(Request::create('DELETE', '/api/users/5'))->status);
    }

    public function testRestfulResourceRejectsUnsupportedVerbs(): void
    {
        $router = $this->router();
        $router->resource('/users', TestResourceController::class);

        self::assertSame(405, $router->dispatch(Request::create('PATCH', '/users'))->status);
    }

    public function testValidationExceptionBecomes422WithErrors(): void
    {
        $router = $this->router();
        $router->post('/users', static function (): never {
            throw new \Dr2g\ErpLibs\Exceptions\ValidationException(['email' => ['must be a valid email']]);
        });

        $response = $router->dispatch(Request::create('POST', '/users'));

        self::assertSame(422, $response->status);
        self::assertSame(
            '{"error":422,"message":"Validation failed: email: must be a valid email","errors":{"email":["must be a valid email"]}}',
            $response->body
        );
    }

    public function testCustomErrorHandlerIsUsed(): void
    {
        $router = $this->router();
        $router->setErrorHandler(static fn (\Throwable $e, Request $r) => Response::text('custom', 418));

        self::assertSame(418, $router->dispatch(Request::create('GET', '/missing'))->status);
    }

    public function testNamedRoutesBuildUrls(): void
    {
        $route = new Route(['GET'], '/users/{id}/posts/{post}', static fn () => null);

        self::assertSame('/users/42/posts/7', $route->url(['id' => 42, 'post' => 7]));
        self::assertSame('/users/42/posts/7', $route->named('users.posts')->url(['id' => 42, 'post' => 7]));
    }

    public function testMissingRouteParameterFailsLoudly(): void
    {
        $route = new Route(['GET'], '/users/{id}', static fn () => null);

        $this->expectException(\InvalidArgumentException::class);

        $route->url([]);
    }

    public function testMatchThrowsWhenNoRouteFits(): void
    {
        $this->expectException(NotFoundException::class);

        $this->router()->match('GET', '/nothing');
    }

    public function testJsonBodyIsParsedAndInvalidJsonFails(): void
    {
        self::assertSame(
            ['name' => 'Ada'],
            Request::create('POST', '/users', body: '{"name":"Ada"}')->json()
        );

        $this->expectException(\Dr2g\ErpLibs\Routing\Exception\UnprocessableEntityException::class);

        Request::create('POST', '/users', body: '{invalid')->json();
    }

    public function testInputReadsJsonOrFormBody(): void
    {
        $json = Request::create('POST', '/todos', body: '{"title":"A"}', headers: ['Content-Type' => 'application/json']);
        $form = Request::create('POST', '/todos', body: 'title=B&done=1');
        $empty = Request::create('GET', '/todos');

        self::assertSame(['title' => 'A'], $json->input());
        self::assertSame(['title' => 'B', 'done' => '1'], $form->input());
        self::assertSame([], $empty->input());
    }

    public function testRequestExposesHeadersAndQuery(): void
    {
        $request = Request::create('GET', '/search', query: ['q' => 'azul'], headers: ['X-Tenant' => 'acme']);

        self::assertSame('azul', $request->query['q']);
        self::assertSame('acme', $request->header('x-tenant'));
    }
}

final class GreetingController
{
    public function greet(Request $request): string
    {
        return 'Hola ' . $request->param('name');
    }
}
