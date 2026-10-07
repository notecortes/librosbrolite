<?php
/**
 * BookSwap · auth_google.php — Integración de autenticación Google OAuth 2.0 (PHP puro con cURL).
 */
declare(strict_types=1);

/**
 * Procesa la información obtenida de Google (sub, email, nombre, foto) y gestiona la vinculación o alta.
 *
 * Casos:
 * 1. google_sub ya existe en usuarios: retorna la cuenta existente.
 * 2. email coincide con una cuenta local existente: vincula google_sub y pasa auth_provider a 'ambos'.
 * 3. Cuenta totalmente nueva: inserta usuario google-only (password_hash = NULL, auth_provider = 'google').
 *
 * @param string $sub Identificador único de Google (sub)
 * @param string $email Correo electrónico verificado
 * @param string $nombre Nombre del perfil
 * @param string|null $foto URL de la foto de perfil
 * @return array Datos completos del usuario autenticado
 */
function auth_google_procesar(string $sub, string $email, string $nombre, ?string $foto = null): array {
    $pdo = db();
    $subLimpio = trim($sub);
    $emailNormalizado = trim(mb_strtolower($email));
    $nombreLimpio = trim($nombre);

    // 1. Buscar por google_sub
    $stmtSub = $pdo->prepare('
        SELECT u.*, r.nombre AS rol_nombre
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE u.google_sub = ?
        LIMIT 1
    ');
    $stmtSub->execute([$subLimpio]);
    $usuarioPorSub = $stmtSub->fetch();

    if ($usuarioPorSub) {
        $stmtUpd = $pdo->prepare('UPDATE usuarios SET ultima_actividad = NOW(), foto_url = COALESCE(foto_url, ?) WHERE id = ?');
        $stmtUpd->execute([$foto, (int) $usuarioPorSub['id']]);
        return $usuarioPorSub;
    }

    // 2. Buscar por email para vincular cuenta existente (T-GOOG-04)
    $stmtEmail = $pdo->prepare('
        SELECT u.*, r.nombre AS rol_nombre
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE u.email = ?
        LIMIT 1
    ');
    $stmtEmail->execute([$emailNormalizado]);
    $usuarioPorEmail = $stmtEmail->fetch();

    if ($usuarioPorEmail) {
        $stmtVincular = $pdo->prepare('
            UPDATE usuarios
            SET google_sub = ?,
                auth_provider = "ambos",
                foto_url = COALESCE(foto_url, ?),
                email_verificado = 1,
                ultima_actividad = NOW()
            WHERE id = ?
        ');
        $stmtVincular->execute([$subLimpio, $foto, (int) $usuarioPorEmail['id']]);

        log_accion((int) $usuarioPorEmail['id'], 'vincular_google', 'usuarios', (int) $usuarioPorEmail['id'], [
            'email' => $emailNormalizado,
        ]);

        $usuarioPorEmail['google_sub'] = $subLimpio;
        $usuarioPorEmail['auth_provider'] = 'ambos';
        return $usuarioPorEmail;
    }

    // 3. Crear nueva cuenta google-only (T-GOOG-05)
    $stmtInsert = $pdo->prepare('
        INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, google_sub, auth_provider, email_verificado, foto_url, fecha_registro, ultima_actividad)
        VALUES (?, ?, NULL, 3, 1, ?, "google", 1, ?, NOW(), NOW())
    ');
    $stmtInsert->execute([$nombreLimpio, $emailNormalizado, $subLimpio, $foto]);
    $nuevoId = (int) $pdo->lastInsertId();

    log_accion($nuevoId, 'registro_google', 'usuarios', $nuevoId, [
        'email' => $emailNormalizado,
    ]);

    $stmtNuevo = $pdo->prepare('
        SELECT u.*, r.nombre AS rol_nombre
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmtNuevo->execute([$nuevoId]);
    return $stmtNuevo->fetch();
}

/**
 * Genera la URL de autorización para redirigir a Google OAuth.
 *
 * @param array $config Configuración global de la aplicación
 * @return string|null URL de redirección a Google o null si no está configurado
 */
function google_generar_url_auth(array $config): ?string {
    $clientId = trim((string) ($config['google_client_id'] ?? ''));
    $redirectUri = trim((string) ($config['google_redirect_uri'] ?? ''));

    if ($clientId === '') {
        return null;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}
