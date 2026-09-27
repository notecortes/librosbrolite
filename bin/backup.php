<?php
/**
 * BookSwap · backup.php — Ejecución de copia de seguridad automática vía CLI / Cron (v4.1).
 *
 * Uso:
 *   php bin/backup.php [--force]
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/config.php';
require_once ROOT . '/app/helpers/funciones.php';
require_once ROOT . '/app/helpers/auditoria.php';
require_once ROOT . '/app/helpers/backup.php';

try {
    $pdo = db();
    $forzar = in_array('--force', $argv, true);

    $stmtConf = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('dias_backup_auto', 'retencion_backups', 'ultimo_backup_auto')");
    $conf = $stmtConf->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $dias = (int) ($conf['dias_backup_auto'] ?? 7);
    $retencion = (int) ($conf['retencion_backups'] ?? 10);
    $ultimo = $conf['ultimo_backup_auto'] ?? null;

    $debeEjecutar = $forzar;
    if (!$debeEjecutar) {
        if ($dias <= 0) {
            echo "[BookSwap Backup] Copias automáticas deshabilitadas (dias_backup_auto = 0).\n";
            exit(0);
        }

        if (empty($ultimo)) {
            $debeEjecutar = true;
        } else {
            $ultimoTs = strtotime($ultimo);
            $segundosEspera = $dias * 86400;
            if ((time() - $ultimoTs) >= $segundosEspera) {
                $debeEjecutar = true;
            }
        }
    }

    if (!$debeEjecutar) {
        echo "[BookSwap Backup] No es necesario ejecutar backup hoy. Último: " . ($ultimo ?? 'nunca') . ".\n";
        exit(0);
    }

    echo "[BookSwap Backup] Iniciando copia de seguridad automática...\n";
    $res = backup_crear($pdo, 'auto', null);
    echo "[BookSwap Backup] Copia creada exitosamente: {$res['archivo']} ({$res['tamano']} bytes).\n";

    // Actualizar fecha del último backup automático
    $stmtUpd = $pdo->prepare("UPDATE configuracion SET valor = NOW() WHERE clave = 'ultimo_backup_auto'");
    $stmtUpd->execute();

    // Aplicar retención
    $purgadas = backup_aplicar_retencion($pdo, $retencion);
    if ($purgadas > 0) {
        echo "[BookSwap Backup] Se eliminaron {$purgadas} copias antiguas por superar la retención de {$retencion}.\n";
    }

    echo "[BookSwap Backup] Proceso completado con éxito.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[BookSwap Backup ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
