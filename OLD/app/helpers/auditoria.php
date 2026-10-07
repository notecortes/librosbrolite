<?php
/**
 * BookSwap · auditoria.php — Registro inmutable de eventos y auditoría del sistema (v4.2).
 */
declare(strict_types=1);

/**
 * Registra una acción de auditoría en la tabla registro_auditoria.
 *
 * @param int|null $usuario_id ID del usuario que ejecuta la acción (o null si es sistema)
 * @param string $accion Nombre clave de la acción (ej: 'login_local', 'usuario.crear')
 * @param string|null $entidad Nombre de la tabla o entidad afectada
 * @param int|null $entidad_id ID del registro afectado en la entidad
 * @param array|null $detalle Datos adicionales estructurados que se guardan en formato JSON
 * @param string|null $ip Dirección IP del cliente (si es null se detecta automáticamente)
 */
function log_accion(
    ?int $usuario_id,
    string $accion,
    ?string $entidad = null,
    ?int $entidad_id = null,
    ?array $detalle = null,
    ?string $ip = null
): void {
    try {
        $pdo = db();
        $ipCliente = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $jsonDetalle = $detalle !== null ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare('
            INSERT INTO registro_auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip, fecha)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $usuario_id,
            $accion,
            $entidad,
            $entidad_id,
            $jsonDetalle,
            $ipCliente,
        ]);
    } catch (Throwable $e) {
        error_log('Error en log_accion: ' . $e->getMessage());
    }
}

/**
 * Alias estandarizado para registrar eventos de auditoría con PDO explícito.
 *
 * @param PDO $pdo Instancia de base de datos
 * @param string $accion Nombre de la acción realizada
 * @param string|null $entidad Entidad o tabla afectada
 * @param int|null $entidad_id Identificador del registro modificado
 * @param array|null $detalle Información adicional en formato estructurado
 * @param int|null $usuario_id Identificador del usuario que ejecuta la acción
 */
function auditoria_registrar(
    PDO $pdo,
    string $accion,
    ?string $entidad = null,
    ?int $entidad_id = null,
    ?array $detalle = null,
    ?int $usuario_id = null
): void {
    log_accion($usuario_id, $accion, $entidad, $entidad_id, $detalle);
}

/**
 * Consulta y lista el registro de auditoría con soporte para filtros y paginación.
 *
 * @param PDO $pdo Conexión a la base de datos
 * @param array $filtros Filtros aplicables: accion, entidad, usuario_id, desde, hasta, q
 * @param int $pagina Número de página actual (1-indexed)
 * @param int $porPagina Cantidad de elementos por página
 * @return array Arreglo con registros, total, pagina, por_pagina y total_paginas
 */
function auditoria_listar(PDO $pdo, array $filtros = [], int $pagina = 1, int $porPagina = 25): array {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['accion'])) {
        $where[] = 'a.accion = ?';
        $params[] = $filtros['accion'];
    }

    if (!empty($filtros['entidad'])) {
        $where[] = 'a.entidad = ?';
        $params[] = $filtros['entidad'];
    }

    if (!empty($filtros['usuario_id'])) {
        $where[] = 'a.usuario_id = ?';
        $params[] = (int) $filtros['usuario_id'];
    }

    if (!empty($filtros['desde'])) {
        $where[] = 'a.fecha >= ?';
        $params[] = $filtros['desde'] . ' 00:00:00';
    }

    if (!empty($filtros['hasta'])) {
        $where[] = 'a.fecha <= ?';
        $params[] = $filtros['hasta'] . ' 23:59:59';
    }

    if (!empty($filtros['q'])) {
        $where[] = '(a.accion LIKE ? OR a.entidad LIKE ? OR a.detalle LIKE ? OR u.nombre LIKE ? OR u.email LIKE ?)';
        $term = '%' . $filtros['q'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $where);

    // Contar total
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM registro_auditoria a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE {$whereSql}
    ");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $offset = ($pagina - 1) * $porPagina;

    // Obtener registros
    $stmt = $pdo->prepare("
        SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
        FROM registro_auditoria a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE {$whereSql}
        ORDER BY a.fecha DESC, a.id DESC
        LIMIT {$porPagina} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'registros'     => $registros,
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => $totalPaginas,
    ];
}

/**
 * Obtiene la lista única de acciones registradas en el sistema para selectores.
 *
 * @param PDO $pdo Conexión a la base de datos
 * @return array Lista de strings con los nombres de las acciones
 */
function auditoria_acciones_disponibles(PDO $pdo): array {
    $stmt = $pdo->query('SELECT DISTINCT accion FROM registro_auditoria ORDER BY accion ASC');
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Obtiene la lista única de entidades auditadas en el sistema para selectores.
 *
 * @param PDO $pdo Conexión a la base de datos
 * @return array Lista de strings con los nombres de las entidades
 */
function auditoria_entidades_disponibles(PDO $pdo): array {
    $stmt = $pdo->query('SELECT DISTINCT entidad FROM registro_auditoria WHERE entidad IS NOT NULL AND entidad != "" ORDER BY entidad ASC');
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
