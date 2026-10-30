<?php

namespace Dr2g\ErpLibs\Routing;

/**
 * Ruta casada con sus parámetros de path.
 */
final class RouteMatch
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly Route $route,
        public readonly array $params = [],
        public readonly bool $asHead = false
    ) {
    }
}
