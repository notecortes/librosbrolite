# Reporte de Fase 0 — Andamiaje v4.1 y Enrutamiento Central

## 1. Resumen de Implementación
En esta Fase 0 se ha establecido el andamiaje del proyecto adaptado a la especificación **v4.1**:
- **Punto de Entrada Frontal (`public/index.php`)**: Sustituida la maqueta temporal por la llamada directa al enrutador central `app/router.php`, asegurando que todas las peticiones pasen por el ciclo de vida de la aplicación.
- **Inicialización Central (`app/bootstrap.php`)**:
  - Emisión de cabeceras de seguridad HTTP (`X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN`).
  - Configuración e inicio seguro de sesión (`session.cookie_httponly = 1`, `session.cookie_samesite = Lax`, `session.use_only_cookies = 1`).
  - Carga modular defensiva de helpers (`funciones.php`, `centro.php`, y detección automática condicional de helpers de fases futuras).
  - Carga y fusión de la configuración desde la base de datos (`config_cargar`).
  - Detección de sesión de usuario y cálculo directo del saldo de tokens a través del ledger inmutable (`SUM(cantidad)` de `movimientos_tokens`).
  - Conteo de notificaciones no leídas para la campana de navegación y generación de token CSRF.
- **Enrutador (`app/router.php`)**:
  - Adaptación de la ruta raíz `/` para consultar datos reales de la base de datos sin referencias a campos obsoletos (`nivel_valor`), obteniendo ejemplares disponibles, transacciones entregadas y libros admitidos.
- **Vista Principal (`app/views/home.php`)**:
  - Creada la vista de portada respetando el sistema de diseño v4.1.
  - Eliminación total de clases `chip-nivel-*`.
  - Muestra del coste uniforme de libros `🪙 {coste_libro}` (1 token).
  - Bloque explicativo de 3 pasos (Deposita, Reserva online, Recoge en mostrador) y llamada a la acción para visitar el punto físico.
- **Página de Error 404 (`app/views/404.php`)**: Creada para un manejo visual limpio de rutas no encontradas.

## 2. Decisiones Técnicas
- **Carga modular de helpers en bootstrap**: En lugar de requerir incondicionalmente archivos que se desarrollarán en fases posteriores (como `auth.php`, `catalogo.php`, etc.), se realiza una inclusión comprobada mediante `file_exists()`. Esto permite avanzar fase a fase sin generar errores fatales.
- **Saldo del usuario**: Se alinea estrictamente con el modelo de negocio v4.1 donde el saldo se deriva únicamente de la suma inmutable del ledger (`movimientos_tokens`), dado que las reservas en v4.1 no retiran tokens de forma anticipada.

## 3. Salida Real de Tests (`./tests/run_all.sh`)
```text
════════ BookSwap · Suite de tests (v4.1) ════════
Base: http://127.0.0.1  ·  BD: bookswap@db  ·  2026-09-23 18:26:37
↺  Reiniciando BD bookswap desde database/*.sql

── T-SMOKE · Humo (activo desde la base)
  ✔ T-SMOKE-01: la home responde 200 y muestra BookSwap
  ✔ T-SMOKE-07: /health.php responde 200 con ok:true

── T-SMOKE (dependen de fases 1-6)
  · T-SMOKE-02  [pendiente: Fase 1]
  · T-SMOKE-05  [pendiente: Fase 1]
  · T-SMOKE-06  [pendiente: Fase 1]
  · T-SMOKE-03  [pendiente: Fase 2]
  · T-SMOKE-04  [pendiente: Fase 2]
  · T-SMOKE-08  [pendiente: Fase 6]

── Fase 1 — Auth + número de socio + Google
  · T-AUTH-01  [pendiente: Fase 1]
  · T-AUTH-02  [pendiente: Fase 1]
  · T-AUTH-03  [pendiente: Fase 1]
  · T-AUTH-04  [pendiente: Fase 1]
  · T-GOOG-01  [pendiente: Fase 1]
  · T-GOOG-02  [pendiente: Fase 1]
  · T-GOOG-03  [pendiente: Fase 1]
  · T-GOOG-04  [pendiente: Fase 1]
  · T-GOOG-05  [pendiente: Fase 1]
  · T-SOCIO-01  [pendiente: Fase 1]
  · T-SOCIO-02  [pendiente: Fase 1]
  · T-SOCIO-03  [pendiente: Fase 1]
  · T-SOCIO-04  [pendiente: Fase 1]
  · T-SEC-01  [pendiente: Fase 1]
  · T-SEC-02  [pendiente: Fase 1]
  · T-SEC-03  [pendiente: Fase 1]
  · T-SEC-04  [pendiente: Fase 1]
  · T-SEC-05  [pendiente: Fase 1]
  · T-PRIV-01  [pendiente: Fase 1]

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
  PASS: 2   FAIL: 0   SKIP: 58

✔ SUITE VERDE
```

## 4. Estado de Definición de Hecho (DoD)
- [x] Router completo (sesión, $config, usuario+rol, notificaciones, CSRF, flash).
- [x] Home actualizada (coste uniforme, sin chips de nivel).
- [x] `/health.php` OK.
- [x] `./tests/run_all.sh` → T-SMOKE-01 y T-SMOKE-07 verdes, 0 FAIL.
