<?php
/**
 * BookSwap · permisos.php — Gestión centralizada de permisos y RBAC (v4.5).
 *
 * Funcionalidades:
 * - Diccionario completo de permisos organizados por grupos funcionales.
 * - Comprobación de permisos en sesión con cacheado y regeneración automática: puede(string $codigo).
 * - Enforcing de permisos mínimos (BASE) inmutables.
 * - Actualización transaccional de permisos para el rol PERSONAL con registro de auditoría detallado.
 * - Restauración de la matriz por defecto del seed canónico.
 * - Guardia de rutas del router: exigir_permiso(string|array $permisos).
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';

/**
 * Retorna el diccionario canónico de permisos de la aplicación.
 *
 * @return array<string, array{nombre: string, descripcion: string, grupo: string}>
 */
function permisos_dict(): array {
    return [
        // BASE (siempre activas, NO editables, checkbox disabled)
        'usuario.base' => [
            'nombre' => 'Usar la aplicación',
            'descripcion' => 'Acceso general y uso básico de la plataforma',
            'grupo' => 'BASE',
        ],
        'catalogo.ver' => [
            'nombre' => 'Ver el catálogo',
            'descripcion' => 'Consultar los libros y ejemplares disponibles',
            'grupo' => 'BASE',
        ],
        'reserva.crear' => [
            'nombre' => 'Reservar libros',
            'descripcion' => 'Realizar reservas de ejemplares disponibles en el catálogo',
            'grupo' => 'BASE',
        ],

        // CATÁLOGO
        'catalogo.editar' => [
            'nombre' => 'Crear y editar libros y ejemplares',
            'descripcion' => 'Alta, edición y catalogación de libros y ejemplares físicos',
            'grupo' => 'CATÁLOGO',
        ],
        'csv.importar' => [
            'nombre' => 'Importar catálogo CSV',
            'descripcion' => 'Importación masiva de catálogo de libros por archivo CSV',
            'grupo' => 'CATÁLOGO',
        ],

        // MOSTRADOR
        'mostrador.acceder' => [
            'nombre' => 'Acceder al Modo Mostrador',
            'descripcion' => 'Acceso a la interfaz de atención de mostrador y escáner universal',
            'grupo' => 'MOSTRADOR',
        ],
        'entrega.confirmar' => [
            'nombre' => 'Entregar libros: reservas y entrega directa',
            'descripcion' => 'Confirmar entregas de reservas y procesar entregas directas',
            'grupo' => 'MOSTRADOR',
        ],
        'deposito.registrar' => [
            'nombre' => 'Registrar depósitos',
            'descripcion' => 'Recepción y registro de libros depositados por usuarios',
            'grupo' => 'MOSTRADOR',
        ],

        // PERSONAS
        'usuarios.gestionar' => [
            'nombre' => 'Gestionar usuarios',
            'descripcion' => 'Crear, editar, cambiar roles, activar/desactivar y ajustes de tokens',
            'grupo' => 'PERSONAS',
        ],

        // SISTEMA
        'config.editar' => [
            'nombre' => 'Editar configuración',
            'descripcion' => 'Modificar parámetros del centro, préstamos y economía',
            'grupo' => 'SISTEMA',
        ],
        'backup.gestionar' => [
            'nombre' => 'Gestionar copias de seguridad',
            'descripcion' => 'Creación y descarga de copias de seguridad de la base de datos',
            'grupo' => 'SISTEMA',
        ],
        'restaurar.ejecutar' => [
            'nombre' => 'Restaurar copias de seguridad',
            'descripcion' => 'Restauración de copias de seguridad sobre la base de datos',
            'grupo' => 'SISTEMA',
        ],
        'auditoria.ver' => [
            'nombre' => 'Ver registro de auditoría',
            'descripcion' => 'Consulta del registro de auditoría y traza de operaciones',
            'grupo' => 'SISTEMA',
        ],
        'metricas.ver' => [
            'nombre' => 'Ver métricas e informes',
            'descripcion' => 'Visualización de estadísticas y métricas del centro',
            'grupo' => 'SISTEMA',
        ],
        'roles.gestionar' => [
            'nombre' => 'Configurar permisos del personal',
            'descripcion' => 'Gestión y configuración de la matriz de permisos para el personal',
            'grupo' => 'SISTEMA',
        ],
    ];
}

/**
 * Retorna los permisos base no modificables que todo usuario autenticado posee.
 *
 * @return string[]
 */
function permisos_base(): array {
    return ['usuario.base', 'catalogo.ver', 'reserva.crear'];
}

/**
 * Retorna los permisos por defecto del rol PERSONAL según el seed canónico v4.2.
 *
 * @return string[]
 */
function permisos_seed_personal(): array {
    return [
        'usuario.base',
        'catalogo.ver',
        'reserva.crear',
        'mostrador.acceder',
        'catalogo.editar',
        'deposito.registrar',
        'entrega.confirmar',
        'csv.importar',
    ];
}

