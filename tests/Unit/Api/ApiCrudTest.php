<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\Api;

use Dr2g\ErpLibs\Api\ApiCrud;
use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\DatabaseManager;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use PHPUnit\Framework\TestCase;

#[Table(name: 'api_items')]
final class ApiItem extends Model
{
    #[Column(type: 'VARCHAR(120)')]
    public string $title;

    #[Column(type: 'BOOLEAN')]
    public bool $active = false;

    #[Column(type: 'VARCHAR(255)', nullable: true)]
    public ?string $secret = null;
}

final class ItemController extends ApiCrud
{
    protected function model(): string
    {
        return ApiItem::class;
    }

    protected function rules(): array
    {
        return ['title' => ['required', 'min:3'], 'active' => ['boolean']];
    }

    protected function fillable(): array
    {
        return ['title', 'active'];
    }

    protected function sortable(): array
    {
        return ['id', 'title'];
    }

    protected function filters(): array
    {
        return ['active' => 'active'];
    }

    protected function visible(): array
    {
        return ['id', 'title', 'active'];
    }
}

final class ApiCrudTest extends TestCase
{
    private ItemController $controller;

    protected function setUp(): void
    {
        $connection = new Connection(['dsn' => 'sqlite::memory:']);
        $connection->execute(
            'CREATE TABLE api_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(120) NOT NULL,
                active INTEGER NOT NULL DEFAULT 0,
                secret VARCHAR(255) NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        $manager = new DatabaseManager(['default' => 'test', 'connections' => ['test' => []]]);
        (new \ReflectionProperty(DatabaseManager::class, 'connections'))->setValue($manager, ['test' => $connection]);
        Model::setDbManager($manager);
        Model::flushMetadata();

        $this->controller = new ItemController();
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function call(string $method, array $body = [], array $query = [], int|string $id = 0): Response
    {
        $request = Request::create($method, '/api/v1/items', query: $query, body: $body === [] ? '' : (string) json_encode($body), headers: ['Content-Type' => 'application/json']);

        return match ($method) {
            'POST' => $this->controller->store($request),
            'GET' => $id === 0 ? $this->controller->index($request) : $this->controller->show($request, $id),
            'PATCH', 'PUT' => $this->controller->update($request, $id),
            'DELETE' => $this->controller->destroy($request, $id),
            default => throw new \InvalidArgumentException($method),
        };
    }

    public function testStoreCreatesResourceWithLocation(): void
    {
        $response = $this->call('POST', ['title' => 'Primero', 'secret' => 'x']);

        self::assertSame(201, $response->status);
        self::assertSame('/api/v1/1', $response->headers['Location']);
        $data = json_decode($response->body, true)['data'];
        self::assertSame(1, $data['id']);
        self::assertSame('Primero', $data['title']);
        self::assertArrayNotHasKey('secret', $data);
    }

    public function testValidationReturns422Problem(): void
    {
        $response = $this->call('POST', ['title' => 'ab']);

        self::assertSame(422, $response->status);
        self::assertSame('validation_failed', json_decode($response->body, true)['error']['code']);
    }

    public function testMassAssignmentIsBlockedByFillable(): void
    {
        $response = $this->call('POST', ['title' => 'Con id', 'id' => 999, 'secret' => 'filtrado']);

        $data = json_decode($response->body, true)['data'];
        self::assertSame(1, $data['id']);
        self::assertSame(['id', 'title'], array_values(array_intersect(['id', 'title'], array_keys($data))));
        self::assertArrayNotHasKey('secret', $data);
    }

    public function testPartialUpdateOnlyChangesSentFields(): void
    {
        $this->call('POST', ['title' => 'Original', 'active' => true]);
        $response = $this->call('PATCH', ['active' => false], id: 1);

        $data = json_decode($response->body, true)['data'];
        self::assertSame('Original', $data['title']);
        self::assertFalse($data['active']);
    }

    public function testPaginationMetadata(): void
    {
        foreach (['aaa', 'bbb', 'ccc'] as $title) {
            $this->call('POST', ['title' => $title]);
        }

        $meta = json_decode($this->call('GET', query: ['per_page' => '2', 'page' => '2'])->body, true)['meta'];

        self::assertSame(3, $meta['total']);
        self::assertSame(2, $meta['page']);
        self::assertSame(2, $meta['per_page']);
        self::assertSame(2, $meta['last_page']);
    }

    public function testPerPageIsCapped(): void
    {
        $meta = json_decode($this->call('GET', query: ['per_page' => '1000'])->body, true)['meta'];

        self::assertSame(ApiCrud::MAX_PER_PAGE, $meta['per_page']);
    }

    public function testSortingIgnoresUnknownColumns(): void
    {
        foreach (['bbb', 'aaa'] as $title) {
            $this->call('POST', ['title' => $title]);
        }

        $bodies = json_decode($this->call('GET', query: ['sort' => '-title'])->body, true);
        self::assertSame('bbb', $bodies['data'][0]['title']);
        self::assertSame('-title', $bodies['meta']['sort']);

        $unsafe = json_decode($this->call('GET', query: ['sort' => 'password'])->body, true);
        self::assertNull($unsafe['meta']['sort']);
    }

    public function testSearchUsesOrBetweenColumns(): void
    {
        $this->call('POST', ['title' => 'Alfa']);
        $this->call('POST', ['title' => 'Beta']);

        $total = json_decode($this->call('GET', query: ['q' => 'Alf'])->body, true)['meta']['total'];

        self::assertSame(1, $total);
    }

    public function testBooleanFilter(): void
    {
        $this->call('POST', ['title' => 'Activa', 'active' => true]);
        $this->call('POST', ['title' => 'Inactiva', 'active' => false]);

        $body = json_decode($this->call('GET', query: ['filter' => ['active' => '1']])->body, true);

        self::assertSame(1, $body['meta']['total']);
        self::assertSame('Activa', $body['data'][0]['title']);
    }

    public function testShowMissingReturns404(): void
    {
        $response = $this->call('GET', id: 55);

        self::assertSame(404, $response->status);
        self::assertSame('not_found', json_decode($response->body, true)['error']['code']);
    }

    public function testDeleteReturns204AndRemoves(): void
    {
        $this->call('POST', ['title' => 'Borrar']);

        self::assertSame(204, $this->call('DELETE', id: 1)->status);
        self::assertSame(404, $this->call('GET', id: 1)->status);
    }

    public function testVisibleFieldsHideOthers(): void
    {
        $this->call('POST', ['title' => 'Visible', 'secret' => 'no debería salir']);

        self::assertStringNotContainsString('secret', $this->call('GET', id: 1)->body);
    }
}
