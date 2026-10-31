<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Gate;
use PHPUnit\Framework\TestCase;

final class GateTest extends TestCase
{
    public function testAnonymousUserHasNoPermissions(): void
    {
        self::assertFalse((new Gate())->can(null, 'users.view'));
    }

    public function testExactPermission(): void
    {
        $gate = new Gate();
        $user = new FakeUser(1, ['users.view']);

        self::assertTrue($gate->can($user, 'users.view'));
        self::assertFalse($gate->can($user, 'users.delete'));
    }

    public function testResourceWildcard(): void
    {
        $gate = new Gate();
        $user = new FakeUser(1, ['users.*']);

        self::assertTrue($gate->can($user, 'users.view'));
        self::assertTrue($gate->can($user, 'users.delete'));
        self::assertFalse($gate->can($user, 'roles.view'));
    }

    public function testGlobalWildcard(): void
    {
        self::assertTrue((new Gate())->can(new FakeUser(1, ['*']), 'cualquier.cosa'));
    }

    public function testAny(): void
    {
        $gate = new Gate();
        $user = new FakeUser(1, ['roles.view']);

        self::assertTrue($gate->any($user, ['users.view', 'roles.view']));
        self::assertFalse($gate->any($user, ['users.view', 'users.delete']));
    }
}
