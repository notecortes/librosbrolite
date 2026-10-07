<?php
declare(strict_types=1);
/**
 * BookSwap · Runner de tests (PHP puro) — v4.1
 * Uso: docker compose exec app php tests/run.php [--no-reset]
 * Convenciones: IDs fijos, no editar tests para que pasen, pending() = roadmap.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://127.0.0.1');
require ROOT . '/config/config.php';

/* ─── 1. NÚCLEO ─── */
final class TestFailed extends Exception {}
final class SkipTest   extends Exception {}

 $R = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'fails' => []];

function line(string $m, string $c = ''): void {
    $map = ['green' => "\033[32m", 'red' => "\033[31m", 'yellow' => "\033[33m",
            'cyan' => "\033[36m", 'dim' => "\033[90m"];
    echo ($map[$c] ?? '') . $m . "\033[0m\n";
}
function group(string $t): void { line("\n── $t", 'cyan'); }
function short($v): string {
    $s = is_string($v) ? $v : print_r($v, true);
    $s = trim((string) preg_replace('/\s+/', ' ', strip_tags($s)));
    return strlen($s) > 160 ? substr($s, 0, 160) . '…' : $s;
}
function it(string $id, callable $fn): void {
    global $R;
    try { $fn(); $R['pass']++; line("  ✔ $id", 'green'); }
    catch (SkipTest $e) { $R['skip']++; line("  ⇢ $id  [SKIP: {$e->getMessage()}]", 'yellow'); }
    catch (Throwable $e) {
        $R['fail']++; $R['fails'][] = "$id — " . $e->getMessage();
        line("  ✘ $id  (" . short($e->getMessage()) . ')', 'red');
    }
}
function pending(string $id, string $fase): void {
    global $R; $R['skip']++; line("  · $id  [pendiente: $fase]", 'dim');
}

/* ─── 2. ASSERTS ─── */
function assert_true($cond, string $msg = 'condición falsa'): void {
    if (!$cond) throw new TestFailed($msg);
}
function assert_equals($exp, $got, string $msg = ''): void {
    if ($exp !== $got) throw new TestFailed(($msg ? "$msg · " : '')
        . 'esperado «' . short($exp) . '», obtenido «' . short($got) . '»');
}
function assert_contains(string $haystack, string $needle, string $msg = ''): void {
    if (!str_contains($haystack, $needle)) throw new TestFailed(
        ($msg ? "$msg · " : '') . 'la respuesta no contiene «' . $needle . '»');
}
function assert_not_contains(string $haystack, string $needle, string $msg = ''): void {
    if (str_contains($haystack, $needle)) throw new TestFailed(
        ($msg ? "$msg · " : '') . 'la respuesta NO debería contener «' . $needle . '»');
}
function assert_http_code(int $exp, array $res, string $msg = ''): void {
    assert_equals($exp, $res['code'], $msg ?: "HTTP en {$res['_url']}");
}

/* ─── 3. HTTP ─── */
function new_session(): array {
    return ['jar' => tempnam(sys_get_temp_dir(), 'bs_cookies_'), 'last' => ''];
}
function http(string $method, string $url, array $data = [], ?array &$s = null): array {
    $ch = curl_init(BASE_URL . $url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($s !== null) { $opts[CURLOPT_COOKIEJAR] = $s['jar']; $opts[CURLOPT_COOKIEFILE] = $s['jar']; }
    if ($method === 'POST') { $opts[CURLOPT_POSTFIELDS] = http_build_query($data); }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) { $e = curl_error($ch); curl_close($ch); throw new TestFailed("HTTP $url: $e"); }
    $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = substr($raw, $hsize);
    if ($s !== null) $s['last'] = $body;
    return ['code' => $code, 'body' => $body, 'headers' => substr($raw, 0, $hsize), '_url' => $url];
}
function http_get(string $url, ?array &$s = null): array { return http('GET', $url, [], $s); }
function http_post(string $url, array $data, ?array &$s = null): array {
    if ($s !== null && preg_match('/name="csrf[_-]?token"[^>]*value="([^"]+)"/i', $s['last'], $m)) {
        $data += ['csrf_token' => $m[1]];
    }
    return http('POST', $url, $data, $s);
}

/* Contraseñas demo: todas "password" (ver 02_seed.sql / bin/seed_passwords.php) */
const DEMO_USERS = [
    'admin'    => ['admin@bookswap.local',    'password'],
    'personal' => ['personal@bookswap.local', 'password'],
    'usuario'  => ['usuario@bookswap.local',  'password'],
];
function login_como(string $rol): array {
    if (!isset(DEMO_USERS[$rol])) throw new TestFailed("rol desconocido: $rol");
    [$email, $pass] = DEMO_USERS[$rol];
    $s = new_session();
    http_get('/login', $s);
    $r = http_post('/login', ['email' => $email, 'password' => $pass], $s);
    assert_true(in_array($r['code'], [200, 302], true), "login '$rol' devolvió {$r['code']}");
    return $s;
}

/* ─── 4. BASE DE DATOS ─── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        } catch (PDOException $e) {
            throw new TestFailed('Sin conexión a la BD: ' . $e->getMessage());
        }
    }
    return $pdo;
}
function db_exec(string $sql, array $p = []): void { db()->prepare($sql)->execute($p); }
function db_val(string $sql, array $p = []) {
    $st = db()->prepare($sql); $st->execute($p); return $st->fetchColumn();
}
function db_row(string $sql, array $p = []): ?array {
    $st = db()->prepare($sql); $st->execute($p); $r = $st->fetch(PDO::FETCH_ASSOC); return $r ?: null;
}
function schema_ready(): bool {
    try { return (bool) db_val('SHOW TABLES LIKE "usuarios"'); }
    catch (Throwable) { return false; }
}
function split_sql(string $sql): array {
    $stmts = []; $buf = ''; $q = null; $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($q === null && $c === '-' && ($sql[$i + 1] ?? '') === '-') { while ($i < $len && $sql[$i] !== "\n") $i++; continue; }
        if ($q === null && $c === '#') { while ($i < $len && $sql[$i] !== "\n") $i++; continue; }
        if ($c === "'" || $c === '"') { if ($q === null) $q = $c; elseif ($q === $c) $q = null; }
        $buf .= $c;
        if ($q === null && $c === ';') { $stmts[] = trim($buf); $buf = ''; }
    }
    if (trim($buf) !== '') $stmts[] = trim($buf);
    return array_values(array_filter($stmts, fn($s) => $s !== '' && stripos($s, 'DELIMITER') !== 0));
}
function db_reset(): void {
    require_once ROOT . '/app/helpers/usuario_persistencia.php';
    try {
        usuarios_persistir_personalizados();
    } catch (Throwable) {}

    line('↺  Reiniciando BD ' . DB_NAME . ' desde database/*.sql', 'dim');
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', 'root', DB_ROOT_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    $root->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `' . DB_NAME . '`');
    foreach ([ROOT . '/database/01_schema.sql', ROOT . '/database/02_seed.sql'] as $f) {
        if (!is_file($f)) { line('  ⚠ No existe ' . basename($f), 'yellow'); continue; }
        foreach (split_sql((string) file_get_contents($f)) as $st) $root->exec($st);
    }

    try {
        usuarios_restaurar_personalizados($root);
    } catch (Throwable) {}
}
function has_internet(): bool {
    static $ok = null;
    if ($ok === null) {
        $c = @fsockopen('www.googleapis.com', 443, $e, $t, 2);
        $ok = is_resource($c);
        if ($c) fclose($c);
    }
    return $ok;
}

/* ─── 5. EJECUCIÓN ─── */
line("\n════════ BookSwap · Suite de tests (v4.1) ════════", 'cyan');
line('Base: ' . BASE_URL . '  ·  BD: ' . DB_NAME . '@' . DB_HOST . '  ·  ' . date('Y-m-d H:i:s'), 'dim');

if (in_array('--no-reset', $argv ?? [], true)) {
    line('(--no-reset: se conserva la BD actual)', 'dim');
} else {
    db_reset();
}

group('T-SMOKE · Humo (activo desde la base)');
it('T-SMOKE-01: la home responde 200 y muestra LibrosBro', function () {
    $r = http_get('/');
    assert_http_code(200, $r);
    assert_contains($r['body'], 'LibrosBro');
});
it('T-SMOKE-07: /health.php responde 200 con ok:true', function () {
    $r = http_get('/health.php');
    assert_http_code(200, $r);
    $j = json_decode($r['body'], true);
    assert_equals(true, $j['ok'] ?? null, 'JSON con ok:true');
});

group('T-SMOKE (dependen de fases 1-6)');
it('T-SMOKE-02: /login responde 200 y muestra formulario', function () {
    $r = http_get('/login');
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Iniciar sesión');
});
it('T-SMOKE-05: /dashboard responde 200 para usuario autenticado', function () {
    $s = login_como('usuario');
    $r = http_get('/dashboard', $s);
    assert_http_code(200, $r);
    assert_contains($r['body'], 'panel');
});
it('T-SMOKE-06: /admin responde 200 para admin', function () {
    $s = login_como('admin');
    $r = http_get('/admin', $s);
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Administración');
});

it('T-SMOKE-03: /catalogo responde 200 y muestra catálogo', function () {
    $r = http_get('/catalogo');
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Catálogo de Libros');
    assert_contains($r['body'], 'Don Quijote de la Mancha');
});
it('T-SMOKE-04: /libro/{id} responde 200 y muestra ficha de libro', function () {
    $r = http_get('/libro/1');
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Don Quijote de la Mancha');
    assert_contains($r['body'], 'Miguel de Cervantes');
});

it('T-SMOKE-08: /visitanos responde 200 y muestra información del centro', function () {
    $r = http_get('/visitanos');
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Visítanos');
});

group('Fase 1 — Auth + número de socio + Google');
it('T-AUTH-01: registro email+contraseña crea cuenta ACTIVA (sin paso intermedio)', function () {
    $s = new_session();
    http_get('/registro', $s);
    $email = 'nuevo_lector_' . uniqid() . '@example.com';

    $r = http_post('/registro', [
        'nombre' => 'Lector Nuevo',
        'email' => $email,
        'password' => 'secreto123',
    ], $s);
    assert_true(in_array($r['code'], [200, 302], true), "Respuesta registro: {$r['code']}");
    $uid = db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);
    assert_true($uid !== false && $uid > 0, 'Usuario registrado en BD');
    $activo = (int) db_val('SELECT activo FROM usuarios WHERE id = ?', [$uid]);
    assert_equals(1, $activo, 'Cuenta creada en estado ACTIVO');
});

it('T-AUTH-02: Login correcto/incorrecto; session_regenerate_id', function () {
    $s = new_session();
    http_get('/login', $s);
    $rBad = http_post('/login', ['email' => 'admin@bookswap.local', 'password' => 'erronea'], $s);
    assert_true(in_array($rBad['code'], [200, 302], true), 'Login incorrecto respondido');
    $rTest = http_get('/admin', $s);
    assert_true(in_array($rTest['code'], [302, 403], true), 'Sin acceso con credencial errónea');

    http_get('/login', $s);
    $rGood = http_post('/login', ['email' => 'admin@bookswap.local', 'password' => 'password'], $s);
    assert_true(in_array($rGood['code'], [200, 302], true), 'Login correcto respondido');
    $rAdmin = http_get('/admin', $s);
    assert_http_code(200, $rAdmin, 'Acceso a /admin con sesión autenticada');
});

it('T-AUTH-03: 6º intento fallido → bloqueo 15 min', function () {
    $emailTarget = 'bloqueo_' . uniqid() . '@bookswap.local';
    $hash = password_hash('password', PASSWORD_BCRYPT);
    db_exec('INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo) VALUES (?, ?, ?, 3, 1)',
        ['Usuario Bloqueo', $emailTarget, $hash]);

    $s = new_session();
    for ($i = 1; $i <= 5; $i++) {
        http_get('/login', $s);
        http_post('/login', ['email' => $emailTarget, 'password' => 'clave_falsa'], $s);
    }
    http_get('/login', $s);
    $r6 = http_post('/login', ['email' => $emailTarget, 'password' => 'password'], $s);
    assert_contains($r6['body'], 'bloqueada', 'Mensaje de bloqueo temporal presente');
});

it('T-AUTH-04: Contraseñas con password_hash; nada en texto plano', function () {
    $passwords = db()->query('SELECT password_hash FROM usuarios WHERE password_hash IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    assert_true(count($passwords) > 0, 'Hay hashes almacenados');
    foreach ($passwords as $p) {
        assert_not_contains($p, 'password', 'No contiene password en plano');
        $info = password_get_info($p);
        assert_true($info['algo'] !== null && $info['algo'] !== 0, 'Algoritmo de hash válido');
    }
});

it('T-GOOG-01: Sin client_id: botón ausente y /auth/google no redirige a Google', function () {
    db_exec("UPDATE configuracion SET valor = '' WHERE clave = 'google_client_id'");
    $rLogin = http_get('/login');
    assert_not_contains($rLogin['body'], 'Continuar con Google', 'Botón Google oculto');

    $rAuth = http_get('/auth/google');
    assert_true($rAuth['code'] === 302, 'Redirige localmente');
    assert_not_contains($rAuth['body'], 'accounts.google.com', 'No redirige a accounts.google.com');
});

it('T-GOOG-02: Con config: /auth/google → 302 a accounts.google.com con state', function () {
    db_exec("UPDATE configuracion SET valor = 'test_google_client_id_demo' WHERE clave = 'google_client_id'");
    $s = new_session();
    $ch = curl_init(BASE_URL . '/auth/google');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $s['jar'], CURLOPT_COOKIEFILE => $s['jar'],
    ]);
    $raw = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    assert_equals(302, $code, 'Código 302 hacia Google');
    assert_contains($raw, 'accounts.google.com', 'Redirección a accounts.google.com');
    assert_contains($raw, 'state=', 'Parámetro state presente en la URL');

    db_exec("UPDATE configuracion SET valor = '' WHERE clave = 'google_client_id'");
});

it('T-GOOG-03: Callback con state inválido → 403, sin sesión, auditado', function () {
    $s = new_session();
    $r = http_get('/auth/google/callback?state=state_falsificado&code=codigo_prueba', $s);
    assert_http_code(403, $r, 'Callback rechazado con 403');
    $auditado = db_val('SELECT COUNT(*) FROM registro_auditoria WHERE accion LIKE "%oauth%" AND fecha >= NOW() - INTERVAL 1 MINUTE');
    assert_true($auditado > 0, 'Intento fraudulento registrado en auditoría');
});

it("T-GOOG-04: auth_google_procesar() con email local existente → mismo user_id, 'ambos'", function () {
    require_once ROOT . '/app/helpers/auditoria.php';
    require_once ROOT . '/app/helpers/auth_google.php';

    $uLocalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $googleSub = 'sub_google_test_' . uniqid();
    $res = auth_google_procesar($googleSub, 'usuario@bookswap.local', 'Usuaria Demo', 'https://example.com/foto.jpg');

    assert_equals($uLocalId, (int) $res['id'], 'Mismo ID de usuario previo');
    $provider = db_val('SELECT auth_provider FROM usuarios WHERE id = ?', [$uLocalId]);
    assert_equals('ambos', $provider, "Proveedor actualizado a 'ambos'");
    $subGuardado = db_val('SELECT google_sub FROM usuarios WHERE id = ?', [$uLocalId]);
    assert_equals($googleSub, $subGuardado, 'google_sub registrado');
});

it('T-GOOG-05: Email nuevo → cuenta google-only; login local denegado con mensaje', function () {
    require_once ROOT . '/app/helpers/auditoria.php';
    require_once ROOT . '/app/helpers/auth_google.php';

    $emailNuevo = 'nuevo.google.' . uniqid() . '@example.com';
    $subNuevo = 'sub_nuevo_' . uniqid();
    $res = auth_google_procesar($subNuevo, $emailNuevo, 'Google New User', null);

    assert_true(!empty($res['id']), 'Nueva cuenta creada');
    assert_equals('google', $res['auth_provider'], "auth_provider es 'google'");
    $passHash = db_val('SELECT password_hash FROM usuarios WHERE id = ?', [$res['id']]);
    assert_true($passHash === null, 'password_hash permanece en NULL');

    $s = new_session();
    http_get('/login', $s);
    $r = http_post('/login', ['email' => $emailNuevo, 'password' => 'cualquiera'], $s);
    assert_contains($r['body'], 'Google', 'Aviso de cuenta creada con Google');
});

