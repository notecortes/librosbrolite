<?php
/**
 * BookSwap · notificaciones.php — Helper de gestión de notificaciones in-app (v4.1).
 *
 * Funcionalidades:
 * - Creación de avisos para usuarios (reservas, expiraciones, bonos, activaciones).
 * - Consulta paginada de notificaciones del usuario autenticado.
 * - Conteo de avisos pendientes de lectura.
 * - Marcado masivo o individual de notificaciones como leídas.
 */
declare(strict_types=1);

/**
 * Crea una nueva notificación en el sistema para un usuario específico.
 *
 * @param PDO         $pdo        Instancia de conexión PDO
 * @param int         $usuarioId  ID del usuario destinatario
 * @param string      $mensaje    Texto explicativo del aviso
 * @param string|null $url        Ruta opcional interna de navegación (ej: '/mis-reservas')
 * @return int ID de la notificación creada
 */
function notificacion_crear(PDO $pdo, int $usuarioId, string $mensaje, ?string $url = null): int {
    $stmt = $pdo->prepare('
        INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
        VALUES (?, ?, 0, ?, NOW())
    ');
    $stmt->execute([
        $usuarioId,
        trim($mensaje),
        $url !== null ? trim($url) : null,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Obtiene la lista de notificaciones de un usuario ordenadas de más reciente a más antigua.
 *
 * @param PDO $pdo        Instancia de conexión PDO
 * @param int $usuarioId  ID del usuario
 * @param int $limite     Número máximo de resultados
 * @param int $offset     Desplazamiento para paginación
 * @return array Lista de notificaciones
 */
function notificaciones_listar(PDO $pdo, int $usuarioId, int $limite = 50, int $offset = 0): array {
    $stmt = $pdo->prepare('
        SELECT id, usuario_id, mensaje, leida, url, fecha
        FROM notificaciones
        WHERE usuario_id = ?
        ORDER BY fecha DESC, id DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limite), PDO::PARAM_INT);
    $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Cuenta la cantidad de notificaciones no leídas que tiene un usuario.
 *
 * @param PDO $pdo        Instancia de conexión PDO
 * @param int $usuarioId  ID del usuario
 * @return int Total de avisos pendientes de lectura
 */
function notificaciones_contar_no_leidas(PDO $pdo, int $usuarioId): int {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM notificaciones
        WHERE usuario_id = ? AND leida = 0
    ');
    $stmt->execute([$usuarioId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Marca todas las notificaciones pendientes de un usuario como leídas.
 *
 * @param PDO $pdo        Instancia de conexión PDO
 * @param int $usuarioId  ID del usuario
 * @return int Cantidad de notificaciones actualizadas
 */
function notificaciones_marcar_leidas(PDO $pdo, int $usuarioId): int {
    $stmt = $pdo->prepare('
        UPDATE notificaciones
        SET leida = 1
        WHERE usuario_id = ? AND leida = 0
    ');
    $stmt->execute([$usuarioId]);

    return $stmt->rowCount();
}

/**
 * Marca una notificación específica como leída comprobando pertenencia al usuario.
 *
 * @param PDO $pdo             Instancia de conexión PDO
 * @param int $notificacionId  ID de la notificación
 * @param int $usuarioId       ID del usuario propietario
 * @return bool True si se actualizó el registro
 */
function notificacion_marcar_leida(PDO $pdo, int $notificacionId, int $usuarioId): bool {
    $stmt = $pdo->prepare('
        UPDATE notificaciones
        SET leida = 1
        WHERE id = ? AND usuario_id = ?
    ');
    $stmt->execute([$notificacionId, $usuarioId]);

    return $stmt->rowCount() > 0;
}

/**
 * Elimina una notificación del sistema comprobando pertenencia al usuario.
 *
 * @param PDO $pdo             Instancia de conexión PDO
 * @param int $notificacionId  ID de la notificación
 * @param int $usuarioId       ID del usuario propietario
 * @return bool True si se eliminó el registro
 */
function notificacion_eliminar(PDO $pdo, int $notificacionId, int $usuarioId): bool {
    $stmt = $pdo->prepare('
        DELETE FROM notificaciones
        WHERE id = ? AND usuario_id = ?
    ');
    $stmt->execute([$notificacionId, $usuarioId]);

    return $stmt->rowCount() > 0;
}

/**
 * Obtiene una notificación por su ID verificando la pertenencia al usuario.
 *
 * @param PDO $pdo             Instancia de conexión PDO
 * @param int $notificacionId  ID de la notificación
 * @param int $usuarioId       ID del usuario propietario
 * @return array|null Datos de la notificación o null si no existe
 */
function notificacion_obtener(PDO $pdo, int $notificacionId, int $usuarioId): ?array {
    $stmt = $pdo->prepare('
        SELECT id, usuario_id, mensaje, leida, url, fecha
        FROM notificaciones
        WHERE id = ? AND usuario_id = ?
        LIMIT 1
    ');
    $stmt->execute([$notificacionId, $usuarioId]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    return $notif ?: null;
}
