# Reporte de Fase 2 — Catálogo, Búsqueda y Alta Asistida por ISBN

## 1. Resumen de Implementación
En esta Fase 2 se ha desarrollado completamente el subsistema de catálogo de libros, búsqueda con coste uniforme y alta asistida por código de barras / ISBN:
- **Helpers de Catálogo (`app/helpers/catalogo.php`)**:
  - `catalogo_listar_libros`: listado y búsqueda multicampo (título, autor, género e ISBN-13) con parámetros SQL defensivos e independientes (`:q1..:q4`) para compatibilidad estricta con sentencias preparadas nativas PDO (`PDO::ATTR_EMULATE_PREPARES => false`). Cálculo en subconsulta de copias físicas disponibles y totales, y paginación server-side.
  - `catalogo_obtener_libro` y `catalogo_obtener_por_isbn`: obtención completa de fichas de libros y consultas por ISBN-13.
  - `catalogo_listar_ejemplares` y `catalogo_obtener_ejemplar`: control de copias físicas por estado (`disponible`, `reservado`, `retirado`, `baja`), ubicación física y condición de conservación.
  - `catalogo_guardar_libro` y `catalogo_guardar_ejemplar`: operaciones de creación y actualización con validaciones y registro inmutable en auditoría (`log_accion`).
  - `catalogo_autocompletar`: buscador rápido en tiempo real.
- **Asistente Bibliográfico por cURL y Generador SVG (`app/helpers/catalogo_api.php`)**:
  - `catalogo_normalizar_isbn`: limpieza de guiones y caracteres de control.
  - `catalogo_buscar_isbn`: consulta server-side en cascada defensiva sin dependencias externas:
    1. Verificación en catálogo local (si existe, evita reconsultas externas).
    2. Google Books API (`https://www.googleapis.com/books/v1/volumes?q=isbn:...`, timeout 8s).
    3. Fallback a Open Library API (formato `bibkeys` y `search.json?isbn=...`, timeout 8s) con extracción automática de título, autor, año de publicación y carátula.
  - `catalogo_generar_svg_portada`: generador dinámico de portadas vectoriales SVG locales con paleta armónica determinista según título, autor y género para libros sin imagen o ante fallo de conectividad.
- **Vistas Públicas y Administrativas**:
  - `app/views/catalogo/index.php`: cuadrícula responsiva de libros con buscador, filtro de género, filtro de disponibilidad, coste uniforme `🪙 {coste_libro}` (1 token) y paginación.
  - `app/views/catalogo/ficha.php`: ficha técnica detallada del libro con metadatos editoriales, inventario de copias físicas, condición y botón de reserva contextual.
  - `app/views/admin/libros/index.php`: tabla de gestión de catálogo para personal y administradores.
  - `app/views/admin/libros/nuevo.php`: alta asistida con tres vías: manual, ISBN/lector USB y escáner de cámara integrado vía `html5-qrcode` CDN con degradación elegante. Creación opcional del primer ejemplar físico en un solo paso.
  - `app/views/admin/libros/editar.php`: formulario de actualización de metadatos.
  - `app/views/admin/ejemplares/index.php`, `nuevo.php`, `editar.php`: inventario de ejemplares físicos.
- **Enrutador (`app/router.php`)**:
  - Integración de `/catalogo`, `/libro` (por parámetro `?id=X`) y `/libro/{id}` (por ruta amigable).
  - Endpoint JSON `/api/isbn` y endpoint de imagen vectorial `/portada-svg`.
  - Rutas protegidas bajo RBAC para personal y administradores (`/admin/libros`, `/admin/libros/nuevo`, `/admin/libros/editar`, `/admin/ejemplares`, etc.).

## 2. Decisiones Técnicas y Correcciones
- **Compatibilidad con Prepared Statements Nativos**: Dado que `funciones.php` configura `PDO::ATTR_EMULATE_PREPARES => false`, el motor MySQL/MariaDB rechaza la reutilización del mismo nombre de parámetro (`:q`) múltiples veces en la misma sentencia. Se desacoplaron los nombres de parámetros a `:q1`, `:q2`, `:q3`, `:q4` garantizando consultas SQL robustas.
- **Resiliencia ante Cuotas Externas**: En caso de que la cuota anónima de Google Books devuelva HTTP 429 (`RESOURCE_EXHAUSTED`), el asistente conmuta fluidamente a Open Library (`search.json`), asegurando la obtención de metadatos y portadas incluso sin API keys configuradas.
- **Orden de Parámetros en Auditoría**: Se sincronizó el paso de argumentos en `catalogo_guardar_libro` y `catalogo_guardar_ejemplar` con la firma de `log_accion(?int $usuario_id, string $accion, ...)` de `auditoria.php`.

## 3. Salida Real de Tests (`./tests/run_all.sh`)
```text
════════ BookSwap · Suite de tests (v4.1) ════════
Base: http://127.0.0.1  ·  BD: bookswap@db  ·  2026-09-23 18:45:48
↺  Reiniciando BD bookswap desde database/*.sql

── T-SMOKE · Humo (activo desde la base)
  ✔ T-SMOKE-01: la home responde 200 y muestra BookSwap
  ✔ T-SMOKE-07: /health.php responde 200 con ok:true

── T-SMOKE (dependen de fases 1-6)
  ✔ T-SMOKE-02: /login responde 200 y muestra formulario
  ✔ T-SMOKE-05: /dashboard responde 200 para usuario autenticado
  ✔ T-SMOKE-06: /admin responde 200 para admin
  ✔ T-SMOKE-03: /catalogo responde 200 y muestra catálogo
  ✔ T-SMOKE-04: /libro/{id} responde 200 y muestra ficha de libro
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
  ✔ T-CAT-01: PERSONAL crea/edita libro y ejemplar
  ✔ T-CAT-02: USUARIO en CRUD de catálogo → 403
  ✔ T-BUSQ-01: Búsqueda por título y por autor encuentra resultados
  ✔ T-API-01: Alta por ISBN real → metadatos y portada (SKIP sin red)
  ✔ T-API-02: ISBN inexistente → formulario manual, sin excepción (SKIP sin red)

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
  PASS: 31   FAIL: 0   SKIP: 29

✔ SUITE VERDE
```

## 4. Estado de Definición de Hecho (DoD)
- [x] CRUD de catálogo para PERSONAL y ADMIN (creación y edición de libros y ejemplares).
- [x] Control de acceso RBAC estricto: denegación con HTTP 403 para rol USUARIO en endpoints administrativos.
- [x] Búsqueda y filtrado multicampo por título, autor, género e ISBN con coste uniforme `🪙 1`.
- [x] Alta asistida por ISBN vía cURL server-side con Google Books y Open Library.
- [x] Manejo elegante de ISBN inexistente o sin conexión con fallback al formulario manual.
- [x] Generador dinámico de portadas vectoriales SVG locales en `/portada-svg`.
- [x] Suite de tests Fase 2 en verde (31 PASS, 0 FAIL).
