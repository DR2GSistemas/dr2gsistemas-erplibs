<?php

namespace Dr2g\ErpLibs\Repositories;

use Dr2g\ErpLibs\Core\QueryBuilder;
use Dr2g\ErpLibs\Models\Model;

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
