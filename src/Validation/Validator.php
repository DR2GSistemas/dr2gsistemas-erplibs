<?php

namespace Dr2g\ErpLibs\Validation;

use Dr2g\ErpLibs\Exceptions\ValidationException;

/**
 * Validates an attribute map against rules keyed by attribute.
 */
class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, list<string>> errors keyed by attribute
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $attribute => $attributeRules) {
            $value = $data[$attribute] ?? null;

            foreach ((array) $attributeRules as $rule) {
                $message = Rule::validate((string) $rule, $value);

                if ($message !== null) {
                    $errors[$attribute][] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @throws ValidationException
     */
    public static function assert(array $data, array $rules): void
    {
        $errors = self::validate($data, $rules);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
