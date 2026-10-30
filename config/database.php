<?php

/*
 * Configuración de base de datos de la librería.
 *
 * Puede sobrescribirse desde el proyecto consumidor con la variable de entorno
 * DR2G_DB_* (útil en CI) o editando este archivo en local.
 */

$env = static fn (string $key, string $default): string => (string) (getenv($key) ?: $default);

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => $env('DR2G_DB_DRIVER', 'mysql'),
            'host' => $env('DR2G_DB_HOST', '127.0.0.1'),
            'port' => (int) $env('DR2G_DB_PORT', '3306'),
            'database' => $env('DR2G_DB_NAME', 'azulerp'),
            'username' => $env('DR2G_DB_USER', 'root'),
            'password' => $env('DR2G_DB_PASSWORD', ''),
            'charset' => $env('DR2G_DB_CHARSET', 'utf8mb4'),
        ],
    ],
];
