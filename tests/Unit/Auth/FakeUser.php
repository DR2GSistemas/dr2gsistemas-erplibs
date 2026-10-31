<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Auth;

use Dr2g\ErpLibs\Auth\Authorizable;

/**
 * Usuario de prueba para Gate y AuthManager.
 */
final class FakeUser implements Authorizable
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly int $id,
        private readonly array $permissions
    ) {
    }

    public function permissionNames(): array
    {
        return $this->permissions;
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }
}