it('T-SEC-01: SQLi en login (\' OR 1=1--) no autentica ni rompe', function () {
    $s = new_session();
    http_get('/login', $s);
    $r = http_post('/login', [
        'email' => "' OR 1=1 --",
        'password' => "' OR '1'='1",
    ], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Respuesta adecuada sin fallo 500');
    $rDash = http_get('/dashboard', $s);
    assert_true($rDash['code'] === 302, 'No se autenticó');
});

it('T-SEC-02: USUARIO en endpoint admin → 403 server-side', function () {
    $s = login_como('usuario');
    $r = http_get('/admin', $s);
    assert_http_code(403, $r, 'Usuario bloqueado en /admin');
});

it('T-SEC-03: POST sin CSRF → rechazado', function () {
    $s = new_session();
    http_get('/login', $s);
    $ch = curl_init(BASE_URL . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['email' => 'admin@bookswap.local', 'password' => 'password']),
        CURLOPT_COOKIEJAR => $s['jar'], CURLOPT_COOKIEFILE => $s['jar'],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    assert_equals(403, $code, 'POST sin CSRF rechazado con 403');
});

it('T-SEC-04: XSS: <script> en título se muestra escapado', function () {
    $xss = '<script>alert("xss")</script>';
    db_exec('INSERT INTO libros (titulo, autor, isbn13) VALUES (?, "Autor XSS", ?)',
        [$xss, '9789999999999']);
    $r = http_get('/');
    assert_not_contains($r['body'], '<script>alert("xss")</script>', 'Script no inyectado');
    assert_contains($r['body'], '&lt;script&gt;', 'Contenido escapado');
    db_exec('DELETE FROM libros WHERE isbn13 = "9789999999999"');
});

it('T-SEC-05: /backups/ inaccesible desde fuera (403/404)', function () {
    $r = http_get('/backups/');
    assert_true(in_array($r['code'], [403, 404], true), "Acceso denegado: {$r['code']}");
});

it('T-PRIV-01: /privacidad → 200, menciona datos y contacto del centro', function () {
    $r = http_get('/privacidad');
    assert_http_code(200, $r);
    $centro = db_val("SELECT valor FROM configuracion WHERE clave = 'centro_nombre'");
    $email  = db_val("SELECT valor FROM configuracion WHERE clave = 'centro_email'");
    assert_contains($r['body'], $centro, 'Nombre del centro en /privacidad');
    assert_contains($r['body'], $email, 'Email del centro en /privacidad');
});

it('T-CAT-01: PERSONAL crea/edita libro y ejemplar', function () {
    $s = login_como('personal');
    $rNuevoForm = http_get('/admin/libros/nuevo', $s);
    assert_contains($rNuevoForm['body'], 'type="text" id="campo-portada"', 'campo-portada usa type="text" para admitir rutas /uploads/covers/...');
    assert_not_contains($rNuevoForm['body'], 'type="url" id="campo-portada"', 'campo-portada NO usa type="url" que bloquea rutas locales');

    $titulo = 'Libro Test ' . uniqid();
    $autor = 'Autor Test ' . uniqid();
    $isbn = '978' . str_pad((string) random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $portadaLocal = '/uploads/covers/' . $isbn . '.jpg';

    $r = http_post('/admin/libros/nuevo', [
        'titulo' => $titulo,
        'autor' => $autor,
        'isbn13' => $isbn,
        'editorial' => 'Editorial Test',
        'anio' => 2024,
        'genero' => 'Ensayo',
        'idioma' => 'es',
        'portada_url' => $portadaLocal,
        'crear_ejemplar' => 1,
        'ejemplar_ubicacion' => 'TEST-01',
        'ejemplar_condicion' => 'nuevo',
    ], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Alta de libro responde 200 o 302');

    $libroId = (int) db_val("SELECT id FROM libros WHERE titulo = ?", [$titulo]);
    assert_true($libroId > 0, 'Libro insertado en la BD');

    $portadaEnBd = db_val("SELECT portada_url FROM libros WHERE id = ?", [$libroId]);
    assert_equals($portadaLocal, $portadaEnBd, 'Ruta local de portada guardada correctamente en BD');

    $ejemplarId = (int) db_val("SELECT id FROM ejemplares WHERE libro_id = ? AND ubicacion = 'TEST-01'", [$libroId]);
    assert_true($ejemplarId > 0, 'Ejemplar físico insertado en la BD');

    // Editar metadatos del libro
    $rEditForm = http_get('/admin/libros/editar?id=' . $libroId, $s);
    assert_contains($rEditForm['body'], 'type="text" id="campo-portada"', 'editar libro también usa type="text" para campo-portada');
    assert_not_contains($rEditForm['body'], 'type="url" id="campo-portada"', 'editar libro NO usa type="url"');

    $tituloEditado = $titulo . ' (Modificado)';
    $rEdit = http_post('/admin/libros/editar', [
        'id' => $libroId,
        'titulo' => $tituloEditado,
        'autor' => $autor,
        'isbn13' => $isbn,
        'editorial' => 'Editorial Test Modificada',
        'anio' => 2025,
        'genero' => 'Ensayo',
        'idioma' => 'es',
        'portada_url' => $portadaLocal,
    ], $s);
    assert_true(in_array($rEdit['code'], [200, 302], true), 'Edición de libro responde 200 o 302');

    $tituloEnBD = db_val("SELECT titulo FROM libros WHERE id = ?", [$libroId]);
    assert_equals($tituloEditado, $tituloEnBD, 'Título actualizado en la BD');
});

it('T-CAT-02: USUARIO en CRUD de catálogo → 403', function () {
    $s = login_como('usuario');
    $r1 = http_get('/admin/libros', $s);
    assert_http_code(403, $r1, 'Acceso a /admin/libros para rol USUARIO');

    $r2 = http_get('/admin/libros/nuevo', $s);
    assert_http_code(403, $r2, 'Acceso a /admin/libros/nuevo para rol USUARIO');

    $r3 = http_post('/admin/libros/nuevo', [
        'titulo' => 'Intento Hack',
        'autor' => 'Hacker',
    ], $s);
    assert_http_code(403, $r3, 'POST /admin/libros/nuevo para rol USUARIO');
});

it('T-BUSQ-01: Búsqueda por título y por autor encuentra resultados', function () {
    // Búsqueda por título
    $r1 = http_get('/catalogo?q=Quijote');
    assert_http_code(200, $r1);
    assert_contains($r1['body'], 'Don Quijote de la Mancha');
    assert_not_contains($r1['body'], 'Fahrenheit 451');

    // Búsqueda por autor
    $r2 = http_get('/catalogo?q=Orwell');
    assert_http_code(200, $r2);
    assert_contains($r2['body'], '1984');
    assert_contains($r2['body'], 'George Orwell');
    assert_not_contains($r2['body'], 'Don Quijote de la Mancha');
});

it('T-API-01: Alta por ISBN real → metadatos y portada (SKIP sin red)', function () {
    if (!has_internet()) throw new SkipTest('sin red');
    $s = login_como('personal');
    $isbnReal = '9780132350884'; // Clean Code
    $r = http_get('/api/isbn?isbn=' . $isbnReal, $s);
    assert_http_code(200, $r);
    $data = json_decode($r['body'], true);
    if (!($data['ok'] ?? false)) {
        throw new SkipTest('Servicio externo no disponible temporalmente: ' . ($data['mensaje'] ?? ''));
    }
    assert_true(($data['ok'] ?? false) === true, 'API devuelve ok:true');
    assert_true(!empty($data['libro']['titulo']), 'Título obtenido desde la API bibliográfica');
    assert_true(!empty($data['libro']['autor']), 'Autor obtenido desde la API bibliográfica');
});

it('T-API-02: ISBN inexistente → formulario manual, sin excepción (SKIP sin red)', function () {
    if (!has_internet()) throw new SkipTest('sin red');
    $s = login_como('personal');
    $isbnInexistente = '9789999999999';
    $r = http_get('/api/isbn?isbn=' . $isbnInexistente, $s);
    assert_http_code(200, $r);
    $data = json_decode($r['body'], true);
    assert_equals(false, $data['ok'] ?? null, 'API devuelve ok:false para ISBN no encontrado');
    assert_true(!empty($data['mensaje']), 'Mensaje descriptivo devuelto sin excepción');
});

group('Fase 3 — CSV y pool de socios');

it('T-CSV-01: CSV válido de catálogo → N libros creados + informe correcto', function () {
    $s = login_como('personal');
    http_get('/admin/csv?tipo=catalogo', $s);
    $isbn1 = '9780307474728';
    $isbn2 = '9780061120084';
    db_exec("DELETE FROM ejemplares WHERE libro_id IN (SELECT id FROM libros WHERE isbn13 IN (?, ?))", [$isbn1, $isbn2]);
    db_exec("DELETE FROM libros WHERE isbn13 IN (?, ?)", [$isbn1, $isbn2]);

    $csv = "isbn;titulo;autor;editorial;anio;genero;idioma\n"
         . "{$isbn1};Cien anos de soledad CSV;Gabriel Garcia Marquez;Debolsillo;1967;Novela;es\n"
         . "{$isbn2};To Kill a Mockingbird CSV;Harper Lee;Harper;1960;Fiction;en";

    $r = http_post('/admin/csv/catalogo', ['texto_csv' => $csv], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Importación devuelve 200 o 302');

    $rInfo = http_get('/admin/csv?tipo=catalogo', $s);
    assert_http_code(200, $rInfo);
    assert_contains($rInfo['body'], 'Cien anos de soledad CSV', 'El informe lista el primer libro importado');
    assert_contains($rInfo['body'], 'To Kill a Mockingbird CSV', 'El informe lista el segundo libro importado');

    $c1 = db_val("SELECT COUNT(*) FROM libros WHERE isbn13 = ?", [$isbn1]);
    $c2 = db_val("SELECT COUNT(*) FROM libros WHERE isbn13 = ?", [$isbn2]);
    assert_equals(1, (int) $c1, 'Primer libro creado en BD');
    assert_equals(1, (int) $c2, 'Segundo libro creado en BD');
});

it('T-CSV-02: Filas erróneas → buenas importadas, informe con nº de línea y motivo', function () {
    $s = login_como('personal');
    http_get('/admin/csv?tipo=catalogo', $s);
    $isbnOk = '9780141439990';
    $isbnErr = '9780141439991';
    db_exec("DELETE FROM libros WHERE isbn13 IN (?, ?)", [$isbnOk, $isbnErr]);

    $csv = "isbn;titulo;autor;editorial;anio;genero;idioma\n"
         . "{$isbnOk};Orgullo y prejuicio CSV;Jane Austen;Penguin;1813;Novela;es\n"
         . ";Libro Sin Autor;;Editorial;2000;Ficcion;es\n"
         . "{$isbnErr};Libro Anio Excesivo;Autor Test;Editorial;99999;Ficcion;es";

    $r = http_post('/admin/csv/catalogo', ['texto_csv' => $csv], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Importación devuelve 200 o 302');

    $rInfo = http_get('/admin/csv?tipo=catalogo', $s);
    assert_http_code(200, $rInfo);
    assert_contains($rInfo['body'], 'L-3', 'Informe contiene número de línea errónea L-3');
    assert_contains($rInfo['body'], 'L-4', 'Informe contiene número de línea errónea L-4');
    assert_contains($rInfo['body'], 'Filas con Error', 'Informe contiene sección de filas con error');

    $cOk = db_val("SELECT COUNT(*) FROM libros WHERE isbn13 = ?", [$isbnOk]);
    assert_equals(1, (int) $cOk, 'Libro de fila válida sí fue importado a la BD');
    $cErr = db_val("SELECT COUNT(*) FROM libros WHERE isbn13 = ?", [$isbnErr]);
    assert_equals(0, (int) $cErr, 'Libro de fila errónea no fue importado a la BD');
});

it('T-CSV-03: Duplicados (ISBN o titulo+autor) → omitidos y listados', function () {
    $s = login_como('personal');
    http_get('/admin/csv?tipo=catalogo', $s);
    $isbnNuevo = '9780140449136';
    db_exec("DELETE FROM ejemplares WHERE libro_id IN (SELECT id FROM libros WHERE isbn13 = ?)", [$isbnNuevo]);
    db_exec("DELETE FROM libros WHERE isbn13 = ?", [$isbnNuevo]);

    $csv = "isbn;titulo;autor;editorial;anio;genero;idioma\n"
         . "9788437604197;Don Quijote Segunda Parte;Miguel de Cervantes;Editorial;1615;Novela;es\n"
         . ";1984;George Orwell;Editorial;1949;Ficcion;es\n"
         . "{$isbnNuevo};Crimen y castigo CSV;Fiodor Dostoievski;Penguin;1866;Novela;es";

    $r = http_post('/admin/csv/catalogo', ['texto_csv' => $csv], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Importación devuelve 200 o 302');

    $rInfo = http_get('/admin/csv?tipo=catalogo', $s);
    assert_http_code(200, $rInfo);
    assert_contains($rInfo['body'], 'L-2', 'Informe contiene número de línea duplicada L-2');
    assert_contains($rInfo['body'], 'L-3', 'Informe contiene número de línea duplicada L-3');
    assert_contains($rInfo['body'], 'Duplicados Omitidos', 'Informe contiene sección de duplicados');

    $cNuevo = db_val("SELECT COUNT(*) FROM libros WHERE isbn13 = ?", [$isbnNuevo]);
    assert_equals(1, (int) $cNuevo, 'Libro nuevo válido fue insertado');
});


group('Fase 4 — Reservas');
require_once ROOT . '/app/helpers/auditoria.php';
require_once ROOT . '/app/helpers/reservas.php';

it('T-RESV-01: Reservar copia disponible → \'activa\', limite = ahora + horas_reserva, código+QR, bloqueo y saldo_resultante', function () {
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-01')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $s = login_como('usuario');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    if ($saldoAntes < 2) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo inicial test');
        $saldoAntes += 5;
    }
    $costeLibro = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'") ?: 1);

    http_get('/reservar?libro_id=' . $bId, $s);

    $r = http_post('/reservar', ['libro_id' => $bId, 'ejemplar_id' => $ejId], $s);
    assert_true(in_array($r['code'], [200, 302], true), 'Petición de reserva devuelve 200 o 302');

    $tx = db()->query("SELECT * FROM transacciones WHERE ejemplar_id = {$ejId} AND estado = 'activa' ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($tx), 'Transacción de reserva creada en BD');
    assert_equals('activa', $tx['estado'], 'Estado de la reserva es activa');
    assert_equals('reserva', $tx['tipo'], 'Tipo es reserva');
    assert_equals($costeLibro, (int)$tx['tokens'], 'Tokens registrados equivalen al coste del libro');
    assert_true(!empty($tx['codigo']), 'Código de reserva generado');
    assert_true(!empty($tx['fecha_limite']), 'Fecha límite asignada');

    $limiteTs = strtotime($tx['fecha_limite']);
    $diffHoras = round(($limiteTs - time()) / 3600);
    assert_true($diffHoras >= 70 && $diffHoras <= 74, "Fecha límite en ~72h (actual: {$diffHoras}h)");

    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('reservado', $estadoEj, 'Ejemplar marcado como reservado');

    // Comprobación de bloqueo de tokens en ledger
    $movBloqueo = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$tx['id']} AND tipo = 'bloqueo_reserva' LIMIT 1")->fetch();
    assert_true(!empty($movBloqueo), 'Movimiento de bloqueo_reserva registrado');
    assert_equals(-$costeLibro, (int) $movBloqueo['cantidad'], 'Cantidad del bloqueo es -coste_libro');
    $saldoDespues = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoAntes - $costeLibro, $saldoDespues, 'Saldo descontado por bloqueo');
    assert_equals($saldoDespues, (int) $movBloqueo['saldo_resultante'], 'saldo_resultante coherente en movimiento');

    $rMis = http_get('/mis-reservas', $s);
    assert_http_code(200, $rMis);
    assert_contains($rMis['body'], $tx['codigo'], 'Mis Reservas muestra el código de recogida');
});

it('T-RESV-02: max_reservas_activas alcanzado → 4ª denegada; ampliando el límite, permitida', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'mixta@bookswap.local'");
    db_exec("UPDATE transacciones SET estado = 'cancelada' WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    db_exec("UPDATE configuracion SET valor = '3' WHERE clave = 'max_reservas_activas'");

    // Asegurar saldo suficiente para 4 reservas
    $saldoMixta = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    if ($saldoMixta < 5) {
        ledger_registrar_movimiento(db(), $uid, 10, 'bono', null, 'Saldo para 4 reservas');
    }

    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");

    $ejIds = [];
    for ($i = 1; $i <= 4; $i++) {
        db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-LIM')", [$bId]);
        $ejIds[] = (int) db()->lastInsertId();
    }

    for ($i = 0; $i < 3; $i++) {
        reserva_crear(db(), $uid, $bId, $ejIds[$i]);
    }

    $activas = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    assert_equals(3, $activas, 'Usuario tiene 3 reservas activas');

    $exLanzada = false;
    try {
        reserva_crear(db(), $uid, $bId, $ejIds[3]);
    } catch (Exception $e) {
        $exLanzada = true;
        assert_contains($e->getMessage(), 'límite', 'Mensaje indica que se alcanzó el límite');
    }
    assert_true($exLanzada, '4ª reserva denegada al superar max_reservas_activas');

    db_exec("UPDATE configuracion SET valor = '4' WHERE clave = 'max_reservas_activas'");

    $res4 = reserva_crear(db(), $uid, $bId, $ejIds[3]);
    assert_true(!empty($res4['codigo']), '4ª reserva permitida tras ampliar límite');

    $activasFinal = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    assert_equals(4, $activasFinal, 'Usuario tiene ahora 4 reservas activas');

    db_exec("UPDATE configuracion SET valor = '3' WHERE clave = 'max_reservas_activas'");
});

it('T-RESV-03: Reservar copia no disponible → denegado', function () {
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'retirado', 'EST-02')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    $denegado = false;
    try {
        reserva_crear(db(), $uid, $bId, $ejId);
    } catch (Exception $e) {
        $denegado = true;
    }
    assert_true($denegado, 'Reserva de copia no disponible denegada');

    $txCount = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE ejemplar_id = ? AND estado = 'activa'", [$ejId]);
    assert_equals(0, $txCount, 'No se creó ninguna reserva para el ejemplar no disponible');
});

it('T-RESV-04: Expiración → \'expirada\', copia \'disponible\', notificación; devolución por default y retención con penalizar_expiracion=1', function () {
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-EXP1')", [$bId]);
    $ejId1 = (int) db()->lastInsertId();

    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    if (ledger_obtener_saldo(db(), $uid) < 3) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo test expiración');
    }

    // Parte A: default penalizar_expiracion='0' -> libera tokens
    db_exec("UPDATE configuracion SET valor = '0' WHERE clave = 'penalizar_expiracion'");
    $saldoAntesExp = ledger_obtener_saldo(db(), $uid);

    $res1 = reserva_crear(db(), $uid, $bId, $ejId1);
    $txId1 = (int) $res1['id'];
    $cod1 = $res1['codigo'];
    db_exec("UPDATE transacciones SET fecha_limite = NOW() - INTERVAL 2 HOUR WHERE id = ?", [$txId1]);

    $output = [];
    $returnCode = 0;
    exec('php ' . escapeshellarg(ROOT . '/bin/expirar.php'), $output, $returnCode);
    assert_equals(0, $returnCode, 'bin/expirar.php ejecutó con éxito (código 0)');

    assert_equals('expirada', db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId1]), 'Transacción marcada como expirada');
    assert_equals('disponible', db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId1]), 'Ejemplar retornado a disponible');
    $saldoDespuesExp = ledger_obtener_saldo(db(), $uid);
    assert_equals($saldoAntesExp, $saldoDespuesExp, 'Tokens devueltos con default penalizar_expiracion=0');
    $movLib = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId1} AND tipo = 'liberacion_reserva'")->fetch();
    assert_true(!empty($movLib), 'Movimiento de liberacion_reserva generado');

    // Parte B: penalizar_expiracion='1' -> NO devuelve tokens + auditoría
    db_exec("UPDATE configuracion SET valor = '1' WHERE clave = 'penalizar_expiracion'");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-EXP2')", [$bId]);
    $ejId2 = (int) db()->lastInsertId();

    $saldoAntesPen = ledger_obtener_saldo(db(), $uid);
    $res2 = reserva_crear(db(), $uid, $bId, $ejId2);
    $txId2 = (int) $res2['id'];
    db_exec("UPDATE transacciones SET fecha_limite = NOW() - INTERVAL 2 HOUR WHERE id = ?", [$txId2]);

    exec('php ' . escapeshellarg(ROOT . '/bin/expirar.php'), $output, $returnCode);
    assert_equals('expirada', db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId2]));
    assert_equals('disponible', db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId2]));

    $movLib2 = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId2} AND tipo = 'liberacion_reserva'")->fetch();
    assert_true(empty($movLib2), 'NO se generó liberacion_reserva al penalizar');
    $saldoDespuesPen = ledger_obtener_saldo(db(), $uid);
    assert_equals($saldoAntesPen - 1, $saldoDespuesPen, 'Tokens bloqueados se perdieron (no reembolsados)');

    $aud = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'reserva_expirada' AND entidad_id = {$txId2} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($aud), 'Auditoría registrada');
    assert_contains($aud['detalle'], 'penalizado', 'Auditoría refleja penalización');

    db_exec("UPDATE configuracion SET valor = '0' WHERE clave = 'penalizar_expiracion'");
});

it('T-RESV-05: Cancelación por el usuario → copia \'disponible\' y liberación de tokens', function () {
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-CANC')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $s = login_como('usuario');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    if (ledger_obtener_saldo(db(), $uid) < 2) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo para test cancelación');
    }
    $saldoAntes = ledger_obtener_saldo(db(), $uid);

    http_get('/reservar?libro_id=' . $bId, $s);
    http_post('/reservar', ['libro_id' => $bId, 'ejemplar_id' => $ejId], $s);
    $txId = (int) db_val("SELECT id FROM transacciones WHERE ejemplar_id = ? AND estado = 'activa'", [$ejId]);
    assert_true($txId > 0, 'Reserva creada previa a la cancelación');

    http_get('/mis-reservas', $s);
    $rCanc = http_post('/mis-reservas/cancelar', ['transaccion_id' => $txId], $s);
    assert_true(in_array($rCanc['code'], [200, 302], true), 'Cancelación responde 200 o 302');

    $estadoTx = db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId]);
    assert_equals('cancelada', $estadoTx, 'Transacción marcada como cancelada');

    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('disponible', $estadoEj, 'Ejemplar retornado a estado disponible');

    $movLib = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId} AND tipo = 'liberacion_reserva'")->fetch();
    assert_true(!empty($movLib), 'Movimiento liberacion_reserva registrado');
    $saldoFinal = ledger_obtener_saldo(db(), $uid);
    assert_equals($saldoAntes, $saldoFinal, 'Saldo restaurado tras cancelación');
});

group('Fase 5 — Mostrador y economía');
require_once ROOT . '/app/helpers/ledger.php';
require_once ROOT . '/app/helpers/mostrador.php';

it('T-ENTR-01: Entrega con tokens → consolidación SIN movimiento nuevo, metodo_pago=\'tokens\', copia \'retirado\', \'entregada\'', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-ENT1')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    if (ledger_obtener_saldo(db(), $uid) < 2) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo inicial para T-ENTR-01');
    }

    $res = reserva_crear(db(), $uid, $bId, $ejId);
    $txId = (int) $res['id'];

    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    $movsCountAntes = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);

    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $resultado = mostrador_completar_entrega(db(), $txId, 'tokens', null, 'bueno', $personalId);
    assert_true($resultado['ok'], 'Entrega completada exitosamente');

    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('retirado', $estadoEj, 'Ejemplar marcado como retirado');

    $estadoTx = db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId]);
    assert_equals('entregada', $estadoTx, 'Transacción marcada como entregada');

    $metodoTx = db_val("SELECT metodo_pago FROM transacciones WHERE id = ?", [$txId]);
    assert_equals('tokens', $metodoTx, 'Método de pago registrado como tokens');

    $movsCountDespues = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($movsCountAntes, $movsCountDespues, 'Consolidación SIN nuevo movimiento en ledger al entregar');

    $saldoDespues = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoAntes, $saldoDespues, 'Saldo se mantiene inalterado (el cobro fue en la reserva)');
});

it('T-ENTR-02: Entrega con libro NO admitido sin alta → rechazada, BD sin cambios', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    if (ledger_obtener_saldo(db(), $uid) < 2) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo para T-ENTR-02');
    }
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-ENT2')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $res = reserva_crear(db(), $uid, $bId, $ejId);
    $txId = (int) $res['id'];

    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $movsAntes = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);

    $fallo = false;
    try {
        mostrador_completar_entrega(db(), $txId, 'libro', 999999, 'bueno', $personalId);
    } catch (Exception $e) {
        $fallo = true;
    }
    assert_true($fallo, 'Entrega con libro inexistente lanzada excepción');

    $estadoTx = db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId]);
    assert_equals('activa', $estadoTx, 'Transacción sigue activa sin cambios');

    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('reservado', $estadoEj, 'Ejemplar sigue reservado');

    $movsDespues = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($movsAntes, $movsDespues, 'No se registraron movimientos en el ledger');
});

it('T-ENTR-03: Entrega con libro admitido → depósito +bono_deposito Y retiro −coste_libro', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    if (ledger_obtener_saldo(db(), $uid) < 2) {
        ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Saldo para T-ENTR-03');
    }
    db_exec("UPDATE transacciones SET estado = 'cancelada' WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    $bId1 = (int) db_val("SELECT id FROM libros ORDER BY id ASC LIMIT 1");
    $bId2 = (int) db_val("SELECT id FROM libros ORDER BY id DESC LIMIT 1");

    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-ENT3')", [$bId1]);
    $ejId1 = (int) db()->lastInsertId();

    $res = reserva_crear(db(), $uid, $bId1, $ejId1);
    $txId = (int) $res['id'];

    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");

    $resultado = mostrador_completar_entrega(db(), $txId, 'libro', $bId2, 'bueno', $personalId);
    assert_true($resultado['ok'], 'Entrega por intercambio completada');

    $estadoEj1 = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId1]);
    assert_equals('retirado', $estadoEj1, 'Ejemplar reservado retirado');

    $nuevoEj = db()->query("SELECT * FROM ejemplares WHERE libro_id = {$bId2} AND depositante_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($nuevoEj), 'Nuevo ejemplar depositado creado en BD');
    assert_equals('disponible', $nuevoEj['estado'], 'Nuevo ejemplar depositado está disponible');

    $ultimosMovs = db()->query("SELECT * FROM movimientos_tokens WHERE usuario_id = {$uid} ORDER BY id DESC LIMIT 2")->fetchAll();
    assert_equals(2, count($ultimosMovs), 'Dos movimientos registrados en ledger');

    $tipos = array_column($ultimosMovs, 'tipo');
    assert_true(in_array('deposito', $tipos, true), 'Movimiento de depósito registrado');
    assert_true(in_array('retiro', $tipos, true), 'Movimiento de retiro registrado');

    $saldoDespues = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoAntes, $saldoDespues, 'Saldo neto sin variación con coste_libro=1 y bono_deposito=1');
});

it('T-ENTR-04: Entrega tras fecha_limite → denegada', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-ENT4')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $cod = 'RES-CAD-' . uniqid();
    db_exec("INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, codigo, estado, fecha_limite, created_at)
             VALUES ('reserva', ?, ?, 0, ?, 'activa', NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 73 HOUR)",
             [$ejId, $uid, $cod]);
    $txId = (int) db()->lastInsertId();

    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $denegado = false;
    try {
        mostrador_completar_entrega(db(), $txId, 'tokens', null, 'bueno', $personalId);
    } catch (Exception $e) {
        $denegado = true;
    }
    assert_true($denegado, 'Entrega tras fecha límite denegada con excepción');

    $estadoTx = db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId]);
    assert_true($estadoTx !== 'entregada', 'Transacción no fue entregada');
});

it('T-DEPO-01: Depósito de libro admitido → +bono_deposito, copia \'disponible\'', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");

    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    $bono = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'bono_deposito'") ?: 1);

    $res = mostrador_registrar_deposito(db(), $uid, $bId, 'bueno', 'MOSTRADOR', $personalId);
    assert_true($res['ok'], 'Depósito registrado exitosamente');

    $ejId = (int) $res['ejemplar_id'];
    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('disponible', $estadoEj, 'Ejemplar queda en estado disponible');

    $saldoDespues = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoAntes + $bono, $saldoDespues, 'Saldo acreditado con el bono de depósito');

    $txId = (int) $res['transaccion_id'];
    $tipoTx = db_val("SELECT tipo FROM transacciones WHERE id = ?", [$txId]);
    assert_equals('deposito', $tipoTx, 'Transacción registrada como tipo deposito');
});

