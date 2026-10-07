<?php
declare(strict_types=1);
/**
 * BookSwap · Benchmark de Rendimiento y Tiempos de Respuesta (Fase 12)
 *
 * Mide tiempos de respuesta (min/mediana/max) y peso en KB de las rutas clave
 * bajo peticiones consecutivas utilizando cURL y sesiones autenticadas.
 *
 * Uso:
 *   docker compose exec app php tests/benchmark.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', dirname(__DIR__));
define('BASE_URL', 'http://127.0.0.1');

require_once ROOT . '/config/config.php';
require_once ROOT . '/app/helpers/funciones.php';

/* ─── 1. UTILIDADES HTTP Y AUTENTICACIÓN ─── */

function bm_new_session(): array {
    return ['jar' => tempnam(sys_get_temp_dir(), 'bs_bm_cookie_'), 'last' => ''];
}

function bm_http_request(string $method, string $url, array $data = [], ?array &$s = null): array {
    $ch = curl_init(BASE_URL . $url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($s !== null) {
        $opts[CURLOPT_COOKIEJAR] = $s['jar'];
        $opts[CURLOPT_COOKIEFILE] = $s['jar'];
    }
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    curl_setopt_array($ch, $opts);

    $tInicio = microtime(true);
    $raw = curl_exec($ch);
    $tDuracionMs = (microtime(true) - $tInicio) * 1000.0;

    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("Error cURL en {$url}: {$error}");
    }

    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr($raw, $headerSize);
    if ($s !== null) {
        $s['last'] = $body;
    }

    return [
        'code'      => $code,
        'body'      => $body,
        'duracion'  => $tDuracionMs,
        'bytes'     => strlen($body),
        'url'       => $url,
    ];
}

const BM_USERS = [
    'admin'    => ['admin@bookswap.local',    'password'],
    'personal' => ['personal@bookswap.local', 'password'],
    'usuario'  => ['usuario@bookswap.local',  'password'],
];

function bm_login_como(string $rol): array {
    if (!isset(BM_USERS[$rol])) {
        throw new InvalidArgumentException("Rol desconocido para benchmark: {$rol}");
    }
    [$email, $pass] = BM_USERS[$rol];
    $s = bm_new_session();
    $rGet = bm_http_request('GET', '/login', [], $s);

    $csrf = '';
    if (preg_match('/name="csrf[_-]?token"[^>]*value="([^"]+)"/i', $rGet['body'], $m)) {
        $csrf = $m[1];
    }

    $rPost = bm_http_request('POST', '/login', [
        'csrf_token' => $csrf,
        'email'      => $email,
        'password'   => $pass,
    ], $s);

    if (!in_array($rPost['code'], [200, 302], true)) {
        throw new RuntimeException("Login fallido para {$rol} (HTTP {$rPost['code']})");
    }

    return $s;
}

/**
 * Calcula la mediana de un arreglo numérico.
 *
 * @param array $valores
 * @return float
 */
function bm_calcular_mediana(array $valores): float {
    sort($valores);
    $count = count($valores);
    if ($count === 0) return 0.0;
    $medio = (int) floor($count / 2);
    if ($count % 2 !== 0) {
        return (float) $valores[$medio];
    }
    return (float) (($valores[$medio - 1] + $valores[$medio]) / 2.0);
}

/* ─── 2. DEFINICIÓN DE RUTAS A MEDIR ─── */

$pdoBm = db();
$stmtEjBm = $pdoBm->query("SELECT id FROM ejemplares LIMIT 1");
$ejIdBm = (int) ($stmtEjBm ? $stmtEjBm->fetchColumn() : 0);
if ($ejIdBm === 0) {
    $stmtLibBm = $pdoBm->query("SELECT id FROM libros LIMIT 1");
    $libIdBm = (int) ($stmtLibBm ? $stmtLibBm->fetchColumn() : 1);
    $pdoBm->prepare("INSERT INTO ejemplares (libro_id, estado, ubicacion, condicion) VALUES (?, 'disponible', 'BM-01', 'bueno')")->execute([$libIdBm]);
    $ejIdBm = (int) $pdoBm->lastInsertId();
}

