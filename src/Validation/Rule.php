<?php

namespace Dr2g\ErpLibs\Validation;

/**
 * A single validation rule. Custom rules are plain callables:
 * fn(mixed $value, array $parameters): ?string returning an error message.
 */
class Rule
{
    /** @var array<string, callable> */
    private static array $custom = [];

    public static function extend(string $name, callable $rule): void
    {
        self::$custom[$name] = $rule;
    }

    public static function validate(string $rule, mixed $value): ?string
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
        $name = strtolower(trim((string) $name));
        $parameter = $parameter === null ? null : trim($parameter);

        if (isset(self::$custom[$name])) {
            return (self::$custom[$name])($value, $parameter === null ? [] : explode(',', $parameter));
        }

        return match ($name) {
            'required' => self::isBlank($value) ? 'is required' : null,
            'string' => $value !== null && !is_string($value) ? 'must be a string' : null,
            'integer' => $value !== null && !is_int($value) && !ctype_digit((string) $value) ? 'must be an integer' : null,
            'numeric' => $value !== null && !is_numeric($value) ? 'must be numeric' : null,
            'email' => $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'must be a valid email' : null,
            'min' => self::checkMin($value, $parameter),
            'max' => self::checkMax($value, $parameter),
            'boolean' => $value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1'], true) ? 'must be a boolean' : null,
            default => throw new \InvalidArgumentException("Unknown validation rule: '{$name}'."),
        };
    }

    private static function checkMin(mixed $value, ?string $parameter): ?string
    {
        $limit = (int) $parameter;

        if (is_string($value)) {
            return mb_strlen($value) < $limit ? "must be at least {$limit} characters" : null;
        }

        return is_numeric($value) && $value < $limit ? "must be at least {$limit}" : null;
    }

    private static function checkMax(mixed $value, ?string $parameter): ?string
    {
        $limit = (int) $parameter;

        if (is_string($value)) {
            return mb_strlen($value) > $limit ? "must be at most {$limit} characters" : null;
        }

        return is_numeric($value) && $value > $limit ? "must be at most {$limit}" : null;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