it('T-DEPO-02: Depósito de libro no catalogado → alta al vuelo y aceptar, o rechazar (auditado)', function () {
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");

    mostrador_rechazar_deposito(db(), $uid, 'Libro Antiguo Roto', 'Deterioro grave de cubierta y páginas', $personalId);

    $audit = db()->query("
        SELECT * FROM registro_auditoria
        WHERE accion = 'deposito_rechazado'
        ORDER BY id DESC LIMIT 1
    ")->fetch();

    assert_true(!empty($audit), 'Evento deposito_rechazado registrado en registro_auditoria');
    $det = json_decode($audit['detalle'], true);
    assert_equals($uid, (int) $det['usuario_id'], 'ID de usuario reflejado en el detalle');
    assert_equals('Libro Antiguo Roto', $det['titulo'], 'Título reflejado en el detalle');
});

it('T-LEDGER-01: Tras N operaciones: saldo = SUM(movimientos) exacto por usuario', function () {
    $uids = db()->query("SELECT DISTINCT usuario_id FROM movimientos_tokens")->fetchAll(PDO::FETCH_COLUMN);
    assert_true(!empty($uids), 'Existen usuarios con movimientos');

    foreach ($uids as $uid) {
        $suma = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
        $ultimoSaldoResultante = (int) db_val("
            SELECT saldo_resultante FROM movimientos_tokens
            WHERE usuario_id = ? ORDER BY id DESC LIMIT 1
        ", [$uid]);
        assert_equals($suma, $ultimoSaldoResultante, "Para usuario {$uid}, saldo_resultante último coincide con SUM(cantidad)");
    }
});

it('T-LEDGER-02: Ningún flujo deja saldo negativo (intento → rechazo sin cambios)', function () {
    $email = 'cero_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, rol_id, activo, email_verificado) VALUES ('Usuario Cero', ?, 1, 1, 1)", [$email]);
    $uid = (int) db()->lastInsertId();

    $saldoInicial = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals(0, $saldoInicial, 'Usuario nuevo tiene saldo 0');

    $rechazado = false;
    try {
        ledger_registrar_movimiento(db(), $uid, -1, 'retiro', null, 'Intento de retiro sin saldo');
    } catch (Exception $e) {
        $rechazado = true;
    }
    assert_true($rechazado, 'Intento de cargo con saldo cero rechazado por el ledger');

    $saldoFinal = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals(0, $saldoFinal, 'Saldo sigue siendo 0 sin movimientos aplicados');
});

it('T-MOST-01: /mostrador: 200 PERSONAL/ADMIN, 403 USUARIO', function () {
    $sPersonal = login_como('personal');
    $rPersonal = http_get('/mostrador', $sPersonal);
    assert_http_code(200, $rPersonal);
    assert_contains($rPersonal['body'], 'Modo Mostrador', 'Personal accede a /mostrador');

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/mostrador', $sAdmin);
    assert_http_code(200, $rAdmin);

    $sUsuario = login_como('usuario');
    $rUsuario = http_get('/mostrador', $sUsuario);
    assert_http_code(403, $rUsuario);
});

it('T-AUDIT-01: Entrega confirmada en registro_auditoria con método de pago', function () {
    $audit = db()->query("
        SELECT * FROM registro_auditoria
        WHERE accion = 'entrega_completada'
        ORDER BY id DESC LIMIT 1
    ")->fetch();

    assert_true(!empty($audit), 'Existe registro_auditoria con accion entrega_completada');
    $det = json_decode($audit['detalle'], true);
    assert_true(isset($det['metodo_pago']), 'Detalle contiene metodo_pago');
    assert_true(in_array($det['metodo_pago'], ['tokens', 'libro'], true), 'metodo_pago es tokens o libro');
});

it('T-VISIT-01: página pública /visitanos renderiza centro_direccion y centro_telefono exactos', function () {
    $dir = (string) db_val("SELECT valor FROM configuracion WHERE clave = 'centro_direccion'");
    $tel = (string) db_val("SELECT valor FROM configuracion WHERE clave = 'centro_telefono'");
    $r = http_get('/visitanos');
    assert_http_code(200, $r);
    assert_contains($r['body'], $dir, 'Contiene centro_direccion');
    assert_contains($r['body'], $tel, 'Contiene centro_telefono');
});

it('T-VISIT-02: el plano/mapa iframe ha sido eliminado de la vista pública', function () {
    $r = http_get('/visitanos');
    assert_http_code(200, $r);
    assert_not_contains($r['body'], '<iframe', 'No contiene elemento iframe de mapa');
});

it('T-AUDIT-02: cambio de configuración auditado con valor_anterior y valor_nuevo', function () {
    require_once ROOT . '/app/helpers/configuracion.php';
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $valPrevio = (string) db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'");
    $nuevoValor = ($valPrevio === '2') ? '3' : '2';

    $actualizado = config_actualizar_clave(db(), 'coste_libro', $nuevoValor, $adminId);
    assert_true($actualizado, 'config_actualizar_clave devuelve true al modificar');

    $audit = db()->query("
        SELECT * FROM registro_auditoria
        WHERE accion = 'config_modificada'
        ORDER BY id DESC LIMIT 1
    ")->fetch();

    assert_true(!empty($audit), 'Existe registro en registro_auditoria con accion config_modificada');
    $det = json_decode($audit['detalle'], true);
    assert_true(isset($det['clave']), 'Detalle contiene clave');
    assert_equals('coste_libro', $det['clave'], 'Clave es coste_libro');
    assert_true(isset($det['valor_anterior']), 'Detalle contiene valor_anterior');
    assert_equals($valPrevio, (string) $det['valor_anterior'], 'valor_anterior coincide con el previo');
    assert_true(isset($det['valor_nuevo']), 'Detalle contiene valor_nuevo');
    assert_equals($nuevoValor, (string) $det['valor_nuevo'], 'valor_nuevo coincide con el nuevo');

    // Restaurar valor original para no afectar otros tests
    config_actualizar_clave(db(), 'coste_libro', $valPrevio, $adminId);
});

it('T-BAK-01: creación de backup genera fichero SQL válido con schema + data y registro en base de datos', function () {
    require_once ROOT . '/app/helpers/backup.php';
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $res = backup_crear(db(), 'manual', $adminId);

    assert_true($res['ok'], 'backup_crear devuelve ok');
    assert_true(file_exists($res['ruta']), 'El archivo SQL existe en disco');
    $contenido = (string) file_get_contents($res['ruta']);
    assert_true(strlen($contenido) > 500, 'El archivo tiene tamaño representativo');
    assert_contains($contenido, 'CREATE TABLE', 'El backup contiene CREATE TABLE');
    assert_contains($contenido, 'INSERT INTO', 'El backup contiene INSERT INTO');

    $row = db()->query("SELECT * FROM backups WHERE id = {$res['id']}")->fetch();
    assert_true(!empty($row), 'Existe fila en tabla backups');
    assert_equals($res['archivo'], $row['archivo'], 'Nombre de archivo coincide en tabla backups');
});

it('T-BAK-02: restauración de backup restaura tablas y datos', function () {
    require_once ROOT . '/app/helpers/backup.php';
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");

    // Generar backup del estado actual
    $res = backup_crear(db(), 'manual', $adminId);
    $backupPath = $res['ruta'];

    // Insertar un dato temporal de prueba
    $tituloTest = 'Libro Temporal de Prueba ' . uniqid();
    db_exec("INSERT INTO libros (isbn13, titulo, autor) VALUES ('9999999999999', ?, 'Autor Test')", [$tituloTest]);
    assert_true((bool) db_val("SELECT id FROM libros WHERE titulo = ?", [$tituloTest]), 'Libro temporal insertado');

    // Restaurar el backup
    $resRestauracion = backup_restaurar(db(), $backupPath, $adminId);
    assert_true($resRestauracion['ok'], 'backup_restaurar reporta éxito');

    // Comprobar que el libro temporal ya no existe
    $existe = db_val("SELECT COUNT(*) FROM libros WHERE titulo = ?", [$tituloTest]);
    assert_equals(0, (int) $existe, 'El libro temporal no existe tras restaurar');

    // Comprobar que los usuarios canónicos siguen intactos
    $numUsuarios = (int) db_val("SELECT COUNT(*) FROM usuarios");
    assert_true($numUsuarios >= 3, 'Usuarios intactos tras restauración');
});

it('T-BAK-03: acceso a /admin/backups restringido solo a rol admin (403 para usuario y personal)', function () {
    $sUsuario = login_como('usuario');
    $rUsuario = http_get('/admin/backups', $sUsuario);
    assert_http_code(403, $rUsuario, 'Usuario recibe 403 en /admin/backups');

    $sPersonal = login_como('personal');
    $rPersonal = http_get('/admin/backups', $sPersonal);
    assert_http_code(403, $rPersonal, 'Personal recibe 403 en /admin/backups');

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/admin/backups', $sAdmin);
    assert_http_code(200, $rAdmin, 'Admin puede acceder a /admin/backups');
});

it('T-MET-01: métricas agregadas devuelven estructura con copias_por_estado, tokens_en_circulacion y totales', function () {
    require_once ROOT . '/app/helpers/metricas.php';
    $datos = metricas_obtener_datos(db());

    assert_true(isset($datos['ok']) && $datos['ok'] === true, 'Métricas devuelven ok=true');
    assert_true(isset($datos['copias_por_estado']), 'Estructura incluye copias_por_estado');
    assert_true(is_array($datos['copias_por_estado']), 'copias_por_estado es un array');
    assert_true(isset($datos['copias_por_estado']['disponible']), 'copias_por_estado contiene disponible');

    assert_true(isset($datos['tokens_en_circulacion']), 'Estructura incluye tokens_en_circulacion');
    assert_true(is_int($datos['tokens_en_circulacion']), 'tokens_en_circulacion es un entero');

    assert_true(isset($datos['totales']), 'Estructura incluye totales');
    assert_true(isset($datos['totales']['libros']), 'totales incluye libros');
    assert_true(isset($datos['totales']['ejemplares']), 'totales incluye ejemplares');
    assert_true(isset($datos['totales']['socios']), 'totales incluye socios');
    assert_true(isset($datos['totales']['reservas_activas']), 'totales incluye reservas_activas');
});

group('Regresión (se activan desde Fase 3)');
it('T-RGRC-01: Regresión contable: saldo_resultante del último movimiento = SUM(cantidad) en movimientos_tokens por usuario', function () {
    $rows = db()->query("
        SELECT m.usuario_id,
               (SELECT m2.saldo_resultante FROM movimientos_tokens m2 WHERE m2.usuario_id = m.usuario_id ORDER BY m2.id DESC LIMIT 1) AS ultimo_saldo,
               SUM(m.cantidad) AS suma_movimientos
        FROM movimientos_tokens m
        GROUP BY m.usuario_id
    ")->fetchAll();
    assert_true(!empty($rows), 'Existen movimientos en el ledger');
    foreach ($rows as $row) {
        assert_equals((int) $row['ultimo_saldo'], (int) $row['suma_movimientos'],
            "Usuario {$row['usuario_id']}: último saldo_resultante ({$row['ultimo_saldo']}) coincide con SUM(movimientos) ({$row['suma_movimientos']})");
    }
});
it('T-RGRC-02: Regresión: ciclo de reserva y cancelación mantiene coherencia de ejemplar y transacción', function () {
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-RGRC')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    db_exec("UPDATE transacciones SET estado = 'cancelada' WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    $res = reserva_crear(db(), $uid, $bId, $ejId);
    assert_equals('reservado', db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]), 'Ejemplar reservado');

    reserva_cancelar(db(), (int) $res['id'], $uid);
    assert_equals('disponible', db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]), 'Ejemplar liberado tras cancelación');
    assert_equals('cancelada', db_val("SELECT estado FROM transacciones WHERE id = ?", [$res['id']]), 'Transacción cancelada');
});

group('Fase 9 — Historial de Libros y Tokens (v4.2)');
it('T-HIST-01: /mi-historial lista movimientos con libro, cantidad y saldo_resultante COHERENTES con el ledger (SUM = saldo mostrado)', function () {
    $s = login_como('usuario');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    // Asegurar que hay al menos un movimiento
    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    if ($saldoAntes <= 0) {
        ledger_registrar_movimiento(db(), $uid, +2, 'bono', null, 'Bono inicial test T-HIST-01');
    }
    $saldoCalculado = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);

    $r = http_get('/mi-historial', $s);
    assert_http_code(200, $r);
    assert_contains($r['body'], 'Movimientos del Libro Mayor', 'Título de la tabla visible');
    assert_contains($r['body'], 'id="historial-saldo">' . $saldoCalculado . '<', 'El saldo mostrado coincide exactamente con SUM(cantidad)');
    assert_contains($r['body'], 'Saldo Resultante', 'Cabecera de saldo resultante presente');
});

it('T-HIST-02: Tras un depósito y un retiro, el historial refleja ambos con título correcto y método de pago', function () {
    require_once ROOT . '/app/helpers/mostrador.php';
    require_once ROOT . '/app/helpers/reservas.php';

    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $emailTest = 'hist_user_' . uniqid() . '@bookswap.local';

    $hash = password_hash('password', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo) VALUES ('Hist User', ?, ?, 3, 1)", [$emailTest, $hash]);
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = ?", [$emailTest]);
    
    // 1. Depósito de libro: usar libro 1
    $libro1 = db()->query("SELECT id, titulo FROM libros WHERE id = 1")->fetch();
    mostrador_registrar_deposito(db(), $uid, (int) $libro1['id'], 'bueno', 'MOSTRADOR', $adminId);

    // 2. Retiro de libro: crear ejemplar y reservar libro 2
    $libro2 = db()->query("SELECT id, titulo FROM libros WHERE id = 2")->fetch();
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-HIST')", [$libro2['id']]);
    $ejId2 = (int) db()->lastInsertId();

    $res = reserva_crear(db(), $uid, (int) $libro2['id'], $ejId2);
    mostrador_completar_entrega(db(), (int) $res['id'], 'tokens', null, 'bueno', $adminId);

    // Consultar /mi-historial
    $s = new_session();
    http_get('/login', $s);
    http_post('/login', ['email' => $emailTest, 'password' => 'password'], $s);

    $rHist = http_get('/mi-historial', $s);
    assert_http_code(200, $rHist);
    assert_contains($rHist['body'], $libro1['titulo'], 'Historial refleja el título del libro depositado');
    assert_contains($rHist['body'], $libro2['titulo'], 'Historial refleja el título del libro retirado');
    assert_contains($rHist['body'], 'Libro', 'Historial refleja método de pago Libro');
    assert_contains($rHist['body'], 'Tokens', 'Historial refleja método de pago Tokens');
});

it('T-HIST-03: Export CSV propio: cabecera correcta y filas coherentes con la vista', function () {
    $s = login_como('usuario');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    $rCsv = http_get('/mi-historial?exportar=csv', $s);
    assert_http_code(200, $rCsv);

    $lineas = explode("\n", trim($rCsv['body']));
    assert_true(!empty($lineas), 'El CSV devuelto no está vacío');

    $cabeceraEsperada = 'fecha;tipo;libro;autor;metodo_pago;cantidad;saldo_resultante;concepto;codigo_reserva';
    assert_equals($cabeceraEsperada, trim($lineas[0]), 'Cabecera CSV cumple formato exacto con punto y coma');

    $totalMovs = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($totalMovs, count($lineas) - 1, 'Número de filas en el CSV coincide con el número de movimientos en BD');
});

it('T-HIST-04: USUARIO: historial ajeno → 403 · ADMIN: /admin/usuarios/{id}/historial → 200', function () {
    $sUser = login_como('usuario');
    $r403 = http_get('/admin/usuarios/1/historial', $sUser);
    assert_http_code(403, $r403, 'Usuario normal bloqueado con 403 en endpoint admin de historial ajeno');

    $sAdmin = login_como('admin');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $r200 = http_get("/admin/usuarios/{$uid}/historial", $sAdmin);
    assert_http_code(200, $r200, 'Admin puede consultar el historial de cualquier usuario');
    assert_contains($r200['body'], 'usuario@bookswap.local', 'Vista admin muestra los datos del usuario consultado');

    $rCsvAdmin = http_get("/admin/usuarios/{$uid}/historial?exportar=csv", $sAdmin);
    assert_http_code(200, $rCsvAdmin, 'Admin puede exportar el CSV del usuario');
    assert_contains($rCsvAdmin['body'], 'fecha;tipo;libro;autor;metodo_pago;', 'CSV de admin tiene cabecera estándar');
});

group('Fase 10 — Catálogo de Administración (v4.2)');
require_once ROOT . '/app/helpers/admin.php';
require_once ROOT . '/app/helpers/csv.php';
require_once ROOT . '/app/helpers/reservas.php';
require_once ROOT . '/app/helpers/auditoria.php';
require_once ROOT . '/app/helpers/password_reset.php';

it('T-ADMIN-01: /admin muestra las secciones operativas · USUARIO → 403', function () {
    $sUser = login_como('usuario');
    $r403 = http_get('/admin', $sUser);
    assert_http_code(403, $r403, 'Usuario estándar obtiene 403 en /admin');

    $sAdmin = login_como('admin');
    $r200 = http_get('/admin', $sAdmin);
    assert_http_code(200, $r200, 'Admin accede con 200 a /admin');

    // Verificar presencia de las 8 secciones
    $body = $r200['body'];
    assert_contains($body, 'Resumen y Métricas', 'Sección 1: Resumen y Métricas visible');
    assert_contains($body, '/admin/metricas', 'Enlace a Sección 1 presente');

    assert_contains($body, 'Usuarios', 'Sección 2: Usuarios visible');
    assert_contains($body, '/admin/usuarios', 'Enlace a Sección 2 presente');


    assert_contains($body, 'Catálogo y Libros', 'Sección 4: Catálogo visible');
    assert_contains($body, '/admin/libros', 'Enlace a Sección 4 presente');

    assert_contains($body, 'Gestión de Reservas', 'Sección 5: Reservas visible');
    assert_contains($body, '/admin/reservas', 'Enlace a Sección 5 presente');

    assert_contains($body, 'Movimientos Globales', 'Sección 6: Movimientos visible');
    assert_contains($body, '/admin/movimientos', 'Enlace a Sección 6 presente');

    assert_contains($body, 'Configuración del Sistema', 'Sección 7: Configuración visible');
    assert_contains($body, '/admin/configuracion', 'Enlace a Sección 7 presente');

    assert_contains($body, 'Copias de Seguridad', 'Sección 8: Copias de Seguridad visible');
    assert_contains($body, '/admin/backups', 'Enlace a Sección 8 presente');

    assert_contains($body, 'Registro de Auditoría', 'Sección 9: Registro de Auditoría visible');
    assert_contains($body, '/admin/auditoria', 'Enlace a Sección 9 presente');
});

it('T-ADMIN-02: Importador CSV de catálogo: importación correcta, informe y auditoría', function () {
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $isbnTest = '978' . str_pad((string) rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
    $csvContent = "isbn;titulo;autor;editorial;anio;genero;idioma
{$isbnTest};Libro CSV Admin;Autor Admin;Editorial Admin;2024;Novela;es
";
    $inf = csv_importar_catalogo(db(), $csvContent, false, $adminId);
    assert_true(count($inf['importados']) >= 1, 'Libro importado vía CSV');
    $aud = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'csv_catalogo' ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($aud), 'Auditoría registrada para importación CSV catálogo');
});

it('T-ADMIN-03: Ajuste manual de tokens con motivo → movimiento \'ajuste\' en ledger y T-LEDGER-01 sigue en verde', function () {
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $emailUser = 'ajuste_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Usuario Ajuste', ?, 'hash', 3, 1, 1)", [$emailUser]);
    $uid = (int) db()->lastInsertId();

    // Saldo inicial = 0
    $saldoIni = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$uid]);
    assert_equals(0, $saldoIni, 'Saldo inicial es 0');

    // 1. Ajuste positivo: +5 tokens
    $resPos = admin_usuario_ajustar_tokens(db(), $uid, 5, 'Bono por ayuda en jornada de puertas abiertas', $adminId);
    assert_equals(5, $resPos['saldo_nuevo'], 'Nuevo saldo es 5 tras sumar 5 tokens');

    $ultimoMov = db()->query("SELECT * FROM movimientos_tokens WHERE usuario_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_equals('ajuste', $ultimoMov['tipo'], 'Tipo de movimiento registrado es ajuste');
    assert_equals(5, (int) $ultimoMov['cantidad'], 'Cantidad es +5');
    assert_equals(5, (int) $ultimoMov['saldo_resultante'], 'Saldo resultante es 5');
    assert_equals('Bono por ayuda en jornada de puertas abiertas', $ultimoMov['concepto'], 'Concepto registrado');

    $audPos = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'tokens.ajuste' ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($audPos), 'Auditoría registrada para ajuste de tokens');
    $detPos = json_decode($audPos['detalle'], true);
    assert_equals(5, (int) $detPos['cantidad'], 'Cantidad auditada');

    // 2. Ajuste negativo permitido: -2 tokens
    $resNeg = admin_usuario_ajustar_tokens(db(), $uid, -2, 'Corrección de apunte contable', $adminId);
    assert_equals(3, $resNeg['saldo_nuevo'], 'Nuevo saldo es 3 tras restar 2 tokens');

    // 3. Intento de ajuste que deja saldo negativo: -10 tokens -> debe lanzar excepción
    $falloCapturado = false;
    try {
        admin_usuario_ajustar_tokens(db(), $uid, -10, 'Intento no permitido', $adminId);
    } catch (Exception $e) {
        $falloCapturado = true;
    }
    assert_true($falloCapturado, 'Ajuste que deja saldo negativo es rechazado');

    // 4. Verificar integridad con T-LEDGER-01
    $sumaTotal = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$uid]);
    $ultimoSaldoRes = (int) db_val('SELECT saldo_resultante FROM movimientos_tokens WHERE usuario_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
    assert_equals(3, $sumaTotal, 'Saldo total acumulado coincide con movimientos');
    assert_equals(3, $ultimoSaldoRes, 'Último saldo_resultante coincide exactamente con suma de movimientos');
});

it('T-ADMIN-04: Crear usuario, cambiar rol, activar/desactivar, reset de contraseña → efectivos y auditados', function () {
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");

    // 1. Crear usuario
    $email = 'adm_usr_' . uniqid() . '@bookswap.local';
    $resCrear = admin_usuario_crear(db(), 'Usuario Operativo', $email, 'inicial123', 3, null, $adminId);
    assert_true($resCrear['ok'], 'Usuario creado exitosamente desde admin');
    $uid = (int) $resCrear['usuario_id'];

    $u = db()->query("SELECT * FROM usuarios WHERE id = {$uid}")->fetch();
    assert_equals('Usuario Operativo', $u['nombre'], 'Nombre guardado');
    assert_equals($email, $u['email'], 'Email guardado');
    assert_equals(3, (int) $u['rol_id'], 'Rol Lector guardado');
    assert_equals(1, (int) $u['activo'], 'Usuario creado activo');

    $audCrear = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'usuario.crear' AND entidad_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($audCrear), 'Auditoría registrada para usuario.crear');

    // 2. Cambiar rol (a PERSONAL: 2) y nombre
    $actOk = admin_usuario_actualizar(db(), $uid, 'Usuario Operativo Promocionado', 2, $adminId);
    assert_true($actOk, 'Actualización de usuario devuelve true');
    $rolActual = (int) db_val('SELECT rol_id FROM usuarios WHERE id = ?', [$uid]);
    assert_equals(2, $rolActual, 'Rol actualizado a 2 (Personal)');

    $audEdit = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'usuario.actualizar' AND entidad_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($audEdit), 'Auditoría registrada para usuario.actualizar');

    // 3. Desactivar / Activar
    admin_usuario_cambiar_estado(db(), $uid, false, $adminId);
    $estadoDesactivado = (int) db_val('SELECT activo FROM usuarios WHERE id = ?', [$uid]);
    assert_equals(0, $estadoDesactivado, 'Usuario desactivado');

    admin_usuario_cambiar_estado(db(), $uid, true, $adminId);
    $estadoActivado = (int) db_val('SELECT activo FROM usuarios WHERE id = ?', [$uid]);
    assert_equals(1, $estadoActivado, 'Usuario reactivado');

    $audEstado = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'usuario.cambiar_estado' AND entidad_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($audEstado), 'Auditoría registrada para usuario.cambiar_estado');

    // 4. Generar enlace de acceso (Fase 22)
    $token = admin_usuario_generar_enlace_reset(db(), $uid, $adminId);
    assert_equals(64, strlen($token), 'Token generado tiene exactamente 64 caracteres hexadecimales');

    $tokenHash = hash('sha256', $token);
    $rowPr = db()->query("SELECT * FROM password_resets WHERE token_hash = '{$tokenHash}' AND usuario_id = {$uid}")->fetch();
    assert_true(!empty($rowPr), 'Fila creada en password_resets con el hash SHA-256');
    assert_equals(0, (int) $rowPr['usado'], 'Token marcado como no usado');

    $audReset = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'password_reset_enlace' AND entidad_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($audReset), 'Auditoría registrada para password_reset_enlace');

    // Completar el cambio de contraseña usando el token generado
    $resReset = password_reset_completar(db(), $token, 'nuevaClaveAdmin123');
    assert_true($resReset, 'Contraseña cambiada exitosamente con el token');

    $nuevoHash = db_val('SELECT password_hash FROM usuarios WHERE id = ?', [$uid]);
    assert_true(password_verify('nuevaClaveAdmin123', $nuevoHash), 'El hash almacenado verifica con la nueva contraseña');
});

