<?php

namespace Dr2g\ErpLibs\Routing\Exception;

/**
 * La petición entra en conflicto con otro recurso (p. ej. un duplicado).
 */
class ConflictException extends RoutingException
{
    public function __construct(string $message = 'Conflict.')
    {
        parent::__construct($message, 409);
    }
}
