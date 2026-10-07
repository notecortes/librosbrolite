<?php
/**
 * BookSwap · funciones.php — helpers globales mínimos (CANÓNICO).
 * El agente puede AÑADIR funciones, no modificar las existentes.
 */
declare(strict_types=1);

/** Escapado HTML obligatorio en toda salida dinámica. */
function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Iniciales para el avatar cuando no hay foto (máx. 2 letras). */
function iniciales(string $nombre): string {
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : 'U';
}

if (!function_exists('db')) {
    /**
     * Proporciona una conexión PDO reutilizable (singleton) a la base de datos MySQL/MariaDB.
     */
    function db(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        }
        return $pdo;
    }
}

/**
 * Obtiene el token CSRF activo de la sesión actual, generándolo si aún no existe.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * Genera un campo HTML oculto con el token CSRF para incrustar en formularios POST.
 */
function csrf_campo(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Valida un token CSRF recibido contra el token almacenado en la sesión.
 */
function csrf_verificar(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

/**
 * Almacena un mensaje flash en la sesión para ser mostrado en la siguiente página.
 */
function flash(string $mensaje, string $tipo = 'info'): void {
    $_SESSION['flash'] = [
        'mensaje' => $mensaje,
        'tipo' => $tipo,
    ];
}

/**
 * Redirige al navegador a una ruta relativa o absoluta y detiene la ejecución.
 */
function redireccionar(string $url): void {
    header('Location: ' . $url);
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . e($url) . '"></head><body><p>Redirigiendo a <a href="' . e($url) . '">' . e($url) . '</a>...</p></body></html>';
    exit;
}

/**
 * Renderiza una vista hija dentro del layout base canónico respetando su contrato.
 *
 * @param string $vista Nombre de la vista en app/views/ (sin extensión .php)
 * @param array $datos Variables asociativas disponibles para la vista hija
 * @param string $titulo Título de la página que se mostrará en el encabezado
 */
function render_vista(string $vista, array $datos = [], string $titulo = 'Inicio'): void {
    global $usuario, $config, $notif_count, $csrf_token, $saldo, $tokensComprometidos, $numReservasActivas;

    // Extraer datos pasados a la vista para su uso directo
    extract($datos, EXTR_SKIP);

    // Capturar el contenido generado por la vista
    ob_start();
    $archivoVista = __DIR__ . '/../views/' . $vista . '.php';
    if (file_exists($archivoVista)) {
        require $archivoVista;
    } else {
        echo '<div class="alert alert-danger">Error: no se encontró la vista ' . e($vista) . '</div>';
    }
    $contenido = ob_get_clean();

    // Rellenar el saldo del navbar (#saldo-nav) desde el router cuando hay sesión activa
    if ($usuario !== null) {
        $saldoNumero = (int) ($saldo ?? 0);
        $contenido .= '<script>
        (function () {
            var ponerSaldo = function () {
                var el = document.getElementById("saldo-nav");
                if (el) {
                    el.dataset.contador = "' . $saldoNumero . '";
                    el.textContent = "' . $saldoNumero . '";
                }
            };
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", ponerSaldo);
            } else {
                ponerSaldo();
            }
        })();
        </script>';
    }

    // Asegurar título y token CSRF
    $csrf_token = csrf_token();

    // Invocar el layout base canónico
    require __DIR__ . '/../views/layouts/base.php';
}

/**
 * Exige la presencia y validez de un token CSRF en la petición actual.
 */
function exigir_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verificar($token)) {
        http_response_code(403);
        echo 'Error de seguridad: token CSRF inválido o ausente.';
        exit;
    }
}