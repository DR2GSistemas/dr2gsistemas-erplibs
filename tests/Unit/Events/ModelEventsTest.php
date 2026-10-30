<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Events;

use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Events\Dispatcher;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

#[Table(name: 'customers')]
final class Customer extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;
}

final class ModelEventsTest extends TestCase
{
    private FakeConnection $connection;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->connection = new FakeConnection();
        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $this->connection]);
        Model::setDbManager($manager);

        $this->dispatcher = new Dispatcher();
        Model::setEventDispatcher($this->dispatcher);
    }

    protected function tearDown(): void
    {
        Model::setEventDispatcher(null);
    }

    public function testCreateDispatchesLifecycleEventsInOrder(): void
    {
        $seen = [];
        foreach (['saving', 'creating', 'created'] as $event) {
            $this->dispatcher->listen($event, function (Customer $model) use ($event, &$seen) {
                $seen[] = $event;
            });
        }

        Customer::create(['name' => 'Ada']);

        self::assertSame(['saving', 'creating', 'created'], $seen);
    }

    public function testUpdateDispatchesUpdatingAndUpdated(): void
    {
        $seen = [];
        foreach (['saving', 'updating', 'updated'] as $event) {
            $this->dispatcher->listen($event, function () use ($event, &$seen) {
                $seen[] = $event;
            });
        }

        $customer = Customer::hydrate(['id' => 1, 'name' => 'Ada']);
        $customer->fill(['name' => 'Grace']);
        $customer->save();

        self::assertSame(['saving', 'updating', 'updated'], $seen);
    }

    public function testListenerReturningFalseCancelsTheSave(): void
    {
        $this->dispatcher->listen('creating', static fn () => false);

        $customer = Customer::create(['name' => 'Blocked']);

        self::assertFalse($customer->exists());
        self::assertSame([], $this->connection->inserts);
    }

    public function testDeleteDispatchesDeletingAndDeleted(): void
    {
        $seen = [];
        $this->dispatcher->listen('deleting', function () use (&$seen) {
            $seen[] = 'deleting';
        });
        $this->dispatcher->listen('deleted', function () use (&$seen) {
            $seen[] = 'deleted';
        });

        $customer = Customer::hydrate(['id' => 5, 'name' => 'Ada']);
        $customer->delete();

        self::assertSame(['deleting', 'deleted'], $seen);
    }

    public function testDispatchRunsAllListenersUntilCancelled(): void
    {
        $calls = [];
        $dispatcher = new Dispatcher();
        $dispatcher->listen('x', function () use (&$calls) {
            $calls[] = 1;
        });
        $dispatcher->listen('x', function () use (&$calls) {
            $calls[] = 2;
            return false;
        });
        $dispatcher->listen('x', function () use (&$calls) {
            $calls[] = 3;
        });

        self::assertFalse($dispatcher->dispatch('x'));
        self::assertSame([1, 2], $calls);
    }
}
