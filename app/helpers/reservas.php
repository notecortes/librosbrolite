<?php
/**
 * BookSwap · reservas.php — Helper de gestión de reservas de libros (v4.1).
 *
 * Funcionalidades:
 * - Creación de reserva vinculada a un ejemplar físico disponible.
 * - Validación del límite máximo de reservas simultáneas (max_reservas_activas).
 * - Asignación de fecha límite según horas_reserva (configuración).
 * - Generación de código único y soporte para QR de recogida.
 * - Cancelación de reserva por el usuario con retorno inmediato del ejemplar a 'disponible'.
 * - Expiración automática de reservas vencidas con notificación al usuario (cron / pseudo-cron).
 * - Búsqueda de reservas por código para el mostrador de recepción.
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/ledger.php';

/**
 * Crea una nueva reserva para un usuario y un libro especificado.
 *
 * @param PDO      $pdo         Instancia de conexión a la base de datos
 * @param int      $usuarioId   ID del usuario que realiza la reserva
 * @param int      $libroId     ID del libro a reservar
 * @param int|null $ejemplarId  ID opcional de un ejemplar específico
 * @param int|null $operadorId  ID opcional del operador (personal/admin) que tramita la reserva
 * @return array Datos de la reserva creada (id, codigo, fecha_limite, ejemplar_id)
 * @throws Exception Si el usuario no tiene saldo, supera el límite o no hay ejemplares disponibles
 */
