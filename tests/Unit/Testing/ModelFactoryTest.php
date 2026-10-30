<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Testing;

use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Testing\FakeConnection;
use Dr2g\ErpLibs\Testing\ModelFactory;
use PHPUnit\Framework\TestCase;

#[Table(name: 'widgets')]
final class Widget extends Model
{
    #[Column(type: 'VARCHAR(50)')]
    public string $label;
}

final class ModelFactoryTest extends TestCase
{
    public function testWithFakeConnectionWiresTheOrm(): void
    {
        $connection = ModelFactory::withFakeConnection(['last_insert_id' => 42]);

        $widget = Widget::create(['label' => 'uno']);

        self::assertSame(42, $widget->id);
        self::assertSame('widgets', $connection->inserts[0]['table']);
        self::assertSame(['label' => 'uno'], $connection->inserts[0]['data']);
    }

    public function testUsingRealConnectionSubclass(): void
    {
        $connection = new FakeConnection(['all' => [['id' => 1, 'label' => 'a']]]);
        ModelFactory::using($connection);

        $widgets = Widget::all();

        self::assertCount(1, $widgets);
        self::assertSame('a', $widgets[0]->label);
    }
}
