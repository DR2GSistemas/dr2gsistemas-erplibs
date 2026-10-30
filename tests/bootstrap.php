<?php

/*
 * Punto de entrada del cargador de la librería para tests y ejecutables.
 * Funciona con o sin `composer install`.
 */

require dirname(__DIR__) . '/bootstrap.php';

// Atajo: los tests de integración apuntan a un MySQL desechable vía DR2G_TEST_DSN;
// los unitarios nunca lo necesitan. Subir a contenedor cuando forme parte de CI.
