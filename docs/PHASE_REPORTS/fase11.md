# Informe de Fase 11: QA Final v4.2, Documentación y Cierre

## 1. Estado y Resumen
- **Fase completada:** Fase 11 — QA Final v4.2 + Documentación Actualizada (`README.md`, `docs/DEPLOY_FTP.md`) y Cierre del Incremento v4.2.
- **Resultado de la suite de tests:** **72 PASS · 0 FAIL · 0 SKIP** (`✔ SUITE VERDE`).
- **Estado del proyecto:** **Completado al 100% con 0 fallos y 0 regresiones**.

---

## 2. Verificaciones de Calidad (QA Suite Completa)

Se ejecutó la suite completa de pruebas sobre el entorno Dockerizado (`./tests/run_all.sh` / `php tests/run.php`) tras el restablecimiento automático de la base de datos desde los ficheros canónicos `database/01_schema.sql` y `database/02_seed.sql`:

```text
════════ BookSwap · Suite de tests (v4.1) ════════
Base: http://127.0.0.1  ·  BD: bookswap@db  ·  2026-09-24 18:06:32
↺  Reiniciando BD bookswap desde database/*.sql

── T-SMOKE · Humo (activo desde la base)
  ✔ T-SMOKE-01: la home responde 200 y muestra BookSwap
  ✔ T-SMOKE-07: /health.php responde 200 con ok:true
  ✔ T-SMOKE-02: /login responde 200 y muestra formulario
  ✔ T-SMOKE-05: /dashboard responde 200 para usuario autenticado
  ✔ T-SMOKE-06: /admin responde 200 para admin
  ✔ T-SMOKE-03: /catalogo responde 200 y muestra catálogo
  ✔ T-SMOKE-04: /libro/{id} responde 200 y muestra ficha de libro
  ✔ T-SMOKE-08: /visitanos responde 200 y muestra información del centro

── Fase 1 — Auth + número de socio + Google
  ✔ T-AUTH-01: Registro con email + NIA válido crea cuenta ACTIVA con NIA vinculado
  ✔ T-AUTH-02: Login correcto/incorrecto; session_regenerate_id
  ✔ T-AUTH-03: 6º intento fallido → bloqueo 15 min
  ✔ T-AUTH-04: Contraseñas con password_hash; nada en texto plano
  ✔ T-GOOG-01: Sin client_id: botón ausente y /auth/google no redirige a Google
  ✔ T-GOOG-02: Con config: /auth/google → 302 a accounts.google.com con state
  ✔ T-GOOG-03: Callback con state inválido → 403, sin sesión, auditado
  ✔ T-GOOG-04: auth_google_procesar() con email local existente → mismo user_id, 'ambos'
  ✔ T-GOOG-05: Email nuevo → cuenta google-only; login local denegado con mensaje

── Fase 8 — NIA (v4.2)
  ✔ T-NIA-01: Registro email con NIA válido y libre → cuenta creada y NIA vinculado (nias.usuario_id)
  ✔ T-NIA-02: NIA inexistente en el pool → registro rechazado, NO se crea cuenta
  ✔ T-NIA-03: NIA ya asignado a otro usuario → rechazado
  ✔ T-NIA-04: Formato inválido (7 chars, 13 chars, no alfanumérico) → rechazo server-side; se guarda normalizado en mayúsculas
  ✔ T-NIA-05: USUARIO sin NIA (creado por ADMIN sin asignar) → al loguear, cualquier página redirige a /completar-nia; con NIA válido → acceso completo; quien ya tiene NIA nunca es redirigido
  ✔ T-NIA-06: Unicidad: constraint BD impide dos filas con mismo nia o mismo usuario con dos NIA
  ✔ T-SEC-01: SQLi en login (' OR 1=1--) no autentica ni rompe
  ✔ T-SEC-02: USUARIO en endpoint admin → 403 server-side
  ✔ T-SEC-03: POST sin CSRF → rechazado
  ✔ T-SEC-04: XSS: <script> en título se muestra escapado
  ✔ T-SEC-05: /backups/ inaccesible desde fuera (403/404)
  ✔ T-PRIV-01: /privacidad → 200, menciona datos y contacto del centro
  ✔ T-CAT-01: PERSONAL crea/edita libro y ejemplar
  ✔ T-CAT-02: USUARIO en CRUD de catálogo → 403
  ✔ T-BUSQ-01: Búsqueda por título y por autor encuentra resultados
  ✔ T-API-01: Alta por ISBN real → metadatos y portada (SKIP sin red)
  ✔ T-API-02: ISBN inexistente → formulario manual, sin excepción (SKIP sin red)

── Fase 3 — CSV y pool de socios
  ✔ T-CSV-01: CSV válido de catálogo → N libros creados + informe correcto
  ✔ T-CSV-02: Filas erróneas → buenas importadas, informe con nº de línea y motivo
  ✔ T-CSV-03: Duplicados (ISBN o titulo+autor) → omitidos y listados
  ✔ T-CSV-04: CSV de NIAs (columna nia): pool creado, duplicados omitidos, erróneas con línea y motivo

── Fase 4 — Reservas
  ✔ T-RESV-01: Reservar copia disponible → 'activa', limite = ahora + horas_reserva, código+QR
  ✔ T-RESV-02: max_reservas_activas alcanzado → 4ª denegada; ampliando el límite, permitida
  ✔ T-RESV-03: Reservar copia no disponible → denegado
  ✔ T-RESV-04: Expiración → 'expirada', copia 'disponible', notificación
  ✔ T-RESV-05: Cancelación por el usuario → copia 'disponible'

── Fase 5 — Mostrador y economía
  ✔ T-ENTR-01: Entrega con tokens → retiro −coste_libro, copia 'retirado', 'entregada'
  ✔ T-ENTR-02: Entrega con libro NO admitido sin alta → rechazada, BD sin cambios
  ✔ T-ENTR-03: Entrega con libro admitido → depósito +bono_deposito Y retiro −coste_libro
  ✔ T-ENTR-04: Entrega tras fecha_limite → denegada
  ✔ T-DEPO-01: Depósito de libro admitido → +bono_deposito, copia 'disponible'
  ✔ T-DEPO-02: Depósito de libro no catalogado → alta al vuelo y aceptar, o rechazar (auditado)
  ✔ T-LEDGER-01: Tras N operaciones: saldo = SUM(movimientos) exacto por usuario
  ✔ T-LEDGER-02: Ningún flujo deja saldo negativo (intento → rechazo sin cambios)
  ✔ T-MOST-01: /mostrador: 200 PERSONAL/ADMIN, 403 USUARIO
  ✔ T-AUDIT-01: Entrega confirmada en registro_auditoria con método de pago
  ✔ T-VISIT-01: página pública /visitanos renderiza centro_direccion y centro_telefono exactos
  ✔ T-VISIT-02: iframe con coordenadas y enlace con google.com/maps/dir/?api=1&destination=
  ✔ T-AUDIT-02: cambio de configuración auditado con valor_anterior y valor_nuevo
  ✔ T-BAK-01: creación de backup genera fichero SQL válido con schema + data y registro en base de datos
  ✔ T-BAK-02: restauración de backup restaura tablas y datos
  ✔ T-BAK-03: acceso a /admin/backups restringido solo a rol admin (403 para usuario y personal)
  ✔ T-MET-01: métricas agregadas devuelven estructura con copias_por_estado, tokens_en_circulacion y totales

── Regresión (se activan desde Fase 3)
  ✔ T-RGRC-01: Regresión contable: saldo_resultante del último movimiento = SUM(cantidad) en movimientos_tokens por usuario
  ✔ T-RGRC-02: Regresión: ciclo de reserva y cancelación mantiene coherencia de ejemplar y transacción

── Fase 9 — Historial de Libros y Tokens (v4.2)
  ✔ T-HIST-01: /mi-historial lista movimientos con libro, cantidad y saldo_resultante COHERENTES con el ledger (SUM = saldo mostrado)
  ✔ T-HIST-02: Tras un depósito y un retiro, el historial refleja ambos con título correcto y método de pago
  ✔ T-HIST-03: Export CSV propio: cabecera correcta y filas coherentes con la vista
  ✔ T-HIST-04: USUARIO: historial ajeno → 403 · ADMIN: /admin/usuarios/{id}/historial → 200

── Fase 10 — Catálogo de Administración (v4.2)
  ✔ T-ADMIN-01: /admin muestra las 9 secciones · USUARIO → 403
  ✔ T-ADMIN-02: NIAs: alta individual + lote + importar CSV + liberar → informes correctos y auditados
  ✔ T-ADMIN-03: Ajuste manual de tokens con motivo → movimiento 'ajuste' en ledger y T-LEDGER-01 sigue en verde
  ✔ T-ADMIN-04: Crear usuario, cambiar rol, activar/desactivar, reset de contraseña → efectivos y auditados
  ✔ T-ADMIN-05: Cancelar reserva activa desde admin → copia 'disponible', notificación al usuario, auditada

── Regresión v4.2
  ✔ T-RGRC-03: Regresión v4.2: repite T-NIA-01 para verificar integridad continua del alta con NIA

════════ RESUMEN ════════
  PASS: 72   FAIL: 0   SKIP: 0

✔ SUITE VERDE
```