$rutas = [
    ['ruta' => '/',                   'nombre' => 'Portada (Home)',         'rol' => null],
    ['ruta' => '/catalogo',           'nombre' => 'Catálogo General',       'rol' => null],
    ['ruta' => '/catalogo?q=quijote', 'nombre' => 'Catálogo (Búsqueda)',    'rol' => null],
    ['ruta' => '/libro/1',            'nombre' => 'Ficha Libro (/libro/1)', 'rol' => null],
    ['ruta' => '/login',              'nombre' => 'Iniciar Sesión',         'rol' => null],
    ['ruta' => '/visitanos',          'nombre' => 'Visítanos (Centro)',     'rol' => null],
    ['ruta' => '/como-funciona',      'nombre' => 'Cómo Funciona',          'rol' => null],
    ['ruta' => '/ayuda',              'nombre' => 'Centro de Ayuda',        'rol' => null],
    ['ruta' => '/dashboard',          'nombre' => 'Dashboard Usuario',      'rol' => 'usuario'],
    ['ruta' => '/mostrador',          'nombre' => 'Modo Mostrador',         'rol' => 'personal'],
    ['ruta' => '/mi-historial',       'nombre' => 'Mi Historial',           'rol' => 'usuario'],
    ['ruta' => '/admin',              'nombre' => 'Panel Administración',   'rol' => 'admin'],
    ['ruta' => '/admin/roles',        'nombre' => 'Matriz de Permisos',     'rol' => 'admin'],
    ['ruta' => '/libros/entrada',     'nombre' => 'Entrada de Copias',      'rol' => 'personal'],
    ['ruta' => '/ejemplar/' . $ejIdBm, 'nombre' => 'Trazabilidad Copia',   'rol' => 'personal'],
];

/* ─── 3. EJECUCIÓN DEL BENCHMARK ─── */

$numPeticiones = 10;
$resultados = [];
$sesionesCache = [];

echo "═══════════════════════════════════════════════════════════════════════════════════════════════════════\n";
echo " BookSwap · Benchmark de Rendimiento y Consumo (Fase 12)\n";
echo " Base: " . BASE_URL . "  ·  Peticiones por ruta: {$numPeticiones}  ·  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════════════════════════════════════════════\n\n";

foreach ($rutas as $item) {
    $ruta = $item['ruta'];
    $nombre = $item['nombre'];
    $rol = $item['rol'];

    // Obtener o reutilizar sesión según rol
    $sesion = null;
    if ($rol !== null) {
        if (!isset($sesionesCache[$rol])) {
            $sesionesCache[$rol] = bm_login_como($rol);
        }
        $sesion = $sesionesCache[$rol];
    }

    $tiempos = [];
    $ultimoBytes = 0;
    $ultimoCodigo = 0;

    for ($i = 0; $i < $numPeticiones; $i++) {
        $res = bm_http_request('GET', $ruta, [], $sesion);
        $tiempos[] = $res['duracion'];
        $ultimoBytes = $res['bytes'];
        $ultimoCodigo = $res['code'];
    }

    $min = min($tiempos);
    $mediana = bm_calcular_mediana($tiempos);
    $max = max($tiempos);
    $pesoKb = round($ultimoBytes / 1024, 2);

    $resultados[] = [
        'ruta'     => $ruta,
        'nombre'   => $nombre,
        'rol'      => $rol ?? '(público)',
        'codigo'   => $ultimoCodigo,
        'min'      => $min,
        'mediana'  => $mediana,
        'max'      => $max,
        'peso_kb'  => $pesoKb,
    ];
}

/* ─── 4. PRESENTACIÓN DE LA TABLA ─── */

$mask = "| %-22s | %-11s | %-6s | %10s | %10s | %10s | %10s |\n";
$sep  = "+------------------------+-------------+--------+------------+------------+------------+------------+\n";

echo $sep;
printf($mask, "Ruta / Recurso", "Autenticado", "HTTP", "Mínimo", "Mediana", "Máximo", "Peso (KB)");
echo $sep;

$todas200 = true;
foreach ($resultados as $r) {
    if ($r['codigo'] !== 200) {
        $todas200 = false;
    }
    printf(
        $mask,
        $r['ruta'],
        $r['rol'],
        $r['codigo'],
        number_format($r['min'], 2) . ' ms',
        number_format($r['mediana'], 2) . ' ms',
        number_format($r['max'], 2) . ' ms',
        number_format($r['peso_kb'], 2) . ' KB'
    );
}
echo $sep;
echo "\nEstado general: " . ($todas200 ? "OK (todas las rutas responden 200)" : "ALERTA (alguna ruta no devolvió 200)") . "\n";
echo "Benchmark completado con éxito.\n";

exit($todas200 ? 0 : 1);
