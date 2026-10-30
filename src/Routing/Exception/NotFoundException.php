<?php

namespace Dr2g\ErpLibs\Routing\Exception;

/**
 * El cliente pidió un recurso que no existe o un método no permitido.
 */
class NotFoundException extends RoutingException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(string $message = 'Route not found.', private readonly array $allowedMethods = [])
    {
        parent::__construct($message, $allowedMethods === [] ? 404 : 405);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
