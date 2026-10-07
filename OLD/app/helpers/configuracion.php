<?php
/**
 * BookSwap · configuracion.php — Helper de gestión y auditoría de configuración (v4.1).
 *
 * Funcionalidades:
 * - Actualización de parámetros del sistema con registro de auditoría estricto (T-AUDIT-02).
 * - Comparación de valor anterior vs nuevo para evitar entradas redundantes.
 * - Validación y saneamiento de claves económicas, operativas y del punto físico.
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';

/**
 * Actualiza una clave de configuración registrando el cambio en registro_auditoria con valor anterior y nuevo.
 *
 * @param PDO         $pdo          Instancia de conexión PDO
 * @param string      $clave        Nombre clave de la configuración (ej: 'coste_libro')
 * @param string|null $valorNuevo   Nuevo valor a establecer
 * @param int|null    $usuarioId    ID del usuario que realiza la modificación (o null si es sistema)
 * @return bool True si se actualizó el valor, false si no hubo cambios
 */
function config_actualizar_clave(PDO $pdo, string $clave, ?string $valorNuevo, ?int $usuarioId = null): bool {
    $stmtAnt = $pdo->prepare('SELECT valor FROM configuracion WHERE clave = ? LIMIT 1');
    $stmtAnt->execute([$clave]);
    $valorAnterior = $stmtAnt->fetchColumn();

    if ($valorAnterior === false) {
        $valorAnterior = null;
    } else {
        $valorAnterior = (string) $valorAnterior;
    }

    $valNuevoNormalizado = $valorNuevo !== null ? (string) $valorNuevo : null;

    // Si el valor no ha cambiado, no es necesario actualizar ni auditar
    if ($valorAnterior === $valNuevoNormalizado) {
        return false;
    }

    $stmtUpd = $pdo->prepare('
        INSERT INTO configuracion (clave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ');
    $stmtUpd->execute([$clave, $valNuevoNormalizado]);

    // T-AUDIT-02: Registrar en auditoría el valor anterior y el nuevo valor
    log_accion(
        $usuarioId,
        'config_modificada',
        'configuracion',
        null,
        [
            'clave'          => $clave,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo'    => $valNuevoNormalizado,
            'anterior'       => $valorAnterior,
            'nuevo'          => $valNuevoNormalizado,
        ]
    );

    return true;
}

/**
 * Actualiza un conjunto asociativo de claves de configuración en lote.
 *
 * @param PDO      $pdo        Instancia de conexión PDO
 * @param array    $valores    Array asociativo [clave => valor]
 * @param int|null $usuarioId  ID del usuario que ejecuta los cambios
 * @return int Cantidad de claves que efectivamente cambiaron de valor
 */
function config_actualizar_multiples(PDO $pdo, array $valores, ?int $usuarioId = null): int {
    $cambios = 0;
    foreach ($valores as $clave => $valor) {
        if (config_actualizar_clave($pdo, (string) $clave, $valor !== null ? (string) $valor : null, $usuarioId)) {
            $cambios++;
        }
    }
    return $cambios;
}
