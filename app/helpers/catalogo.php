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
 * Obtiene el listado paginado de libros del catálogo aplicando filtros opcionales.
 *
 * @param PDO   $pdo              Instancia activa de base de datos
 * @param array $filtros          Filtros de búsqueda (q, genero, solo_disponibles)
 * @param int   $pagina           Número de página actual (1-indexed)
 * @param int   $elementosPagina  Cantidad de libros por página
 * @return array{libros: array, total: int, pagina: int, totalPaginas: int}
 */
function catalogo_listar_libros(PDO $pdo, array $filtros = [], int $pagina = 1, int $elementosPagina = 12): array {
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