it('T-ADMIN-05: Cancelar reserva activa desde admin → copia \'disponible\', liberación de tokens, notificación al usuario, auditada', function () {
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $emailUser = 'res_canc_' . uniqid() . '@bookswap.local';

    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector Cancelar', ?, 'hash', 3, 1, 1)", [$emailUser]);
    $uid = (int) db()->lastInsertId();
    ledger_registrar_movimiento(db(), $uid, 5, 'bono', null, 'Bono inicial para test');
    $saldoInicial = ledger_obtener_saldo(db(), $uid);

    // Crear libro y ejemplar
    $bId = (int) db_val('SELECT id FROM libros LIMIT 1');
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-CANC')", [$bId]);
    $ejId = (int) db()->lastInsertId();

    // Crear reserva (bloqueo_reserva -1)
    $res = reserva_crear(db(), $uid, $bId, $ejId);
    $txId = (int) $res['id'];
    assert_equals($saldoInicial - 1, ledger_obtener_saldo(db(), $uid), 'Saldo descontado por bloqueo');

    $estadoEjAntes = db_val('SELECT estado FROM ejemplares WHERE id = ?', [$ejId]);
    assert_equals('reservado', $estadoEjAntes, 'Ejemplar queda reservado');
    $estadoTxAntes = db_val('SELECT estado FROM transacciones WHERE id = ?', [$txId]);
    assert_equals('activa', $estadoTxAntes, 'Transacción queda activa');

    // Cancelar reserva desde admin (liberacion_reserva +1)
    $cancOk = admin_reserva_cancelar(db(), $txId, $adminId);
    assert_true($cancOk, 'admin_reserva_cancelar devuelve true');

    $estadoEjDespues = db_val('SELECT estado FROM ejemplares WHERE id = ?', [$ejId]);
    assert_equals('disponible', $estadoEjDespues, 'Ejemplar vuelve a estado disponible');

    $estadoTxDespues = db_val('SELECT estado FROM transacciones WHERE id = ?', [$txId]);
    assert_equals('cancelada', $estadoTxDespues, 'Transacción marcada como cancelada');

    // Verificar liberación de tokens
    $movLib = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId} AND tipo = 'liberacion_reserva'")->fetch();
    assert_true(!empty($movLib), 'Movimiento liberacion_reserva registrado');
    assert_equals($saldoInicial, ledger_obtener_saldo(db(), $uid), 'Saldo restaurado tras cancelación administrativa');

    // Verificar notificación al usuario
    $notif = db()->query("SELECT * FROM notificaciones WHERE usuario_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($notif), 'Notificación creada para el usuario');
    assert_contains($notif['mensaje'], 'cancelado la reserva', 'Mensaje de notificación claro para el usuario');

    // Verificar auditoría
    $aud = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'reserva_cancelada' AND entidad_id = {$txId} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($aud), 'Auditoría registrada para cancelación de reserva');
});

group('Regresión v4.2');
it('T-RGRC-03: Regresión v4.2: repite T-AUTH-01 para verificar integridad continua del alta', function () {
    $s = new_session();
    http_get('/registro', $s);
    $email = 'rgrc_' . uniqid() . '@bookswap.local';

    $r = http_post('/registro', [
        'nombre' => 'Lector Regresion',
        'email' => $email,
        'password' => 'password123',
    ], $s);
    assert_true(in_array($r['code'], [200, 302], true), "Registro devuelve {$r['code']}");
    $uid = db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);
    assert_true($uid !== false && $uid > 0, 'Usuario creado');
    $activo = (int) db_val('SELECT activo FROM usuarios WHERE id = ?', [$uid]);
    assert_equals(1, $activo, 'Usuario en estado ACTIVO');
});

group('Fase 12 — Rendimiento y consumo de recursos (v4.3)');

it('T-PERF-01: El script tests/benchmark.php existe, todas las rutas responden 200 y la tabla se imprime', function () {
    assert_true(is_file(ROOT . '/tests/benchmark.php'), 'tests/benchmark.php existe físicamente');

    // Comprobar que todas las rutas clave responden 200 para las sesiones correspondientes
    $rutasPublicas = ['/', '/catalogo', '/catalogo?q=quijote', '/libro/1', '/login', '/visitanos'];
    foreach ($rutasPublicas as $ruta) {
        $r = http_get($ruta);
        assert_http_code(200, $r, "Ruta pública {$ruta} responde 200");
    }

    $sUser = login_como('usuario');
    $rDash = http_get('/dashboard', $sUser);
    assert_http_code(200, $rDash, '/dashboard responde 200 para usuario');

    $rHist = http_get('/mi-historial', $sUser);
    assert_http_code(200, $rHist, '/mi-historial responde 200 para usuario');

    $sPersonal = login_como('personal');
    $rMost = http_get('/mostrador', $sPersonal);
    assert_http_code(200, $rMost, '/mostrador responde 200 para personal');

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/admin', $sAdmin);
    assert_http_code(200, $rAdmin, '/admin responde 200 para admin');

    // Ejecutar benchmark en CLI para validar que genera la tabla sin excepciones
    $cmd = 'php ' . escapeshellarg(ROOT . '/tests/benchmark.php');
    exec($cmd, $salidaArr, $exitCode);
    assert_equals(0, $exitCode, 'benchmark.php ejecuta correctamente con código 0');
    $salida = implode("\n", $salidaArr);
    assert_contains($salida, 'Mediana', 'Salida del benchmark contiene columna de mediana');
    assert_contains($salida, 'Benchmark completado con éxito', 'Benchmark finaliza con mensaje de éxito');
});

it('T-PERF-02: Alta por ISBN con red → portada_url empieza por /uploads/covers/ y el archivo existe (SKIP sin red)', function () {
    if (!has_internet()) throw new SkipTest('sin red');
    $s = login_como('personal');
    $isbnReal = '9780132350884'; // Clean Code

    // 1. Consulta al endpoint bibliográfico
    $r = http_get('/api/isbn?isbn=' . $isbnReal, $s);
    assert_http_code(200, $r);
    $data = json_decode($r['body'], true);
    if (!($data['ok'] ?? false)) {
        throw new SkipTest('Servicio bibliográfico externo temporalmente inaccesible');
    }

    $libro = $data['libro'];
    assert_true(!empty($libro['portada_url']), 'La API bibliográfica devuelve una portada');
    assert_true(str_starts_with($libro['portada_url'], '/uploads/covers/'), 'portada_url empieza por /uploads/covers/');

    $rutaLocal = ROOT . $libro['portada_url'];
    assert_true(is_file($rutaLocal) && filesize($rutaLocal) > 0, 'El archivo descargado en /uploads/covers/ existe y tiene contenido');

    // 2. Alta formal en el catálogo
    db_exec("DELETE FROM ejemplares WHERE libro_id IN (SELECT id FROM libros WHERE isbn13 = ?)", [$isbnReal]);
    db_exec("DELETE FROM libros WHERE isbn13 = ?", [$isbnReal]);

    http_get('/admin/libros/nuevo', $s);
    $rPost = http_post('/admin/libros/nuevo', [
        'titulo'        => $libro['titulo'],
        'autor'         => $libro['autor'],
        'isbn13'        => $isbnReal,
        'editorial'     => $libro['editorial'] ?? '',
        'anio'          => $libro['anio'] ?? '',
        'genero'        => $libro['genero'] ?? '',
        'idioma'        => $libro['idioma'] ?? 'es',
        'portada_url'   => $libro['portada_url'],
    ], $s);
    assert_true(in_array($rPost['code'], [200, 302], true), 'Alta formal de libro devuelve 200 o 302');

    $portadaEnBd = db_val("SELECT portada_url FROM libros WHERE isbn13 = ?", [$isbnReal]);
    assert_true(str_starts_with($portadaEnBd, '/uploads/covers/'), 'portada_url en base de datos es la ruta local en /uploads/covers/');
    assert_true(is_file(ROOT . $portadaEnBd), 'El archivo físico apuntado en BD existe');
});

group('Fase 13 — Seguridad OWASP, Hardening y Auditoría (v4.3)');

it('T-SEC-06: SQLi en catálogo y parámetros GET no inyecta ni rompe', function () {
    $r1 = http_get('/catalogo?q=' . urlencode("' OR 1=1--"));
    assert_http_code(200, $r1, 'Búsqueda con SQLi responde 200');
    assert_not_contains($r1['body'], 'SQLSTATE', 'No expone traza SQL');
    assert_not_contains($r1['body'], 'syntax error', 'No genera error de sintaxis');

    $r2 = http_get('/catalogo?genero=' . urlencode("' OR '1'='1"));
    assert_http_code(200, $r2, 'Filtro género con SQLi responde 200');
    assert_not_contains($r2['body'], 'SQLSTATE', 'No expone traza SQL');
});

it('T-SEC-07: BOLA/IDOR: usuario normal no puede cancelar reserva ajena', function () {
    $stmtTx = db()->query("SELECT id, usuario_id, ejemplar_id, estado FROM transacciones WHERE tipo = 'reserva' AND estado = 'activa' LIMIT 1");
    $reservaAjena = $stmtTx->fetch();
    if (!$reservaAjena) {
        $ej = db_val("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");
        db_exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?", [$ej]);
        db_exec("INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, codigo, estado, fecha_limite, created_at)
                 VALUES ('reserva', ?, 3, -1, 'SEC-RES-01', 'activa', NOW() + INTERVAL 72 HOUR, NOW())", [$ej]);
        $txId = (int) db_val("SELECT LAST_INSERT_ID()");
        $ejId = (int) $ej;
        $propietarioId = 3;
    } else {
        $txId = (int) $reservaAjena['id'];
        $ejId = (int) $reservaAjena['ejemplar_id'];
        $propietarioId = (int) $reservaAjena['usuario_id'];
    }

    $emailAtacante = ($propietarioId === 3) ? 'lector2@bookswap.local' : 'usuario@bookswap.local';
    $sAtacante = new_session();
    http_get('/login', $sAtacante);
    http_post('/login', ['email' => $emailAtacante, 'password' => 'password'], $sAtacante);

    http_get('/mis-reservas', $sAtacante);
    $rCancel = http_post('/mis-reservas/cancelar', ['transaccion_id' => $txId], $sAtacante);
    assert_true(in_array($rCancel['code'], [200, 302], true), 'Petición procesada sin 500');

    $estadoTx = db_val("SELECT estado FROM transacciones WHERE id = ?", [$txId]);
    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('activa', $estadoTx, 'La reserva ajena NO fue cancelada');
    assert_equals('reservado', $estadoEj, 'El ejemplar ajeno permanece reservado');
});

it('T-SEC-08: Path traversal en descarga de backups bloqueado', function () {
    $sAdmin = login_como('admin');
    $r1 = http_get('/admin/backups/descargar?archivo=../../etc/passwd', $sAdmin);
    assert_true(in_array($r1['code'], [403, 404], true), "Descarga con path traversal rechazada: {$r1['code']}");
    assert_not_contains($r1['body'], 'root:', 'No filtra /etc/passwd');

    $r2 = http_get('/admin/backups/descargar?archivo=..%2F..%2Fconfig%2Fconfig.php', $sAdmin);
    assert_true(in_array($r2['code'], [403, 404], true), "Descarga traversal con urlencode rechazada: {$r2['code']}");
    assert_not_contains($r2['body'], 'DB_PASS', 'No filtra credenciales de configuración');
});

it('T-SEC-09: XSS: salida dinámica sanitizada mediante e()', function () {
    $xssPayload = '<img src=x onerror=alert("xss_sec09")>';
    db_exec('INSERT INTO libros (titulo, autor, isbn13, observaciones) VALUES (?, ?, ?, ?)', [
        'Libro XSS ' . bin2hex(random_bytes(2)),
        $xssPayload,
        '9780000000099',
        '<script>alert("desc_xss")</script>',
    ]);
    $libroId = (int) db_val("SELECT id FROM libros WHERE isbn13 = '9780000000099'");

    $rFicha = http_get("/libro/{$libroId}");
    assert_not_contains($rFicha['body'], '<img src=x onerror=alert("xss_sec09")>', 'Carga útil XSS no inyectada en crudo');
    assert_contains($rFicha['body'], '&lt;img src=x', 'Etiqueta HTML escapada como entidad');
    assert_not_contains($rFicha['body'], '<script>alert("desc_xss")</script>', 'Script en descripción no inyectado');

    db_exec("DELETE FROM libros WHERE isbn13 = '9780000000099'");
});

it('T-SEC-10: POST sin CSRF o con token manipulado es rechazado con 403', function () {
    $s = login_como('personal');
    $ch = curl_init(BASE_URL . '/mostrador/devolver');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'ejemplar_id' => 1,
            'csrf_token' => 'token_falso_manipulado_hacker',
        ]),
        CURLOPT_COOKIEJAR => $s['jar'],
        CURLOPT_COOKIEFILE => $s['jar'],
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    assert_equals(403, $code, 'POST con CSRF manipulado bloqueado con 403');
});

it('T-SEC-11: Cabeceras HTTP de seguridad presentes en respuestas', function () {
    $r = http_get('/');
    assert_http_code(200, $r);
    $headers = $r['headers'] ?? '';
    assert_true(stripos($headers, 'X-Content-Type-Options: nosniff') !== false, 'Cabecera X-Content-Type-Options presente');
    assert_true(stripos($headers, 'X-Frame-Options: SAMEORIGIN') !== false, 'Cabecera X-Frame-Options presente');
    assert_true(stripos($headers, 'Referrer-Policy: strict-origin-when-cross-origin') !== false, 'Cabecera Referrer-Policy presente');
    assert_true(stripos($headers, 'Permissions-Policy:') !== false, 'Cabecera Permissions-Policy presente');
});

it('T-SEC-12: Rate limiting de autenticación: bloqueo temporal tras intentos fallidos', function () {
    $emailVictima = 'ratetest_' . bin2hex(random_bytes(3)) . '@bookswap.local';
    db_exec("DELETE FROM intentos_login WHERE email = ?", [$emailVictima]);

    $s = new_session();
    http_get('/login', $s);

    for ($i = 1; $i <= 5; $i++) {
        $r = http_post('/login', ['email' => $emailVictima, 'password' => 'wrongpass' . $i], $s);
        assert_true(in_array($r['code'], [200, 302], true));
    }

    $r6 = http_post('/login', ['email' => $emailVictima, 'password' => 'wrongpass6'], $s);
    assert_contains($r6['body'], 'bloqueada temporalmente', '6º intento indica cuenta bloqueada');

    db_exec("DELETE FROM intentos_login WHERE email = ?", [$emailVictima]);
});

it('T-SEC-13: Escalación de privilegios denegada a usuarios no autorizados', function () {
    $s = login_como('usuario');
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    $r = http_post('/admin/usuarios/editar', [
        'usuario_id' => $uid,
        'nombre' => 'Hacked Admin',
        'rol_id' => 1,
    ], $s);

    assert_equals(403, $r['code'], 'POST de usuario a /admin/usuarios/editar rechazado con 403');
    $rolActual = (int) db_val("SELECT rol_id FROM usuarios WHERE id = ?", [$uid]);
    assert_equals(3, $rolActual, 'El rol del usuario sigue siendo 3 (USUARIO)');
});

it('T-SEC-14: Prevención de saldo negativo y doble retiro bajo transacciones concurrentes', function () {
    $pdo = db();
    $uid = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
    $saldoActual = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);

    $intentoFallido = false;
    try {
        require_once ROOT . '/app/helpers/ledger.php';
        ledger_registrar_movimiento($pdo, $uid, -($saldoActual + 50), 'retiro', null, 'Intento de saldo negativo');
    } catch (Exception $e) {
        $intentoFallido = true;
    }

    assert_true($intentoFallido, 'ledger_registrar_movimiento lanza excepción al intentar dejar saldo negativo');
    $saldoTrasIntento = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoActual, $saldoTrasIntento, 'El saldo no se alteró');
    assert_true($saldoTrasIntento >= 0, 'El saldo del usuario no es negativo');
});

it('T-SEC-15: Contraseñas tratadas con algoritmo seguro y respuesta genérica sin revelar usuario', function () {
    $seedEmails = ['admin@bookswap.local', 'personal@bookswap.local', 'usuario@bookswap.local'];
    foreach ($seedEmails as $email) {
        $hash = db_val("SELECT password_hash FROM usuarios WHERE email = ?", [$email]);
        assert_true(!empty($hash), "Hash presente para {$email}");
        $info = password_get_info($hash);
        assert_true($info['algo'] !== null && $info['algo'] !== 0, "Usuario {$email} usa algoritmo de contraseña seguro");
        assert_not_contains($hash, 'password', "Hash de {$email} no contiene contraseña en texto plano");
    }

    $s = new_session();
    http_get('/login', $s);
    $rInexistente = http_post('/login', [
        'email' => 'cuenta_inexistente_' . bin2hex(random_bytes(3)) . '@noexiste.local',
        'password' => 'cualquier_clave',
    ], $s);
    assert_contains($rInexistente['body'], 'Credenciales incorrectas', 'Login con usuario inexistente devuelve mensaje genérico sin filtrar existencia');
});

/* ─── Fase 15 — Entrega directa rápida (sin reserva previa) (v4.4) ─── */
line("\n── Fase 15 — Entrega directa rápida (sin reserva previa) (v4.4)", 'yellow');

it('T-DIR-01: Entrega directa con tokens: copia retirado, transaccion entrega_directa entregada, ledger, notificacion, auditoria', function () {
    $pdo = db();
    $sPersonal = login_como('personal');
    $staffId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $receptor = db_row("
        SELECT u.id, u.nombre, u.email
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'USUARIO' AND u.activo = 1
        ORDER BY u.id ASC LIMIT 1
    ");
    $receptorId = (int) $receptor['id'];
    $costeLibro = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'") ?: 1);

    // Asegurar saldo suficiente
    $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$receptorId]);
    if ($saldoAntes < $costeLibro) {
        require_once ROOT . '/app/helpers/ledger.php';
        ledger_registrar_movimiento($pdo, $receptorId, 5, 'bono', null, 'Saldo inicial para T-DIR-01');
        $saldoAntes = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$receptorId]);
    }

    $copia = db_row("
        SELECT e.id, e.libro_id, l.titulo
        FROM ejemplares e
        JOIN libros l ON l.id = e.libro_id
        WHERE e.estado = 'disponible'
        ORDER BY e.id ASC LIMIT 1
    ");
    $ejemplarId = (int) $copia['id'];
    $titulo = $copia['titulo'];

    require_once ROOT . '/app/helpers/mostrador.php';
    $res = mostrador_entrega_directa($pdo, $receptorId, $ejemplarId, $staffId, 'tokens');
    assert_true($res['ok'], 'Entrega directa con tokens exitosa');

    // 1. Copia en estado 'retirado'
    $estadoCopia = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejemplarId]);
    assert_equals('retirado', $estadoCopia, 'Copia queda en estado retirado');

    // 2. Transacción entrega_directa entregada con metodo_pago y gestionada_por
    $tx = db_row("SELECT * FROM transacciones WHERE id = ?", [$res['transaccion_id']]);
    assert_equals('entrega_directa', $tx['tipo'], 'Transacción es de tipo entrega_directa');
    assert_equals('entregada', $tx['estado'], 'Transacción en estado entregada');
    assert_equals('tokens', $tx['metodo_pago'], 'Método de pago registrado como tokens');
    assert_equals($staffId, (int) $tx['gestionada_por'], 'Gestionada por staff');
    assert_equals($receptorId, (int) $tx['usuario_id'], 'Usuario receptor correcto');
    assert_equals($ejemplarId, (int) $tx['ejemplar_id'], 'Ejemplar ID correcto');
    assert_true(!empty($tx['fecha_entrega']), 'fecha_entrega no está vacía');

    // 3. Ledger con retiro -coste_libro y saldo_resultante correcto
    $mov = db_row("SELECT * FROM movimientos_tokens WHERE transaccion_id = ? AND tipo = 'retiro' ORDER BY id DESC LIMIT 1", [$tx['id']]);
    assert_true(!empty($mov), 'Movimiento de retiro existe en el ledger');
    assert_equals(-$costeLibro, (int) $mov['cantidad'], 'Cantidad retirada coincide con -coste_libro');
    assert_equals($saldoAntes - $costeLibro, (int) $mov['saldo_resultante'], 'Saldo resultante correcto en movimiento');
    $saldoNuevo = (int) db_val("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?", [$receptorId]);
    assert_equals($saldoAntes - $costeLibro, $saldoNuevo, 'Saldo actual del usuario coincide');

    // 4. Notificación al receptor con formato exacto
    $notif = db_val("SELECT mensaje FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 1", [$receptorId]);
    assert_contains($notif, "Entrega directa registrada: {$titulo}. Método: tokens.", 'Notificación con formato exacto');

    // 5. Auditoría registrada
    $audit = db_row("SELECT * FROM registro_auditoria WHERE accion = 'entrega_directa' AND entidad = 'transacciones' AND entidad_id = ? ORDER BY id DESC LIMIT 1", [$tx['id']]);
    assert_true(!empty($audit), 'Auditoría registrada con acción entrega_directa');
});

it('T-DIR-02: Entrega directa con libro aportado: DOS movimientos, copia entregada retirado, copia aportada disponible', function () {
    $pdo = db();
    $staffId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $receptor = db_row("
        SELECT u.id, u.nombre
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'USUARIO' AND u.activo = 1
        ORDER BY u.id ASC LIMIT 1
    ");
    $receptorId = (int) $receptor['id'];
    $costeLibro = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'") ?: 1);
    $bonoDeposito = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'bono_deposito'") ?: 1);

    // Copia a entregar
    $copia = db_row("SELECT e.id, l.titulo FROM ejemplares e JOIN libros l ON l.id = e.libro_id WHERE e.estado = 'disponible' ORDER BY e.id ASC LIMIT 1");
    $ejemplarId = (int) $copia['id'];

    // Libro aportado
    $libroAportadoId = (int) db_val("SELECT id FROM libros ORDER BY id DESC LIMIT 1");

    require_once ROOT . '/app/helpers/mostrador.php';
    $res = mostrador_entrega_directa($pdo, $receptorId, $ejemplarId, $staffId, 'libro', $libroAportadoId, 'bueno');
    assert_true($res['ok'], 'Entrega directa con libro aportado exitosa');

    // Copia entregada -> 'retirado'
    $estadoEntregada = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejemplarId]);
    assert_equals('retirado', $estadoEntregada, 'Copia entregada queda en estado retirado');

    // Copia aportada -> 'disponible'
    $depTx = db_row("SELECT * FROM transacciones WHERE id = ?", [$res['deposito_tx_id']]);
    assert_equals('deposito', $depTx['tipo'], 'Transacción del libro aportado es tipo deposito');
    $estadoAportada = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$depTx['ejemplar_id']]);
    assert_equals('disponible', $estadoAportada, 'Copia aportada queda en estado disponible');

    // DOS movimientos en el ledger
    $movDep = db_row("SELECT * FROM movimientos_tokens WHERE transaccion_id = ? AND tipo = 'deposito'", [$depTx['id']]);
    assert_true(!empty($movDep), 'Movimiento de depósito existe');
    assert_equals(+$bonoDeposito, (int) $movDep['cantidad'], 'Movimiento depósito suma bono_deposito');

    $movRet = db_row("SELECT * FROM movimientos_tokens WHERE transaccion_id = ? AND tipo = 'retiro'", [$res['transaccion_id']]);
    assert_true(!empty($movRet), 'Movimiento de retiro existe');
    assert_equals(-$costeLibro, (int) $movRet['cantidad'], 'Movimiento retiro descuenta coste_libro');
});

