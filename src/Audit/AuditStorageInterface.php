<?php

namespace Dr2g\ErpLibs\Audit;

/**
 * Audit channel contract. Implementations receive one record per CRUD event;
 * the default channel keeps them in memory, callers can plug any store.
 */
interface AuditStorageInterface
{
    /**
     * @param array<string, mixed> $record
     */
    public function store(array $record): void;

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array;
}
