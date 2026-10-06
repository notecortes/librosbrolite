<?php
/**
 * BookSwap · catalogo_api.php — Helper de consulta bibliográfica por ISBN y generador de portadas SVG (v4.1).
 *
 * Funcionalidades:
 * - Consulta server-side mediante cURL sin dependencias externas:
 *   1) Base de datos local primero (si ya existe, no reconsulta).
 *   2) Google Books API (sin API key, timeout 8s).
 *   3) Fallback a Open Library API (timeout 8s).
 * - Manejo robusto sin excepciones ante cortes de red o fallos externos.
 * - Generador dinámico de portadas SVG vectoriales elegantes para libros sin portada.
 */
declare(strict_types=1);

require_once __DIR__ . '/funciones.php';

/**
 * Normaliza un código ISBN eliminando guiones, espacios y caracteres no válidos.
 *
 * @param string $isbn Código ISBN en cualquier formato
 * @return string Código limpio (dígitos y posible 'X' al final)
 */
function catalogo_normalizar_isbn(string $isbn): string {
    $limpio = strtoupper((string) preg_replace('/[^0-9X]/i', '', trim($isbn)));
    if (strlen($limpio) === 14) {
        if (str_starts_with($limpio, '978') || str_starts_with($limpio, '979')) {
            $limpio = substr($limpio, 0, 13);
        } else {
            $limpio = substr($limpio, 1, 13);
        }
    } elseif (strlen($limpio) > 14 && (str_starts_with($limpio, '978') || str_starts_with($limpio, '979'))) {
        $limpio = substr($limpio, 0, 13);
    }
    return $limpio;
}

/**
 * Calcula el dígito de control correcto para un ISBN-13 (12 primeros dígitos).
 *
 * @param string $primeros12 Primeros 12 dígitos del ISBN
 * @return string Dígito de control calculado (0-9)
 */
function catalogo_calcular_checksum_isbn13(string $primeros12): string {
    if (strlen($primeros12) < 12) {
        return '';
    }
    $suma = 0;
    for ($i = 0; $i < 12; $i++) {
        $digito = (int) $primeros12[$i];
        $suma += ($i % 2 === 0) ? $digito : ($digito * 3);
    }
    $resto = $suma % 10;
    return (string) (($resto === 0) ? 0 : (10 - $resto));
}

/**
 * Realiza una petición HTTP GET mediante cURL con tiempo de espera seguro (8s) y sin lanzar excepciones.
 * Forzando HTTP/1.1 e IPv4 para evitar desconexiones SSL con CDNs como Open Library.
 *
 * @param string $url URL a consultar
 * @param int    $timeout Segundos máximos de espera
 * @return string|null Contenido de la respuesta o null si ocurrió un error
 */
function catalogo_curl_get(string $url, int $timeout = 8): ?string {
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT      => 'BookSwap/4.1 (Biblioteca Ciudadana; PHP cURL)',
    ]);

    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    @curl_close($ch);

    if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
        return null;
    }

    return (string) $respuesta;
}

/**
 * Normaliza códigos de idioma a formato ISO-639-1 (2 caracteres: 'es', 'en', 'ca', etc.)
 *
 * @param string|null $lang Código o nombre del idioma
 * @return string Código ISO normalizado de 2 caracteres
 */
function catalogo_normalizar_codigo_idioma(?string $lang): string {
    if (!$lang) {
        return 'es';
    }
    $lang = strtolower(trim($lang));
    $lang = str_replace('/languages/', '', $lang);

    $map = [
        'spa' => 'es', 'es' => 'es', 'spanish' => 'es', 'castellano' => 'es', 'español' => 'es',
        'val' => 'val', 'valenciano' => 'val', 'valencià' => 'val', 'valencian' => 'val',
        'cat' => 'ca', 'ca' => 'ca', 'catalan' => 'ca', 'català' => 'ca',
        'eng' => 'en', 'en' => 'en', 'english' => 'en', 'inglés' => 'en', 'ingles' => 'en',
        'fra' => 'fr', 'fre' => 'fr', 'fr' => 'fr', 'french' => 'fr', 'francés' => 'fr',
        'deu' => 'de', 'ger' => 'de', 'de' => 'de', 'german' => 'de', 'alemán' => 'de',
        'ita' => 'it', 'it' => 'it', 'italian' => 'it', 'italiano' => 'it',
        'por' => 'pt', 'pt' => 'pt', 'portuguese' => 'pt', 'portugués' => 'pt',
        'gal' => 'gl', 'gl' => 'gl', 'galician' => 'gl', 'gallego' => 'gl',
        'eus' => 'eu', 'baq' => 'eu', 'eu' => 'eu', 'basque' => 'eu', 'euskera' => 'eu',
    ];
    return $map[$lang] ?? (strlen($lang) === 2 || $lang === 'val' ? $lang : 'es');
}

/**
 * Detecta el idioma probable de un libro según el prefijo/grupo del ISBN.
 *
 * @param string $isbn Código ISBN
 * @return string Código de idioma de 2 letras ('es', 'en', 'fr', etc.)
 */
function catalogo_detectar_idioma_isbn(string $isbn): string {
    $limpio = preg_replace('/[^0-9X]/i', '', $isbn);
    if (strlen($limpio) === 13 && (str_starts_with($limpio, '978') || str_starts_with($limpio, '979'))) {
        $resto = substr($limpio, 3);
    } else {
        $resto = $limpio;
    }

    // Reglas de grupo de registro ISBN hispanohablante
    if (
        str_starts_with($resto, '84') ||
        str_starts_with($resto, '607') ||
        str_starts_with($resto, '950') ||
        str_starts_with($resto, '956') ||
        str_starts_with($resto, '958') ||
        str_starts_with($resto, '959') ||
        str_starts_with($resto, '968') ||
        str_starts_with($resto, '970') ||
        str_starts_with($resto, '980') ||
        str_starts_with($resto, '987') ||
        str_starts_with($resto, '9972') ||
        str_starts_with($resto, '9974')
    ) {
        return 'es';
    }

    // Grupo 0 o 1: Área de lengua inglesa
    if (str_starts_with($resto, '0') || str_starts_with($resto, '1')) {
        return 'en';
    }

    // Grupo 2: Área de lengua francesa
    if (str_starts_with($resto, '2')) {
        return 'fr';
    }

    // Grupo 3: Área de lengua alemana
    if (str_starts_with($resto, '3')) {
        return 'de';
    }

    // Grupo 88: Italia
    if (str_starts_with($resto, '88')) {
        return 'it';
    }

    // Grupo 85, 972: Portugal / Brasil
    if (str_starts_with($resto, '85') || str_starts_with($resto, '972')) {
        return 'pt';
    }

    return 'es';
}