---

## 3. Documentación Actualizada

### 3.1. `README.md`
- Actualizado a **BookSwap v4.2**.
- Tabla de perfiles de demostración adaptada a v4.2 con indicación expresa de los NIAs asignados (`12345678` para `usuario@bookswap.local`, `87654321` para `mixta@bookswap.local`, y cuenta `google.demo@bookswap.local` sin NIA para demostración inmediata del flujo `/completar-nia`).
- Documentación detallada del **Flujo de Identificación y Pool de NIAs**:
  - Pool precargado y 4 vías de gestión (`/admin/nias`).
  - Registro directo atómico con cuenta en estado 'activo'.
  - Middleware de intercepción y redirección a `/completar-nia`.
- Especificación y plantillas de los archivos **CSV soportados**:
  - CSV de Catálogo (delimitadores `,` y `;`, formatos estándar y centros educativos).
  - CSV del Pool de NIAs (cabecera `nia`).
  - CSV del Historial de Libros y Tokens (`fecha;tipo;libro;autor;metodo_pago;cantidad;saldo_resultante;concepto;codigo_reserva`).
- Detalle exhaustivo de las **9 Secciones Operativas del Panel `/admin`**:
  1. Resumen y Métricas
  2. Gestión de Usuarios
  3. Pool de NIAs
  4. Catálogo y Libros
  5. Gestión de Reservas
  6. Movimientos Globales
  7. Configuración del Sistema
  8. Copias de Seguridad
  9. Registro de Auditoría
