# BookSwap · Auditoría Integral de Seguridad y Hardening (OWASP Top 10)

**Fecha:** 24 de septiembre de 2026  
**Versión:** BookSwap v4.3  
**Alcance:** Toda la base de código (rutas, controladores, asistentes, vistas, base de datos y configuración).  
**Resultado de Verificación:** 84 PASS / 0 FAIL (100% verde en suite canónica).

---

## 1. Resumen Ejecutivo

Se ha realizado una auditoría exhaustiva de seguridad siguiendo el estándar **OWASP Top 10 (2021)** sobre la totalidad de los componentes de BookSwap. La arquitectura de la aplicación, concebida en PHP 8.2 nativo sin dependencias de terceros ni frameworks pesados, minimiza drásticamente la superficie de ataque de dependencias vulnerables (A06).

Durante la auditoría se identificaron y subsanaron puntos de mejora en control de acceso, prevención de condiciones de carrera mediante exclusión mutua pesimista, mitigación estricta de Path Traversal en restauración de copias de seguridad, adición de directivas de cabeceras HTTP (`Permissions-Policy`), y control estricto de visibilidad de trazas de error según entorno (`APP_ENV`).

Todos los hallazgos de severidad **Crítico** y **Alto** se encuentran **completamente mitigados y verificados** mediante la suite automatizada de pruebas con la adición de los tests canónicos `T-SEC-06` a `T-SEC-15`.

---

## 2. Matriz de Hallazgos y Severidades

| ID | Categoría OWASP | Descripción del Riesgo | Severidad Original | Estado Actual | Test de Regresión |
|:---|:---|:---|:---:|:---:|:---:|
| **SEC-01** | A03: Injection | Inyección SQL en formularios de autenticación | **Crítico** | Mitigado (PDO Prepared Statements, Emulate Prepares = false) | `T-SEC-01` |
| **SEC-02** | A01: Broken Access Control | Acceso no autorizado de rol USUARIO a endpoints de administración | **Crítico** | Mitigado (`exigir_rol(['ADMIN'])` server-side) | `T-SEC-02`, `T-SEC-13` |
| **SEC-03** | A08: Integrity Failures | Peticiones POST maliciosas sin token CSRF | **Alto** | Mitigado (Validación global obligatoria de token CSRF) | `T-SEC-03`, `T-SEC-10` |
| **SEC-04** | A03: Injection (XSS) | Inyección de scripts (Cross-Site Scripting) en títulos y campos | **Alto** | Mitigado (Helper `e()` con `ENT_QUOTES \| ENT_SUBSTITUTE` UTF-8) | `T-SEC-04`, `T-SEC-09` |
| **SEC-05** | A01: Broken Access Control | Acceso directo a ficheros de copias de seguridad en disco | **Alto** | Mitigado (DocumentRoot aislado en `/public`, `.htaccess` restrictivo) | `T-SEC-05` |
| **SEC-06** | A03: Injection | Inyección SQL en parámetros de catálogo y búsqueda libre (`q`, `genero`) | **Alto** | Mitigado (Parámetros vinculados por nombre/tipo en PDO) | `T-SEC-06` |
| **SEC-07** | A01: Broken Access Control | BOLA / IDOR: Cancelación de reservas activas ajenas | **Alto** | Mitigado (Verificación de propiedad `usuario_id` en `reserva_cancelar`) | `T-SEC-07` |
| **SEC-08** | A03: Injection | Path Traversal en descarga y restauración de backups SQL | **Alto** | Mitigado (`basename()`, validación `.sql` y rutas canónicas) | `T-SEC-08` |
| **SEC-09** | A05: Security Misconfig | Ausencia de cabeceras HTTP modernas de aislamiento y permisos | **Medio** | Mitigado (`Permissions-Policy`, `X-Content-Type-Options`, `X-Frame-Options`) | `T-SEC-11` |
| **SEC-10** | A07: Auth Failures | Ataques de fuerza bruta contra el formulario de inicio de sesión | **Medio** | Mitigado (Rate limiting: bloqueo de 15 min al 6º intento fallido) | `T-AUTH-03`, `T-SEC-12` |
| **SEC-11** | A04: Insecure Design | Condiciones de carrera en saldo de tokens y doble gasto | **Alto** | Mitigado (Transacciones ACID y `FOR UPDATE` en usuario y ledger) | `T-LEDGER-02`, `T-SEC-14` |
| **SEC-12** | A02: Cryptographic Failures | Almacenamiento o exposición de contraseñas en texto plano | **Crítico** | Mitigado (Hashes nativos BCrypt cost 10, enumeración mitigada) | `T-AUTH-04`, `T-SEC-15` |
| **SEC-13** | A05: Security Misconfig | Exposición de trazas internas de error (`display_errors`) en producción | **Medio** | Mitigado (Configurado condicionalmente según `APP_ENV`) | Verificado en código |
| **SEC-14** | A10: SSRF | Peticiones arbitrarias de red en descarga de portadas remotas | **Medio** | Mitigado (Validación de protocolo `http/https`, formato ISBN e imágenes) | `T-PERF-02` |