/**
 * Busca y resuelve la mejor portada para un libro,
 * priorizando en primer lugar portadas en el idioma del libro (ej. español)
 * y, si no la encuentra, utilizando la portada encontrada en inglés (fallback).
 *
 * @param string      $isbn                   Código ISBN normalizado
 * @param string|null $titulo                 Título del libro
 * @param string|null $autor                  Autor del libro
 * @param string|null $idioma                 Idioma preferido del libro
 * @param string|null $workKey                Clave de obra Open Library (/works/OL...W)
 * @param int|null    $coverIdEdicion         ID de portada directa de la edición Open Library
 * @param string|null $portadaGoogleCandidata URL de portada obtenida de Google Books
 * @param string|null $idiomaGoogleCandidata  Idioma del volumen de Google Books
 * @return string|null Ruta local o URL remota de la portada
 */
function catalogo_resolver_portada_por_idioma(
    string $isbn,
    ?string $titulo = null,
    ?string $autor = null,
    ?string $idioma = null,
    ?string $workKey = null,
    ?int $coverIdEdicion = null,
    ?string $portadaGoogleCandidata = null,
    ?string $idiomaGoogleCandidata = null
): ?string {
    $isbnLimpio = catalogo_normalizar_isbn($isbn);
    $idiomaLibro = catalogo_normalizar_codigo_idioma($idioma ?: catalogo_detectar_idioma_isbn($isbnLimpio));
    $idiomaGoogle = $idiomaGoogleCandidata ? catalogo_normalizar_codigo_idioma($idiomaGoogleCandidata) : null;

    $candidatosIngles = [];
    $candidatosOtros = [];

    // Helper interno para probar, validar (> 300 bytes) y descargar localmente
    $probarCandidato = function(?string $url) use ($isbnLimpio): ?string {
        if (!$url) return null;
        $url = trim($url);
        if ($url === '') return null;

        // Si ya está descargada localmente en /uploads/covers/ o /uploads/portadas/
        if (str_starts_with($url, '/uploads/covers/') || str_starts_with($url, '/uploads/portadas/')) {
            $f = dirname(__DIR__, 2) . $url;
            if (is_file($f) && filesize($f) > 300) {
                return $url;
            }
        }

        // Descargar y cachear con validación de imagen real > 300 bytes
        if (function_exists('portada_descargar_cache')) {
            $local = portada_descargar_cache($url, $isbnLimpio);
            if ($local !== null) {
                return $local;
            }
        }

        return null;
    };

    // =========================================================================
    // FASE 1: PROBAR CANDIDATOS DIRECTOS EN EL IDIOMA DEL LIBRO
    // =========================================================================

    // 1.1 Si Open Library proveyó un coverId para esta edición exacta:
    if (!empty($coverIdEdicion) && $coverIdEdicion > 0) {
        $urlCoverEd = 'https://covers.openlibrary.org/b/id/' . (int) $coverIdEdicion . '-L.jpg';
        $res = $probarCandidato($urlCoverEd);
        if ($res !== null) {
            return $res;
        }
    }

    // 1.2 Si Google Books proveyó portada para el volumen en el idioma del libro:
    if (!empty($portadaGoogleCandidata)) {
        if ($idiomaGoogle === $idiomaLibro || catalogo_detectar_idioma_isbn($isbnLimpio) === $idiomaLibro) {
            $res = $probarCandidato($portadaGoogleCandidata);
            if ($res !== null) {
                return $res;
            }
        } elseif ($idiomaGoogle === 'en') {
            $candidatosIngles[] = $portadaGoogleCandidata;
        } else {
            $candidatosOtros[] = $portadaGoogleCandidata;
        }
    }

    // 1.3 Intentar portada directa por ISBN en Open Library si el ISBN es del idioma del libro:
    if ($isbnLimpio !== '' && catalogo_detectar_idioma_isbn($isbnLimpio) === $idiomaLibro) {
        $urlOpenLibIsbn = 'https://covers.openlibrary.org/b/isbn/' . urlencode($isbnLimpio) . '-L.jpg?default=false';
        $res = $probarCandidato($urlOpenLibIsbn);
        if ($res !== null) {
            return $res;
        }
    }

    // 1.4 Google Books ViewAPI para este ISBN (rápido y sin rate limits 429)
    if ($isbnLimpio !== '') {
        $urlGBView = 'https://books.google.com/books?bibkeys=ISBN:' . urlencode($isbnLimpio) . '&jscmd=viewapi&format=json';
        $jsonGBView = catalogo_curl_get($urlGBView, 3);
        if ($jsonGBView && preg_match('/"thumbnail_url"\s*:\s*"([^"]+)"/', $jsonGBView, $m)) {
            $thumb = str_replace(['\u0026', '&amp;'], '&', $m[1]);
            $thumb = preg_replace('/zoom=\d+/', 'zoom=1', $thumb);
            if (catalogo_detectar_idioma_isbn($isbnLimpio) === $idiomaLibro) {
                $res = $probarCandidato($thumb);
                if ($res !== null) {
                    return $res;
                }
            } else {
                $candidatosIngles[] = $thumb;
            }
        }
    }

    // 1.5 Buscar en Open Library: ediciones de la obra en el idioma del libro
    if ($workKey) {
        $urlEditions = 'https://openlibrary.org' . $workKey . '/editions.json?limit=15';
        $jsonEditions = catalogo_curl_get($urlEditions, 4);
        if ($jsonEditions) {
            $dataEditions = json_decode($jsonEditions, true);
            foreach ($dataEditions['entries'] ?? [] as $entry) {
                if (empty($entry['covers'])) continue;
                $entryLangs = [];
                foreach ($entry['languages'] ?? [] as $l) {
                    $entryLangs[] = catalogo_normalizar_codigo_idioma($l['key'] ?? '');
                }
                $coverId = (int) $entry['covers'][0];
                if ($coverId <= 0) continue;

                if (in_array($idiomaLibro, $entryLangs, true)) {
                    $urlCover = 'https://covers.openlibrary.org/b/id/' . $coverId . '-L.jpg';
                    $res = $probarCandidato($urlCover);
                    if ($res !== null) {
                        return $res;
                    }
                } elseif (in_array('en', $entryLangs, true)) {
                    $candidatosIngles[] = 'https://covers.openlibrary.org/b/id/' . $coverId . '-L.jpg';
                }
            }
        }
    }

    // =========================================================================
    // FASE 2: SI NO SE ENCONTRÓ EN EL IDIOMA DEL LIBRO, USAR PORTADA EN INGLÉS
    // =========================================================================
    if ($idiomaLibro !== 'en') {
        // Probar candidatos en inglés ya reunidos previamente
        foreach ($candidatosIngles as $cEn) {
            $res = $probarCandidato($cEn);
            if ($res !== null) {
                return $res;
            }
        }

        // Portada por defecto de la obra en Open Library (suele ser la edición original)
        if ($workKey) {
            $urlWork = 'https://openlibrary.org' . $workKey . '.json';
            $jsonWork = catalogo_curl_get($urlWork, 4);
            if ($jsonWork) {
                $dataWork = json_decode($jsonWork, true);
                if (!empty($dataWork['covers'][0])) {
                    $coverId = (int) $dataWork['covers'][0];
                    if ($coverId > 0) {
                        $urlCover = 'https://covers.openlibrary.org/b/id/' . $coverId . '-L.jpg';
                        $res = $probarCandidato($urlCover);
                        if ($res !== null) {
                            return $res;
                        }
                    }
                }
            }
        }

        // Google Books búsqueda por título en inglés (fallback ligero con timeout 3s)
        $googleKey = $_ENV['GOOGLE_BOOKS_API_KEY'] ?? getenv('GOOGLE_BOOKS_API_KEY') ?: '';
        if ($titulo) {
            $q = 'intitle:' . urlencode(mb_substr($titulo, 0, 80));
            if ($autor) {
                $q .= '+inauthor:' . urlencode(mb_substr($autor, 0, 50));
            }
            $urlGBEn = 'https://www.googleapis.com/books/v1/volumes?q=' . $q . '&langRestrict=en&maxResults=2' . ($googleKey ? '&key=' . urlencode($googleKey) : '');
            $jsonGBEn = catalogo_curl_get($urlGBEn, 3);
            if ($jsonGBEn) {
                $dataGBEn = json_decode($jsonGBEn, true);
                foreach ($dataGBEn['items'] ?? [] as $item) {
                    $info = $item['volumeInfo'] ?? [];
                    $thumb = $info['imageLinks']['thumbnail'] ?? ($info['imageLinks']['smallThumbnail'] ?? null);
                    if ($thumb) {
                        $thumbUrl = str_replace('http://', 'https://', (string) $thumb);
                        $res = $probarCandidato($thumbUrl);
                        if ($res !== null) {
                            return $res;
                        }
                    }
                }
            }
        }
    }

    // =========================================================================
    // FASE 3: CUALQUIER PORTADA GENERAL DISPONIBLE
    // =========================================================================
    foreach ($candidatosOtros as $cOtro) {
        $res = $probarCandidato($cOtro);
        if ($res !== null) {
            return $res;
        }
    }

    if ($isbnLimpio !== '') {
        $urlFallback = 'https://covers.openlibrary.org/b/isbn/' . urlencode($isbnLimpio) . '-L.jpg?default=false';
        $res = $probarCandidato($urlFallback);
        if ($res !== null) {
            return $res;
        }
    }

    // Si estamos offline o no se pudo descargar ningún binario, devolver URL remota tentativa
    if (!empty($coverIdEdicion) && $coverIdEdicion > 0) {
        return 'https://covers.openlibrary.org/b/id/' . (int) $coverIdEdicion . '-L.jpg';
    }
    if (!empty($candidatosIngles[0])) {
        return $candidatosIngles[0];
    }
    if ($isbnLimpio !== '') {
        return 'https://covers.openlibrary.org/b/isbn/' . urlencode($isbnLimpio) . '-L.jpg';
    }

    return null;
}