/**
 * Obtiene la versión actual de la caché de permisos para invalidación global.
 */
function permisos_version_actual(): int {
    $ruta = sys_get_temp_dir() . '/bookswap_permisos_version.txt';
    if (!file_exists($ruta)) {
        return 1;
    }
    return (int) @file_get_contents($ruta);
}

/**
 * Incrementa la versión de permisos para forzar la regeneración de sesión en todas las cuentas activas.
 */
function permisos_marcar_regeneracion(): void {
    $ruta = sys_get_temp_dir() . '/bookswap_permisos_version.txt';
    $nueva = permisos_version_actual() + 1;
    @file_put_contents($ruta, (string) $nueva);
}

/**
 * Carga los permisos de un usuario dado desde la base de datos y opcionalmente actualiza la sesión.
 *
 * @return string[]
 */
function permisos_cargar_usuario(?PDO $pdo = null, ?int $usuarioId = null): array {
    global $usuario;
    $uid = $usuarioId ?? ($_SESSION['usuario_id'] ?? ($usuario['id'] ?? null));
    if (!$uid) {
        if (isset($_SESSION)) {
            $_SESSION['permisos'] = [];
            $_SESSION['permisos_version'] = permisos_version_actual();
        }
        return [];
    }

    try {
        $pdo = $pdo ?? db();
        $stmt = $pdo->prepare('
            SELECT p.codigo
            FROM permisos p
            JOIN rol_permiso rp ON p.id = rp.permiso_id
            JOIN usuarios u ON u.rol_id = rp.rol_id
            WHERE u.id = ? AND u.activo = 1
        ');
        $stmt->execute([(int) $uid]);
        $permisos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Asegurar que si es ADMIN disponga siempre de todas las capacidades registradas
        $stmtRol = $pdo->prepare('SELECT r.nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?');
        $stmtRol->execute([(int) $uid]);
        $nombreRol = (string) $stmtRol->fetchColumn();
        if ($nombreRol === 'ADMIN') {
            $todos = array_keys(permisos_dict());
            $permisos = array_values(array_unique(array_merge($permisos, $todos)));
        }
    } catch (Throwable $e) {
        error_log('Error al cargar permisos para usuario ' . $uid . ': ' . $e->getMessage());
        $permisos = [];
    }

    if (isset($_SESSION)) {
        $_SESSION['permisos'] = $permisos;
        $_SESSION['permisos_version'] = permisos_version_actual();
    }

    return $permisos;
}

/**
 * Regenera la lista de permisos en la sesión del usuario actual.
 *
 * @return string[]
 */
function permisos_regenerar_sesion(?PDO $pdo = null, ?int $usuarioId = null): array {
    return permisos_cargar_usuario($pdo, $usuarioId);
}

/**
 * Comprueba si el usuario autenticado en la sesión actual posee el permiso indicado.
 *
 * @param string $codigo Código del permiso a verificar (e.g. 'entrega.confirmar')
 * @return bool True si el usuario tiene el permiso, false en caso contrario
 */
function puede(string $codigo): bool {
    global $usuario;

    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            @session_start();
        }
    }

    $uid = $_SESSION['usuario_id'] ?? ($usuario['id'] ?? null);
    if (!$uid) {
        return false;
    }

    $verActual = permisos_version_actual();
    if (!isset($_SESSION['permisos']) || ($_SESSION['permisos_version'] ?? 0) !== $verActual) {
        permisos_regenerar_sesion(null, (int) $uid);
    }

    $permisosUsuario = $_SESSION['permisos'] ?? [];
    return in_array($codigo, $permisosUsuario, true);
}

/**
 * Exige que el usuario disponga de al menos uno de los permisos requeridos; de lo contrario aborta con 403.
 *
 * @param string|string[] $permisos Código o lista de códigos de permisos
 */
function exigir_permiso(string|array $permisos): void {
    global $usuario;

    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            @session_start();
        }
    }

    if (empty($_SESSION['usuario_id']) && empty($usuario)) {
        header('Location: /login');
        exit;
    }

    $lista = is_array($permisos) ? $permisos : [$permisos];
    $autorizado = false;
    foreach ($lista as $p) {
        if (puede($p)) {
            $autorizado = true;
            break;
        }
    }

    if (!$autorizado) {
        http_response_code(403);
        $mensajeAmable = 'No tienes permiso para esta acción; contacta con el administrador.';
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>403 Prohibido · LibrosBro</title><link rel="stylesheet" href="/assets/css/theme.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></head><body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-4"><div class="card border-0 shadow-sm rounded-4 p-5 text-center" style="max-width:520px;"><div class="display-4 text-warning mb-3"><i class="bi bi-shield-lock"></i></div><h1 class="h4 fw-bold mb-2">Acceso Denegado</h1><p class="text-muted mb-4">' . htmlspecialchars($mensajeAmable, ENT_QUOTES, 'UTF-8') . '</p><div class="d-flex gap-2 justify-content-center"><a href="/" class="btn btn-outline-secondary">Volver al inicio</a><a href="/login" class="btn btn-primary">Iniciar sesión</a></div></div></body></html>';
        exit;
    }
}

