<?php

namespace Dr2g\ErpLibs\Routing\Exception;

/**
 * La petición no cumple el contrato (datos inválidos, cuerpo mal formado).
 */
class UnprocessableEntityException extends RoutingException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(string $message = 'Unprocessable entity.', private readonly array $errors = [])
    {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