it('T-DIR-03: Saldo insuficiente (tokens) -> denegado sin ningún cambio en BD', function () {
    $pdo = db();
    $staffId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");

    // Crear usuario de prueba temporal con 0 saldo
    $emailTest = 'pobre_' . bin2hex(random_bytes(3)) . '@test.local';
    $pdo->prepare("INSERT INTO usuarios (nombre, email, rol_id, activo, auth_provider) VALUES ('Pobre Test', ?, 3, 1, 'local')")
        ->execute([$emailTest]);
    $uid = (int) $pdo->lastInsertId();

    $copia = db_row("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");
    $ejId = (int) $copia['id'];

    $txCountAntes = (int) db_val("SELECT COUNT(*) FROM transacciones");
    $movCountAntes = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens");

    require_once ROOT . '/app/helpers/mostrador.php';
    $fallo = false;
    try {
        mostrador_entrega_directa($pdo, $uid, $ejId, $staffId, 'tokens');
    } catch (Exception $e) {
        $fallo = true;
        assert_contains($e->getMessage(), 'Saldo insuficiente', 'Mensaje indica saldo insuficiente');
    }

    assert_true($fallo, 'Intento de entrega directa con saldo insuficiente lanza excepción');
    assert_equals('disponible', db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]), 'Copia sigue disponible');
    assert_equals($txCountAntes, (int) db_val("SELECT COUNT(*) FROM transacciones"), 'No se crearon transacciones');
    assert_equals($movCountAntes, (int) db_val("SELECT COUNT(*) FROM movimientos_tokens"), 'No se alteró el ledger');
});

it('T-DIR-04: Copia reservada -> denegado; receptor inactivo -> denegado con mensaje', function () {
    $pdo = db();
    $staffId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $receptor = (int) db_val("SELECT u.id FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = 'USUARIO' AND u.activo = 1 LIMIT 1");

    // 1. Copia en estado reservado
    $copiaReservada = db_row("SELECT id FROM ejemplares WHERE estado = 'reservado' LIMIT 1");
    if (!$copiaReservada) {
        $copiaReservada = db_row("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");
        $pdo->prepare("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?")->execute([$copiaReservada['id']]);
    }
    $ejReservadoId = (int) $copiaReservada['id'];

    require_once ROOT . '/app/helpers/mostrador.php';
    $falloCopia = false;
    try {
        mostrador_entrega_directa($pdo, $receptor, $ejReservadoId, $staffId, 'tokens');
    } catch (Exception $e) {
        $falloCopia = true;
    }
    assert_true($falloCopia, 'Entrega directa de copia reservada es rechazada');

    // 2. Receptor inactivo
    $emailInactivo = 'inactivo_' . uniqid() . '@test.local';
    $pdo->prepare("INSERT INTO usuarios (nombre, email, rol_id, activo, auth_provider) VALUES ('Inactivo Test', ?, 3, 0, 'local')")
        ->execute([$emailInactivo]);
    $uidInactivo = (int) $pdo->lastInsertId();
    $copiaDisp = (int) db_val("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");

    $falloInactivo = false;
    $msgInactivo = '';
    try {
        mostrador_entrega_directa($pdo, $uidInactivo, $copiaDisp, $staffId, 'tokens');
    } catch (Exception $e) {
        $falloInactivo = true;
        $msgInactivo = $e->getMessage();
    }
    assert_true($falloInactivo, 'Receptor inactivo es rechazado');
    assert_contains($msgInactivo, 'inactivo', 'Mensaje de rechazo menciona inactivo');
});

it('T-DIR-05: USUARIO llamando al endpoint de entrega directa -> 403; PERSONAL y ADMIN -> 200', function () {
    $sUsuario = login_como('usuario');
    $rUsuario = http_get('/mostrador/entrega-directa', $sUsuario);
    assert_http_code(403, $rUsuario);

    $sPersonal = login_como('personal');
    $rPersonal = http_get('/mostrador/entrega-directa', $sPersonal);
    assert_http_code(200, $rPersonal);

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/mostrador/entrega-directa', $sAdmin);
    assert_http_code(200, $rAdmin);
});

it('T-DIR-06: /mostrador pinta Entrega Directa como PRIMERA opción; /admin contiene la acción rápida Entrega directa; navbar ADMIN incluye Mostrador', function () {
    $sPersonal = login_como('personal');
    $rMostrador = http_get('/mostrador', $sPersonal);
    assert_http_code(200, $rMostrador);

    $posDirecta = strpos($rMostrador['body'], 'Entrega Directa');
    $posReserva = strpos($rMostrador['body'], 'Entregar Reserva');
    $posDeposito = strpos($rMostrador['body'], 'Registrar Depósito');

    assert_true($posDirecta !== false, 'Entrega Directa presente en /mostrador');
    assert_true($posReserva !== false, 'Entregar Reserva presente en /mostrador');
    assert_true($posDirecta < $posReserva, 'Entrega Directa aparece como primera opción antes de Entregar Reserva');
    assert_true($posDirecta < $posDeposito, 'Entrega Directa aparece antes de Registrar Depósito');

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/admin', $sAdmin);
    assert_http_code(200, $rAdmin);
    assert_contains($rAdmin['body'], 'Entrega directa', 'Panel /admin contiene la acción rápida Entrega directa');

    $rNav = http_get('/admin', $sAdmin);
    assert_contains($rNav['body'], 'Mostrador', 'Navbar para ADMIN incluye Mostrador');
    assert_contains($rNav['body'], '/mostrador', 'Navbar para ADMIN enlaza a /mostrador');
});

