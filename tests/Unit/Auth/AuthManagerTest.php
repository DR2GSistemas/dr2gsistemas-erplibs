<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\AuthManager;
use Dr2g\ErpLibs\Auth\Authorizable;
use Dr2g\ErpLibs\Auth\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class AuthManagerTest extends TestCase
{
    /**
     * Almacén mutable por objeto: el resolvedor lo lee en cada llamada, así el
     * test puede añadir usuarios después de crear el manager.
     */
    private \ArrayObject $users;

    private ArraySession $session;

    private AuthManager $auth;

    protected function setUp(): void
    {
        $this->users = new \ArrayObject();
        $this->session = new ArraySession();

        $users = $this->users;
        $this->auth = new AuthManager(
            $this->session,
            static fn (int|string $id): ?Authorizable => $users->offsetExists($id)
                ? $users->offsetGet($id)
                : null
        );
    }

    public function testLoginRegeneratesTheSessionIdAndStoresTheUser(): void
    {
        $user = new FakeUser(7, ['users.view']);
        $this->users[7] = $user;
        $before = $this->session->getId();

        $this->auth->login($user);

        self::assertNotSame($before, $this->session->getId());
        self::assertTrue($this->auth->check());
        self::assertSame(7, $this->auth->id());
        self::assertSame($user, $this->auth->user());
    }

    public function testLogoutClearsTheUser(): void
    {
        $this->users[7] = new FakeUser(7, []);

        $this->auth->login($this->users[7]);
        $this->auth->logout();

        self::assertFalse($this->auth->check());
        self::assertNull($this->auth->user());
        self::assertNull($this->auth->id());
    }

    public function testUserMissingFromTheStoreMeansNoSession(): void
    {
        $this->session->set(AuthManager::SESSION_KEY, 99);

        self::assertFalse($this->auth->check());
    }

    public function testEmptySessionMeansAnonymous(): void
    {
        self::assertNull($this->auth->id());
        self::assertNull($this->auth->user());
    }
}
