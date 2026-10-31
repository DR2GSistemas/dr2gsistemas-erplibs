<?php

namespace Dr2g\ErpLibs\CLI\Contracts;

use Dr2g\ErpLibs\Models\Model;

interface AuthUserRepository
{
    public function findByEmail(string $email): ?Model;

    /** @param list<int|string> $roleIds */
    public function syncRoles(Model $user, array $roleIds): void;
}
