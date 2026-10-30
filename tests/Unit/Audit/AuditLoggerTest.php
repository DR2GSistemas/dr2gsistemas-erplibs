<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Audit;

use Dr2g\ErpLibs\Audit\AuditLogger;
use Dr2g\ErpLibs\Audit\Gdpr;
use Dr2g\ErpLibs\Audit\MemoryAuditStorage;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Events\Dispatcher;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

#[Table(name: 'people')]
final class Person extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;

    #[Column(type: 'VARCHAR(190)')]
    public string $email;
}

final class AuditLoggerTest extends TestCase
{
    private MemoryAuditStorage $storage;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $connection = new FakeConnection();
        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $connection]);
        Model::setDbManager($manager);

        $this->dispatcher = new Dispatcher();
        Model::setEventDispatcher($this->dispatcher);

        $this->storage = new MemoryAuditStorage();
    }

    private function logger(): AuditLogger
    {
        return (new AuditLogger($this->storage, static fn () => 'user-7'))->attach($this->dispatcher);
    }

    public function testCreateUpdateDeleteAreRecorded(): void
    {
        AuditLogger::markSensitive(Person::class, ['email']);
        $this->logger();

        $person = Person::create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $person->fill(['name' => 'Grace']);
        $person->save();
        $person->delete();

        $records = $this->storage->all();

        self::assertSame(['create', 'update', 'delete'], array_column($records, 'action'));
        self::assertSame('people', $records[0]['table']);
        self::assertSame(1, $records[0]['id']);
        self::assertSame('user-7', $records[0]['actor']);
        self::assertSame('[redacted]', $records[0]['changes']['email']);
        self::assertSame('Grace', $records[1]['changes']['name']);
    }

    public function testActorCanBeChangedAfterConstruction(): void
    {
        $logger = (new AuditLogger($this->storage))->attach($this->dispatcher);
        $logger->setActor(static fn () => 42);

        Person::create(['name' => 'Ada', 'email' => 'a@b.c']);

        self::assertSame(42, $this->storage->all()[0]['actor']);
    }

    public function testFilteredExportBySubject(): void
    {
        $this->logger();

        Person::create(['name' => 'Ada', 'email' => 'a@b.c']);

        self::assertCount(1, Gdpr::export($this->storage, 1));
        self::assertCount(0, Gdpr::export($this->storage, 99));
    }

    public function testGdprAnonymizeAndErase(): void
    {
        $row = Gdpr::anonymize(['id' => 1, 'email' => 'ada@example.com', 'name' => 'Ada', 'city' => 'Madrid']);

        self::assertSame('[erased]', $row['email']);
        self::assertSame('Madrid', $row['city']);

        $erased = Gdpr::eraseFrom([['changes' => ['email' => 'x@y.z', 'plan' => 'pro']]], ['email']);

        self::assertSame('[erased]', $erased[0]['changes']['email']);
        self::assertSame('pro', $erased[0]['changes']['plan']);
    }
}
