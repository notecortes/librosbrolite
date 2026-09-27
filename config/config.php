<?php
/**
 * Configuración central de BookSwap.
 * Lee variables de entorno (Docker) con valores por defecto de desarrollo.
 * La app NUNCA debe hardcodear credenciales: siempre usar estas constantes.
 */
declare(strict_types=1);

define('APP_ENV',      getenv('APP_ENV')      ?: 'development');
define('DB_HOST',      getenv('DB_HOST')      ?: 'db');
define('DB_NAME',      getenv('DB_NAME')      ?: 'bookswap');
define('DB_USER',      getenv('DB_USER')      ?: 'bookswap');
define('DB_PASS',      getenv('DB_PASS')      ?: 'bookswap_pass');
define('DB_ROOT_PASS', getenv('DB_ROOT_PASS') ?: 'root_secret_dev'); // SOLO tests
define('MAIL_FROM',    getenv('MAIL_FROM')    ?: 'no-reply@bookswap.local');

date_default_timezone_set('Europe/Madrid');

// Control de exposición de errores según entorno
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}