<?php

namespace Dr2g\ErpLibs\Serialization;

use Dr2g\ErpLibs\Core\QueryBuilder;

/**
 * Offset or cursor pagination over a QueryBuilder.
 */
class Paginator
{
    /**
     * @param list<mixed> $items
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta
    ) {
    }

    public static function offset(QueryBuilder $query, int $page = 1, int $perPage = 15): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $query->count();
        $items = $query->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return new self($items, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public static function cursor(
        QueryBuilder $query,
        string $cursorColumn,
        mixed $after = null,
        int $perPage = 15
    ): self {
        $perPage = max(1, $perPage);

        if ($after !== null) {
            $query->where($cursorColumn, '>', $after);
        }

        $items = $query->orderBy($cursorColumn)->limit($perPage + 1)->get();
        $hasMore = count($items) > $perPage;
        $items = array_slice($items, 0, $perPage);
        $next = $hasMore ? ($items[$perPage - 1][$cursorColumn] ?? null) : null;

        return new self($items, [
            'per_page' => $perPage,
            'next_cursor' => $next,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['items' => $this->items, 'meta' => $this->meta];
    }
}