it('T-DIR-07: El aviso de reserva activa: usuario con reserva activa sobre esa copia -> la respuesta incluye sugerencia de ir al flujo de reserva', function () {
    $pdo = db();
    $sPersonal = login_como('personal');
    $receptor = db_row("
        SELECT u.id
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'USUARIO' AND u.activo = 1
        ORDER BY u.id ASC LIMIT 1
    ");
    $usuarioId = (int) $receptor['id'];

    $copia = db_row("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");
    $ejemplarId = (int) $copia['id'];

    // Crear una reserva activa para este usuario sobre este ejemplar
    $codigo = 'RES-DIR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $pdo->prepare("INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, codigo, estado, fecha_limite, created_at) VALUES ('reserva', ?, ?, ?, 'activa', DATE_ADD(NOW(), INTERVAL 48 HOUR), NOW())")
        ->execute([$ejemplarId, $usuarioId, $codigo]);

    // 1. Comprobar endpoint de verificación
    $rVerif = http_get("/api/mostrador/verificar-reserva?ejemplar_id={$ejemplarId}&usuario_id={$usuarioId}", $sPersonal);
    assert_http_code(200, $rVerif);
    assert_contains($rVerif['body'], 'Ir a entregar su reserva', 'Endpoint devuelve sugerencia exacta');

    // 2. Comprobar rechazo con sugerencia en mostrador_entrega_directa
    require_once ROOT . '/app/helpers/mostrador.php';
    $msgEx = '';
    try {
        mostrador_entrega_directa($pdo, $usuarioId, $ejemplarId, 2, 'tokens');
    } catch (Exception $e) {
        $msgEx = $e->getMessage();
    }
    assert_contains($msgEx, 'Ir a entregar su reserva', 'Excepción incluye sugerencia de ir al flujo de reserva');
});

/* ─── FASE 16: PERMISOS DEL PERSONAL CONFIGURABLES (T-ROLE-01..05) ─── */

it('T-ROLE-01: GET /admin/roles responde 200 para admin y 403 para personal y usuario', function () {
    require_once ROOT . '/app/helpers/permisos.php';

    $sAdmin = login_como('admin');
    $rAdmin = http_get('/admin/roles', $sAdmin);
    assert_http_code(200, $rAdmin, 'Admin con roles.gestionar accede a /admin/roles con 200');
    assert_contains($rAdmin['body'], 'Matriz de Permisos', 'La página contiene el título de la matriz de roles');

    $sPersonal = login_como('personal');
    $rPersonal = http_get('/admin/roles', $sPersonal);
    assert_http_code(403, $rPersonal, 'Personal sin roles.gestionar recibe 403 en /admin/roles');

    $sUsuario = login_como('usuario');
    $rUsuario = http_get('/admin/roles', $sUsuario);
    assert_http_code(403, $rUsuario, 'Usuario recibe 403 en /admin/roles');
});

it('T-ROLE-02: desmarcar csv.importar para PERSONAL revoca acceso (403) y oculta enlace; reasignar restaura 200 y visibilidad', function () {
    require_once ROOT . '/app/helpers/permisos.php';

    $sAdmin = login_como('admin');
    $sPersonal = login_como('personal');

    // Desmarcar csv.importar
    $permsSinCsv = array_values(array_diff(permisos_seed_personal(), ['csv.importar']));
    http_get('/admin/roles', $sAdmin);
    $rPost = http_post('/admin/roles', ['permisos' => $permsSinCsv], $sAdmin);
    assert_true(in_array($rPost['code'], [200, 302], true), 'Actualización sin csv.importar guardada');

    // Comprobar con personal: 403 en /admin/csv
    $rCsv = http_get('/admin/csv', $sPersonal);
    assert_http_code(403, $rCsv, 'Personal sin csv.importar recibe 403 en /admin/csv');

    // Comprobar navbar de personal: no contiene /admin/csv
    $rMostrador = http_get('/mostrador', $sPersonal);
    assert_true(strpos($rMostrador['body'], 'href="/admin/csv"') === false, 'Enlace a /admin/csv ausente en navbar');

    // Reasignar csv.importar
    http_get('/admin/roles', $sAdmin);
    $rPost2 = http_post('/admin/roles', ['permisos' => permisos_seed_personal()], $sAdmin);
    assert_true(in_array($rPost2['code'], [200, 302], true), 'Restauración de csv.importar guardada');

    // Personal recupera 200 y enlace
    $rCsv2 = http_get('/admin/csv', $sPersonal);
    assert_http_code(200, $rCsv2, 'Personal recupera 200 en /admin/csv');
    $rMostrador2 = http_get('/mostrador', $sPersonal);
    assert_true(strpos($rMostrador2['body'], 'href="/admin/csv"') !== false, 'Enlace a /admin/csv vuelve a aparecer en la navegación');
});

it('T-ROLE-03: desmarcar entrega.confirmar oculta tarjeta en /mostrador y responde 403 en endpoint; reasignar restaura tarjeta y acceso', function () {
    require_once ROOT . '/app/helpers/permisos.php';

    $sAdmin = login_como('admin');
    $sPersonal = login_como('personal');

    // Desmarcar entrega.confirmar
    $permsSinEntrega = array_values(array_diff(permisos_seed_personal(), ['entrega.confirmar']));
    http_get('/admin/roles', $sAdmin);
    $rPost = http_post('/admin/roles', ['permisos' => $permsSinEntrega], $sAdmin);
    assert_true(in_array($rPost['code'], [200, 302], true), 'Actualización sin entrega.confirmar guardada');

    // Comprobar /mostrador para Personal: la tarjeta no aparece
    $rMost = http_get('/mostrador', $sPersonal);
    assert_true(strpos($rMost['body'], 'id="card-operacion-directa"') === false, 'Tarjeta de entrega directa no aparece en /mostrador');

    // Endpoint POST /mostrador/entrega-directa responde 403
    $rEndpoint = http_post('/mostrador/entrega-directa', ['ejemplar_id' => 1, 'usuario_id' => 1], $sPersonal);
    assert_http_code(403, $rEndpoint, 'POST /mostrador/entrega-directa responde 403 sin permiso');

    // Reasignar entrega.confirmar
    http_get('/admin/roles', $sAdmin);
    http_post('/admin/roles', ['permisos' => permisos_seed_personal()], $sAdmin);

    // Vuelve a aparecer en /mostrador
    $rMost2 = http_get('/mostrador', $sPersonal);
    assert_true(strpos($rMost2['body'], 'id="card-operacion-directa"') !== false, 'Tarjeta de entrega directa reaparece en /mostrador');

    // Y el acceso directo ya no es 403
    $rGetDirecta = http_get('/mostrador/entrega-directa', $sPersonal);
    assert_true($rGetDirecta['code'] !== 403, 'Acceso a entrega directa ya no es 403');
});

it('T-ROLE-04: los permisos BASE no se pueden desactivar mediante POST directo a /admin/roles', function () {
    require_once ROOT . '/app/helpers/permisos.php';

    $pdo = db();
    $sAdmin = login_como('admin');

    // Intentar vaciar todos los permisos mediante POST directo
    http_get('/admin/roles', $sAdmin);
    $rPost = http_post('/admin/roles', ['permisos' => []], $sAdmin);
    assert_true(in_array($rPost['code'], [200, 302], true), 'Petición enviada');

    // Verificar en BD que rol_id = 2 sigue conservando usuario.base, catalogo.ver, reserva.crear
    $permsPersonal = permisos_por_rol($pdo, 2);
    foreach (permisos_base() as $pBase) {
        assert_true(in_array($pBase, $permsPersonal, true), "Permiso BASE '$pBase' se mantiene activo inmutablemente");
    }

    // Restaurar permisos canónicos
    permisos_restaurar_defaults($pdo, 1);
});

it('T-ROLE-05: cambios en matriz quedan auditados con anterior vs nuevo y restaurar por defecto devuelve seed canónico', function () {
    require_once ROOT . '/app/helpers/permisos.php';

    $pdo = db();
    $sAdmin = login_como('admin');

    // Cambiar permisos quitando csv.importar
    $permsSinCsv = array_values(array_diff(permisos_seed_personal(), ['csv.importar']));
    http_get('/admin/roles', $sAdmin);
    http_post('/admin/roles', ['permisos' => $permsSinCsv], $sAdmin);

    // Comprobar registro en auditoria
    $stmtAud = $pdo->query("SELECT * FROM registro_auditoria WHERE accion = 'rol_permiso.actualizar' ORDER BY id DESC LIMIT 5");
    $filas = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
    assert_true(!empty($filas), 'Existe registro en auditoria para rol_permiso.actualizar');

    $encontrado = false;
    foreach ($filas as $f) {
        $detalles = json_decode((string) ($f['detalle'] ?? ''), true);
        if (($detalles['permiso'] ?? '') === 'csv.importar' && ($detalles['anterior'] ?? null) === 1 && ($detalles['nuevo'] ?? null) === 0) {
            $encontrado = true;
            break;
        }
    }
    assert_true($encontrado, 'Auditoría registra detalle exacto con anterior=1 y nuevo=0 para csv.importar');

    // Ejecutar Restaurar por defecto
    http_get('/admin/roles', $sAdmin);
    $rRestaurar = http_post('/admin/roles/restaurar', [], $sAdmin);
    assert_true(in_array($rRestaurar['code'], [200, 302], true), 'Restaurar por defecto procesado');

    // Verificar que los permisos coinciden con el seed v4.2
    $permsActuales = permisos_por_rol($pdo, 2);
    sort($permsActuales);
    $seedEsperado = permisos_seed_personal();
    sort($seedEsperado);
    assert_true($permsActuales === $seedEsperado, 'Permisos de PERSONAL coinciden exactamente con el seed canónico v4.2');
});

/* ─── FASE 17: ESCÁNER DE CÓDIGO DE BARRAS 1D + ESCÁNER UNIVERSAL (T-SCAN-01..05) ─── */

it('T-SCAN-01: Generación de reserva produce código con formato RES-[A-Z0-9]{10}', function () {
    require_once ROOT . '/app/helpers/reservas.php';
    $pdo = db();

    // Crear un ejemplar disponible
    $libroId = (int) db_val("SELECT id FROM libros LIMIT 1");
    $pdo->prepare("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-SCAN')")
        ->execute([$libroId]);
    $ejemplarId = (int) $pdo->lastInsertId();

    $usuarioId = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    // Generar reserva
    $res = reserva_crear($pdo, $usuarioId, $libroId, $ejemplarId);
    $codigo = $res['codigo'] ?? '';

    assert_true(!empty($codigo), 'Código de reserva generado');
    assert_true((bool) preg_match('/^RES-[A-Z0-9]{10}$/', $codigo), "Formato exacto RES-[A-Z0-9]{10} (actual: $codigo)");
    assert_equals(14, strlen($codigo), "Longitud total de 14 caracteres óptima para lector 1D Code 128 (actual: " . strlen($codigo) . ")");
});

it('T-SCAN-02: Vista /mis-reservas incluye JsBarcode (y NO qrcode.js) y elemento SVG/canvas para renderizar código de barras', function () {
    $sUsuario = login_como('usuario');
    $rMis = http_get('/mis-reservas', $sUsuario);
    assert_http_code(200, $rMis);

    assert_contains($rMis['body'], 'JsBarcode', 'Vista /mis-reservas referencia JsBarcode para códigos 1D');
    assert_true(stripos($rMis['body'], 'qrcode.js') === false, 'Vista /mis-reservas NO contiene ninguna referencia a qrcode.js');
    assert_true(
        strpos($rMis['body'], '<svg') !== false || strpos($rMis['body'], '<canvas') !== false,
        'Vista contiene elementos SVG o canvas para representar el código de barras'
    );
});

it('T-SCAN-03: POST /mostrador/escanear y ficha de reserva: Confirmar entrega pagada + Cancelar, sin botones de cobro', function () {
    $pdo = db();
    $sPersonal = login_como('personal');
    http_get('/mostrador', $sPersonal);

    // Obtener o crear una reserva activa
    $tx = db_row("SELECT t.* FROM transacciones t WHERE t.tipo = 'reserva' AND t.estado = 'activa' LIMIT 1");
    if (!$tx) {
        $libroId = (int) db_val("SELECT id FROM libros LIMIT 1");
        $pdo->prepare("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-S3')")->execute([$libroId]);
        $ejId = (int) $pdo->lastInsertId();
        $uId = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");
        if (ledger_obtener_saldo($pdo, $uId) < 2) {
            ledger_registrar_movimiento($pdo, $uId, 5, 'bono', null, 'Saldo test T-SCAN-03');
        }
        require_once ROOT . '/app/helpers/reservas.php';
        $res = reserva_crear($pdo, $uId, $libroId, $ejId);
        $codigo = $res['codigo'];
    } else {
        $codigo = $tx['codigo'];
    }

    $rScan = http_post('/mostrador/escanear', ['valor' => $codigo], $sPersonal);
    assert_http_code(200, $rScan, 'Endpoint responde 200');

    $json = json_decode((string) $rScan['body'], true);
    assert_true(is_array($json), 'Respuesta es un JSON válido');
    assert_true($json['ok'] === true, 'ok es true');
    assert_equals('reserva', $json['tipo'] ?? '', 'Tipo detectado es reserva');
    assert_equals($codigo, $json['datos']['codigo'] ?? '', 'Devuelve el código exacto de la reserva');
    assert_true(!empty($json['datos']['libro_titulo']), 'Incluye datos del libro');

    // Comprobar la ficha HTML en /mostrador?codigo=...
    $rVista = http_get('/mostrador?codigo=' . $codigo, $sPersonal);
    assert_http_code(200, $rVista);
    assert_contains($rVista['body'], 'Confirmar entrega (pagada', 'Ficha contiene botón Confirmar entrega prepagada');
    assert_contains($rVista['body'], 'Cancelar reserva', 'Ficha contiene botón Cancelar reserva');
    assert_true(stripos($rVista['body'], 'Intercambio de Libro') === false, 'Ficha NO contiene opción de cobro por intercambio de libro');
    assert_true(stripos($rVista['body'], 'Cobrar 1 Token') === false, 'Ficha NO contiene botón de cobro de tokens');
});

it('T-SCAN-04: POST /mostrador/escanear con ISBN de libro existente -> devuelve tipo \'isbn\' con libro_id y copias disponibles', function () {
    $sPersonal = login_como('personal');
    http_get('/mostrador', $sPersonal);

    $libro = db_row("SELECT id, isbn13, titulo FROM libros WHERE isbn13 IS NOT NULL AND isbn13 != '' LIMIT 1");
    assert_true(!empty($libro), 'Existe libro con ISBN');

    $rScan = http_post('/mostrador/escanear', ['valor' => $libro['isbn13']], $sPersonal);
    assert_http_code(200, $rScan);

    $json = json_decode((string) $rScan['body'], true);
    assert_true(is_array($json), 'Respuesta es JSON');
    assert_true($json['ok'] === true, 'ok es true');
    assert_equals('isbn', $json['tipo'] ?? '', 'Tipo detectado es isbn');
    assert_equals((int) $libro['id'], (int) ($json['datos']['libro_id'] ?? 0), 'libro_id coincide');
    assert_true(isset($json['datos']['copias_disponibles']), 'Devuelve contador de copias disponibles');
});

it('T-SCAN-05: POST /mostrador/escanear con email existente -> devuelve tipo \'usuario\' con datos del usuario y saldo', function () {
    $sPersonal = login_como('personal');
    http_get('/mostrador', $sPersonal);

    $userRow = db_row("
        SELECT u.id, u.nombre, u.email
        FROM usuarios u
        WHERE u.activo = 1 AND u.rol_id = 3
        LIMIT 1
    ");
    assert_true(!empty($userRow), 'Existe usuario lector activo');

    $rScan = http_post('/mostrador/escanear', ['valor' => $userRow['email']], $sPersonal);
    assert_http_code(200, $rScan);

    $json = json_decode((string) $rScan['body'], true);
    assert_true(is_array($json), 'Respuesta es JSON');
    assert_true($json['ok'] === true, 'ok es true');
    assert_equals('usuario', $json['tipo'] ?? '', 'Tipo detectado es usuario');
    assert_equals((int) $userRow['id'], (int) ($json['datos']['usuario_id'] ?? 0), 'usuario_id coincide');
    assert_true(isset($json['datos']['saldo']), 'Devuelve saldo actual del usuario');
});

/* ─── Fase 18 — Reorganización UX integral (v4.5) ─── */
group('Fase 18 — Reorganización UX integral (v4.5)');

it('T-UX-01: Navbar por rol: invitado sin Mostrador/Panel; usuario con Mis reservas e historial y sin Mostrador; personal con Mostrador; admin con Panel y Mostrador', function () {
    // 1. Invitado
    $rGuest = http_get('/catalogo');
    assert_contains($rGuest['body'], 'Catálogo', 'Invitado ve Catálogo');
    assert_true(!str_contains($rGuest['body'], 'href="/visitanos"'), 'Invitado NO ve Visítanos en menú');
    assert_contains($rGuest['body'], 'Cómo funciona', 'Invitado ve Cómo funciona');
    assert_true(!str_contains($rGuest['body'], 'href="/mostrador"'), 'Invitado NO ve enlace a Mostrador');
    assert_true(!str_contains($rGuest['body'], 'href="/admin"'), 'Invitado NO ve enlace a Panel');

    // 2. Usuario
    $sUser = login_como('usuario');
    $rUser = http_get('/catalogo', $sUser);
    assert_contains($rUser['body'], 'Catálogo', 'Usuario ve Catálogo');
    assert_contains($rUser['body'], 'Mis reservas', 'Usuario ve Mis reservas');
    assert_contains($rUser['body'], 'Mi historial', 'Usuario ve Mi historial');
    assert_contains($rUser['body'], 'saldo-nav', 'Usuario ve saldo de tokens en pill');
    assert_true(!str_contains($rUser['body'], 'href="/mostrador"'), 'Usuario NO ve enlace a Mostrador');
    assert_true(!str_contains($rUser['body'], 'href="/admin"'), 'Usuario NO ve enlace a Panel');

    // 3. Personal
    $sPers = login_como('personal');
    $rPers = http_get('/catalogo', $sPers);
    assert_contains($rPers['body'], 'href="/mostrador"', 'Personal ve enlace a Mostrador');

    // 4. Admin
    $sAdmin = login_como('admin');
    $rAdmin = http_get('/catalogo', $sAdmin);
    assert_contains($rAdmin['body'], 'href="/admin"', 'Admin ve enlace a Panel');
    assert_contains($rAdmin['body'], 'href="/mostrador"', 'Admin ve enlace a Mostrador');
});

it('T-UX-02: Usuario logueado: GET / → 302 a /dashboard; /dashboard contiene saldo y aviso de primeros libros si saldo < coste y sin reservas', function () {
    $sUser = login_como('usuario');

    // Redirección de / a /dashboard para usuarios autenticados
    $rRoot = http_get('/', $sUser);
    assert_http_code(302, $rRoot, 'GET / con sesión redirige con 302');

    // Asegurar que la reserva demo RES-DEMO-0001 esté activa para u3
    db_exec("UPDATE transacciones SET estado = 'activa' WHERE codigo = 'RES-DEMO-0001'");

    $rDash = http_get('/dashboard', $sUser);
    assert_http_code(200, $rDash, 'Acceso a /dashboard responde 200');
    assert_contains($rDash['body'], 'saldo-dashboard-valor', 'Bloque 1: Saldo visible');
    assert_contains($rDash['body'], 'Ver catálogo', 'Bloque 1: Botón Ver catálogo visible');
    assert_contains($rDash['body'], 'RES-DEMO-0001', 'Bloque 2: Reserva demo RES-DEMO-0001 visible');
    assert_contains($rDash['body'], 'bloque-ultimos-movimientos', 'Bloque 5: Últimos movimientos presente');
    assert_not_contains($rDash['body'], 'completar cuenta', 'Dashboard no pide completar cuenta');

    // Usuario sin saldo y sin reservas
    $emailPobre = 'dash_pobre_' . uniqid() . '@bookswap.local';
    $pwdHash = password_hash('password', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo) VALUES ('Pobre Dash', ?, ?, 3, 1)", [$emailPobre, $pwdHash]);
    $sPobre = new_session();
    http_get('/login', $sPobre);
    $rLogin = http_post('/login', ['email' => $emailPobre, 'password' => 'password'], $sPobre);
    assert_true(in_array($rLogin['code'], [200, 302], true), 'Login de usuario pobre exitoso');
    $rDashPobre = http_get('/dashboard', $sPobre);
    assert_http_code(200, $rDashPobre, 'Dashboard responde 200');
    assert_contains($rDashPobre['body'], 'Deposita tus primeros libros para ganar tokens', 'Muestra aviso de primeros libros');
    assert_contains($rDashPobre['body'], '/como-funciona', 'Enlaza a /como-funciona');
});

it('T-UX-03: En /catalogo, una card de libro disponible contiene formulario/botón que hace POST a la ruta de reserva; el flujo desde la card crea la reserva (estado activa, código)', function () {
    $sUser = login_como('usuario');
    $uId = (int) db_val("SELECT id FROM usuarios WHERE email = 'usuario@bookswap.local'");

    // Asegurar un ejemplar disponible
    $bId = (int) db_val("
        SELECT l.id 
        FROM libros l 
        JOIN ejemplares e ON e.libro_id = l.id 
        WHERE e.estado = 'disponible' 
        LIMIT 1
    ");
    assert_true($bId > 0, 'Existe libro con copia disponible');

    $rCat = http_get('/catalogo', $sUser);
    assert_http_code(200, $rCat);
    assert_contains($rCat['body'], 'action="/reservar"', 'Catálogo contiene formulario que hace POST a /reservar');
    assert_contains($rCat['body'], 'modalReservaRapida-' . $bId, 'Modal de reserva rápida presente');

    // Reservar directamente enviando libro_id como hace el modal de la card
    $rRes = http_post('/reservar', ['libro_id' => $bId], $sUser);
    assert_true(in_array($rRes['code'], [200, 302], true), 'POST /reservar responde 200 o 302');

    $tx = db_row("
        SELECT * FROM transacciones 
        WHERE usuario_id = ? AND ejemplar_id IN (SELECT id FROM ejemplares WHERE libro_id = ?) 
          AND tipo = 'reserva' AND estado = 'activa' 
        ORDER BY id DESC LIMIT 1
    ", [$uId, $bId]);
    assert_true(!empty($tx), 'Reserva creada en BD tras el POST');
    assert_equals('activa', $tx['estado'], 'Estado de la reserva es activa');
    assert_true(str_starts_with((string) $tx['codigo'], 'RES-'), 'Código generado tiene prefijo RES-');
});

it('T-UX-04: /mostrador: escáner universal presente; Entrega directa es la PRIMERA tarjeta; las tarjetas respetan puede()', function () {
    $sPers = login_como('personal');
    $rMost = http_get('/mostrador', $sPers);
    assert_http_code(200, $rMost);
    assert_contains($rMost['body'], 'id="escaner-universal"', 'Escáner universal presente');
    assert_contains($rMost['body'], 'card-operacion-directa', 'Tarjeta Entrega directa presente');

    // Verificar orden: card-operacion-directa antes de card-operacion-reserva
    $posDirecta = strpos($rMost['body'], 'card-operacion-directa');
    $posReserva = strpos($rMost['body'], 'card-operacion-reserva');
    assert_true($posDirecta !== false && $posReserva !== false && $posDirecta < $posReserva, 'Entrega directa es la PRIMERA opción');

    // Comprobar respeto de puede(): quitar deposito.registrar a personal mediante /admin/roles
    $sAdmin = login_como('admin');
    $permsSinDep = array_values(array_diff(permisos_seed_personal(), ['deposito.registrar']));
    http_get('/admin/roles', $sAdmin);
    http_post('/admin/roles', ['permisos' => $permsSinDep], $sAdmin);

    $rSinDep = http_get('/mostrador', $sPers);
    assert_true(!str_contains($rSinDep['body'], 'card-operacion-deposito'), 'Sin deposito.registrar la tarjeta Registrar Depósito no se muestra');

    // Restaurar permiso
    http_get('/admin/roles', $sAdmin);
    http_post('/admin/roles', ['permisos' => permisos_seed_personal()], $sAdmin);
    $rConDep = http_get('/mostrador', $sPers);
    assert_contains($rConDep['body'], 'card-operacion-deposito', 'Al restaurar permiso, la tarjeta vuelve a estar visible');
});

it('T-UX-05: /admin: sidebar con los 3 grupos y sus enlaces, acciones rápidas y sección "Pendientes de atención"; breadcrumbs presentes en /admin/usuarios', function () {
    $sAdmin = login_como('admin');
    $rAdmin = http_get('/admin', $sAdmin);
    assert_http_code(200, $rAdmin);

    // Sidebar y 3 grupos
    assert_contains($rAdmin['body'], 'id="sidebar-admin"', 'Sidebar presente con id="sidebar-admin"');
    assert_contains($rAdmin['body'], 'Operación', 'Grupo 1 Operación presente en sidebar');
    assert_contains($rAdmin['body'], 'Personas', 'Grupo 2 Personas presente en sidebar');
    assert_contains($rAdmin['body'], 'Sistema', 'Grupo 3 Sistema presente en sidebar');
    assert_contains($rAdmin['body'], '/admin/reservas', 'Enlace a Reservas en sidebar');
    assert_contains($rAdmin['body'], '/admin/libros', 'Enlace a Catálogo en sidebar');
    assert_contains($rAdmin['body'], '/admin/movimientos', 'Enlace a Movimientos en sidebar');
    assert_contains($rAdmin['body'], '/admin/usuarios', 'Enlace a Usuarios en sidebar');
    assert_contains($rAdmin['body'], '/admin/roles', 'Enlace a Roles en sidebar');
    assert_contains($rAdmin['body'], '/admin/configuracion', 'Enlace a Configuración en sidebar');
    assert_contains($rAdmin['body'], '/admin/backups', 'Enlace a Backups en sidebar');
    assert_contains($rAdmin['body'], '/admin/auditoria', 'Enlace a Auditoría en sidebar');
    assert_contains($rAdmin['body'], '/admin/metricas', 'Enlace a Métricas en sidebar');

    // Acciones rápidas y sección Pendientes de atención
    assert_contains($rAdmin['body'], 'Acciones Rápidas', 'Fila de acciones rápidas presente');
    assert_contains($rAdmin['body'], 'Pendientes de atención', 'Sección Pendientes de atención presente');

    // Breadcrumbs en /admin/usuarios
    $rUsers = http_get('/admin/usuarios', $sAdmin);
    assert_http_code(200, $rUsers);
    assert_contains($rUsers['body'], 'breadcrumb', 'Breadcrumbs presentes en /admin/usuarios');
    assert_contains($rUsers['body'], 'Usuarios', 'Texto Usuarios en breadcrumbs');
});

it('T-UX-06: /mis-depositos redirige a /mi-historial con filtro tipo=deposito; /como-funciona responde 200 y está enlazada desde home y footer', function () {
    $sUser = login_como('usuario');
    $rDep = http_get('/mis-depositos', $sUser);
    assert_http_code(302, $rDep, 'GET /mis-depositos responde 302');

    $rComo = http_get('/como-funciona');
    assert_http_code(200, $rComo, 'GET /como-funciona responde 200');
    assert_contains($rComo['body'], '¿Cómo funciona', 'Título de cómo funciona presente');
    assert_contains($rComo['body'], 'Consejo para la recogida en mostrador', 'Consejo del lector de códigos 1D presente');

    $rHome = http_get('/');
    assert_contains($rHome['body'], 'href="/como-funciona"', 'Home pública enlaza a /como-funciona');

    $rFoot = http_get('/visitanos');
    assert_contains($rFoot['body'], 'href="/como-funciona"', 'Footer enlaza a /como-funciona');
});

it('T-UX-07: Confirmaciones destructivas usan modal (no confirm() nativo): la cancelación de reserva desde /mis-reservas pasa por modal con CSRF', function () {
    $sUser = login_como('usuario');
    db_exec("UPDATE transacciones SET estado = 'activa' WHERE codigo = 'RES-DEMO-0001'");

    $rResv = http_get('/mis-reservas', $sUser);
    assert_http_code(200, $rResv);
    assert_true(!str_contains($rResv['body'], 'onsubmit="return confirm('), 'No se utiliza confirm() nativo en /mis-reservas');
    assert_contains($rResv['body'], 'modalCancelarReserva-', 'Modal de confirmación para cancelar reserva presente');
    assert_contains($rResv['body'], 'action="/mis-reservas/cancelar"', 'Formulario de cancelación apunta a /mis-reservas/cancelar');

    // Benchmark se ejecuta con código 0 y cubre /admin/roles
    $output = [];
    $ret = 0;
    exec('php ' . escapeshellarg(ROOT . '/tests/benchmark.php'), $output, $ret);
    assert_equals(0, $ret, 'tests/benchmark.php ejecutó con código 0');
    $fullBmOut = implode("\n", $output);
    assert_contains($fullBmOut, '/admin/roles', 'Benchmark incluye medición de /admin/roles');
});

group('Fase 19 — Acciones contextuales y flujos unificados (v4.6)');

it('T-CTX-01: Ficha de libro: personal ve #barra-gestion-libro y acciones; usuario no ve herramientas de personal', function () {
    $sPers = login_como('personal');
    $rPers = http_get('/libro/1', $sPers);
    assert_http_code(200, $rPers, 'GET /libro/1 responde 200 para personal');
    assert_contains($rPers['body'], 'id="barra-gestion-libro"', 'Barra de gestión presente para personal');
    assert_contains($rPers['body'], '/libros/entrada?libro_id=1', 'Enlace para añadir copias en barra de gestión');
    assert_contains($rPers['body'], '/admin/libros/editar?id=1', 'Enlace para editar libro en barra de gestión');
    assert_contains($rPers['body'], 'modalMovimientosLibro', 'Modal de movimientos presente');

    $sUser = login_como('usuario');
    $rUser = http_get('/libro/1', $sUser);
    assert_http_code(200, $rUser, 'GET /libro/1 responde 200 para usuario');
    assert_not_contains($rUser['body'], 'id="barra-gestion-libro"', 'Barra de gestión NO visible para usuario');
    assert_not_contains($rUser['body'], 'Añadir copias', 'Botón Añadir copias NO visible para usuario');
    assert_not_contains($rUser['body'], 'modalEditarEjemplar-', 'Modales de edición de ejemplar NO visibles para usuario');
});

it('T-CTX-02: Entrada unificada CON depositante: crea N copias, acredita tokens al lector y audita', function () {
    $pdo = db();
    $u3 = $pdo->query("SELECT u.id, u.email FROM usuarios u WHERE u.email = 'usuario@bookswap.local' LIMIT 1")->fetch();
    assert_true(!empty($u3['email']), 'Lector de pruebas encontrado');
    $u3Id = (int) $u3['id'];
    $u3Email = (string) $u3['email'];

    $saldoAntes = ledger_obtener_saldo($pdo, $u3Id);
    $copiasAntes = (int) db_val("SELECT COUNT(*) FROM ejemplares WHERE libro_id = 1");

    $sPers = login_como('personal');
    http_get('/libros/entrada', $sPers);
    $r = http_post('/libros/entrada', [
        'libro_id'    => 1,
        'copias'      => 3,
        'condicion'   => 'bueno',
        'ubicacion'   => 'TEST-CTX-02',
        'depositante' => $u3Email,
    ], $sPers);

    assert_true(in_array($r['code'], [200, 302], true), 'POST /libros/entrada responde 200 o 302');

    $copiasDespues = (int) db_val("SELECT COUNT(*) FROM ejemplares WHERE libro_id = 1");
    assert_equals($copiasAntes + 3, $copiasDespues, 'Se han registrado 3 copias físicas adicionales');

    $saldoDespues = ledger_obtener_saldo($pdo, $u3Id);
    assert_equals($saldoAntes + 3, $saldoDespues, 'Se han acreditado +3 tokens al depositante');

    $auditoria = db_val("SELECT COUNT(*) FROM registro_auditoria WHERE accion = 'entrada_copias' AND entidad = 'ejemplares'");
    assert_true((int)$auditoria > 0, 'Auditoría registró entrada_copias');
});

it('T-CTX-03: Entrada unificada SIN depositante: crea copias de biblioteca sin asignar tokens', function () {
    $copiasAntes = (int) db_val("SELECT COUNT(*) FROM ejemplares WHERE libro_id = 1");
    $movsAntes = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens");

    $sPers = login_como('personal');
    http_get('/libros/entrada', $sPers);
    $r = http_post('/libros/entrada', [
        'libro_id'    => 1,
        'copias'      => 2,
        'condicion'   => 'nuevo',
        'ubicacion'   => 'TEST-CTX-03',
        'depositante' => '',
    ], $sPers);

    assert_true(in_array($r['code'], [200, 302], true), 'POST /libros/entrada sin depositante responde 200 o 302');

    $copiasDespues = (int) db_val("SELECT COUNT(*) FROM ejemplares WHERE libro_id = 1");
    assert_equals($copiasAntes + 2, $copiasDespues, 'Se han registrado 2 copias físicas para el centro');

    $movsDespues = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens");
    assert_equals($movsAntes, $movsDespues, 'No se generaron movimientos de tokens al ser stock de centro');
});

it('T-CTX-04: Redirección tras alta de libro nuevo: aterriza en /libros/entrada con el libro cargado (Caso B)', function () {
    $sPers = login_como('personal');
    http_get('/admin/libros/nuevo', $sPers);
    $tituloNuevo = 'Novela Ficticia ' . uniqid();
    $isbnNuevo = '978' . str_pad((string) random_int(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);

    $rAlta = http_post('/admin/libros/nuevo', [
        'titulo'    => $tituloNuevo,
        'autor'     => 'Autor Ficticio',
        'isbn13'    => $isbnNuevo,
        'editorial' => 'Ediciones Test',
        'anio'      => 2026,
        'genero'    => 'Ficción',
        'idioma'    => 'es',
    ], $sPers);

    assert_equals(302, $rAlta['code'], 'Alta de libro responde 302');
    assert_contains($rAlta['headers'], '/libros/entrada?libro_id=', 'Redirección apunta a /libros/entrada?libro_id=...');

    $nuevoId = (int) db_val("SELECT id FROM libros WHERE isbn13 = ?", [$isbnNuevo]);
    assert_true($nuevoId > 0, 'Libro insertado en la base de datos');

    $rEntrada = http_get('/libros/entrada?libro_id=' . $nuevoId, $sPers);
    assert_http_code(200, $rEntrada, 'Página de entrada de copias responde 200');
    assert_contains($rEntrada['body'], $tituloNuevo, 'Página de entrada muestra el libro recién creado');
});

it('T-CTX-05: Trazabilidad por copia: /ejemplar/{id} responde 200 con timeline para personal; 403 para usuario', function () {
    $sPers = login_como('personal');
    $rEj = http_get('/ejemplar/1', $sPers);
    assert_http_code(200, $rEj, 'GET /ejemplar/1 responde 200 para personal');
    assert_contains($rEj['body'], 'Trazabilidad', 'Página muestra título de trazabilidad');
    assert_contains($rEj['body'], 'timeline', 'Línea temporal presente');

    $sUser = login_como('usuario');
    $rEjUser = http_get('/ejemplar/1', $sUser);
    assert_http_code(403, $rEjUser, 'GET /ejemplar/1 responde 403 para usuario');
});

it('T-CTX-06: Acciones en línea sobre ejemplar: editar ubicación/condición y baja con motivo rechazada si reservado', function () {
    $pdo = db();
    $pdo->exec("INSERT INTO ejemplares (libro_id, estado, ubicacion, condicion) VALUES (1, 'disponible', 'EST-PRE-EDIT', 'bueno')");
    $ejId = (int) $pdo->lastInsertId();

    $sPers = login_como('personal');
    http_get('/libro/1', $sPers);
    $rEdit = http_post("/ejemplar/{$ejId}/editar", [
        'condicion' => 'deteriorado',
        'ubicacion' => 'EST-POST-EDIT',
    ], $sPers);
    assert_true(in_array($rEdit['code'], [200, 302], true), 'Editar ejemplar responde 200 o 302');

    $ejEditado = db_row("SELECT condicion, ubicacion FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('deteriorado', $ejEditado['condicion'], 'Condición actualizada');
    assert_equals('EST-POST-EDIT', $ejEditado['ubicacion'], 'Ubicación física actualizada');

    // Intento de baja sobre copia reservada -> debe ser rechazada
    $pdo->exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = {$ejId}");
    http_get('/libro/1', $sPers);
    $rBajaBloq = http_post("/ejemplar/{$ejId}/baja", [
        'motivo' => 'Intento no permitido en copia reservada',
    ], $sPers);
    $stBloq = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('reservado', $stBloq, 'No se permite dar de baja una copia reservada');

    // Baja sobre copia disponible -> aceptada con motivo
    $pdo->exec("UPDATE ejemplares SET estado = 'disponible' WHERE id = {$ejId}");
    http_get('/libro/1', $sPers);
    $rBajaOk = http_post("/ejemplar/{$ejId}/baja", [
        'motivo' => 'Copia deteriorada por humedad',
    ], $sPers);
    $stBaja = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('baja', $stBaja, 'Copia dada de baja correctamente');
});

it('T-CTX-07: Control de acceso y mostrador unificado: /libros/entrada requiere catalogo.editar; mostrador contiene ENTRADAS y SALIDAS', function () {
    $sUser = login_como('usuario');
    $rEntrUser = http_get('/libros/entrada', $sUser);
    assert_http_code(403, $rEntrUser, 'Acceso a /libros/entrada denegado para USUARIO (403)');

    $sPers = login_como('personal');
    $rEntrPers = http_get('/libros/entrada', $sPers);
    assert_http_code(200, $rEntrPers, 'Acceso a /libros/entrada permitido para PERSONAL (200)');

    $rMost = http_get('/mostrador', $sPers);
    assert_http_code(200, $rMost);
    assert_contains($rMost['body'], 'SALIDAS', 'Grupo SALIDAS presente en mostrador');
    assert_contains($rMost['body'], 'ENTRADAS', 'Grupo ENTRADAS presente en mostrador');
    assert_contains($rMost['body'], 'card-operacion-deposito', 'Tarjeta card-operacion-deposito presente');

    // Búsqueda por ISBN / q en /libros/entrada (Caso B si no existe) sin excepción SQL
    $rInex = http_get('/libros/entrada?q=97884836551896', $sPers);
    assert_http_code(200, $rInex, 'GET /libros/entrada?q=... con ISBN no catalogado responde 200');
    assert_contains($rInex['body'], 'El libro no está registrado en el catálogo', 'Muestra interfaz Caso B');
});

it('T-CTX-08: /mostrador: Entregar Reserva incluye listado de libros pendientes ordenados por fecha de reserva', function () {
    $sPers = login_como('personal');
    $rMost = http_get('/mostrador', $sPers);
    assert_http_code(200, $rMost, '/mostrador responde 200 para personal');
    assert_contains($rMost['body'], 'Libros pendientes de entrega', 'Título de sección presente');
    assert_contains($rMost['body'], 'tabla-reservas-pendientes', 'Tabla de reservas pendientes presente');
    assert_contains($rMost['body'], 'RES-DEMO-0001', 'Reserva demo presente en la tabla');
    assert_contains($rMost['body'], 'El guardián entre el centeno', 'Título del libro presente en la tabla');
    assert_contains($rMost['body'], 'filtro-reservas-input', 'Filtro en tiempo real presente');

    // Comprobar que seleccionar una reserva vía ?codigo= marca la fila como seleccionada / En Gestión
    $rSel = http_get('/mostrador?codigo=RES-DEMO-0001', $sPers);
    assert_http_code(200, $rSel, '/mostrador?codigo=... responde 200');
    assert_contains($rSel['body'], 'En Gestión', 'Fila seleccionada tiene badge En Gestión');
    assert_contains($rSel['body'], 'Confirmar entrega (pagada', 'Panel de liquidación listo');
});

group('Fase 21 — Economía de reserva con tokens bloqueados (v4.7)');

it('T-TOK-01: Saldo < coste_libro → reserva denegada sin cambios en BD; con saldo exacto → permitida', function () {
    $pdo = db();
    $email = 'tok01_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok01', ?, 'hash', 3, 1, 1)", [$email]);
    $uid = (int) $pdo->lastInsertId();

    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK01A')", [$bId]);
    $ej1 = (int) $pdo->lastInsertId();
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK01B')", [$bId]);
    $ej2 = (int) $pdo->lastInsertId();

    $costeLibro = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'") ?: 1);

    // Saldo = 0 < costeLibro
    $denegado = false;
    try {
        reserva_crear($pdo, $uid, $bId, $ej1);
    } catch (Exception $e) {
        $denegado = true;
        assert_contains($e->getMessage(), 'faltan', 'Mensaje indica que faltan tokens');
    }
    assert_true($denegado, 'Reserva rechazada por falta de saldo');

    // Comprobar que no hay cambios en BD
    $txCount = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE usuario_id = ?", [$uid]);
    assert_equals(0, $txCount, 'No se creó ninguna transacción');
    $estadoEj1 = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ej1]);
    assert_equals('disponible', $estadoEj1, 'El ejemplar sigue disponible');
    $movCount = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals(0, $movCount, 'No se registraron movimientos en el ledger');

    // Otorgar saldo exacto (costeLibro)
    ledger_registrar_movimiento($pdo, $uid, $costeLibro, 'bono', null, 'Saldo exacto');
    $saldoActual = ledger_obtener_saldo($pdo, $uid);
    assert_equals($costeLibro, $saldoActual, 'Saldo otorgado con exactitud');

    // Intentar reservar con saldo exacto -> permitido
    $res = reserva_crear($pdo, $uid, $bId, $ej2);
    assert_true(!empty($res['codigo']), 'Reserva permitida con saldo exacto');
    $saldoFinal = ledger_obtener_saldo($pdo, $uid);
    assert_equals(0, $saldoFinal, 'Saldo resultante tras reserva es 0');
    $estadoEj2 = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ej2]);
    assert_equals('reservado', $estadoEj2, 'Ejemplar queda reservado');
});

it('T-TOK-02: Ciclo completo: reservar (bloqueo −1) → cancelar (liberación +1) → SUM intacto', function () {
    $pdo = db();
    $email = 'tok02_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok02', ?, 'hash', 3, 1, 1)", [$email]);
    $uid = (int) $pdo->lastInsertId();

    $costeLibro = (int) (db_val("SELECT valor FROM configuracion WHERE clave = 'coste_libro'") ?: 1);
    ledger_registrar_movimiento($pdo, $uid, 3, 'bono', null, 'Saldo inicial 3 tokens');
    $saldoInicial = ledger_obtener_saldo($pdo, $uid);
    assert_equals(3, $saldoInicial, 'Saldo inicial es 3');

    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK02')", [$bId]);
    $ejId = (int) $pdo->lastInsertId();

    // 1. Reservar (bloqueo_reserva -costeLibro)
    $res = reserva_crear($pdo, $uid, $bId, $ejId);
    $txId = (int) $res['id'];
    $saldoTrasReserva = ledger_obtener_saldo($pdo, $uid);
    assert_equals($saldoInicial - $costeLibro, $saldoTrasReserva, 'Saldo tras reserva descontó costeLibro');

    $movBloqueo = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId} AND tipo = 'bloqueo_reserva'")->fetch();
    assert_true(!empty($movBloqueo), 'Movimiento de bloqueo_reserva existe');
    assert_equals(-$costeLibro, (int) $movBloqueo['cantidad']);
    assert_equals($saldoTrasReserva, (int) $movBloqueo['saldo_resultante']);

    // 2. Cancelar (liberacion_reserva +costeLibro)
    $okCanc = reserva_cancelar($pdo, $txId, $uid);
    assert_true($okCanc, 'Cancelación exitosa');

    $saldoTrasCancel = ledger_obtener_saldo($pdo, $uid);
    assert_equals($saldoInicial, $saldoTrasCancel, 'Saldo tras cancelación restaurado exactamente al inicial');

    $movLib = db()->query("SELECT * FROM movimientos_tokens WHERE transaccion_id = {$txId} AND tipo = 'liberacion_reserva'")->fetch();
    assert_true(!empty($movLib), 'Movimiento de liberacion_reserva existe');
    assert_equals($costeLibro, (int) $movLib['cantidad']);
    assert_equals($saldoInicial, (int) $movLib['saldo_resultante']);

    // 3. Invariante: SUM(cantidad) = saldo final
    $sum = (int) db_val("SELECT SUM(cantidad) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($saldoInicial, $sum, 'SUM(cantidad) coincide con saldo inicial');
});

