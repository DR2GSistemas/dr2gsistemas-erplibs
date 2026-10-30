<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Repositories;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Repositories\Repository;
use Dr2g\ErpLibs\Routing\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

#[Table(name: 'repo_products')]
final class RepoProduct extends Model
{
    #[Column(type: 'VARCHAR(200)', validation: ['required', 'max:200'])]
    public string $name;
}

/**
 * @extends Repository<RepoProduct>
 */
final class RepoProductRepository extends Repository
{
    protected function model(): string
    {
        return RepoProduct::class;
    }
}

final class RepositoryTest extends TestCase
{
    private RepoProductRepository $repository;

    protected function setUp(): void
    {
        $connection = new Connection(['dsn' => 'sqlite::memory:']);
        $connection->execute(
            'CREATE TABLE repo_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(200) NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $connection]);
        Model::setDbManager($manager);
        Model::flushMetadata();

        $this->repository = new RepoProductRepository();
    }

    public function testCreateFindAndAll(): void
    {
        $created = $this->repository->create(['name' => 'Pan']);

        self::assertInstanceOf(RepoProduct::class, $created);
        self::assertSame('Pan', $created->toArray()['name']);

        $found = $this->repository->find(1);
        self::assertInstanceOf(RepoProduct::class, $found);
        self::assertSame('Pan', $found->toArray()['name']);

        self::assertNull($this->repository->find(99));
        self::assertCount(1, $this->repository->all());
    }

    public function testFindOrFailThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->repository->findOrFail(123);
    }

    public function testUpdateAndDelete(): void
    {
        $product = $this->repository->create(['name' => 'Pan']);

        $this->repository->update($product, ['name' => 'Pan integral']);

        self::assertSame('Pan integral', $this->repository->findOrFail(1)->toArray()['name']);
        self::assertTrue($this->repository->delete($product));
        self::assertSame(0, $this->repository->count());
    }

    public function testPaginateReturnsModelsAndMetadata(): void
    {
        foreach (['c', 'a', 'b', 'd', 'e'] as $name) {
            $this->repository->create(['name' => $name]);
        }

        $page = $this->repository->paginate(page: 1, perPage: 2, order: ['name', 'ASC']);

        self::assertSame(5, $page['total']);
        self::assertSame(3, $page['last_page']);
        self::assertCount(2, $page['items']);
        self::assertInstanceOf(RepoProduct::class, $page['items'][0]);
        self::assertSame('a', $page['items'][0]->toArray()['name']);
        self::assertSame('b', $page['items'][1]->toArray()['name']);

        $last = $this->repository->paginate(page: 3, perPage: 2, order: ['name', 'ASC']);
        self::assertCount(1, $last['items']);
        self::assertSame('e', $last['items'][0]->toArray()['name']);
    }

    public function testPaginateClampsThePageSize(): void
    {
        $this->repository->create(['name' => 'a']);

        $page = $this->repository->paginate(page: 0, perPage: 500);

        self::assertSame(1, $page['page']);
        self::assertSame(100, $page['per_page']);
    }

    public function testCount(): void
    {
        self::assertSame(0, $this->repository->count());

        $this->repository->create(['name' => 'a']);
        $this->repository->create(['name' => 'b']);

        self::assertSame(2, $this->repository->count());
    }
}
