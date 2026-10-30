<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Models;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

#[Table(name: 'products')]
final class Product extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;

    #[Column(type: 'DECIMAL(10,2)')]
    public float $price = 0.0;
}

#[Table(name: 'archived_items')]
final class ArchivedItem extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $label;

    #[Column(type: 'TIMESTAMP', nullable: true)]
    public ?string $deleted_at = null;
}

#[Table(name: 'timestamped_products')]
final class TimestampedProduct extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string $created_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string $updated_at = null;

    #[Column(type: 'DATETIME', nullable: true)]
    public ?string $deleted_at = null;
}

final class ModelTest extends TestCase
{
    private FakeConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new FakeConnection(['all' => []]);
        $manager = $this->managerReturning($this->connection);
        Model::setDbManager($manager);
    }

    /**
     * DatabaseManager opens a real PDO by design; inject the fake via reflection
     * so Model can be exercised without a server.
     */
    private function managerReturning(Connection $connection): DatabaseManager
    {
        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);

        $property = new \ReflectionProperty(DatabaseManager::class, 'connections');
        $property->setValue($manager, ['test' => $connection]);

        return $manager;
    }

    public function testFillKeepsUndeclaredColumns(): void
    {
        $model = (new Product())->fill(['name' => 'Chair', 'price' => 19.99, 'id' => 7]);

        self::assertSame('Chair', $model->toArray()['name']);
        self::assertSame(7, $model->id);
        self::assertFalse($model->exists());
    }

    public function testCreateInsertsAndAssignsPrimaryKey(): void
    {
        $product = Product::create(['name' => 'Desk', 'price' => 120.5]);

        self::assertTrue($product->exists());
        self::assertSame(1, $product->id);
        self::assertSame('products', $this->connection->inserts[0]['table']);
        self::assertSame(['name' => 'Desk', 'price' => 120.5], $this->connection->inserts[0]['data']);
    }

    public function testGetDirtyOnlyReturnsChangedAttributes(): void
    {
        $product = Product::hydrate(['id' => 3, 'name' => 'Desk', 'price' => 10.0]);

        self::assertSame([], $product->getDirty());

        // shadowing a declared property makes PHP assign it directly, so keep the
        // column map in sync explicitly.
        $product->fill(['name' => 'Table']);

        self::assertSame(['name' => 'Table'], $product->getDirty());
    }

    public function testHydratedDeclaredPropertiesAreInitialised(): void
    {
        $product = Product::hydrate(['id' => 3, 'name' => 'Desk', 'price' => 10.0]);

        self::assertSame('Desk', $product->name);
        self::assertSame(10.0, $product->price);
    }

    public function testUndeclaredColumnsAreReadableAndWritable(): void
    {
        $product = Product::hydrate(['id' => 3, 'name' => 'Desk']);

        $product->slug = 'desk';

        self::assertSame('desk', $product->toArray()['slug']);
        self::assertSame(['slug' => 'desk'], $product->getDirty());
    }

    public function testSoftDeletesIsDetectedFromDeletedAtColumn(): void
    {
        self::assertTrue(ArchivedItem::usesSoftDeletes());
        self::assertFalse(Product::usesSoftDeletes());
    }

    public function testPrimaryKeyDefaultsToId(): void
    {
        self::assertSame('id', Product::getPrimaryKey());
    }

    public function testQueryReturnsHydratedModelsViaHydrateMany(): void
    {
        $models = Product::hydrateMany([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);

        self::assertCount(2, $models);
        self::assertInstanceOf(Product::class, $models[1]);
        self::assertSame('B', $models[1]->toArray()['name']);
        self::assertTrue($models[0]->exists());
    }

    public function testMissingDatabaseManagerFailsClearly(): void
    {
        $property = new \ReflectionProperty(Model::class, 'dbManager');
        $property->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No database manager configured');

        Product::find(1);
    }

    public function testTimestampsAreStampedOnCreate(): void
    {
        $stamped = new TimestampedProduct();
        $stamped->fill(['name' => 'Pan'])->save();

        $insert = $this->connection->inserts[0]['data'] ?? [];

        self::assertArrayHasKey('created_at', $insert);
        self::assertArrayHasKey('updated_at', $insert);
        self::assertSame($insert['created_at'], $insert['updated_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $insert['created_at']);
    }

    public function testUpdateRefreshesOnlyUpdatedAt(): void
    {
        $model = TimestampedProduct::hydrate([
            'id' => 4,
            'name' => 'Pan',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);

        $this->connection->affectedRows = 1;
        $model->fill(['name' => 'Pan integral'])->save();

        $data = $model->toArray();

        self::assertSame('2020-01-01 00:00:00', $data['created_at']);
        self::assertNotSame('2020-01-01 00:00:00', $data['updated_at']);
    }

    public function testModelsWithoutTimestampColumnsAreNotStamped(): void
    {
        // Product no declara created_at/updated_at: no deben inyectarse.
        $product = new Product();
        $product->fill(['name' => 'Pan'])->save();

        $insert = $this->connection->inserts[0]['data'] ?? [];

        self::assertArrayNotHasKey('created_at', $insert);
        self::assertArrayNotHasKey('updated_at', $insert);
    }

    public function testDeclaresColumnDetectsTheAttribute(): void
    {
        self::assertTrue(TimestampedProduct::declaresColumn('created_at'));
        self::assertTrue(TimestampedProduct::declaresColumn('deleted_at'));
        self::assertFalse(Product::declaresColumn('created_at'));
    }
}
