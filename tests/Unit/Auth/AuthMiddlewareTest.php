<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Gate;
use Dr2g\ErpLibs\Auth\Middleware\Authenticate;
use Dr2g\ErpLibs\Auth\Middleware\RequirePermission;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private static function next(): callable
    {
        return static fn (Request $request): Response => Response::text('ok');
    }

    private function authenticated(FakeUser $user): Authenticate
    {
        $auth = new \Dr2g\ErpLibs\Auth\AuthManager(
            new \Dr2g\ErpLibs\Auth\Session\ArraySession(['auth_user_id' => $user->getAuthIdentifier()]),
            static fn (): ?\Dr2g\ErpLibs\Auth\Authorizable => $user
        );

        return new Authenticate($auth);
    }

    private function anonymous(): Authenticate
    {
        $auth = new \Dr2g\ErpLibs\Auth\AuthManager(
            new \Dr2g\ErpLibs\Auth\Session\ArraySession(),
            static fn (): ?\Dr2g\ErpLibs\Auth\Authorizable => null
        );

        return new Authenticate($auth);
    }

    public function testAllowsAValidSession(): void
    {
        $middleware = $this->authenticated(new FakeUser(1, []));

        self::assertSame(200, $middleware(Request::create('GET', '/admin'), self::next())->status);
    }

    public function testRedirectsAnonymousUsersToLoginWithReturnUrl(): void
    {
        $response = $this->anonymous()(Request::create('GET', '/admin/users'), self::next());

        self::assertSame(302, $response->status);
        self::assertSame('/login?redirect=%2Fadmin%2Fusers', $response->headers['Location']);
    }

    public function testPostRequestsDoNotAdvertiseARedirectTarget(): void
    {
        $response = $this->anonymous()(Request::create('POST', '/admin/users'), self::next());

        self::assertSame('/login', $response->headers['Location']);
    }

    public function testReturns401ForJsonClients(): void
    {
        $response = $this->anonymous()(
            Request::create('GET', '/admin', headers: ['Accept' => 'application/json']),
            self::next()
        );

        self::assertSame(401, $response->status);
        self::assertStringContainsString('unauthenticated', $response->body);
    }

    public function testRequirePermissionAllowsGrantedPermission(): void
    {
        $middleware = new RequirePermission(new Gate(), 'users.view');
        $request = Request::create('GET', '/admin/users')
            ->withAttributes(['auth_user' => new FakeUser(1, ['users.view'])]);

        self::assertSame(200, $middleware($request, self::next())->status);
    }

    public function testRequirePermissionBlocksWithoutThePermission(): void
    {
        $middleware = new RequirePermission(new Gate(), 'users.delete');
        $request = Request::create('GET', '/admin/users')
            ->withAttributes(['auth_user' => new FakeUser(1, ['users.view'])]);

        self::assertSame(403, $middleware($request, self::next())->status);
    }

    public function testRequirePermissionWithoutUserIsForbidden(): void
    {
        $middleware = new RequirePermission(new Gate(), 'users.view');

        self::assertSame(403, $middleware(Request::create('GET', '/admin'), self::next())->status);
    }

    public function testRequirePermissionReturnsJsonForApi(): void
    {
        $middleware = new RequirePermission(new Gate(), 'users.view');
        $request = Request::create('GET', '/admin', headers: ['Accept' => 'application/json']);

        $response = $middleware($request, self::next());

        self::assertSame(403, $response->status);
        self::assertStringContainsString('forbidden', $response->body);
    }
}
