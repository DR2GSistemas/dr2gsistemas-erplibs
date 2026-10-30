<?php

namespace Dr2g\ErpLibs\Api;

use Dr2g\ErpLibs\Exceptions\ValidationException;
use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Routing\ApiProblem;
use Dr2g\ErpLibs\Routing\Request;
use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\Validation\Validator;

/**
 * CRUD REST especializado para un modelo con un contrato JSON uniforme.
 *
 * La implementación concreta sólo declara el modelo y su contrato:
 *
 *   final class TodoController extends ApiCrud
 *   {
 *       protected function model(): string { return Todo::class; }
 *       protected function rules(): array { return ['title' => ['required', 'min:3']]; }
 *       protected function fillable(): array { return ['title', 'done']; }
 *       protected function sortable(): array { return ['id', 'title', 'created_at']; }
 *   }
 *
 * Contrato:
 *   GET    /recurso?page=1&per_page=25&sort=-created_at&q=texto&filter[campo]=valor
 *          → 200 { "data": [...], "meta": { page, per_page, total, last_page, sort } }
 *   POST   /recurso            → 201 { "data": {...} } + Location
 *   GET    /recurso/{id}       → 200 { "data": {...} }
 *   PUT    /recurso/{id}       → 200 { "data": {...} }   (parcial)
 *   PATCH  /recurso/{id}       → 200 { "data": {...} }   (parcial)
 *   DELETE /recurso/{id}       → 204
 *
 * Errores: { "error": { "status", "code", "message", "details" } }
 */
abstract class ApiCrud
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Reglas de validación (formato de `Dr2g\ErpLibs\Validation\Validator`).
     *
     * @return array<string, string|list<string>>
     */
    abstract protected function rules(): array;

    /**
     * Campos escribibles por el cliente (whitelist anti mass-assignment).
     *
     * @return list<string>
     */
    abstract protected function fillable(): array;

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['id'];
    }

    /**
     * Columnas con búsqueda libre (`?q=`); por defecto las ordenables.
     *
     * @return list<string>
     */
    protected function searchable(): array
    {
        return $this->sortable();
    }

    /**
     * Filtros `?filter[nombre]=valor` → columna.
     *
     * @return array<string, string>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Columnas devueltas; vacío = todas las visibles del modelo.
     *
     * @return list<string>
     */
    protected function visible(): array
    {
        return [];
    }

    public function index(Request $request): Response
    {
        $model = $this->model();
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = $this->perPage($request->query['per_page'] ?? null);
        $query = $model::query();

        foreach ($this->filters() as $name => $column) {
            /** @var array<string, string> $filterParams */
            $filterParams = $request->query['filter'] ?? [];
            $value = $filterParams[$name] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            // Booleanos llegan como "1"/"0"/"true"; comparar con el valor real.
            $normalized = match (true) {
                $value === 'true', $value === '1' => 1,
                $value === 'false', $value === '0' => 0,
                is_numeric($value) => $value + 0,
                default => "%{$value}%",
            };

            $query->where($column, is_string($normalized) ? 'LIKE' : '=', $normalized);
        }

        /** @var string|null $search */
        $search = $request->query['q'] ?? null;

        if (is_string($search) && $search !== '') {
            // Un único LIKE sobre las columnas buscables (OR en SQL), para que el
            // AND con los filtros anteriores no lo anule.
            $columns = array_map(
                static fn (string $column): string => "COALESCE({$column}, '') LIKE ?",
                $this->searchable()
            );
            $query->whereRaw('(' . implode(' OR ', $columns) . ')', array_fill(0, count($columns), '%' . $search . '%'));
        }

        [$sort, $direction] = $this->resolveSort((string) ($request->query['sort'] ?? ''));

        if ($sort !== null) {
            $query->orderBy($sort, $direction);
        }

        $total = $query->count();
        $rows = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return Response::json([
            'data' => array_map(fn (Model $item): array => $this->present($item), $model::hydrateMany($rows)),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'sort' => $sort === null ? null : ($direction === 'DESC' ? '-' . $sort : $sort),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $payload = $this->validate($request->input(), full: true);
            $item = $this->model()::create($payload);
        } catch (ValidationException $error) {
            return ApiProblem::validation($error->errors);
        }

        return Response::json(['data' => $this->present($item)], 201)
            ->withHeader('Location', $this->resourcePath() . '/' . $this->identifier($item));
    }

    public function show(Request $request, int|string $id): Response
    {
        $item = $this->find($id);

        return $item === null
            ? ApiProblem::notFound($this->notFoundMessage())
            : Response::json(['data' => $this->present($item)]);
    }

    public function update(Request $request, int|string $id): Response
    {
        $item = $this->find($id);

        if ($item === null) {
            return ApiProblem::notFound($this->notFoundMessage());
        }

        try {
            $payload = $this->validate($request->input(), full: false);
            $item->fill($payload)->save();
        } catch (ValidationException $error) {
            return ApiProblem::validation($error->errors);
        }

        return Response::json(['data' => $this->present($item)]);
    }

    public function destroy(Request $request, int|string $id): Response
    {
        $item = $this->find($id);

        if ($item === null) {
            return ApiProblem::notFound($this->notFoundMessage());
        }

        $item->delete();

        return Response::empty(204);
    }

    /**
     * Representación pública del recurso (oculta `#[Hidden]`, aplica `visible()`).
     *
     * @return array<string, mixed>
     */
    protected function present(Model $model): array
    {
        $data = array_diff_key($model->toArray(), array_flip($model->getHiddenAttributes()));

        if ($this->visible() !== []) {
            $data = array_intersect_key($data, array_flip($this->visible()));
        }

        return $data;
    }

    protected function resourcePath(): string
    {
        return '/api/v1';
    }

    protected function notFoundMessage(): string
    {
        return 'Resource not found.';
    }

    /**
     * Valida `$input` y devuelve sólo los campos permitidos y normalizados.
     *
     * En modo parcial (PUT/PATCH) `required` sólo aplica a los campos enviados.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function validate(array $input, bool $full): array
    {
        $rules = $this->rules();

        if (!$full) {
            foreach ($rules as $field => $fieldRules) {
                if (!array_key_exists($field, $input)) {
                    unset($rules[$field]);
                    continue;
                }

                $rules[$field] = array_values(array_filter(
                    (array) $fieldRules,
                    static fn (string $rule): bool => $rule !== 'required'
                ));
            }
        }

        Validator::assert($input, $rules);

        $payload = [];

        foreach ($this->fillable() as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $this->cast($field, $input[$field]);
            }
        }

        return $payload;
    }

    /**
     * "true"/"false" y "1"/"0" → bool para campos declarados boolean.
     */
    protected function cast(string $field, mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (in_array($value, ['true', '1', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['false', '0', 'off'], true)) {
            return false;
        }

        return $value;
    }

    /**
     * @return array{0: string|null, 1: string} columna ordenable y dirección
     */
    protected function resolveSort(string $sort): array
    {
        if ($sort === '') {
            return [null, 'ASC'];
        }

        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        return in_array($column, $this->sortable(), true)
            ? [$column, $descending ? 'DESC' : 'ASC']
            : [null, 'ASC'];
    }

    protected function find(int|string $id): ?Model
    {
        /** @var Model|null $item */
        $item = $this->model()::find($id);

        return $item;
    }

    protected function identifier(Model $model): int|string
    {
        $value = $model->toArray()[$model::getPrimaryKey()] ?? '';

        return is_int($value) || is_string($value) ? $value : (string) $value;
    }

    protected function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