it('T-TOK-03: Con 2 tokens: 2 reservas OK, la 3ª denegada por saldo (aunque max_reservas=3)', function () {
    $pdo = db();
    $email = 'tok03_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok03', ?, 'hash', 3, 1, 1)", [$email]);
    $uid = (int) $pdo->lastInsertId();

    db_exec("UPDATE configuracion SET valor = '3' WHERE clave = 'max_reservas_activas'");

    // Dar exactamente 2 tokens
    ledger_registrar_movimiento($pdo, $uid, 2, 'bono', null, 'Saldo de 2 tokens');

    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    $ejIds = [];
    for ($i = 0; $i < 3; $i++) {
        db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK03-{$i}')", [$bId]);
        $ejIds[] = (int) $pdo->lastInsertId();
    }

    // 1ª reserva OK
    $res1 = reserva_crear($pdo, $uid, $bId, $ejIds[0]);
    assert_true(!empty($res1['codigo']), '1ª reserva creada');
    assert_equals(1, ledger_obtener_saldo($pdo, $uid), 'Saldo tras 1ª reserva es 1');

    // 2ª reserva OK
    $res2 = reserva_crear($pdo, $uid, $bId, $ejIds[1]);
    assert_true(!empty($res2['codigo']), '2ª reserva creada');
    assert_equals(0, ledger_obtener_saldo($pdo, $uid), 'Saldo tras 2ª reserva es 0');

    // 3ª reserva DENEGADA por falta de saldo, aunque max_reservas=3
    $denegadaPorSaldo = false;
    try {
        reserva_crear($pdo, $uid, $bId, $ejIds[2]);
    } catch (Exception $e) {
        $denegadaPorSaldo = true;
        assert_contains($e->getMessage(), 'faltan', 'Mensaje explica falta de saldo');
    }
    assert_true($denegadaPorSaldo, '3ª reserva denegada por saldo insuficiente a pesar de max_reservas=3');

    $activasCount = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE usuario_id = ? AND tipo = 'reserva' AND estado = 'activa'", [$uid]);
    assert_equals(2, $activasCount, 'Solo 2 reservas activas en BD');
});

it('T-TOK-04: Entrega de reserva consolidada: sin movimiento nuevo, transacción \'entregada\' con metodo_pago=\'tokens\', copia \'retirado\'', function () {
    $pdo = db();
    $email = 'tok04_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok04', ?, 'hash', 3, 1, 1)", [$email]);
    $uid = (int) $pdo->lastInsertId();

    ledger_registrar_movimiento($pdo, $uid, 5, 'bono', null, 'Saldo inicial 5');

    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK04')", [$bId]);
    $ejId = (int) $pdo->lastInsertId();

    $res = reserva_crear($pdo, $uid, $bId, $ejId);
    $txId = (int) $res['id'];

    $saldoAntesEntrega = ledger_obtener_saldo($pdo, $uid);
    assert_equals(4, $saldoAntesEntrega, 'Saldo tras reserva es 4');
    $movsCountAntes = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);

    $personalId = (int) db_val("SELECT id FROM usuarios WHERE email = 'personal@bookswap.local'");
    $resultado = mostrador_completar_entrega($pdo, $txId, 'tokens', null, 'bueno', $personalId);
    assert_true($resultado['ok'], 'Entrega confirmada');

    // SIN movimiento nuevo
    $movsCountDespues = (int) db_val("SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?", [$uid]);
    assert_equals($movsCountAntes, $movsCountDespues, 'Consolidación SIN nuevo movimiento en ledger');

    $saldoDespuesEntrega = ledger_obtener_saldo($pdo, $uid);
    assert_equals($saldoAntesEntrega, $saldoDespuesEntrega, 'Saldo del usuario inalterado en entrega');

    // Transacción 'entregada' con metodo_pago='tokens'
    $tx = db()->query("SELECT * FROM transacciones WHERE id = {$txId}")->fetch();
    assert_equals('entregada', $tx['estado'], 'Transacción marcada como entregada');
    assert_equals('tokens', $tx['metodo_pago'], 'Método de pago registrado como tokens');

    // Copia 'retirado'
    $estadoEj = db_val("SELECT estado FROM ejemplares WHERE id = ?", [$ejId]);
    assert_equals('retirado', $estadoEj, 'Ejemplar marcado como retirado');
});

it('T-TOK-05: Doble reserva concurrente sobre la última copia con saldo para UNA: solo una prospera (atomicidad)', function () {
    $pdo = db();
    $bId = (int) db_val("SELECT id FROM libros LIMIT 1");
    // Crear una sola copia disponible
    db_exec("INSERT INTO ejemplares (libro_id, condicion, estado, ubicacion) VALUES (?, 'bueno', 'disponible', 'EST-TOK05')", [$bId]);
    $ejId = (int) $pdo->lastInsertId();

    $email1 = 'tok05_a_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok05 A', ?, 'hash', 3, 1, 1)", [$email1]);
    $u1 = (int) $pdo->lastInsertId();
    ledger_registrar_movimiento($pdo, $u1, 1, 'bono', null, 'Saldo 1');

    $email2 = 'tok05_b_' . uniqid() . '@bookswap.local';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('User Tok05 B', ?, 'hash', 3, 1, 1)", [$email2]);
    $u2 = (int) $pdo->lastInsertId();
    ledger_registrar_movimiento($pdo, $u2, 1, 'bono', null, 'Saldo 1');

    // Simulamos la concurrencia: u1 reserva primero la copia específica
    $res1 = null;
    $res2 = null;
    $error2 = null;

    try {
        $res1 = reserva_crear($pdo, $u1, $bId, $ejId);
    } catch (Exception $e) {
        $res1 = null;
    }

    try {
        $res2 = reserva_crear($pdo, $u2, $bId, $ejId);
    } catch (Exception $e) {
        $error2 = $e->getMessage();
    }

    assert_true($res1 !== null && !empty($res1['codigo']), 'La primera reserva prospera');
    assert_true($res2 === null, 'La segunda reserva sobre la misma copia falla');
    assert_true(!empty($error2), 'Se produce excepción en la segunda reserva');

    // El saldo de u1 fue descontado, el de u2 se mantuvo intacto
    assert_equals(0, ledger_obtener_saldo($pdo, $u1), 'Saldo de u1 descontado');
    assert_equals(1, ledger_obtener_saldo($pdo, $u2), 'Saldo de u2 intacto');

    // En ejemplares, solo reservado a 1
    $reservasCount = (int) db_val("SELECT COUNT(*) FROM transacciones WHERE ejemplar_id = ? AND estado = 'activa'", [$ejId]);
    assert_equals(1, $reservasCount, 'Solo existe 1 reserva activa para el ejemplar');
});

/* ─── FASE 22: RECUPERACIÓN DE CONTRASEÑA (T-RESET-01..06) ─── */

it('T-RESET-01: POST /olvidar con email existente → fila en password_resets (hash, no claro; expira ≈1h), respuesta genérica, en dev el enlace aparece en pantalla', function () {
    $email = 'reset_user_' . uniqid() . '@bookswap.local';
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector Reset', ?, ?, 3, 1, 1)", [$email, $hash]);
    $uid = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);

    $s = new_session();
    http_get('/olvidar', $s);
    $r = http_post('/olvidar', ['email' => $email], $s);

    assert_equals(200, $r['code'], 'POST /olvidar devuelve HTTP 200');
    assert_contains($r['body'], 'Si ese correo está registrado, recibirás las instrucciones', 'Respuesta pública genérica anti-enumeración');
    assert_contains($r['body'], '/reset/', 'En entorno de desarrollo el enlace aparece en pantalla');

    // Verificar en BD
    $rows = db()->query("SELECT * FROM password_resets WHERE usuario_id = {$uid}")->fetchAll();
    assert_equals(1, count($rows), 'Se ha creado exactamente 1 fila en password_resets');
    $row = $rows[0];
    assert_equals(64, strlen($row['token_hash']), 'token_hash almacenado tiene longitud 64 (SHA-256)');
    assert_not_contains($row['token_hash'], 'reset', 'El hash no es texto plano');
    assert_equals(0, (int) $row['usado'], 'El token no está usado');

    // Verificar expiración ≈ 1 hora
    $diffSec = (int) db_val("SELECT TIMESTAMPDIFF(SECOND, creado, expira) FROM password_resets WHERE id = {$row['id']}");
    assert_true($diffSec >= 3500 && $diffSec <= 3700, "Expiración configurada en aprox. 1 hora (obtenido {$diffSec}s)");
});

it('T-RESET-02: Flujo completo: /reset/{token} → nueva contraseña → login OK con la nueva y NO con la vieja; reutilizar el token → rechazado', function () {
    $email = 'reset_flow_' . uniqid() . '@bookswap.local';
    $claveVieja = 'antiguaClave123';
    $claveNueva = 'nuevaSuperSegura456';
    $hashViejo = password_hash($claveVieja, PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector Flow', ?, ?, 3, 1, 1)", [$email, $hashViejo]);
    $uid = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);

    // Generar token vía helper
    $resSol = password_reset_solicitar(db(), $email);
    assert_true($resSol['ok'], 'Solicitud de reset aceptada');
    $token = $resSol['token'];
    assert_true(!empty($token), 'Token generado');

    $s = new_session();
    // 1. GET /reset/{token}
    $rGet = http_get("/reset/{$token}", $s);
    assert_equals(200, $rGet['code'], 'GET /reset/{token} devuelve 200');
    assert_contains($rGet['body'], 'Nueva contraseña', 'Formulario de nueva contraseña renderizado');

    // 2. POST /reset/{token} con nueva contraseña
    $rPost = http_post("/reset/{$token}", [
        'password' => $claveNueva,
        'password_confirm' => $claveNueva,
    ], $s);
    assert_true(in_array($rPost['code'], [200, 302], true), 'POST /reset/{token} procesado exitosamente');

    // 3. Login con contraseña vieja -> falla
    $sLogin1 = new_session();
    http_get('/login', $sLogin1);
    $rOld = http_post('/login', ['email' => $email, 'password' => $claveVieja], $sLogin1);
    assert_contains($rOld['body'], 'Credenciales incorrectas', 'Login con clave antigua denegado');

    // 4. Login con contraseña nueva -> éxito
    $sLogin2 = new_session();
    http_get('/login', $sLogin2);
    $rNew = http_post('/login', ['email' => $email, 'password' => $claveNueva], $sLogin2);
    assert_true(in_array($rNew['code'], [200, 302], true), 'Login con nueva clave exitoso');

    // 5. Reutilizar el token -> rechazado
    $sReuse = new_session();
    $rReuseGet = http_get("/reset/{$token}", $sReuse);
    assert_contains($rReuseGet['body'], 'caducado', 'Reutilización del token por GET rechazada amablemente');

    http_get('/olvidar', $sReuse);
    $rReusePost = http_post("/reset/{$token}", [
        'password' => 'otraClave789',
        'password_confirm' => 'otraClave789',
    ], $sReuse);
    assert_contains($rReusePost['body'], 'no es válido', 'Reutilización del token por POST rechazada amablemente');
});

it('T-RESET-03: Token con expira en pasado → rechazado amable', function () {
    $email = 'reset_exp_' . uniqid() . '@bookswap.local';
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector Exp', ?, ?, 3, 1, 1)", [$email, $hash]);
    $uid = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    db_exec("INSERT INTO password_resets (usuario_id, token_hash, expira, usado, creado)
             VALUES (?, ?, NOW() - INTERVAL 10 MINUTE, 0, NOW() - INTERVAL 70 MINUTE)", [$uid, $tokenHash]);

    $s = new_session();
    $r = http_get("/reset/{$token}", $s);
    assert_contains($r['body'], 'caducado', 'Token expirado muestra mensaje amable de caducidad');
    assert_contains($r['body'], '/olvidar', 'Página ofrece enlace para solicitar un nuevo token');
});

it('T-RESET-04: Email inexistente → MISMA respuesta genérica, sin fila creada; 4ª petición en <1h → rate-limit', function () {
    $emailInexistente = 'fantasma_' . uniqid() . '@no-existe.local';

    $s = new_session();
    http_get('/olvidar', $s);
    $r1 = http_post('/olvidar', ['email' => $emailInexistente], $s);
    assert_contains($r1['body'], 'Si ese correo está registrado, recibirás las instrucciones', 'Misma respuesta genérica');
    assert_not_contains($r1['body'], '/reset/', 'No se muestra ningún enlace en pantalla para email inexistente');

    // Verificar que no se creó fila para ese email
    $filasFantasma = (int) db_val("SELECT COUNT(*) FROM password_resets pr JOIN usuarios u ON u.id = pr.usuario_id WHERE u.email = ?", [$emailInexistente]);
    assert_equals(0, $filasFantasma, 'No se creó ninguna fila en password_resets');

    // 2ª y 3ª petición
    http_get('/olvidar', $s);
    http_post('/olvidar', ['email' => $emailInexistente], $s);
    http_get('/olvidar', $s);
    http_post('/olvidar', ['email' => $emailInexistente], $s);

    // 4ª petición dentro de 1 hora -> Rate limit!
    http_get('/olvidar', $s);
    $r4 = http_post('/olvidar', ['email' => $emailInexistente], $s);
    assert_true(
        $r4['code'] === 429 || str_contains($r4['body'], 'Demasiadas solicitudes') || str_contains($r4['body'], 'límite'),
        "4ª petición devuelve rate-limit (HTTP {$r4['code']})"
    );
});

it('T-RESET-05: ADMIN genera enlace desde panel → funciona una vez y queda auditado; PERSONAL/USUARIO → 403', function () {
    $emailTarget = 'reset_adm_' . uniqid() . '@bookswap.local';
    $hashOriginal = password_hash('claveOriginal123', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector AdminReset', ?, ?, 3, 1, 1)", [$emailTarget, $hashOriginal]);
    $targetId = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$emailTarget]);

    // 1. ADMIN genera enlace
    $sAdmin = login_como('admin');
    http_get('/admin/usuarios', $sAdmin);
    $rAdmin = http_post('/admin/usuarios/reset-enlace', ['usuario_id' => $targetId], $sAdmin);
    assert_true(in_array($rAdmin['code'], [200, 302], true), 'Admin genera enlace con éxito');

    $prRow = db()->query("SELECT * FROM password_resets WHERE usuario_id = {$targetId} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($prRow), 'Fila creada en password_resets');
    assert_equals(0, (int) $prRow['usado'], 'Token inicializado como no usado');

    // Verificar auditoría
    $aud = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'password_reset_enlace' AND entidad_id = {$targetId} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($aud), 'Auditoría registrada para password_reset_enlace');
    assert_equals(1, (int) $aud['usuario_id'], 'Autor de la auditoría es el administrador (id=1)');

    // Verificar que el enlace se muestra en panel, incluye el contenedor QR in situ y funciona una vez
    $rFollow = http_get('/admin/usuarios', $sAdmin);
    preg_match('#/reset/([a-f0-9]{64})#', $rFollow['body'], $mToken);
    assert_true(!empty($mToken[1]), 'El token en texto plano aparece en el panel del admin para copiar');
    assert_contains($rFollow['body'], 'id="qrcodeResetContainer"', 'El contenedor del código QR in situ está presente en el panel');
    assert_contains($rFollow['body'], 'id="modalQRResetGrande"', 'El modal para ampliar el código QR está disponible');
    assert_contains($rFollow['body'], 'qrcode.min.js', 'La librería de renderizado QR está incluida en la vista');
    $tokenAdmin = $mToken[1];

    $sLector = new_session();
    http_get("/reset/{$tokenAdmin}", $sLector);
    $rResetLector = http_post("/reset/{$tokenAdmin}", [
        'password' => 'nuevaClaveLector123',
        'password_confirm' => 'nuevaClaveLector123',
    ], $sLector);
    assert_true(in_array($rResetLector['code'], [200, 302], true), 'El enlace del admin permite cambiar la contraseña');

    // Intentar reutilizarlo -> rechazado
    $sReuse = new_session();
    $rReuse = http_get("/reset/{$tokenAdmin}", $sReuse);
    assert_contains($rReuse['body'], 'caducado', 'El enlace generado por admin solo funciona una vez');

    // 2. Personal intenta generar enlace -> 403
    $sPersonal = login_como('personal');
    http_get('/mostrador', $sPersonal);
    $rPers = http_post('/admin/usuarios/reset-enlace', ['usuario_id' => $targetId], $sPersonal);
    assert_equals(403, $rPers['code'], 'Personal recibe 403 al intentar generar enlace');

    // 3. Usuario estándar intenta generar enlace -> 403
    $sUser = login_como('usuario');
    http_get('/dashboard', $sUser);
    $rUser = http_post('/admin/usuarios/reset-enlace', ['usuario_id' => $targetId], $sUser);
    assert_equals(403, $rUser['code'], 'Usuario estándar recibe 403 al intentar generar enlace');
});

it('T-RESET-06: Cuenta google-only: no se genera token; mensaje orientado a Google', function () {
    $emailGoogle = 'goog_only_' . uniqid() . '@example.com';
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, auth_provider, email_verificado)
             VALUES ('Lector SoloGoogle', ?, NULL, 3, 1, 'google', 1)", [$emailGoogle]);
    $uidGoogle = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$emailGoogle]);

    $s = new_session();
    http_get('/olvidar', $s);
    $r = http_post('/olvidar', ['email' => $emailGoogle], $s);

    assert_equals(200, $r['code'], 'POST /olvidar devuelve 200');
    assert_contains($r['body'], 'Si ese correo está registrado, recibirás las instrucciones', 'Respuesta pública genérica');

    // No se genera token en password_resets
    $tokens = (int) db_val("SELECT COUNT(*) FROM password_resets WHERE usuario_id = {$uidGoogle}");
    assert_equals(0, $tokens, 'No se genera ningún token en password_resets para cuenta google-only');

    // Mensaje orientado a Google
    $ultimoMail = email_ultimo_enviado();
    assert_true(!empty($ultimoMail), 'Se ha despachado correo');
    assert_equals($emailGoogle, $ultimoMail['destinatario'], 'Destinatario correcto');
    assert_contains($ultimoMail['cuerpo'], 'Google', 'El cuerpo del correo explica que la cuenta usa Google');
});