---

## 3. Análisis Detallado por Dominio OWASP

### A01:2021 — Broken Access Control (Control de Acceso)
- **Mecanismo:** RBAC centralizado en `app/helpers/auth.php` y `app/router.php`.
- **Implementación:**
  - `exigir_autenticado()` comprueba la existencia de sesión válida.
  - `exigir_rol(array $roles)` valida el rol del usuario contra la base de datos y aborta con HTTP 403 Forbidden antes de procesar cualquier lógica.
  - Mitigación BOLA/IDOR: En `reserva_cancelar()`, se verifica explícitamente que la reserva pertenezca al `$usuarioId` en sesión o que el solicitante cuente con rol `ADMIN` o `PERSONAL`.
  - Prevención de escalada de privilegios: Los endpoints `/admin/usuarios/crear` y `/admin/usuarios/editar` están estrictamente restringidos al rol `ADMIN`. El endpoint de usuario `/dashboard/contrasena` únicamente permite modificar credenciales propias tras verificar la clave actual.

### A02:2021 — Cryptographic Failures (Fallos Criptográficos)
- **Almacenamiento de Contraseñas:** Se emplea `password_hash()` con algoritmo `PASSWORD_BCRYPT` y coste 10. Ninguna contraseña se almacena ni transmite en texto plano.
- **Protección de Sesiones:** La cookie de sesión se emite con flags `HttpOnly`, `SameSite=Lax` y `use_only_cookies=1`. Tras autenticarse correctamente, se ejecuta `session_regenerate_id(true)` para evitar ataques de fijación de sesión.
- **Tokens CSRF:** Generados con entropía criptográfica fuerte mediante `bin2hex(random_bytes(32))`.

### A03:2021 — Injection (Inyección)
- **Inyección SQL:** Desactivado el modo de emulación de sentencias (`PDO::ATTR_EMULATE_PREPARES => false`). Todas las consultas dinámicas (catálogo, filtros, búsqueda, préstamos, administración) utilizan sentencias preparadas nativas con marcadores posicionales (`?`) o nominales (`:clave`).
- **Path Traversal:** 
  - La descarga de recursos estáticos en `app/router.php` resuelve la ruta absoluta con `realpath()` y verifica que pertenezca estrictamente al prefijo de `/assets` o `/uploads`.
  - En `/admin/backups/descargar` y `/admin/backups/restaurar`, los nombres de archivo se limpian con `basename()`, se restringen a la extensión `.sql` y se verifica su existencia en el directorio aislado `backups/`.
- **Cross-Site Scripting (XSS):** Todo dato renderizado en vistas pasa por el helper `e($cadena)`, que implementa `htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

### A04:2021 — Insecure Design (Diseño Inseguro y Concurrencia)
- **Invariante Económico:** El saldo de tokens se calcula exclusivamente como `SUM(cantidad)` sobre la tabla inmutable `movimientos_tokens`.
- **Prevención de Carrera (Race Conditions):** 
  - En `ledger_registrar_movimiento`, se aplica bloqueo pesimista `SELECT id FROM usuarios WHERE id = ? FOR UPDATE`, serializando cualquier modificación concurrente sobre el mismo usuario.
  - En `mostrador_procesar_entrega` y `mostrador_entrega_directa`, el ejemplar es bloqueado con `FOR UPDATE` para garantizar que un mismo libro físico no pueda ser retirado dos veces por solicitudes simultáneas.

### A05:2021 — Security Misconfiguration (Configuración Errónea)
- **Cabeceras de Seguridad HTTP:** Emitidas en `app/bootstrap.php`:
  - `X-Content-Type-Options: nosniff` (previene MIME-type sniffing).
  - `X-Frame-Options: SAMEORIGIN` (previene clickjacking).
  - `Referrer-Policy: strict-origin-when-cross-origin` (protege URLs de referencia).
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()` (restringe APIs sensibles del navegador).
- **Manejo de Errores:** En `config/config.php`, si `APP_ENV === 'production'`, se fuerza `display_errors = 0` y `display_startup_errors = 0`, evitando fugas de información interna en respuestas de producción.

