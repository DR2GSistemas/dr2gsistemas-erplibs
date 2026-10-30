<?php

namespace Dr2g\ErpLibs\Audit;

/**
 * Minimal in-memory channel, useful for tests and as a reference implementation.
 */
final class MemoryAuditStorage implements AuditStorageInterface
{
    /** @var list<array<string, mixed>> */
    private array $records = [];

    public function store(array $record): void
    {
        $this->records[] = $record;
    }

    public function all(array $filters = []): array
    {
        return array_values(array_filter($this->records, static function (array $record) use ($filters): bool {
            foreach ($filters as $field => $value) {
                if (($record[$field] ?? null) !== $value) {
                    return false;
                }
            }

            return true;
        }));
    }
}