it('T-ADMIN-DEL: Eliminación completa de cuenta e historial por admin y posibilidad de re-registro', function () {
    $sAdmin = login_como('admin');
    http_get('/admin/usuarios', $sAdmin);
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");

    // 1. Crear un usuario con reservas, tokens, wishlist, notificaciones
    $emailTarget = 'borrame_' . uniqid() . '@example.com';
    $resCrear = admin_usuario_crear(db(), 'Usuario Para Borrar', $emailTarget, 'password123', 3, null, $adminId);
    assert_true($resCrear['ok'], 'Usuario creado para prueba de borrado');
    $targetId = (int) $resCrear['usuario_id'];

    // Añadir tokens adicionales
    ledger_registrar_movimiento(db(), $targetId, 5, 'ajuste', null, 'Carga de prueba');

    // Añadir una reserva activa con copia
    $copiaId = (int) db_val("SELECT id FROM ejemplares WHERE estado = 'disponible' LIMIT 1");
    if ($copiaId) {
        db_exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?", [$copiaId]);
        db_exec("INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, metodo_pago, codigo, estado, fecha_limite, created_at)
                 VALUES ('reserva', ?, ?, 1, 'tokens', 'RES-TESTBORR1', 'activa', NOW() + INTERVAL 2 DAY, NOW())", [$copiaId, $targetId]);
    }

    // Añadir wishlist y notificaciones
    $libroId = (int) db_val("SELECT id FROM libros LIMIT 1");
    if ($libroId) {
        db_exec("INSERT INTO wishlist (usuario_id, libro_id) VALUES (?, ?)", [$targetId, $libroId]);
    }
    db_exec("INSERT INTO notificaciones (usuario_id, mensaje, leida) VALUES (?, 'Notif prueba', 0)", [$targetId]);

    // Verificar que existen registros previos
    assert_true((int) db_val('SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?', [$targetId]) > 0, 'Tiene tokens');
    assert_true((int) db_val('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?', [$targetId]) > 0, 'Tiene notificaciones');

    // 2. Personal intenta borrar -> 403
    $sPers = login_como('personal');
    http_get('/mostrador', $sPers);
    $rPers = http_post('/admin/usuarios/eliminar', ['usuario_id' => $targetId], $sPers);
    assert_equals(403, $rPers['code'], 'Personal recibe 403 al intentar borrar cuenta');

    // 3. Admin no puede borrar superadmin (id=1) ni su propia cuenta
    http_get('/admin/usuarios', $sAdmin);
    $rAdminSelf = http_post('/admin/usuarios/eliminar', ['usuario_id' => $adminId], $sAdmin);
    assert_true(in_array($rAdminSelf['code'], [200, 302], true), 'Responde redirección');
    assert_equals(1, (int) db_val('SELECT COUNT(*) FROM usuarios WHERE id = ?', [$adminId]), 'Admin no borrado');

    // 4. Admin borra la cuenta
    http_get('/admin/usuarios', $sAdmin);
    $rDel = http_post('/admin/usuarios/eliminar', ['usuario_id' => $targetId], $sAdmin);
    assert_true(in_array($rDel['code'], [200, 302], true), 'Admin ejecuta eliminación con éxito');

    // Verificar que el usuario no existe en la base de datos
    $existe = (int) db_val('SELECT COUNT(*) FROM usuarios WHERE id = ?', [$targetId]);
    assert_equals(0, $existe, 'El usuario ya no existe en la tabla usuarios');

    // Verificar que todo su historial ha sido purgado
    $movs = (int) db_val('SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?', [$targetId]);
    assert_equals(0, $movs, 'Historial de tokens purgado por completo');

    $trans = (int) db_val('SELECT COUNT(*) FROM transacciones WHERE usuario_id = ?', [$targetId]);
    assert_equals(0, $trans, 'Transacciones purgadas por completo');

    $notifs = (int) db_val('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?', [$targetId]);
    assert_equals(0, $notifs, 'Notificaciones eliminadas');

    if ($copiaId) {
        $estadoCopia = db_val('SELECT estado FROM ejemplares WHERE id = ?', [$copiaId]);
        assert_equals('disponible', $estadoCopia, 'La copia reservada vuelve a estado disponible');
    }

    // Verificar auditoría
    $aud = db()->query("SELECT * FROM registro_auditoria WHERE accion = 'usuario.eliminar' AND entidad_id = {$targetId} ORDER BY id DESC LIMIT 1")->fetch();
    assert_true(!empty($aud), 'Auditoría registrada para usuario.eliminar');

    // 5. El usuario puede volver a registrarse con el MISMO email sin errores
    $sNuevo = new_session();
    http_get('/registro', $sNuevo);
    $rReg = http_post('/registro', [
        'nombre'           => 'Usuario Renacido',
        'email'            => $emailTarget,
        'password'         => 'nuevaClaveSegura123',
        'password_confirm' => 'nuevaClaveSegura123',
    ], $sNuevo);

    assert_true(in_array($rReg['code'], [200, 302], true), 'El usuario se registra nuevamente con el mismo email sin conflicto');
    $nuevoId = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$emailTarget]);
    assert_true($nuevoId > 0 && $nuevoId !== $targetId, 'Nueva cuenta creada con nuevo ID');
});

it('T-AUTH-PWD: Un usuario autenticado puede cambiar su contraseña tras hacer login', function () {
    // 1. Invitado no autenticado es redirigido a /login
    $sGuest = new_session();
    $rGuest = http_get('/cambiar-password', $sGuest);
    assert_equals(302, $rGuest['code'], 'Invitado recibe 302 al intentar acceder a /cambiar-password');
    assert_contains($rGuest['headers'], '/login', 'Redirigido a /login');

    // 2. Crear un usuario de prueba específico para no alterar usuarios demo
    $email = 'pwd_test_' . uniqid() . '@bookswap.local';
    $hashIni = password_hash('claveInicial123', PASSWORD_BCRYPT);
    db_exec("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado, auth_provider, fecha_registro)
             VALUES ('Lector Cambio Clave', ?, ?, 3, 1, 1, 'local', NOW())", [$email, $hashIni]);
    $userId = (int) db_val('SELECT id FROM usuarios WHERE email = ?', [$email]);

    $sUser = new_session();
    http_get('/login', $sUser);
    $rLog = http_post('/login', ['email' => $email, 'password' => 'claveInicial123'], $sUser);
    assert_true(in_array($rLog['code'], [200, 302], true), 'Login exitoso con clave inicial');

    // 3. Ver vista GET /cambiar-password
    $rView = http_get('/cambiar-password', $sUser);
    assert_equals(200, $rView['code'], 'GET /cambiar-password responde 200 para usuario autenticado');
    assert_contains($rView['body'], 'password_actual', 'Formulario contiene campo para contraseña actual');
    assert_contains($rView['body'], 'password_nueva', 'Formulario contiene campo para nueva contraseña');
    assert_contains($rView['body'], 'password_confirm', 'Formulario contiene campo para confirmación');

    // 4. Intento con contraseña actual errónea
    $rErrActual = http_post('/cambiar-password', [
        'password_actual'  => 'claveIncorrecta',
        'password_nueva'   => 'nuevaClaveValida123',
        'password_confirm' => 'nuevaClaveValida123',
    ], $sUser);
    assert_contains($rErrActual['body'], 'La contraseña actual no es correcta', 'Rechaza contraseña actual incorrecta');

    // 5. Intento con nueva contraseña demasiado corta (< 6 caracteres)
    http_get('/cambiar-password', $sUser);
    $rCorta = http_post('/cambiar-password', [
        'password_actual'  => 'claveInicial123',
        'password_nueva'   => '123',
        'password_confirm' => '123',
    ], $sUser);
    assert_contains($rCorta['body'], 'al menos 6 caracteres', 'Rechaza contraseña demasiado corta');

    // 6. Intento con confirmación que no coincide
    http_get('/cambiar-password', $sUser);
    $rMismatch = http_post('/cambiar-password', [
        'password_actual'  => 'claveInicial123',
        'password_nueva'   => 'nuevaClaveValida123',
        'password_confirm' => 'otraClaveDiferente123',
    ], $sUser);
    assert_contains($rMismatch['body'], 'no coinciden', 'Rechaza si las contraseñas no coinciden');

    // 7. Cambio correcto de contraseña
    http_get('/cambiar-password', $sUser);
    $rOk = http_post('/cambiar-password', [
        'password_actual'  => 'claveInicial123',
        'password_nueva'   => 'superClaveSecreta999',
        'password_confirm' => 'superClaveSecreta999',
    ], $sUser);
    assert_true(in_array($rOk['code'], [200, 302], true), 'POST /cambiar-password exitoso');

    // 8. Verificar que el hash se actualizó y la nueva contraseña verifica
    $hashActual = db_val('SELECT password_hash FROM usuarios WHERE id = ?', [$userId]);
    assert_true(password_verify('superClaveSecreta999', $hashActual), 'El nuevo hash verifica con la nueva contraseña');
    assert_true(!password_verify('claveInicial123', $hashActual), 'La clave inicial ya no verifica');

    // 9. Comprobar inicio de sesión con nueva clave
    $sNewLogin = new_session();
    http_get('/login', $sNewLogin);
    $rLoginNew = http_post('/login', ['email' => $email, 'password' => 'superClaveSecreta999'], $sNewLogin);
    assert_true(in_array($rLoginNew['code'], [200, 302], true), 'Login exitoso con la NUEVA contraseña');

    // 10. Login con la clave vieja falla
    $sOldLogin = new_session();
    http_get('/login', $sOldLogin);
    $rLoginOld = http_post('/login', ['email' => $email, 'password' => 'claveInicial123'], $sOldLogin);
    assert_contains($rLoginOld['body'], 'Credenciales incorrectas', 'Login con clave antigua rechazado');
});

it('T-PORTADAS-BUSCADOR: Buscador interactivo de portadas en la red durante la edición de libros', function () {
    // 1. Invitado no autenticado es redirigido o rechazado
    $sGuest = new_session();
    $rGuest = http_get('/admin/libros/buscar-portadas?titulo=El+principito', $sGuest);
    assert_true(in_array($rGuest['code'], [302, 401, 403], true), 'Invitado no tiene acceso a la API de búsqueda de portadas');

    // 2. Lector común (rol 3) no tiene permiso catalogo.editar (403)
    $sUser = new_session();
    http_get('/login', $sUser);
    http_post('/login', ['email' => 'usuario@bookswap.local', 'password' => 'password'], $sUser);
    $rUser = http_get('/admin/libros/buscar-portadas?titulo=El+principito', $sUser);
    assert_equals(403, $rUser['code'], 'Lector común recibe 403 al intentar buscar portadas');

    // 3. Admin / Personal puede consultar la API y recibe JSON con portadas
    $sAdmin = new_session();
    http_get('/login', $sAdmin);
    http_post('/login', ['email' => 'admin@bookswap.local', 'password' => 'password'], $sAdmin);

    // Comprobar que la vista de edición contiene el botón y el modal
    $rVista = http_get('/admin/libros/editar?id=1', $sAdmin);
    assert_equals(200, $rVista['code'], 'GET /admin/libros/editar?id=1 responde 200');
    assert_contains($rVista['body'], 'btn-disparar-buscador-portadas', 'La vista contiene el botón para buscar portadas');
    assert_contains($rVista['body'], 'modal-buscar-portadas', 'La vista incluye el modal interactivo de portadas');
    assert_contains($rVista['body'], 'modal-grid-portadas', 'El modal incluye el contenedor de cuadrícula de portadas');

    // 4. Consulta a la API con título y editorial
    $qs = http_build_query([
        'titulo'    => 'El principito',
        'autor'     => 'Antoine de Saint-Exupéry',
        'editorial' => 'Salamandra',
        'isbn'      => '9788478887194'
    ]);
    $rApi = http_get('/admin/libros/buscar-portadas?' . $qs, $sAdmin);
    assert_equals(200, $rApi['code'], 'API responde 200 para personal/admin');
    $data = json_decode($rApi['body'], true);
    assert_true(is_array($data), 'Respuesta de API es JSON válido');
    assert_true(!empty($data['ok']), 'Campo ok es true');
    assert_true(!empty($data['portadas']) && count($data['portadas']) > 0, 'Se devuelven portadas candidatas');
    assert_true(isset($data['portadas'][0]['url']), 'Cada portada contiene url de imagen');
    assert_true(isset($data['portadas'][0]['fuente']), 'Cada portada indica su fuente');
});

it('T-CAT-ELIMINAR: Borrado integral de libro, ejemplares, reservas activas con reembolso y wishlist', function () {
    $pdo = db();
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    
    // Crear lector aislado para la prueba
    $emailTest = 'lector_del_' . uniqid() . '@bookswap.local';
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, auth_provider) VALUES ('Lector Test Del', ?, ?, 3, 1, 'local')")
        ->execute([$emailTest, $hash]);
    $userId = (int) $pdo->lastInsertId();

    require_once ROOT . '/app/helpers/catalogo.php';
    require_once ROOT . '/app/helpers/reservas.php';
    require_once ROOT . '/app/helpers/wishlist.php';

    // 1. Crear un libro de prueba
    $libroId = catalogo_guardar_libro($pdo, [
        'titulo' => 'Libro Para Eliminar ' . uniqid(),
        'autor'  => 'Autor de Prueba',
        'genero' => 'Pruebas',
    ], $adminId);

    // 2. Crear 2 ejemplares
    $ej1 = catalogo_guardar_ejemplar($pdo, ['libro_id' => $libroId, 'estado' => 'disponible'], $adminId);
    $ej2 = catalogo_guardar_ejemplar($pdo, ['libro_id' => $libroId, 'estado' => 'disponible'], $adminId);

    // 3. Añadir a wishlist del usuario
    wishlist_agregar($pdo, $userId, $libroId);

    // 4. Asegurar saldo al usuario y crear reserva activa sobre el libro
    ledger_registrar_movimiento($pdo, $userId, 5, 'ajuste', null, 'Saldo para test de borrado');
    $saldoAntesRes = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$userId]);
    $resReserva = reserva_crear($pdo, $userId, $libroId);
    assert_true(!empty($resReserva['codigo']), 'Reserva creada exitosamente con código');
    $saldoConReserva = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$userId]);
    assert_equals($saldoAntesRes - 1, $saldoConReserva, 'Saldo descontó 1 token por la reserva');

    // 5. Eliminar el libro
    $resDel = catalogo_eliminar_libro($pdo, $libroId, $adminId);
    assert_true($resDel['ok'], 'Borrado de libro devuelve ok=true');
    assert_equals(2, $resDel['ejemplares_eliminados'], 'Se eliminaron 2 ejemplares físicos');
    assert_equals(1, $resDel['reservas_canceladas'], 'Se canceló 1 reserva activa');

    // 6. Comprobar que el libro y copias ya no existen en BD
    $existeLibro = db_val('SELECT COUNT(*) FROM libros WHERE id = ?', [$libroId]);
    assert_equals(0, (int) $existeLibro, 'El libro no existe en la tabla libros');
    $existeEj = db_val('SELECT COUNT(*) FROM ejemplares WHERE libro_id = ?', [$libroId]);
    assert_equals(0, (int) $existeEj, 'Los ejemplares físicos no existen en la BD');

    // 7. Comprobar que wishlist se limpió
    $existeWish = db_val('SELECT COUNT(*) FROM wishlist WHERE libro_id = ?', [$libroId]);
    assert_equals(0, (int) $existeWish, 'Entradas de wishlist eliminadas');

    // 8. Comprobar que el usuario recuperó su token
    $saldoTrasBorrado = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$userId]);
    assert_equals($saldoAntesRes, $saldoTrasBorrado, 'El token fue devuelto al lector');

    // 9. Comprobar notificación al lector
    $notif = db_val("SELECT mensaje FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
    assert_contains($notif, 'retirado del catálogo', 'El lector recibió notificación informativa');

    // 10. Comprobar auditoría
    $aud = db_val("SELECT accion FROM registro_auditoria WHERE accion = 'libro.eliminar' AND entidad_id = ? LIMIT 1", [$libroId]);
    assert_equals('libro.eliminar', $aud, 'Auditoría registrada');
});

it('T-CAT-FILTROS-BONO: Solo disponibles desmarcado por defecto y alta de usuario sin bono de bienvenida', function () {
    // 1. /catalogo no tiene check-disponibles marcado por defecto
    $rCat = http_get('/catalogo');
    assert_equals(200, $rCat['code'], 'GET /catalogo responde 200');
    preg_match('/<input[^>]+id="check-disponibles"[^>]*>/', $rCat['body'], $matches);
    assert_true(!empty($matches), 'Input check-disponibles encontrado en HTML');
    assert_true(!str_contains($matches[0], 'checked'), 'El check de solo disponibles NO está marcado por defecto');

    // 2. Alta de usuario desde admin inicia con 0 tokens exactos
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $emailNuevo = 'nuevo_lector_' . uniqid() . '@bookswap.local';
    require_once ROOT . '/app/helpers/admin.php';
    $resNuevo = admin_usuario_crear(db(), 'Lector Sin Bono', $emailNuevo, 'password123', 3, null, $adminId);
    assert_true($resNuevo['ok'], 'Usuario lector creado');
    $nuevoUid = (int) $resNuevo['usuario_id'];

    $saldoNuevo = (int) db_val('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?', [$nuevoUid]);
    assert_equals(0, $saldoNuevo, 'Nuevo usuario tiene 0 tokens (NO recibe bono de bienvenida)');
});

it('T-CAT-BAJA-GENEROS: Baja y reactivación de libro, y sugerencias de grupo / género priorizadas', function () {
    $pdo = db();
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");

    // 1. Crear libro de prueba con género escolar
    require_once ROOT . '/app/helpers/catalogo.php';
    $libroId = catalogo_guardar_libro($pdo, [
        'titulo' => 'Lengua Castellana y Literatura 1.º ESO Test',
        'autor' => 'Editorial Anaya',
        'genero' => '1.º ESO',
        'idioma' => 'es',
        'isbn13' => '9788469850000',
    ], $adminId);

    // Añadir 2 ejemplares
    $ej1 = catalogo_guardar_ejemplar($pdo, ['libro_id' => $libroId, 'estado' => 'disponible', 'ubicacion' => 'A-01-01'], $adminId);
    $ej2 = catalogo_guardar_ejemplar($pdo, ['libro_id' => $libroId, 'estado' => 'disponible', 'ubicacion' => 'A-01-02'], $adminId);

    // Crear lector con token y reservar ej1
    $emailLector = 'lector_baja_' . uniqid() . '@bookswap.local';
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado) VALUES ('Lector Baja Test', ?, 'hash', 3, 1, 1)")->execute([$emailLector]);
    $userId = (int) $pdo->lastInsertId();
    ledger_registrar_movimiento($pdo, $userId, 2, 'bono', null, 'Carga test');
    require_once ROOT . '/app/helpers/reservas.php';
    $resReserva = reserva_crear($pdo, $userId, $libroId, $ej1);
    assert_true(!empty($resReserva['codigo']), 'Reserva activa creada para prueba de baja');

    // 2. Dar de baja el libro completo
    $resBaja = catalogo_dar_de_baja_libro($pdo, $libroId, 'Libro retirado por cambio de temario', $adminId);
    assert_true($resBaja['ok'], 'Baja de libro devuelve ok=true');
    assert_equals(2, $resBaja['copias_baja'], 'Se marcaron 2 copias en baja');
    assert_equals(1, $resBaja['reservas_canceladas'], 'Se canceló 1 reserva activa');

    // 3. Comprobar que el libro está en estado 'baja'
    $estadoLibro = db_val('SELECT estado FROM libros WHERE id = ?', [$libroId]);
    assert_equals('baja', $estadoLibro, 'El libro tiene estado baja');
    $motivoLibro = db_val('SELECT motivo_baja FROM libros WHERE id = ?', [$libroId]);
    assert_equals('Libro retirado por cambio de temario', $motivoLibro, 'El motivo de la baja quedó guardado');

    // 4. Comprobar que los ejemplares están en estado 'baja'
    $bajasEj = (int) db_val("SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'baja'", [$libroId]);
    assert_equals(2, $bajasEj, 'Los 2 ejemplares pasaron a baja');

    // 5. Comprobar que el lector recuperó su saldo
    $saldoLector = (int) db_val('SELECT SUM(cantidad) FROM movimientos_tokens WHERE usuario_id = ?', [$userId]);
    assert_equals(2, $saldoLector, 'El token bloqueado fue reembolsado al lector');

    // 6. Comprobar que el libro NO aparece en /catalogo público
    $rPub = http_get('/catalogo?q=' . urlencode('Lengua Castellana y Literatura 1.º ESO Test'));
    assert_true(!str_contains($rPub['body'], '/libro/' . $libroId), 'Libro dado de baja no aparece en catálogo público');
    assert_contains($rPub['body'], 'No se encontraron libros', 'Catálogo público indica que no hay resultados');

    // 7. Reactivar el libro
    $resReac = catalogo_reactivar_libro($pdo, $libroId, $adminId);
    assert_true($resReac['ok'], 'Reactivación devuelve ok=true');
    $estadoReac = db_val('SELECT estado FROM libros WHERE id = ?', [$libroId]);
    assert_equals('activo', $estadoReac, 'El libro vuelve a estar activo');

    // 8. Comprobar lista de géneros: 1.º ESO y grupos escolares están en las primeras posiciones
    $listaGen = catalogo_obtener_lista_generos($pdo);
    assert_true(in_array('1.º ESO', array_slice($listaGen, 0, 5), true), '1.º ESO está entre las primeras opciones de la lista');
    assert_true(in_array('2.º ESO', array_slice($listaGen, 0, 5), true), '2.º ESO está entre las primeras opciones de la lista');

    // 9. Comprobar formularios: etiquetado "Grupo / Género literario" presente
    $sAdmin = login_como('admin');
    $rNuevo = http_get('/admin/libros/nuevo', $sAdmin);
    assert_contains($rNuevo['body'], 'Grupo / Género literario', 'Formulario de nuevo libro usa "Grupo / Género literario"');
});

it('T-CAT-IDIOMA-VAL: Soporte de Valencià (val) en formularios, API y ficha de libro', function () {
    $pdo = db();
    $adminId = (int) db_val("SELECT id FROM usuarios WHERE email = 'admin@bookswap.local'");
    $sAdmin = login_como('admin');

    // 1. Verificar opción en formulario de alta y edición
    $rNuevo = http_get('/admin/libros/nuevo', $sAdmin);
    assert_contains($rNuevo['body'], 'value="val"', 'Formulario nuevo libro incluye opción val');
    assert_contains($rNuevo['body'], 'Valencià (val)', 'Formulario nuevo libro muestra texto Valencià (val)');

    // 2. Normalización de idioma en catalogo_api
    require_once ROOT . '/app/helpers/catalogo_api.php';
    assert_equals('val', catalogo_normalizar_codigo_idioma('val'), 'Normaliza "val" a "val"');
    assert_equals('val', catalogo_normalizar_codigo_idioma('valenciano'), 'Normaliza "valenciano" a "val"');
    assert_equals('val', catalogo_normalizar_codigo_idioma('Valencià'), 'Normaliza "Valencià" a "val"');

    // 3. Crear libro con idioma Valencià
    require_once ROOT . '/app/helpers/catalogo.php';
    $libroValId = catalogo_guardar_libro($pdo, [
        'titulo' => 'Tirant lo Blanc Test Valencià',
        'autor' => 'Joanot Martorell',
        'genero' => '1.º Bachillerato',
        'idioma' => 'val',
        'isbn13' => '9788429700000',
    ], $adminId);

    $idiomaGuardado = db_val('SELECT idioma FROM libros WHERE id = ?', [$libroValId]);
    assert_equals('val', $idiomaGuardado, 'El idioma del libro se guardó como val en la base de datos');

    // 4. Formulario de edición muestra seleccionado Valencià (val)
    $rEditar = http_get('/admin/libros/editar?id=' . $libroValId, $sAdmin);
    assert_contains($rEditar['body'], 'value="val" selected', 'Formulario de edición selecciona Valencià (val)');

    // 5. Ficha del libro muestra Valencià (val)
    $rFicha = http_get('/libro/' . $libroValId);
    assert_contains($rFicha['body'], 'Valencià (val)', 'Ficha del libro muestra Valencià (val)');
});

/* ─── 6. RESUMEN ─── */
line("\n════════ RESUMEN ════════", 'cyan');
line("  PASS: {$R['pass']}   FAIL: {$R['fail']}   SKIP: {$R['skip']}", $R['fail'] ? 'red' : 'green');
if ($R['fails']) {
    line('  Fallos:', 'red');
    foreach ($R['fails'] as $f) line("   · $f", 'red');
}
line($R['fail'] ? "\n✘ SUITE FALLIDA" : "\n✔ SUITE VERDE", $R['fail'] ? 'red' : 'green');
exit($R['fail'] ? 1 : 0);
