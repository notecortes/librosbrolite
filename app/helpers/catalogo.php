<?php
/**
 * BookSwap · catalogo.php — Helper de gestión del catálogo de libros y ejemplares (v4.1).
 *
 * Funcionalidades:
 * - Listado y filtrado de libros con paginación server-side.
 * - Búsqueda por texto (título, autor, género, ISBN) y disponibilidad.
 * - CRUD de libros admitidos con registro de auditoría.
 * - CRUD de ejemplares físicos (condición, ubicación, estado).
 * - Autocompletado rápido para sugerencias de búsqueda.
 */
declare(strict_types=1);

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/auditoria.php';

/**
 * Asegura que las columnas de baja existan en la tabla libros (auto-migración segura).
 */
function catalogo_asegurar_columnas_libro(PDO $pdo): void {
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM libros LIKE 'estado'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("
                ALTER TABLE libros 
                ADD COLUMN estado ENUM('activo','baja') NOT NULL DEFAULT 'activo',
                ADD COLUMN motivo_baja VARCHAR(255) NULL,
                ADD COLUMN fecha_baja DATETIME NULL,
                ADD INDEX idx_libros_estado (estado)
            ");
        }
    } catch (Throwable $e) {
        // En caso de que el usuario de BD no tenga permisos ALTER, continúa con el esquema existente
    }
}

/**
 * Obtiene el listado paginado de libros del catálogo aplicando filtros opcionales.
 *
 * @param PDO   $pdo              Instancia activa de base de datos
 * @param array $filtros          Filtros de búsqueda (q, genero, solo_disponibles)
 * @param int   $pagina           Número de página actual (1-indexed)
 * @param int   $elementosPagina  Cantidad de libros por página
 * @return array{libros: array, total: int, pagina: int, totalPaginas: int}
 */
function catalogo_listar_libros(PDO $pdo, array $filtros = [], int $pagina = 1, int $elementosPagina = 12): array {
    catalogo_asegurar_columnas_libro($pdo);
    $where = ['1=1'];
    $params = [];

    // Filtro de búsqueda libre: busca en título, autor, género e ISBN-13
    $q = trim((string) ($filtros['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(l.titulo LIKE :q1 OR l.autor LIKE :q2 OR l.genero LIKE :q3 OR l.isbn13 LIKE :q4)';
        $params[':q1'] = '%' . $q . '%';
        $params[':q2'] = '%' . $q . '%';
        $params[':q3'] = '%' . $q . '%';
        $params[':q4'] = '%' . $q . '%';
    }

    // Filtro por género exacto
    $genero = trim((string) ($filtros['genero'] ?? ''));
    if ($genero !== '') {
        $where[] = 'l.genero = :genero';
        $params[':genero'] = $genero;
    }

    // Filtro por estado del libro (por defecto ocultar dados de baja salvo que se solicite incluirlos)
    if (empty($filtros['incluir_bajas'])) {
        $where[] = "(l.estado IS NULL OR l.estado != 'baja')";
    } elseif (!empty($filtros['solo_bajas'])) {
        $where[] = "l.estado = 'baja'";
    }

    $whereSql = implode(' AND ', $where);

    // Conteo total de libros que coinciden con los filtros base
    $sqlCount = "SELECT COUNT(*) FROM libros l WHERE {$whereSql}";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / $elementosPagina));
    $pagina = max(1, min($pagina, $totalPaginas));
    $offset = ($pagina - 1) * $elementosPagina;

    // Consulta de libros con número de ejemplares disponibles y total de copias
    $sql = "
        SELECT l.*,
               COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) AS disponibles_count,
               COUNT(CASE WHEN e.estado = 'reservado' THEN 1 END) AS reservadas_count,
               COUNT(CASE WHEN e.estado = 'retirado' THEN 1 END) AS retiradas_count,
               COUNT(CASE WHEN e.estado = 'baja' THEN 1 END) AS bajas_count,
               COUNT(e.id) AS total_ejemplares
        FROM libros l
        LEFT JOIN ejemplares e ON e.libro_id = l.id
        WHERE {$whereSql}
        GROUP BY l.id
    ";

    // Si se solicitó filtrar solo libros con ejemplares actualmente disponibles
    if (!empty($filtros['solo_disponibles'])) {
        $sql .= " HAVING disponibles_count > 0 ";
    }

    $sql .= " ORDER BY l.id ASC LIMIT " . (int) $elementosPagina . " OFFSET " . (int) $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $libros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'libros' => $libros,
        'total' => $total,
        'pagina' => $pagina,
        'totalPaginas' => $totalPaginas,
    ];
}

