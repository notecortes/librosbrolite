<?php
/**
 * BookSwap · backup.php — Helper de copias de seguridad y restauración en PHP puro (v4.1).
 *
 * Funcionalidades (§12):
 * - Generación de volcado SQL completo (CREATE TABLE + INSERT) sin herramientas externas.
 * - Almacenamiento seguro en /backups/ protegido contra acceso web (.htaccess).
 * - Registro en tabla backups y auditoría detallada (T-BAK-01).
 * - Restauración completa de base de datos sobre esquema limpio (T-BAK-02).
 * - Control de retención automática de copias (retencion_backups).
 */
declare(strict_types=1);

require_once __DIR__ . '/auditoria.php';

/**
 * Genera una copia de seguridad en formato SQL de toda la base de datos BookSwap.
 *
 * @param PDO      $pdo        Instancia de conexión PDO
 * @param string   $tipo       Tipo de copia: 'manual' o 'auto'
 * @param int|null $usuarioId  ID del administrador que genera la copia (null para copias automáticas)
 * @return array Datos de la copia creada (id, archivo, tamano, ruta)
 * @throws Exception Si no se pueden consultar las tablas o escribir el fichero
 */
function backup_crear(PDO $pdo, string $tipo = 'manual', ?int $usuarioId = null): array {
    $dirBackups = dirname(__DIR__, 2) . '/backups';
    if (!is_dir($dirBackups)) {
        if (!mkdir($dirBackups, 0755, true) && !is_dir($dirBackups)) {
            throw new RuntimeException("No se pudo crear el directorio de backups: {$dirBackups}");
        }
    }

    $nombreArchivo = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
    $rutaCompleta = $dirBackups . '/' . $nombreArchivo;

    $tablas = $pdo->query('SHOW FULL TABLES WHERE Table_Type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);

    $sql = "-- ============================================================\n";
    $sql .= "-- BookSwap · Copia de Seguridad SQL (v4.1)\n";
    $sql .= "-- Generado el: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Tipo de copia: " . strtoupper($tipo) . "\n";
    $sql .= "-- ============================================================\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET time_zone = '+00:00';\n\n";

    foreach ($tablas as $tabla) {
        // 1. Estructura de la tabla (CREATE TABLE)
        $stmtCreate = $pdo->query("SHOW CREATE TABLE `{$tabla}`");
        $rowCreate = $stmtCreate->fetch(PDO::FETCH_NUM);
        $createSql = $rowCreate[1] ?? '';

        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "-- Estructura de tabla para `{$tabla}`\n";
        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n";
        $sql .= $createSql . ";\n\n";

        // 2. Datos de la tabla (INSERT INTO)
        $stmtRows = $pdo->query("SELECT * FROM `{$tabla}`");
        $filas = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($filas)) {
            $sql .= "-- Volcado de datos para `{$tabla}`\n";
            $columnas = array_keys($filas[0]);
            $colList = implode('`, `', $columnas);

            $valoresSql = [];
            foreach ($filas as $fila) {
                $campos = [];
                foreach ($fila as $val) {
                    if ($val === null) {
                        $campos[] = 'NULL';
                    } else {
                        $campos[] = $pdo->quote((string) $val);
                    }
                }
                $valoresSql[] = '(' . implode(', ', $campos) . ')';

                // Escribir en bloques de 50 filas para no desbordar memoria
                if (count($valoresSql) >= 50) {
                    $sql .= "INSERT INTO `{$tabla}` (`{$colList}`) VALUES\n" . implode(",\n", $valoresSql) . ";\n";
                    $valoresSql = [];
                }
            }

            if (!empty($valoresSql)) {
                $sql .= "INSERT INTO `{$tabla}` (`{$colList}`) VALUES\n" . implode(",\n", $valoresSql) . ";\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $sql .= "-- Fin del volcado BookSwap\n";

    if (file_put_contents($rutaCompleta, $sql) === false) {
        throw new RuntimeException("Error al escribir el archivo de copia de seguridad en: {$rutaCompleta}");
    }

    $tamano = (int) filesize($rutaCompleta);

    // Registro en la tabla backups
    $stmtIns = $pdo->prepare('
        INSERT INTO backups (archivo, tamano, tipo, fecha, usuario_id)
        VALUES (?, ?, ?, NOW(), ?)
    ');
    $stmtIns->execute([$nombreArchivo, $tamano, $tipo, $usuarioId]);
    $backupId = (int) $pdo->lastInsertId();

    // Auditoría del sistema
    log_accion(
        $usuarioId,
        'backup_creado',
        'backups',
        $backupId,
        [
            'archivo' => $nombreArchivo,
            'tamano'  => $tamano,
            'tipo'    => $tipo,
        ]
    );

    return [
        'ok'      => true,
        'id'      => $backupId,
        'archivo' => $nombreArchivo,
        'tamano'  => $tamano,
        'ruta'    => $rutaCompleta,
    ];
}

/**
 * Restaura una copia de seguridad SQL sobre la base de datos actual.
 *
 * @param PDO         $pdo         Instancia de conexión PDO
 * @param string      $contenidoOArchivo Contenido SQL directo o ruta al fichero .sql
 * @param int|null    $usuarioId   ID del administrador que ejecuta la restauración
 * @return array Resultado de la operación
 * @throws Exception Si el contenido es inválido o falla la ejecución
 */
function backup_restaurar(PDO $pdo, string $contenidoOArchivo, ?int $usuarioId = null): array {
    if (file_exists($contenidoOArchivo)) {
        $sql = (string) file_get_contents($contenidoOArchivo);
    } else {
        $sql = $contenidoOArchivo;
    }

    if (trim($sql) === '') {
        throw new InvalidArgumentException('El contenido del archivo de copia de seguridad está vacío.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

    // Ejecución segura de sentencias individuales
    $len = strlen($sql);
    $query = '';
    $inString = false;
    $stringChar = '';

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];

        if ($inString) {
            if ($char === $stringChar) {
                if ($i > 0 && $sql[$i - 1] === '\\') {
                    // Carácter escapado
                } else {
                    $inString = false;
                }
            }
            $query .= $char;
        } else {
            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $query .= $char;
            } elseif ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                // Comentario de una línea
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
            } elseif ($char === ';') {
                $qTrim = trim($query);
                if ($qTrim !== '') {
                    $pdo->exec($qTrim);
                }
                $query = '';
            } else {
                $query .= $char;
            }
        }
    }

    $qTrim = trim($query);
    if ($qTrim !== '') {
        $pdo->exec($qTrim);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');

    // Registro de auditoría
    log_accion(
        $usuarioId,
        'backup_restaurado',
        'backups',
        null,
        ['bytes_procesados' => strlen($sql)]
    );

    return ['ok' => true];
}

/**
 * Obtiene la lista completa de copias de seguridad registradas en el sistema.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @return array Lista de copias ordenadas de más reciente a más antigua
 */
function backup_listar(PDO $pdo): array {
    $stmt = $pdo->query('
        SELECT b.*, u.nombre AS usuario_nombre, u.email AS usuario_email
        FROM backups b
        LEFT JOIN usuarios u ON b.usuario_id = u.id
        ORDER BY b.id DESC
    ');
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Aplica la política de retención eliminando copias de seguridad que excedan el límite configurado.
 *
 * @param PDO $pdo Instancia de conexión PDO
 * @param int $retencion Máximo de copias a conservar
 * @return int Cantidad de archivos eliminados
 */
function backup_aplicar_retencion(PDO $pdo, int $retencion = 10): int {
    if ($retencion <= 0) {
        return 0;
    }

    $stmt = $pdo->query('SELECT id, archivo FROM backups ORDER BY id DESC');
    $todas = $stmt ? $stmt->fetchAll() : [];

    if (count($todas) <= $retencion) {
        return 0;
    }

    $sobrantes = array_slice($todas, $retencion);
    $dirBackups = dirname(__DIR__, 2) . '/backups';
    $eliminadas = 0;

    foreach ($sobrantes as $b) {
        $ruta = $dirBackups . '/' . $b['archivo'];
        if (file_exists($ruta)) {
            @unlink($ruta);
        }
        $pdo->prepare('DELETE FROM backups WHERE id = ?')->execute([(int) $b['id']]);
        $eliminadas++;
    }

    return $eliminadas;
}