/**
 * Obtiene los códigos de permisos asignados a un rol específico en la base de datos.
 *
 * @return string[]
 */
function permisos_por_rol(PDO $pdo, int $rolId): array {
    $stmt = $pdo->prepare('
        SELECT p.codigo
        FROM permisos p
        JOIN rol_permiso rp ON p.id = rp.permiso_id
        WHERE rp.rol_id = ?
    ');
    $stmt->execute([$rolId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Actualiza la matriz de permisos para el rol PERSONAL (rol_id = 2).
 * Aplica validaciones, garantiza permisos BASE, audita cada cambio y regenera la caché.
 *
 * @param PDO $pdo
 * @param string[] $nuevosCodigos Lista de códigos seleccionados para PERSONAL
 * @param int $operadorId ID del usuario que ejecuta la acción
 * @return array{ok: bool, aviso?: string, cambios: int}
 */
function permisos_actualizar_personal(PDO $pdo, array $nuevosCodigos, int $operadorId): array {
    $dict = permisos_dict();
    $base = permisos_base();

    // 1. Filtrar solo códigos válidos del diccionario
    $solicitados = array_intersect($nuevosCodigos, array_keys($dict));

    // 2. Garantizar que los permisos BASE siempre estén incluidos (T-ROLE-04)
    $finales = array_values(array_unique(array_merge($solicitados, $base)));

    // 3. Obtener permisos actuales de PERSONAL (rol_id = 2)
    $actuales = permisos_por_rol($pdo, 2);

    // 4. Iniciar transacción atómica
    $transaccionPropia = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transaccionPropia = true;
    }

    try {
        $cambiosCount = 0;

        // 5. Auditar cada cambio individual (anterior -> nuevo)
        foreach (array_keys($dict) as $codigo) {
            $estabaActivo = in_array($codigo, $actuales, true);
            $quedaActivo = in_array($codigo, $finales, true);

            if ($estabaActivo !== $quedaActivo) {
                auditoria_registrar(
                    $pdo,
                    'rol_permiso.actualizar',
                    'rol_permiso',
                    2,
                    [
                        'permiso' => $codigo,
                        'anterior' => $estabaActivo ? 1 : 0,
                        'nuevo' => $quedaActivo ? 1 : 0,
                    ],
                    $operadorId
                );
                $cambiosCount++;
            }
        }

        // 6. Mapear códigos a IDs de permisos
        $stmtMap = $pdo->query('SELECT id, codigo FROM permisos');
        $map = $stmtMap->fetchAll(PDO::FETCH_KEY_PAIR); // id => codigo
        $idPorCodigo = array_flip($map);

        // 7. Reemplazar registros en rol_permiso para rol_id = 2
        $stmtDel = $pdo->prepare('DELETE FROM rol_permiso WHERE rol_id = 2');
        $stmtDel->execute();

        $stmtIns = $pdo->prepare('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (2, ?)');
        foreach ($finales as $cod) {
            if (isset($idPorCodigo[$cod])) {
                $stmtIns->execute([$idPorCodigo[$cod]]);
            }
        }

        if ($transaccionPropia) {
            $pdo->commit();
        }

        // 8. Forzar regeneración de caché de permisos para todas las sesiones
        permisos_marcar_regeneracion();
        if (isset($_SESSION['usuario_id'])) {
            permisos_regenerar_sesion($pdo, (int) $_SESSION['usuario_id']);
        }

        // 9. Verificar aviso no bloqueante si PERSONAL queda sin operativa de mostrador
        $aviso = null;
        $tieneMostrador = in_array('mostrador.acceder', $finales, true);
        $tieneAccion = in_array('entrega.confirmar', $finales, true) || in_array('deposito.registrar', $finales, true);

        if (!$tieneMostrador || !$tieneAccion) {
            $aviso = 'Aviso: El rol Personal no tiene asignada ninguna operativa completa de mostrador (requiere "mostrador.acceder" y al menos entrega o depósito).';
        }

        return ['ok' => true, 'aviso' => $aviso, 'cambios' => $cambiosCount];
    } catch (Throwable $e) {
        if ($transaccionPropia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Restaura los permisos del rol PERSONAL a sus valores por defecto del seed canónico.
 *
 * @param PDO $pdo
 * @param int $operadorId ID del usuario que ejecuta la acción
 * @return array{ok: bool, cambios: int, aviso?: string}
 */
function permisos_restaurar_defaults(PDO $pdo, int $operadorId): array {
    $defaults = permisos_seed_personal();
    $resultado = permisos_actualizar_personal($pdo, $defaults, $operadorId);

    // Registro adicional en auditoría
    auditoria_registrar(
        $pdo,
        'rol_permiso.restaurar_defaults',
        'rol_permiso',
        2,
        ['accion' => 'restaurar_defaults_seed'],
        $operadorId
    );

    return $resultado;
}
