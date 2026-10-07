<?php
/**
 * BookSwap · csv.php — Helper de importación y exportación CSV (v4.1).
 *
 * Funcionalidades:
 * - Detección automática de delimitadores (, o ;) y soporte UTF-8 con o sin BOM.
 * - Importador de Catálogo con informe detallado por líneas (creadas, duplicadas, erróneas).
 * - Detección de duplicados por ISBN-13 y por combinación de título + autor.
 * - Modo transaccional "detener ante el primer error" (todo-o-nada).
 * - Importador de números de socio hacia el pool preautorizado.
 * - Generador de plantillas descargables de ejemplo.
 */
declare(strict_types=1);

require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/auditoria.php';

/**
 * Detecta el delimitador de campos (, o ;) examinando la cabecera del contenido CSV.
 *
 * @param string $primeraLinea Primera línea del archivo CSV
 * @return string Delimitador predominante (';' o ',')
 */
function csv_detectar_delimitador(string $primeraLinea): string {
    $comas = substr_count($primeraLinea, ',');
    $puntosYComas = substr_count($primeraLinea, ';');
    return $puntosYComas >= $comas ? ';' : ',';
}

/**
 * Elimina la marca de orden de bytes (BOM UTF-8) del principio de una cadena si está presente.
 *
 * @param string $texto Contenido de texto
 * @return string Texto sin BOM
 */
function csv_limpiar_bom(string $texto): string {
    if (str_starts_with($texto, "\xEF\xBB\xBF")) {
        return substr($texto, 3);
    }
    return $texto;
}

/**
 * Normaliza el nombre de una columna de cabecera CSV eliminando acentos, caracteres especiales y espacios.
 *
 * @param string $col Nombre crudo de la columna
 * @return string Nombre limpio y normalizado
 */
function csv_normalizar_columna(string $col): string {
    $c = mb_strtolower(trim($col), 'UTF-8');
    $c = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
        $c
    );
    $c = preg_replace('/[\s\-\.\/]+/', '_', $c);
    return trim($c, '_');
}

/**
 * Busca el índice de una columna entre varias variantes posibles de nombres.
 *
 * @param array $cabeceraNormalizada Lista de nombres de columnas normalizadas
 * @param array $aliasPosibles Lista de nombres posibles para la columna
 * @return int|false Índice encontrado o false si no existe
 */
function csv_buscar_indice_columna(array $cabeceraNormalizada, array $aliasPosibles) {
    foreach ($aliasPosibles as $alias) {
        $idx = array_search($alias, $cabeceraNormalizada, true);
        if ($idx !== false) {
            return $idx;
        }
    }
    return false;
}

/**
 * Importa libros al catálogo desde una cadena de texto en formato CSV.
 * Soporta formato canónico y formato escolar/red (Curso, Título, Autor, Editorial, Comentarios, Cantidad en la red).
 *
 * @param PDO      $pdo              Instancia activa de base de datos
 * @param string   $contenidoCsv     Texto íntegro del archivo CSV subido
 * @param bool     $detenerAnteError Si es true, cualquier fila errónea revierte toda la importación
 * @param int|null $usuarioId        ID del usuario administrador o personal responsable
 * @return array Informe detallado de la importación
 */
