<?php

/**
 * Alias de compatibilidad del namespace antiguo `Azulerp\` → `Dr2g\ErpLibs\`.
 *
 * Este archivo NO declara clases (por eso no se autoloada por nombre): el
 * proyecto consumidor lo carga explícitamente desde `bootstrap.php`. Eliminar
 * cuando todo el código consumidor haya migrado sus `use`.
 */

$aliases = [
    'Core\\Connection' => \Dr2g\ErpLibs\Core\Connection::class,
    'Core\\DatabaseManager' => \Dr2g\ErpLibs\Core\DatabaseManager::class,
    'Core\\QueryBuilder' => \Dr2g\ErpLibs\Core\QueryBuilder::class,
    'Core\\ConnectionPool' => \Dr2g\ErpLibs\Core\ConnectionPool::class,
    'Core\\CircuitBreaker' => \Dr2g\ErpLibs\Core\CircuitBreaker::class,
    'Models\\Model' => \Dr2g\ErpLibs\Models\Model::class,
    'Events\\Dispatcher' => \Dr2g\ErpLibs\Events\Dispatcher::class,
    'Serialization\\Resource' => \Dr2g\ErpLibs\Serialization\Resource::class,
    'Serialization\\Paginator' => \Dr2g\ErpLibs\Serialization\Paginator::class,
    'Validation\\Validator' => \Dr2g\ErpLibs\Validation\Validator::class,
    'Validation\\Rule' => \Dr2g\ErpLibs\Validation\Rule::class,
    'Log\\Logger' => \Dr2g\ErpLibs\Log\Logger::class,
    'Cache\\ArrayCache' => \Dr2g\ErpLibs\Cache\ArrayCache::class,
    'Cache\\FileCache' => \Dr2g\ErpLibs\Cache\FileCache::class,
    'Cache\\RedisCache' => \Dr2g\ErpLibs\Cache\RedisCache::class,
    'Audit\\AuditLogger' => \Dr2g\ErpLibs\Audit\AuditLogger::class,
    'Audit\\MemoryAuditStorage' => \Dr2g\ErpLibs\Audit\MemoryAuditStorage::class,
    'Audit\\Gdpr' => \Dr2g\ErpLibs\Audit\Gdpr::class,
    'Audit\\RateLimiter' => \Dr2g\ErpLibs\Audit\RateLimiter::class,
    'Migrations\\Blueprint' => \Dr2g\ErpLibs\Migrations\Blueprint::class,
    'Migrations\\SchemaBuilder' => \Dr2g\ErpLibs\Migrations\SchemaBuilder::class,
    'Migrations\\MigrationManager' => \Dr2g\ErpLibs\Migrations\MigrationManager::class,
    'Migrations\\MigrationRepository' => \Dr2g\ErpLibs\Migrations\MigrationRepository::class,
    'Migrations\\Migration' => \Dr2g\ErpLibs\Migrations\Migration::class,
    'Exceptions\\DatabaseException' => \Dr2g\ErpLibs\Exceptions\DatabaseException::class,
    'Exceptions\\ValidationException' => \Dr2g\ErpLibs\Exceptions\ValidationException::class,
    'Security\\SQLInjectionGuard' => \Dr2g\ErpLibs\Security\SQLInjectionGuard::class,
];

foreach ($aliases as $legacy => $target) {
    $full = 'Azulerp\\' . $legacy;

    if (!class_exists($full, false) && !interface_exists($full, false)) {
        class_alias($target, $full);
    }
}

unset($aliases, $legacy, $target, $full);
