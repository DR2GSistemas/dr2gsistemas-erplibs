<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Repositories;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Repositories\Repository;
use PHPUnit\Framework\TestCase;

#[Table(name: 'pivot_users')]
final class PivotUser extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;
}

#[Table(name: 'pivot_roles')]
final class PivotRole extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;
}

/**
 * @extends Repository<PivotUser>
 */
final class PivotUserRepository extends Repository
{
    protected function model(): string
    {
        return PivotUser::class;
    }
}

/**
 * Relaciones N:N sobre tablas pivote, contra SQLite real.
 */
final class PivotRelationTest extends TestCase
{
    private PivotUserRepository $users;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection(['dsn' => 'sqlite::memory:']);
        $this->connection->execute(
            'CREATE TABLE pivot_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL)'
        );
        $this->connection->execute(
            'CREATE TABLE pivot_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL)'
        );
        $this->connection->execute(
            'CREATE TABLE pivot_role_user (
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                PRIMARY KEY (user_id, role_id)
            )'
        );

        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $this->connection]);
        Model::setDbManager($manager);
        Model::flushMetadata();

        $this->users = new PivotUserRepository();
    }

    private function user(string $name): PivotUser
    {
        /** @var PivotUser $user */
        $user = $this->users->create(['name' => $name]);

        return $user;
    }

    /**
     * @return list<int>
     */
    private function roleIds(PivotUser $user): array
    {
        return $this->users->relatedIds('pivot_role_user', 'user_id', 'role_id', $user->toArray()['id']);
    }

    public function testAttachAddsAndIgnoresDuplicates(): void
    {
        $user = $this->user('ana');

        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', [1, 2]);
        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', [2, 3]);

        self::assertSame([1, 2, 3], $this->roleIds($user));
    }

    public function testAttachNormalizesAndAcceptsNumericStrings(): void
    {
        $user = $this->user('ana');

        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', ['1', '1', '2']);

        self::assertSame([1, 2], $this->roleIds($user));
    }

    public function testAttachIgnoresNonNumericIds(): void
    {
        $user = $this->user('ana');

        // Un intento de inyección nunca llega a la SQL: se descarta.
        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', ['1 OR 1=1', '2']);

        self::assertSame([2], $this->roleIds($user));
    }

    public function testSyncReplacesTheWholeSet(): void
    {
        $user = $this->user('ana');

        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', [1, 2, 3]);
        $this->users->syncMany($user, 'pivot_role_user', 'user_id', 'role_id', [2, 4]);

        self::assertSame([2, 4], $this->roleIds($user));
    }

    public function testSyncWithEmptyArrayRemovesEverything(): void
    {
        $user = $this->user('ana');

        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id', [1, 2]);
        $this->users->syncMany($user, 'pivot_role_user', 'user_id', 'role_id', []);

        self::assertSame([], $this->roleIds($user));
    }

    public function testSyncIsIdempotent(): void
    {
        $user = $this->user('ana');

        $this->users->syncMany($user, 'pivot_role_user', 'user_id', 'role_id', [1, 2]);
        $this->users->syncMany($user, 'pivot_role_user', 'user_id', 'role_id', [1, 2]);

        self::assertSame([1, 2], $this->roleIds($user));
    }

    public function testRelationsAreIsolatedPerOwner(): void
    {
        $ana = $this->user('ana');
        $luis = $this->user('luis');

        $this->users->attachMany($ana, 'pivot_role_user', 'user_id', 'role_id', [1]);
        $this->users->attachMany($luis, 'pivot_role_user', 'user_id', 'role_id', [2]);

        self::assertSame([1], $this->roleIds($ana));
        self::assertSame([2], $this->roleIds($luis));
    }

    public function testUnsafeIdentifiersAreRejected(): void
    {
        $user = $this->user('ana');

        $this->expectException(\InvalidArgumentException::class);

        $this->users->relatedIds('pivot_role_user; DROP TABLE pivot_users', 'user_id', 'role_id', 1);
    }

    public function testUnsafeColumnIsRejected(): void
    {
        $user = $this->user('ana');

        $this->expectException(\InvalidArgumentException::class);

        $this->users->attachMany($user, 'pivot_role_user', 'user_id', 'role_id = 1 --', [1]);
    }

    public function testUnsavedModelCannotBeRelated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('guárdalo antes');

        $this->users->attachMany(new PivotUser(), 'pivot_role_user', 'user_id', 'role_id', [1]);
    }
}
