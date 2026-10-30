<?php

namespace Dr2g\ErpLibs\Audit;

/**
 * GDPR helpers: anonymisation and subject export.
 */
final class Gdpr
{
    /**
     * Masks personal data fields in a row.
     *
     * @param array<string, mixed> $row
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    public static function anonymize(array $row, array $fields = ['email', 'name', 'phone', 'address']): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = '[erased]';
            }
        }

        return $row;
    }

    /**
     * Everything the audit store holds about one subject id.
     *
     * @return list<array<string, mixed>>
     */
    public static function export(AuditStorageInterface $storage, string|int $subjectId): array
    {
        return $storage->all(['id' => $subjectId]);
    }

    /**
     * Erases selected fields inside every record's change set.
     *
     * @param list<array<string, mixed>> $records
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    public static function eraseFrom(array $records, array $fields): array
    {
        return array_map(static function (array $record) use ($fields): array {
            $record['changes'] = self::anonymize((array) ($record['changes'] ?? []), $fields);
            return $record;
        }, $records);
    }
}
