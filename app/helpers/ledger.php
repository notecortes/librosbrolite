<?php
/**
 * BookSwap · ledger.php — Libro Mayor (Ledger) inmutable de tokens (v4.1).
 *
 * Principios económicos:
 * - Toda variación de saldo queda reflejada en la tabla movimientos_tokens.
 * - Los registros de movimientos_tokens son estrictamente inmutables (solo INSERT).
 * - En cualquier instante, el saldo de un usuario es exactamente igual a SUM(cantidad).
 * - Se prohíbe taxativamente cualquier operación que resulte en un saldo negativo.
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';

/**
 * Obtiene el saldo total actual de tokens de un usuario calculándolo desde el ledger.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @param int $usuarioId ID del usuario a consultar
 * @return int Saldo total acumulado (suma de movimientos)
 */
function ledger_obtener_saldo(PDO $pdo, int $usuarioId): int {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(cantidad), 0)
        FROM movimientos_tokens
        WHERE usuario_id = ?
    ');
    $stmt->execute([$usuarioId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Registra un movimiento inmutable en el ledger de tokens asegurando coherencia y no negatividad.
 *
 * @param PDO         $pdo            Instancia de conexión PDO
 * @param int         $usuarioId      ID del usuario beneficiario o deudor
 * @param int         $cantidad       Cantidad con signo (+N para abonos, -N para cargos)
 * @param string      $tipo           Tipo de movimiento: 'deposito', 'retiro', 'bono', 'bono_bienvenida', 'ajuste'
 * @param int|null    $transaccionId  ID opcional de la transacción asociada
 * @param string      $concepto       Descripción legible del motivo del movimiento
 * @return int ID del movimiento recién insertado en el ledger
 * @throws Exception Si el tipo es inválido o si la operación deja el saldo resultante en negativo
 */
function ledger_registrar_movimiento(
    PDO $pdo,
    int $usuarioId,
    int $cantidad,
    string $tipo,
    ?int $transaccionId,
    string $concepto
): int {
    $tiposPermitidos = ['deposito', 'retiro', 'bono', 'bono_bienvenida', 'ajuste', 'bloqueo_reserva', 'liberacion_reserva'];
    if (!in_array($tipo, $tiposPermitidos, true)) {
        throw new InvalidArgumentException("Tipo de movimiento inválido: {$tipo}");
    }

    $iniciaTransaccion = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciaTransaccion = true;
    }

    try {
        // Bloqueo pesimista del usuario para garantizar exclusión mutua estricta entre operaciones concurrentes
        $stmtUserLock = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
        $stmtUserLock->execute([$usuarioId]);

        // Bloqueo pesimista sobre el último movimiento del usuario para evitar condiciones de carrera
        $stmtLock = $pdo->prepare('
            SELECT saldo_resultante
            FROM movimientos_tokens
            WHERE usuario_id = ?
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ');
        $stmtLock->execute([$usuarioId]);
        $ultimoSaldoCol = $stmtLock->fetchColumn();

        if ($ultimoSaldoCol !== false) {
            $saldoActual = (int) $ultimoSaldoCol;
        } else {
            // Si no hay movimientos previos, calcular desde suma (será 0)
            $stmtSum = $pdo->prepare('
                SELECT COALESCE(SUM(cantidad), 0)
                FROM movimientos_tokens
                WHERE usuario_id = ?
            ');
            $stmtSum->execute([$usuarioId]);
            $saldoActual = (int) $stmtSum->fetchColumn();
        }

        $saldoResultante = $saldoActual + $cantidad;

        // Regla inviolable: el saldo jamás puede ser negativo
        if ($saldoResultante < 0) {
            throw new Exception("Saldo insuficiente de tokens. Saldo actual: {$saldoActual}, requerido: " . abs($cantidad));
        }

        $stmtIns = $pdo->prepare('
            INSERT INTO movimientos_tokens (usuario_id, cantidad, tipo, transaccion_id, concepto, saldo_resultante, fecha)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmtIns->execute([
            $usuarioId,
            $cantidad,
            $tipo,
            $transaccionId,
            $concepto,
            $saldoResultante,
        ]);
        $movimientoId = (int) $pdo->lastInsertId();

        if ($iniciaTransaccion) {
            $pdo->commit();
        }

        return $movimientoId;
    } catch (Throwable $e) {
        if ($iniciaTransaccion && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Devuelve el historial cronológico de movimientos del ledger de un usuario con paginación.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @param int $usuarioId ID del usuario
 * @param int $limite Límite de registros a devolver
 * @param int $offset Desplazamiento inicial
 * @return array Lista asociativa de movimientos
 */
function ledger_listar_movimientos(PDO $pdo, int $usuarioId, int $limite = 50, int $offset = 0): array {
    $stmt = $pdo->prepare('
        SELECT m.*, t.codigo AS transaccion_codigo, t.tipo AS transaccion_tipo
        FROM movimientos_tokens m
        LEFT JOIN transacciones t ON m.transaccion_id = t.id
        WHERE m.usuario_id = ?
        ORDER BY m.id DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limite, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