function reserva_crear(PDO $pdo, int $usuarioId, int $libroId, ?int $ejemplarId = null, ?int $operadorId = null): array {
    $transaccionIniciada = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transaccionIniciada = true;
    }

    try {
        // 1. Bloqueo pesimista del usuario y verificación de actividad
        $stmtUser = $pdo->prepare('SELECT id, activo FROM usuarios WHERE id = ? FOR UPDATE');
        $stmtUser->execute([$usuarioId]);
        $userRow = $stmtUser->fetch();
        if (!$userRow || (int) $userRow['activo'] !== 1) {
            throw new Exception('El usuario no está activo.');
        }

        // 2. Verificar límite de reservas activas simultáneas
        $stmtConf = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'max_reservas_activas' LIMIT 1");
        $stmtConf->execute();
        $maxReservas = (int) ($stmtConf->fetchColumn() ?: 3);

        $stmtActivas = $pdo->prepare("
            SELECT COUNT(*)
            FROM transacciones
            WHERE usuario_id = ? AND tipo = 'reserva' AND estado = 'activa'
            FOR UPDATE
        ");
        $stmtActivas->execute([$usuarioId]);
        $activasActuales = (int) $stmtActivas->fetchColumn();

        if ($activasActuales >= $maxReservas) {
            throw new Exception("Has alcanzado el límite máximo de reservas simultáneas ({$maxReservas}).");
        }

        // 3. Verificar coste del libro y saldo disponible (atómico con bloqueo)
        $stmtCoste = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'coste_libro' LIMIT 1");
        $stmtCoste->execute();
        $costeLibro = (int) ($stmtCoste->fetchColumn() ?: 1);

        $saldoDisponible = ledger_obtener_saldo($pdo, $usuarioId);
        if ($saldoDisponible < $costeLibro) {
            $faltan = $costeLibro - $saldoDisponible;
            throw new Exception("Te faltan {$faltan} tokens: deposita libros o pide un ajuste al personal.");
        }

        // 4. Obtener horas de vigencia de la reserva
        $stmtHoras = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'horas_reserva' LIMIT 1");
        $stmtHoras->execute();
        $horasReserva = (int) ($stmtHoras->fetchColumn() ?: 72);

        $fechaLimite = date('Y-m-d H:i:s', time() + ($horasReserva * 3600));

        // 5. Localizar y bloquear el ejemplar disponible
        if ($ejemplarId !== null && $ejemplarId > 0) {
            $stmtEjemplar = $pdo->prepare('
                SELECT id, libro_id, estado
                FROM ejemplares
                WHERE id = ?
                FOR UPDATE
            ');
            $stmtEjemplar->execute([$ejemplarId]);
            $ejemplar = $stmtEjemplar->fetch();

            if (!$ejemplar || $ejemplar['estado'] !== 'disponible') {
                throw new Exception('El ejemplar seleccionado no se encuentra disponible.');
            }
            $targetEjemplarId = (int) $ejemplar['id'];
            $libroId = (int) $ejemplar['libro_id'];
        } else {
            $stmtEjemplar = $pdo->prepare('
                SELECT id, libro_id, estado
                FROM ejemplares
                WHERE libro_id = ? AND estado = \'disponible\'
                LIMIT 1
                FOR UPDATE
            ');
            $stmtEjemplar->execute([$libroId]);
            $ejemplar = $stmtEjemplar->fetch();

            if (!$ejemplar) {
                throw new Exception('No hay copias disponibles de este libro para reservar.');
            }
            $targetEjemplarId = (int) $ejemplar['id'];
        }

        // 6. Generar código único para la reserva formato Code 128: RES-XXXXXXXXXX (14 caracteres)
        $alfabeto = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sufijo = '';
        for ($i = 0; $i < 10; $i++) {
            $sufijo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $codigo = 'RES-' . $sufijo;

        // 7. Marcar ejemplar como reservado
        $stmtUpdEj = $pdo->prepare("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?");
        $stmtUpdEj->execute([$targetEjemplarId]);

        // 8. Insertar la transacción de reserva (tokens = costeLibro)
        $stmtInsTx = $pdo->prepare("
            INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, codigo, estado, fecha_limite, created_at)
            VALUES ('reserva', ?, ?, ?, ?, 'activa', ?, NOW())
        ");
        $stmtInsTx->execute([$targetEjemplarId, $usuarioId, $costeLibro, $codigo, $fechaLimite]);
        $txId = (int) $pdo->lastInsertId();

        // 9. Bloquear tokens en el ledger inmutable (bloqueo_reserva)
        ledger_registrar_movimiento(
            $pdo,
            $usuarioId,
            -$costeLibro,
            'bloqueo_reserva',
            $txId,
            "Bloqueo por reserva {$codigo}"
        );

        // 10. Crear notificación para el usuario
        $stmtLibro = $pdo->prepare('SELECT titulo FROM libros WHERE id = ?');
        $stmtLibro->execute([$libroId]);
        $tituloLibro = (string) ($stmtLibro->fetchColumn() ?: 'Libro');

        $mensajeNotif = "Reserva realizada: «{$tituloLibro}». Se ha bloqueado temporalmente {$costeLibro} 🪙. Código de recogida: {$codigo}. Plazo: {$horasReserva}h.";
        if ($operadorId !== null && $operadorId !== $usuarioId) {
            $mensajeNotif = "El personal de la biblioteca ha tramitado una reserva a tu nombre: «{$tituloLibro}». Se ha bloqueado temporalmente {$costeLibro} 🪙. Código de recogida: {$codigo}. Plazo: {$horasReserva}h.";
        }
        $stmtNotif = $pdo->prepare('
            INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
            VALUES (?, ?, 0, \'/mis-reservas\', NOW())
        ');
        $stmtNotif->execute([$usuarioId, $mensajeNotif]);

        // 11. Registrar auditoría
        log_accion($operadorId ?? $usuarioId, 'reserva_creada', 'transacciones', $txId, [
            'codigo' => $codigo,
            'libro_id' => $libroId,
            'ejemplar_id' => $targetEjemplarId,
            'tokens_bloqueados' => $costeLibro,
            'fecha_limite' => $fechaLimite,
            'reservado_a_usuario_id' => $usuarioId,
            'operador_id' => $operadorId,
        ]);

        if ($transaccionIniciada) {
            $pdo->commit();
        }

        return [
            'id' => $txId,
            'transaccion_id' => $txId,
            'codigo' => $codigo,
            'codigo_qr' => $codigo,
            'ejemplar_id' => $targetEjemplarId,
            'libro_id' => $libroId,
            'titulo' => $tituloLibro,
            'fecha_limite' => $fechaLimite,
            'horas_reserva' => $horasReserva,
            'tokens' => $costeLibro,
        ];
    } catch (Throwable $e) {
        if ($transaccionIniciada && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Cancela una reserva activa liberando de inmediato el ejemplar a estado 'disponible'.
 *
 * @param PDO  $pdo              Instancia de base de datos
 * @param int  $transaccionId    ID de la transacción de reserva
 * @param int  $usuarioId        ID del usuario que solicita la cancelación
 * @param bool $esAdminOPersonal Permite cancelación por personal o administrador
 * @return bool True si se canceló correctamente
 * @throws Exception Si la reserva no existe, no está activa o no hay permisos
 */
function reserva_cancelar(PDO $pdo, int $transaccionId, int $usuarioId, bool $esAdminOPersonal = false): bool {
    $transaccionIniciada = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transaccionIniciada = true;
    }

    try {
        $stmtTx = $pdo->prepare('
            SELECT t.*, e.libro_id, l.titulo
            FROM transacciones t
            JOIN ejemplares e ON e.id = t.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE t.id = ?
            FOR UPDATE
        ');
        $stmtTx->execute([$transaccionId]);
        $tx = $stmtTx->fetch();

        if (!$tx) {
            throw new Exception('Reserva no encontrada.');
        }

        if (!$esAdminOPersonal && (int) $tx['usuario_id'] !== $usuarioId) {
            throw new Exception('No tienes autorización para cancelar esta reserva.');
        }

        if ($tx['estado'] !== 'activa') {
            throw new Exception('Solo se pueden cancelar reservas que se encuentren activas.');
        }

        // Marcar transacción como cancelada
        $stmtUpd = $pdo->prepare("UPDATE transacciones SET estado = 'cancelada' WHERE id = ?");
        $stmtUpd->execute([$transaccionId]);

        // Liberar el ejemplar
        $stmtUpdEj = $pdo->prepare("UPDATE ejemplares SET estado = 'disponible' WHERE id = ?");
        $stmtUpdEj->execute([$tx['ejemplar_id']]);

        // Liberar tokens bloqueados al usuario
        $costeDevolver = (int) ($tx['tokens'] > 0 ? $tx['tokens'] : 1);
        ledger_registrar_movimiento(
            $pdo,
            (int) $tx['usuario_id'],
            $costeDevolver,
            'liberacion_reserva',
            $transaccionId,
            "Liberación de reserva {$tx['codigo']}"
        );

        // Notificar al usuario
        $msg = "Has cancelado la reserva del libro «{$tx['titulo']}». Se han liberado {$costeDevolver} 🪙 a tu saldo y el ejemplar vuelve a estar disponible.";
        $stmtNotif = $pdo->prepare('
            INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
            VALUES (?, ?, 0, \'/mis-reservas\', NOW())
        ');
        $stmtNotif->execute([$tx['usuario_id'], $msg]);

        // Auditoría
        log_accion($usuarioId, 'reserva_cancelada', 'transacciones', $transaccionId, [
            'codigo' => $tx['codigo'],
            'libro_id' => $tx['libro_id'],
            'ejemplar_id' => $tx['ejemplar_id'],
            'tokens_liberados' => $costeDevolver,
        ]);

        if ($transaccionIniciada) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad') && !empty($tx['libro_id'])) {
            wishlist_notificar_disponibilidad($pdo, (int) $tx['libro_id']);
        }

        return true;
    } catch (Throwable $e) {
        if ($transaccionIniciada && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Lista las reservas de un usuario con detalles del libro y del ejemplar.
 *
 * @param PDO $pdo       Instancia de base de datos
 * @param int $usuarioId ID del usuario
 * @return array Lista de reservas ordenadas de más recientes a más antiguas
 */
function reserva_listar_usuario(PDO $pdo, int $usuarioId): array {
    $stmt = $pdo->prepare('
        SELECT t.id, t.tipo, t.ejemplar_id, t.usuario_id, t.tokens, t.codigo,
               t.estado, t.fecha_limite, t.fecha_entrega, t.created_at,
               l.id AS libro_id, l.titulo, l.autor, l.editorial, l.portada_url,
               e.ubicacion, e.condicion
        FROM transacciones t
        JOIN ejemplares e ON e.id = t.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE t.usuario_id = ? AND t.tipo = \'reserva\'
        ORDER BY t.created_at DESC
    ');
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
}

/**
 * Busca una reserva mediante su código o escaneo de código QR.
 *
 * @param PDO    $pdo    Instancia de base de datos
 * @param string $codigo Código de la reserva (ej. RES-DEMO-0001)
 * @return array|null Datos de la reserva con libro y usuario o null si no se encuentra
 */
function reserva_buscar_por_codigo(PDO $pdo, string $codigo): ?array {
    $codigoLimpio = trim($codigo);
    if ($codigoLimpio === '') {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT t.*,
        l.id AS libro_id, l.titulo, l.autor, l.isbn13, l.portada_url,
        e.ubicacion, e.condicion,
        u.nombre AS usuario_nombre, u.email AS usuario_email
        FROM transacciones t
        JOIN ejemplares e ON e.id = t.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.codigo = ? AND t.tipo = \'reserva\'
        LIMIT 1
    ');
    $stmt->execute([$codigoLimpio]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/**
 * Marca como 'expirada' toda reserva activa cuya fecha límite haya sido rebasada,
 * retornando sus ejemplares a 'disponible' y gestionando tokens según penalizar_expiracion.
 *
 * @param PDO $pdo Instancia de base de datos
 * @return array Resumen del proceso (expiradas, detalles)
 */
function reserva_expirar_vencidas(PDO $pdo): array {
    $transaccionIniciada = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transaccionIniciada = true;
    }

    try {
        $stmtPen = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'penalizar_expiracion' LIMIT 1");
        $stmtPen->execute();
        $penalizar = ($stmtPen->fetchColumn() === '1');

        $stmtVencidas = $pdo->prepare('
            SELECT t.id, t.usuario_id, t.ejemplar_id, t.tokens, t.codigo, t.fecha_limite,
                   l.id AS libro_id, l.titulo
            FROM transacciones t
            JOIN ejemplares e ON e.id = t.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE t.tipo = \'reserva\'
              AND t.estado = \'activa\'
              AND t.fecha_limite < NOW()
            FOR UPDATE
        ');
        $stmtVencidas->execute();
        $vencidas = $stmtVencidas->fetchAll();

        if (empty($vencidas)) {
            if ($transaccionIniciada) {
                $pdo->commit();
            }
            return ['expiradas' => 0, 'detalles' => []];
        }

        $stmtUpdTx = $pdo->prepare("UPDATE transacciones SET estado = 'expirada' WHERE id = ?");
        $stmtUpdEj = $pdo->prepare("UPDATE ejemplares SET estado = 'disponible' WHERE id = ?");
        $stmtNotif = $pdo->prepare('
            INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
            VALUES (?, ?, 0, \'/mis-reservas\', NOW())
        ');

        foreach ($vencidas as $v) {
            $stmtUpdTx->execute([$v['id']]);
            $stmtUpdEj->execute([$v['ejemplar_id']]);

            $coste = (int) ($v['tokens'] > 0 ? $v['tokens'] : 1);

            if (!$penalizar) {
                // Liberar tokens al usuario
                ledger_registrar_movimiento(
                    $pdo,
                    (int) $v['usuario_id'],
                    $coste,
                    'liberacion_reserva',
                    (int) $v['id'],
                    "Liberación por expiración de reserva {$v['codigo']}"
                );

                $mensaje = "Tu reserva de «{$v['titulo']}» (código {$v['codigo']}) ha expirado por superar la fecha límite. Se han liberado {$coste} 🪙 a tu saldo y el ejemplar vuelve a estar disponible.";
                $stmtNotif->execute([$v['usuario_id'], $mensaje]);

                log_accion(null, 'reserva_expirada', 'transacciones', (int) $v['id'], [
                    'codigo' => $v['codigo'],
                    'ejemplar_id' => $v['ejemplar_id'],
                    'fecha_limite' => $v['fecha_limite'],
                    'tokens_liberados' => $coste,
                    'penalizado' => false,
                ]);
            } else {
                // Penalizar: el usuario pierde el token bloqueado; no se devuelve
                $mensaje = "Tu reserva de «{$v['titulo']}» (código {$v['codigo']}) ha expirado por superar la fecha límite. Los {$coste} 🪙 bloqueados no han sido reembolsados según la política de penalización por expiración.";
                $stmtNotif->execute([$v['usuario_id'], $mensaje]);

                log_accion(null, 'reserva_expirada', 'transacciones', (int) $v['id'], [
                    'codigo' => $v['codigo'],
                    'ejemplar_id' => $v['ejemplar_id'],
                    'fecha_limite' => $v['fecha_limite'],
                    'tokens_perdidos' => $coste,
                    'penalizado' => true,
                    'motivo' => 'penalizar_expiracion activa: tokens bloqueados no devueltos',
                ]);
            }
        }

        if ($transaccionIniciada) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad')) {
            $librosNotificados = [];
            foreach ($vencidas as $v) {
                $libId = (int) ($v['libro_id'] ?? 0);
                if ($libId > 0 && !isset($librosNotificados[$libId])) {
                    wishlist_notificar_disponibilidad($pdo, $libId);
                    $librosNotificados[$libId] = true;
                }
            }
        }

        return [
            'expiradas' => count($vencidas),
            'detalles' => $vencidas,
        ];
    } catch (Throwable $e) {
        if ($transaccionIniciada && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
