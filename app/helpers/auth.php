<?php
/**
 * BookSwap · auth.php — Asistente central de autenticación, control de intentos y RBAC.
 */
declare(strict_types=1);

/**
 * Consulta el número de intentos fallidos de inicio de sesión en los últimos 15 minutos.
 *
 * @param string $email Correo electrónico del usuario
 * @param string|null $ip Dirección IP opcional
 * @return int Cantidad de intentos fallidos registrados en la ventana temporal
 */
function auth_intentos_fallidos(string $email, ?string $ip = null): int {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM intentos_login
            WHERE email = ? AND fecha >= NOW() - INTERVAL 15 MINUTE
        ');
        $stmt->execute([trim(mb_strtolower($email))]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Error en auth_intentos_fallidos: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Registra un intento fallido de autenticación en la tabla intentos_login.
 *
 * @param string $email Correo electrónico
 * @param string|null $ip Dirección IP del cliente
 */
function auth_registrar_intento_fallido(string $email, ?string $ip = null): void {
    try {
        $pdo = db();
        $ipCliente = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare('
            INSERT INTO intentos_login (email, ip, fecha)
            VALUES (?, ?, NOW())
        ');
        $stmt->execute([trim(mb_strtolower($email)), $ipCliente]);
    } catch (Throwable $e) {
        error_log('Error en auth_registrar_intento_fallido: ' . $e->getMessage());
    }
}

/**
 * Limpia los intentos fallidos tras un inicio de sesión exitoso.
 *
 * @param string $email Correo electrónico del usuario
 */
function auth_limpiar_intentos(string $email): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM intentos_login WHERE email = ?');
        $stmt->execute([trim(mb_strtolower($email))]);
    } catch (Throwable $e) {
        error_log('Error en auth_limpiar_intentos: ' . $e->getMessage());
    }
}

/**
 * Verifica las credenciales locales de un usuario comprobando bloqueo por fuerza bruta.
 *
 * @param string $email Correo electrónico introducido
 * @param string $password Contraseña en texto plano
 * @return array Resultado de verificación ['ok' => bool, 'usuario' => ?array, 'bloqueado' => bool, 'error' => ?string]
 */
function auth_verificar_credenciales(string $email, string $password): array {
    $emailNormalizado = trim(mb_strtolower($email));

    // Comprobar bloqueo si acumula 5 o más intentos en los últimos 15 minutos (T-AUTH-03)
    if (auth_intentos_fallidos($emailNormalizado) >= 5) {
        return [
            'ok' => false,
            'usuario' => null,
            'bloqueado' => true,
            'error' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos.',
        ];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT u.id, u.nombre, u.email, u.password_hash, u.rol_id, u.activo, u.auth_provider,
                   r.nombre AS rol_nombre
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.email = ?
            LIMIT 1
        ');
        $stmt->execute([$emailNormalizado]);
        $usuario = $stmt->fetch();

        if (!$usuario || (int) $usuario['activo'] !== 1) {
            auth_registrar_intento_fallido($emailNormalizado);
            return [
                'ok' => false,
                'usuario' => null,
                'bloqueado' => false,
                'error' => 'Credenciales incorrectas.',
            ];
        }

        // Cuenta google-only sin contraseña local establecida (T-GOOG-05)
        if (empty($usuario['password_hash'])) {
            auth_registrar_intento_fallido($emailNormalizado);
            return [
                'ok' => false,
                'usuario' => null,
                'bloqueado' => false,
                'error' => 'Esta cuenta fue creada exclusivamente con Google. Inicia sesión con el botón de Google.',
            ];
        }

        // Comprobar hash seguro con password_verify
        if (!password_verify($password, $usuario['password_hash'])) {
            auth_registrar_intento_fallido($emailNormalizado);
            return [
                'ok' => false,
                'usuario' => null,
                'bloqueado' => false,
                'error' => 'Credenciales incorrectas.',
            ];
        }

        // Credenciales correctas: limpiar intentos fallidos acumulados
        auth_limpiar_intentos($emailNormalizado);

        return [
            'ok' => true,
            'usuario' => $usuario,
            'bloqueado' => false,
            'error' => null,
        ];
    } catch (Throwable $e) {
        error_log('Error en auth_verificar_credenciales: ' . $e->getMessage());
        return [
            'ok' => false,
            'usuario' => null,
            'bloqueado' => false,
            'error' => 'Error de autenticación.',
        ];
    }
}

/**
 * Inicia la sesión para el usuario indicado regenerando el ID de sesión y auditando.
 *
 * @param array $usuario Datos del usuario autenticado
 */
function auth_iniciar_sesion(array $usuario): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Regenerar ID de sesión para prevenir fijación de sesión (T-AUTH-02)
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $usuario['id'];

    try {
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE usuarios SET ultima_actividad = NOW() WHERE id = ?');
        $stmt->execute([(int) $usuario['id']]);

        log_accion((int) $usuario['id'], 'login_local', 'usuarios', (int) $usuario['id'], [
            'email' => $usuario['email'],
        ]);
    } catch (Throwable $e) {
        error_log('Error actualizando última actividad en login: ' . $e->getMessage());
    }
}

/**
 * Cierra la sesión activa actual del usuario.
 */
