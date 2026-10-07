<?php
/**
 * BookSwap · usuario_persistencia.php — Persistencia de cuentas de usuario creadas.
 *
 * Garantiza que cualquier cuenta de usuario creada manualmente por administradores,
 * profesores o lectores no se pierda durante reinicios de contenedores o ejecuciones de pruebas.
 */
declare(strict_types=1);

require_once __DIR__ . '/funciones.php';

/**
 * Obtiene la ruta al fichero de almacenamiento persistente de usuarios.
 *
 * @return string Ruta absoluta al fichero JSON en database/
 */
function usuarios_obtener_ruta_persistencia(): string {
    return dirname(__DIR__, 2) . '/database/usuarios_persistentes.json';
}

/**
 * Guarda en disco todas las cuentas de usuario no canónicas (personalizadas).
 *
 * @param PDO|null $pdo Instancia de conexión opcional
 * @return int Cantidad de usuarios persistidos
 */
function usuarios_persistir_personalizados(?PDO $pdo = null): int {
    try {
        $db = $pdo ?? db();
        
        // Comprobar si existe la tabla usuarios
        $existe = $db->query("SHOW TABLES LIKE 'usuarios'")->fetch();
        if (!$existe) {
            return 0;
        }

        // Seleccionar usuarios que no sean demos canónicas ni tests efímeros
        $sql = "
            SELECT u.id, u.nombre, u.email, u.password_hash, u.rol_id, u.activo,
                   u.auth_provider, u.email_verificado, u.fecha_registro,
                   (SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_tokens WHERE usuario_id = u.id) AS tokens_saldo
            FROM usuarios u
            WHERE u.email NOT IN (
                'admin@bookswap.local',
                'personal@bookswap.local',
                'usuario@bookswap.local',
                'mixta@bookswap.local',
                'google.demo@bookswap.local'
            )
            AND u.email NOT LIKE 'nuevo_lector_%'
            AND u.email NOT LIKE 'bloqueo_%'
            AND u.email NOT LIKE 'nuevo.google.%'
            AND u.email NOT LIKE 'pendiente_%'
            AND u.email NOT LIKE 'pendiente2_%'
            AND u.email NOT LIKE 'socio_test_%'
            AND u.email NOT LIKE 'sin_activar_%'
            AND u.email NOT LIKE 'norm_%'
            AND u.email NOT LIKE 'hist_user_%'
            AND u.email NOT LIKE 'rgrc_%'
            AND u.email NOT LIKE 'adm_usr_%'
            AND u.email NOT LIKE '%@example.com'
            AND u.email NOT LIKE '%@test.local'
            AND u.email NOT LIKE '%@mock.google.com'
            AND u.email NOT LIKE '%@bookswap.local'
        ";

        $stmt = $db->query($sql);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($usuarios)) {
            return 0;
        }

        $archivo = usuarios_obtener_ruta_persistencia();
        
        // Leer usuarios ya existentes en el archivo para no sobreescribir si hubo anteriores
        $anteriores = [];
        if (file_exists($archivo)) {
            $contenido = file_get_contents($archivo);
            $decodificado = json_decode((string) $contenido, true);
            if (is_array($decodificado)) {
                foreach ($decodificado as $uAnt) {
                    if (!empty($uAnt['email'])) {
                        $anteriores[$uAnt['email']] = $uAnt;
                    }
                }
            }
        }

        foreach ($usuarios as $u) {
            // Solo persistir si tiene un hash bcrypt válido o es google
            if (!empty($u['password_hash'])) {
                $info = password_get_info($u['password_hash']);
                if ($info['algo'] === null || $info['algo'] === 0) {
                    continue;
                }
            }
            $anteriores[$u['email']] = $u;
        }

        file_put_contents($archivo, json_encode(array_values($anteriores), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return count($anteriores);
    } catch (Throwable $e) {
        error_log('Error en usuarios_persistir_personalizados: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Restaura en la base de datos las cuentas guardadas en el almacenamiento persistente.
 *
 * @param PDO|null $pdo Instancia de conexión opcional
 * @return int Cantidad de usuarios restaurados
 */
function usuarios_restaurar_personalizados(?PDO $pdo = null): int {
    $archivo = usuarios_obtener_ruta_persistencia();
    if (!file_exists($archivo)) {
        return 0;
    }

    $contenido = file_get_contents($archivo);
    $usuarios = json_decode((string) $contenido, true);
    if (!is_array($usuarios) || empty($usuarios)) {
        return 0;
    }

    try {
        $db = $pdo ?? db();
        $restaurados = 0;

        $stmtExiste = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmtInsert = $db->prepare('
            INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo, auth_provider, email_verificado, fecha_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmtToken = $db->prepare('
            INSERT INTO movimientos_tokens (usuario_id, cantidad, tipo, concepto, saldo_resultante, fecha)
            VALUES (?, ?, "bono_bienvenida", ?, ?, NOW())
        ');

        foreach ($usuarios as $u) {
            $email = $u['email'] ?? '';
            if ($email === '') {
                continue;
            }

            // Validar hash si existe
            if (!empty($u['password_hash'])) {
                $info = password_get_info($u['password_hash']);
                if ($info['algo'] === null || $info['algo'] === 0) {
                    continue;
                }
            }

            $stmtExiste->execute([$email]);
            $fila = $stmtExiste->fetch(PDO::FETCH_ASSOC);

            $userId = null;
            if (!$fila) {
                $stmtInsert->execute([
                    $u['nombre'] ?? 'Usuario',
                    $email,
                    $u['password_hash'] ?? null,
                    (int) ($u['rol_id'] ?? 3),
                    (int) ($u['activo'] ?? 1),
                    $u['auth_provider'] ?? 'local',
                    (int) ($u['email_verificado'] ?? 1),
                    $u['fecha_registro'] ?? date('Y-m-d H:i:s'),
                ]);
                $userId = (int) $db->lastInsertId();
                $restaurados++;
            } else {
                $userId = (int) $fila['id'];
            }

            // Restaurar saldo de tokens si tenía y la cuenta no tiene movimientos
            $tokens = (int) ($u['tokens_saldo'] ?? 0);
            if ($tokens > 0 && $userId) {
                $stmtCheckMov = $db->prepare('SELECT COUNT(*) FROM movimientos_tokens WHERE usuario_id = ?');
                $stmtCheckMov->execute([(int) $userId]);
                $hayMov = (int) $stmtCheckMov->fetchColumn();
                if ($hayMov === 0) {
                    $stmtToken->execute([
                        $userId,
                        $tokens,
                        'Saldo restaurado de sesión previa',
                        $tokens,
                    ]);
                }
            }
        }

        return $restaurados;
    } catch (Throwable $e) {
        error_log('Error en usuarios_restaurar_personalizados: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Elimina un usuario del archivo de almacenamiento persistente usuarios_persistentes.json.
 *
 * @param string $email Correo electrónico del usuario
 * @return bool True si se procesó correctamente
 */
function usuarios_eliminar_de_persistencia(string $email): bool {
    $archivo = usuarios_obtener_ruta_persistencia();
    if (!file_exists($archivo)) {
        return true;
    }

    $contenido = @file_get_contents($archivo);
    if ($contenido === false) {
        return false;
    }

    $usuarios = json_decode($contenido, true);
    if (!is_array($usuarios)) {
        return true;
    }

    $emailNorm = strtolower(trim($email));
    $filtrados = [];
    $encontrado = false;

    foreach ($usuarios as $u) {
        if (isset($u['email']) && strtolower(trim((string) $u['email'])) === $emailNorm) {
            $encontrado = true;
            continue;
        }
        $filtrados[] = $u;
    }

    if ($encontrado) {
        @file_put_contents($archivo, json_encode(array_values($filtrados), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return true;
}