/**
 * Obtiene la información completa de un libro por su identificador primario,
 * incluyendo el conteo de ejemplares disponibles y totales.
 *
 * @param PDO $pdo   Instancia de base de datos
 * @param int $id    Identificador único del libro
 * @return array|null Datos del libro o null si no existe
 */
function catalogo_obtener_libro(PDO $pdo, int $id): ?array {
    catalogo_asegurar_columnas_libro($pdo);
    $stmt = $pdo->prepare("
        SELECT l.*,
               COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) AS disponibles_count,
               COUNT(e.id) AS total_ejemplares
        FROM libros l
        LEFT JOIN ejemplares e ON e.libro_id = l.id
        WHERE l.id = ?
        GROUP BY l.id
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);
    return $libro ?: null;
}

/**
 * Busca un libro en la base de datos local por su código ISBN-13 normalizado.
 *
 * @param PDO    $pdo   Instancia de base de datos
 * @param string $isbn  Código ISBN limpio
 * @return array|null Datos del libro encontrado o null
 */
function catalogo_obtener_por_isbn(PDO $pdo, string $isbn): ?array {
    $isbnLimpio = preg_replace('/[^0-9X]/i', '', $isbn);
    if ($isbnLimpio === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT l.*,
               COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) AS disponibles_count,
               COUNT(e.id) AS total_ejemplares
        FROM libros l
        LEFT JOIN ejemplares e ON e.libro_id = l.id
        WHERE l.isbn13 = ?
        GROUP BY l.id
        LIMIT 1
    ");
    $stmt->execute([$isbnLimpio]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Obtiene la lista de ejemplares físicos asociados a un libro (o todos si libroId es null).
 *
 * @param PDO      $pdo      Instancia de base de datos
 * @param int|null $libroId  Identificador del libro o null para listar todos
 * @return array Lista de ejemplares con título de libro y nombre de depositante
 */
function catalogo_listar_ejemplares(PDO $pdo, ?int $libroId = null): array {
    $sql = "
        SELECT e.*,
               l.titulo AS libro_titulo,
               l.autor AS libro_autor,
               u.nombre AS depositante_nombre,
               tx.id AS reserva_id,
               tx.codigo AS reserva_codigo,
               tx.fecha_limite AS reserva_fecha_limite,
               ru.id AS lector_id,
               ru.nombre AS lector_nombre,
               ru.email AS lector_email
        FROM ejemplares e
        JOIN libros l ON l.id = e.libro_id
        LEFT JOIN usuarios u ON u.id = e.depositante_id
        LEFT JOIN transacciones tx ON tx.ejemplar_id = e.id AND tx.tipo = 'reserva' AND tx.estado = 'activa'
        LEFT JOIN usuarios ru ON ru.id = tx.usuario_id
    ";

    $params = [];
    if ($libroId !== null && $libroId > 0) {
        $sql .= " WHERE e.libro_id = ? ";
        $params[] = $libroId;
    }

    $sql .= " ORDER BY e.id DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los detalles de un ejemplar físico específico por su ID.
 *
 * @param PDO $pdo Instancia de base de datos
 * @param int $id  ID del ejemplar físico
 * @return array|null Fila del ejemplar o null si no existe
 */
function catalogo_obtener_ejemplar(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT e.*,
               l.titulo AS libro_titulo,
               l.autor AS libro_autor
        FROM ejemplares e
        JOIN libros l ON l.id = e.libro_id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Helper para resolver la portada de un libro priorizando la copia local en uploads/covers/.
 * Si portada_url es remota Y existe copia local en uploads/covers/, sirve la local; si no, sirve la remota.
 *
 * @param array $libro Datos del libro (portada_url, isbn13, titulo, autor, genero)
 * @return string URL de la portada (local o remota) o fallback
 */
function portada_src(array $libro): string {
    $portadaUrl = trim((string) ($libro['portada_url'] ?? ''));

    // Si ya es una ruta relativa local en /uploads/ o /assets/
    if ($portadaUrl !== '' && (str_starts_with($portadaUrl, '/uploads/') || str_starts_with($portadaUrl, '/assets/'))) {
        return $portadaUrl;
    }

    // Si es remota (http:// o https://)
    if (str_starts_with($portadaUrl, 'http://') || str_starts_with($portadaUrl, 'https://')) {
        $isbnLimpio = !empty($libro['isbn13']) ? preg_replace('/[^0-9X]/i', '', (string) $libro['isbn13']) : '';
        if ($isbnLimpio !== '') {
            $archivoIsbn = dirname(__DIR__, 2) . '/uploads/covers/' . $isbnLimpio . '.jpg';
            if (is_file($archivoIsbn) && filesize($archivoIsbn) > 0) {
                return '/uploads/covers/' . $isbnLimpio . '.jpg';
            }
        }

        $archivoHash = dirname(__DIR__, 2) . '/uploads/covers/' . md5($portadaUrl) . '.jpg';
        if (is_file($archivoHash) && filesize($archivoHash) > 0) {
            return '/uploads/covers/' . md5($portadaUrl) . '.jpg';
        }

        // Si no existe copia local, servir la remota
        return $portadaUrl;
    }

    if ($portadaUrl !== '') {
        return $portadaUrl;
    }

    // Fallback dinámico si no hay URL configurada
    $tit = urlencode((string) ($libro['titulo'] ?? 'Libro'));
    $aut = urlencode((string) ($libro['autor'] ?? ''));
    $gen = urlencode((string) ($libro['genero'] ?? ''));
    return '/portada-svg?titulo=' . $tit . '&autor=' . $aut . '&genero=' . $gen;
}

/**
 * Intenta descargar una portada remota a uploads/covers/{isbn o hash}.jpg server-side.
 *
 * Requisitos:
 * - cURL con timeout 5s.
 * - Solo si Content-Type es image/* y tamaño <= 2MB.
 * - Si ya existe, no re-descarga y devuelve la ruta local existente.
 * - Si falla, devuelve null (degradación elegante).
 *
 * @param string      $url  URL remota de la portada
 * @param string|null $isbn Código ISBN-13/10 opcional para el nombre del fichero
 * @return string|null Ruta web local ('/uploads/covers/...') o null si no se pudo descargar
 */
function portada_descargar_cache(string $url, ?string $isbn = null): ?string {
    $url = trim($url);
    if (str_starts_with($url, '/uploads/covers/') || str_starts_with($url, '/uploads/portadas/')) {
        $rutaLocalExistente = dirname(__DIR__, 2) . $url;
        if (is_file($rutaLocalExistente) && filesize($rutaLocalExistente) > 300) {
            return $url;
        }
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
        return null;
    }

    $isbnLimpio = $isbn !== null ? preg_replace('/[^0-9X]/i', '', $isbn) : '';
    $nombreArchivo = ($isbnLimpio !== '' ? $isbnLimpio : md5($url)) . '.jpg';

    $dirCovers = dirname(__DIR__, 2) . '/uploads/covers';
    if (!is_dir($dirCovers)) {
        @mkdir($dirCovers, 0777, true);
    }

    $rutaFisica = $dirCovers . '/' . $nombreArchivo;
    $rutaWeb = '/uploads/covers/' . $nombreArchivo;

    // No re-descargar si ya existe y tiene contenido válido (> 300 bytes)
    if (is_file($rutaFisica) && filesize($rutaFisica) > 300) {
        return $rutaWeb;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT      => 'BookSwap/4.3 (CoverCache/1.0)',
    ]);

    $data = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    @curl_close($ch);

    if ($data === false || $httpCode !== 200 || !empty($error)) {
        return null;
    }

    // Validar Content-Type sea image/*
    if (!str_starts_with(strtolower($contentType), 'image/')) {
        return null;
    }

    // Validar tamaño > 300 bytes y <= 2MB (2 * 1024 * 1024 = 2097152 bytes)
    $tamano = strlen($data);
    if ($tamano < 300 || $tamano > 2097152) {
        return null;
    }

    $escrito = @file_put_contents($rutaFisica, $data);
    if ($escrito === false) {
        return null;
    }

    return $rutaWeb;
}

/**
 * Crea o actualiza un libro en la base de datos, validando datos y registrando auditoría.
 *
 * @param PDO      $pdo       Instancia de base de datos
 * @param array    $datos     Campos del libro (titulo, autor, isbn13, etc.)
 * @param int|null $usuarioId ID del usuario administrador o personal que realiza la acción
 * @return int ID del libro insertado o actualizado
 * @throws InvalidArgumentException Si faltan campos obligatorios
 */
function catalogo_guardar_libro(PDO $pdo, array $datos, ?int $usuarioId = null): int {
    $titulo = trim((string) ($datos['titulo'] ?? ''));
    $autor = trim((string) ($datos['autor'] ?? ''));

    if ($titulo === '') {
        throw new InvalidArgumentException('El título del libro es obligatorio.');
    }
    if ($autor === '') {
        throw new InvalidArgumentException('El autor del libro es obligatorio.');
    }

    $isbn13 = trim((string) ($datos['isbn13'] ?? ''));
    $isbn13 = preg_replace('/[^0-9X]/i', '', $isbn13) ?: null;

    $editorial = trim((string) ($datos['editorial'] ?? '')) ?: null;
    $anio = !empty($datos['anio']) ? (int) $datos['anio'] : null;
    $genero = trim((string) ($datos['genero'] ?? '')) ?: null;
    $idioma = trim((string) ($datos['idioma'] ?? 'es')) ?: 'es';
    $portadaUrl = trim((string) ($datos['portada_url'] ?? '')) ?: null;
    $observaciones = trim((string) ($datos['observaciones'] ?? '')) ?: null;

    // Si la portada es remota, intentar descargarla server-side a uploads/covers/
    if ($portadaUrl !== null && (str_starts_with($portadaUrl, 'http://') || str_starts_with($portadaUrl, 'https://'))) {
        $portadaLocal = portada_descargar_cache($portadaUrl, $isbn13);
        if ($portadaLocal !== null) {
            $portadaUrl = $portadaLocal;
        }
    }

    $id = !empty($datos['id']) ? (int) $datos['id'] : 0;

    if ($id > 0) {
        // Actualización de libro existente
        $stmt = $pdo->prepare("
            UPDATE libros
            SET isbn13 = ?,
                titulo = ?,
                autor = ?,
                editorial = ?,
                anio = ?,
                genero = ?,
                idioma = ?,
                portada_url = ?,
                observaciones = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $isbn13, $titulo, $autor, $editorial, $anio, $genero, $idioma, $portadaUrl, $observaciones, $id
        ]);

        log_accion($usuarioId, 'libro_editar', 'libros', $id, [
            'titulo' => $titulo,
            'autor' => $autor,
            'isbn13' => $isbn13,
            'modificado_por' => $usuarioId,
        ]);

        if ($genero !== null && trim($genero) !== '') {
            catalogo_persistir_genero($genero);
        }

        return $id;
    }

    // Inserción de nuevo libro
    $via = trim((string) ($datos['via'] ?? ($isbn13 ? 'isbn' : 'manual')));

    $stmt = $pdo->prepare("
        INSERT INTO libros (isbn13, titulo, autor, editorial, anio, genero, idioma, portada_url, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $isbn13, $titulo, $autor, $editorial, $anio, $genero, $idioma, $portadaUrl, $observaciones
    ]);
    $nuevoId = (int) $pdo->lastInsertId();

    log_accion($usuarioId, 'libro_crear', 'libros', $nuevoId, [
        'via' => $via,
        'titulo' => $titulo,
        'autor' => $autor,
        'isbn13' => $isbn13,
        'creado_por' => $usuarioId,
    ]);

    if ($genero !== null && trim($genero) !== '') {
        catalogo_persistir_genero($genero);
    }

    return $nuevoId;
}

/**
 * Crea o actualiza un ejemplar físico asociado a un libro en el catálogo.
 *
 * @param PDO      $pdo       Instancia de base de datos
 * @param array    $datos     Campos del ejemplar (libro_id, estado, ubicacion, condicion, depositante_id)
 * @param int|null $usuarioId ID del operador que registra o modifica el ejemplar
 * @return int ID del ejemplar físico guardado
 * @throws InvalidArgumentException Si falta el ID del libro
 */
function catalogo_guardar_ejemplar(PDO $pdo, array $datos, ?int $usuarioId = null): int {
    $id = !empty($datos['id']) ? (int) $datos['id'] : 0;
    $libroId = !empty($datos['libro_id']) ? (int) $datos['libro_id'] : 0;

    if ($id === 0 && $libroId === 0) {
        throw new InvalidArgumentException('Debe asociarse el ejemplar a un libro válido.');
    }

    $estado = in_array($datos['estado'] ?? '', ['disponible', 'reservado', 'retirado', 'baja'], true)
        ? $datos['estado']
        : 'disponible';

    $condicion = in_array($datos['condicion'] ?? '', ['nuevo', 'como_nuevo', 'bueno', 'aceptable', 'deteriorado'], true)
        ? $datos['condicion']
        : 'bueno';

    $ubicacion = trim((string) ($datos['ubicacion'] ?? 'A-01-01')) ?: 'A-01-01';
    $depositanteId = !empty($datos['depositante_id']) ? (int) $datos['depositante_id'] : null;

    if ($id > 0) {
        // Actualización de ejemplar existente
        $stmt = $pdo->prepare("
            UPDATE ejemplares
            SET estado = ?,
                ubicacion = ?,
                condicion = ?,
                depositante_id = COALESCE(?, depositante_id)
            WHERE id = ?
        ");
        $stmt->execute([$estado, $ubicacion, $condicion, $depositanteId, $id]);

        log_accion($usuarioId, 'ejemplar_editar', 'ejemplares', $id, [
            'estado' => $estado,
            'condicion' => $condicion,
            'ubicacion' => $ubicacion,
            'modificado_por' => $usuarioId,
        ]);

        if ($estado === 'disponible' && function_exists('wishlist_notificar_disponibilidad')) {
            $libId = (int) ($libroId > 0 ? $libroId : db_val('SELECT libro_id FROM ejemplares WHERE id = ?', [$id]));
            if ($libId > 0) {
                wishlist_notificar_disponibilidad($pdo, $libId);
            }
        }

        return $id;
    }

    // Inserción de nueva copia física
    $stmt = $pdo->prepare("
        INSERT INTO ejemplares (libro_id, estado, ubicacion, condicion, depositante_id, fecha_ingreso)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$libroId, $estado, $ubicacion, $condicion, $depositanteId]);
    $nuevoId = (int) $pdo->lastInsertId();

    log_accion($usuarioId, 'ejemplar_crear', 'ejemplares', $nuevoId, [
        'libro_id' => $libroId,
        'estado' => $estado,
        'condicion' => $condicion,
        'ubicacion' => $ubicacion,
        'creado_por' => $usuarioId,
    ]);

    if ($estado === 'disponible' && function_exists('wishlist_notificar_disponibilidad')) {
        wishlist_notificar_disponibilidad($pdo, $libroId);
    }

    return $nuevoId;
}

/**
 * Ofrece resultados rápidos de autocompletado para el buscador en tiempo real.
 *
 * @param PDO    $pdo     Instancia de base de datos
 * @param string $termino Término parcial tecleado por el usuario
 * @param int    $limite  Máximo de sugerencias devueltas
 * @return array Lista de libros sugeridos
 */
function catalogo_autocompletar(PDO $pdo, string $termino, int $limite = 6): array {
    $termino = trim($termino);
    if (mb_strlen($termino) < 2) {
        return [];
    }

    $param = '%' . $termino . '%';
    $stmt = $pdo->prepare("
        SELECT id, titulo, autor, genero, isbn13, portada_url
        FROM libros
        WHERE titulo LIKE ? OR autor LIKE ? OR isbn13 LIKE ?
        ORDER BY id DESC
        LIMIT " . (int) $limite
    );
    $stmt->execute([$param, $param, $param]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Registra un término de búsqueda para fines de trazabilidad o métricas.
 * Si no existe tabla de búsquedas en el esquema canónico, no produce excepción.
 *
 * @param PDO      $pdo       Instancia de base de datos
 * @param string   $termino   Término buscado
 * @param int|null $usuarioId ID del usuario si está identificado
 */
function catalogo_registrar_busqueda(PDO $pdo, string $termino, ?int $usuarioId = null): void {
    // En v4.1 canónico no hay tabla de términos de búsqueda; mantenemos la función sin error
}

/**
 * Elimina de forma integral y atómica un libro del catálogo y todas sus dependencias.
 *
 * Flujo:
 * 1. Verifica existencia del libro.
 * 2. Obtiene todos sus ejemplares.
 * 3. Si hay reservas activas en sus ejemplares:
 *    - Reembolsa los tokens bloqueados al usuario vía ledger_registrar_movimiento ('liberacion_reserva').
 *    - Notifica al usuario de la cancelación por retirada del título del catálogo.
 *    - Marca las transacciones como 'cancelada'.
 * 4. Desvincula transacciones de movimientos_tokens (SET transaccion_id = NULL).
 * 5. Elimina todas las transacciones vinculadas a los ejemplares del libro.
 * 6. Elimina los ejemplares físicos.
 * 7. Elimina las entradas de wishlist vinculadas al libro.
 * 8. Si la portada es local y no está en uso por otro libro, elimina el fichero en disco.
 * 9. Elimina el registro del libro en la tabla `libros`.
 * 10. Registra auditoría de la eliminación.
 *
 * @param PDO      $pdo       Instancia de base de datos
 * @param int      $libroId   ID del libro a eliminar
 * @param int|null $usuarioId ID del operador (Admin / Personal)
 * @return array{ok: bool, libro: array, ejemplares_eliminados: int, reservas_canceladas: int}
 * @throws Exception Si el libro no existe o ocurre un error en BD
 */
function catalogo_eliminar_libro(PDO $pdo, int $libroId, ?int $usuarioId = null): array {
    $stmtLibro = $pdo->prepare('SELECT * FROM libros WHERE id = ?');
    $stmtLibro->execute([$libroId]);
    $libro = $stmtLibro->fetch(PDO::FETCH_ASSOC);

    if (!$libro) {
        throw new Exception('El libro que intentas eliminar no existe.');
    }

    $inTx = $pdo->inTransaction();
    if (!$inTx) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Obtener todos los IDs de ejemplares físicos
        $stmtEj = $pdo->prepare('SELECT id, estado FROM ejemplares WHERE libro_id = ?');
        $stmtEj->execute([$libroId]);
        $ejemplares = $stmtEj->fetchAll(PDO::FETCH_ASSOC);
        $ejemplarIds = array_column($ejemplares, 'id');

        $reservasCanceladas = 0;

        if (!empty($ejemplarIds)) {
            $placeholders = implode(',', array_fill(0, count($ejemplarIds), '?'));

            // 2. Gestionar reservas activas sobre estos ejemplares
            $sqlRes = "
                SELECT t.id, t.usuario_id, t.tokens, t.codigo
                FROM transacciones t
                WHERE t.ejemplar_id IN ($placeholders)
                  AND t.tipo = 'reserva'
                  AND t.estado = 'activa'
            ";
            $stmtRes = $pdo->prepare($sqlRes);
            $stmtRes->execute($ejemplarIds);
            $reservasActivas = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

            require_once __DIR__ . '/ledger.php';

            foreach ($reservasActivas as $res) {
                $costeDevolver = (int) ($res['tokens'] > 0 ? $res['tokens'] : 1);
                $uId = (int) $res['usuario_id'];
                $cod = $res['codigo'] ?? '';

                // Reembolsar tokens bloqueados al usuario
                ledger_registrar_movimiento(
                    $pdo,
                    $uId,
                    $costeDevolver,
                    'liberacion_reserva',
                    null,
                    "Reembolso por retirada del libro «{$libro['titulo']}» del catálogo ({$cod})"
                );

                // Notificar al lector
                $msg = "Tu reserva del libro «{$libro['titulo']}» ({$cod}) ha sido cancelada porque el título ha sido retirado del catálogo. Se han devuelto {$costeDevolver} 🪙 a tu cuenta.";
                $stmtNotif = $pdo->prepare('
                    INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
                    VALUES (?, ?, 0, \'/mi-historial\', NOW())
                ');
                $stmtNotif->execute([$uId, $msg]);

                $reservasCanceladas++;
            }

            // 3. Desvincular transacciones de movimientos_tokens para preservar integridad del ledger
            $sqlUnlinkMov = "
                UPDATE movimientos_tokens
                SET transaccion_id = NULL
                WHERE transaccion_id IN (
                    SELECT id FROM transacciones WHERE ejemplar_id IN ($placeholders)
                )
            ";
            $stmtUnlink = $pdo->prepare($sqlUnlinkMov);
            $stmtUnlink->execute($ejemplarIds);

            // 4. Eliminar todas las transacciones asociadas a estos ejemplares
            $sqlDelTx = "DELETE FROM transacciones WHERE ejemplar_id IN ($placeholders)";
            $stmtDelTx = $pdo->prepare($sqlDelTx);
            $stmtDelTx->execute($ejemplarIds);

            // 5. Eliminar todos los ejemplares físicos
            $sqlDelEj = "DELETE FROM ejemplares WHERE libro_id = ?";
            $stmtDelEj = $pdo->prepare($sqlDelEj);
            $stmtDelEj->execute([$libroId]);
        }

        // 6. Eliminar registros de wishlist asociados al libro
        $stmtDelWish = $pdo->prepare('DELETE FROM wishlist WHERE libro_id = ?');
        $stmtDelWish->execute([$libroId]);

        // 7. Eliminar archivo físico de portada local si no lo usa ningún otro libro
        $portadaUrl = (string) ($libro['portada_url'] ?? '');
        if ($portadaUrl !== '' && (str_starts_with($portadaUrl, '/uploads/covers/') || str_starts_with($portadaUrl, '/uploads/portadas/'))) {
            $stmtOtro = $pdo->prepare('SELECT COUNT(*) FROM libros WHERE portada_url = ? AND id != ?');
            $stmtOtro->execute([$portadaUrl, $libroId]);
            $enUsoPorOtro = (int) $stmtOtro->fetchColumn();
            if ($enUsoPorOtro === 0) {
                $rutaFisica = dirname(__DIR__, 2) . $portadaUrl;
                if (is_file($rutaFisica)) {
                    @unlink($rutaFisica);
                }
            }
        }

        // 8. Eliminar el libro de la tabla `libros`
        $stmtDelLibro = $pdo->prepare('DELETE FROM libros WHERE id = ?');
        $stmtDelLibro->execute([$libroId]);

        // 9. Registrar auditoría
        auditoria_registrar($pdo, 'libro.eliminar', 'libros', $libroId, [
            'titulo' => $libro['titulo'],
            'autor' => $libro['autor'],
            'isbn13' => $libro['isbn13'],
            'copias_eliminadas' => count($ejemplarIds),
            'reservas_canceladas' => $reservasCanceladas,
        ], $usuarioId);

        log_accion($usuarioId, 'libro_eliminar', 'libros', $libroId, [
            'titulo' => $libro['titulo'],
            'autor' => $libro['autor'],
        ]);

        if (!$inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'libro' => $libro,
            'ejemplares_eliminados' => count($ejemplarIds),
            'reservas_canceladas' => $reservasCanceladas,
        ];
    } catch (Throwable $e) {
        if (!$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Da de baja un libro completo del catálogo junto con todos sus ejemplares físicos asociados.
 * Si existen reservas activas pendientes sobre sus copias, las cancela y devuelve los tokens bloqueados a los lectores.
 *
 * @param PDO      $pdo       Instancia activa de base de datos
 * @param int      $libroId   ID del libro a dar de baja
 * @param string   $motivo    Motivo obligatorio de la baja
 * @param int|null $usuarioId Operador que ejecuta la acción
 * @return array{ok: bool, libro: array, copias_baja: int, reservas_canceladas: int}
 */
function catalogo_dar_de_baja_libro(PDO $pdo, int $libroId, string $motivo, ?int $usuarioId = null): array {
    $motivoLimpio = trim($motivo);
    if ($motivoLimpio === '') {
        throw new InvalidArgumentException('El motivo de la baja del libro es obligatorio.');
    }

    $inTx = $pdo->inTransaction();
    if (!$inTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmtLibro = $pdo->prepare('SELECT * FROM libros WHERE id = ? FOR UPDATE');
        $stmtLibro->execute([$libroId]);
        $libro = $stmtLibro->fetch(PDO::FETCH_ASSOC);
        if (!$libro) {
            throw new Exception('Libro no encontrado en el catálogo.');
        }

        if (($libro['estado'] ?? 'activo') === 'baja') {
            throw new Exception('El libro ya está dado de baja del catálogo.');
        }

        // 1. Cancelar reservas activas sobre ejemplares de este libro y reembolsar tokens bloqueados
        $stmtRes = $pdo->prepare("
            SELECT t.id, t.usuario_id, t.ejemplar_id, t.tokens, t.codigo
            FROM transacciones t
            JOIN ejemplares e ON e.id = t.ejemplar_id
            WHERE e.libro_id = ? AND t.tipo = 'reserva' AND t.estado = 'activa'
            FOR UPDATE
        ");
        $stmtRes->execute([$libroId]);
        $reservasActivas = $stmtRes->fetchAll(PDO::FETCH_ASSOC);
        $reservasCanceladas = 0;

        foreach ($reservasActivas as $res) {
            $txId = (int) $res['id'];
            $lectorId = (int) $res['usuario_id'];
            $tokensDevueltos = (int) $res['tokens'];

            // Cancelar transacción de reserva
            $stmtCan = $pdo->prepare("UPDATE transacciones SET estado = 'cancelada' WHERE id = ?");
            $stmtCan->execute([$txId]);

            // Reembolsar tokens bloqueados en el ledger
            if ($tokensDevueltos > 0) {
                ledger_registrar_movimiento(
                    $pdo,
                    $lectorId,
                    $tokensDevueltos,
                    'liberacion_reserva',
                    null,
                    'Liberación por baja de libro del catálogo: ' . $libro['titulo']
                );
            }

            // Notificar al lector
            $stmtNotif = $pdo->prepare("
                INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha)
                VALUES (?, ?, 0, '/catalogo', NOW())
            ");
            $stmtNotif->execute([
                $lectorId,
                "Tu reserva ({$res['codigo']}) del libro \"{$libro['titulo']}\" ha sido cancelada porque el libro se ha dado de baja del catálogo. Se han reembolsado tus tokens.",
            ]);

            $reservasCanceladas++;
        }

        // 2. Dar de baja todos los ejemplares físicos asociados que no estuvieran ya en estado retirado o baja
        $stmtEj = $pdo->prepare("
            UPDATE ejemplares 
            SET estado = 'baja' 
            WHERE libro_id = ? AND estado != 'retirado'
        ");
        $stmtEj->execute([$libroId]);
        $copiasBaja = $stmtEj->rowCount();

        // 3. Marcar el libro como 'baja' con motivo y fecha
        $stmtUpLibro = $pdo->prepare("
            UPDATE libros 
            SET estado = 'baja', motivo_baja = ?, fecha_baja = NOW() 
            WHERE id = ?
        ");
        $stmtUpLibro->execute([$motivoLimpio, $libroId]);

        // 4. Registro de auditoría
        auditoria_registrar($pdo, 'libro.baja', 'libros', $libroId, [
            'titulo' => $libro['titulo'],
            'motivo' => $motivoLimpio,
            'copias_baja' => $copiasBaja,
            'reservas_canceladas' => $reservasCanceladas,
        ], $usuarioId);

        log_accion($usuarioId, 'libro_baja', 'libros', $libroId, [
            'titulo' => $libro['titulo'],
            'motivo' => $motivoLimpio,
            'copias_baja' => $copiasBaja,
            'reservas_canceladas' => $reservasCanceladas,
        ]);

        if (!$inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'libro' => $libro,
            'copias_baja' => $copiasBaja,
            'reservas_canceladas' => $reservasCanceladas,
        ];
    } catch (Throwable $e) {
        if (!$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Reactiva un libro previamente dado de baja en el catálogo.
 */
function catalogo_reactivar_libro(PDO $pdo, int $libroId, ?int $usuarioId = null): array {
    $stmtLibro = $pdo->prepare('SELECT * FROM libros WHERE id = ?');
    $stmtLibro->execute([$libroId]);
    $libro = $stmtLibro->fetch(PDO::FETCH_ASSOC);
    if (!$libro) {
        throw new Exception('Libro no encontrado en el catálogo.');
    }

    $stmtUp = $pdo->prepare("UPDATE libros SET estado = 'activo', motivo_baja = NULL, fecha_baja = NULL WHERE id = ?");
    $stmtUp->execute([$libroId]);

    auditoria_registrar($pdo, 'libro.reactivar', 'libros', $libroId, [
        'titulo' => $libro['titulo'],
    ], $usuarioId);

    log_accion($usuarioId, 'libro_reactivar', 'libros', $libroId, [
        'titulo' => $libro['titulo'],
    ]);

    return [
        'ok' => true,
        'libro' => $libro,
    ];
}

/**
 * Guarda un grupo o género en el archivo persistente database/generos_persistentes.json.
 */
function catalogo_persistir_genero(string $genero): void {
    $limpio = trim($genero);
    if ($limpio === '') return;

    $archivo = dirname(__DIR__, 2) . '/database/generos_persistentes.json';
    $lista = [];
    if (file_exists($archivo)) {
        $json = @file_get_contents($archivo);
        $data = @json_decode((string) $json, true);
        if (is_array($data)) {
            $lista = array_values(array_filter(array_map('trim', $data)));
        }
    }

    if (!in_array($limpio, $lista, true)) {
        array_unshift($lista, $limpio);
        @file_put_contents($archivo, json_encode(array_values(array_unique($lista)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Devuelve la lista unificada y priorizada de grupos y géneros literarios.
 * Garantiza que los cursos/grupos escolares y los valores registrados previamente
 * aparezcan siempre en las primeras posiciones.
 */
function catalogo_obtener_lista_generos(PDO $pdo): array {
    $gruposEscolares = [
        '1.º ESO',
        '2.º ESO',
        '3.º ESO',
        '4.º ESO',
        '1.º Bachillerato',
        '2.º Bachillerato',
        'FP Básica',
        'Ciclos Formativos',
        'Primaria',
        'Infantil',
    ];

    $archivo = dirname(__DIR__, 2) . '/database/generos_persistentes.json';
    $persistidos = [];
    if (file_exists($archivo)) {
        $json = @file_get_contents($archivo);
        $data = @json_decode((string) $json, true);
        if (is_array($data)) {
            $persistidos = array_values(array_filter(array_map('trim', $data)));
        }
    }

    $registradosBD = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT genero FROM libros WHERE genero IS NOT NULL AND TRIM(genero) != '' ORDER BY genero ASC");
        $registradosBD = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable) {}

    $generosGenerales = [
        'Novela',
        'Clásicos',
        'Fantasía',
        'Ciencia ficción',
        'Distopía',
        'Novela negra',
        'Juvenil',
        'Poesía',
        'Ensayo',
        'Historia',
        'Biografía',
        'Teatro',
        'Aventuras',
        'Cómic / Manga',
        'Lecturas graduadas',
        'Divulgación',
        'Otros',
    ];

    // Grupos escolares y valores persistidos primero
    $primeros = array_values(array_unique(array_filter(array_merge(
        $gruposEscolares,
        $persistidos,
        $registradosBD
    ))));

    // Ordenar los primeros para que los escolares y numéricos (1.º ESO, 2.º ESO...) queden de primeros
    usort($primeros, function($a, $b) {
        $esA_Escolar = preg_match('/^\d+\.?[º°ª]|\bESO\b|\bBachillerato\b|\bFP\b|\bPrimaria\b/i', $a);
        $esB_Escolar = preg_match('/^\d+\.?[º°ª]|\bESO\b|\bBachillerato\b|\bFP\b|\bPrimaria\b/i', $b);
        if ($esA_Escolar && !$esB_Escolar) return -1;
        if (!$esA_Escolar && $esB_Escolar) return 1;
        return strnatcasecmp($a, $b);
    });

    $resto = array_values(array_diff($generosGenerales, $primeros));

    return array_values(array_unique(array_merge($primeros, $resto)));
}
