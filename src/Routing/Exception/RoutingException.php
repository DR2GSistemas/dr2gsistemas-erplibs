<?php

namespace Dr2g\ErpLibs\Routing\Exception;

/**
 * Base para los errores de enrutamiento. El código HTTP permite convertirlo
 * directamente en una respuesta.
 */
class RoutingException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
