<?php

namespace Dr2g\ErpLibs\Exceptions;

class ValidationException extends \Exception
{
    /** @param array<string, list<string>> $errors */
    public function __construct(public readonly array $errors = [])
    {
        $flattened = [];

        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $flattened[] = "{$field}: {$message}";
            }
        }

        parent::__construct('Validation failed: ' . implode(', ', $flattened));
    }
}
