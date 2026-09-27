# Reporte de Fase 1 — Autenticación, Número de Socio, Google OAuth, RBAC y Seguridad

## 1. Resumen de Implementación
En esta Fase 1 se ha desarrollado e integrado todo el subsistema de identidad, acceso y seguridad conforme a la especificación **v4.1**:
- **Helpers de Autenticación y RBAC (`app/helpers/auth.php`)**:
  - `auth_hashear_password` y `auth_verificar_password` utilizando `password_hash` con algoritmo `PASSWORD_DEFAULT`.
  - Protección ante fuerza bruta con `auth_registrar_intento_fallido` y `auth_esta_bloqueado`: bloqueo de 15 minutos al alcanzar el 5º intento fallido usando la tabla `intentos_login`.
  - `auth_iniciar_sesion`: regeneración segura de ID de sesión (`session_regenerate_id(true)`) para mitigar fijación de sesión y establecimiento de variables de sesión (`usuario_id`, `email`, `nombre`, `rol`, etc.).
  - `usuario_tiene_permiso`, `exigir_permiso`, `exigir_rol` y `exigir_autenticado`: validaciones de permisos RBAC para control de acceso server-side.
- **Auditoría (`app/helpers/auditoria.php`)**:
  - `log_accion`: registro detallado de eventos de seguridad y negocio en la tabla `registro_auditoria` con IP, User-Agent, usuario_id y detalles JSON.
- **Autenticación con Google OAuth (`app/helpers/auth_google.php`)**:
  - `google_generar_url_auth`: generación de URL hacia `accounts.google.com` con `state` criptográfico almacenado en sesión (`CSRF`).
  - `auth_google_procesar`: vinculación automática de cuentas. Si existe una cuenta local previa con el mismo email, se actualiza a proveedor `'ambos'` conservando el `id`. Si es nueva, se crea cuenta `'google'` con `password_hash = NULL`.
- **Gestión de Socios (`app/helpers/socio.php`)**:
  - `socio_obtener_numero` y `socio_esta_activado`: consulta del estado de activación del socio.
  - `socio_activar_cuenta`: asignación atómica de número de socio desde el pool preautorizado (`socios_pool`), verificación de duplicidad, asignación de bono de bienvenida (`bono_bienvenida` tokens) mediante transacción e inserción en el ledger inmutable `movimientos_tokens`, y registro de auditoría.
- **Vistas Desarrolladas**:
  - `app/views/auth/login.php`: formulario local y botón de Google condicional (`google_client_id`).
  - `app/views/auth/registro.php`: registro por email y contraseña.
  - `app/views/auth/activar.php`: pantalla de activación de número de socio `SOC-XXXX`.
  - `app/views/auth/privacidad.php`: política de privacidad conforme a `T-PRIV-01`, mencionando los datos tratados y el contacto del centro.
  - `app/views/usuario/dashboard.php`: panel de usuario con saldo de tokens, badge de socio y enlaces rápidos.
  - `app/views/admin/panel.php`: panel de administración con estadísticas básicas.
- **Middleware y Enrutador (`app/router.php`)**:
  - Middleware de activación forzada: cualquier usuario con rol `USUARIO` no activado es redirigido a `/activar` ante cualquier ruta (salvo `/activar` y `/logout`).
  - Rutas integradas: `/login`, `/registro`, `/activar`, `/logout`, `/auth/google`, `/auth/google/callback`, `/dashboard`, `/privacidad`, `/admin`.

## 2. Decisiones Técnicas
- **Mitigación de XSS**: Escapado estricto mediante `e()` en todas las vistas de títulos y contenidos dinámicos.
- **Economía y Ledger**: El bono de bienvenida solo se inserta en `movimientos_tokens` si `bono_bienvenida > 0`, manteniendo el historial fiel e íntegro.
- **Cuentas Híbridas**: Los usuarios registrados inicialmente con contraseña pueden autenticarse posteriormente mediante Google con el mismo email pasando a proveedor `'ambos'`, mientras que las cuentas originadas en Google tienen login local denegado si no tienen `password_hash`.

