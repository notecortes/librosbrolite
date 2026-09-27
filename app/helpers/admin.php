<?php
/**
 * BookSwap · admin.php — Asistente de operaciones administrativas integrales (v4.2).
 *
 * Implementa las 9 secciones operativas del panel de administración:
 * - Gestión completa de usuarios (CRUD, activación, reset password, ajuste de tokens).
 * - Gestión y monitorización de reservas (listado global, cancelación administrativa, expiración inmediata).
 * - Libro mayor global (movimientos_tokens de solo lectura con filtros).
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/reservas.php';
require_once __DIR__ . '/usuario_persistencia.php';
require_once __DIR__ . '/password_reset.php';

/**
 * Consulta la lista paginada de usuarios con filtros por rol, estado de activación y búsqueda.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param array $filtros Filtros: rol_id, estado ('activo', 'inactivo'), q
 * @param int $pagina Número de página (1-indexed)
 * @param int $porPagina Cantidad de usuarios por página
 * @return array Arreglo con usuarios, total, pagina, por_pagina y total_paginas
 */
function admin_usuarios_listar(PDO $pdo, array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['rol_id'])) {
        $where[] = 'u.rol_id = ?';
        $params[] = (int) $filtros['rol_id'];
    }

    if (isset($filtros['estado']) && $filtros['estado'] !== '') {
        if ($filtros['estado'] === 'activo') {
            $where[] = 'u.activo = 1';
        } elseif ($filtros['estado'] === 'inactivo') {
            $where[] = 'u.activo = 0';
        }
    }

    if (!empty($filtros['q'])) {
        $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
        $term = '%' . $filtros['q'] . '%';
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $where);

    // Contar total
    $stmtCount = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id)
        FROM usuarios u
        WHERE {$whereSql}
    ");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $offset = ($pagina - 1) * $porPagina;

    // Obtener usuarios con saldo
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.email, u.password_hash, u.rol_id, u.activo, u.email_verificado, u.auth_provider, u.fecha_registro,
               r.nombre AS rol_nombre,
               (SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = u.id) AS saldo
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE {$whereSql}
        ORDER BY u.id DESC
        LIMIT {$porPagina} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'usuarios'      => $usuarios,
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => $totalPaginas,
    ];
}

/**
 * Crea un nuevo usuario desde el panel de administración.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param string $nombre Nombre del usuario
 * @param string $email Correo electrónico único
 * @param string $password Contraseña de acceso
 * @param int $rolId Identificador del rol asignado (1: ADMIN, 2: PERSONAL, 3: USUARIO)
 * @param mixed $arg6 AdminId o argumento legado
 * @param mixed $arg7 AdminId si arg6 fue legado
 * @return array Resultado con 'ok', 'usuario_id' y posibles 'error'
 */
