<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Serialization;

use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Hidden;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Serialization\Paginator;
use Dr2g\ErpLibs\Serialization\Resource;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

#[Table(name: 'members')]
final class Member extends Model
{
    #[Column(type: 'VARCHAR(100)')]
    public string $name;

    #[Column(type: 'VARCHAR(190)')]
    public string $email;

    #[Hidden]
    #[Column(type: 'VARCHAR(255)')]
    public string $password_hash;
}

final class ResourceTest extends TestCase
{
    public function testHiddenAttributesAreExcluded(): void
    {
        $member = Member::hydrate([
            'id' => 1,
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password_hash' => '$2y$secret',
        ]);

        $json = Resource::make($member)->toArray();

        self::assertArrayNotHasKey('password_hash', $json);
        self::assertSame('Ada', $json['name']);
    }

    public function testCollectionSerializesEachModel(): void
    {
        $members = Member::hydrateMany([
            ['id' => 1, 'name' => 'Ada', 'password_hash' => 'x'],
            ['id' => 2, 'name' => 'Grace', 'password_hash' => 'y'],
        ]);

        $items = Resource::collection($members);

        self::assertCount(2, $items);
        self::assertSame('Grace', $items[1]['name']);
        self::assertArrayNotHasKey('password_hash', $items[0]);
    }

    public function testIncludesAreEmbedded(): void
    {
        $member = Member::hydrate(['id' => 1, 'name' => 'Ada']);

        $data = Resource::make($member, ['team' => ['id' => 9, 'name' => 'Core']])->toArray();

        self::assertSame(['id' => 9, 'name' => 'Core'], $data['team']);
    }

    public function testToJsonProducesJson(): void
    {
        $member = Member::hydrate(['id' => 1, 'name' => 'Ada']);

        self::assertSame('{"id":1,"name":"Ada"}', Resource::make($member)->toJson());
    }
}

final class PaginatorTest extends TestCase
{
    public function testOffsetPaginationBuildsMeta(): void
    {
        $connection = new FakeConnection(['all' => [['id' => 16], ['id' => 17]]]);
        $query = new \Dr2g\ErpLibs\Core\QueryBuilder($connection, 'members');

        $page = Paginator::offset($query, page: 2, perPage: 15);

        self::assertSame('SELECT * FROM members LIMIT 15 OFFSET 15', $query->toSql());
        self::assertSame(2, $page->meta['page']);
        self::assertSame(15, $page->meta['per_page']);
        self::assertCount(2, $page->items);
    }

    public function testCursorPaginationStopsAtPageSizeAndExposesNextCursor(): void
    {
        $rows = [];
        for ($id = 11; $id <= 13; $id++) {
            $rows[] = ['id' => $id];
        }

        $connection = new FakeConnection(['all' => $rows]);
        $query = new \Dr2g\ErpLibs\Core\QueryBuilder($connection, 'members');

        $page = Paginator::cursor($query, 'id', after: 10, perPage: 2);

        self::assertSame('SELECT * FROM members WHERE id > ? ORDER BY id ASC LIMIT 3', $query->toSql());
        self::assertCount(2, $page->items);
        self::assertSame(12, $page->meta['next_cursor']);
        self::assertTrue($page->meta['has_more']);
    }

    public function testCursorPaginationWithoutMoreHasNoNextCursor(): void
    {
        $connection = new FakeConnection(['all' => [['id' => 1], ['id' => 2]]]);
        $query = new \Dr2g\ErpLibs\Core\QueryBuilder($connection, 'members');

        $page = Paginator::cursor($query, 'id', perPage: 5);

        self::assertNull($page->meta['next_cursor']);
        self::assertFalse($page->meta['has_more']);
    }
}
