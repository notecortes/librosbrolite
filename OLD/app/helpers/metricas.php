<?php
/**
 * BookSwap · metricas.php — Cálculo de métricas, estadísticas y datos analíticos (v4.1).
 *
 * Proporciona (§11):
 * - Distribución de copias por estado físico (disponible, reservado, retirado, baja).
 * - Total de tokens en circulación según el ledger inmutable.
 * - Histórico mensual de depósitos y entregas físicas.
 * - Ranking de libros con mayor presencia y demanda en el centro.
 */
declare(strict_types=1);

/**
 * Obtiene el conjunto completo de métricas del sistema para gráficos Chart.js y cuadros de mando.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @return array Métricas estructuradas del sistema
 */
function metricas_obtener_datos(PDO $pdo): array {
    // 1. Copias por estado
    $stmtEstados = $pdo->query('
        SELECT estado, COUNT(*) AS total
        FROM ejemplares
        GROUP BY estado
    ');
    $copiasPorEstado = [
        'disponible' => 0,
        'reservado'  => 0,
        'retirado'   => 0,
        'baja'       => 0,
    ];
    while ($row = $stmtEstados->fetch(PDO::FETCH_ASSOC)) {
        $estado = (string) $row['estado'];
        $copiasPorEstado[$estado] = (int) $row['total'];
    }

    // 2. Tokens en circulación (suma de saldos positivos de usuarios)
    $stmtTokens = $pdo->query('
        SELECT COALESCE(SUM(saldo_usuario), 0)
        FROM (
            SELECT usuario_id, SUM(cantidad) AS saldo_usuario
            FROM movimientos_tokens
            GROUP BY usuario_id
            HAVING saldo_usuario > 0
        ) AS saldos
    ');
    $tokensEnCirculacion = (int) ($stmtTokens->fetchColumn() ?: 0);

    // 3. Depósitos y entregas por mes (últimos 6 meses)
    $stmtMeses = $pdo->query("
        SELECT DATE_FORMAT(fecha_entrega, '%Y-%m') AS mes,
               tipo,
               COUNT(*) AS total
        FROM transacciones
        WHERE estado = 'entregada' AND fecha_entrega IS NOT NULL
        GROUP BY mes, tipo
        ORDER BY mes ASC
    ");
    $transPorMes = [];
    while ($row = $stmtMeses->fetch(PDO::FETCH_ASSOC)) {
        $m = (string) $row['mes'];
        $t = (string) $row['tipo'];
        $transPorMes[$m][$t] = (int) $row['total'];
    }

    $depositosPorMes = [];
    $entregasPorMes = [];
    foreach ($transPorMes as $mes => $datos) {
        $depositosPorMes[$mes] = $datos['deposito'] ?? 0;
        $entregasPorMes[$mes] = $datos['reserva'] ?? 0;
    }

    // 4. Top libros con más ejemplares en el catálogo
    $stmtTop = $pdo->query('
        SELECT l.id, l.titulo, l.autor, COUNT(e.id) AS total_ejemplares
        FROM libros l
        LEFT JOIN ejemplares e ON l.id = e.libro_id
        GROUP BY l.id, l.titulo, l.autor
        ORDER BY total_ejemplares DESC
        LIMIT 5
    ');
    $topLibros = $stmtTop ? $stmtTop->fetchAll(PDO::FETCH_ASSOC) : [];

    // 5. Totales generales del sistema
    $totales = [
        'libros'           => (int) $pdo->query('SELECT COUNT(*) FROM libros')->fetchColumn(),
        'ejemplares'       => (int) $pdo->query('SELECT COUNT(*) FROM ejemplares')->fetchColumn(),
        'socios'           => (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn(),
        'reservas_activas' => (int) $pdo->query("SELECT COUNT(*) FROM transacciones WHERE tipo = 'reserva' AND estado = 'activa'")->fetchColumn(),
    ];

    return [
        'ok'                    => true,
        'totales'               => $totales,
        'copias_por_estado'     => $copiasPorEstado,
        'tokens_en_circulacion' => $tokensEnCirculacion,
        'depositos_por_mes'     => $depositosPorMes,
        'entregas_por_mes'      => $entregasPorMes,
        'top_libros'            => $topLibros,
    ];
}
