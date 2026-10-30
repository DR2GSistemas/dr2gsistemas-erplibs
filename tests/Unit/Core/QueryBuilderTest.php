<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Core;

use Dr2g\ErpLibs\Core\QueryBuilder;
use Dr2g\ErpLibs\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private function builder(): QueryBuilder
    {
        return new QueryBuilder(new FakeConnection(), 'users');
    }

    public function testSelectWhereOrderLimit(): void
    {
        $query = $this->builder()
            ->select(['id', 'name'])
            ->where('status', 'active')
            ->where('age', '>=', 18)
            ->orderBy('name', 'desc')
            ->limit(10)
            ->offset(20);

        self::assertSame(
            'SELECT id, name FROM users WHERE status = ? AND age >= ? ORDER BY name DESC LIMIT 10 OFFSET 20',
            $query->toSql()
        );
        self::assertSame(['active', 18], $query->getBindings());
    }

    public function testWhereNullBecomesIsNull(): void
    {
        self::assertSame('SELECT * FROM users WHERE deleted_at IS NULL', $this->builder()->where('deleted_at', null)->toSql());
    }

    public function testWhereInAndOrWhere(): void
    {
        $query = $this->builder()->where('team', 'a')->orWhere('role', 'admin');

        self::assertSame('SELECT * FROM users WHERE team = ? AND (role = ?)', $query->toSql());
        self::assertSame(['a', 'admin'], $query->getBindings());
    }

    public function testOrWhereWorksAsFirstCondition(): void
    {
        self::assertSame('SELECT * FROM users WHERE (role = ?)', $this->builder()->orWhere('role', 'admin')->toSql());
    }

    public function testWhereInWithEmptyListMatchesNothing(): void
    {
        self::assertSame('SELECT * FROM users WHERE 1 = 0', $this->builder()->whereIn('id', [])->toSql());
    }

    public function testRejectsInjectedIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->where('id; DROP TABLE users', 1);
    }

    public function testRejectsInjectedOperatorAndDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->where('id', '= 1 OR 1=1 --', 1);
    }

    public function testRejectsDirectionInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->orderBy('id', 'ASC; DROP TABLE users');
    }
}
