<?php

namespace Dr2g\ErpLibs\Security;

class SQLInjectionGuard
{
    private const IDENTIFIER = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

    private const DIRECTIONS = ['ASC', 'DESC'];

    public static function assertIdentifier(string $identifier, string $context = 'identifier'): string
    {
        if (!self::validateColumnName($identifier)) {
            throw new \InvalidArgumentException("Invalid {$context}: '{$identifier}'.");
        }

        return $identifier;
    }

    public static function assertOperator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));

        if (!in_array($normalized, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid SQL operator: '{$operator}'.");
        }

        return $normalized;
    }

    public static function assertDirection(string $direction): string
    {
        $normalized = strtoupper(trim($direction));

        if (!in_array($normalized, self::DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid sort direction: '{$direction}'.");
        }

        return $normalized;
    }

    public static function validateTableName(string $table): bool
    {
        return preg_match(self::IDENTIFIER, $table) === 1;
    }

    public static function validateColumnName(string $column): bool
    {
        return preg_match(self::IDENTIFIER, $column) === 1;
    }
}