function csv_importar_catalogo(
    PDO $pdo,
    string $contenidoCsv,
    bool $detenerAnteError = false,
    ?int $usuarioId = null
): array {
    $contenidoLimpio = csv_limpiar_bom($contenidoCsv);
    $lineas = preg_split('/\r\n|\r|\n/', trim($contenidoLimpio));

    if (empty($lineas) || trim($lineas[0]) === '') {
        return [
            'ok' => false,
            'mensaje' => 'El archivo CSV está vacío.',
            'total_leidas' => 0,
            'importadas_count' => 0,
            'copias_creadas' => 0,
            'duplicados_count' => 0,
            'errores_count' => 0,
            'importados' => [],
            'duplicados' => [],
            'errores' => [],
            'detenido' => false,
        ];
    }

    $delimitador = csv_detectar_delimitador($lineas[0]);
    $cabeceraRaw = str_getcsv($lineas[0], $delimitador);
    $cabecera = array_map('csv_normalizar_columna', $cabeceraRaw);

    // Mapeo flexible de columnas requeridas y opcionales
    $idxTitulo      = csv_buscar_indice_columna($cabecera, ['titulo', 'title', 'nombre_libro', 'nombre', 'libro', 'titul']);
    $idxAutor       = csv_buscar_indice_columna($cabecera, ['autor', 'author', 'autores', 'escritor', 'authors']);
    $idxEditorial   = csv_buscar_indice_columna($cabecera, ['editorial', 'editoriales', 'publisher', 'edicion', 'ed']);
    $idxCurso       = csv_buscar_indice_columna($cabecera, ['curso', 'cursos', 'nivel', 'grado', 'etapa']);
    $idxComentarios = csv_buscar_indice_columna($cabecera, ['comentarios', 'comentario', 'observaciones', 'observacion', 'notas', 'nota', 'sinopsis', 'descripcion', 'desc']);
    $idxCantidad    = csv_buscar_indice_columna($cabecera, ['cantidad_en_la_red', 'cantidad_en_red', 'cantidad_red', 'cantidad', 'copias', 'ejemplares', 'stock', 'red', 'unidades', 'cant']);
    $idxIsbn        = csv_buscar_indice_columna($cabecera, ['isbn', 'isbn13', 'isbn_13', 'codigo', 'ean']);
    $idxAnio        = csv_buscar_indice_columna($cabecera, ['anio', 'ano', 'year', 'fecha']);
    $idxGenero      = csv_buscar_indice_columna($cabecera, ['genero', 'genre', 'categoria', 'tematica', 'materia', 'asignatura']);
    $idxIdioma      = csv_buscar_indice_columna($cabecera, ['idioma', 'lengua', 'lang', 'language']);
    $idxPortada     = csv_buscar_indice_columna($cabecera, ['portada_url', 'portada', 'cover', 'imagen', 'foto']);

    $esFormatoEscolar = ($idxCurso !== false || $idxCantidad !== false || $idxComentarios !== false);

    if ($idxTitulo === false || ($idxAutor === false && !$esFormatoEscolar)) {
        return [
            'ok' => false,
            'mensaje' => 'La cabecera del CSV debe contener obligatoriamente la columna "Título" (o "titulo").',
            'total_leidas' => 0,
            'importadas_count' => 0,
            'copias_creadas' => 0,
            'duplicados_count' => 0,
            'errores_count' => 0,
            'importados' => [],
            'duplicados' => [],
            'errores' => [['linea' => 1, 'motivo' => 'Cabecera no válida: falta columna de título o autor']],
            'detenido' => false,
        ];
    }

    $importados = [];
    $duplicados = [];
    $errores = [];
    $filasTotal = 0;
    $totalCopiasCreadas = 0;

    // Iniciar transacción de base de datos
    $pdo->beginTransaction();

    try {
        $stmtCheckIsbn = $pdo->prepare('SELECT id, titulo FROM libros WHERE isbn13 = ? LIMIT 1');
        $stmtCheckTituloAutor = $pdo->prepare('SELECT id, titulo FROM libros WHERE LOWER(titulo) = LOWER(?) AND LOWER(autor) = LOWER(?) LIMIT 1');
        $stmtInsertLibro = $pdo->prepare('
            INSERT INTO libros (isbn13, titulo, autor, editorial, anio, genero, idioma, portada_url, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtInsertEjemplar = $pdo->prepare("
            INSERT INTO ejemplares (libro_id, estado, condicion, ubicacion)
            VALUES (?, 'disponible', 'bueno', 'Biblioteca')
        ");

        for ($i = 1; $i < count($lineas); $i++) {
            $numLinea = $i + 1; // 1-indexed para el informe humano
            $lineaTexto = trim($lineas[$i]);
            if ($lineaTexto === '') {
                continue; // Saltar líneas vacías
            }

            $filasTotal++;
            $fila = str_getcsv($lineaTexto, $delimitador);

            $titulo = trim((string) ($fila[$idxTitulo] ?? ''));
            $autorRaw = $idxAutor !== false ? trim((string) ($fila[$idxAutor] ?? '')) : '';

            // 1. Validación de campos obligatorios
            if ($titulo === '') {
                $errores[] = ['linea' => $numLinea, 'motivo' => 'El título del libro no puede estar vacío.'];
                if ($detenerAnteError) throw new Exception('Detención ante el primer error activada.');
                continue;
            }

            // Gestión de autor
            $editorial = $idxEditorial !== false ? (trim((string) ($fila[$idxEditorial] ?? '')) ?: null) : null;
            if ($autorRaw === '') {
                if ($esFormatoEscolar) {
                    // En formato escolar/red se permite autor no especificado o implícito en editorial
                    $autor = $editorial ? 'Desconocido (ed. ' . $editorial . ')' : 'Anónimo / Desconocido';
                } else {
                    $errores[] = ['linea' => $numLinea, 'motivo' => 'El autor del libro no puede estar vacío.'];
                    if ($detenerAnteError) throw new Exception('Detención ante el primer error activada.');
                    continue;
                }
            } else {
                $autor = $autorRaw;
            }

            // Normalización de ISBN (si existe en el CSV)
            $isbnRaw = $idxIsbn !== false ? trim((string) ($fila[$idxIsbn] ?? '')) : '';
            $isbn = preg_replace('/[^0-9X]/i', '', $isbnRaw) ?: null;

            // Validación de año
            $anioRaw = $idxAnio !== false ? trim((string) ($fila[$idxAnio] ?? '')) : '';
            $anio = null;
            if ($anioRaw !== '') {
                if (ctype_digit($anioRaw) && (int) $anioRaw >= 1400 && (int) $anioRaw <= (date('Y') + 2)) {
                    $anio = (int) $anioRaw;
                } else {
                    $errores[] = ['linea' => $numLinea, 'motivo' => 'Año de publicación inválido («' . $anioRaw . '»).'];
                    if ($detenerAnteError) throw new Exception('Detención ante el primer error activada.');
                    continue;
                }
            }

            // Curso, comentarios y género
            $curso = $idxCurso !== false ? trim((string) ($fila[$idxCurso] ?? '')) : '';
            $genero = $idxGenero !== false ? (trim((string) ($fila[$idxGenero] ?? '')) ?: null) : null;
            if ($genero === null && $curso !== '') {
                $genero = mb_substr($curso, 0, 80);
            }

            $comentarios = $idxComentarios !== false ? trim((string) ($fila[$idxComentarios] ?? '')) : '';
            $observaciones = null;
            if ($comentarios !== '' && $curso !== '') {
                $observaciones = mb_substr("[{$curso}] {$comentarios}", 0, 500);
            } elseif ($comentarios !== '') {
                $observaciones = mb_substr($comentarios, 0, 500);
            } elseif ($curso !== '' && $genero !== $curso) {
                $observaciones = mb_substr("Curso: {$curso}", 0, 500);
            }

            $idioma = $idxIdioma !== false ? (trim((string) ($fila[$idxIdioma] ?? 'es')) ?: 'es') : 'es';
            $portadaUrl = $idxPortada !== false ? (trim((string) ($fila[$idxPortada] ?? '')) ?: null) : null;

            // Cantidad en la red (ejemplares físicos a crear)
            $cantidadCopias = 0;
            if ($idxCantidad !== false && isset($fila[$idxCantidad])) {
                $cantTexto = trim((string) $fila[$idxCantidad]);
                if ($cantTexto !== '') {
                    $cantidadCopias = max(0, (int) preg_replace('/[^0-9]/', '', $cantTexto));
                }
            }

            // 2. Detección de duplicados en base de datos o en el mismo lote
            $esDuplicado = false;
            $motivoDup = '';

            if ($isbn !== null) {
                $stmtCheckIsbn->execute([$isbn]);
                $dupIsbn = $stmtCheckIsbn->fetch(PDO::FETCH_ASSOC);
                if ($dupIsbn) {
                    $esDuplicado = true;
                    $motivoDup = 'ISBN «' . $isbn . '» ya registrado (Libro #' . $dupIsbn['id'] . ' «' . $dupIsbn['titulo'] . '»)';
                }
            }

            if (!$esDuplicado) {
                $stmtCheckTituloAutor->execute([$titulo, $autor]);
                $dupTA = $stmtCheckTituloAutor->fetch(PDO::FETCH_ASSOC);
                if ($dupTA) {
                    $esDuplicado = true;
                    $motivoDup = 'Combinación de título y autor ya existente (Libro #' . $dupTA['id'] . ')';
                }
            }

            if ($esDuplicado) {
                $duplicados[] = [
                    'linea'  => $numLinea,
                    'titulo' => $titulo,
                    'autor'  => $autor,
                    'isbn'   => $isbn,
                    'motivo' => $motivoDup,
                ];
                continue; // Omitir duplicado y continuar
            }

            // Si la portada es remota, intentar descargarla server-side a uploads/covers/
            if ($portadaUrl !== null && (str_starts_with($portadaUrl, 'http://') || str_starts_with($portadaUrl, 'https://'))) {
                if (function_exists('portada_descargar_cache')) {
                    $pLocal = portada_descargar_cache($portadaUrl, $isbn);
                    if ($pLocal !== null) {
                        $portadaUrl = $pLocal;
                    }
                }
            }

            // 3. Inserción del libro en catálogo
            $stmtInsertLibro->execute([
                $isbn, $titulo, $autor, $editorial, $anio, $genero, $idioma, $portadaUrl, $observaciones
            ]);
            $nuevoId = (int) $pdo->lastInsertId();

            // 4. Inserción de ejemplares físicos si se especificó cantidad en la red
            if ($cantidadCopias > 0) {
                for ($k = 0; $k < $cantidadCopias; $k++) {
                    $stmtInsertEjemplar->execute([$nuevoId]);
                    $totalCopiasCreadas++;
                }
            }

            $importados[] = [
                'id'      => $nuevoId,
                'linea'   => $numLinea,
                'titulo'  => $titulo,
                'autor'   => $autor,
                'isbn'    => $isbn,
                'curso'   => $curso,
                'copias'  => $cantidadCopias,
            ];
        }

        $pdo->commit();

        if (function_exists('wishlist_notificar_disponibilidad')) {
            foreach ($importados as $imp) {
                if (($imp['copias'] ?? 0) > 0 && !empty($imp['id'])) {
                    wishlist_notificar_disponibilidad($pdo, (int) $imp['id']);
                }
            }
        }

        log_accion($usuarioId, 'csv_catalogo', 'libros', null, [
            'total_leidas'    => $filasTotal,
            'importadas'      => count($importados),
            'copias_creadas'  => $totalCopiasCreadas,
            'duplicadas'      => count($duplicados),
            'errores'         => count($errores),
        ]);

        return [
            'ok'               => true,
            'mensaje'          => 'Importación completada con éxito.',
            'total_leidas'     => $filasTotal,
            'importadas_count' => count($importados),
            'copias_creadas'   => $totalCopiasCreadas,
            'duplicados_count' => count($duplicados),
            'errores_count'    => count($errores),
            'importados'       => $importados,
            'duplicados'       => $duplicados,
            'errores'          => $errores,
            'detenido'         => false,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [
            'ok'               => false,
            'mensaje'          => 'Importación cancelada: ' . $e->getMessage(),
            'total_leidas'     => $filasTotal,
            'importadas_count' => 0,
            'copias_creadas'   => 0,
            'duplicados_count' => count($duplicados),
            'errores_count'    => count($errores),
            'importados'       => [],
            'duplicados'       => $duplicados,
            'errores'          => $errores,
            'detenido'         => true,
        ];
    }
}


/**
 * Genera el contenido de una plantilla CSV descargable para importar libros al catálogo.
 * Usa el formato escolar / red con Curso, Título, Autor, Editorial, Comentarios y Cantidad en la red.
 *
 * @return string Contenido CSV con cabeceras y filas de demostración
 */
function csv_generar_plantilla_catalogo(): string {
    return "Curso,Título,Autor,Editorial,Comentarios,Cantidad en la red
"
         . "1 ESO,Dr Doolittle,Hugh Lofting,Burlington Books,Activity Reader,6
"
         . "2 ESO,The Canterville Ghost,Oscar Wilde,Burlington Books,Activity Reader,2
"
         . "1º Bachillerato,Jane Eyre,Charlotte Brontë,Burlington Books,Lectura recomendada,3
";
}

/**
 * Genera el contenido de una plantilla CSV estándar (con ISBN y año).
 *
 * @return string Contenido CSV estándar
 */
function csv_generar_plantilla_catalogo_estandar(): string {
    return "isbn;titulo;autor;editorial;anio;genero;idioma
"
         . "9788437604197;Don Quijote de la Mancha;Miguel de Cervantes;Cátedra;1605;Clásicos;es
"
         . "9780451524935;1984;George Orwell;Signet;1949;Distopía;es
";
}