function auth_cerrar_sesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['usuario_id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Registra un nuevo usuario con credenciales locales.
 *
 * @param string $nombre Nombre completo del usuario
 * @param string $email Correo electrónico
 * @param string $password Contraseña en texto plano
 * @return array Resultado ['ok' => bool, 'usuario_id' => ?int, 'error' => ?string]
 */
function auth_registrar_usuario(string $nombre, string $email, string $password): array {
    $nombreLimpio = trim($nombre);
    $emailNormalizado = trim(mb_strtolower($email));

    if (mb_strlen($nombreLimpio) < 2) {
        return ['ok' => false, 'usuario_id' => null, 'error' => 'Por favor introduce un nombre válido.'];
    }
    if (!filter_var($emailNormalizado, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'usuario_id' => null, 'error' => 'El formato del correo electrónico no es válido.'];
    }
    if (mb_strlen($password) < 6) {
        return ['ok' => false, 'usuario_id' => null, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        // 1. Comprobar si el email ya existe
        $stmtComprobar = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmtComprobar->execute([$emailNormalizado]);
        if ($stmtComprobar->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'usuario_id' => null, 'error' => 'Ya existe una cuenta registrada con este correo electrónico.'];
        }

        // 2. Hasheo obligatorio con bcrypt (T-AUTH-04)
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        $stmtInsert = $pdo->prepare('
            INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, auth_provider, email_verificado, fecha_registro)
            VALUES (?, ?, ?, 3, 1, "local", 1, NOW())
        ');
        $stmtInsert->execute([$nombreLimpio, $emailNormalizado, $passwordHash]);
        $nuevoId = (int) $pdo->lastInsertId();

        $pdo->commit();

        log_accion($nuevoId, 'registro_usuario', 'usuarios', $nuevoId, [
            'email' => $emailNormalizado,
            'auth_provider' => 'local',
        ]);

        if (function_exists('usuarios_persistir_personalizados')) {
            usuarios_persistir_personalizados($pdo);
        }

        return ['ok' => true, 'usuario_id' => $nuevoId, 'error' => null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error en auth_registrar_usuario: ' . $e->getMessage());
        return ['ok' => false, 'usuario_id' => null, 'error' => 'Ocurrió un error al crear la cuenta. Inténtalo de nuevo.'];
    }
}

/**
 * Comprueba si el usuario autenticado tiene asignado un permiso específico en el sistema.
 *
 * @param array|null $usuario Datos del usuario
 * @param string $codigoPermiso Código del permiso a comprobar (ej: 'catalogo.editar')
 * @return bool True si tiene permiso, False en caso contrario
 */
function usuario_tiene_permiso(?array $usuario, string $codigoPermiso): bool {
    if (!$usuario || empty($usuario['rol_id'])) {
        return false;
    }
    // Administrador siempre tiene todos los permisos
    if (($usuario['rol_nombre'] ?? '') === 'ADMIN') {
        return true;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM rol_permiso rp
            JOIN permisos p ON rp.permiso_id = p.id
            WHERE rp.rol_id = ? AND p.codigo = ?
        ');
        $stmt->execute([(int) $usuario['rol_id'], $codigoPermiso]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('Error en usuario_tiene_permiso: ' . $e->getMessage());
        return false;
    }
}

/**
 * Exige que exista un usuario autenticado en la sesión actual; si no, redirige a /login.
 */
function exigir_autenticado(): void {
    global $usuario;
    if ($usuario === null) {
        header('Location: /login');
        exit;
    }
}

if (!function_exists('exigir_permiso')) {
    /**
     * Exige que el usuario posea un permiso; de lo contrario aborta con HTTP 403 Forbidden.
     *
     * @param string|array $codigoPermiso Código o lista de códigos de permisos requeridos
     */
    function exigir_permiso(string|array $codigoPermiso): void {
        global $usuario;
        if ($usuario === null) {
            header('Location: /login');
            exit;
        }
        $lista = is_array($codigoPermiso) ? $codigoPermiso : [$codigoPermiso];
        $tiene = false;
        foreach ($lista as $cp) {
            if (usuario_tiene_permiso($usuario, $cp)) {
                $tiene = true;
                break;
            }
        }
        if (!$tiene) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Prohibido</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>403 Prohibido</h1><p>No tienes permiso para esta acción; contacta con el administrador.</p><p><a href="/">Volver al inicio</a></p></body></html>';
            exit;
        }
    }
}

/**
 * Exige que el usuario tenga uno de los roles permitidos; de lo contrario aborta con HTTP 403 Forbidden.
 *
 * @param array|string $rolesPermitidos Nombre del rol o lista de nombres de rol permitidos
 */
function exigir_rol(array|string $rolesPermitidos): void {
    global $usuario;
    if ($usuario === null) {
        header('Location: /login');
        exit;
    }
    $roles = is_array($rolesPermitidos) ? $rolesPermitidos : [$rolesPermitidos];
    $rolActual = $usuario['rol_nombre'] ?? '';

    if (!in_array($rolActual, $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Prohibido</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>403 Prohibido</h1><p>Acceso denegado: esta sección está restringida.</p><p><a href="/">Volver al inicio</a></p></body></html>';
        exit;
    }
}