function admin_usuario_crear(
    PDO $pdo,
    string $nombre,
    string $email,
    string $password,
    int $rolId,
    $arg6 = null,
    $arg7 = null
): array {
    $adminId = is_int($arg6) ? $arg6 : (is_int($arg7) ? $arg7 : null);
    $nombre = trim($nombre);
    $email = strtolower(trim($email));

    if ($nombre === '') {
        return ['ok' => false, 'error' => 'El nombre no puede estar vacío.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'El formato del correo electrónico es inválido.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
    }

    // Verificar unicidad de email
    $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmtEmail->execute([$email]);
    if ($stmtEmail->fetchColumn()) {
        return ['ok' => false, 'error' => 'Ya existe un usuario con este correo electrónico.'];
    }

    $inTx = $pdo->inTransaction();
    if (!$inTx) {
        $pdo->beginTransaction();
    }

    try {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmtIns = $pdo->prepare('
            INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, email_verificado, auth_provider, fecha_registro)
            VALUES (?, ?, ?, ?, 1, 1, \'local\', NOW())
        ');
        $stmtIns->execute([$nombre, $email, $hash, $rolId]);
        $nuevoId = (int) $pdo->lastInsertId();

        // Auditoría
        auditoria_registrar($pdo, 'usuario.crear', 'usuarios', $nuevoId, [
            'nombre'  => $nombre,
            'email'   => $email,
            'rol_id'  => $rolId,
        ], $adminId);

        // Bono de bienvenida si es lector
        if ($rolId === 3) {
            $stmtBono = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'bono_bienvenida'");
            $stmtBono->execute();
            $bono = (int) ($stmtBono->fetchColumn() ?: 2);
            if ($bono > 0) {
                ledger_registrar_movimiento($pdo, $nuevoId, $bono, 'bono', null, 'Bono de bienvenida');
            }
        }

        if (!$inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }

        // Persistir en usuarios_persistentes.json si existe el helper
        if (function_exists('usuarios_persistir_personalizados')) {
            usuarios_persistir_personalizados($pdo);
        }

        return ['ok' => true, 'usuario_id' => $nuevoId];
    } catch (Throwable $e) {
        if (!$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Actualiza los datos básicos (nombre y rol) de un usuario existente.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $usuarioId ID del usuario a modificar
 * @param string $nombre Nuevo nombre completo
 * @param int $rolId Nuevo rol asignado
 * @param int|null $adminId Administrador que ejecuta la acción
 * @return bool True si la actualización se realizó con éxito
 */
function admin_usuario_actualizar(PDO $pdo, int $usuarioId, string $nombre, int $rolId, ?int $adminId = null): bool {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new Exception('El nombre no puede estar vacío.');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, rol_id = ? WHERE id = ?');
    $stmt->execute([$nombre, $rolId, $usuarioId]);

    auditoria_registrar($pdo, 'usuario.actualizar', 'usuarios', $usuarioId, [
        'nombre' => $nombre,
        'rol_id' => $rolId,
    ], $adminId);

    return true;
}

/**
 * Modifica el estado activo/inactivo de una cuenta de usuario.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $usuarioId ID del usuario
 * @param bool $activo Nuevo estado booleano
 * @param int|null $adminId Administrador que ejecuta la acción
 * @return bool True si el estado cambió
 */
function admin_usuario_cambiar_estado(PDO $pdo, int $usuarioId, bool $activo, ?int $adminId = null): bool {
    if ($adminId !== null && $adminId === $usuarioId && !$activo) {
        throw new Exception('No puedes desactivar tu propia cuenta de administrador.');
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
    $stmt->execute([$activo ? 1 : 0, $usuarioId]);

    auditoria_registrar($pdo, 'usuario.cambiar_estado', 'usuarios', $usuarioId, [
        'activo' => $activo ? 1 : 0,
    ], $adminId);

    return true;
}

/**
 * Restablece la contraseña de un usuario generando una clave temporal aleatoria de 10 caracteres.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $usuarioId ID del usuario
 * @param int|null $adminId Administrador que realiza el restablecimiento
 * @return string Contraseña temporal en texto plano para mostrar al administrador
 */
function admin_usuario_reset_password(PDO $pdo, int $usuarioId, ?int $adminId = null): string {
    return admin_usuario_generar_enlace_reset($pdo, $usuarioId, $adminId);
}

/**
 * Elimina permanentemente la cuenta de un usuario y todo su historial asociado (tokens, reservas, transacciones, etc.).
 * Permite que el usuario pueda volver a registrarse con el mismo correo electrónico sin conflictos.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $usuarioId ID del usuario a eliminar
 * @param int|null $adminId Administrador que ejecuta la eliminación
 * @return array ['ok' => bool, 'id' => int, 'nombre' => string, 'email' => string]
 * @throws Exception Si el usuario no existe, es superadmin (id=1) o es el propio admin autenticado
 */
function admin_usuario_eliminar(PDO $pdo, int $usuarioId, ?int $adminId = null): array {
    $stmt = $pdo->prepare('SELECT id, nombre, email, rol_id FROM usuarios WHERE id = ?');
    $stmt->execute([$usuarioId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception('El usuario que intentas eliminar no existe.');
    }

    if ($usuarioId === 1) {
        throw new Exception('No se puede eliminar la cuenta principal de administración del sistema.');
    }

    if ($adminId !== null && $adminId === $usuarioId) {
        throw new Exception('No puedes eliminar tu propia cuenta de administrador.');
    }

    $inTx = $pdo->inTransaction();
    if (!$inTx) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Liberar copias asociadas a reservas activas de este usuario
        $stmtLib = $pdo->prepare("
            UPDATE ejemplares 
            SET estado = 'disponible'
            WHERE id IN (
                SELECT ejemplar_id FROM transacciones WHERE usuario_id = ? AND estado = 'activa'
            )
        ");
        $stmtLib->execute([$usuarioId]);

        // 2. Desvincular ejemplares depositados por este usuario (el fondo físico permanece en la biblioteca)
        $stmtDep = $pdo->prepare("UPDATE ejemplares SET depositante_id = NULL WHERE depositante_id = ?");
        $stmtDep->execute([$usuarioId]);

        // 3. Desvincular transacciones que haya gestionado como personal/admin
        $stmtGest = $pdo->prepare("UPDATE transacciones SET gestionada_por = NULL WHERE gestionada_por = ?");
        $stmtGest->execute([$usuarioId]);

        // 4. Eliminar movimientos del ledger de tokens de este usuario
        $stmtMov = $pdo->prepare("DELETE FROM movimientos_tokens WHERE usuario_id = ?");
        $stmtMov->execute([$usuarioId]);

        // 5. Eliminar transacciones (depósitos, reservas, entregas) de este usuario
        $stmtTr = $pdo->prepare("DELETE FROM transacciones WHERE usuario_id = ?");
        $stmtTr->execute([$usuarioId]);

        // 6. Eliminar elementos de su lista de deseos
        $stmtW = $pdo->prepare("DELETE FROM wishlist WHERE usuario_id = ?");
        $stmtW->execute([$usuarioId]);

        // 7. Eliminar notificaciones recibidas
        $stmtNot = $pdo->prepare("DELETE FROM notificaciones WHERE usuario_id = ?");
        $stmtNot->execute([$usuarioId]);

        // 8. Eliminar tokens de restablecimiento de contraseña
        $stmtPr = $pdo->prepare("DELETE FROM password_resets WHERE usuario_id = ?");
        $stmtPr->execute([$usuarioId]);

        // 9. Limpiar intentos de login y rate limits para que pueda registrarse de inmediato
        $emailNorm = strtolower(trim((string) $user['email']));
        $stmtInt = $pdo->prepare("DELETE FROM intentos_login WHERE email = ? OR email = ?");
        $stmtInt->execute([$emailNorm, 'reset:' . $emailNorm]);

        // 10. Desvincular copias de seguridad
        $stmtBak = $pdo->prepare("UPDATE backups SET usuario_id = NULL WHERE usuario_id = ?");
        $stmtBak->execute([$usuarioId]);

        // 11. Auditoría del borrado de cuenta
        auditoria_registrar($pdo, 'usuario.eliminar', 'usuarios', $usuarioId, [
            'nombre' => $user['nombre'],
            'email'  => $user['email'],
            'rol_id' => $user['rol_id'],
        ], $adminId);

        // 12. Borrar permanentemente el registro en la tabla de usuarios
        $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmtDel->execute([$usuarioId]);

        if (!$inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }

        // 13. Eliminar de la persistencia de cuentas personalizadas
        if (function_exists('usuarios_eliminar_de_persistencia')) {
            usuarios_eliminar_de_persistencia($user['email']);
        }

        return [
            'ok'     => true,
            'id'     => $usuarioId,
            'nombre' => $user['nombre'],
            'email'  => $user['email'],
        ];
    } catch (Throwable $e) {
        if (!$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}




/**
 * Realiza un ajuste manual en el saldo de tokens de un usuario, registrándolo en el ledger.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $usuarioId ID del usuario objetivo
 * @param int $cantidad Cantidad con signo (+/-) a ajustar
 * @param string $motivo Motivo obligatorio del ajuste
 * @param int|null $adminId Administrador que autoriza el ajuste
 * @return array Arreglo con saldo_anterior y saldo_nuevo
 */
function admin_usuario_ajustar_tokens(PDO $pdo, int $usuarioId, int $cantidad, string $motivo, ?int $adminId = null): array {
    $motivo = trim($motivo);
    if ($cantidad === 0) {
        throw new Exception('La cantidad de tokens a ajustar no puede ser cero.');
    }
    if ($motivo === '') {
        throw new Exception('Es obligatorio indicar el motivo del ajuste.');
    }

    $stmtSaldo = $pdo->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ?');
    $stmtSaldo->execute([$usuarioId]);
    $saldoActual = (int) $stmtSaldo->fetchColumn();
    $nuevoSaldo = $saldoActual + $cantidad;

    if ($nuevoSaldo < 0) {
        throw new Exception("El ajuste resultaría en un saldo negativo ({$nuevoSaldo}). Operación rechazada.");
    }

    $movId = ledger_registrar_movimiento($pdo, $usuarioId, $cantidad, 'ajuste', null, $motivo);

    auditoria_registrar($pdo, 'tokens.ajuste', 'usuarios', $usuarioId, [
        'movimiento_id'  => $movId,
        'cantidad'       => $cantidad,
        'motivo'         => $motivo,
        'saldo_anterior' => $saldoActual,
        'saldo_nuevo'    => $nuevoSaldo,
    ], $adminId);

    return [
        'saldo_anterior' => $saldoActual,
        'saldo_nuevo'    => $nuevoSaldo,
        'movimiento_id'  => $movId,
    ];
}

/**
 * Lista todas las reservas del sistema con opciones de filtrado y paginación.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param array $filtros Filtros: estado, usuario_id, desde, hasta, q
 * @param int $pagina Número de página (1-indexed)
 * @param int $porPagina Cantidad de reservas por página
 * @return array Arreglo con reservas, total, pagina, por_pagina y total_paginas
 */
function admin_reservas_listar(PDO $pdo, array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
    $where = ["t.tipo = 'reserva'"];
    $params = [];

    if (!empty($filtros['estado'])) {
        $where[] = 't.estado = ?';
        $params[] = $filtros['estado'];
    }

    if (!empty($filtros['usuario_id'])) {
        $where[] = 't.usuario_id = ?';
        $params[] = (int) $filtros['usuario_id'];
    }

    if (!empty($filtros['desde'])) {
        $where[] = 't.created_at >= ?';
        $params[] = $filtros['desde'] . ' 00:00:00';
    }

    if (!empty($filtros['hasta'])) {
        $where[] = 't.created_at <= ?';
        $params[] = $filtros['hasta'] . ' 23:59:59';
    }

    if (!empty($filtros['q'])) {
        $where[] = '(t.codigo LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR l.titulo LIKE ?)';
        $term = '%' . $filtros['q'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $where);

    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM transacciones t
        JOIN usuarios u ON t.usuario_id = u.id
        JOIN ejemplares e ON t.ejemplar_id = e.id
        JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
    ");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
               l.titulo AS libro_titulo, l.autor AS libro_autor, l.id AS libro_id,
               e.ubicacion, e.condicion
        FROM transacciones t
        JOIN usuarios u ON t.usuario_id = u.id
        JOIN ejemplares e ON t.ejemplar_id = e.id
        JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
        ORDER BY t.created_at DESC, t.id DESC
        LIMIT {$porPagina} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'reservas'      => $reservas,
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => $totalPaginas,
    ];
}

/**
 * Cancela una reserva activa de forma administrativa, liberando el ejemplar y notificando al lector.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $transaccionId ID de la reserva a cancelar
 * @param int|null $adminId Administrador que ejecuta la cancelación
 * @return bool True si la reserva se canceló correctamente
 */
function admin_reserva_cancelar(PDO $pdo, int $transaccionId, ?int $adminId = null): bool {
    return reserva_cancelar($pdo, $transaccionId, $adminId ?? 1, true);
}

/**
 * Ejecuta de inmediato el proceso de comprobación y expiración de reservas vencidas.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int|null $adminId Administrador que desencadena la acción
 * @return int Cantidad de reservas vencidas que fueron expiradas
 */
function admin_reservas_expirar_ahora(PDO $pdo, ?int $adminId = null): int {
    $expiradas = reserva_expirar_vencidas($pdo);
    auditoria_registrar($pdo, 'reservas.expirar_manual', 'transacciones', null, [
        'expiradas' => $expiradas,
    ], $adminId);
    return $expiradas;
}

/**
 * Consulta el listado global de movimientos del libro mayor (ledger) con filtros y paginación.
 *
 * @param PDO $pdo Conexión a base de datos
 * @param array $filtros Filtros: tipo, usuario_id, desde, hasta, q
 * @param int $pagina Número de página (1-indexed)
 * @param int $porPagina Cantidad de movimientos por página
 * @return array Arreglo con movimientos, total, pagina, por_pagina y total_paginas
 */
function admin_movimientos_listar(PDO $pdo, array $filtros = [], int $pagina = 1, int $porPagina = 25): array {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['tipo'])) {
        $where[] = 'm.tipo = ?';
        $params[] = $filtros['tipo'];
    }

    if (!empty($filtros['usuario_id'])) {
        $where[] = 'm.usuario_id = ?';
        $params[] = (int) $filtros['usuario_id'];
    }

    if (!empty($filtros['desde'])) {
        $where[] = 'm.fecha >= ?';
        $params[] = $filtros['desde'] . ' 00:00:00';
    }

    if (!empty($filtros['hasta'])) {
        $where[] = 'm.fecha <= ?';
        $params[] = $filtros['hasta'] . ' 23:59:59';
    }

    if (!empty($filtros['q'])) {
        $where[] = '(u.nombre LIKE ? OR u.email LIKE ? OR m.concepto LIKE ? OR l.titulo LIKE ?)';
        $term = '%' . $filtros['q'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $where);

    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM movimientos_tokens m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN transacciones t ON m.transaccion_id = t.id
        LEFT JOIN ejemplares e ON t.ejemplar_id = e.id
        LEFT JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
    ");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare("
        SELECT m.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
               l.titulo AS libro_titulo, t.codigo AS codigo_reserva
        FROM movimientos_tokens m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN transacciones t ON m.transaccion_id = t.id
        LEFT JOIN ejemplares e ON t.ejemplar_id = e.id
        LEFT JOIN libros l ON e.libro_id = l.id
        WHERE {$whereSql}
        ORDER BY m.fecha DESC, m.id DESC
        LIMIT {$porPagina} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'movimientos'   => $movimientos,
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => $totalPaginas,
    ];
}
