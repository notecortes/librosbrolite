<?php
/**
 * BookSwap · Punto de entrada frontal HTTP.
 *
 * Todas las peticiones son redirigidas a este archivo a través de .htaccess
 * y procesadas centralizadamente por el enrutador principal de la aplicación.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/router.php';