<?php

namespace Dr2g\ErpLibs\Routing;

use Dr2g\ErpLibs\Cache\CachesResponses;

/**
 * Controlador con caché integrada. Extiende esta clase (o usa el trait
 * `CachesResponses`) y el router inyectará el `CacheManager` del request.
 */
abstract class CachedController extends Controller
{
    use CachesResponses;
}
