<?php
/**
 * BookSwap · historial.php — Gestión del Historial de Libros y Tokens (v4.2).
 *
 * Proporciona consultas consolidadas del ledger de tokens con detalle bibliográfico,
 * métricas resumen por usuario y exportación en formato CSV estandarizado.
 */
declare(strict_types=1);

require_once __DIR__ . '/ledger.php';

/**
 * Obtiene el resumen consolidado de actividad y balance de un usuario.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @param int $usuarioId ID del usuario a consultar
 * @return array Métricas agregadas (libros_depositados, libros_retirados, saldo_actual, tokens_ganados, tokens_gastados)
 */
function historial_obtener_resumen(PDO $pdo, int $usuarioId): array {
    // Libros depositados: movimientos tipo 'deposito' o transacciones de depósito entregadas
    $stmtDep = $pdo->prepare("
        SELECT COUNT(*)
        FROM movimientos_tokens
        WHERE usuario_id = ? AND tipo = 'deposito'
    ");
    $stmtDep->execute([$usuarioId]);
    $librosDepositados = (int) $stmtDep->fetchColumn();

    // Libros retirados: movimientos tipo 'retiro'
    $stmtRet = $pdo->prepare("
        SELECT COUNT(*)
        FROM movimientos_tokens
        WHERE usuario_id = ? AND tipo = 'retiro'
    ");
    $stmtRet->execute([$usuarioId]);
    $librosRetirados = (int) $stmtRet->fetchColumn();

    // Saldo actual exacto según ledger
    $saldoActual = ledger_obtener_saldo($pdo, $usuarioId);

    // Tokens ganados (sumatorio de movimientos positivos)
    $stmtGan = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad), 0)
        FROM movimientos_tokens
        WHERE usuario_id = ? AND cantidad > 0
    ");
    $stmtGan->execute([$usuarioId]);
    $tokensGanados = (int) $stmtGan->fetchColumn();

    // Tokens gastados (sumatorio en positivo de movimientos negativos)
    $stmtGas = $pdo->prepare("
        SELECT COALESCE(ABS(SUM(cantidad)), 0)
        FROM movimientos_tokens
        WHERE usuario_id = ? AND cantidad < 0
    ");
    $stmtGas->execute([$usuarioId]);
    $tokensGastados = (int) $stmtGas->fetchColumn();

    return [
        'libros_depositados' => $librosDepositados,
        'libros_retirados'   => $librosRetirados,
        'saldo_actual'       => $saldoActual,
        'tokens_ganados'     => $tokensGanados,
        'tokens_gastados'    => $tokensGastados,
    ];
}

/**
 * Lista los movimientos del historial con enlace a libros y transacciones, con filtros y paginación.
 *
 * @param PDO   $pdo        Instancia de conexión PDO
 * @param int   $usuarioId  ID del usuario
 * @param array $filtros    Filtros opcionales ('tipo', 'desde', 'hasta')
 * @param int   $pagina     Página actual (1-indexed)
 * @param int   $porPagina  Número de elementos por página
 * @return array Estructura con movimientos, total, página y totalPaginas
 */
function historial_obtener_movimientos(
    PDO $pdo,
    int $usuarioId,
    array $filtros = [],
    int $pagina = 1,
    int $porPagina = 20
): array {
    $where = ['m.usuario_id = :usuario_id'];
    $params = [':usuario_id' => $usuarioId];

    if (!empty($filtros['tipo'])) {
        $where[] = 'm.tipo = :tipo';
        $params[':tipo'] = $filtros['tipo'];
    }

    if (!empty($filtros['desde'])) {
        $where[] = 'm.fecha >= :desde';
        $params[':desde'] = $filtros['desde'] . ' 00:00:00';
    }

    if (!empty($filtros['hasta'])) {
        $where[] = 'm.fecha <= :hasta';
        $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
    }

    $whereSql = implode(' AND ', $where);

    // Contar total de filas para paginación
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM movimientos_tokens m WHERE {$whereSql}");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $porPagina;

    // Consulta con JOINs a transacciones, ejemplares y libros
    $sql = "
        SELECT 
            m.id,
            m.usuario_id,
            m.cantidad,
            m.tipo,
            m.transaccion_id,
            m.concepto,
            m.saldo_resultante,
            m.fecha,
            t.codigo AS codigo_reserva,
            t.metodo_pago AS tx_metodo_pago,
            t.tipo AS transaccion_tipo,
            l.id AS libro_id,
            l.titulo AS libro_titulo,
            l.autor AS libro_autor
        FROM movimientos_tokens m
        LEFT JOIN transacciones t ON m.transaccion_id = t.id
        LEFT JOIN ejemplares e ON t.ejemplar_id = e.id
        LEFT JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
        ORDER BY m.fecha DESC, m.id DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolver método de pago normalizado
    foreach ($filas as &$row) {
        if (!empty($row['tx_metodo_pago'])) {
            $row['metodo_pago'] = $row['tx_metodo_pago'];
        } elseif ($row['tipo'] === 'deposito') {
            $row['metodo_pago'] = 'libro';
        } elseif ($row['tipo'] === 'retiro' || in_array($row['tipo'], ['bloqueo_reserva', 'liberacion_reserva'], true)) {
            $row['metodo_pago'] = 'tokens';
        } elseif (str_starts_with($row['tipo'], 'bono')) {
            $row['metodo_pago'] = 'bono';
        } else {
            $row['metodo_pago'] = 'ajuste';
        }
    }
    unset($row);

    return [
        'movimientos'  => $filas,
        'total'        => $total,
        'pagina'       => $pagina,
        'totalPaginas' => max(1, (int) ceil($total / $porPagina)),
        'porPagina'    => $porPagina,
    ];
}

/**
 * Exporta el historial completo de un usuario en formato CSV con delimitador ';'.
 * Formato de cabecera: fecha;tipo;libro;autor;metodo_pago;cantidad;saldo_resultante;concepto;codigo_reserva
 *
 * @param PDO   $pdo       Instancia de conexión PDO
 * @param int   $usuarioId ID del usuario
 * @param array $filtros   Filtros aplicados ('tipo', 'desde', 'hasta')
 * @return string Contenido CSV listo para descargar
 */
function historial_exportar_csv(PDO $pdo, int $usuarioId, array $filtros = []): string {
    $where = ['m.usuario_id = :usuario_id'];
    $params = [':usuario_id' => $usuarioId];

    if (!empty($filtros['tipo'])) {
        $where[] = 'm.tipo = :tipo';
        $params[':tipo'] = $filtros['tipo'];
    }

    if (!empty($filtros['desde'])) {
        $where[] = 'm.fecha >= :desde';
        $params[':desde'] = $filtros['desde'] . ' 00:00:00';
    }

    if (!empty($filtros['hasta'])) {
        $where[] = 'm.fecha <= :hasta';
        $params[':hasta'] = $filtros['hasta'] . ' 23:59:59';
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT 
            m.id,
            m.usuario_id,
            m.cantidad,
            m.tipo,
            m.transaccion_id,
            m.concepto,
            m.saldo_resultante,
            m.fecha,
            t.codigo AS codigo_reserva,
            t.metodo_pago AS tx_metodo_pago,
            t.tipo AS transaccion_tipo,
            l.id AS libro_id,
            l.titulo AS libro_titulo,
            l.autor AS libro_autor
        FROM movimientos_tokens m
        LEFT JOIN transacciones t ON m.transaccion_id = t.id
        LEFT JOIN ejemplares e ON t.ejemplar_id = e.id
        LEFT JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
        ORDER BY m.fecha DESC, m.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stream = fopen('php://memory', 'w+');
    // Cabecera canónica según INCREMENTO v4.2 §3.4
    fputcsv($stream, [
        'fecha',
        'tipo',
        'libro',
        'autor',
        'metodo_pago',
        'cantidad',
        'saldo_resultante',
        'concepto',
        'codigo_reserva',
    ], ';');

    foreach ($filas as $f) {
        $metodoPago = !empty($f['tx_metodo_pago'])
            ? $f['tx_metodo_pago']
            : ($f['tipo'] === 'deposito' ? 'libro' : ($f['tipo'] === 'retiro' ? 'tokens' : 'bono'));

        fputcsv($stream, [
            $f['fecha'],
            $f['tipo'],
            $f['libro_titulo'] ?? '',
            $f['libro_autor'] ?? '',
            $metodoPago,
            $f['cantidad'],
            $f['saldo_resultante'],
            $f['concepto'],
            $f['codigo_reserva'] ?? '',
        ], ';');
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    return $csv !== false ? $csv : '';
}
