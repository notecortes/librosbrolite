<?php
/**
 * BookSwap · wishlist.php — Helper de gestión de lista de deseos y avisos de disponibilidad.
 *
 * Funcionalidades:
 * - Asegurar la tabla `wishlist` en base de datos de manera dinámica y no intrusiva.
 * - Añadir libros a la lista de deseos del usuario (`wishlist_agregar`).
 * - Eliminar libros de la lista de deseos (`wishlist_eliminar`).
 * - Comprobar si un usuario tiene un libro en su lista (`wishlist_existe`).
 * - Listar libros en la lista de deseos con disponibilidad en tiempo real (`wishlist_listar`).
 * - Notificar a los usuarios en espera cuando un libro vuelve a tener ejemplares disponibles (`wishlist_notificar_disponibilidad`).
 */
declare(strict_types=1);

require_once __DIR__ . '/notificaciones.php';

/**
 * Asegura la existencia de la tabla `wishlist` en la base de datos.
 */
function wishlist_asegurar_tabla(PDO $pdo): void {
    static $asegurada = false;
    if ($asegurada) {
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wishlist (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT UNSIGNED NOT NULL,
                libro_id INT UNSIGNED NOT NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notificado TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY uq_wishlist_usuario_libro (usuario_id, libro_id),
                CONSTRAINT fk_wishlist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_wishlist_libro FOREIGN KEY (libro_id) REFERENCES libros(id) ON DELETE CASCADE,
                INDEX idx_wishlist_libro (libro_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $asegurada = true;
    } catch (Throwable $e) {
        // En caso de que no tenga permisos DDL o ya exista
    }
}

/**
 * Añade un libro a la lista de deseos del usuario.
 */
function wishlist_agregar(PDO $pdo, int $usuarioId, int $libroId): bool {
    wishlist_asegurar_tabla($pdo);

    if ($usuarioId <= 0 || $libroId <= 0) {
        return false;
    }

    // Comprobar que el libro existe
    $stmtLibro = $pdo->prepare("SELECT id FROM libros WHERE id = ?");
    $stmtLibro->execute([$libroId]);
    if (!$stmtLibro->fetch()) {
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO wishlist (usuario_id, libro_id, creado_en, notificado)
        VALUES (?, ?, NOW(), 0)
        ON DUPLICATE KEY UPDATE notificado = 0
    ");
    return $stmt->execute([$usuarioId, $libroId]);
}

/**
 * Elimina un libro de la lista de deseos del usuario.
 */
function wishlist_eliminar(PDO $pdo, int $usuarioId, int $libroId): bool {
    wishlist_asegurar_tabla($pdo);

    if ($usuarioId <= 0 || $libroId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        DELETE FROM wishlist
        WHERE usuario_id = ? AND libro_id = ?
    ");
    return $stmt->execute([$usuarioId, $libroId]);
}

/**
 * Comprueba si un usuario tiene un libro en su lista de deseos.
 */
function wishlist_existe(PDO $pdo, int $usuarioId, int $libroId): bool {
    wishlist_asegurar_tabla($pdo);

    if ($usuarioId <= 0 || $libroId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM wishlist
        WHERE usuario_id = ? AND libro_id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuarioId, $libroId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Obtiene los libros de la lista de deseos de un usuario con número de copias disponibles.
 */
function wishlist_listar(PDO $pdo, int $usuarioId): array {
    wishlist_asegurar_tabla($pdo);

    if ($usuarioId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT w.id AS wishlist_id, w.creado_en AS fecha_deseo, w.notificado,
               l.id AS libro_id, l.titulo, l.autor, l.editorial, l.anio, l.genero, l.idioma, l.portada_url,
               (SELECT COUNT(*) FROM ejemplares e WHERE e.libro_id = l.id AND e.estado = 'disponible') AS num_disponibles
        FROM wishlist w
        JOIN libros l ON w.libro_id = l.id
        WHERE w.usuario_id = ?
        ORDER BY w.id DESC
    ");
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Notifica a los usuarios con aviso activo que un libro ya tiene ejemplares disponibles.
 */
function wishlist_notificar_disponibilidad(PDO $pdo, int $libroId): int {
    wishlist_asegurar_tabla($pdo);

    if ($libroId <= 0) {
        return 0;
    }

    $stmtLibro = $pdo->prepare("SELECT titulo FROM libros WHERE id = ?");
    $stmtLibro->execute([$libroId]);
    $titulo = $stmtLibro->fetchColumn();
    if (!$titulo) {
        return 0;
    }

    $stmtUsers = $pdo->prepare("
        SELECT usuario_id
        FROM wishlist
        WHERE libro_id = ? AND notificado = 0
    ");
    $stmtUsers->execute([$libroId]);
    $usuarios = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    if (empty($usuarios)) {
        return 0;
    }

    $totalNotificados = 0;
    foreach ($usuarios as $uid) {
        $mensaje = "¡El libro '{$titulo}' que esperabas ya tiene ejemplares disponibles en la biblioteca!";
        notificacion_crear($pdo, (int) $uid, $mensaje, "/libro/{$libroId}");
        $totalNotificados++;
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE wishlist
        SET notificado = 1
        WHERE libro_id = ?
    ");
    $stmtUpdate->execute([$libroId]);

    return $totalNotificados;
}