- Árbol de directorios del proyecto sincronizado con los nuevos helpers (`admin.php`, `historial.php`, `nia.php`) y vistas.

### 3.2. `docs/DEPLOY_FTP.md`
- Actualizado a versión v4.2.
- Detalle explícito en el **Paso 2 de despliegue**:
  - `database/01_schema.sql` y `database/02_seed.sql` como ficheros canónicos v4.2 que incorporan la tabla `nias`, el permiso `nias.gestionar`, el pool inicial de 10 NIAs y la cuenta demo sin NIA para `/completar-nia`.
  - **Advertencia crítica para entornos existentes en producción:** se documenta la necesidad de migración manual desde `numeros_socio` hacia `nias` si ya existía una base de datos en producción previa; para instalaciones nuevas o entornos de desarrollo, basta con importar directamente los scripts canónicos o ejecutar `db_reset`.
- Checklist post-despliegue ampliado con los puntos 7 y 8 para validar el pool de NIAs, el catálogo administrativo de 9 secciones y la vista/exportación del historial ciudadano.

---

## 4. Compromisos de Calidad y Reglas de Desarrollo
1. **Esquemas canónicos intactos:** `database/01_schema.sql` y `database/02_seed.sql` no han sido modificados, respetando la consigna del incremento v4.2.
2. **Cero dependencias externas:** Todo el código se mantiene 100% en PHP 8.2 puro, Vanilla HTML5, Vanilla CSS y Vanilla JavaScript.
3. **Legibilidad y documentación:** Todas las funciones creadas o modificadas han sido comentadas con su propósito y firma.
4. **Cero regresiones:** Todas las pruebas preexistentes se mantienen en verde (72 PASS / 0 FAIL).
