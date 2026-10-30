<?php

/*
 * Configuración de base de datos de la librería.
 *
 * SOLO valores por defecto para desarrollo local. En producción y CI define
 * las variables de entorno DR2G_DB_* y no versiones credenciales reales.
 * Ningún secreto debe escribirse en este archivo.
 */

$env = static fn (string $key, string $default): string => (string) (getenv($key) ?: $default);

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => $env('DR2G_DB_DRIVER', 'mysql'),
            'host' => $env('DR2G_DB_HOST', '127.0.0.1'),
            'port' => (int) $env('DR2G_DB_PORT', '3306'),
            'database' => $env('DR2G_DB_NAME', 'dr2g'),
            'username' => $env('DR2G_DB_USER', 'root'),
            'password' => $env('DR2G_DB_PASSWORD', ''),
            'charset' => $env('DR2G_DB_CHARSET', 'utf8mb4'),
        ],
    ],
];
