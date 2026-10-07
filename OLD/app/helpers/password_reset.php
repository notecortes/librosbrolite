<?php
/**
 * BookSwap · password_reset.php — Asistente para la recuperación y restablecimiento de contraseñas (v4.7).
 *
 * Incluye:
 * - Envío de correos mediante mail() nativo de PHP con degradación y registro en desarrollo.
 * - Control de rate-limiting (máx. 3 solicitudes por email en 1 hora).
 * - Protección anti-enumeración (misma respuesta pública siempre).
 * - Generación segura de tokens con hash SHA-256 (nunca en claro en la BD).
 * - Invalidación automática de tokens previos y caducidad temporal (reset_horas).
 * - Notificación específica para cuentas asociadas a Google (sin token generado).
 * - Generación administrativa presencial de enlaces de acceso único auditados.
 */
declare(strict_types=1);

/**
 * Envía un correo electrónico utilizando la función mail() nativa de PHP.
 *
 * @param string $destinatario Correo electrónico de destino
 * @param string $asunto Asunto del correo
 * @param string $cuerpo Contenido en texto plano
 * @return bool True si se despachó correctamente, false en caso contrario
 */
function email_enviar(string $destinatario, string $asunto, string $cuerpo): bool {
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@bookswap.local';
    if (function_exists('config_obtener')) {
        $from = config_obtener('centro_email', $from);
    }

    $headers = [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    // Almacenar el último correo despachado para depuración e inspección en tests
    $info = [
        'destinatario' => $destinatario,
        'asunto'       => $asunto,
        'cuerpo'       => $cuerpo,
        'headers'      => $headers,
        'fecha'        => date('Y-m-d H:i:s'),
    ];
    $GLOBALS['_ultimo_email_enviado'] = $info;

    $archivoTmp = sys_get_temp_dir() . '/bs_ultimo_email.json';
    @file_put_contents($archivoTmp, json_encode($info, JSON_UNESCAPED_UNICODE));

    // Si sendmail no está instalado (entornos de desarrollo Docker o test), no disparar comando de shell inexistente
    $sendmail = ini_get('sendmail_path');
    $bin = preg_split('/\s+/', trim((string) $sendmail))[0] ?? '';
    if ($bin !== '' && !file_exists($bin)) {
        return false;
    }

    $ok = false;
    try {
        $ok = @mail($destinatario, $asunto, $cuerpo, implode("\r\n", $headers));
    } catch (Throwable $e) {
        error_log('Error en email_enviar: ' . $e->getMessage());
        $ok = false;
    }

    return $ok;
}

/**
 * Devuelve la información del último correo enviado en el proceso actual o mediante petición web.
 *
 * @return array|null Array con destinatario, asunto, cuerpo y fecha, o null
 */
function email_ultimo_enviado(): ?array {
    $archivoTmp = sys_get_temp_dir() . '/bs_ultimo_email.json';
    if (file_exists($archivoTmp)) {
        $contenido = @file_get_contents($archivoTmp);
        if ($contenido !== false) {
            $data = json_decode($contenido, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }
    return $GLOBALS['_ultimo_email_enviado'] ?? null;
}

/**
 * Consulta la cantidad de solicitudes de restablecimiento realizadas para un email en la última hora.
 *
 * @param string $email Correo electrónico
 * @return int Número de intentos registrados en los últimos 60 minutos
 */
function password_reset_intentos(string $email): int {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM intentos_login
            WHERE email = ? AND fecha >= NOW() - INTERVAL 1 HOUR
        ');
        $stmt->execute(['reset:' . trim(mb_strtolower($email))]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Error en password_reset_intentos: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Registra una solicitud de restablecimiento de contraseña para aplicar rate-limiting.
 *
 * @param string $email Correo electrónico
 * @param string|null $ip Dirección IP del solicitante
 */
function password_reset_registrar_intento(string $email, ?string $ip = null): void {
    try {
        $pdo = db();
        $ipCliente = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare('
            INSERT INTO intentos_login (email, ip, fecha)
            VALUES (?, ?, NOW())
        ');
        $stmt->execute(['reset:' . trim(mb_strtolower($email)), $ipCliente]);
    } catch (Throwable $e) {
        error_log('Error en password_reset_registrar_intento: ' . $e->getMessage());
    }
}

/**
 * Genera la URL absoluta para el enlace de restablecimiento de contraseña.
 *
 * @param string $token Token en texto claro (64 caracteres hexadecimales)
 * @return string URL absoluta para acceder a /reset/{token}
 */
function password_reset_generar_enlace(string $token): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return "{$proto}://{$host}/reset/{$token}";
}

/**
 * Procesa la solicitud de restablecimiento de contraseña para un correo electrónico.
 *
 * Implementa estrictamente:
 * 1. Rate-limiting: máx. 3 solicitudes por email en 1 hora.
 * 2. Anti-enumeración: siempre devuelve el mismo mensaje genérico para usuarios existentes o inexistentes.
 * 3. Cuentas Google-only: NO genera token en password_resets; despacha aviso orientado a Google.
 * 4. Cuentas estándar: genera token aleatorio, guarda hash SHA-256 en BD con expiración configurable,
 *    audita el evento y despacha email con enlace.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param string $email Correo electrónico introducido
 * @param string|null $ip Dirección IP del cliente
 * @return array ['ok' => bool, 'rate_limited' => bool, 'mensaje' => string, 'token' => ?string, 'es_google' => bool]
 */
function password_reset_solicitar(PDO $pdo, string $email, ?string $ip = null): array {
    $emailNormalizado = trim(mb_strtolower($email));
    $mensajeGenerico = 'Si ese correo está registrado, recibirás las instrucciones';

    // 1. Control de rate-limiting (máx. 3 en 1 hora)
    if (password_reset_intentos($emailNormalizado) >= 3) {
        return [
            'ok'           => false,
            'rate_limited' => true,
            'mensaje'      => 'Demasiadas solicitudes para este correo. Por favor, espera una hora antes de volver a intentarlo.',
            'token'        => null,
            'es_google'    => false,
        ];
    }

    // Registrar intento para el contador de rate-limiting
    password_reset_registrar_intento($emailNormalizado, $ip);

    // 2. Comprobar si existe el usuario en la BD
    $stmt = $pdo->prepare('
        SELECT id, nombre, email, password_hash, auth_provider, activo
        FROM usuarios
        WHERE email = ?
        LIMIT 1
    ');
    $stmt->execute([$emailNormalizado]);
    $usuario = $stmt->fetch();

    // Si el usuario no existe o está desactivado, responder con mensaje genérico (anti-enumeración)
    if (!$usuario || (int) $usuario['activo'] !== 1) {
        return [
            'ok'           => true,
            'rate_limited' => false,
            'mensaje'      => $mensajeGenerico,
            'token'        => null,
            'es_google'    => false,
        ];
    }

    // 3. Caso cuenta Google-only (password_hash NULL): no se genera token
    if ($usuario['password_hash'] === null || $usuario['auth_provider'] === 'google') {
        $nombre = $usuario['nombre'] ?: 'Lector';
        $asunto = 'Inicio de sesión en LibrosBro (cuenta de Google)';
        $cuerpo = "Hola {$nombre},\n\n"
                . "Has solicitado instrucciones para acceder a tu cuenta en LibrosBro.\n\n"
                . "Tu cuenta está vinculada a Google. Para iniciar sesión, por favor utiliza el botón 'Continuar con Google' en la página de acceso de LibrosBro.\n\n"
                . "No es necesario restablecer una contraseña porque tu autenticación se realiza de forma directa y segura mediante tu cuenta de Google.\n\n"
                . "Saludos,\nEl equipo de LibrosBro";

        email_enviar($emailNormalizado, $asunto, $cuerpo);

        return [
            'ok'           => true,
            'rate_limited' => false,
            'mensaje'      => $mensajeGenerico,
            'token'        => null,
            'es_google'    => true,
        ];
    }

    // 4. Caso usuario estándar con contraseña local:
    // Invalidar cualquier token activo previo para este usuario
    $stmtInv = $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0');
    $stmtInv->execute([$usuario['id']]);

    // Generar token criptográficamente seguro de 64 caracteres hex
    $tokenClaro = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenClaro);

    // Obtener horas de caducidad desde la configuración (default '1')
    $horas = 1;
    if (function_exists('config_obtener')) {
        $cfgHoras = (int) config_obtener('reset_horas', '1');
        if ($cfgHoras > 0) {
            $horas = $cfgHoras;
        }
    }

    // Insertar el hash en password_resets con caducidad NOW() + reset_horas
    $stmtIns = $pdo->prepare('
        INSERT INTO password_resets (usuario_id, token_hash, expira, usado, creado)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 0, NOW())
    ');
    $stmtIns->execute([$usuario['id'], $tokenHash, $horas]);

    // Registrar en auditoría
    if (function_exists('auditoria_registrar')) {
        auditoria_registrar($pdo, 'password_reset_solicitado', 'usuarios', (int) $usuario['id'], [
            'email' => $emailNormalizado,
        ]);
    }

    // Componer y enviar correo electrónico
    $enlace = password_reset_generar_enlace($tokenClaro);
    $nombre = $usuario['nombre'] ?: 'Lector';
    $asunto = 'Recuperación de contraseña en LibrosBro';
    $cuerpo = "Hola {$nombre},\n\n"
            . "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en LibrosBro.\n\n"
            . "Puedes definir tu nueva contraseña accediendo al siguiente enlace:\n"
            . "{$enlace}\n\n"
            . "Por motivos de seguridad, este enlace es de un solo uso y caducará en {$horas} hora(s).\n\n"
            . "Si no has solicitado este cambio, puedes ignorar este mensaje de forma segura.\n\n"
            . "Saludos,\nEl equipo de LibrosBro";

    email_enviar($emailNormalizado, $asunto, $cuerpo);

    return [
        'ok'           => true,
        'rate_limited' => false,
        'mensaje'      => $mensajeGenerico,
        'token'        => $tokenClaro,
        'es_google'    => false,
    ];
}

/**
 * Valida si un token en texto claro existe, no ha sido usado y no ha caducado.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param string $token Token en texto claro
 * @return array|null Datos de la fila y del usuario si es válido, o null
 */
function password_reset_validar_token(PDO $pdo, string $token): ?array {
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare('
        SELECT pr.*, u.email, u.nombre, u.activo
        FROM password_resets pr
        JOIN usuarios u ON u.id = pr.usuario_id
        WHERE pr.token_hash = ? AND pr.usado = 0 AND pr.expira > NOW()
        LIMIT 1
    ');
    $stmt->execute([$tokenHash]);
    $fila = $stmt->fetch();

    if (!$fila || (int) $fila['activo'] !== 1) {
        return null;
    }

    return $fila;
}

/**
 * Completa el proceso de restablecimiento de contraseña para un token válido.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param string $token Token en texto claro
 * @param string $nuevaPassword Nueva contraseña en texto plano
 * @param int|null $autorId ID del usuario que realiza la acción para auditoría
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function password_reset_completar(PDO $pdo, string $token, string $nuevaPassword, ?int $autorId = null): bool {
    // Validar política de contraseña (mínimo 8 caracteres, v4.1)
    if (mb_strlen($nuevaPassword) < 8) {
        return false;
    }

    $fila = password_reset_validar_token($pdo, $token);
    if (!$fila) {
        return false;
    }

    $usuarioId = (int) $fila['usuario_id'];
    $nuevoHash = password_hash($nuevaPassword, PASSWORD_BCRYPT);

    // Actualizar hash de contraseña del usuario
    $stmtU = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
    $stmtU->execute([$nuevoHash, $usuarioId]);

    // Marcar todos los tokens de este usuario como usados
    $stmtPr = $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE usuario_id = ?');
    $stmtPr->execute([$usuarioId]);

    // Registrar en auditoría
    if (function_exists('auditoria_registrar')) {
        auditoria_registrar($pdo, 'password_reset_completado', 'usuarios', $usuarioId, [
            'email' => $fila['email'],
        ], $autorId ?? $usuarioId);
    }

    return true;
}

/**
 * Genera un enlace presencial de acceso único desde el panel de administración.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $targetUserId ID del usuario objetivo
 * @param int|null $adminId ID del administrador actuante
 * @return string Token en texto claro generado (64 caracteres hex)
 * @throws InvalidArgumentException Si el usuario no existe o es una cuenta de Google
 */
function admin_usuario_generar_enlace_reset(PDO $pdo, int $targetUserId, ?int $adminId = null): string {
    $stmt = $pdo->prepare('SELECT id, email, nombre, password_hash, activo FROM usuarios WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $u = $stmt->fetch();

    if (!$u) {
        throw new InvalidArgumentException('El usuario indicado no existe.');
    }

    if ($u['password_hash'] === null) {
        throw new InvalidArgumentException('No se puede generar un enlace de restablecimiento para una cuenta vinculada a Google.');
    }

    // Invalidar tokens previos
    $stmtInv = $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE usuario_id = ? AND usado = 0');
    $stmtInv->execute([$targetUserId]);

    // Generar token
    $tokenClaro = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenClaro);

    $horas = 1;
    if (function_exists('config_obtener')) {
        $cfgHoras = (int) config_obtener('reset_horas', '1');
        if ($cfgHoras > 0) {
            $horas = $cfgHoras;
        }
    }

    $stmtIns = $pdo->prepare('
        INSERT INTO password_resets (usuario_id, token_hash, expira, usado, creado)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), 0, NOW())
    ');
    $stmtIns->execute([$targetUserId, $tokenHash, $horas]);

    if (function_exists('auditoria_registrar')) {
        auditoria_registrar($pdo, 'password_reset_enlace', 'usuarios', $targetUserId, [
            'email' => $u['email'],
            'motivo' => 'Generación presencial de enlace de acceso por administrador',
        ], $adminId);
    }

    return $tokenClaro;
}
