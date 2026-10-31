<?php

namespace Dr2g\ErpLibs\Repositories;

use Dr2g\ErpLibs\Core\Connection;
use Dr2g\ErpLibs\Core\QueryBuilder;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Security\SQLInjectionGuard;

/**
 * Acceso a datos de un modelo: encapsula consultas y evita repetir SQL en
 * controladores.
 *
 * La clase concreta sólo declara el modelo:
 *
 *     final class ProductRepository extends Repository
 *     {
 *         protected function model(): string { return Product::class; }
 *     }
 *
 * Los métodos devuelven modelos hidratados (nunca arrays sueltos), de modo que
 * validación, relaciones y serialización siguen funcionando.
 *
 * @template T of Model
 */
abstract class Repository
{
    /**
     * @return class-string<T>
     */
    abstract protected function model(): string;

    /**
     * @return T|null
     */
    public function find(int|string $id): ?Model
    {
        $model = $this->model();

        return $model::find($id);
    }

    /**
     * Devuelve el modelo o lanza excepción de "no encontrado".
     *
     * @return T
     */
    public function findOrFail(int|string $id): Model
    {
        $model = $this->model();
        $found = $model::find($id);

        if ($found === null) {
            throw new \Dr2g\ErpLibs\Routing\Exception\NotFoundException(
                $this->notFoundMessage()
            );
        }

        return $found;
    }

    /**
     * @return list<T>
     */
    public function all(): array
    {
        $model = $this->model();

        return $model::all();
    }

    /**
     * Listado paginado con orden opcional.
     *
     * @param array{0: string, 1?: string} $order par [columna, dirección]
     * @return array{items: list<T>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $page = 1, int $perPage = 25, array $order = ['id', 'ASC']): array
    {
        $model = $this->model();
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $query = $model::query();

        if ($model::usesSoftDeletes()) {
            $query->where('deleted_at', null);
        }

        $total = $query->count();
        $rows = $this->ordered(clone $query, $order)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'items' => $model::hydrateMany($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function create(array $attributes): Model
    {
        $model = $this->model();

        return $model::create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        /** @var T $model */
        return $model;
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function count(): int
    {
        $model = $this->model();

        return $model::query()->count();
    }

    /**
     * Añade relacionadas a una tabla pivote, ignorando las que ya existan.
     *
     * @param list<int|string> $ids
     */
    public function attachMany(Model $model, string $pivot, string $ownerKey, string $relatedKey, array $ids): void
    {
        $connection = $this->connection();
        $ownerId = $this->ownerId($model);
        $existing = $this->relatedIds($pivot, $ownerKey, $relatedKey, $ownerId);

        foreach ($this->cleanIds($ids) as $relatedId) {
            if (in_array($relatedId, $existing, true)) {
                continue;
            }

            $connection->insert($pivot, [
                $ownerKey => $ownerId,
                $relatedKey => $relatedId,
            ]);
        }
    }

    /**
     * Deja en la tabla pivote exactamente las relacionadas indicadas.
     *
     * @param list<int|string> $ids
     */
    public function syncMany(Model $model, string $pivot, string $ownerKey, string $relatedKey, array $ids): void
    {
        $connection = $this->connection();
        $ownerId = $this->ownerId($model);
        $target = $this->cleanIds($ids);
        $existing = $this->relatedIds($pivot, $ownerKey, $relatedKey, $ownerId);

        foreach (array_diff($existing, $target) as $removed) {
            $connection->delete(
                $pivot,
                sprintf('%s = ? AND %s = ?', $ownerKey, $relatedKey),
                [$ownerId, $removed]
            );
        }

        $this->attachMany($model, $pivot, $ownerKey, $relatedKey, $target);
    }

    /**
     * Identificadores relacionados ya guardados en la tabla pivote.
     *
     * @return list<int>
     */
    public function relatedIds(string $pivot, string $ownerKey, string $relatedKey, int|string $ownerId): array
    {
        $this->assertPivot($pivot, $ownerKey, $relatedKey);

        $rows = $this->connection()->select(
            sprintf('SELECT %s AS related_id FROM %s WHERE %s = ?', $relatedKey, $pivot, $ownerKey),
            [$ownerId]
        );

        return array_map(static fn (array $row): int => (int) $row['related_id'], $rows);
    }

    /**
     * Comprueba que tabla y columnas son identificadores simples antes de
     * interpolarlas: los valores siempre van por binding.
     */
    private function assertPivot(string $pivot, string $ownerKey, string $relatedKey): void
    {
        SQLInjectionGuard::assertIdentifier($pivot, 'pivot table');
        SQLInjectionGuard::assertIdentifier($ownerKey, 'owner key');
        SQLInjectionGuard::assertIdentifier($relatedKey, 'related key');
    }

    /**
     * @return list<int>
     */
    /**
     * @param list<int|string> $ids
     * @return list<int>
     */
    private function cleanIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (is_int($id)) {
                $clean[] = $id;

                continue;
            }

            // Cadenas: sólo dígitos. Cualquier otra cosa se descarta en vez de
            // llegar a la SQL.
            if (ctype_digit($id)) {
                $clean[] = (int) $id;
            }
        }

        return array_values(array_unique($clean));
    }

    private function ownerId(Model $model): int
    {
        $key = $model::getPrimaryKey();
        $value = $model->toArray()[$key] ?? null;

        if ($value === null) {
            throw new \RuntimeException('El modelo no tiene clave primaria: guárdalo antes de relacionarlo.');
        }

        return (int) $value;
    }

    private function connection(): Connection
    {
        $model = $this->model();

        return $model::connection();
    }

    /**
     * @param array{0: string, 1?: string} $order
     */
    private function ordered(QueryBuilder $query, array $order): QueryBuilder
    {
        $column = $order[0];
        $direction = strtoupper($order[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        return $query->orderBy($column, $direction);
    }

    protected function notFoundMessage(): string
    {
        $model = $this->model();

        return 'Registro no encontrado: ' . $model::getTableName() . '.';
    }
}