## 3. Salida Real de Tests (`./tests/run_all.sh`)
```text
════════ BookSwap · Suite de tests (v4.1) ════════
Base: http://127.0.0.1  ·  BD: bookswap@db  ·  2026-09-23 18:33:35
↺  Reiniciando BD bookswap desde database/*.sql

── T-SMOKE · Humo (activo desde la base)
  ✔ T-SMOKE-01: la home responde 200 y muestra BookSwap
  ✔ T-SMOKE-07: /health.php responde 200 con ok:true

── T-SMOKE (dependen de fases 1-6)
  ✔ T-SMOKE-02: /login responde 200 y muestra formulario
  ✔ T-SMOKE-05: /dashboard responde 200 para usuario autenticado
  ✔ T-SMOKE-06: /admin responde 200 para admin
  · T-SMOKE-03  [pendiente: Fase 2]
  · T-SMOKE-04  [pendiente: Fase 2]
  · T-SMOKE-08  [pendiente: Fase 6]

── Fase 1 — Auth + número de socio + Google
  ✔ T-AUTH-01: Registro con email crea cuenta pendiente de activación
  ✔ T-AUTH-02: Login correcto/incorrecto; session_regenerate_id
  ✔ T-AUTH-03: 6º intento fallido → bloqueo 15 min
  ✔ T-AUTH-04: Contraseñas con password_hash; nada en texto plano
  ✔ T-GOOG-01: Sin client_id: botón ausente y /auth/google no redirige a Google
  ✔ T-GOOG-02: Con config: /auth/google → 302 a accounts.google.com con state
  ✔ T-GOOG-03: Callback con state inválido → 403, sin sesión, auditado
  ✔ T-GOOG-04: auth_google_procesar() con email local existente → mismo user_id, 'ambos'
  ✔ T-GOOG-05: Email nuevo → cuenta google-only; login local denegado con mensaje
  ✔ T-SOCIO-01: USUARIO pendiente → cualquier página redirige a /activar
  ✔ T-SOCIO-02: Número válido y libre → vinculado, activado, bono_bienvenida si >0
  ✔ T-SOCIO-03: Número inexistente o ya asignado → rechazo claro, sigue pendiente
  ✔ T-SOCIO-04: Sin activar no puede reservar (denegado server-side)
  ✔ T-SEC-01: SQLi en login (' OR 1=1--) no autentica ni rompe
  ✔ T-SEC-02: USUARIO en endpoint admin → 403 server-side
  ✔ T-SEC-03: POST sin CSRF → rechazado
  ✔ T-SEC-04: XSS: <script> en título se muestra escapado
  ✔ T-SEC-05: /backups/ inaccesible desde fuera (403/404)
  ✔ T-PRIV-01: /privacidad → 200, menciona datos y contacto del centro

── Fase 2 — Catálogo, búsqueda y alta por ISBN
  · T-CAT-01  [pendiente: Fase 2]
  · T-CAT-02  [pendiente: Fase 2]
  · T-BUSQ-01  [pendiente: Fase 2]
  · T-API-01  [pendiente: Fase 2]
  · T-API-02  [pendiente: Fase 2]

── Fase 3 — CSV y pool de socios
  · T-CSV-01  [pendiente: Fase 3]
  · T-CSV-02  [pendiente: Fase 3]
  · T-CSV-03  [pendiente: Fase 3]
  · T-CSV-04  [pendiente: Fase 3]

── Fase 4 — Reservas
  · T-RESV-01  [pendiente: Fase 4]
  · T-RESV-02  [pendiente: Fase 4]
  · T-RESV-03  [pendiente: Fase 4]
  · T-RESV-04  [pendiente: Fase 4]
  · T-RESV-05  [pendiente: Fase 4]

── Fase 5 — Mostrador y economía
  · T-ENTR-01  [pendiente: Fase 5]
  · T-ENTR-02  [pendiente: Fase 5]
  · T-ENTR-03  [pendiente: Fase 5]
  · T-ENTR-04  [pendiente: Fase 5]
  · T-DEPO-01  [pendiente: Fase 5]
  · T-DEPO-02  [pendiente: Fase 5]
  · T-LEDGER-01  [pendiente: Fase 5]
  · T-LEDGER-02  [pendiente: Fase 5]
  · T-MOST-01  [pendiente: Fase 5]
  · T-AUDIT-01  [pendiente: Fase 5]

── Fase 6 — Config, métricas, backups, visitanos
  · T-VISIT-01  [pendiente: Fase 6]
  · T-VISIT-02  [pendiente: Fase 6]
  · T-AUDIT-02  [pendiente: Fase 6]
  · T-BAK-01  [pendiente: Fase 6]
  · T-BAK-02  [pendiente: Fase 6]
  · T-BAK-03  [pendiente: Fase 6]
  · T-MET-01  [pendiente: Fase 6]

── Regresión (se activan desde Fase 3)
  · T-RGRC-01  [pendiente: Fase 3]
  · T-RGRC-02  [pendiente: Fase 4]

════════ RESUMEN ════════
  PASS: 24   FAIL: 0   SKIP: 36

✔ SUITE VERDE
```

## 4. Estado de Definición de Hecho (DoD)
- [x] Registro y Login local con contraseñas seguras (`password_hash`).
- [x] Protección contra fuerza bruta (bloqueo tras 5 intentos fallidos).
- [x] Flujo de activación de socio con validación contra `socios_pool` y asignación de bono.
- [x] Integración defensiva de Google OAuth con soporte de cuentas híbridas y control CSRF (`state`).
- [x] Control de acceso RBAC y bloqueo de acceso no autorizado server-side.
- [x] Suite de tests Fase 1 en verde (24 PASS, 0 FAIL).
