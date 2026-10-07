<?php
/**
 * BookSwap · bootstrap.php — Inicialización central de la aplicación (v4.1).
 *
 * Centraliza:
 * - Emisión de cabeceras de seguridad HTTP básicas.
 * - Inicio seguro de sesión PHP (session_start con cookies HttpOnly y SameSite=Lax).
 * - Carga de configuración desde la base de datos (config_cargar).
 * - Carga modular de asistentes y helpers del sistema.
 * - Inicialización del usuario autenticado (datos y rol_nombre).
 * - Cálculo del saldo de tokens (SUM del ledger inmutable) y notificaciones no leídas.
 * - Generación del token CSRF global.
 */
declare(strict_types=1);

// 1. Cabeceras de seguridad HTTP en todas las respuestas
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// 2. Inicio de sesión con parámetros seguros
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// 3. Inclusión de configuración y asistentes globales obligatorios
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers/funciones.php';
require_once __DIR__ . '/helpers/centro.php';

// Inclusión modular de asistentes según existan en el sistema
$helpersOpcionales = [
    'permisos.php',
    'auditoria.php',
    'auth.php',
    'auth_google.php',
    'catalogo.php',
    'catalogo_api.php',
    'csv.php',
    'reservas.php',
    'mostrador.php',
    'ledger.php',
    'configuracion.php',
    'backup.php',
    'metricas.php',
    'notificaciones.php',
    'usuario_persistencia.php',
    'historial.php',
    'admin.php',
    'password_reset.php',
    'wishlist.php',
];

foreach ($helpersOpcionales as $nombreHelper) {
    $rutaHelper = __DIR__ . '/helpers/' . $nombreHelper;
    if (file_exists($rutaHelper)) {
        require_once $rutaHelper;
    }
}

// 4. Variables globales del contrato de interfaz
$config = [
    'centro_nombre' => 'LibrosBro — Biblioteca Ciudadana',
    'centro_direccion' => '',
    'centro_telefono' => '',
    'centro_email' => '',
    'centro_horario' => '',
    'coste_libro' => '1',
    'bono_deposito' => '1',
    'bono_bienvenida' => '0',
    'horas_reserva' => '72',
    'max_reservas_activas' => '3',
];
$usuario = null;
$saldo = 0;
$tokensComprometidos = 0;
$numReservasActivas = 0;
$notif_count = 0;
$csrf_token = csrf_token();

// 5. Carga de datos desde base de datos con degradación elegante si no responde
try {
    $pdo = db();

    // Asegurar restauración de usuarios persistentes si existen
    if (function_exists('usuarios_restaurar_personalizados')) {
        usuarios_restaurar_personalizados($pdo);
    }

    // Cargar toda la configuración del sistema
    $config = array_merge($config, config_cargar($pdo));

    // Si hay una sesión de usuario activa, recuperar sus datos
    if (!empty($_SESSION['usuario_id'])) {
        $stmtUsuario = $pdo->prepare('
            SELECT u.id, u.nombre, u.email, u.rol_id, u.activo, u.foto_url, u.auth_provider,
                   r.nombre AS rol_nombre
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ? AND u.activo = 1
            LIMIT 1
        ');
        $stmtUsuario->execute([(int) $_SESSION['usuario_id']]);
        $datosUsuario = $stmtUsuario->fetch();

        if ($datosUsuario) {
            $usuario = $datosUsuario;

            // Saldo de tokens desde el ledger inmutable: SUM(cantidad)
            $stmtSaldo = $pdo->prepare('
                SELECT COALESCE(SUM(cantidad), 0)
                FROM movimientos_tokens
                WHERE usuario_id = ?
            ');
            $stmtSaldo->execute([$usuario['id']]);
            $saldo = (int) $stmtSaldo->fetchColumn();

            // Reservas activas y tokens comprometidos para tooltip del saldo
            $stmtResAct = $pdo->prepare("
                SELECT COUNT(*) as num_reservas, COALESCE(SUM(tokens), 0) as tokens_bloqueados
                FROM transacciones
                WHERE usuario_id = ? AND tipo = 'reserva' AND estado = 'activa'
            ");
            $stmtResAct->execute([$usuario['id']]);
            $resInfo = $stmtResAct->fetch(PDO::FETCH_ASSOC);
            $numReservasActivas = (int) ($resInfo['num_reservas'] ?? 0);
            $tokensComprometidos = (int) ($resInfo['tokens_bloqueados'] ?? 0);
            if ($tokensComprometidos === 0 && $numReservasActivas > 0) {
                $tokensComprometidos = $numReservasActivas;
            }

            // Contar notificaciones no leídas para la campana del navbar
            $stmtNotif = $pdo->prepare('
                SELECT COUNT(*)
                FROM notificaciones
                WHERE usuario_id = ? AND leida = 0
            ');
            $stmtNotif->execute([$usuario['id']]);
            $notif_count = (int) $stmtNotif->fetchColumn();

            // Inicializar permisos en sesión si no existen o la versión cambió
            if (function_exists('permisos_cargar_usuario')) {
                $verActual = function_exists('permisos_version_actual') ? permisos_version_actual() : 1;
                if (!isset($_SESSION['permisos']) || ($_SESSION['permisos_version'] ?? 0) !== $verActual) {
                    permisos_cargar_usuario($pdo, (int) $usuario['id']);
                }
            }
        } else {
            // Usuario inexistente o inactivo: limpiar sesión
            unset($_SESSION['usuario_id']);
        }
    }
} catch (Throwable $e) {
    error_log('Error en bootstrap de BookSwap: ' . $e->getMessage());
}
