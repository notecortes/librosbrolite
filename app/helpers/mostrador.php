<?php
/**
 * BookSwap · mostrador.php — Operativa de mostrador físico de biblioteca (v4.1).
 *
 * Funcionalidades (§5.3, §5.4):
 * - Búsqueda de reservas activas por código alfanumérico o lectura de QR.
 * - Validación estricta de plazos y expiración antes de la entrega.
 * - Entrega de reserva mediante pago con tokens (-coste_libro).
 * - Entrega de reserva mediante intercambio aportando un libro (+bono_deposito y -coste_libro).
 * - Depósito de libros admitidos en mostrador (+bono_deposito, copia disponible).
 * - Rechazo de depósitos no aptos con registro de auditoría.
 * - Soporte para alta de libro al vuelo en el mostrador.
 */
declare(strict_types=1);

require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/auditoria.php';

/**
 * Busca una reserva activa por su código o payload de código QR para atenderla en el mostrador.
 *
 * @param PDO    $pdo     Instancia de conexión PDO
 * @param string $codigo  Código alfanumérico de la reserva (ej: RES-260923-A1B2)
 * @return array|null Datos completos de la reserva, libro, ejemplar y usuario, o null si no existe
 */
function mostrador_buscar_reserva(PDO $pdo, string $codigo): ?array {
    $codigoLimpio = trim($codigo);
    if ($codigoLimpio === '') {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT t.id AS transaccion_id,
               t.codigo,
               t.tipo,
               t.estado,
               t.tokens,
               t.fecha_limite,
               t.created_at,
               e.id AS ejemplar_id,
               e.condicion,
               e.ubicacion,
               l.id AS libro_id,
               l.titulo,
               l.autor,
               l.isbn13,
               l.portada_url,
               u.id AS usuario_id,
               u.nombre AS usuario_nombre,
               u.email AS usuario_email
        FROM transacciones t
        JOIN ejemplares e ON t.ejemplar_id = e.id
        JOIN libros l ON e.libro_id = l.id
        JOIN usuarios u ON t.usuario_id = u.id
        WHERE (t.codigo = ? OR u.email = ?) AND t.tipo = \'reserva\'
        ORDER BY (t.estado = \'activa\') DESC, t.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$codigoLimpio, $codigoLimpio]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        return null;
    }

    // Comprobar si ha vencido la fecha límite
    $ahora = time();
    $limiteTs = !empty($reserva['fecha_limite']) ? strtotime((string) $reserva['fecha_limite']) : 0;
    $reserva['ha_expirado'] = ($limiteTs > 0 && $ahora > $limiteTs);

    // Obtener saldo actual del usuario
    $reserva['usuario_saldo'] = ledger_obtener_saldo($pdo, (int) $reserva['usuario_id']);

    return $reserva;
}

/**
 * Completa la entrega física de un libro reservado en el mostrador, liquidando con tokens o intercambio.
 *
 * @param PDO         $pdo                       Instancia de conexión PDO
 * @param int         $transaccionId             ID de la transacción de reserva
 * @param string      $metodoPago                Método de liquidación: 'tokens' o 'libro'
 * @param int|null    $libroDepositadoId         ID del libro entregado por el usuario en caso de intercambio
 * @param string|null $condicionLibroDepositado  Estado físico del libro aportado ('nuevo', 'bueno', 'regular')
 * @param int         $gestionadaPorId           ID del personal o administrador que gestiona la entrega
 * @return array Datos informativos del resultado de la entrega
 * @throws Exception Si la reserva no es válida, ha expirado o el usuario no cumple los requisitos
 */
function mostrador_completar_entrega(
    PDO $pdo,
    int $transaccionId,
    string $metodoPago,
    ?int $libroDepositadoId = null,
    ?string $condicionLibroDepositado = 'bueno',
    int $gestionadaPorId = 0
): array {
    if (!in_array($metodoPago, ['tokens', 'libro'], true)) {
        throw new InvalidArgumentException("Método de pago inválido: {$metodoPago}");
    }

    $iniciaTransaccion = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciaTransaccion = true;
    }

    try {
        // Bloqueo pesimista de la transacción de reserva
        $stmtTx = $pdo->prepare('
            SELECT t.*, e.libro_id, l.titulo AS libro_titulo
            FROM transacciones t
            JOIN ejemplares e ON t.ejemplar_id = e.id
            JOIN libros l ON e.libro_id = l.id
            WHERE t.id = ?
            FOR UPDATE
        ');
        $stmtTx->execute([$transaccionId]);
        $tx = $stmtTx->fetch();

        if (!$tx) {
            throw new Exception('La reserva no existe.');
        }

        if ($tx['tipo'] !== 'reserva' || $tx['estado'] !== 'activa') {
            throw new Exception("La reserva no está activa (estado actual: {$tx['estado']}).");
        }

        // T-ENTR-04: Verificar que no haya superado la fecha límite
        if (!empty($tx['fecha_limite'])) {
            $limiteTs = strtotime((string) $tx['fecha_limite']);
            if (time() > $limiteTs) {
                if (function_exists('reserva_expirar_vencidas')) {
                    reserva_expirar_vencidas($pdo);
                } else {
                    $stmtExpTx = $pdo->prepare("UPDATE transacciones SET estado = 'expirada' WHERE id = ?");
                    $stmtExpTx->execute([$transaccionId]);
                    $stmtLibEj = $pdo->prepare("UPDATE ejemplares SET estado = 'disponible' WHERE id = ?");
                    $stmtLibEj->execute([$tx['ejemplar_id']]);
                }
                throw new Exception('La reserva ha expirado y no puede ser entregada.');
            }
        }

        $usuarioId = (int) $tx['usuario_id'];
        $ejemplarId = (int) $tx['ejemplar_id'];
        $libroId = (int) $tx['libro_id'];
        $tituloLibro = (string) $tx['libro_titulo'];

        // Obtener configuración económica
        $stmtConf = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('coste_libro', 'bono_deposito')");
        $conf = $stmtConf->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $costeLibro = (int) ($conf['coste_libro'] ?? 1);
        $bonoDeposito = (int) ($conf['bono_deposito'] ?? 1);

        $depositoTxId = null;
        $nuevoEjemplarId = null;

        // Proceso según método de pago
        if ($metodoPago === 'tokens') {
            // v4.7: Consolidación SIN movimiento nuevo (el bloqueo al reservar ya fue el pago)
        } else {
            // T-ENTR-02 / T-ENTR-03: Intercambio libro por libro aportando ejemplar admitido
            if ($libroDepositadoId === null || $libroDepositadoId <= 0) {
                throw new Exception('Debe seleccionarse un libro admitido del catálogo para el intercambio.');
            }

            // Comprobar que el libro aportado exista en el catálogo
            $stmtLibDep = $pdo->prepare('SELECT id, titulo FROM libros WHERE id = ?');
            $stmtLibDep->execute([$libroDepositadoId]);
            $libroDep = $stmtLibDep->fetch();
            if (!$libroDep) {
                throw new Exception('El libro aportado no se encuentra en el catálogo o no está admitido.');
            }

            $tituloDep = (string) $libroDep['titulo'];
            $condicionValida = in_array($condicionLibroDepositado, ['nuevo', 'bueno', 'regular'], true)
                ? $condicionLibroDepositado
                : 'bueno';

            // Insertar el ejemplar depositado a estado 'disponible'
            $stmtInsEj = $pdo->prepare("
                INSERT INTO ejemplares (libro_id, estado, ubicacion, depositante_id, condicion)
                VALUES (?, 'disponible', 'MOSTRADOR', ?, ?)
            ");
            $stmtInsEj->execute([$libroDepositadoId, $usuarioId, $condicionValida]);
            $nuevoEjemplarId = (int) $pdo->lastInsertId();

            // Insertar transacción de depósito completada
            $stmtInsDepTx = $pdo->prepare("
                INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, estado, fecha_entrega, gestionada_por, created_at)
                VALUES ('deposito', ?, ?, ?, 'entregada', NOW(), ?, NOW())
            ");
            $stmtInsDepTx->execute([$nuevoEjemplarId, $usuarioId, $bonoDeposito, $gestionadaPorId > 0 ? $gestionadaPorId : null]);
            $depositoTxId = (int) $pdo->lastInsertId();

            // Registrar movimiento de depósito (+bono_deposito)
            ledger_registrar_movimiento(
                $pdo,
                $usuarioId,
                +$bonoDeposito,
                'deposito',
                $depositoTxId,
                "Depósito por intercambio: «{$tituloDep}»"
            );

            // Registrar movimiento de retiro (-coste_libro)
            ledger_registrar_movimiento(
                $pdo,
                $usuarioId,
                -$costeLibro,
                'retiro',
                $transaccionId,
                "Recogida en intercambio: «{$tituloLibro}»"
            );
        }

        // Actualizar el ejemplar reservado a estado 'retirado'
        $stmtUpdEj = $pdo->prepare("UPDATE ejemplares SET estado = 'retirado' WHERE id = ?");
        $stmtUpdEj->execute([$ejemplarId]);

        // Actualizar la transacción de reserva a estado 'entregada'
        $tokensRegistrados = (int) ($tx['tokens'] > 0 ? $tx['tokens'] : $costeLibro);
        $stmtUpdTx = $pdo->prepare("
            UPDATE transacciones
            SET estado = 'entregada',
                metodo_pago = ?,
                tokens = ?,
                fecha_entrega = NOW(),
                gestionada_por = ?
            WHERE id = ?
        ");
        $stmtUpdTx->execute([
            $metodoPago,
            $tokensRegistrados,
            $gestionadaPorId > 0 ? $gestionadaPorId : null,
            $transaccionId,
        ]);

        // Notificación al usuario
        $stmtNotif = $pdo->prepare('
            INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
            VALUES (?, ?, 0, \'/mis-reservas\', NOW())
        ');
        $stmtNotif->execute([
            $usuarioId,
            "Has recogido tu reserva de «{$tituloLibro}» en el mostrador. ¡Que disfrutes de la lectura!"
        ]);

        // T-AUDIT-01: Auditoría obligatoria con método de pago registrado
        log_accion(
            $gestionadaPorId > 0 ? $gestionadaPorId : null,
            'entrega_completada',
            'transacciones',
            $transaccionId,
            [
                'metodo_pago'     => $metodoPago,
                'usuario_id'      => $usuarioId,
                'libro_id'        => $libroId,
                'ejemplar_id'     => $ejemplarId,
                'tokens_cobrados' => ($metodoPago === 'tokens' ? 0 : $costeLibro),
                'libro_aportado'  => $libroDepositadoId,
                'deposito_tx_id'  => $depositoTxId,
                'reserva_previa'  => true,
            ]
        );

        if ($iniciaTransaccion) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad') && !empty($libroDepositadoId)) {
            wishlist_notificar_disponibilidad($pdo, (int) $libroDepositadoId);
        }

        return [
            'ok'             => true,
            'transaccion_id' => $transaccionId,
            'metodo_pago'    => $metodoPago,
            'libro_titulo'   => $tituloLibro,
            'usuario_id'     => $usuarioId,
        ];
    } catch (Throwable $e) {
        if ($iniciaTransaccion && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Registra el depósito físico de un libro admitido en el mostrador entregando el bono correspondiente.
 *
 * @param PDO         $pdo              Instancia de conexión PDO
 * @param int         $usuarioId        ID del usuario depositante
 * @param int         $libroId          ID del libro en el catálogo
 * @param string      $condicion        Estado físico del ejemplar ('nuevo', 'bueno', 'regular')
 * @param string      $ubicacion        Ubicación física asignada en la biblioteca
 * @param int         $gestionadaPorId  ID del personal o administrador que recepciona el libro
 * @return array Datos informativos del depósito registrado
 * @throws Exception Si el libro o usuario no existen
 */
function mostrador_registrar_deposito(
    PDO $pdo,
    int $usuarioId,
    int $libroId,
    string $condicion = 'bueno',
    string $ubicacion = 'MOSTRADOR',
    int $gestionadaPorId = 0
): array {
    $iniciaTransaccion = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciaTransaccion = true;
    }

    try {
        // Validar libro
        $stmtLib = $pdo->prepare('SELECT id, titulo FROM libros WHERE id = ?');
        $stmtLib->execute([$libroId]);
        $libro = $stmtLib->fetch();
        if (!$libro) {
            throw new Exception('El libro indicado no existe en el catálogo.');
        }
        $tituloLibro = (string) $libro['titulo'];

        // Validar usuario
        $stmtUsr = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE id = ? AND activo = 1');
        $stmtUsr->execute([$usuarioId]);
        $usr = $stmtUsr->fetch();
        if (!$usr) {
            throw new Exception('El usuario depositante no es válido o está inactivo.');
        }

        // Obtener bono de depósito configurado
        $stmtConf = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'bono_deposito' LIMIT 1");
        $bonoDeposito = (int) ($stmtConf->fetchColumn() ?: 1);

        $condicionValida = in_array($condicion, ['nuevo', 'bueno', 'regular'], true) ? $condicion : 'bueno';
        $ubicacionLimpia = trim($ubicacion) !== '' ? trim($ubicacion) : 'MOSTRADOR';

        // T-DEPO-01: Insertar copia en estado 'disponible'
        $stmtEj = $pdo->prepare("
            INSERT INTO ejemplares (libro_id, estado, ubicacion, depositante_id, condicion)
            VALUES (?, 'disponible', ?, ?, ?)
        ");
        $stmtEj->execute([$libroId, $ubicacionLimpia, $usuarioId, $condicionValida]);
        $ejemplarId = (int) $pdo->lastInsertId();

        // Insertar transacción de depósito completada
        $stmtTx = $pdo->prepare("
            INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, estado, fecha_entrega, gestionada_por, created_at)
            VALUES ('deposito', ?, ?, ?, 'entregada', NOW(), ?, NOW())
        ");
        $stmtTx->execute([$ejemplarId, $usuarioId, $bonoDeposito, $gestionadaPorId > 0 ? $gestionadaPorId : null]);
        $txId = (int) $pdo->lastInsertId();

        // Acreditar bono en el ledger inmutable
        ledger_registrar_movimiento(
            $pdo,
            $usuarioId,
            +$bonoDeposito,
            'deposito',
            $txId,
            "Depósito en mostrador: «{$tituloLibro}»"
        );

        // Registrar auditoría
        log_accion(
            $gestionadaPorId > 0 ? $gestionadaPorId : null,
            'deposito_registrado',
            'transacciones',
            $txId,
            [
                'usuario_id'   => $usuarioId,
                'libro_id'     => $libroId,
                'ejemplar_id'  => $ejemplarId,
                'tokens_bono'  => $bonoDeposito,
                'condicion'    => $condicionValida,
            ]
        );

        if ($iniciaTransaccion) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad') && !empty($libroId)) {
            wishlist_notificar_disponibilidad($pdo, (int) $libroId);
        }

        return [
            'ok'             => true,
            'transaccion_id' => $txId,
            'ejemplar_id'    => $ejemplarId,
            'tokens_bono'    => $bonoDeposito,
            'libro_titulo'   => $tituloLibro,
        ];
    } catch (Throwable $e) {
        if ($iniciaTransaccion && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Registra el rechazo motivado de un libro presentado para depósito en el mostrador.
 *
 * @param PDO         $pdo              Instancia de conexión PDO
 * @param int         $usuarioId        ID del usuario que intentó depositar
 * @param string|null $tituloLibro      Título o identificación del libro rechazado
 * @param string      $motivo           Motivo del rechazo (ej: mal estado, no admitido)
 * @param int         $gestionadaPorId  ID del personal o administrador
 */
function mostrador_rechazar_deposito(
    PDO $pdo,
    int $usuarioId,
    ?string $tituloLibro,
    string $motivo,
    int $gestionadaPorId = 0
): void {
    // T-DEPO-02: Registro auditado del rechazo
    log_accion(
        $gestionadaPorId > 0 ? $gestionadaPorId : null,
        'deposito_rechazado',
        'usuarios',
        $usuarioId,
        [
            'usuario_id' => $usuarioId,
            'titulo'     => $tituloLibro,
            'motivo'     => $motivo,
        ]
    );
}

/**
 * Registra la entrega directa y manual de un ejemplar a un lector en el mostrador sin reserva previa.
 *
 * Descuenta el token del saldo del lector (o procesa intercambio con libro aportado),
 * marca el ejemplar físico como 'retirado' en la base de datos para darlo de baja inmediata,
 * crea la transacción de retiro completada y registra la auditoría.
 *
 * @param PDO         $pdo                       Instancia de conexión PDO
 * @param int         $usuarioId                 ID del lector que retira el libro
 * @param int         $ejemplarId                ID del ejemplar físico disponible
 * @param int         $gestionadaPorId           ID del personal o admin que atiende en mostrador
 * @param string      $metodoPago                'tokens' o 'libro'
 * @param int|null    $libroDepositadoId         ID del libro aportado si es intercambio
 * @param string      $condicionLibroDepositado  'nuevo', 'bueno' o 'regular'
 * @return array      Información de la entrega realizada (transacción, libro, tokens cobrados)
 * @throws Exception  Si el ejemplar no está disponible, saldo insuficiente o error en BD
/**
 * Comprueba si un usuario tiene una reserva activa para un ejemplar específico.
 *
 * @param PDO $pdo         Instancia de conexión PDO
 * @param int $ejemplarId ID del ejemplar físico
 * @param int $usuarioId  ID del usuario a comprobar
 * @return array|null     Datos de la reserva activa si existe, o null
 */
function mostrador_verificar_reserva_activa(PDO $pdo, int $ejemplarId, int $usuarioId): ?array {
    $stmt = $pdo->prepare("
        SELECT id, codigo, ejemplar_id, usuario_id, fecha_limite
        FROM transacciones
        WHERE ejemplar_id = ? AND usuario_id = ? AND tipo = 'reserva' AND estado = 'activa'
        LIMIT 1
    ");
    $stmt->execute([$ejemplarId, $usuarioId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Registra la entrega directa y atómica de un ejemplar a un lector en el mostrador sin reserva previa.
 *
 * Reglas de negocio (v4.4 §1):
 * 1. Solo copias en estado 'disponible'. Copia 'reservada' o en otro estado -> denegada.
 * 2. Solo usuarios destino con rol USUARIO y activos.
 * 3. Pago idéntico a entrega de reserva:
 *    a) Tokens: retiro de -coste_libro. Saldo disponible < coste_libro -> denegado sin cambios.
 *    b) Libro: depósito de libro aportado (+bono_deposito, copia disponible) y retiro (-coste_libro).
 *       Si bono_deposito < coste_libro, se exige saldo disponible para la diferencia.
 * 4. Atomicidad: todo el proceso es una única transacción de BD con rollback en cualquier fallo.
 * 5. Registro: nueva fila en transacciones con tipo 'entrega_directa', estado 'entregada', fecha_entrega = NOW(),
 *    metodo_pago (tokens|libro), gestionada_por = staff, usuario_id = receptor, ejemplar_id = copia entregada,
 *    tokens = -coste_libro.
 * 6. Notificación al receptor: "Entrega directa registrada: {titulo}. Método: {tokens|libro aportado}."
 *    + registro en auditoría (accion 'entrega_directa', detalle JSON con metodo_pago, ejemplar y receptor).
 * 7. Si el usuario seleccionado tiene reserva activa para esa copia -> advertencia y sugerencia de ir al flujo de reserva.
 *
 * @param PDO         $pdo                       Instancia de conexión PDO
 * @param int         $usuarioId                 ID del lector que retira el libro
 * @param int         $ejemplarId                ID del ejemplar físico disponible
 * @param int         $gestionadaPorId           ID del personal o admin que atiende en mostrador
 * @param string      $metodoPago                'tokens' o 'libro'
 * @param int|null    $libroDepositadoId         ID del libro aportado si es intercambio
 * @param string      $condicionLibroDepositado  'nuevo', 'bueno' o 'regular'
 * @return array      Información de la entrega realizada
 * @throws Exception  Si no cumple condiciones, saldo insuficiente o error en BD
 */
function mostrador_entrega_directa(
    PDO $pdo,
    int $usuarioId,
    int $ejemplarId,
    int $gestionadaPorId,
    string $metodoPago = 'tokens',
    ?int $libroDepositadoId = null,
    string $condicionLibroDepositado = 'bueno'
): array {
    if (!in_array($metodoPago, ['tokens', 'libro'], true)) {
        throw new InvalidArgumentException("Método de pago inválido: {$metodoPago}");
    }

    $iniciaTransaccion = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciaTransaccion = true;
    }

    try {
        // 1. Verificar usuario receptor: rol USUARIO y activo (§1.2)
        $stmtUsr = $pdo->prepare('
            SELECT u.id, u.nombre, u.email, u.activo, r.nombre AS rol_nombre
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ?
        ');
        $stmtUsr->execute([$usuarioId]);
        $lector = $stmtUsr->fetch(PDO::FETCH_ASSOC);
        if (!$lector) {
            throw new Exception('El usuario receptor indicado no existe.');
        }
        if (strtoupper((string) $lector['rol_nombre']) !== 'USUARIO') {
            throw new Exception('Solo se pueden realizar entregas directas a usuarios con rol USUARIO.');
        }
        if (empty($lector['activo'])) {
            throw new Exception("El usuario {$lector['nombre']} se encuentra inactivo.");
        }

        // 2. Bloquear y verificar el ejemplar a entregar (§1.1)
        $stmtEj = $pdo->prepare('
            SELECT e.id, e.libro_id, e.estado, e.ubicacion, l.titulo, l.autor
            FROM ejemplares e
            JOIN libros l ON l.id = e.libro_id
            WHERE e.id = ?
            FOR UPDATE
        ');
        $stmtEj->execute([$ejemplarId]);
        $ejemplar = $stmtEj->fetch(PDO::FETCH_ASSOC);

        if (!$ejemplar) {
            throw new Exception('El ejemplar seleccionado no existe en el sistema.');
        }

        // Comprobar si el usuario tiene reserva activa para esta misma copia (§1.7)
        $reservaActiva = mostrador_verificar_reserva_activa($pdo, $ejemplarId, $usuarioId);
        if ($reservaActiva) {
            throw new Exception("El usuario ya tiene una reserva activa para este ejemplar (código: {$reservaActiva['codigo']}). Sugerencia: Ir a entregar su reserva.");
        }

        if ($ejemplar['estado'] !== 'disponible') {
            throw new Exception("El ejemplar #{$ejemplarId} no está disponible para entrega directa (estado actual: {$ejemplar['estado']}).");
        }

        // 3. Obtener configuración económica
        $stmtConf = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('coste_libro', 'bono_deposito')");
        $conf = $stmtConf->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $costeLibro = (int) ($conf['coste_libro'] ?? 1);
        $bonoDeposito = (int) ($conf['bono_deposito'] ?? 1);

        $depositoTxId = null;
        $nuevoEjemplarId = null;

        // Comprobar saldo previo del receptor con bloqueo pesimista
        $stmtSaldo = $pdo->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = ? FOR UPDATE');
        $stmtSaldo->execute([$usuarioId]);
        $saldoActual = (int) $stmtSaldo->fetchColumn();

        $codigoDirecto = 'DIR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // 4. Procesar según forma de pago (§1.3)
        if ($metodoPago === 'tokens') {
            if ($saldoActual < $costeLibro) {
                throw new Exception("Saldo insuficiente del lector (dispone de {$saldoActual} tokens, se requieren {$costeLibro}).");
            }

            // Insertar transacción tipo 'entrega_directa', estado 'entregada' (§1.5)
            $stmtTx = $pdo->prepare("
                INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, metodo_pago, codigo, estado, fecha_entrega, gestionada_por, created_at)
                VALUES ('entrega_directa', ?, ?, ?, 'tokens', ?, 'entregada', NOW(), ?, NOW())
            ");
            $stmtTx->execute([
                $ejemplarId,
                $usuarioId,
                -$costeLibro,
                $codigoDirecto,
                $gestionadaPorId > 0 ? $gestionadaPorId : null,
            ]);
            $retiroTxId = (int) $pdo->lastInsertId();

            // Movimiento en el ledger contable (tipo: 'retiro', cantidad: -coste_libro)
            ledger_registrar_movimiento(
                $pdo,
                $usuarioId,
                -$costeLibro,
                'retiro',
                $retiroTxId,
                "Entrega directa en mostrador: «{$ejemplar['titulo']}»"
            );
        } else {
            // Intercambio aportando un libro
            if ($libroDepositadoId === null || $libroDepositadoId <= 0) {
                throw new Exception('Debes indicar el libro aportado por el socio para el intercambio.');
            }

            if ($bonoDeposito < $costeLibro) {
                $diferencia = $costeLibro - $bonoDeposito;
                if ($saldoActual < $diferencia) {
                    throw new Exception("Saldo insuficiente del lector para cubrir la diferencia (dispone de {$saldoActual} tokens, diferencia requerida: {$diferencia}).");
                }
            }

            $stmtLibDep = $pdo->prepare('SELECT id, titulo FROM libros WHERE id = ?');
            $stmtLibDep->execute([$libroDepositadoId]);
            $libroDep = $stmtLibDep->fetch(PDO::FETCH_ASSOC);
            if (!$libroDep) {
                throw new Exception('El libro aportado no existe en el catálogo.');
            }

            $condicionValida = in_array($condicionLibroDepositado, ['nuevo', 'bueno', 'regular'], true)
                ? $condicionLibroDepositado
                : 'bueno';

            // Insertar ejemplar aportado a estado 'disponible'
            $stmtInsEj = $pdo->prepare("
                INSERT INTO ejemplares (libro_id, estado, ubicacion, depositante_id, condicion)
                VALUES (?, 'disponible', 'MOSTRADOR', ?, ?)
            ");
            $stmtInsEj->execute([$libroDepositadoId, $usuarioId, $condicionValida]);
            $nuevoEjemplarId = (int) $pdo->lastInsertId();

            // Insertar transacción de depósito del libro aportado (§1.5)
            $stmtInsDepTx = $pdo->prepare("
                INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, estado, fecha_entrega, gestionada_por, created_at)
                VALUES ('deposito', ?, ?, ?, 'entregada', NOW(), ?, NOW())
            ");
            $stmtInsDepTx->execute([$nuevoEjemplarId, $usuarioId, $bonoDeposito, $gestionadaPorId > 0 ? $gestionadaPorId : null]);
            $depositoTxId = (int) $pdo->lastInsertId();

            // 1er movimiento de ledger: depósito (+bono_deposito)
            ledger_registrar_movimiento(
                $pdo,
                $usuarioId,
                +$bonoDeposito,
                'deposito',
                $depositoTxId,
                "Depósito por intercambio en mostrador: «{$libroDep['titulo']}»"
            );

            // Insertar transacción tipo 'entrega_directa', estado 'entregada'
            $stmtTx = $pdo->prepare("
                INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, metodo_pago, codigo, estado, fecha_entrega, gestionada_por, created_at)
                VALUES ('entrega_directa', ?, ?, ?, 'libro', ?, 'entregada', NOW(), ?, NOW())
            ");
            $stmtTx->execute([
                $ejemplarId,
                $usuarioId,
                -$costeLibro,
                $codigoDirecto,
                $gestionadaPorId > 0 ? $gestionadaPorId : null,
            ]);
            $retiroTxId = (int) $pdo->lastInsertId();

            // 2º movimiento de ledger: retiro (-coste_libro)
            ledger_registrar_movimiento(
                $pdo,
                $usuarioId,
                -$costeLibro,
                'retiro',
                $retiroTxId,
                "Entrega en intercambio en mostrador: «{$ejemplar['titulo']}»"
            );
        }

        // 5. Dar de baja el ejemplar entregado: cambiar de 'disponible' a 'retirado'
        $stmtUpd = $pdo->prepare("UPDATE ejemplares SET estado = 'retirado' WHERE id = ?");
        $stmtUpd->execute([$ejemplarId]);

        // 6. Notificación al receptor (§1.6)
        $metodoDesc = ($metodoPago === 'tokens') ? 'tokens' : 'libro aportado';
        $mensajeNotif = "Entrega directa registrada: {$ejemplar['titulo']}. Método: {$metodoDesc}.";
        $stmtNotif = $pdo->prepare('
            INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
            VALUES (?, ?, 0, \'/mi-historial\', NOW())
        ');
        $stmtNotif->execute([$usuarioId, $mensajeNotif]);

        // 7. Registro en auditoría (§1.6)
        log_accion(
            $gestionadaPorId > 0 ? $gestionadaPorId : null,
            'entrega_directa',
            'transacciones',
            $retiroTxId,
            [
                'metodo_pago' => $metodoPago,
                'ejemplar_id' => $ejemplarId,
                'ejemplar'    => $ejemplarId,
                'usuario_id'  => $usuarioId,
                'receptor_id' => $usuarioId,
                'receptor'    => $usuarioId,
                'libro_id'    => $ejemplar['libro_id'],
                'titulo'      => $ejemplar['titulo'],
                'coste_tokens'=> $costeLibro,
                'deposito_id' => $depositoTxId,
            ]
        );

        if ($iniciaTransaccion) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad') && !empty($libroDepositadoId)) {
            wishlist_notificar_disponibilidad($pdo, (int) $libroDepositadoId);
        }

        return [
            'ok'             => true,
            'transaccion_id' => $retiroTxId,
            'ejemplar_id'    => $ejemplarId,
            'titulo'         => $ejemplar['titulo'],
            'usuario_id'     => $usuarioId,
            'usuario_nombre' => $lector['nombre'],
            'metodo_pago'    => $metodoPago,
            'tokens_cobrados'=> $costeLibro,
            'deposito_tx_id' => $depositoTxId,
        ];
    } catch (Throwable $e) {
        if ($iniciaTransaccion && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Procesa una lectura del escáner universal de mostrador (§2.2, §2.3).
 * Identifica si la entrada corresponde a:
 *  1. 'reserva': Código RES-...
 *  2. 'isbn': Código numérico de 10 o 13 dígitos o prefijo 978/979
 *  3. 'usuario': Correo electrónico de un usuario registrado
 *  4. 'desconocido': Cualquier otra entrada no clasificada
 *
 * @param PDO    $pdo
 * @param string $valorBruto Cadena leída por el escáner o tecleada
 * @return array{ok: bool, tipo: string, datos: ?array, error?: string, accion_sugerida?: ?string}
 */
function mostrador_escanear(PDO $pdo, string $valorBruto): array {
    $valor = trim($valorBruto);
    if ($valor === '') {
        return [
            'ok' => false,
            'tipo' => 'desconocido',
            'datos' => null,
            'error' => 'Entrada vacía',
            'accion_sugerida' => null,
        ];
    }

    // 1. Detectar Código de Reserva (prefijo RES-)
    if (stripos($valor, 'RES-') === 0) {
        $res = mostrador_buscar_reserva($pdo, $valor);
        if ($res && $res['estado'] === 'activa') {
            return [
                'ok' => true,
                'tipo' => 'reserva',
                'datos' => [
                    'id' => (int) $res['transaccion_id'],
                    'transaccion_id' => (int) $res['transaccion_id'],
                    'codigo' => $res['codigo'],
                    'estado' => $res['estado'],
                    'libro_id' => (int) $res['libro_id'],
                    'libro_titulo' => $res['titulo'],
                    'libro_autor' => $res['autor'],
                    'usuario_id' => (int) $res['usuario_id'],
                    'usuario_nombre' => $res['usuario_nombre'],
                    'usuario_email' => $res['usuario_email'],
                    'usuario_saldo' => (int) ($res['usuario_saldo'] ?? 0),
                    'ejemplar_id' => (int) $res['ejemplar_id'],
                    'fecha_limite' => $res['fecha_limite'],
                ],
                'accion_sugerida' => '/mostrador?tab=reserva&codigo=' . urlencode($res['codigo']),
            ];
        }
        return [
            'ok' => false,
            'tipo' => 'reserva',
            'datos' => null,
            'error' => 'No se encontró ninguna reserva activa con el código «' . $valor . '».',
            'accion_sugerida' => null,
        ];
    }

    // Limpieza de caracteres para verificación de ISBN
    $limpioNum = preg_replace('/[^0-9X]/i', '', $valor);
    $len = strlen($limpioNum);

    // 2. Detectar ISBN (10 o 13 dígitos, o empieza por 978/979)
    $esIsbn = false;
    if ($len === 10 || $len === 13) {
        $esIsbn = true;
    } elseif (str_starts_with($limpioNum, '978') || str_starts_with($limpioNum, '979')) {
        $esIsbn = true;
    }

    if ($esIsbn) {
        $stmtLibro = $pdo->prepare('
            SELECT l.*,
                   (SELECT COUNT(*) FROM ejemplares e WHERE e.libro_id = l.id AND e.estado = "disponible") AS copias_disponibles
            FROM libros l
            WHERE l.isbn13 = ? OR REPLACE(l.isbn13, "-", "") = ?
            LIMIT 1
        ');
        $stmtLibro->execute([$valor, $limpioNum]);
        $libro = $stmtLibro->fetch();

        if ($libro) {
            $accionSugerida = '/libro/' . (int) $libro['id'];
            if ((int) $libro['copias_disponibles'] === 0) {
                $accionSugerida .= '?accion=anadir_copias';
            }

            // Obtener la primera copia disponible del libro para agilizar la entrega en mostrador
            $stmtCopia = $pdo->prepare('
                SELECT id, condicion, ubicacion
                FROM ejemplares
                WHERE libro_id = ? AND estado = "disponible"
                ORDER BY id ASC
                LIMIT 1
            ');
            $stmtCopia->execute([(int) $libro['id']]);
            $copia = $stmtCopia->fetch(PDO::FETCH_ASSOC);

            return [
                'ok' => true,
                'tipo' => 'isbn',
                'datos' => [
                    'libro_id' => (int) $libro['id'],
                    'titulo' => $libro['titulo'],
                    'autor' => $libro['autor'],
                    'isbn13' => $libro['isbn13'],
                    'copias_disponibles' => (int) $libro['copias_disponibles'],
                    'url_ficha' => '/libro/' . (int) $libro['id'],
                    'ejemplar_id' => $copia ? (int) $copia['id'] : null,
                    'condicion' => $copia ? $copia['condicion'] : 'bueno',
                    'ubicacion' => $copia ? $copia['ubicacion'] : 'MOSTRADOR',
                ],
                'accion_sugerida' => $accionSugerida,
            ];
        } else {
            return [
                'ok' => false,
                'tipo' => 'isbn',
                'datos' => [
                    'libro_id' => null,
                    'isbn' => $limpioNum,
                    'copias_disponibles' => 0,
                    'url_alta' => '/admin/libros/nuevo?isbn=' . urlencode($limpioNum),
                ],
                'error' => 'Libro no encontrado en el catálogo local.',
                'accion_sugerida' => '/admin/libros/nuevo?isbn=' . urlencode($limpioNum),
            ];
        }
    }

    // 2.b Detectar código de ejemplar físico (#12, EJ-12 o ID numérico directo)
    $limpioEj = preg_replace('/^(EJ|EJEMPLAR|#)[-_ ]*/i', '', $valor);
    if (ctype_digit($limpioEj) && strlen($limpioEj) <= 8 && !str_starts_with($limpioEj, '978') && !str_starts_with($limpioEj, '979')) {
        $stmtEj = $pdo->prepare('
            SELECT e.id AS ejemplar_id, e.libro_id, e.condicion, e.ubicacion, e.estado,
                   l.titulo, l.autor, l.isbn13,
                   (SELECT COUNT(*) FROM ejemplares WHERE libro_id = l.id AND estado = "disponible") AS copias_disponibles
            FROM ejemplares e
            JOIN libros l ON l.id = e.libro_id
            WHERE e.id = ?
            LIMIT 1
        ');
        $stmtEj->execute([(int) $limpioEj]);
        $ej = $stmtEj->fetch(PDO::FETCH_ASSOC);
        if ($ej) {
            return [
                'ok' => true,
                'tipo' => 'isbn',
                'datos' => [
                    'libro_id' => (int) $ej['libro_id'],
                    'titulo' => $ej['titulo'],
                    'autor' => $ej['autor'],
                    'isbn13' => $ej['isbn13'],
                    'copias_disponibles' => (int) $ej['copias_disponibles'],
                    'url_ficha' => '/libro/' . (int) $ej['libro_id'],
                    'ejemplar_id' => (int) $ej['ejemplar_id'],
                    'condicion' => $ej['condicion'],
                    'ubicacion' => $ej['ubicacion'],
                ],
                'accion_sugerida' => '/libro/' . (int) $ej['libro_id'],
            ];
        }
    }

    // 3. Detectar usuario (por correo electrónico)
    if (filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        $stmtUser = $pdo->prepare('
            SELECT id AS usuario_id, nombre, email, activo
            FROM usuarios
            WHERE LOWER(email) = LOWER(?)
            LIMIT 1
        ');
        $stmtUser->execute([$valor]);
        $socio = $stmtUser->fetch();

        if ($socio) {
            $saldo = ledger_obtener_saldo($pdo, (int) $socio['usuario_id']);
            return [
                'ok' => true,
                'tipo' => 'usuario',
                'datos' => [
                    'usuario_id' => (int) $socio['usuario_id'],
                    'nombre' => $socio['nombre'],
                    'email' => $socio['email'],
                    'saldo' => $saldo,
                    'activo' => (int) $socio['activo'],
                ],
                'accion_sugerida' => '/mostrador?usuario_id=' . (int) $socio['usuario_id'],
            ];
        }
    }

    return [
        'ok' => false,
        'tipo' => 'desconocido',
        'datos' => null,
        'error' => 'Código no reconocido (no coincide con formato de reserva, ISBN ni email).',
        'accion_sugerida' => null,
    ];
}

/**
 * Entrada unificada de copias de un libro (Fase 19 §1.1).
 *
 * Unifica el alta de stock y el registro de depósito:
 * - Si $depositanteId es NULL: alta de stock de N copias. Transacción tipo 'deposito' (tokens = 0)
 *   sin movimientos de tokens en el ledger.
 * - Si $depositanteId es un ID de usuario válido: depósito con acreditación de +bono_deposito
 *   por cada copia añadida (N * bono_deposito), transacción tipo 'deposito' y notificación.
 *
 * @param PDO      $pdo            Conexión PDO
 * @param int      $libroId        ID del libro en catálogo
 * @param int      $copias         Número de copias a incorporar (1 a 20)
 * @param string   $condicion      Condición física ('nuevo','como_nuevo','bueno','aceptable','deteriorado')
 * @param string   $ubicacion      Ubicación en estantería/mostrador
 * @param int|null $depositanteId  ID del usuario depositante (o null para stock del centro)
 * @param int      $operadorId     ID del usuario del personal que opera
 * @return array   Resultado con copias creadas, transacciones, y tokens acreditados
 */
function libro_entrada_copias(
    PDO $pdo,
    int $libroId,
    int $copias = 1,
    string $condicion = 'bueno',
    string $ubicacion = 'MOSTRADOR',
    string|int|null $depositanteId = null,
    int $operadorId = 0
): array {
    $copias = max(1, min(20, $copias));
    $condicionesValidas = ['nuevo', 'como_nuevo', 'bueno', 'aceptable', 'deteriorado'];
    $condicionLimpia = in_array($condicion, $condicionesValidas, true) ? $condicion : 'bueno';
    $ubicacionLimpia = trim($ubicacion) !== '' ? trim($ubicacion) : 'MOSTRADOR';

    $iniciaTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciaTx = true;
    }

    try {
        // Validar libro
        $stmtLib = $pdo->prepare('SELECT id, titulo, autor, isbn13 FROM libros WHERE id = ?');
        $stmtLib->execute([$libroId]);
        $libro = $stmtLib->fetch();
        if (!$libro) {
            throw new Exception('El libro indicado no existe en el catálogo.');
        }

        // Validar depositante si viene indicado (por ID numérico o email)
        $depositante = null;
        if ($depositanteId !== null && trim((string) $depositanteId) !== '') {
            $depStr = trim((string) $depositanteId);
            if (is_numeric($depStr)) {
                $stmtUsr = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE id = ? AND activo = 1');
                $stmtUsr->execute([(int) $depStr]);
                $depositante = $stmtUsr->fetch();
            }
            if (!$depositante) {
                $stmtEmail = $pdo->prepare('SELECT id, nombre, email, activo FROM usuarios WHERE LOWER(email) = LOWER(?) AND activo = 1');
                $stmtEmail->execute([$depStr]);
                $depositante = $stmtEmail->fetch();
            }
            if (!$depositante) {
                throw new Exception('El usuario depositante no es válido o está inactivo.');
            }
        }

        // Obtener bono de depósito configurado
        $stmtConf = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'bono_deposito' LIMIT 1");
        $bonoUnitario = (int) ($stmtConf->fetchColumn() ?: 1);

        $ejemplaresCreados = [];
        $transaccionesCreadas = [];
        $totalTokensAcreditados = 0;

        for ($i = 0; $i < $copias; $i++) {
            // 1. Insertar ejemplar
            $stmtEj = $pdo->prepare("
                INSERT INTO ejemplares (libro_id, estado, ubicacion, depositante_id, condicion, fecha_ingreso)
                VALUES (?, 'disponible', ?, ?, ?, NOW())
            ");
            $stmtEj->execute([$libroId, $ubicacionLimpia, $depositante ? $depositante['id'] : null, $condicionLimpia]);
            $ejId = (int) $pdo->lastInsertId();
            $ejemplaresCreados[] = $ejId;

            // 2. Transacción tipo 'deposito'
            $tokensTx = $depositante ? $bonoUnitario : 0;
            $usuarioTxId = $depositante ? (int) $depositante['id'] : ($operadorId > 0 ? $operadorId : null);

            $stmtTx = $pdo->prepare("
                INSERT INTO transacciones (tipo, ejemplar_id, usuario_id, tokens, estado, fecha_entrega, gestionada_por, created_at)
                VALUES ('deposito', ?, ?, ?, 'entregada', NOW(), ?, NOW())
            ");
            $stmtTx->execute([$ejId, $usuarioTxId, $tokensTx, $operadorId > 0 ? $operadorId : null]);
            $txId = (int) $pdo->lastInsertId();
            $transaccionesCreadas[] = $txId;

            // 3. Si hay depositante: ledger + notificación
            if ($depositante) {
                ledger_registrar_movimiento(
                    $pdo,
                    (int) $depositante['id'],
                    +$bonoUnitario,
                    'deposito',
                    $txId,
                    "Depósito en mostrador: «{$libro['titulo']}» (Copia #{$ejId})"
                );
                $totalTokensAcreditados += $bonoUnitario;

                notificacion_crear(
                    $pdo,
                    (int) $depositante['id'],
                    "Has recibido +{$bonoUnitario} token(s) por el depósito de «{$libro['titulo']}» (Copia #{$ejId}).",
                    'deposito',
                    $txId
                );
            }
        }

        // 4. Registro de auditoría (Fase 19 §1.1)
        log_accion(
            $operadorId > 0 ? $operadorId : null,
            'entrada_copias',
            'ejemplares',
            $libroId,
            [
                'libro_id'         => $libroId,
                'libro_titulo'     => $libro['titulo'],
                'copias'           => $copias,
                'ejemplares_ids'   => $ejemplaresCreados,
                'depositante_id'   => $depositante ? (int) $depositante['id'] : null,
                'condicion'        => $condicionLimpia,
                'tokens_total'     => $totalTokensAcreditados,
            ]
        );

        if ($iniciaTx) {
            $pdo->commit();
        }

        if (function_exists('wishlist_notificar_disponibilidad') && !empty($libroId)) {
            wishlist_notificar_disponibilidad($pdo, (int) $libroId);
        }

        return [
            'ok'                     => true,
            'copias'                 => $copias,
            'copias_creadas'         => $copias,
            'ejemplares_ids'         => $ejemplaresCreados,
            'transacciones_ids'      => $transaccionesCreadas,
            'tokens_bono_total'      => $totalTokensAcreditados,
            'tokens_acreditados'     => $totalTokensAcreditados,
            'depositante_id'         => $depositante ? (int) $depositante['id'] : null,
            'depositante'            => $depositante,
            'libro'                  => $libro,
        ];
    } catch (Throwable $e) {
        if ($iniciaTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Actualiza la condición y ubicación física de un ejemplar con auditoría (Fase 19 §2.2).
 */
function ejemplar_editar(PDO $pdo, int $ejemplarId, string $condicion, string $ubicacion, int $operadorId = 0): array {
    $stmt = $pdo->prepare('SELECT id, libro_id, estado, condicion, ubicacion FROM ejemplares WHERE id = ?');
    $stmt->execute([$ejemplarId]);
    $ej = $stmt->fetch();
    if (!$ej) {
        throw new Exception('Ejemplar no encontrado.');
    }

    $condicionesValidas = ['nuevo', 'como_nuevo', 'bueno', 'aceptable', 'deteriorado'];
    $condicionLimpia = in_array($condicion, $condicionesValidas, true) ? $condicion : $ej['condicion'];
    $ubicacionLimpia = trim($ubicacion) !== '' ? trim($ubicacion) : ($ej['ubicacion'] ?: 'MOSTRADOR');

    $stmtUp = $pdo->prepare('UPDATE ejemplares SET condicion = ?, ubicacion = ? WHERE id = ?');
    $stmtUp->execute([$condicionLimpia, $ubicacionLimpia, $ejemplarId]);

    log_accion(
        $operadorId > 0 ? $operadorId : null,
        'ejemplar_editado',
        'ejemplares',
        $ejemplarId,
        [
            'condicion_anterior' => $ej['condicion'],
            'condicion_nueva'    => $condicionLimpia,
            'ubicacion_anterior' => $ej['ubicacion'],
            'ubicacion_nueva'    => $ubicacionLimpia,
        ]
    );

    return [
        'ok' => true,
        'ejemplar_id' => $ejemplarId,
        'libro_id' => (int) $ej['libro_id'],
        'condicion' => $condicionLimpia,
        'ubicacion' => $ubicacionLimpia,
    ];
}

/**
 * Da de baja un ejemplar físico con motivo obligatorio y registro en auditoría (Fase 19 §2.2).
 */
function ejemplar_dar_de_baja(PDO $pdo, int $ejemplarId, string $motivo, int $operadorId = 0): array {
    $motivoLimpio = trim($motivo);
    if ($motivoLimpio === '') {
        throw new Exception('El motivo de la baja es obligatorio.');
    }

    $stmt = $pdo->prepare('SELECT id, libro_id, estado, condicion, ubicacion FROM ejemplares WHERE id = ?');
    $stmt->execute([$ejemplarId]);
    $ej = $stmt->fetch();
    if (!$ej) {
        throw new Exception('Ejemplar no encontrado.');
    }

    // Si la copia está reservada, denegar: primero cancelar reserva
    if ($ej['estado'] === 'reservado') {
        throw new Exception('No se puede dar de baja una copia reservada. Cancela primero la reserva activa.');
    }

    $estadoAnterior = (string) $ej['estado'];
    $stmtUp = $pdo->prepare("UPDATE ejemplares SET estado = 'baja' WHERE id = ?");
    $stmtUp->execute([$ejemplarId]);

    log_accion(
        $operadorId > 0 ? $operadorId : null,
        'ejemplar_baja',
        'ejemplares',
        $ejemplarId,
        [
            'ejemplar_id'     => $ejemplarId,
            'libro_id'        => (int) $ej['libro_id'],
            'motivo'          => $motivoLimpio,
            'estado_anterior' => $estadoAnterior,
        ]
    );

    return [
        'ok' => true,
        'ejemplar_id' => $ejemplarId,
        'libro_id' => (int) $ej['libro_id'],
        'motivo' => $motivoLimpio,
    ];
}

/**
 * Obtiene el historial completo de trazabilidad vertical de una copia física (Fase 19 §2.3).
 */
function ejemplar_obtener_trazabilidad(PDO $pdo, int $ejemplarId): ?array {
    $stmtEj = $pdo->prepare('
        SELECT e.*,
               l.titulo AS libro_titulo,
               l.autor AS libro_autor,
               l.isbn13 AS libro_isbn13,
               l.editorial AS libro_editorial,
               l.genero AS libro_genero,
               l.portada_url AS libro_portada_url,
               u.id AS depositante_usuario_id,
               u.nombre AS depositante_nombre,
               u.email AS depositante_email
        FROM ejemplares e
        JOIN libros l ON l.id = e.libro_id
        LEFT JOIN usuarios u ON u.id = e.depositante_id
        WHERE e.id = ?
    ');
    $stmtEj->execute([$ejemplarId]);
    $ejemplar = $stmtEj->fetch();
    if (!$ejemplar) {
        return null;
    }

    $eventos = [];

    // 1. Evento de Entrada / Alta
    $eventos[] = [
        'tipo' => 'entrada',
        'fecha' => $ejemplar['fecha_ingreso'],
        'titulo' => 'Entrada en el catálogo',
        'descripcion' => $ejemplar['depositante_nombre'] 
            ? 'Depositado por ' . $ejemplar['depositante_nombre']
            : 'Alta directa de stock de biblioteca',
        'badge' => '📥 Entrada',
        'badge_class' => 'bg-success',
        'icono' => 'bi-box-arrow-in-down',
        'condicion' => $ejemplar['condicion'],
        'ubicacion' => $ejemplar['ubicacion'],
    ];

    // 2. Transacciones vinculadas a esta copia (reservas, depósitos, entregas)
    $stmtTx = $pdo->prepare('
        SELECT t.*,
               u.nombre AS usuario_nombre,
               u.email AS usuario_email,
               g.nombre AS gestor_nombre
        FROM transacciones t
        JOIN usuarios u ON u.id = t.usuario_id
        LEFT JOIN usuarios g ON g.id = t.gestionada_por
        WHERE t.ejemplar_id = ?
        ORDER BY t.created_at ASC
    ');
    $stmtTx->execute([$ejemplarId]);
    $transacciones = $stmtTx->fetchAll();

    foreach ($transacciones as $tx) {
        if ($tx['tipo'] === 'reserva') {
            $eventos[] = [
                'tipo' => 'reserva',
                'fecha' => $tx['created_at'],
                'titulo' => 'Reserva ' . ($tx['codigo'] ?: '#' . $tx['id']),
                'descripcion' => 'Reservado por ' . $tx['usuario_nombre'] . '. Estado: ' . $tx['estado'],
                'badge' => '🎟️ Reserva',
                'badge_class' => $tx['estado'] === 'activa' ? 'bg-primary' : ($tx['estado'] === 'entregada' ? 'bg-info' : 'bg-secondary'),
                'icono' => 'bi-bookmark-check',
                'codigo' => $tx['codigo'],
                'estado' => $tx['estado'],
                'fecha_limite' => $tx['fecha_limite'],
            ];

            if ($tx['estado'] === 'entregada' && !empty($tx['fecha_entrega'])) {
                $eventos[] = [
                    'tipo' => 'entrega',
                    'fecha' => $tx['fecha_entrega'],
                    'titulo' => 'Entrega de reserva en mostrador',
                    'descripcion' => 'Entregado a ' . $tx['usuario_nombre'] . ' (Pago: ' . ($tx['metodo_pago'] === 'libro' ? 'intercambio de libro' : 'tokens') . ')' . ($tx['gestor_nombre'] ? ' · Atendido por: ' . $tx['gestor_nombre'] : ''),
                    'badge' => '📤 Entrega',
                    'badge_class' => 'bg-success',
                    'icono' => 'bi-check2-circle',
                    'metodo_pago' => $tx['metodo_pago'],
                ];
            }
        } elseif ($tx['tipo'] === 'entrega_directa') {
            $eventos[] = [
                'tipo' => 'entrega',
                'fecha' => $tx['fecha_entrega'] ?: $tx['created_at'],
                'titulo' => 'Entrega directa en mostrador',
                'descripcion' => 'Entregado a ' . $tx['usuario_nombre'] . ' (Pago: ' . ($tx['metodo_pago'] === 'libro' ? 'intercambio de libro' : 'tokens') . ')' . ($tx['gestor_nombre'] ? ' · Atendido por: ' . $tx['gestor_nombre'] : ''),
                'badge' => '🚀 Entrega directa',
                'badge_class' => 'bg-primary',
                'icono' => 'bi-send-check',
                'metodo_pago' => $tx['metodo_pago'],
            ];
        }
    }

    // 3. Auditoría de la copia (ejemplar_baja, ejemplar_editado)
    $stmtAud = $pdo->prepare('
        SELECT a.*, u.nombre AS operador_nombre
        FROM registro_auditoria a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.entidad = "ejemplares" AND a.entidad_id = ?
        ORDER BY a.fecha ASC
    ');
    $stmtAud->execute([$ejemplarId]);
    $auditorias = $stmtAud->fetchAll();

    foreach ($auditorias as $aud) {
        $det = json_decode((string) $aud['detalle'], true) ?: [];
        if ($aud['accion'] === 'ejemplar_baja') {
            $eventos[] = [
                'tipo' => 'baja',
                'fecha' => $aud['fecha'],
                'titulo' => 'Copia dada de baja',
                'descripcion' => 'Motivo: ' . ($det['motivo'] ?? 'No especificado') . ($aud['operador_nombre'] ? ' · Registrado por: ' . $aud['operador_nombre'] : ''),
                'badge' => '🚫 Baja',
                'badge_class' => 'bg-danger',
                'icono' => 'bi-slash-circle',
                'motivo' => $det['motivo'] ?? '',
            ];
        } elseif ($aud['accion'] === 'ejemplar_editado') {
            $eventos[] = [
                'tipo' => 'edicion',
                'fecha' => $aud['fecha'],
                'titulo' => 'Condición / ubicación actualizada',
                'descripcion' => 'Nueva condición: ' . ($det['condicion_nueva'] ?? '—') . ', nueva ubicación: ' . ($det['ubicacion_nueva'] ?? '—'),
                'badge' => '✏️ Modificación',
                'badge_class' => 'bg-secondary',
                'icono' => 'bi-pencil',
            ];
        }
    }

    // Ordenar cronológicamente
    usort($eventos, fn($a, $b) => strcmp((string)($a['fecha'] ?? ''), (string)($b['fecha'] ?? '')));

    return [
        'ejemplar' => $ejemplar,
        'libro'    => [
            'id'          => (int) $ejemplar['libro_id'],
            'titulo'      => $ejemplar['libro_titulo'],
            'autor'       => $ejemplar['libro_autor'],
            'isbn13'      => $ejemplar['libro_isbn13'],
            'editorial'   => $ejemplar['libro_editorial'],
            'genero'      => $ejemplar['libro_genero'],
            'portada_url' => $ejemplar['libro_portada_url'],
        ],
        'eventos'  => $eventos,
    ];
}

/**
 * Obtiene el listado de reservas activas pendientes de entrega física,
 * ordenadas cronológicamente por fecha de reserva (las más antiguas primero).
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @return array Lista de reservas pendientes con datos del libro, ejemplar, usuario y saldo
 */
function mostrador_listar_reservas_pendientes(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT t.id AS transaccion_id,
               t.codigo,
               t.created_at AS fecha_reserva,
               t.fecha_limite,
               (t.fecha_limite IS NOT NULL AND t.fecha_limite < NOW()) AS ha_expirado,
               u.id AS usuario_id,
               u.nombre AS usuario_nombre,
               u.email AS usuario_email,
               COALESCE((SELECT SUM(cantidad) FROM movimientos_tokens WHERE usuario_id = u.id), 0) AS usuario_saldo,
               e.id AS ejemplar_id,
               e.ubicacion,
               e.condicion,
               l.id AS libro_id,
               l.titulo,
               l.autor,
               l.isbn13,
               l.portada_url
        FROM transacciones t
        JOIN usuarios u ON u.id = t.usuario_id
        JOIN ejemplares e ON e.id = t.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE t.tipo = 'reserva' AND t.estado = 'activa'
        ORDER BY t.created_at ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
