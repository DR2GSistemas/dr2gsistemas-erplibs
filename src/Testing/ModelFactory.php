<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Testing;

use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Model;

/**
 * Utilidades de testing para consumidores de la librería: arrancar el ORM contra
 * una conexión simulada o real sin repetir el cableado en cada test.
 */
final class ModelFactory
{
    /**
     * Inyecta una conexión simulada en el ORM y la devuelve para inspección.
     *
     * @param array{all?: list<array<string, mixed>>, max_batch?: int|null, last_insert_id?: int} $results
     */
    public static function withFakeConnection(array $results = []): FakeConnection
    {
        $connection = new FakeConnection($results);

        self::using($connection);

        return $connection;
    }

    /**
     * Usa la conexión indicada como conexión "default" del ORM.
     */
    public static function using(\Dr2g\ErpLibs\Core\Connection $connection): void
    {
        $manager = new DatabaseManager([
            'default' => 'test',
            'connections' => ['test' => []],
        ]);

        $property = new \ReflectionProperty(DatabaseManager::class, 'connections');
        $property->setValue($manager, ['test' => $connection]);

        Model::setDbManager($manager);
        Model::setEventDispatcher(null);
        Model::flushMetadata();
    }
}
