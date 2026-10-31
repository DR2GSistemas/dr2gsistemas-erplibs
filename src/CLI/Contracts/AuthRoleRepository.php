<?php

namespace Dr2g\ErpLibs\CLI\Contracts;

use Dr2g\ErpLibs\Models\Model;

interface AuthRoleRepository
{
    public function findByName(string $name): ?Model;
}
