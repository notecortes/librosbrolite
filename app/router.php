<?php
/**
 * BookSwap · router.php — Enrutador central de peticiones HTTP.
 *
 * Responsabilidades:
 * 1. Inicializar el entorno a través de bootstrap.php.
 * 2. Validar tokens CSRF en todas las peticiones POST.
 * 3. Despachar a vistas y controladores según método y ruta.
 * 4. Gestionar autenticación local y Google OAuth 2.0.
 * 5. Proteger rutas administrativas y de mostrador mediante RBAC.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// 1. Normalización de la ruta solicitada
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uriPath = rtrim($uriPath, '/');
if ($uriPath === '' || $uriPath === '/index.php') {
    $uriPath = '/';
}
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Servicio de respaldo para recursos estáticos (/assets/ y /uploads/)
if (str_starts_with($uriPath, '/assets/') || str_starts_with($uriPath, '/uploads/')) {
    $rutaReal = realpath(dirname(__DIR__) . '/public' . $uriPath) ?: realpath(dirname(__DIR__) . $uriPath);
    $dirAssetsPublic = realpath(dirname(__DIR__) . '/public/assets');
    $dirAssets = realpath(dirname(__DIR__) . '/assets');
    $dirUploads = realpath(dirname(__DIR__) . '/uploads');

    if ($rutaReal && is_file($rutaReal) && (
        ($dirAssetsPublic && str_starts_with($rutaReal, $dirAssetsPublic)) ||
        ($dirAssets && str_starts_with($rutaReal, $dirAssets)) ||
        ($dirUploads && str_starts_with($rutaReal, $dirUploads))
    )) {
        $ext = strtolower(pathinfo($rutaReal, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'text/javascript; charset=utf-8',
            'svg'   => 'image/svg+xml; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];
        $contentType = $mimes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($rutaReal) : 'application/octet-stream');
        header("Content-Type: {$contentType}");
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: ' . filesize($rutaReal));
        readfile($rutaReal);
        exit;
    }
}

// 2. Validación obligatoria de CSRF en todas las peticiones POST (T-SEC-03)
if ($metodo === 'POST') {
    $tokenRecibido = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verificar($tokenRecibido)) {
        http_response_code(403);
        echo 'Error de seguridad: token CSRF inválido o ausente.';
        exit;
    }
}



// 4. Despacho de rutas
switch ($uriPath) {
    // -------------------------------------------------------------
    // PORTADA (Home)
    // -------------------------------------------------------------
    case '/':
        if ($usuario !== null) {
            redireccionar('/dashboard');
        }

        if ($metodo !== 'GET' && $metodo !== 'HEAD') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $stats = ['disponibles' => null, 'intercambios' => null, 'lectores' => null];
        $destacados = null;

        try {
            $pdo = db();
            $stats['disponibles']  = (int) $pdo->query("SELECT COUNT(*) FROM ejemplares WHERE estado = 'disponible'")->fetchColumn();
            $stats['intercambios'] = (int) $pdo->query("SELECT COUNT(*) FROM transacciones WHERE estado = 'entregada'")->fetchColumn();
            $stats['lectores']     = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();

            $destacados = $pdo->query("
                SELECT id, isbn13, titulo, autor, editorial, anio, genero, portada_url
                FROM libros
                ORDER BY id DESC
                LIMIT 4
            ")->fetchAll();
        } catch (Throwable $e) {
            error_log('Error cargando home: ' . $e->getMessage());
        }

        if (empty($destacados)) {
            $destacados = [
                ['id' => 4, 'titulo' => 'Harry Potter y la piedra filosofal', 'autor' => 'J. K. Rowling', 'portada_url' => 'https://covers.openlibrary.org/b/isbn/9788478884459-L.jpg?default=false'],
                ['id' => 2, 'titulo' => '1984', 'autor' => 'George Orwell', 'portada_url' => 'https://covers.openlibrary.org/b/isbn/9780451524935-L.jpg?default=false'],
                ['id' => 6, 'titulo' => 'El Hobbit', 'autor' => 'J. R. R. Tolkien', 'portada_url' => 'https://covers.openlibrary.org/b/isbn/9788445000639-L.jpg?default=false'],
                ['id' => 14, 'titulo' => 'El principito', 'autor' => 'Antoine de Saint-Exupéry', 'portada_url' => 'https://covers.openlibrary.org/b/isbn/9780156012195-L.jpg?default=false'],
            ];
        }

        render_vista('home', [
            'stats' => $stats,
            'destacados' => $destacados,
            'config' => $config,
        ], 'Inicio');
        break;

    // -------------------------------------------------------------
    // AUTENTICACIÓN LOCAL
    // -------------------------------------------------------------
    case '/login':
        if ($usuario !== null) {
            if (($usuario['rol_nombre'] ?? '') === 'ADMIN') {
                redireccionar('/admin');
            } elseif (($usuario['rol_nombre'] ?? '') === 'PERSONAL') {
                redireccionar('/mostrador');
            } else {
                redireccionar('/dashboard');
            }
        }

        if ($metodo === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $res = auth_verificar_credenciales($email, $password);
            if ($res['ok'] && $res['usuario']) {
                auth_iniciar_sesion($res['usuario']);
                flash('Has iniciado sesión correctamente.', 'exito');

                if (($res['usuario']['rol_nombre'] ?? '') === 'ADMIN') {
                    redireccionar('/admin');
                } elseif (($res['usuario']['rol_nombre'] ?? '') === 'PERSONAL') {
                    redireccionar('/mostrador');
                } else {
                    redireccionar('/dashboard');
                }
            } else {
                render_vista('auth/login', [
                    'error' => $res['error'] ?? 'Credenciales incorrectas.',
                    'email' => $email,
                    'config' => $config,
                ], 'Iniciar sesión');
                exit;
            }
        }

        render_vista('auth/login', [
            'config' => $config,
            'email' => '',
            'error' => null,
        ], 'Iniciar sesión');
        break;

    case '/registro':
        if ($usuario !== null) {
            redireccionar('/dashboard');
        }

        if ($metodo === 'POST') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            $res = auth_registrar_usuario($nombre, $email, $password);
            if ($res['ok'] && !empty($res['usuario_id'])) {
                $pdo = db();
                $stmt = $pdo->prepare('SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?');
                $stmt->execute([(int) $res['usuario_id']]);
                $nuevoUser = $stmt->fetch();
                if ($nuevoUser) {
                    auth_iniciar_sesion($nuevoUser);
                }
                flash('Cuenta creada correctamente. ¡Bienvenido a LibrosBro!', 'exito');
                redireccionar('/dashboard');
            } else {
                render_vista('auth/registro', [
                    'error' => $res['error'] ?? 'Error al registrar el usuario.',
                    'nombre' => $nombre,
                    'email' => $email,
                ], 'Crear cuenta');
                exit;
            }
        }

        render_vista('auth/registro', [], 'Crear cuenta');
        break;

    case '/olvidar':
        if ($usuario !== null) {
            redireccionar('/dashboard');
        }

        if ($metodo === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                render_vista('auth/olvidar', [
                    'error' => 'Por favor, introduce una dirección de correo electrónico válida.',
                    'email' => $email,
                ], 'Recuperar contraseña');
                exit;
            }

            $pdo = db();
            $res = password_reset_solicitar($pdo, $email, $_SERVER['REMOTE_ADDR'] ?? null);

            if ($res['rate_limited']) {
                http_response_code(429);
                render_vista('auth/olvidar', [
                    'error' => $res['mensaje'],
                    'email' => $email,
                ], 'Recuperar contraseña');
                exit;
            }

            $devToken = $res['token'];
            $devResetUrl = $devToken ? password_reset_generar_enlace($devToken) : null;
            $devGoogle = $res['es_google'];

            render_vista('auth/olvidar', [
                'mensaje'     => $res['mensaje'],
                'email'       => $email,
                'devToken'    => $devToken,
                'devResetUrl' => $devResetUrl,
                'devGoogle'   => $devGoogle,
            ], 'Recuperar contraseña');
            exit;
        }

        render_vista('auth/olvidar', [
            'email'   => '',
            'error'   => null,
            'mensaje' => null,
        ], 'Recuperar contraseña');
        break;

    case '/logout':
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        if ($usuario !== null) {
            log_accion((int) $usuario['id'], 'logout', 'usuarios', (int) $usuario['id']);
        }
        auth_cerrar_sesion();
        flash('Has cerrado sesión.', 'info');
        redireccionar('/login');
        break;

    // -------------------------------------------------------------
    // LOGIN CON GOOGLE OAUTH 2.0 (PHP puro con cURL)
    // -------------------------------------------------------------
    case '/auth/google':
        if ($metodo !== 'GET') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        // Degradación elegante si google_client_id está vacío (§6.1, T-GOOG-01)
        $clientId = trim((string) ($config['google_client_id'] ?? ''));
        if ($clientId === '') {
            flash('El inicio de sesión con Google no está habilitado.', 'aviso');
            redireccionar('/login');
        }

        // Redirigir a Google con state generado (T-GOOG-02)
        $urlGoogle = google_generar_url_auth($config);
        header('Location: ' . $urlGoogle);
        exit;

    case '/auth/google/callback':
        if ($metodo !== 'GET') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        if (!empty($_GET['error'])) {
            flash('Error en la autenticación con Google: ' . e((string) $_GET['error']), 'error');
            redireccionar('/login');
        }

        // Validar state contra sesión (T-GOOG-03)
        $stateEsperado = (string) ($_SESSION['google_oauth_state'] ?? '');
        $stateRecibido = (string) ($_GET['state'] ?? '');
        unset($_SESSION['google_oauth_state']);

        if ($stateEsperado === '' || !hash_equals($stateEsperado, $stateRecibido)) {
            log_accion(null, 'error_oauth_state', 'auth', null, [
                'state_recibido' => $stateRecibido,
            ]);
            http_response_code(403);
            echo 'Estado OAuth inválido o expirado. Operación cancelada por seguridad.';
            exit;
        }

        $codigo = (string) ($_GET['code'] ?? '');
        if ($codigo === '') {
            flash('No se recibió el código de autorización de Google.', 'error');
            redireccionar('/login');
        }

        // Intercambiar código por token vía cURL POST
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $codigo,
                'client_id' => $config['google_client_id'] ?? '',
                'client_secret' => $config['google_client_secret'] ?? '',
                'redirect_uri' => $config['google_redirect_uri'] ?? '',
                'grant_type' => 'authorization_code',
            ]),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $resTokenRaw = curl_exec($ch);
        $errToken = curl_error($ch);
        curl_close($ch);

        if ($resTokenRaw === false || $errToken !== '') {
            flash('No se pudo conectar con los servidores de Google.', 'error');
            redireccionar('/login');
        }

        $resToken = json_decode((string) $resTokenRaw, true);
        $accessToken = $resToken['access_token'] ?? null;
        if (empty($accessToken)) {
            flash('Error obteniendo credenciales de acceso de Google.', 'error');
            redireccionar('/login');
        }

        // Consultar datos del perfil vía cURL GET a userinfo
        $chUser = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
        curl_setopt_array($chUser, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);
        $resUserRaw = curl_exec($chUser);
        curl_close($chUser);

        $userInfo = json_decode((string) $resUserRaw, true);
        if (empty($userInfo['sub']) || empty($userInfo['email'])) {
            flash('No se pudo recuperar la información de perfil de Google.', 'error');
            redireccionar('/login');
        }

        if (empty($userInfo['email_verified'])) {
            flash('El correo electrónico de Google no está verificado.', 'error');
            redireccionar('/login');
        }

        // Procesar autenticación / vinculación
        $userGoogle = auth_google_procesar(
            (string) $userInfo['sub'],
            (string) $userInfo['email'],
            (string) ($userInfo['name'] ?? ''),
            (string) ($userInfo['picture'] ?? null)
        );

        auth_iniciar_sesion($userGoogle);
        flash('Has iniciado sesión con Google correctamente.', 'exito');
        redireccionar('/dashboard');
        break;

    // -------------------------------------------------------------
    // PÁGINAS PRIVADAS DE USUARIO
    // -------------------------------------------------------------
    case '/mi-cuenta':
    case '/perfil':
    case '/dashboard':
        exigir_autenticado();
        $pdo = db();
        $usuarioId = (int) $usuario['id'];

        // 1. Saldo actual
        $saldoActual = ledger_obtener_saldo($pdo, $usuarioId);

        // 2. Reservas activas
        $todasRes = reserva_listar_usuario($pdo, $usuarioId);
        $reservasActivas = array_values(array_filter($todasRes, fn($r) => $r['estado'] === 'activa'));

        // 3. Últimos ingresos en catálogo (5 con copias disponibles)
        $stmtLibros = $pdo->query("
            SELECT l.*, COUNT(e.id) as disponibles_count
            FROM libros l
            JOIN ejemplares e ON e.libro_id = l.id AND e.estado = 'disponible'
            GROUP BY l.id
            ORDER BY l.id DESC
            LIMIT 5
        ");
        $ultimosLibros = $stmtLibros->fetchAll(PDO::FETCH_ASSOC);

        // 4. Últimos movimientos (5)
        $stmtMov = $pdo->prepare("
            SELECT * FROM movimientos_tokens
            WHERE usuario_id = ?
            ORDER BY fecha DESC, id DESC
            LIMIT 5
        ");
        $stmtMov->execute([$usuarioId]);
        $ultimosMovimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

        render_vista('usuario/dashboard', [
            'config' => $config,
            'saldo' => $saldoActual,
            'reservasActivas' => $reservasActivas,
            'ultimosLibros' => $ultimosLibros,
            'ultimosMovimientos' => $ultimosMovimientos,
        ], 'Mi LibrosBro');
        break;

    case '/cambiar-password':
    case '/perfil/cambiar-password':
    case '/mi-cuenta/seguridad':
        exigir_autenticado();
        $pdo = db();
        $usuarioId = (int) $usuario['id'];
        $error = null;
        $exito = null;

        // Comprobar si el usuario tiene contraseña actualmente
        $stmtU = $pdo->prepare('SELECT password_hash, auth_provider, email FROM usuarios WHERE id = ? LIMIT 1');
        $stmtU->execute([$usuarioId]);
        $uInfo = $stmtU->fetch(PDO::FETCH_ASSOC);
        $tienePassword = !empty($uInfo['password_hash']);

        if ($metodo === 'POST') {
            $passwordActual = (string) ($_POST['password_actual'] ?? '');
            $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

            $res = auth_cambiar_password_usuario($pdo, $usuarioId, $passwordActual, $passwordNueva, $passwordConfirm);
            if ($res['ok']) {
                flash('Tu contraseña se ha actualizado correctamente.', 'exito');
                redireccionar('/cambiar-password');
            } else {
                $error = $res['error'];
            }
        }

        render_vista('usuario/cambiar_password', [
            'config' => $config,
            'usuario' => $usuario,
            'tienePassword' => $tienePassword,
            'error' => $error,
            'exito' => $exito,
        ], 'Cambiar Contraseña');
        break;

    case '/mis-depositos':
        redireccionar('/mi-historial?tipo=deposito', 302);
        break;

    case '/como-funciona':
        render_vista('estatico/como-funciona', [
            'config' => $config,
        ], 'Cómo funciona');
        break;

    case '/ayuda':
        render_vista('estatico/ayuda', [
            'config' => $config,
        ], 'Centro de Ayuda');
        break;

    case '/mi-historial':
    case '/mi-historial/csv':
        exigir_autenticado();
        $pdo = db();
        $usuarioId = (int) $usuario['id'];

        $filtros = [
            'tipo'  => trim((string) ($_GET['tipo'] ?? '')),
            'desde' => trim((string) ($_GET['desde'] ?? '')),
            'hasta' => trim((string) ($_GET['hasta'] ?? '')),
        ];

        if ($uriPath === '/mi-historial/csv' || ($_GET['exportar'] ?? '') === 'csv') {
            $csv = historial_exportar_csv($pdo, $usuarioId, $filtros);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="mi_historial_' . date('Ymd_His') . '.csv"');
            echo $csv;
            exit;
        }

        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = 20;

        $resumen = historial_obtener_resumen($pdo, $usuarioId);
        $datosMov = historial_obtener_movimientos($pdo, $usuarioId, $filtros, $pagina, $porPagina);

        render_vista('usuario/historial', [
            'resumen'         => $resumen,
            'movimientos'     => $datosMov['movimientos'],
            'total'           => $datosMov['total'],
            'pagina'          => $datosMov['pagina'],
            'totalPaginas'    => $datosMov['totalPaginas'],
            'filtros'         => $filtros,
            'esVistaAdmin'    => false,
            'usuarioObjetivo' => $usuario,
        ], 'Mi Historial de Libros y Tokens');
        break;

    case '/dashboard/contrasena':
        exigir_autenticado();
        if ($metodo !== 'POST') {
            redireccionar('/dashboard');
        }

        $pdo = db();
        $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
        $passwordConfirm = (string) ($_POST['password_nueva_confirm'] ?? '');

        if (mb_strlen($passwordNueva) < 8) {
            render_vista('usuario/dashboard', [
                'error' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            ], 'Mi panel');
            exit;
        }

        if ($passwordNueva !== $passwordConfirm) {
            render_vista('usuario/dashboard', [
                'error' => 'Las nuevas contraseñas no coinciden.',
            ], 'Mi panel');
            exit;
        }

        // Si ya tenía contraseña, verificar la actual
        if (!empty($usuario['password_hash'])) {
            $passwordActual = (string) ($_POST['password_actual'] ?? '');
            if (!password_verify($passwordActual, $usuario['password_hash'])) {
                render_vista('usuario/dashboard', [
                    'error' => 'La contraseña actual no es correcta.',
                ], 'Mi panel');
                exit;
            }
        }

        $nuevoHash = password_hash($passwordNueva, PASSWORD_BCRYPT, ['cost' => 10]);
        $nuevoProvider = ($usuario['auth_provider'] === 'google') ? 'ambos' : $usuario['auth_provider'];

        $stmtPass = $pdo->prepare('
            UPDATE usuarios
            SET password_hash = ?,
                auth_provider = ?
            WHERE id = ?
        ');
        $stmtPass->execute([$nuevoHash, $nuevoProvider, $usuario['id']]);

        log_accion('cambio_password', 'usuarios', (int) $usuario['id']);
        flash('Contraseña actualizada correctamente.', 'exito');
        redireccionar('/dashboard');
        break;

    case '/dashboard/desvincular-google':
        exigir_autenticado();
        if ($metodo !== 'POST') {
            redireccionar('/dashboard');
        }

        // Solo permitir desvincular si tiene contraseña local
        if (empty($usuario['password_hash'])) {
            flash('No puedes desvincular Google sin haber establecido antes una contraseña.', 'error');
            redireccionar('/dashboard');
        }

        $pdo = db();
        $stmtDesvincular = $pdo->prepare('
            UPDATE usuarios
            SET google_sub = NULL,
                auth_provider = "local"
            WHERE id = ?
        ');
        $stmtDesvincular->execute([$usuario['id']]);

        log_accion('desvincular_google', 'usuarios', (int) $usuario['id']);
        flash('Cuenta de Google desvinculada con éxito.', 'exito');
        redireccionar('/dashboard');
        break;

    case '/depositar':
        exigir_autenticado();
        $pdo = db();

        if ($metodo === 'POST') {
            $libroId = (int) ($_POST['libro_id'] ?? 0);
            $condicion = (string) ($_POST['condicion'] ?? 'bueno');

            try {
                $deposito = deposito_preregistrar($pdo, (int) $usuario['id'], $libroId, $condicion);
                $libro = catalogo_obtener_libro($pdo, $libroId);

                render_vista('usuario/deposito_exito', [
                    'deposito' => $deposito,
                    'libro' => $libro,
                ], 'Depósito Pre-registrado');
                exit;
            } catch (Exception $e) {
                $libros = $pdo->query('SELECT id, titulo, autor, nivel_valor FROM libros ORDER BY titulo ASC')->fetchAll();
                render_vista('usuario/depositar', [
                    'error' => $e->getMessage(),
                    'libros' => $libros,
                    'libroPreseleccionado' => $libroId,
                ], 'Depositar Libro');
                exit;
            }
        }

        $libroPre = !empty($_GET['libro_id']) ? (int) $_GET['libro_id'] : null;
        $libros = $pdo->query('SELECT id, titulo, autor, nivel_valor FROM libros ORDER BY titulo ASC')->fetchAll();
        render_vista('usuario/depositar', [
            'libros' => $libros,
            'libroPreseleccionado' => $libroPre,
        ], 'Depositar Libro');
        break;

    // -------------------------------------------------------------
    // RESERVAS DE LIBROS Y MIS RESERVAS (Fase 4)
    // -------------------------------------------------------------
    case '/reservar':
        exigir_autenticado();
        $pdo = db();

        if ($metodo === 'POST') {
            exigir_csrf();
            $libroId = (int) ($_POST['libro_id'] ?? 0);
            $ejemplarId = (int) ($_POST['ejemplar_id'] ?? 0);
            $lectorDestino = trim((string) ($_POST['lector'] ?? $_POST['email'] ?? $_POST['usuario_id'] ?? ''));
            $retorno = trim((string) ($_POST['retorno'] ?? ''));

            if ($libroId <= 0 && $ejemplarId > 0) {
                $stmtLib = $pdo->prepare('SELECT libro_id FROM ejemplares WHERE id = ?');
                $stmtLib->execute([$ejemplarId]);
                $libroId = (int) ($stmtLib->fetchColumn() ?: 0);
            }

            $targetUsuarioId = (int) $usuario['id'];
            $nombreDestino = $usuario['nombre'];
            $esParaTercero = false;

            // Si es ADMIN o PERSONAL y proporciona un lector para reservar
            if ($lectorDestino !== '' && in_array($usuario['rol_nombre'] ?? '', ['ADMIN', 'PERSONAL'], true)) {
                if (is_numeric($lectorDestino)) {
                    $stmtUsr = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE id = ?');
                    $stmtUsr->execute([(int) $lectorDestino]);
                } else {
                    $stmtUsr = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE LOWER(email) = LOWER(?)');
                    $stmtUsr->execute([$lectorDestino]);
                }
                $usuarioDestino = $stmtUsr->fetch();
                if (!$usuarioDestino) {
                    flash('No se encontró ningún lector con «' . htmlspecialchars($lectorDestino) . '».', 'error');
                    if ($retorno !== '' && str_starts_with($retorno, '/')) {
                        redireccionar($retorno);
                    }
                    redireccionar($libroId > 0 ? '/libro/' . $libroId : '/catalogo');
                }
                if (empty($usuarioDestino['activo'])) {
                    flash('El lector «' . htmlspecialchars($lectorDestino) . '» se encuentra inactivo.', 'error');
                    if ($retorno !== '' && str_starts_with($retorno, '/')) {
                        redireccionar($retorno);
                    }
                    redireccionar($libroId > 0 ? '/libro/' . $libroId : '/catalogo');
                }
                $targetUsuarioId = (int) $usuarioDestino['id'];
                $nombreDestino = $usuarioDestino['nombre'] . ' (' . $usuarioDestino['email'] . ')';
                $esParaTercero = true;
            }

            try {
                $operadorId = $esParaTercero ? (int) $usuario['id'] : null;
                $res = reserva_crear($pdo, $targetUsuarioId, $libroId, $ejemplarId > 0 ? $ejemplarId : null, $operadorId);
                $horas = $res['horas_reserva'] ?? 72;

                if ($esParaTercero) {
                    flash("¡Reserva tramitada con éxito para {$nombreDestino}! Código de recogida: «{$res['codigo']}». Plazo: {$horas}h.", 'exito');
                    if ($retorno !== '' && str_starts_with($retorno, '/')) {
                        redireccionar($retorno);
                    }
                    redireccionar($libroId > 0 ? '/libro/' . $libroId : '/admin/reservas');
                } else {
                    flash("¡Reserva realizada con éxito! Tu código es «{$res['codigo']}». Tienes {$horas} horas para retirarlo en mostrador.", 'exito');
                    redireccionar('/mis-reservas');
                }
            } catch (Exception $e) {
                flash($e->getMessage(), 'error');
                if ($retorno !== '' && str_starts_with($retorno, '/')) {
                    redireccionar($retorno);
                }
                if ($libroId > 0) {
                    redireccionar('/libro/' . $libroId);
                } else {
                    redireccionar('/catalogo');
                }
            }
        }

        // GET: pantalla de confirmación previa
        $libroId = (int) ($_GET['libro_id'] ?? 0);
        if ($libroId <= 0) {
            redireccionar('/catalogo');
        }

        $libro = catalogo_obtener_libro($pdo, $libroId);
        if (!$libro) {
            http_response_code(404);
            render_vista('404', [], 'Libro no encontrado');
            exit;
        }

        render_vista('reservas/confirmar', [
            'libro' => $libro,
            'config' => $config,
        ], 'Confirmar Reserva · ' . $libro['titulo']);
        break;

    case '/mis-reservas':
        exigir_autenticado();
        $pdo = db();
        // Pseudo-cron: procesar expiraciones al consultar reservas
        reserva_expirar_vencidas($pdo);

        $todas = reserva_listar_usuario($pdo, (int) $usuario['id']);
        $activas = array_values(array_filter($todas, fn($r) => $r['estado'] === 'activa'));
        $historial = array_values(array_filter($todas, fn($r) => $r['estado'] !== 'activa'));

        render_vista('usuario/reservas', [
            'activas' => $activas,
            'historial' => $historial,
            'config' => $config,
        ], 'Mis Reservas');
        break;

    case '/mis-reservas/cancelar':
        exigir_autenticado();
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }
        exigir_csrf();

        $txId = (int) ($_POST['transaccion_id'] ?? 0);
        try {
            reserva_cancelar(db(), $txId, (int) $usuario['id']);
            flash('Reserva cancelada correctamente. El ejemplar vuelve a estar disponible.', 'info');
        } catch (Exception $e) {
            flash($e->getMessage(), 'error');
        }

        redireccionar('/mis-reservas');
        break;

    // -------------------------------------------------------------
    // LISTA DE DESEOS (WISHLIST) (Fase 6)
    // -------------------------------------------------------------
    case '/wishlist':
        exigir_autenticado();
        $pdo = db();
        $items = wishlist_listar($pdo, (int) $usuario['id']);
        render_vista('usuario/wishlist', [
            'items' => $items,
            'config' => $config,
        ], 'Mi Lista de Deseos');
        break;

    case '/wishlist/agregar':
        exigir_autenticado();
        $libroId = (int) ($_POST['libro_id'] ?? $_GET['libro_id'] ?? 0);
        if ($libroId > 0) {
            wishlist_agregar(db(), (int) $usuario['id'], $libroId);
            flash('Libro añadido a tu lista de deseos. Te avisaremos cuando llegue un ejemplar.', 'exito');
        }
        $redirect = !empty($_POST['redirect']) ? (string) $_POST['redirect'] : (!empty($_GET['redirect']) ? (string) $_GET['redirect'] : '/wishlist');
        redireccionar($redirect);
        break;

    case '/wishlist/eliminar':
        exigir_autenticado();
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }
        $libroId = (int) ($_POST['libro_id'] ?? 0);
        if ($libroId > 0) {
            wishlist_eliminar(db(), (int) $usuario['id'], $libroId);
            flash('Libro eliminado de tu lista de deseos.', 'info');
        }
        $redirect = !empty($_POST['redirect']) ? (string) $_POST['redirect'] : '/wishlist';
        redireccionar($redirect);
        break;

    // -------------------------------------------------------------
    // NOTIFICACIONES IN-APP (Fase 6)
    // -------------------------------------------------------------
    case '/notificaciones':
        exigir_autenticado();
        $pdo = db();
        $notificaciones = notificaciones_listar($pdo, (int) $usuario['id'], 50);
        render_vista('usuario/notificaciones', [
            'notificaciones' => $notificaciones,
        ], 'Notificaciones');
        break;

    case '/notificaciones/abrir':
        exigir_autenticado();
        $notifId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $destino = '/notificaciones';
        if ($notifId > 0) {
            $pdo = db();
            $notif = notificacion_obtener($pdo, $notifId, (int) $usuario['id']);
            if ($notif) {
                notificacion_marcar_leida($pdo, $notifId, (int) $usuario['id']);
                if (!empty($notif['url'])) {
                    $destino = (string) $notif['url'];
                }
            }
        }
        redireccionar($destino);
        break;

    case '/notificaciones/marcar-leida':
        exigir_autenticado();
        $notifId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($notifId > 0) {
            notificacion_marcar_leida(db(), $notifId, (int) $usuario['id']);
            flash('Aviso marcado como leído.', 'info');
        }
        redireccionar('/notificaciones');
        break;

    case '/notificaciones/marcar-leidas':
    case '/notificaciones/leer-todas':
        exigir_autenticado();
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }
        $actualizadas = notificaciones_marcar_leidas(db(), (int) $usuario['id']);
        flash('Notificaciones marcadas como leídas.', 'info');
        redireccionar('/notificaciones');
        break;

    // -------------------------------------------------------------
    // TOP BUSCADOS Y DEMANDADOS (Fase 6)
    // -------------------------------------------------------------
    case '/top-buscados':
    case '/catalogo/top-buscados':
        $pdo = db();
        $stmtTop = $pdo->query("
            SELECT l.*,
                   COUNT(DISTINCT b.id) AS total_busquedas,
                   COUNT(DISTINCT w.id) AS total_deseos,
                   COALESCE(c.copias_disponibles, 0) AS copias_disponibles
            FROM libros l
            LEFT JOIN busquedas_log b ON (b.termino LIKE CONCAT('%', l.titulo, '%') OR b.isbn = l.isbn13)
            LEFT JOIN listas_deseos w ON w.libro_id = l.id
            LEFT JOIN (
                SELECT libro_id, COUNT(*) AS copias_disponibles
                FROM ejemplares
                WHERE estado = 'disponible'
                GROUP BY libro_id
            ) c ON c.libro_id = l.id
            GROUP BY l.id
            ORDER BY l.nivel_valor DESC, total_busquedas DESC, total_deseos DESC, l.id ASC
            LIMIT 10
        ");
        $topLibros = $stmtTop->fetchAll();

        render_vista('catalogo/top_buscados', [
            'topLibros' => $topLibros,
            'config' => $config,
        ], 'Libros Más Buscados y Demandados');
        break;

    // -------------------------------------------------------------
    // CATÁLOGO PÚBLICO Y FICHA
    // -------------------------------------------------------------
    case '/catalogo':
        $pdo = db();
        $terminoBusq = trim((string) ($_GET['q'] ?? ''));
        if ($terminoBusq !== '') {
            catalogo_registrar_busqueda($pdo, $terminoBusq, $usuario ? (int) $usuario['id'] : null);
        }

        $filtros = [
            'q' => $terminoBusq,
            'genero' => $_GET['genero'] ?? '',
            'nivel' => $_GET['nivel'] ?? '',
            'solo_disponibles' => !empty($_GET['solo_disponibles']),
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $datosCat = catalogo_listar_libros($pdo, $filtros, $pagina, 12);

        $generos = $pdo->query('
            SELECT DISTINCT genero 
            FROM libros 
            WHERE genero IS NOT NULL AND genero != "" 
            ORDER BY genero
        ')->fetchAll(PDO::FETCH_COLUMN);

        $datosCat['filtros'] = $filtros;
        $datosCat['generos'] = $generos;

        render_vista('catalogo/index', $datosCat, 'Catálogo de Libros');
        break;

    // -------------------------------------------------------------
    // FICHA PÚBLICA DE LIBRO (/libro?id=X)
    // -------------------------------------------------------------
    case '/libro':
        $libroId = (int) ($_GET['id'] ?? 0);
        if ($libroId <= 0) {
            http_response_code(404);
            render_vista('404', [], 'Libro no encontrado');
            exit;
        }
        $pdo = db();
        $libro = catalogo_obtener_libro($pdo, $libroId);
        if (!$libro) {
            http_response_code(404);
            render_vista('404', [], 'Libro no encontrado');
            exit;
        }
        $ejemplares = catalogo_listar_ejemplares($pdo, $libroId);
        render_vista('catalogo/ficha', [
            'libro' => $libro,
            'ejemplares' => $ejemplares,
        ], $libro['titulo']);
        break;

    // -------------------------------------------------------------
    // API JSON PARA AUTOCOMPLETADO DE BÚSQUEDA
    // -------------------------------------------------------------
    case '/api/autocompletar':
        header('Content-Type: application/json; charset=utf-8');
        $q = (string) ($_GET['q'] ?? '');
        $resultados = catalogo_autocompletar(db(), $q, 6);
        echo json_encode([
            'ok' => true,
            'resultados' => $resultados,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    // -------------------------------------------------------------
    // API JSON PARA ESCÁNER BIBLIOGRÁFICO
    // -------------------------------------------------------------
    case '/api/isbn':
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json; charset=utf-8');
        $isbn = (string) ($_GET['isbn'] ?? '');
        $res = catalogo_buscar_isbn(db(), $isbn);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    // -------------------------------------------------------------
    // API ASÍNCRONA: BÚSQUEDA Y CACHEO EN SEGUNDO PLANO DE PORTADAS
    // -------------------------------------------------------------
    case '/api/libro-portada':
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $libroId = (int) ($_GET['id'] ?? 0);
        $res = catalogo_buscar_y_cachear_portada(db(), $libroId);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    // -------------------------------------------------------------
    // GENERADOR DINÁMICO DE PORTADA SVG PLACEHOLDER LOCAL
    // -------------------------------------------------------------
    case '/portada-svg':
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        $tit = (string) ($_GET['titulo'] ?? '');
        $aut = (string) ($_GET['autor'] ?? '');
        $gen = (string) ($_GET['genero'] ?? '');
        echo catalogo_generar_svg_portada($tit, $aut, $gen);
        exit;

    // -------------------------------------------------------------
    // SERVICIO LOCAL DE PORTADA (CACHEADO Y PROXY SEGURO)
    // -------------------------------------------------------------
    case '/portada-libro':
        $libroId = (int) ($_GET['id'] ?? 0);
        catalogo_servir_portada_libro(db(), $libroId);
        exit;

    // -------------------------------------------------------------
    // -------------------------------------------------------------
    // ADMINISTRACIÓN DE LIBROS (RBAC: catalogo.editar)
    // -------------------------------------------------------------
    case '/admin/libros':
        exigir_permiso('catalogo.editar');
        $pdo = db();
        $filtros = [
            'q' => $_GET['q'] ?? '',
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $datosCat = catalogo_listar_libros($pdo, $filtros, $pagina, 20);
        $datosCat['filtros'] = $filtros;

        render_vista('admin/libros/index', $datosCat, 'Gestión de Libros');
        break;

    case '/libros/entrada':
        exigir_permiso('catalogo.editar');
        $pdo = db();

        if ($metodo === 'POST') {
            $libroId = (int) ($_POST['libro_id'] ?? 0);
            $cantidad = (int) ($_POST['copias'] ?? $_POST['cantidad'] ?? 1);
            $condicion = trim($_POST['condicion'] ?? 'bueno');
            $ubicacion = trim($_POST['ubicacion'] ?? 'MOSTRADOR');
            $depositante = !empty($_POST['depositante']) ? trim($_POST['depositante']) : null;
            $personalId = $usuario ? (int) $usuario['id'] : null;

            try {
                $resultado = libro_entrada_copias($pdo, $libroId, $cantidad, $condicion, $ubicacion, $depositante, $personalId);
                $msg = 'Se han incorporado ' . $resultado['copias_creadas'] . ' copia(s) al catálogo con éxito.';
                if (!empty($resultado['depositante_id'])) {
                    $depNombre = $resultado['depositante']['nombre'] ?? 'lector';
                    $msg .= ' Acreditados +' . $resultado['tokens_acreditados'] . ' token(s) a ' . $depNombre . '.';
                }
                flash($msg, 'exito');
                redireccionar('/libro/' . $libroId);
            } catch (Exception $e) {
                flash($e->getMessage(), 'error');
                $libroSeleccionado = $libroId > 0 ? catalogo_obtener_libro($pdo, $libroId) : null;
                $stock = ['disponible' => 0, 'reservado' => 0, 'retirado' => 0, 'baja' => 0];
                if ($libroSeleccionado) {
                    $ejemplares = catalogo_listar_ejemplares($pdo, (int) $libroSeleccionado['id']);
                    foreach ($ejemplares as $e) {
                        $st = $e['estado'] ?? 'disponible';
                        if (isset($stock[$st])) {
                            $stock[$st]++;
                        }
                    }
                }
                $usuarios = $pdo->query("SELECT u.id, u.nombre, u.email FROM usuarios u WHERE u.activo = 1 ORDER BY u.nombre ASC LIMIT 100")->fetchAll();
                render_vista('catalogo/entrada', [
                    'error' => $e->getMessage(),
                    'libro' => $libroSeleccionado,
                    'stock' => $stock,
                    'criterio' => '',
                    'noEncontrado' => false,
                    'usuarios' => $usuarios,
                ], 'Entrada de Copias');
                exit;
            }
        }

        // GET
        $libroId = !empty($_GET['libro_id']) ? (int) $_GET['libro_id'] : 0;
        $isbn = !empty($_GET['isbn']) ? trim((string) $_GET['isbn']) : '';
        $q = !empty($_GET['q']) ? trim((string) $_GET['q']) : '';
        if ($isbn === '' && $q !== '') {
            $isbn = $q;
        }

        $libroSeleccionado = null;
        $criterio = $isbn !== '' ? $isbn : (!empty($_GET['libro_id']) ? '' : '');

        if ($libroId > 0) {
            $libroSeleccionado = catalogo_obtener_libro($pdo, $libroId);
        } elseif ($isbn !== '') {
            $isbnLimpio = function_exists('catalogo_normalizar_isbn') ? catalogo_normalizar_isbn($isbn) : preg_replace('/[^0-9X]/i', '', $isbn);
            $candidatos = array_unique(array_filter([$isbn, $isbnLimpio]));

            if (function_exists('catalogo_calcular_checksum_isbn13')) {
                if (strlen($isbnLimpio) === 13 && (str_starts_with($isbnLimpio, '978') || str_starts_with($isbnLimpio, '979'))) {
                    $chk = catalogo_calcular_checksum_isbn13(substr($isbnLimpio, 0, 12));
                    if ($chk !== '') {
                        $candidatos[] = substr($isbnLimpio, 0, 12) . $chk;
                    }
                }
                $soloDig = preg_replace('/[^0-9]/', '', $isbn);
                if (strlen($soloDig) >= 12 && (str_starts_with($soloDig, '978') || str_starts_with($soloDig, '979'))) {
                    $chkDig = catalogo_calcular_checksum_isbn13(substr($soloDig, 0, 12));
                    if ($chkDig !== '') {
                        $candidatos[] = substr($soloDig, 0, 12) . $chkDig;
                    }
                }
            }
            $candidatos = array_values(array_unique(array_filter($candidatos)));

            // 1. Buscar por ISBN-13 en la BD local con los candidatos generados
            if (!empty($candidatos)) {
                $inPlaceholders = implode(',', array_fill(0, count($candidatos), '?'));
                $params = array_merge($candidatos, $candidatos);
                $stmt = $pdo->prepare("SELECT * FROM libros WHERE isbn13 IN ({$inPlaceholders}) OR REPLACE(isbn13, '-', '') IN ({$inPlaceholders}) LIMIT 1");
                $stmt->execute($params);
                $libroSeleccionado = $stmt->fetch();
            }

            // 2. Si no coincide por ISBN, buscar por título o autor
            if (!$libroSeleccionado) {
                $stmt = $pdo->prepare("SELECT * FROM libros WHERE titulo LIKE ? OR autor LIKE ? LIMIT 1");
                $stmt->execute(['%' . $isbn . '%', '%' . $isbn . '%']);
                $libroSeleccionado = $stmt->fetch();
            }
        }

        $stock = ['disponible' => 0, 'reservado' => 0, 'retirado' => 0, 'baja' => 0];
        if ($libroSeleccionado) {
            $ejemplares = catalogo_listar_ejemplares($pdo, (int) $libroSeleccionado['id']);
            foreach ($ejemplares as $e) {
                $st = $e['estado'] ?? 'disponible';
                if (isset($stock[$st])) {
                    $stock[$st]++;
                }
            }
        }

        $noEncontrado = ($criterio !== '' && !$libroSeleccionado);
        $usuarios = $pdo->query("SELECT u.id, u.nombre, u.email FROM usuarios u WHERE u.activo = 1 ORDER BY u.nombre ASC LIMIT 100")->fetchAll();

        render_vista('catalogo/entrada', [
            'libro' => $libroSeleccionado,
            'stock' => $stock,
            'criterio' => $criterio,
            'noEncontrado' => $noEncontrado,
            'usuarios' => $usuarios,
        ], 'Entrada de Copias');
        break;

    case '/admin/libros/nuevo':
        exigir_permiso('catalogo.editar');
        $pdo = db();

        if ($metodo === 'POST') {
            try {
                $usuarioId = $usuario ? (int) $usuario['id'] : null;
                $nuevoId = catalogo_guardar_libro($pdo, $_POST, $usuarioId);

                // Si se marcó crear el primer ejemplar físico
                if (!empty($_POST['crear_ejemplar'])) {
                    catalogo_guardar_ejemplar($pdo, [
                        'libro_id' => $nuevoId,
                        'estado' => 'disponible',
                        'ubicacion' => $_POST['ejemplar_ubicacion'] ?? 'A-01-01',
                        'condicion' => $_POST['ejemplar_condicion'] ?? 'bueno',
                        'depositante_id' => $usuarioId,
                    ], $usuarioId);
                }

                flash('Libro incorporado con éxito al catálogo.', 'exito');
                redireccionar('/libros/entrada?libro_id=' . $nuevoId);
            } catch (Exception $e) {
                render_vista('admin/libros/nuevo', [
                    'error' => $e->getMessage(),
                    'libroPrevio' => $_POST,
                ], 'Alta de Libro — Escáner');
                exit;
            }
        }

        render_vista('admin/libros/nuevo', [], 'Alta de Libro — Escáner');
        break;

    case '/admin/libros/editar':
        exigir_permiso('catalogo.editar');
        $pdo = db();

        if ($metodo === 'POST') {
            try {
                $usuarioId = $usuario ? (int) $usuario['id'] : null;
                catalogo_guardar_libro($pdo, $_POST, $usuarioId);
                flash('Metadatos del libro actualizados correctamente.', 'exito');
                redireccionar('/admin/libros');
            } catch (Exception $e) {
                $id = (int) ($_POST['id'] ?? 0);
                $libro = catalogo_obtener_libro($pdo, $id);
                render_vista('admin/libros/editar', [
                    'error' => $e->getMessage(),
                    'libro' => array_merge($libro ?: [], $_POST),
                ], 'Editar Libro');
                exit;
            }
        }

        $id = (int) ($_GET['id'] ?? 0);
        $libro = catalogo_obtener_libro($pdo, $id);
        if (!$libro) {
            http_response_code(404);
            render_vista('404', [], 'Libro no encontrado');
            exit;
        }

        render_vista('admin/libros/editar', [
            'libro' => $libro,
        ], 'Editar Libro');
        break;

    // -------------------------------------------------------------
    // ADMINISTRACIÓN DE EJEMPLARES (RBAC: catalogo.editar)
    // -------------------------------------------------------------
    case '/admin/ejemplares':
        exigir_permiso('catalogo.editar');
        $pdo = db();
        $libroId = !empty($_GET['libro_id']) ? (int) $_GET['libro_id'] : null;
        $ejemplares = catalogo_listar_ejemplares($pdo, $libroId);
        $libro = $libroId ? catalogo_obtener_libro($pdo, $libroId) : null;

        render_vista('admin/ejemplares/index', [
            'ejemplares' => $ejemplares,
            'libro' => $libro,
        ], 'Gestión de Ejemplares Físicos');
        break;

    case '/admin/ejemplares/nuevo':
    case '/admin/deposito':
        exigir_permiso('catalogo.editar');
        $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        redireccionar('/libros/entrada' . $qs, 302);
        break;

    case '/admin/ejemplares/editar':
        exigir_permiso('catalogo.editar');
        $pdo = db();

        if ($metodo === 'POST') {
            try {
                $usuarioId = $usuario ? (int) $usuario['id'] : null;
                catalogo_guardar_ejemplar($pdo, $_POST, $usuarioId);
                flash('Ejemplar físico actualizado con éxito.', 'exito');
                $redirec = !empty($_POST['libro_id']) ? '/admin/ejemplares?libro_id=' . (int) $_POST['libro_id'] : '/admin/ejemplares';
                redireccionar($redirec);
            } catch (Exception $e) {
                $id = (int) ($_POST['id'] ?? 0);
                $ej = catalogo_obtener_ejemplar($pdo, $id);
                render_vista('admin/ejemplares/editar', [
                    'error' => $e->getMessage(),
                    'ejemplar' => array_merge($ej ?: [], $_POST),
                ], 'Editar Ejemplar');
                exit;
            }
        }

        $id = (int) ($_GET['id'] ?? 0);
        $ejemplar = catalogo_obtener_ejemplar($pdo, $id);
        if (!$ejemplar) {
            http_response_code(404);
            render_vista('404', [], 'Ejemplar no encontrado');
            exit;
        }

        render_vista('admin/ejemplares/editar', [
            'ejemplar' => $ejemplar,
        ], 'Editar Ejemplar');
        break;

    // -------------------------------------------------------------
    // IMPORTACIÓN CSV Y GESTIÓN DE POOL DE SOCIOS (RBAC: csv.importar)
    // -------------------------------------------------------------
    case '/admin/csv':
        exigir_permiso('csv.importar');
        $informe = $_SESSION['csv_informe'] ?? null;
        unset($_SESSION['csv_informe']);
        $tipo = (string) ($_GET['tipo'] ?? 'catalogo');

        render_vista('admin/csv/index', [
            'informe' => $informe,
            'tipoImportacion' => $tipo,
        ], 'Importación CSV');
        break;

    case '/admin/csv/catalogo':
        exigir_permiso('csv.importar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/csv?tipo=catalogo');
        }

        $contenido = '';
        if (!empty($_FILES['archivo_csv']['tmp_name']) && is_uploaded_file($_FILES['archivo_csv']['tmp_name'])) {
            $contenido = (string) file_get_contents($_FILES['archivo_csv']['tmp_name']);
        } elseif (!empty($_POST['texto_csv'])) {
            $contenido = (string) $_POST['texto_csv'];
        }

        $detener = !empty($_POST['detener_ante_error']);
        $usuarioId = $usuario ? (int) $usuario['id'] : null;
        $informe = csv_importar_catalogo(db(), $contenido, $detener, $usuarioId);

        $_SESSION['csv_informe'] = $informe;
        redireccionar('/admin/csv?tipo=catalogo');
        break;


    case '/admin/csv/plantilla-catalogo':
        exigir_permiso('csv.importar');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_catalogo_escolar.csv"');
        echo csv_generar_plantilla_catalogo();
        exit;

    case '/admin/csv/plantilla-catalogo-estandar':
        exigir_permiso('csv.importar');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_catalogo_estandar.csv"');
        echo csv_generar_plantilla_catalogo_estandar();
        exit;

    // -------------------------------------------------------------
    // GESTIÓN DE DEPÓSITOS (RBAC: deposito.registrar)
    // -------------------------------------------------------------
    case '/admin/depositos':
        exigir_permiso('deposito.registrar');
        $pendientes = deposito_listar_pendientes(db());
        render_vista('admin/depositos/index', [
            'pendientes' => $pendientes,
        ], 'Depósitos Pendientes');
        break;

    case '/admin/depositos/aprobar':
        exigir_permiso('deposito.registrar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $txId = (int) ($_POST['transaccion_id'] ?? 0);
        $ubicacion = !empty($_POST['ubicacion']) ? trim((string) $_POST['ubicacion']) : null;

        try {
            $res = deposito_aprobar(db(), $txId, (int) $usuario['id'], $ubicacion);
            flash('Depósito aprobado correctamente. Se han acreditado ' . $res['tokens'] . ' tokens al depositante.', 'exito');
        } catch (Exception $e) {
            flash($e->getMessage(), 'error');
        }

        redireccionar('/admin/depositos');
        break;

    case '/admin/depositos/rechazar':
        exigir_permiso('deposito.registrar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $txId = (int) ($_POST['transaccion_id'] ?? 0);
        $motivo = (string) ($_POST['motivo'] ?? '');

        try {
            deposito_rechazar(db(), $txId, (int) $usuario['id'], $motivo);
            flash('El depósito ha sido rechazado y el ejemplar marcado como baja.', 'aviso');
        } catch (Exception $e) {
            flash($e->getMessage(), 'error');
        }

        redireccionar('/admin/depositos');
        break;

    // -------------------------------------------------------------
    // PÁGINAS PÚBLICAS LEGALES E INFORMATIVAS
    // -------------------------------------------------------------
    case '/visitanos':
        render_vista('paginas/visitanos', [
            'config' => $config,
        ], 'Visítanos');
        break;

    case '/privacidad':
        render_vista('auth/privacidad', [
            'config' => $config,
        ], 'Política de Privacidad');
        break;

    // -------------------------------------------------------------
    // ADMINISTRACIÓN (RBAC: Permisos de Gestión o Sistema)
    // -------------------------------------------------------------
    case '/admin':
        exigir_permiso(['config.editar', 'backup.gestionar', 'restaurar.ejecutar', 'auditoria.ver', 'metricas.ver', 'roles.gestionar', 'usuarios.gestionar', 'csv.importar']);

        $pdo = db();
        $stats = [
            'usuarios' => (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn(),
            'libros' => (int) $pdo->query('SELECT COUNT(*) FROM libros')->fetchColumn(),
            'disponibles' => (int) $pdo->query("SELECT COUNT(*) FROM ejemplares WHERE estado = 'disponible'")->fetchColumn(),
            'tokens' => (int) $pdo->query("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE cantidad > 0")->fetchColumn(),
        ];

        // Pendientes de atención
        $reservasExpiranHoy = (int) $pdo->query("SELECT COUNT(*) FROM transacciones WHERE tipo = 'reserva' AND estado = 'activa' AND DATE(fecha_limite) <= CURDATE()")->fetchColumn();

        $diasBackup = (int) ($config['dias_backup_auto'] ?? 7);
        $ultimoBackup = $config['ultimo_backup_auto'] ?? null;
        $backupVencido = false;
        if ($diasBackup > 0) {
            if (empty($ultimoBackup) || (time() - strtotime((string) $ultimoBackup)) > ($diasBackup * 86400)) {
                $backupVencido = true;
            }
        }

        render_vista('admin/panel', [
            'stats' => $stats,
            'reservasExpiranHoy' => $reservasExpiranHoy,
            'backupVencido' => $backupVencido,
        ], 'Panel de Administración');
        break;

    // -------------------------------------------------------------
    // GESTIÓN DE ROLES Y PERMISOS DEL PERSONAL (RBAC: roles.gestionar) (Fase 16)
    // -------------------------------------------------------------
    case '/admin/roles':
        exigir_permiso('roles.gestionar');
        $pdo = db();

        if ($metodo === 'POST') {
            exigir_csrf();
            $nuevos = $_POST['permisos'] ?? [];
            if (!is_array($nuevos)) {
                $nuevos = [];
            }
            $resultado = permisos_actualizar_personal($pdo, $nuevos, (int) $usuario['id']);
            if (!empty($resultado['aviso'])) {
                flash($resultado['aviso'], 'aviso');
            } else {
                flash("Matriz de permisos actualizada con éxito ({$resultado['cambios']} cambios aplicados).", 'exito');
            }
            redireccionar('/admin/roles');
            break;
        }

        $dict = permisos_dict();
        $permisosPersonal = permisos_por_rol($pdo, 2);

        $avisoMostrador = null;
        $tieneMostrador = in_array('mostrador.acceder', $permisosPersonal, true);
        $tieneAccion = in_array('entrega.confirmar', $permisosPersonal, true) || in_array('deposito.registrar', $permisosPersonal, true);
        if (!$tieneMostrador || !$tieneAccion) {
            $avisoMostrador = 'El rol Personal no tiene asignada ninguna operativa completa de mostrador (requiere "mostrador.acceder" y al menos entrega o depósito).';
        }

        render_vista('admin/roles', [
            'dict' => $dict,
            'permisosPersonal' => $permisosPersonal,
            'avisoMostrador' => $avisoMostrador,
        ], 'Roles y Permisos');
        break;

    case '/admin/roles/restaurar':
        exigir_permiso('roles.gestionar');
        $pdo = db();
        if ($metodo === 'POST') {
            exigir_csrf();
            permisos_restaurar_defaults($pdo, (int) $usuario['id']);
            flash('Se han restaurado los permisos del personal a sus valores canónicos por defecto.', 'exito');
            redireccionar('/admin/roles');
        } else {
            redireccionar('/admin/roles');
        }
        break;

    // -------------------------------------------------------------
    // CONFIGURACIÓN DEL SISTEMA (RBAC: config.editar) (Fase 6)
    // -------------------------------------------------------------
    case '/admin/configuracion':
        exigir_permiso('config.editar');
        $pdo = db();

        if ($metodo === 'POST') {
            $clavesPermitidas = [
                'coste_libro', 'bono_deposito', 'bono_bienvenida',
                'horas_reserva', 'max_reservas_activas',
                'dias_backup_auto', 'retencion_backups',
                'centro_nombre', 'centro_direccion', 'centro_telefono', 'centro_email',
                'centro_horario', 'centro_mapa_lat', 'centro_mapa_lng', 'centro_mapa_proveedor',
                'centro_como_llegar',
                'google_client_id', 'google_client_secret', 'google_redirect_uri',
            ];

            $datosActualizar = [];
            foreach ($clavesPermitidas as $k) {
                if (isset($_POST[$k])) {
                    $datosActualizar[$k] = trim((string) $_POST[$k]);
                }
            }

            try {
                $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;
                $cambios = config_actualizar_multiples($pdo, $datosActualizar, $adminId);
                $config = array_merge($config, config_cargar($pdo));
                flash("Configuración guardada correctamente ({$cambios} parámetros actualizados).", 'exito');
            } catch (Exception $e) {
                flash('Error al guardar configuración: ' . $e->getMessage(), 'error');
            }

            redireccionar('/admin/configuracion');
        }

        $configActual = array_merge($config, config_cargar($pdo));
        render_vista('admin/configuracion', [
            'config' => $configActual,
        ], 'Configuración del Sistema');
        break;

    // -------------------------------------------------------------
    // MÉTRICAS Y ANALÍTICA (RBAC: metricas.ver) (Fase 6)
    // -------------------------------------------------------------
    case '/admin/metricas':
        exigir_permiso('metricas.ver');
        $pdo = db();
        $metricas = metricas_obtener_datos($pdo);
        render_vista('admin/metricas', [
            'metricas' => $metricas,
        ], 'Métricas del Sistema');
        break;

    case '/api/admin/metricas':
    case '/admin/metricas/datos':
        exigir_permiso('metricas.ver');
        $datos = metricas_obtener_datos(db());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // GESTIÓN DE COPIAS DE SEGURIDAD (RBAC: backup.gestionar) (Fase 6)
    // -------------------------------------------------------------
    case '/admin/backups':
        exigir_permiso('backup.gestionar');
        $pdo = db();
        $backups = backup_listar($pdo);
        render_vista('admin/backups', [
            'backups' => $backups,
            'config'  => $config,
        ], 'Copias de Seguridad');
        break;

    case '/admin/backups/generar':
    case '/admin/backups/crear':
        exigir_permiso('backup.gestionar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        try {
            $pdo = db();
            $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;
            $res = backup_crear($pdo, 'manual', $adminId);
            flash("Copia de seguridad «{$res['archivo']}» generada correctamente.", 'exito');
        } catch (Exception $e) {
            flash('Error al generar copia de seguridad: ' . $e->getMessage(), 'error');
        }

        redireccionar('/admin/backups');
        break;

    case '/admin/backups/descargar':
        exigir_permiso('backup.gestionar');
        $id = (int) ($_GET['id'] ?? 0);
        $archivo = basename((string) ($_GET['archivo'] ?? ''));
        $pdo = db();

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT archivo FROM backups WHERE id = ?');
            $stmt->execute([$id]);
            $archivo = (string) ($stmt->fetchColumn() ?: '');
        }

        $dirBackups = dirname(__DIR__) . '/backups';
        $ruta = $dirBackups . '/' . $archivo;

        if ($archivo === '' || !str_ends_with($archivo, '.sql') || !is_file($ruta)) {
            http_response_code(404);
            echo 'Archivo de copia de seguridad no encontrado.';
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;

    case '/admin/backups/restaurar':
        exigir_permiso('restaurar.ejecutar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        try {
            $pdo = db();
            $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

            if (!empty($_FILES['archivo_sql']['tmp_name']) && is_uploaded_file($_FILES['archivo_sql']['tmp_name'])) {
                backup_restaurar($pdo, $_FILES['archivo_sql']['tmp_name'], $adminId);
                flash('Base de datos restaurada correctamente desde el archivo subido.', 'exito');
            } elseif (!empty($_POST['archivo'])) {
                $archivo = basename((string) $_POST['archivo']);
                if (!str_ends_with(strtolower($archivo), '.sql')) {
                    throw new Exception('Solo se permiten archivos de copia de seguridad con extensión .sql.');
                }
                $dirBackups = dirname(__DIR__) . '/backups';
                $ruta = $dirBackups . '/' . $archivo;
                if (!file_exists($ruta)) {
                    throw new Exception('El archivo de backup seleccionado no existe.');
                }
                backup_restaurar($pdo, $ruta, $adminId);
                flash("Base de datos restaurada correctamente desde «{$archivo}».", 'exito');
            } else {
                throw new Exception('No se especificó ningún archivo SQL para restaurar.');
            }
        } catch (Exception $e) {
            flash('Error en la restauración: ' . $e->getMessage(), 'error');
        }

        redireccionar('/admin/backups');
        break;

    case '/admin/backups/restaurar-id':
        exigir_permiso('restaurar.ejecutar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $id = (int) ($_POST['backup_id'] ?? 0);
        try {
            $pdo = db();
            $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;
            $stmt = $pdo->prepare('SELECT archivo FROM backups WHERE id = ?');
            $stmt->execute([$id]);
            $archivo = (string) ($stmt->fetchColumn() ?: '');

            $dirBackups = dirname(__DIR__) . '/backups';
            $ruta = $dirBackups . '/' . $archivo;
            if ($archivo === '' || !file_exists($ruta)) {
                throw new Exception('Copia de seguridad no localizada en el servidor.');
            }

            backup_restaurar($pdo, $ruta, $adminId);
            flash("Copia «{$archivo}» restaurada con éxito.", 'exito');
        } catch (Exception $e) {
            flash('Error al restaurar copia: ' . $e->getMessage(), 'error');
        }

        redireccionar('/admin/backups');
        break;


    // -------------------------------------------------------------
    // MODO MOSTRADOR (RBAC: mostrador.acceder) (Fase 5)
    // -------------------------------------------------------------
    case '/mostrador':
        exigir_permiso('mostrador.acceder');
        $pdo = db();

        $codigoBusqueda = trim((string) ($_GET['codigo'] ?? ''));
        $reservaSeleccionada = null;
        if ($codigoBusqueda !== '') {
            $reservaSeleccionada = mostrador_buscar_reserva($pdo, $codigoBusqueda);
            if (!$reservaSeleccionada) {
                flash("No se encontró ninguna reserva activa con el código «{$codigoBusqueda}».", 'aviso');
            }
        }

        $librosCatalogo = $pdo->query('SELECT id, titulo, autor FROM libros ORDER BY titulo ASC')->fetchAll(PDO::FETCH_ASSOC);
        $usuariosSistema = $pdo->query("
            SELECT u.id, u.nombre, u.email, u.activo, r.nombre AS rol_nombre,
                   COALESCE((SELECT SUM(cantidad) FROM movimientos_tokens WHERE usuario_id = u.id), 0) AS saldo
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE r.nombre = 'USUARIO' AND u.activo = 1
            ORDER BY u.nombre ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        $ejemplaresDisponibles = $pdo->query("
            SELECT e.id, e.libro_id, e.ubicacion, e.condicion, l.titulo, l.autor, l.isbn13, l.portada_url
            FROM ejemplares e
            JOIN libros l ON l.id = e.libro_id
            WHERE e.estado = 'disponible'
            ORDER BY l.titulo ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $entregasHoy = (int) $pdo->query("SELECT COUNT(*) FROM transacciones WHERE tipo IN ('reserva', 'entrega_directa') AND estado = 'entregada' AND (DATE(fecha_entrega) = CURDATE() OR DATE(created_at) = CURDATE())")->fetchColumn();
        $depositosHoy = (int) $pdo->query("SELECT COUNT(*) FROM transacciones WHERE tipo = 'deposito' AND DATE(created_at) = CURDATE()")->fetchColumn();
        $reservasExpiranHoyList = $pdo->query("
            SELECT t.id, t.codigo, t.fecha_limite, u.nombre AS usuario_nombre, l.titulo, l.id AS libro_id
            FROM transacciones t
            JOIN usuarios u ON u.id = t.usuario_id
            JOIN ejemplares e ON e.id = t.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE t.tipo = 'reserva' AND t.estado = 'activa' AND DATE(t.fecha_limite) <= CURDATE()
            ORDER BY t.fecha_limite ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $reservasPendientes = mostrador_listar_reservas_pendientes($pdo);

        render_vista('admin/mostrador', [
            'usuario'                => $usuario,
            'config'                 => $config,
            'reservaSeleccionada'    => $reservaSeleccionada,
            'librosCatalogo'         => $librosCatalogo,
            'usuariosSistema'        => $usuariosSistema,
            'ejemplaresDisponibles'  => $ejemplaresDisponibles,
            'entregasHoy'            => $entregasHoy,
            'depositosHoy'           => $depositosHoy,
            'reservasExpiranHoyList' => $reservasExpiranHoyList,
            'reservasPendientes'     => $reservasPendientes,
        ], 'Modo Mostrador');
        break;

    case '/mostrador/entrega-directa':
        exigir_permiso('entrega.confirmar');
        $pdo = db();

        if ($metodo === 'GET') {
            // Acceso directo GET a /mostrador con tab de entrega directa activa (HTTP 200 para quien tenga entrega.confirmar)
            $librosCatalogo = $pdo->query('SELECT id, titulo, autor FROM libros ORDER BY titulo ASC')->fetchAll(PDO::FETCH_ASSOC);
            $usuariosSistema = $pdo->query("
                SELECT u.id, u.nombre, u.email, u.activo, r.nombre AS rol_nombre,
                       COALESCE((SELECT SUM(cantidad) FROM movimientos_tokens WHERE usuario_id = u.id), 0) AS saldo
                FROM usuarios u
                JOIN roles r ON u.rol_id = r.id
                WHERE r.nombre = 'USUARIO' AND u.activo = 1
                ORDER BY u.nombre ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $ejemplaresDisponibles = $pdo->query("
                SELECT e.id, e.libro_id, e.ubicacion, e.condicion, l.titulo, l.autor, l.isbn13, l.portada_url
                FROM ejemplares e
                JOIN libros l ON l.id = e.libro_id
                WHERE e.estado = 'disponible'
                ORDER BY l.titulo ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $reservasPendientes = mostrador_listar_reservas_pendientes($pdo);

            render_vista('admin/mostrador', [
                'usuario'               => $usuario,
                'config'                => $config,
                'reservaSeleccionada'   => null,
                'librosCatalogo'        => $librosCatalogo,
                'usuariosSistema'       => $usuariosSistema,
                'ejemplaresDisponibles' => $ejemplaresDisponibles,
                'reservasPendientes'    => $reservasPendientes,
            ], 'Entrega Directa · Modo Mostrador');
            break;
        }

        exigir_csrf();

        $lector = trim((string) ($_POST['lector'] ?? $_POST['email'] ?? $_POST['usuario_id'] ?? ''));
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $ejemplarId = (int) ($_POST['ejemplar_id'] ?? 0);
        $metodoPago = trim((string) ($_POST['metodo_pago'] ?? 'tokens'));
        $libroDepositadoId = !empty($_POST['libro_depositado_id']) ? (int) $_POST['libro_depositado_id'] : null;
        $condicion = trim((string) ($_POST['condicion_libro_depositado'] ?? 'bueno'));
        $retorno = trim((string) ($_POST['retorno'] ?? ''));
        $esJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));

        if ($usuarioId <= 0 && $lector !== '') {
            if (is_numeric($lector)) {
                $stmtUsr = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND activo = 1');
                $stmtUsr->execute([(int) $lector]);
            } else {
                $stmtUsr = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) AND activo = 1');
                $stmtUsr->execute([$lector]);
            }
            $usuarioId = (int) ($stmtUsr->fetchColumn() ?: 0);
            if ($usuarioId <= 0) {
                $msgError = "No se encontró ningún lector activo con «{$lector}».";
                if ($esJson) {
                    http_response_code(404);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => $msgError]);
                    exit;
                }
                flash($msgError, 'error');
                if ($retorno !== '' && str_starts_with($retorno, '/')) {
                    redireccionar($retorno);
                }
                redireccionar('/mostrador');
            }
        }

        if ($usuarioId <= 0 || $ejemplarId <= 0) {
            $msgError = 'Debes indicar el lector y el ejemplar a entregar.';
            if ($esJson) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msgError]);
                exit;
            }
            flash($msgError, 'error');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno);
            }
            redireccionar('/mostrador');
        }

        try {
            $res = mostrador_entrega_directa(
                $pdo,
                $usuarioId,
                $ejemplarId,
                (int) $usuario['id'],
                $metodoPago,
                $libroDepositadoId,
                $condicion
            );
            if ($esJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array_merge(['ok' => true], $res));
                exit;
            }
            flash("¡Entrega directa completada! Se entregó «{$res['titulo']}» a {$res['usuario_nombre']}. El ejemplar ha quedado retirado del catálogo físico.", 'exito');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno . '?exito=1');
            }
            redireccionar('/mostrador?exito=1');
        } catch (Exception $e) {
            if ($esJson) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
                exit;
            }
            flash('Error en la entrega directa: ' . $e->getMessage(), 'error');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno);
            }
            redireccionar('/mostrador');
        }
        break;

    case '/api/mostrador/verificar-reserva':
        exigir_permiso('entrega.confirmar');
        $ejemplarId = (int) ($_GET['ejemplar_id'] ?? 0);
        $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
        $pdo = db();
        $reserva = mostrador_verificar_reserva_activa($pdo, $ejemplarId, $usuarioId);
        header('Content-Type: application/json; charset=utf-8');
        if ($reserva) {
            echo json_encode([
                'ok'            => true,
                'tiene_reserva' => true,
                'reserva'       => $reserva,
                'aviso'         => 'El usuario ya tiene una reserva activa para este ejemplar.',
                'sugerencia'    => 'Ir a entregar su reserva',
                'url_reserva'   => '/mostrador?codigo=' . urlencode((string) $reserva['codigo']),
            ]);
        } else {
            echo json_encode([
                'ok'            => true,
                'tiene_reserva' => false,
                'reserva'       => null,
                'sugerencia'    => null,
            ]);
        }
        exit;

    case '/mostrador/entregar':
    case '/mostrador/confirmar':
        exigir_permiso('entrega.confirmar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $pdo = db();
        $txId = (int) ($_POST['transaccion_id'] ?? 0);
        $codigo = trim((string) ($_POST['codigo_qr'] ?? $_POST['codigo'] ?? ''));

        if ($txId <= 0 && $codigo !== '') {
            $r = mostrador_buscar_reserva($pdo, $codigo);
            if ($r) {
                $txId = (int) $r['transaccion_id'];
            }
        }

        $metodoPago = (string) ($_POST['metodo_pago'] ?? 'tokens');
        $libroDepositadoId = !empty($_POST['libro_depositado_id']) ? (int) $_POST['libro_depositado_id'] : null;
        $condicion = (string) ($_POST['condicion_libro_depositado'] ?? 'bueno');

        try {
            $resultado = mostrador_completar_entrega(
                $pdo,
                $txId,
                $metodoPago,
                $libroDepositadoId,
                $condicion,
                (int) $usuario['id']
            );
            flash("¡Entrega de «{$resultado['libro_titulo']}» confirmada con éxito!", 'exito');
            redireccionar('/mostrador?exito=1');
        } catch (Exception $e) {
            flash($e->getMessage(), 'error');
            redireccionar('/mostrador');
        }
        break;

    case '/mostrador/deposito':
    case '/mostrador/deposito-directo':
        exigir_permiso('deposito.registrar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $libroId = (int) ($_POST['libro_id'] ?? 0);
        $depositanteId = (int) ($_POST['usuario_id'] ?? 0);
        $condicion = (string) ($_POST['condicion'] ?? 'bueno');
        $ubicacion = trim((string) ($_POST['ubicacion'] ?? 'MOSTRADOR'));

        try {
            $dep = mostrador_registrar_deposito(
                db(),
                $depositanteId,
                $libroId,
                $condicion,
                $ubicacion,
                (int) $usuario['id']
            );
            flash("Depósito en mostrador registrado. Se han acreditado {$dep['tokens_bono']} token(s) al depositante.", 'exito');
            redireccionar('/mostrador?exito=1');
        } catch (Exception $e) {
            flash($e->getMessage(), 'error');
            redireccionar('/mostrador');
        }
        break;

    case '/mostrador/rechazar-deposito':
        exigir_permiso('deposito.registrar');
        if ($metodo !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            exit;
        }

        $depositanteId = (int) ($_POST['usuario_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        mostrador_rechazar_deposito(db(), $depositanteId, $titulo, $motivo, (int) $usuario['id']);
        flash("Rechazo de depósito registrado y auditado correctamente.", 'info');
        redireccionar('/mostrador');
        break;

    case '/api/mostrador/buscar':
        exigir_permiso('mostrador.acceder');
        $codigo = trim((string) ($_GET['q'] ?? $_GET['codigo'] ?? ''));
        $res = mostrador_buscar_reserva(db(), $codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $res !== null, 'reserva' => $res]);
        exit;

    case '/mostrador/escanear':
        exigir_permiso('mostrador.acceder');
        $pdo = db();

        if ($metodo === 'POST') {
            $valor = trim((string) ($_POST['valor'] ?? $_POST['codigo'] ?? ''));
            $resultado = mostrador_escanear($pdo, $valor);

            $esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                || isset($_POST['ajax']);

            if (!$esAjax && isset($_POST['web_submit'])) {
                if (!empty($resultado['accion_sugerida'])) {
                    if (!$resultado['ok'] && !empty($resultado['error'])) {
                        flash($resultado['error'], 'aviso');
                    }
                    redireccionar($resultado['accion_sugerida']);
                } else {
                    flash($resultado['error'] ?? 'Código no reconocido', 'error');
                    redireccionar('/mostrador');
                }
                break;
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            exit;
        }

        redireccionar('/mostrador');
        break;

    // -------------------------------------------------------------
    // -------------------------------------------------------------
    // REGISTRO DE AUDITORÍA (RBAC: auditoria.ver)
    // -------------------------------------------------------------
    case '/admin/auditoria':
        exigir_permiso('auditoria.ver');

        $pdo = db();
        $filtros = [
            'accion' => $_GET['accion'] ?? '',
            'entidad' => $_GET['entidad'] ?? '',
            'usuario_id' => !empty($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : null,
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? '',
            'q' => $_GET['q'] ?? '',
        ];
        $pagina = !empty($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
        $resultado = auditoria_listar($pdo, $filtros, $pagina, 25);
        $acciones = auditoria_acciones_disponibles($pdo);
        $entidades = auditoria_entidades_disponibles($pdo);

        render_vista('admin/auditoria', [
            'registros' => $resultado['registros'],
            'total' => $resultado['total'],
            'pagina' => $resultado['pagina'],
            'por_pagina' => $resultado['por_pagina'],
            'total_paginas' => $resultado['total_paginas'],
            'filtros' => $filtros,
            'acciones' => $acciones,
            'entidades' => $entidades,
        ], 'Registro de Auditoría');
        break;

    // -------------------------------------------------------------
    // GESTIÓN DE USUARIOS (RBAC: usuarios.gestionar) (Fase 10)
    // -------------------------------------------------------------
    case '/admin/usuarios':
        exigir_permiso('usuarios.gestionar');
        $pdo = db();
        $filtros = [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'rol_id' => trim((string) ($_GET['rol_id'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $resUsuarios = admin_usuarios_listar($pdo, $filtros, $pagina, 20);

        $tempPasswordInfo = $_SESSION['temp_password_info'] ?? null;
        unset($_SESSION['temp_password_info']);
        $resetEnlaceInfo = $_SESSION['reset_enlace_info'] ?? null;
        unset($_SESSION['reset_enlace_info']);

        render_vista('admin/usuarios/index', [
            'usuarios'         => $resUsuarios['usuarios'],
            'total'            => $resUsuarios['total'],
            'pagina'           => $resUsuarios['pagina'],
            'porPagina'        => $resUsuarios['por_pagina'],
            'totalPaginas'     => $resUsuarios['total_paginas'],
            'filtros'          => $filtros,
            'tempPasswordInfo' => $tempPasswordInfo,
            'resetEnlaceInfo'  => $resetEnlaceInfo,
        ], 'Gestión de Usuarios');
        break;

    case '/admin/usuarios/crear':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rolId = (int) ($_POST['rol_id'] ?? 3);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        $res = admin_usuario_crear($pdo, $nombre, $email, $password, $rolId, $adminId);
        if ($res['ok']) {
            flash("Usuario «{$nombre}» creado correctamente.", 'exito');
        } else {
            flash('Error al crear usuario: ' . ($res['error'] ?? 'Error desconocido'), 'error');
        }
        redireccionar('/admin/usuarios');
        break;

    case '/admin/usuarios/editar':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $targetUserId = (int) ($_POST['usuario_id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $rolId = (int) ($_POST['rol_id'] ?? 3);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            admin_usuario_actualizar($pdo, $targetUserId, $nombre, $rolId, $adminId);
            flash('Usuario actualizado correctamente.', 'exito');
        } catch (Exception $e) {
            flash('Error al actualizar usuario: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/usuarios');
        break;

    case '/admin/usuarios/cambiar-estado':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $targetUserId = (int) ($_POST['usuario_id'] ?? 0);
        $activo = (bool) (int) ($_POST['activo'] ?? 1);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            admin_usuario_cambiar_estado($pdo, $targetUserId, $activo, $adminId);
            flash('Estado del usuario modificado correctamente.', 'exito');
        } catch (Exception $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/usuarios');
        break;

    case '/admin/usuarios/reset-enlace':
    case '/admin/usuarios/reset-password':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $targetUserId = (int) ($_POST['usuario_id'] ?? 0);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            $stmtTarget = $pdo->prepare('SELECT id, nombre, email, password_hash FROM usuarios WHERE id = ?');
            $stmtTarget->execute([$targetUserId]);
            $targetUser = $stmtTarget->fetch();

            if (!$targetUser) {
                flash('Usuario no encontrado.', 'error');
                redireccionar('/admin/usuarios');
            }

            if ($targetUser['password_hash'] === null) {
                flash('No se puede generar un enlace de restablecimiento para una cuenta vinculada a Google.', 'error');
                redireccionar('/admin/usuarios');
            }

            $token = admin_usuario_generar_enlace_reset($pdo, $targetUserId, $adminId);
            $resetUrl = password_reset_generar_enlace($token);
            $horas = 1;
            if (function_exists('config_obtener')) {
                $cfgH = (int) config_obtener('reset_horas', '1');
                if ($cfgH > 0) $horas = $cfgH;
            }

            $_SESSION['reset_enlace_info'] = [
                'email'        => $targetUser['email'],
                'nombre'       => $targetUser['nombre'],
                'token'        => $token,
                'enlace'       => $resetUrl,
                'expira_horas' => $horas,
            ];
            flash('Enlace de acceso generado con éxito. Se muestra a continuación.', 'aviso');
        } catch (Exception $e) {
            flash('Error al generar enlace de restablecimiento: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/usuarios');
        break;


    case '/admin/usuarios/ajuste-tokens':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $targetUserId = (int) ($_POST['usuario_id'] ?? 0);
        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            $ajuste = admin_usuario_ajustar_tokens($pdo, $targetUserId, $cantidad, $motivo, $adminId);
            flash("Ajuste de {$cantidad} tokens aplicado. Nuevo saldo: {$ajuste['saldo_nuevo']}.", 'exito');
        } catch (Exception $e) {
            flash('Error en el ajuste de tokens: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/usuarios');
        break;

    case '/admin/usuarios/eliminar':
        exigir_permiso('usuarios.gestionar');
        if ($metodo !== 'POST') {
            redireccionar('/admin/usuarios');
        }
        $pdo = db();
        $targetUserId = (int) ($_POST['usuario_id'] ?? 0);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            $resultado = admin_usuario_eliminar($pdo, $targetUserId, $adminId);
            flash('La cuenta de ' . htmlspecialchars($resultado['nombre']) . ' (' . htmlspecialchars($resultado['email']) . ') y todo su historial han sido eliminados permanentemente. El correo queda libre para nuevo registro.', 'exito');
        } catch (Exception $e) {
            flash('Error al eliminar cuenta: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/usuarios');
        break;

    // -------------------------------------------------------------
    // GESTIÓN DE RESERVAS (RBAC: entrega.confirmar, mostrador.acceder, roles.gestionar) (Fase 10)
    // -------------------------------------------------------------
    case '/admin/reservas':
        exigir_permiso(['entrega.confirmar', 'mostrador.acceder', 'roles.gestionar']);
        $pdo = db();
        $filtros = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'estado'  => trim((string) ($_GET['estado'] ?? '')),
            'desde'   => trim((string) ($_GET['desde'] ?? '')),
            'hasta'   => trim((string) ($_GET['hasta'] ?? '')),
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $resReservas = admin_reservas_listar($pdo, $filtros, $pagina, 20);
        $librosDisponibles = $pdo->query("
            SELECT DISTINCT l.id, l.titulo, l.autor, COUNT(e.id) AS copias_disponibles
            FROM libros l
            JOIN ejemplares e ON e.libro_id = l.id
            WHERE e.estado = 'disponible'
            GROUP BY l.id, l.titulo, l.autor
            ORDER BY l.titulo ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        render_vista('admin/reservas/index', [
            'reservas'          => $resReservas['reservas'],
            'total'             => $resReservas['total'],
            'pagina'            => $resReservas['pagina'],
            'porPagina'         => $resReservas['por_pagina'],
            'totalPaginas'      => $resReservas['total_paginas'],
            'filtros'           => $filtros,
            'librosDisponibles' => $librosDisponibles,
        ], 'Gestión de Reservas');
        break;

    case '/admin/reservas/crear':
        exigir_permiso(['entrega.confirmar', 'mostrador.acceder', 'roles.gestionar']);
        if ($metodo !== 'POST') {
            redireccionar('/admin/reservas');
        }
        exigir_csrf();
        $pdo = db();
        $lectorDestino = trim((string) ($_POST['lector'] ?? $_POST['email'] ?? $_POST['usuario_id'] ?? ''));
        $libroId = (int) ($_POST['libro_id'] ?? 0);
        $ejemplarId = (int) ($_POST['ejemplar_id'] ?? 0);
        $retorno = trim((string) ($_POST['retorno'] ?? ''));

        if ($libroId <= 0 && $ejemplarId > 0) {
            $stmtLib = $pdo->prepare('SELECT libro_id FROM ejemplares WHERE id = ?');
            $stmtLib->execute([$ejemplarId]);
            $libroId = (int) ($stmtLib->fetchColumn() ?: 0);
        }

        if ($lectorDestino === '') {
            flash('Debes indicar el email o ID del lector para realizar la reserva.', 'error');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno);
            }
            redireccionar('/admin/reservas');
        }

        if (is_numeric($lectorDestino)) {
            $stmtUsr = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE id = ?');
            $stmtUsr->execute([(int) $lectorDestino]);
        } else {
            $stmtUsr = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE LOWER(email) = LOWER(?)');
            $stmtUsr->execute([$lectorDestino]);
        }
        $usuarioDestino = $stmtUsr->fetch();
        if (!$usuarioDestino) {
            flash('No se encontró ningún lector con «' . htmlspecialchars($lectorDestino) . '».', 'error');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno);
            }
            redireccionar('/admin/reservas');
        }

        if (empty($usuarioDestino['activo'])) {
            flash('El lector «' . htmlspecialchars($lectorDestino) . '» se encuentra inactivo.', 'error');
            if ($retorno !== '' && str_starts_with($retorno, '/')) {
                redireccionar($retorno);
            }
            redireccionar('/admin/reservas');
        }

        try {
            $res = reserva_crear($pdo, (int) $usuarioDestino['id'], $libroId, $ejemplarId > 0 ? $ejemplarId : null, (int) $usuario['id']);
            $horas = $res['horas_reserva'] ?? 72;
            $nombreLector = $usuarioDestino['nombre'] . ' (' . $usuarioDestino['email'] . ')';
            flash("¡Reserva creada con éxito para {$nombreLector}! Código de recogida: «{$res['codigo']}». Plazo: {$horas}h.", 'exito');
        } catch (Exception $e) {
            flash('Error al crear reserva: ' . $e->getMessage(), 'error');
        }

        if ($retorno !== '' && str_starts_with($retorno, '/')) {
            redireccionar($retorno);
        }
        redireccionar('/admin/reservas');
        break;

    case '/admin/reservas/cancelar':
        exigir_permiso(['entrega.confirmar', 'mostrador.acceder', 'roles.gestionar']);
        if ($metodo !== 'POST') {
            redireccionar('/admin/reservas');
        }
        $pdo = db();
        $txId = (int) ($_POST['transaccion_id'] ?? 0);
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;
        $retorno = trim((string) ($_POST['retorno'] ?? ''));

        try {
            admin_reserva_cancelar($pdo, $txId, $adminId);
            flash('Reserva cancelada correctamente. El ejemplar vuelve a estar disponible.', 'exito');
        } catch (Exception $e) {
            flash('Error al cancelar reserva: ' . $e->getMessage(), 'error');
        }

        if ($retorno !== '' && str_starts_with($retorno, '/')) {
            redireccionar($retorno);
        }
        redireccionar('/admin/reservas');
        break;

    case '/admin/reservas/expirar-ahora':
        exigir_permiso(['entrega.confirmar', 'mostrador.acceder', 'roles.gestionar']);
        if ($metodo !== 'POST') {
            redireccionar('/admin/reservas');
        }
        $pdo = db();
        $adminId = isset($usuario['id']) ? (int) $usuario['id'] : null;

        try {
            $expiradas = admin_reservas_expirar_ahora($pdo, $adminId);
            flash("Proceso completado: {$expiradas} reservas expiradas procesadas.", 'info');
        } catch (Exception $e) {
            flash('Error al ejecutar expiración: ' . $e->getMessage(), 'error');
        }
        redireccionar('/admin/reservas');
        break;

    // -------------------------------------------------------------
    // LIBRO MAYOR GLOBAL / MOVIMIENTOS (RBAC: auditoria.ver, metricas.ver, roles.gestionar) (Fase 10)
    // -------------------------------------------------------------
    case '/admin/movimientos':
        exigir_permiso(['auditoria.ver', 'metricas.ver', 'roles.gestionar']);
        $pdo = db();
        $filtros = [
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'tipo'       => trim((string) ($_GET['tipo'] ?? '')),
            'desde'      => trim((string) ($_GET['desde'] ?? '')),
            'hasta'      => trim((string) ($_GET['hasta'] ?? '')),
            'usuario_id' => !empty($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : null,
        ];
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $resMovs = admin_movimientos_listar($pdo, $filtros, $pagina, 25);

        render_vista('admin/movimientos/index', [
            'movimientos'  => $resMovs['movimientos'],
            'total'        => $resMovs['total'],
            'pagina'       => $resMovs['pagina'],
            'porPagina'    => $resMovs['por_pagina'],
            'totalPaginas' => $resMovs['total_paginas'],
            'filtros'      => $filtros,
        ], 'Libro Mayor de Movimientos');
        break;

    // -------------------------------------------------------------
    // 404 NO ENCONTRADO O RUTAS CON PATRÓN DINÁMICO
    // -------------------------------------------------------------
    default:
        // Ruta dinámica: /reset/{token} (Fase 22)
        if (preg_match('#^/reset/([^/]+)$#', $uriPath, $matches)) {
            $token = $matches[1];
            $pdo = db();
            $prFila = password_reset_validar_token($pdo, $token);

            if ($metodo === 'POST') {
                if (!$prFila) {
                    render_vista('auth/reset', [
                        'valido' => false,
                        'token'  => $token,
                        'error'  => 'El enlace de recuperación no es válido o ya ha caducado.',
                    ], 'Enlace no válido');
                    exit;
                }

                $pass = (string) ($_POST['password'] ?? '');
                $passConfirm = (string) ($_POST['password_confirm'] ?? '');

                if (mb_strlen($pass) < 8) {
                    render_vista('auth/reset', [
                        'valido' => true,
                        'token'  => $token,
                        'email'  => $prFila['email'],
                        'error'  => 'La contraseña debe tener al menos 8 caracteres.',
                    ], 'Restablecer contraseña');
                    exit;
                }

                if ($pass !== $passConfirm) {
                    render_vista('auth/reset', [
                        'valido' => true,
                        'token'  => $token,
                        'email'  => $prFila['email'],
                        'error'  => 'Las contraseñas no coinciden.',
                    ], 'Restablecer contraseña');
                    exit;
                }

                $ok = password_reset_completar($pdo, $token, $pass);
                if ($ok) {
                    session_regenerate_id(true);
                    flash('Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión con tu nueva contraseña.', 'exito');
                    redireccionar('/login');
                } else {
                    render_vista('auth/reset', [
                        'valido' => false,
                        'token'  => $token,
                        'error'  => 'No se pudo restablecer la contraseña. El enlace puede haber caducado.',
                    ], 'Enlace no válido');
                    exit;
                }
            } else {
                // GET /reset/{token}
                if (!$prFila) {
                    render_vista('auth/reset', [
                        'valido' => false,
                        'token'  => $token,
                    ], 'Enlace no válido o caducado');
                    exit;
                }

                render_vista('auth/reset', [
                    'valido' => true,
                    'token'  => $token,
                    'email'  => $prFila['email'],
                ], 'Restablecer contraseña');
                break;
            }
        }

        // Ruta dinámica: /admin/usuarios/{id}/historial o /admin/usuarios/{id}/historial/csv (Fase 9)
        if (preg_match('#^/admin/usuarios/(\d+)/historial(/csv)?$#', $uriPath, $matches)) {
            exigir_permiso(['usuarios.gestionar', 'auditoria.ver', 'roles.gestionar']);
            $targetUserId = (int) $matches[1];
            $pdo = db();

            $stmtTarget = $pdo->prepare('SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?');
            $stmtTarget->execute([$targetUserId]);
            $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                http_response_code(404);
                render_vista('404', [], 'Usuario no encontrado');
                exit;
            }

            $filtros = [
                'tipo'  => trim((string) ($_GET['tipo'] ?? '')),
                'desde' => trim((string) ($_GET['desde'] ?? '')),
                'hasta' => trim((string) ($_GET['hasta'] ?? '')),
            ];

            if (!empty($matches[2]) || ($_GET['exportar'] ?? '') === 'csv') {
                $csv = historial_exportar_csv($pdo, $targetUserId, $filtros);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="historial_usuario_' . $targetUserId . '_' . date('Ymd_His') . '.csv"');
                echo $csv;
                exit;
            }

            $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
            $porPagina = 20;

            $resumen = historial_obtener_resumen($pdo, $targetUserId);
            $datosMov = historial_obtener_movimientos($pdo, $targetUserId, $filtros, $pagina, $porPagina);

            render_vista('usuario/historial', [
                'resumen'         => $resumen,
                'movimientos'     => $datosMov['movimientos'],
                'total'           => $datosMov['total'],
                'pagina'          => $datosMov['pagina'],
                'totalPaginas'    => $datosMov['totalPaginas'],
                'filtros'         => $filtros,
                'esVistaAdmin'    => true,
                'usuarioObjetivo' => $targetUser,
            ], 'Historial: ' . $targetUser['nombre']);
            break;
        }

        // API dinámica: /api/libro/{id}/portada
        if (preg_match('#^/api/libro/(\d+)/portada$#', $uriPath, $matches)) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            $libroId = (int) $matches[1];
            $res = catalogo_buscar_y_cachear_portada(db(), $libroId);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // Ruta dinámica: /ejemplar/{id}/editar (§2.2)
        if (preg_match('#^/ejemplar/(\d+)/editar$#', $uriPath, $matches)) {
            exigir_permiso('catalogo.editar');
            $ejemplarId = (int) $matches[1];
            $pdo = db();

            $condicion = trim($_POST['condicion'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $usuarioId = $usuario ? (int) $usuario['id'] : null;

            try {
                ejemplar_editar($pdo, $ejemplarId, $condicion, $ubicacion, $usuarioId);
                flash('Copia #' . $ejemplarId . ' actualizada correctamente.', 'exito');
            } catch (Exception $e) {
                flash('Error al actualizar copia: ' . $e->getMessage(), 'error');
            }

            $retorno = !empty($_POST['retorno']) ? $_POST['retorno'] : ('/ejemplar/' . $ejemplarId);
            redireccionar($retorno);
            break;
        }

        // Ruta dinámica: /ejemplar/{id}/baja (§2.2)
        if (preg_match('#^/ejemplar/(\d+)/baja$#', $uriPath, $matches)) {
            exigir_permiso('catalogo.editar');
            $ejemplarId = (int) $matches[1];
            $pdo = db();

            $motivo = trim($_POST['motivo'] ?? '');
            $usuarioId = $usuario ? (int) $usuario['id'] : null;

            try {
                ejemplar_dar_de_baja($pdo, $ejemplarId, $motivo, $usuarioId);
                flash('Copia #' . $ejemplarId . ' dada de baja del catálogo.', 'exito');
            } catch (Exception $e) {
                flash('No se pudo dar de baja la copia: ' . $e->getMessage(), 'error');
            }

            $retorno = !empty($_POST['retorno']) ? $_POST['retorno'] : ('/ejemplar/' . $ejemplarId);
            redireccionar($retorno);
            break;
        }

        // Ruta dinámica: /ejemplar/{id} (§2.3 Trazabilidad)
        if (preg_match('#^/ejemplar/(\d+)$#', $uriPath, $matches)) {
            exigir_autenticado();
            if (!puede('catalogo.editar') && !puede('entrega.confirmar')) {
                http_response_code(403);
                render_vista('403', [], 'Acceso denegado');
                exit;
            }

            $ejemplarId = (int) $matches[1];
            $pdo = db();
            $trazabilidad = ejemplar_obtener_trazabilidad($pdo, $ejemplarId);

            if (!$trazabilidad) {
                http_response_code(404);
                render_vista('404', [], 'Copia no encontrada');
                exit;
            }

            render_vista('admin/ejemplar_trazabilidad', [
                'ejemplar' => $trazabilidad['ejemplar'],
                'libro' => $trazabilidad['libro'],
                'eventos' => $trazabilidad['eventos'],
            ], 'Trazabilidad Copia #' . $ejemplarId);
            break;
        }

        // Ruta dinámica: /libro/{id}
        if (preg_match('#^/libro/(\d+)$#', $uriPath, $matches)) {
            $libroId = (int) $matches[1];
            $pdo = db();
            $libro = catalogo_obtener_libro($pdo, $libroId);

            if (!$libro) {
                http_response_code(404);
                render_vista('404', [], 'Libro no encontrado');
                exit;
            }

            $ejemplares = catalogo_listar_ejemplares($pdo, $libroId);

            $stmtHist = $pdo->prepare(
                "SELECT t.id, t.tipo, t.created_at, t.estado, t.ejemplar_id,
                        u.nombre AS lector_nombre
                 FROM transacciones t
                 JOIN ejemplares e ON e.id = t.ejemplar_id
                 LEFT JOIN usuarios u ON u.id = t.usuario_id
                 WHERE e.libro_id = ?
                 ORDER BY t.id DESC
                 LIMIT 20"
            );
            $stmtHist->execute([$libroId]);
            $historialLibro = $stmtHist->fetchAll();

            render_vista('catalogo/ficha', [
                'libro' => $libro,
                'ejemplares' => $ejemplares,
                'historialLibro' => $historialLibro,
            ], $libro['titulo']);
            break;
        }

        http_response_code(404);
        render_vista('404', [], 'Página no encontrada');
        break;
}