/**
 * Busca datos de un libro por su ISBN consultando primero la base local,
 * luego Open Library (edición directa, obra, búsqueda) y Google Books.
 * Tolera códigos con dígitos adicionales de escáner y corrige errores de checksum en ISBN-13.
 *
 * @param PDO    $pdo  Instancia de base de datos
 * @param string $isbn Código ISBN a buscar
 * @return array{ok: bool, origen?: string, libro?: array, mensaje?: string}
 */
function catalogo_buscar_isbn(PDO $pdo, string $isbn): array {
    $isbnLimpio = catalogo_normalizar_isbn($isbn);

    if (strlen($isbnLimpio) < 9) {
        return [
            'ok' => false,
            'mensaje' => 'El formato del ISBN debe contener 10 o 13 caracteres numéricos.',
        ];
    }

    $formatearRespuesta = function(array $res, string $isbnTarget): array {
        if (!empty($res['libro']['portada_url']) && (str_starts_with($res['libro']['portada_url'], 'http://') || str_starts_with($res['libro']['portada_url'], 'https://'))) {
            if (function_exists('portada_descargar_cache')) {
                $pLocal = portada_descargar_cache($res['libro']['portada_url'], $isbnTarget);
                if ($pLocal !== null) {
                    $res['libro']['portada_url'] = $pLocal;
                }
            }
        }
        return $res;
    };

    // Construir lista de candidatos de ISBN:
    $candidatos = [$isbnLimpio];

    // Si es de 10 dígitos, añadir la versión ISBN-13 equivalente
    if (strlen($isbnLimpio) === 10) {
        $pref13 = '978' . substr($isbnLimpio, 0, 9);
        $chk10 = catalogo_calcular_checksum_isbn13($pref13);
        if ($chk10 !== '') {
            $candidatos[] = $pref13 . $chk10;
        }
    }

    $candidatos = array_values(array_unique(array_filter($candidatos)));

    // 1. Verificar primero si alguno de los candidatos ya está catalogado en la base de datos local
    foreach ($candidatos as $cand) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM libros WHERE isbn13 = ? OR REPLACE(isbn13, '-', '') = ? LIMIT 1");
            $stmt->execute([$cand, $cand]);
            $libroLocal = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($libroLocal) {
                return [
                    'ok' => true,
                    'origen' => 'local',
                    'mensaje' => 'Libro ya registrado en el catálogo local.',
                    'libro' => [
                        'id'            => (int) $libroLocal['id'],
                        'isbn13'        => $libroLocal['isbn13'],
                        'titulo'        => $libroLocal['titulo'],
                        'autor'         => $libroLocal['autor'],
                        'editorial'     => $libroLocal['editorial'],
                        'anio'          => !empty($libroLocal['anio']) ? (int) $libroLocal['anio'] : null,
                        'genero'        => $libroLocal['genero'],
                        'idioma'        => $libroLocal['idioma'] ?? 'es',
                        'portada_url'   => $libroLocal['portada_url'],
                        'observaciones' => $libroLocal['observaciones'],
                    ],
                ];
            }
        } catch (Throwable $e) {}
    }

    // 2. Consultar fuentes externas para los candidatos
    foreach ($candidatos as $cand) {
        // 2.1 Endpoint de edición directa de Open Library
        $urlOpenLibEdicion = 'https://openlibrary.org/isbn/' . urlencode($cand) . '.json';
        $jsonOpenLibEdicion = catalogo_curl_get($urlOpenLibEdicion, 6);

        if ($jsonOpenLibEdicion !== null) {
            $edition = json_decode($jsonOpenLibEdicion, true);
            if (!empty($edition['title'])) {
                $titulo = (string) $edition['title'];
                $editorial = !empty($edition['publishers'][0]) ? (string) $edition['publishers'][0] : null;

                $anio = null;
                if (!empty($edition['publish_date']) && preg_match('/\b(19\d\d|20\d\d)\b/', (string) $edition['publish_date'], $m)) {
                    $anio = (int) $m[1];
                }

                $coverId = !empty($edition['covers'][0]) ? (int) $edition['covers'][0] : null;
                $workKey = !empty($edition['works'][0]['key']) ? (string) $edition['works'][0]['key'] : null;
                $idioma = !empty($edition['languages'][0]['key'])
                    ? catalogo_normalizar_codigo_idioma((string) $edition['languages'][0]['key'])
                    : catalogo_detectar_idioma_isbn($cand);

                // Resolución del nombre del autor
                $autor = '';
                if (!empty($edition['authors'])) {
                    foreach ($edition['authors'] as $aRef) {
                        $key = $aRef['key'] ?? '';
                        if ($key) {
                            $aJson = catalogo_curl_get('https://openlibrary.org' . $key . '.json', 4);
                            if ($aJson) {
                                $aData = json_decode($aJson, true);
                                if (!empty($aData['name'])) {
                                    $autor = (string) $aData['name'];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Género o temáticas si existen
                $genero = null;
                if (!empty($edition['subjects'][0])) {
                    $genero = is_string($edition['subjects'][0]) ? $edition['subjects'][0] : ($edition['subjects'][0]['name'] ?? null);
                }

                // Si falta autor o género, consultar de forma ultrarrápida Open Library Search API (1 petición única)
                if ($autor === '' || $genero === null) {
                    $urlSearchFast = 'https://openlibrary.org/search.json?isbn=' . urlencode($cand) . '&fields=author_name,subject';
                    $jsonSearchFast = catalogo_curl_get($urlSearchFast, 5);
                    if ($jsonSearchFast) {
                        $dSearch = json_decode($jsonSearchFast, true);
                        if ($autor === '' && !empty($dSearch['docs'][0]['author_name'][0])) {
                            $autor = (string) $dSearch['docs'][0]['author_name'][0];
                        }
                        if ($genero === null && !empty($dSearch['docs'][0]['subject'][0])) {
                            $genero = (string) $dSearch['docs'][0]['subject'][0];
                        }
                    }
                }

                // Si aún falta y hay obra (works), consultar la obra
                if (($autor === '' || $genero === null) && $workKey) {
                    $wJson = catalogo_curl_get('https://openlibrary.org' . $workKey . '.json', 4);
                    if ($wJson) {
                        $wData = json_decode($wJson, true);
                        if ($autor === '' && !empty($wData['authors'])) {
                            foreach ($wData['authors'] as $wRef) {
                                $aKey = $wRef['author']['key'] ?? ($wRef['key'] ?? '');
                                if ($aKey) {
                                    $aJson = catalogo_curl_get('https://openlibrary.org' . $aKey . '.json', 4);
                                    if ($aJson) {
                                        $aData = json_decode($aJson, true);
                                        if (!empty($aData['name'])) {
                                            $autor = (string) $aData['name'];
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        if ($genero === null && !empty($wData['subjects'][0])) {
                            $genero = is_string($wData['subjects'][0]) ? $wData['subjects'][0] : ($wData['subjects'][0]['name'] ?? null);
                        }
                    }
                }

                // Si el autor aún no se pudo resolver, consultar Open Library Search API
                if ($autor === '') {
                    $urlSearchAutor = 'https://openlibrary.org/search.json?isbn=' . urlencode($cand) . '&fields=author_name,subject';
                    $jsonSearchAutor = catalogo_curl_get($urlSearchAutor, 4);
                    if ($jsonSearchAutor) {
                        $dSearch = json_decode($jsonSearchAutor, true);
                        if (!empty($dSearch['docs'][0]['author_name'][0])) {
                            $autor = (string) $dSearch['docs'][0]['author_name'][0];
                        }
                        if ($genero === null && !empty($dSearch['docs'][0]['subject'][0])) {
                            $genero = (string) $dSearch['docs'][0]['subject'][0];
                        }
                    }
                }

                // Resolver portada priorizando el idioma del libro y recurriendo a inglés si no se encuentra
                $portada = catalogo_resolver_portada_por_idioma($cand, $titulo, $autor, $idioma, $workKey, $coverId);

                return $formatearRespuesta([
                    'ok' => true,
                    'origen' => 'open_library',
                    'mensaje' => 'Datos obtenidos desde Open Library (Edición directa).',
                    'libro' => [
                        'isbn13'        => $cand,
                        'titulo'        => $titulo,
                        'autor'         => $autor !== '' ? $autor : 'Autor desconocido',
                        'editorial'     => $editorial,
                        'anio'          => $anio,
                        'genero'        => $genero,
                        'idioma'        => $idioma,
                        'portada_url'   => $portada,
                        'observaciones' => !empty($edition['notes']['value']) ? mb_substr((string) $edition['notes']['value'], 0, 490) : (is_string($edition['notes'] ?? null) ? mb_substr((string) $edition['notes'], 0, 490) : null),
                    ],
                ], $cand);
            }
        }

        // 2.2 Google Books API (Volumes)
        $googleKey = $_ENV['GOOGLE_BOOKS_API_KEY'] ?? getenv('GOOGLE_BOOKS_API_KEY') ?: '';
        $urlGoogle = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . urlencode($cand) . ($googleKey ? '&key=' . urlencode($googleKey) : '');
        $jsonGoogle = catalogo_curl_get($urlGoogle, 4);

        if ($jsonGoogle !== null) {
            $datosGoogle = json_decode($jsonGoogle, true);
            if (!empty($datosGoogle['items'][0]['volumeInfo'])) {
                $info = $datosGoogle['items'][0]['volumeInfo'];

                $autores = !empty($info['authors']) ? implode(', ', $info['authors']) : '';
                $titulo = (string) ($info['title'] ?? '');
                $subtitulo = !empty($info['subtitle']) ? ' — ' . $info['subtitle'] : '';
                $tituloCompleto = trim($titulo . $subtitulo);

                $anio = null;
                if (!empty($info['publishedDate']) && preg_match('/\b(19\d\d|20\d\d)\b/', $info['publishedDate'], $m)) {
                    $anio = (int) $m[1];
                }

                $genero = !empty($info['categories'][0]) ? (string) $info['categories'][0] : null;
                $editorial = !empty($info['publisher']) ? (string) $info['publisher'] : null;
                $idioma = !empty($info['language']) ? catalogo_normalizar_codigo_idioma((string) $info['language']) : catalogo_detectar_idioma_isbn($cand);

                $portadaGoogle = null;
                if (!empty($info['imageLinks']['thumbnail'])) {
                    $portadaGoogle = str_replace('http://', 'https://', (string) $info['imageLinks']['thumbnail']);
                } elseif (!empty($info['imageLinks']['smallThumbnail'])) {
                    $portadaGoogle = str_replace('http://', 'https://', (string) $info['imageLinks']['smallThumbnail']);
                }

                $portada = catalogo_resolver_portada_por_idioma($cand, $tituloCompleto, $autores, $idioma, null, null, $portadaGoogle, $info['language'] ?? null);

                return $formatearRespuesta([
                    'ok' => true,
                    'origen' => 'google_books',
                    'mensaje' => 'Datos obtenidos desde Google Books.',
                    'libro' => [
                        'isbn13'        => $cand,
                        'titulo'        => $tituloCompleto !== '' ? $tituloCompleto : 'Sin título',
                        'autor'         => $autores !== '' ? $autores : 'Autor desconocido',
                        'editorial'     => $editorial,
                        'anio'          => $anio,
                        'genero'        => $genero,
                        'idioma'        => $idioma,
                        'portada_url'   => $portada,
                        'observaciones' => !empty($info['description']) ? mb_substr((string) $info['description'], 0, 490) : null,
                    ],
                ], $cand);
            }
        }

        // 2.3 Open Library Search API optimizado por campos
        $urlSearch = 'https://openlibrary.org/search.json?isbn=' . urlencode($cand) . '&fields=title,author_name,publisher,first_publish_year,cover_i,subject,language';
        $jsonSearch = catalogo_curl_get($urlSearch, 5);
        if ($jsonSearch !== null) {
            $datosSearch = json_decode($jsonSearch, true);
            if (!empty($datosSearch['docs'][0])) {
                $doc = $datosSearch['docs'][0];
                $titulo = (string) ($doc['title'] ?? '');
                $autores = !empty($doc['author_name']) ? implode(', ', $doc['author_name']) : 'Autor desconocido';
                $editorial = !empty($doc['publisher'][0]) ? (string) $doc['publisher'][0] : null;
                $anio = !empty($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null;
                $coverId = !empty($doc['cover_i']) ? (int) $doc['cover_i'] : null;
                $idioma = !empty($doc['language'][0]) ? catalogo_normalizar_codigo_idioma((string) $doc['language'][0]) : catalogo_detectar_idioma_isbn($cand);
                $portada = catalogo_resolver_portada_por_idioma($cand, $titulo, $autores, $idioma, null, $coverId);

                return $formatearRespuesta([
                    'ok' => true,
                    'origen' => 'open_library',
                    'mensaje' => 'Datos obtenidos desde Open Library (Search).',
                    'libro' => [
                        'isbn13'        => $cand,
                        'titulo'        => $titulo !== '' ? $titulo : 'Sin título',
                        'autor'         => $autores,
                        'editorial'     => $editorial,
                        'anio'          => $anio,
                        'genero'        => !empty($doc['subject'][0]) ? (string) $doc['subject'][0] : null,
                        'idioma'        => $idioma,
                        'portada_url'   => $portada,
                        'observaciones' => null,
                    ],
                ], $cand);
            }
        }

        // 2.4 Fallback a Open Library API (bibkeys)
        $urlOpenLib = 'https://openlibrary.org/api/books?bibkeys=ISBN:' . urlencode($cand) . '&format=json&jscmd=data';
        $jsonOpenLib = catalogo_curl_get($urlOpenLib, 4);

        if ($jsonOpenLib !== null) {
            $datosOpenLib = json_decode($jsonOpenLib, true);
            $clave = 'ISBN:' . $cand;

            if (!empty($datosOpenLib[$clave])) {
                $info = $datosOpenLib[$clave];

                $titulo = (string) ($info['title'] ?? '');
                $autoresArr = [];
                if (!empty($info['authors'])) {
                    foreach ($info['authors'] as $a) {
                        if (!empty($a['name'])) {
                            $autoresArr[] = $a['name'];
                        }
                    }
                }
                $autores = implode(', ', $autoresArr);
                $editorial = !empty($info['publishers'][0]['name']) ? (string) $info['publishers'][0]['name'] : null;

                $anio = null;
                if (!empty($info['publish_date']) && preg_match('/\b(19\d\d|20\d\d)\b/', (string) $info['publish_date'], $m)) {
                    $anio = (int) $m[1];
                }

                $genero = !empty($info['subjects'][0]['name']) ? (string) $info['subjects'][0]['name'] : null;

                $portadaCandidata = null;
                if (!empty($info['cover']['large'])) {
                    $portadaCandidata = (string) $info['cover']['large'];
                } elseif (!empty($info['cover']['medium'])) {
                    $portadaCandidata = (string) $info['cover']['medium'];
                }

                $idioma = catalogo_detectar_idioma_isbn($cand);
                $portada = catalogo_resolver_portada_por_idioma($cand, $titulo, $autores, $idioma, null, null, $portadaCandidata);

                return $formatearRespuesta([
                    'ok' => true,
                    'origen' => 'open_library',
                    'mensaje' => 'Datos obtenidos desde Open Library.',
                    'libro' => [
                        'isbn13'        => $cand,
                        'titulo'        => $titulo !== '' ? $titulo : 'Sin título',
                        'autor'         => $autores !== '' ? $autores : 'Autor desconocido',
                        'editorial'     => $editorial,
                        'anio'          => $anio,
                        'genero'        => $genero,
                        'idioma'        => $idioma,
                        'portada_url'   => $portada ?: ('https://covers.openlibrary.org/b/isbn/' . $cand . '-L.jpg'),
                        'observaciones' => null,
                    ],
                ], $cand);
            }
        }
    }

    // 3. Ningún servicio encontró el libro o no hay conectividad
    return [
        'ok' => false,
        'mensaje' => 'No se encontraron datos bibliográficos automáticos para este ISBN. Puede completar el formulario de forma manual.',
    ];
}

/**
 * Genera una imagen SVG dinámica para libros que carecen de portada real.
 * Utiliza gradientes armoniosos y texto legible con iconos vectoriales.
 *
 * @param string $titulo Título del libro
 * @param string $autor  Autor del libro
 * @param string $genero Género del libro
 * @return string Código XML del SVG renderizado
 */
function catalogo_generar_svg_portada(string $titulo, string $autor = '', string $genero = ''): string {
    $tituloSeguro = htmlspecialchars(mb_strimwidth($titulo ?: 'LibrosBro', 0, 45, '…'), ENT_XML1, 'UTF-8');
    $autorSeguro  = htmlspecialchars(mb_strimwidth($autor ?: 'Biblioteca Ciudadana', 0, 35, '…'), ENT_XML1, 'UTF-8');
    $generoSeguro = htmlspecialchars(mb_strimwidth($genero ?: 'Lectura', 0, 20, ''), ENT_XML1, 'UTF-8');

    // Paletas de color deterministas según el hash del título
    $paletas = [
        ['#1e3c72', '#2a5298'],
        ['#0f2027', '#203a43'],
        ['#134e5e', '#71b280'],
        ['#2c3e50', '#4ca1af'],
        ['#4b1248', '#f0c27b'],
        ['#2b5876', '#4e4376'],
        ['#3a6073', '#3a7bd5'],
    ];
    $idx = abs(crc32($titulo)) % count($paletas);
    [$color1, $color2] = $paletas[$idx];

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 450" width="300" height="450">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$color1}" />
      <stop offset="100%" stop-color="{$color2}" />
    </linearGradient>
    <filter id="shadow" x="-5%" y="-5%" width="110%" height="110%">
      <feDropShadow dx="0" dy="4" stdDeviation="6" flood-opacity="0.3"/>
    </filter>
  </defs>

  <!-- Fondo base con gradiente -->
  <rect width="300" height="450" fill="url(#bgGrad)" rx="8" />

  <!-- Lomo de encuadernación sutil a la izquierda -->
  <rect x="0" y="0" width="18" height="450" fill="#000000" opacity="0.22" rx="4" />
  <line x1="18" y1="0" x2="18" y2="450" stroke="#ffffff" stroke-opacity="0.15" stroke-width="1" />

  <!-- Marco decorativo -->
  <rect x="30" y="30" width="240" height="390" fill="none" stroke="#ffffff" stroke-opacity="0.18" stroke-width="2" rx="4" />

  <!-- Chip de género -->
  <rect x="42" y="46" width="110" height="24" rx="12" fill="#ffffff" fill-opacity="0.2" />
  <text x="97" y="62" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="600" fill="#ffffff" text-anchor="middle" letter-spacing="1">{$generoSeguro}</text>

  <!-- Icono de libro central -->
  <g transform="translate(130, 140)" fill="#ffffff" opacity="0.85">
    <path d="M0 0h40v30H0z" fill="none"/>
    <path d="M20 2C13.5 2 7 3.5 1 6.5v28c6-3 12.5-4.5 19-4.5s13 1.5 19 4.5v-28c-6-3-12.5-4.5-19-4.5zm-2 25c-5-1.2-11-1.5-15 0V9c4-1.2 10-1.2 15 0v18zm19 0c-4-1.5-10-1.2-15 0V9c5-1.2 11-1.2 15 0v18z" fill="#ffffff"/>
  </g>

  <!-- Título del libro -->
  <text x="150" y="240" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="20" font-weight="700" fill="#ffffff" text-anchor="middle">
    {$tituloSeguro}
  </text>

  <!-- Separador -->
  <line x1="80" y1="280" x2="220" y2="280" stroke="#ffffff" stroke-opacity="0.3" stroke-width="1.5" />

  <!-- Autor del libro -->
  <text x="150" y="315" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="14" font-weight="500" fill="#f0f4f8" opacity="0.9" text-anchor="middle">
    {$autorSeguro}
  </text>

  <!-- Pie de biblioteca -->
  <text x="150" y="395" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="600" fill="#ffffff" opacity="0.6" text-anchor="middle" letter-spacing="1.5">
    BOOKSWAP · BIBLIOTECA
  </text>
</svg>
SVG;
}

/**
 * Resuelve la URL más fiable para mostrar la portada de un libro:
 * 1) Fichero de caché local en /uploads/portadas/libro_{id}.jpg si existe.
 * 2) Ruta interna /uploads/... o /assets/... si ya es local.
 * 3) Endpoint local /portada-libro?id={id} para proxificar y cachear imágenes externas de forma transparente.
 * 4) Generador dinámico /portada-svg si no hay ninguna portada disponible.
 *
 * @param array $libro Datos del libro (id, portada_url, titulo, autor, genero)
 * @return string URL segura lista para el atributo src
 */
function catalogo_resolver_url_portada(array $libro): string {
    // Prioridad Fase 12: si existe en /uploads/covers/, servirla directamente
    if (function_exists('portada_src')) {
        $srcLocal = portada_src($libro);
        if (str_starts_with($srcLocal, '/uploads/covers/')) {
            return $srcLocal;
        }
    }

    $id = (int) ($libro['id'] ?? 0);
    $portadaUrl = trim((string) ($libro['portada_url'] ?? ''));

    // 1. Si ya existe en la caché local del servidor, servirla directamente
    if ($id > 0) {
        $rutaFisicaCache = dirname(__DIR__, 2) . '/uploads/portadas/libro_' . $id . '.jpg';
        if (is_file($rutaFisicaCache) && filesize($rutaFisicaCache) > 300) {
            return '/uploads/portadas/libro_' . $id . '.jpg';
        }
    }

    // 2. Si ya es una ruta relativa local
    if ($portadaUrl !== '' && (str_starts_with($portadaUrl, '/uploads/') || str_starts_with($portadaUrl, '/assets/'))) {
        return $portadaUrl;
    }

    // 3. Si tiene id y URL remota, servir mediante el proxy local seguro
    if ($id > 0 && $portadaUrl !== '') {
        return '/portada-libro?id=' . $id;
    }

    // 4. Si tiene solo URL remota sin id
    if ($portadaUrl !== '') {
        return $portadaUrl;
    }

    // 5. Fallback a portada vectorial dinámica SVG
    $tit = urlencode((string) ($libro['titulo'] ?? 'Libro'));
    $aut = urlencode((string) ($libro['autor'] ?? ''));
    $gen = urlencode((string) ($libro['genero'] ?? ''));
    return '/portada-svg?titulo=' . $tit . '&autor=' . $aut . '&genero=' . $gen;
}

/**
 * Busca de forma desatendida o descarga y almacena en caché local la portada de un libro.
 * Libera el bloqueo de la sesión PHP de inmediato para garantizar concurrencia sin demoras.
 *
 * @param PDO $pdo Instancia de base de datos
 * @param int $libroId Identificador único del libro
 * @return array{ok: bool, portada_url?: string, mensaje?: string}
 */
function catalogo_buscar_y_cachear_portada(PDO $pdo, int $libroId): array {
    // 1. Liberar la sesión PHP de inmediato para que otras peticiones del navegador no se bloqueen
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if ($libroId <= 0) {
        return ['ok' => false, 'mensaje' => 'Identificador de libro no válido'];
    }

    $dirPortadas = dirname(__DIR__, 2) . '/uploads/portadas';
    if (!is_dir($dirPortadas)) {
        @mkdir($dirPortadas, 0777, true);
    }

    $archivoCache = $dirPortadas . '/libro_' . $libroId . '.jpg';
    $rutaWebCache = '/uploads/portadas/libro_' . $libroId . '.jpg';

    // 2. Si ya está en la caché local del servidor con tamaño válido (> 300 bytes), devolverla inmediatamente
    if (is_file($archivoCache) && filesize($archivoCache) > 300) {
        return ['ok' => true, 'portada_url' => $rutaWebCache];
    }

    // 3. Obtener metadatos del libro en la base de datos
    $stmt = $pdo->prepare('SELECT id, titulo, autor, isbn13, idioma, portada_url FROM libros WHERE id = ? LIMIT 1');
    $stmt->execute([$libroId]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$libro) {
        return ['ok' => false, 'mensaje' => 'Libro no encontrado en la base de datos'];
    }

    $portadaUrl = trim((string) ($libro['portada_url'] ?? ''));
    $isbn = preg_replace('/[^0-9X]/i', '', (string) ($libro['isbn13'] ?? ''));
    $titulo = trim((string) ($libro['titulo'] ?? ''));
    $autor = trim((string) ($libro['autor'] ?? ''));
    $idioma = trim((string) ($libro['idioma'] ?? 'es'));

    // 4. Si ya tiene URL remota asignada en la BD, descargarla directamente
    if ($portadaUrl !== '' && (str_starts_with($portadaUrl, 'http://') || str_starts_with($portadaUrl, 'https://'))) {
        $contenido = catalogo_curl_get($portadaUrl, 4);
        if ($contenido !== null && strlen($contenido) > 300) {
            file_put_contents($archivoCache, $contenido);
            return ['ok' => true, 'portada_url' => $rutaWebCache . '?t=' . time()];
        }
    }

    // 5. Si no tiene portada o la descarga falló, buscar resolviendo por idioma del libro primero y fallback a inglés
    $portadaResuelta = catalogo_resolver_portada_por_idioma($isbn, $titulo, $autor, $idioma);
    if ($portadaResuelta !== null) {
        if (str_starts_with($portadaResuelta, '/uploads/covers/') || str_starts_with($portadaResuelta, '/uploads/portadas/')) {
            $rutaFisicaResuelta = dirname(__DIR__, 2) . $portadaResuelta;
            if (is_file($rutaFisicaResuelta) && filesize($rutaFisicaResuelta) > 300) {
                @copy($rutaFisicaResuelta, $archivoCache);
            }
        } else {
            $contenido = catalogo_curl_get($portadaResuelta, 4);
            if ($contenido !== null && strlen($contenido) > 300) {
                file_put_contents($archivoCache, $contenido);
            }
        }

        if (is_file($archivoCache) && filesize($archivoCache) > 300) {
            try {
                $up = $pdo->prepare('UPDATE libros SET portada_url = ? WHERE id = ?');
                $up->execute([$rutaWebCache, $libroId]);
            } catch (Throwable) {}
            return ['ok' => true, 'portada_url' => $rutaWebCache . '?t=' . time()];
        }
    }

    return ['ok' => false, 'mensaje' => 'No se encontró ninguna portada remota para este libro'];
}

/**
 * Sirve la portada de un libro de forma segura y transparente, descargando y cacheando
 * imágenes remotas para evitar fallos de conexión, bloqueos de ISP o restricciones de CORS.
 *
 * @param PDO $pdo Instancia de conexión a la base de datos
 * @param int $libroId Identificador del libro
 * @return void
 */
function catalogo_servir_portada_libro(PDO $pdo, int $libroId): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $dirPortadas = dirname(__DIR__, 2) . '/uploads/portadas';
    if (!is_dir($dirPortadas)) {
        @mkdir($dirPortadas, 0777, true);
    }

    $archivoCache = $dirPortadas . '/libro_' . $libroId . '.jpg';

    // 1. Si ya está en caché, servirla con cabeceras de caché prolongada
    if (is_file($archivoCache) && filesize($archivoCache) > 300) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=604800');
        readfile($archivoCache);
        exit;
    }

    // 2. Intentar buscar o descargar la portada
    $res = catalogo_buscar_y_cachear_portada($pdo, $libroId);
    if (!empty($res['ok']) && is_file($archivoCache) && filesize($archivoCache) > 300) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=604800');
        readfile($archivoCache);
        exit;
    }

    // 3. Fallback a portada vectorial SVG con los metadatos del libro
    $stmt = $pdo->prepare('SELECT id, titulo, autor, genero FROM libros WHERE id = ? LIMIT 1');
    $stmt->execute([$libroId]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo catalogo_generar_svg_portada(
        (string) ($libro['titulo'] ?? 'Libro'),
        (string) ($libro['autor'] ?? ''),
        (string) ($libro['genero'] ?? '')
    );
    exit;
}

/**
 * Busca múltiples portadas alternativas en Open Library y Google Books a partir
 * de los criterios proporcionados (título, autor, editorial, isbn, año).
 * Prioriza las ediciones que coincidan con la editorial indicada.
 *
 * @param array $criterios ['titulo' => ..., 'autor' => ..., 'editorial' => ..., 'isbn' => ..., 'anio' => ...]
 * @return array{ok: bool, total: int, portadas: array, mensaje?: string}
 */
function catalogo_buscar_portadas_candidatas(array $criterios): array {
    $titulo = trim((string) ($criterios['titulo'] ?? ''));
    $autor = trim((string) ($criterios['autor'] ?? ''));
    $editorial = trim((string) ($criterios['editorial'] ?? ''));
    $isbn = !empty($criterios['isbn']) ? preg_replace('/[^0-9X]/i', '', (string) $criterios['isbn']) : '';
    $anio = !empty($criterios['anio']) ? trim((string) $criterios['anio']) : '';

    if ($titulo === '' && $isbn === '') {
        return [
            'ok' => false,
            'total' => 0,
            'portadas' => [],
            'mensaje' => 'Se requiere al menos el título o el ISBN para buscar portadas.',
        ];
    }

    $portadas = [];
    $urlsVistas = [];

    // Helper para registrar un candidato evitando duplicados
    $agregarCandidato = function(
        string $urlL,
        string $urlM,
        string $t,
        string $a,
        string $ed,
        string $an,
        string $fuente,
        bool $esMatchEditorial = false
    ) use (&$portadas, &$urlsVistas, $editorial) {
        $urlL = trim($urlL);
        $urlM = trim($urlM) ?: $urlL;
        if ($urlL === '' || isset($urlsVistas[$urlL])) {
            return;
        }
        $urlsVistas[$urlL] = true;

        $matchEd = $esMatchEditorial || ($editorial !== '' && $ed !== '' && stripos($ed, $editorial) !== false);

        $portadas[] = [
            'url' => $urlL,
            'thumbnail' => $urlM,
            'titulo' => $t,
            'autor' => $a,
            'editorial' => $ed,
            'anio' => $an,
            'fuente' => $fuente,
            'coincide_editorial' => $matchEd,
        ];
    };

    // 1. Si hay ISBN, comprobar portada directa en Open Library
    if ($isbn !== '') {
        $urlIsbnL = 'https://covers.openlibrary.org/b/isbn/' . $isbn . '-L.jpg?default=false';
        $urlIsbnM = 'https://covers.openlibrary.org/b/isbn/' . $isbn . '-M.jpg?default=false';
        $agregarCandidato(
            $urlIsbnL,
            $urlIsbnM,
            $titulo ?: 'Edición ISBN',
            $autor,
            $editorial ?: 'Edición exacta',
            $anio,
            'Open Library (ISBN)',
            true
        );
    }

    // 2. Consulta a Google Books API (intitle, inauthor, inpublisher, isbn)
    $googleKey = $_ENV['GOOGLE_BOOKS_API_KEY'] ?? getenv('GOOGLE_BOOKS_API_KEY') ?: '';
    $gbParts = [];
    if ($isbn !== '') {
        $gbParts[] = 'isbn:' . $isbn;
    }
    if ($titulo !== '') {
        $gbParts[] = 'intitle:' . mb_substr($titulo, 0, 80);
    }
    if ($autor !== '') {
        $gbParts[] = 'inauthor:' . mb_substr($autor, 0, 50);
    }
    if ($editorial !== '') {
        $gbParts[] = 'inpublisher:' . mb_substr($editorial, 0, 50);
    }

    if (!empty($gbParts)) {
        $queryGb = implode(' ', $gbParts);
        $urlGB = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($queryGb) . '&maxResults=10' . ($googleKey ? '&key=' . urlencode($googleKey) : '');
        $rawGb = catalogo_curl_get($urlGB, 4);
        if ($rawGb) {
            $dataGb = json_decode($rawGb, true);
            foreach ($dataGb['items'] ?? [] as $item) {
                $info = $item['volumeInfo'] ?? [];
                $imgs = $info['imageLinks'] ?? [];
                $thumb = $imgs['thumbnail'] ?? ($imgs['smallThumbnail'] ?? null);
                if ($thumb) {
                    $thumb = str_replace('http://', 'https://', (string) $thumb);
                    $urlL = preg_replace('/&edge=curl/i', '', $thumb);
                    $itemTit = (string) ($info['title'] ?? $titulo);
                    $itemAut = implode(', ', $info['authors'] ?? ($autor ? [$autor] : []));
                    $itemPub = (string) ($info['publisher'] ?? '');
                    $itemDate = (string) ($info['publishedDate'] ?? '');
                    $itemAnio = substr($itemDate, 0, 4);

                    $agregarCandidato(
                        $urlL,
                        $thumb,
                        $itemTit,
                        $itemAut,
                        $itemPub,
                        $itemAnio,
                        'Google Books'
                    );
                }
            }
        }
    }

    // 3. Consulta a Open Library Search API
    if ($titulo !== '') {
        $olParams = ['title' => $titulo];
        if ($autor !== '') $olParams['author'] = $autor;
        if ($editorial !== '') $olParams['publisher'] = $editorial;
        if ($isbn !== '') $olParams['isbn'] = $isbn;

        $urlOl = 'https://openlibrary.org/search.json?' . http_build_query($olParams) . '&limit=12';
        $rawOl = catalogo_curl_get($urlOl, 5);
        $workKeys = [];

        if ($rawOl) {
            $dataOl = json_decode($rawOl, true);
            foreach ($dataOl['docs'] ?? [] as $doc) {
                $coverI = $doc['cover_i'] ?? null;
                $docTit = (string) ($doc['title'] ?? $titulo);
                $docAut = (string) (($doc['author_name'] ?? [])[0] ?? $autor);
                $docPub = (string) (($doc['publisher'] ?? [])[0] ?? '');
                $docYear = (string) ($doc['first_publish_year'] ?? '');

                if ($coverI) {
                    $uL = 'https://covers.openlibrary.org/b/id/' . (int) $coverI . '-L.jpg';
                    $uM = 'https://covers.openlibrary.org/b/id/' . (int) $coverI . '-M.jpg';
                    $agregarCandidato($uL, $uM, $docTit, $docAut, $docPub, $docYear, 'Open Library');
                }

                if (!empty($doc['key']) && count($workKeys) < 2) {
                    $workKeys[] = $doc['key'];
                }
            }
        }

        // Si la búsqueda con editorial devolvió pocas (< 5) y se había filtrado por editorial,
        // buscar también de forma amplia (título + autor)
        if (count($portadas) < 5 && $editorial !== '') {
            $olBroadParams = ['title' => $titulo];
            if ($autor !== '') $olBroadParams['author'] = $autor;
            $urlBroad = 'https://openlibrary.org/search.json?' . http_build_query($olBroadParams) . '&limit=12';
            $rawBroad = catalogo_curl_get($urlBroad, 5);
            if ($rawBroad) {
                $dataBroad = json_decode($rawBroad, true);
                foreach ($dataBroad['docs'] ?? [] as $doc) {
                    $coverI = $doc['cover_i'] ?? null;
                    if ($coverI) {
                        $docTit = (string) ($doc['title'] ?? $titulo);
                        $docAut = (string) (($doc['author_name'] ?? [])[0] ?? $autor);
                        $docPub = (string) (($doc['publisher'] ?? [])[0] ?? '');
                        $docYear = (string) ($doc['first_publish_year'] ?? '');
                        $uL = 'https://covers.openlibrary.org/b/id/' . (int) $coverI . '-L.jpg';
                        $uM = 'https://covers.openlibrary.org/b/id/' . (int) $coverI . '-M.jpg';
                        $agregarCandidato($uL, $uM, $docTit, $docAut, $docPub, $docYear, 'Open Library');
                    }
                    if (!empty($doc['key']) && count($workKeys) < 2) {
                        $workKeys[] = $doc['key'];
                    }
                }
            }
        }

        // 4. Consultar ediciones específicas de la obra en Open Library
        foreach ($workKeys as $wk) {
            $urlEditions = 'https://openlibrary.org' . $wk . '/editions.json?limit=25';
            $rawEditions = catalogo_curl_get($urlEditions, 4);
            if ($rawEditions) {
                $dataEd = json_decode($rawEditions, true);
                foreach ($dataEd['entries'] ?? [] as $entry) {
                    if (empty($entry['covers'])) continue;
                    $coverId = (int) $entry['covers'][0];
                    if ($coverId <= 0) continue;

                    $edTit = (string) ($entry['title'] ?? $titulo);
                    $edPub = implode(', ', $entry['publishers'] ?? []);
                    $edDate = (string) ($entry['publish_date'] ?? '');
                    $uL = 'https://covers.openlibrary.org/b/id/' . $coverId . '-L.jpg';
                    $uM = 'https://covers.openlibrary.org/b/id/' . $coverId . '-M.jpg';
                    $agregarCandidato($uL, $uM, $edTit, $autor, $edPub, $edDate, 'Open Library (Edición)');
                }
            }
        }
    }

    // Ordenar resultados: las que coincidan con la editorial solicitada primero
    usort($portadas, function($a, $b) {
        $matchA = !empty($a['coincide_editorial']);
        $matchB = !empty($b['coincide_editorial']);
        if ($matchA && !$matchB) return -1;
        if (!$matchA && $matchB) return 1;
        return 0;
    });

    return [
        'ok' => true,
        'total' => count($portadas),
        'portadas' => $portadas,
        'criterios' => [
            'titulo' => $titulo,
            'autor' => $autor,
            'editorial' => $editorial,
            'isbn' => $isbn,
            'anio' => $anio,
        ],
    ];
}


