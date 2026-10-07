<?php
/**
 * BookSwap · bin/expirar.php (v4.1)
 *
 * Tarea periódica de expiración de reservas vencidas.
 * Ejecuta reserva_expirar_vencidas(), liberando ejemplares a 'disponible'
 * y notificando a los usuarios afectados.
 *
 * Uso:
 *   php bin/expirar.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/helpers/reservas.php';

try {
    $pdo = db();
    $res = reserva_expirar_vencidas($pdo);
    $expiradas = $res['expiradas'] ?? 0;
    $fecha = date('Y-m-d H:i:s');
    echo "[{$fecha}] Expiración ejecutada con éxito. Reservas vencidas procesadas: {$expiradas}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] Fallo al expirar reservas: " . $e->getMessage() . "\n");
    exit(1);
}