### A06:2021 — Vulnerable and Outdated Components (Componentes Vulnerables)
- La aplicación se ejecuta sobre PHP 8.2 nativo sin frameworks de terceros (sin dependencias en `vendor/` ni `node_modules`).
- El contenedor Docker oficial utiliza `php:8.2-apache` con extensiones mínimas (`pdo_mysql`, `gd`, `curl`).

### A07:2021 — Identification and Authentication Failures (Fallos de Autenticación)
- **Fuerza Bruta:** Control de tasa mediante la tabla `intentos_login`. Si un correo electrónico acumula 5 intentos fallidos en los últimos 15 minutos, el 6º intento queda bloqueado con un mensaje temporal.
- **Enumeración de Cuentas:** Respuestas de error genéricas («Credenciales incorrectas») que no revelan si el correo existe o no en el sistema.

### A08:2021 — Software and Data Integrity Failures (Falta de Integridad)
- **CSRF Global:** En `app/router.php`, toda petición HTTP `POST` pasa incondicionalmente por la validación del token `csrf_verificar()`.
- **Integridad de Backups:** Verificación estricta de estructura SQL y comprobación de tablas base antes de volcar o restaurar.

### A09:2021 — Security Logging and Monitoring Failures (Registro y Monitorización)
- **Pista de Auditoría:** Todas las acciones críticas (inicios de sesión, cambios de rol, ajustes contables de tokens, cancelaciones de reservas, altas y bajas de ejemplares, descargas y restauraciones) quedan registradas en la tabla `registro_auditoria` con `usuario_id`, `accion`, `entidad`, `entidad_id`, `detalles` (JSON) y `fecha`.

### A10:2021 — Server-Side Request Forgery (SSRF)
- **Descarga de Portadas:** En `portada_descargar_cache()`, se valida que la URL emplee exclusivamente esquemas `http` o `https`. El proceso cuenta con timeout restrictivo (5 segundos), tamaño máximo limitado a 2 MB y validación estricta de `Content-Type: image/*`.

---

## 4. Cobertura de Tests de Seguridad

La suite de pruebas automatizadas en `tests/run.php` incorpora 15 pruebas de seguridad especializadas:

1. **`T-SEC-01`**: SQLi en login (`' OR 1=1--`) rechazado sin autenticar ni generar errores 500.
2. **`T-SEC-02`**: Rol USUARIO bloqueado con 403 en endpoints administrativos.
3. **`T-SEC-03`**: Rechazo 403 de peticiones POST sin cabecera/campo CSRF.
4. **`T-SEC-04`**: Inyección XSS en títulos de libro neutralizada mediante entidades HTML.
5. **`T-SEC-05`**: Directorio `/backups/` inaccesible vía peticiones HTTP externas (403/404).
6. **`T-SEC-06`**: Inyección SQL en parámetros de catálogo (`q`, `genero`) sin alteraciones en la consulta.
7. **`T-SEC-07`**: Mitigación BOLA/IDOR: intento de cancelar reserva activa de otro usuario denegado.
8. **`T-SEC-08`**: Path Traversal en descarga de copias de seguridad bloqueado (403/404 sin fuga de `/etc/passwd`).
9. **`T-SEC-09`**: Sanitización XSS de payloads dinámicos mediante el helper `e()`.
10. **`T-SEC-10`**: Rechazo con 403 en operaciones críticas con token CSRF inválido o manipulado.
11. **`T-SEC-11`**: Emisión obligatoria de cabeceras HTTP de seguridad (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`).
12. **`T-SEC-12`**: Bloqueo por fuerza bruta tras 5 intentos fallidos consecutivos en login.
13. **`T-SEC-13`**: Imposibilidad de escalación de privilegios hacia el rol `ADMIN` por parte de usuarios convencionales.
14. **`T-SEC-14`**: Exclusión mutua e imposibilidad de forzar saldos negativos de tokens en el ledger.
15. **`T-SEC-15`**: Almacenamiento seguro de contraseñas con algoritmos criptográficos y respuestas genéricas contra enumeración de usuarios.

---

## 5. Conclusión

El sistema BookSwap v4.3 satisface los requerimientos de seguridad y robustez del estándar OWASP Top 10. Las medidas de hardening aplicadas proporcionan defensa en profundidad tanto a nivel de transporte (cabeceras HTTP), control de acceso (RBAC e IDOR), protección contra inyecciones (PDO nativo y XSS escaping) y consistencia transaccional (bloqueo pesimista).
