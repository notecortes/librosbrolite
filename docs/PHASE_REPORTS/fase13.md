# Reporte de Fase 13 — Seguridad OWASP & Hardening

**Fecha:** 24 de septiembre de 2026  
**Proyecto:** BookSwap (v4.3)  
**Estado:** Completada (0 FAIL, 0 SKIP, 84 PASS)

---

## 1. Objetivos de la Fase

1. **Auditoría Integral de Seguridad:**
   - Realizar una auditoría completa del código fuente, rutas, base de datos y flujos económicos siguiendo el marco OWASP Top 10.
   - Clasificar los hallazgos por severidad (Crítico, Alto, Medio, Bajo, Informativo) y documentarlos detalladamente en `docs/SECURITY_AUDIT.md`.
2. **Hardening y Mitigación de Vulnerabilidades:**
   - Corregir todos los hallazgos de severidad Crítico y Alto.
   - Reforzar el control de concurrencia y prevención de doble gasto/saldo negativo en transacciones de ledger.
   - Bloquear cualquier posibilidad de Path Traversal en restauración y descarga de copias de seguridad.
   - Incorporar cabeceras HTTP de seguridad modernas (`Permissions-Policy`).
   - Evitar exposición de errores internos (`display_errors = 0`) en entorno de producción.
3. **Tests de Regresión de Seguridad:**
   - Implementar y validar los tests automatizados `T-SEC-06` a `T-SEC-15` en `tests/run.php`.
   - Garantizar suite canónica en 0 FAIL.

---

## 2. Acciones Realizadas

### 2.1 Auditoría y Documentación
- Se elaboró el documento exhaustivo [`docs/SECURITY_AUDIT.md`](../SECURITY_AUDIT.md), analizando cada dominio de OWASP Top 10:
  - **A01: Broken Access Control:** Verificación de RBAC (`exigir_rol`), prevención de escalada de privilegios y validación de propiedad en cancelación de reservas (BOLA/IDOR).
  - **A02: Cryptographic Failures:** Uso exclusivo de BCrypt cost 10 para contraseñas, tokens CSRF de 256 bits, y protección de cookies de sesión con flags HttpOnly y SameSite.
  - **A03: Injection:** Sentencias preparadas nativas (`PDO::ATTR_EMULATE_PREPARES => false`), validación estricta de rutas con `basename()` y `realpath()`, y escape sistemático de salida HTML mediante `e()`.
  - **A04: Insecure Design:** Modelo inmutable del ledger y exclusión mutua estricta con `SELECT ... FOR UPDATE`.
  - **A05: Security Misconfiguration:** Cabeceras de seguridad y control de `display_errors`.
  - **A06: Vulnerable Components:** Arquitectura nativa PHP 8.2 sin dependencias externas vulnerables.
  - **A07: Identification and Authentication Failures:** Rate limiting con bloqueo temporal de 15 minutos al 6º intento fallido y mensajes genéricos que impiden enumeración.
  - **A08: Software and Data Integrity Failures:** Validación global obligatoria de tokens CSRF en todas las peticiones POST.
  - **A09: Security Logging and Monitoring Failures:** Trazabilidad completa en `registro_auditoria`.
  - **A10: Server-Side Request Forgery (SSRF):** Filtros estrictos de protocolo, tamaño y tipo de imagen en descarga de portadas.

### 2.2 Hardening del Código
- **`app/bootstrap.php`:**
  - Añadida cabecera `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- **`config/config.php`:**
  - Configurado control condicional de errores según `APP_ENV`: desactivación de `display_errors` y `display_startup_errors` en entornos de producción.
- **`app/router.php`:**
  - Reforzada la validación de extensiones en `/admin/backups/restaurar` para exigir obligatoriamente `.sql`.
- **`app/helpers/ledger.php`:**
  - Añadido bloqueo pesimista a nivel de fila de usuario (`SELECT id FROM usuarios WHERE id = ? FOR UPDATE`) en `ledger_registrar_movimiento` para garantizar aislamiento absoluto ante solicitudes concurrentes.

### 2.3 Implementación de Tests de Seguridad (T-SEC-06..15)
Se incorporaron en `tests/run.php` los 10 tests de seguridad de Fase 13:
- **`T-SEC-06`**: SQLi en catálogo y parámetros GET (`q`, `genero`) neutralizado sin alterar las consultas.
- **`T-SEC-07`**: Mitigación BOLA/IDOR: un usuario común no puede cancelar reservas activas de otro usuario.
- **`T-SEC-08`**: Path Traversal en descarga de copias de seguridad bloqueado (403/404 sin filtración de ficheros del sistema).
- **`T-SEC-09`**: Neutralización de XSS mediante escape con entidades HTML seguras vía `e()`.
- **`T-SEC-10`**: Rechazo estricto con HTTP 403 ante peticiones POST con token CSRF ausente o manipulado.
- **`T-SEC-11`**: Verificación de presencia de cabeceras HTTP de seguridad (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`).
- **`T-SEC-12`**: Verificación del rate limiting de autenticación y bloqueo tras 5 intentos fallidos.
- **`T-SEC-13`**: Imposibilidad de escalación de privilegios hacia el rol `ADMIN`.
- **`T-SEC-14`**: Imposibilidad de forzar saldos negativos de tokens y consistencia transaccional.
- **`T-SEC-15`**: Contraseñas de usuarios almacenadas con algoritmos seguros y mensajes de login que impiden enumeración.

---

## 3. Resultados de los Tests

Ejecución canónica mediante `./tests/run_all.sh`:

```text
════════ RESUMEN ════════
  PASS: 84   FAIL: 0   SKIP: 0

✔ SUITE VERDE
```

- **Fases Anteriores (Humo, Auth, NIA, Catálogo, CSV, Reservas, Mostrador, Métricas, Historial, Admin, Perf):** 74 tests pasando.
- **Fase 13 (T-SEC-06..15):** 10 tests pasando.
- **Total:** 84 tests en verde.
- **Regresiones:** 0 fallos.

---

## 4. Conclusión y Paso a Fase 14

La Fase 13 concluye con éxito, dejando la aplicación blindada y documentada en `docs/SECURITY_AUDIT.md`. Con la suite completa en 84 PASS y 0 FAIL, se procede de inmediato y sin esperar confirmación a la **Fase 14 (QA Final v4.3, Verificación Global y Cierre)**.
