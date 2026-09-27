Perfecto, recuperamos el **escáner ISBN + APIs bibliográficas** como vía de alta de libros (junto a la manual y el CSV). Cambios respecto a la v4: el escáner y las APIs vuelven en §2, §8 y Fase 2; tests `T-API-01/02` restaurados; y los `database/*.sql` pasan a ser canónicos (te los entrego ya hechos, el agente no los regenera).

---

# PROMPT MAESTRO v4.1

```markdown
# PROYECTO: "BookSwap" — Web de intercambio de libros con punto físico
# PROMPT MAESTRO v4.1 (para agente Gemini en Antigravity, con ejecución y auto-test)

## 0. ROL Y MODO DE TRABAJO DEL AGENTE (OBLIGATORIO)
Actúa como un equipo senior: backend PHP, frontend UI, DevOps y QA. Tienes acceso a
terminal y la usarás de verdad: levantarás Docker, ejecutarás la suite de tests,
leerás los errores y corregirás el código hasta que TODOS los tests pasen.

Reglas de trabajo:
1. Ejecuta las fases en orden (Fase 0 → 7). No avances hasta que su "Definición de
   Hecho" esté al 100%.
2. Tras cada fase: ./tests/run_all.sh → corrige hasta 0 fallos → escribe
   docs/PHASE_REPORTS/faseN.md (implementación, decisiones, salida REAL de tests)
   y actualiza PROGRESS.md.
3. REGRESIÓN = FALLO: si un cambio rompe tests anteriores, la fase no está hecha.
   No edites nunca un test para que pase: arregla el código.
4. Escribe SIEMPRE archivos completos (nunca "... resto del código igual").
5. Los tests que dependan de internet (Google Books/Open Library, OAuth Google real)
   se marcan SKIP si no hay red; el resto deben pasar siempre offline.
6. La base del proyecto está VERIFICADA. NO regeneres: docker-compose.yml, Dockerfile,
   config/config.php, health.php, assets/css/theme.css, assets/js/app.js,
   app/views/layouts/base.php, app/views/partials/footer.php, app/helpers/*,
   database/01_schema.sql ni database/02_seed.sql (v4.1, CANÓNICOS: puedes AÑADIR
   tablas/columnas si una fase lo exige, manteniendo nombres existentes; toda clave
   de configuracion nueva va en 02_seed.sql). En Fase 0 ACTUALIZA tests/run.php:
   elimina los pending() de módulos eliminados en v4 (T-VALOR-*, T-WISH-*,
   T-INAC-*, T-BUSQ-02, T-DEPO-03/04, T-RGRC-03) y añade los de §11 (T-SOCIO-*,
   T-CSV-*, T-ENTR-*), manteniendo T-API-01/02.
7. Solo pregunta si algo es imposible. Ante ambigüedad: decide tú, documenta la
   decisión en el reporte de fase y sigue.

## 1. CONTEXTO DE NEGOCIO (MODELO v4.1)
Intercambio de libros en UN PUNTO FÍSICO. Reglas:
- El catálogo es una LISTA CERRADA de libros admitidos. Entran por TRES vías:
  (a) IMPORTACIÓN CSV, (b) ALTA MANUAL del personal, (c) ALTA ASISTIDA POR ISBN:
  escáner de cámara (html5-qrcode) o tecleo/lector USB → el sistema consulta
  Google Books y Open Library para rellenar título, autor, editorial, año y
  portada automáticamente. No hay más fuentes externas.
- TODOS los libros valen lo MISMO: coste_libro (config, default 1) para recoger,
  bono_deposito (config, default 1) por entregar un libro admitido en buen estado.
- El usuario se registra con email (o Gmail vía Google, opcional) y en su PRIMER
  LOGIN debe introducir su NÚMERO DE SOCIO, único y pre-cargado por la
  organización. Sin número no puede reservar.
- El usuario RESERVA un libro disponible por un tiempo configurable
  (horas_reserva). Lo recoge EN LAS INSTALACIONES dentro del plazo, pagando con
  sus TOKENS o TRAYENDO un libro admitido (swap: el personal registra depósito y
  retiro; con los valores por defecto el neto es 0).
- Los tokens NO caducan y no se compran con dinero. Ledger inmutable.

## 2. STACK Y RESTRICCIONES DURAS
Producción: hosting compartido PHP 8.0+ / MySQL / Apache, subida por FTP.
- Backend: PHP plano + PDO. PROHIBIDO: Composer, npm, frameworks,
  exec/shell_exec/proc_open/passthru, dependencia de cron del hosting (pseudo-cron).
- OAuth Google y APIs bibliográficas con cURL + json, SIN librerías.
- Frontend: HTML5 + CSS + JS vanilla (ES6). Librerías SOLO por CDN: Bootstrap 5.3,
  Bootstrap Icons, html5-qrcode (escáner), qrcode.js (QR de reserva), Chart.js,
  Google Fonts. Degradación elegante sin CDN.
- APIs bibliográficas (sin API key, sin registro): 1º Google Books
  (https://www.googleapis.com/books/v1/volumes?q=isbn:XXXX) → fallback Open Library
  (https://openlibrary.org/api/books?bibkeys=ISBN:XXXX&format=json&jscmd=data).
  Consulta SIEMPRE server-side con cURL (timeout 8s, sin excepción si falla).
  Portada desde thumbnail/covers; si ambas fallan o no hay red → formulario manual
  y portada SVG placeholder local. Si el ISBN ya existe en libros, NO reconsultar.
- ESCÁNER: html5-qrcode con getUserMedia (EAN-13). Requiere contexto seguro
  (localhost vale; en LAN móvil haría HTTPS). El escáner convive SIEMPRE con
  (a) entrada manual de ISBN y (b) lector USB (escribe como teclado en un input
  enfocado). Documenta la limitación en el README.
- Seguridad en servidor SIEMPRE: sentencias preparadas, password_hash/verify, CSRF
  en todo POST, permisos por endpoint, htmlspecialchars en toda salida, cookies
  httponly+samesite, session_regenerate_id en cada login, rate-limit (5 → 15 min),
  uploads validados (mime+ext+tamaño, nombres aleatorios), cabeceras
  X-Frame-Options/X-Content-Type-Options/Referrer-Policy.
- Idioma: UI y comentarios en español. BD UTF8MB4.

## 3. ENTORNO DOCKER (YA VERIFICADO — conservar)
app php:8.2-apache (8080, DocumentRoot public/, UID/GID del host vía build args),
db mariadb:10.11 (inicializa database/*.sql automáticamente), phpmyadmin (8081),
healthchecks con --wait, entrypoint prepara backups/ y uploads/ escribibles.
Google OAuth en dev: redirect URI http://localhost:8080/auth/google/callback.

## 4. SISTEMA DE DISEÑO (REQUISITO DE ACEPTACIÓN)
- Usa el tema canónico (theme.css + app.js + layout base + footer global). Mantén:
  dark mode, toasts, skeleton loaders, empty states, micro-interacciones,
  contador de tokens animado, confeti al confirmar una entrega, accesibilidad AA,
  responsive 360/768/1280.
- CAMBIO v4: las clases chip-nivel-* DEJAN de usarse; las cards muestran coste
  uniforme "🪙 {coste_libro}" leído de configuración.
- Páginas que deben lucir: login (split-screen + botón Google), dashboard del
  usuario (saldo grande, reservas activas con cuenta atrás), catálogo (grid de
  portadas), MODO MOSTRADOR (botones ≥48px, input gigante autoenfocado, tablet),
  alta de libro con ISBN (escáner integrado, prefill del formulario) y VISÍTANOS
  (mapa, horario, abierto-ahora). FOOTER GLOBAL: conservar.

## 5. ECONOMÍA: TOKENS Y OPERATIVA
### 5.1 Ledger
movimientos_tokens inmutable: (id, usuario_id, cantidad con signo, tipo
[deposito|retiro|bono|bono_bienvenida|ajuste], transaccion_id NULL, concepto,
saldo_resultante, fecha). Saldo SIEMPRE = SUM(ledger) (test de integridad).
Jamás permitir operaciones que dejen el saldo negativo.
### 5.2 Precios (configuracion, editables por ADMIN, cambios auditados)
coste_libro=1, bono_deposito=1, bono_bienvenida=0 (tokens al activarse).
### 5.3 Reserva y recogida
- Reservar: copia 'disponible' → 'reservado'; transaccion tipo 'reserva' estado
  'activa', fecha_limite = ahora + horas_reserva, código único (+QR). Reservar NO
  cuesta tokens. Límite: max_reservas_activas reservas activas simultáneas (config).
- Recogida EN MOSTRADOR dentro del plazo: PERSONAL introduce el código → elige:
  a) TOKENS: movimiento 'retiro' de coste_libro.
  b) LIBRO: PERSONAL localiza el libro aportado (búsqueda o ISBN) o lo da de alta
     al vuelo (manual o escáner+API) → registra DEPÓSITO (+bono_deposito, copia
     'disponible') y RETIRO (−coste_libro): dos movimientos del ledger.
  → copia reservada 'retirado', transacción 'entregada'.
- Fuera de plazo o código inexistente → denegado. Expiración: bin/expirar.php
  (cron o pseudo-cron al primer login del día) marca reservas vencidas 'expirada',
  devuelve la copia a 'disponible' y notifica.
- El usuario puede CANCELAR su reserva activa (copia 'disponible').
### 5.4 Depósitos en mostrador
PERSONAL busca el libro por título/ISBN/escáner → verifica estado físico →
registra depósito: copia 'disponible' + movimiento 'deposito' (+bono_deposito).
Si el libro NO está en el catálogo: alta al vuelo (manual o escáner+API) y
aceptarlo, o rechazarlo. Ambas acciones auditadas.

## 6. LOGIN CON GOOGLE (OPCIONAL, PHP PURO, SIN LIBRERÍAS)
- Config: google_client_id, google_client_secret, google_redirect_uri. Si
  client_id vacío → botón NO se renderiza y /auth/google redirige a /login con
  aviso. La app es 100% funcional sin Google.
- Flujo: /auth/google (state+nonce en sesión → 302 a accounts.google.com) →
  /auth/google/callback (valida state con hash_equals → token por cURL POST →
  userinfo por cURL GET → SOLO email_verified=true → auth_google_procesar()).
- auth_google_procesar(sub, email, nombre, foto): por google_sub → por email
  (vincula, 'ambos') → alta nueva (google-only, password NULL).
- Cuentas google-only: login local denegado con mensaje; pueden establecer
  contraseña desde su perfil. "Desvincular Google" solo si hay contraseña.
- Tras cualquier login: pasar por el control de activación (§7). Página pública
  /privacidad (Google la exige en el consent screen).

## 7. NÚMERO DE SOCIO (ACTIVACIÓN)
- Tabla numeros_socio (numero UNIQUE, usuario_id UNIQUE NULL). El ADMIN/PERSONAL
  alimenta el pool: alta manual o importación CSV (columna numero), y puede
  asignar un número libre a un usuario desde el panel.
- El control de activación aplica a cuentas con rol USUARIO: sin fila en
  numeros_socio → "pendiente de activación"; tras login se le REDIRIGE SIEMPRE a
  /activar (no puede navegar a nada más). ADMIN y PERSONAL operan sin número.
- /activar: un campo. Validaciones server-side: existe en el pool, sin asignar →
  se vincula, se aplica bono_bienvenida si >0, auditado. Número inexistente/usado
  → error claro ("contacta con el personal").
- El número se muestra en el perfil y en el panel del usuario.

## 8. MÓDULOS FUNCIONALES
1. AUTH: registro email, login local (rate-limit), Google opcional (§6),
   activación (§7), perfil (contraseña, número, vincular/desvincular Google),
   /privacidad.
2. CATÁLOGO (lista cerrada): libros (isbn13 NULL UNIQUE, titulo, autor, editorial,
   anio, genero, idioma, portada_url NULL, observaciones) + ejemplares (estado
   [disponible|reservado|retirado|baja], ubicacion, condicion, fecha_ingreso,
   depositante_id). CRUD PERSONAL/ADMIN con TRES vías de alta:
   (a) formulario manual completo; (b) ISBN tecleado o lector USB → botón
   "Buscar datos" → servidor consulta APIs → formulario precargado editable;
   (c) escáner de cámara (html5-qrcode) que rellena el campo ISBN y dispara (b).
   Ficha pública del libro con copias disponibles y botón Reservar.
3. BÚSQUEDA: título, autor, género, ISBN (LIKE + paginación server-side), grid
   de portadas con coste uniforme.
4. CSV (ADMIN/PERSONAL): importador de CATÁLOGO (isbn;titulo;autor;editorial;
   anio;genero;idioma — separador , o ; autodetectado, UTF-8, cabecera
   obligatoria, plantilla descargable) con INFORME: importadas, duplicados
   omitidos, erróneas con nº de línea y motivo; checkbox "detener ante el primer
   error" (todo-o-nada). Importador de NÚMEROS DE SOCIO (columna numero).
5. RESERVAS: reservar, mis reservas (activas con cuenta atrás + código/QR,
   históricas), cancelar.
6. MODO MOSTRADOR (/mostrador, PERSONAL/ADMIN), tablet-first:
   (a) ENTREGAR RESERVA: input de código → ficha → "Cobrar con tokens" /
   "Cobrar con libro" (buscador + condición, o alta al vuelo por ISBN);
   (b) REGISTRAR DEPÓSITO: buscador + condición → confirmar; (c) alta de
   catálogo con escáner; (d) accesos rápidos.
7. NOTIFICACIONES in-app (campana): reserva creada, reserva expirada, entrega
   confirmada, recordatorio de activación.
8. AUDITORÍA: log_accion(accion, entidad, entidad_id, detalle JSON) +
   registro_auditoria. Registrar SIEMPRE: logins, activación, altas/ediciones de
   catálogo (indicando vía: manual/ISBN), importaciones CSV (resumen), depósitos,
   entregas (método de pago), expiraciones (system), cambios de configuración
   (anterior→nuevo), backup/restore, cambios de rol/estado de usuario. Panel
   ADMIN /admin/auditoria con filtros.
9. CONFIGURACIÓN (ADMIN): precios, horas_reserva, max_reservas_activas, datos del
   centro (nombre, dirección, teléfono, email, horario JSON, coordenadas, mapa
   google|osm, cómo llegar), integración Google. Todo auditado.
10. VISÍTANOS (público): dirección, teléfono, email, horario semanal (JSON),
    chip "Abierto ahora" en servidor (helper abierto_ahora), iframe de mapa SIN
    API KEY (google embed u OpenStreetMap según config) + "Cómo llegar"
    (google.com/maps/dir/?api=1&destination=lat,lng). Footer global ya lo enlaza.
11. MÉTRICAS (Chart.js, ADMIN): copias por estado, tokens en circulación,
    depósitos y entregas por mes, top libros por copias.
12. BACKUPS: manual ADMIN en PHP puro (SHOW TABLES → CREATE+INSERTs) a /backups/
    (.htaccess deny all) + descarga + tabla backups. Auto OPCIONAL según
    dias_backup_auto (bin/backup.php + pseudo-cron + retención). Restaurar: solo
    ADMIN, .sql subido, doble confirmación, por lotes.
13. HOME pública: hero con contadores, cómo funciona (3 pasos), acceso a
    catálogo y Visítanos.

## 9. ESQUEMA BD v4.1 (CANÓNICO — ya entregado en database/01_schema.sql)
roles, permisos, rol_permiso, usuarios (con google_sub/auth_provider/email_verificado/
foto_url), numeros_socio(numero UNIQUE, usuario_id UNIQUE NULL), libros(isbn13 NULL
UNIQUE), ejemplares, transacciones(tipo deposito|reserva, metodo_pago tokens|libro
NULL, codigo UNIQUE NULL, estado activa|entregada|expirada|cancelada, fecha_limite,
fecha_entrega, gestionada_por), movimientos_tokens, notificaciones,
registro_auditoria, intentos_login, backups, configuracion (semilla completa v4.1).

## 10. FASES CON DEFINICIÓN DE HECHO
Fase 0 — Andamiaje v4.1: actualiza pending() de tests/run.php (regla 6); router
  completo (sesión, $config, usuario+rol, notificaciones, CSRF, flash); home
  actualizada (coste uniforme, sin chips de nivel); /health.php OK.
  ✔ DoD: ./tests/run_all.sh → T-SMOKE-01 y T-SMOKE-07 verdes, 0 FAIL.
Fase 1 — Auth + socio: registro, login local, rate-limit, Google opcional (§6),
  /activar (§7), RBAC server-side, /privacidad, log_accion.
  ✔ DoD: T-AUTH-*, T-GOOG-01..05, T-SOCIO-01..04, T-SEC-01..05, T-PRIV-01.
Fase 2 — Catálogo + búsqueda + alta asistida por ISBN: CRUD, ficha, búsqueda,
  alta manual / por ISBN con APIs (cURL server-side) / escáner cámara, placeholder
  SVG, permisos.
  ✔ DoD: T-CAT-01..02, T-BUSQ-01, T-API-01..02 (SKIP sin red, documentado).
Fase 3 — CSV + números de socio: importadores con informe por líneas, plantilla,
  pool, asignación manual.
  ✔ DoD: T-CSV-01..04.
Fase 4 — Reservas: reservar/cancelar, límite simultáneo, fecha_limite, código+QR,
  expiración (bin/expirar.php + pseudo-cron), notificaciones.
  ✔ DoD: T-RESV-01..05.
Fase 5 — Mostrador y economía: depósitos, entregas con tokens o libro (§5.3),
  ledger íntegro, auditoría, confeti.
  ✔ DoD: T-ENTR-01..04, T-DEPO-01..02, T-LEDGER-01..02, T-MOST-01, T-AUDIT-01.
Fase 6 — Config, métricas, backups: panel auditado, Chart.js, backups
  manual/auto/restaurar, /visitanos completo.
  ✔ DoD: T-AUDIT-02, T-MET-01, T-BAK-01..03, T-VISIT-01..02.
Fase 7 — QA final + despliegue: suite 0 FAIL; dark mode verificado; responsive;
  docs/DEPLOY_FTP.md (§12); README con limitación HTTPS del escáner.
  ✔ DoD: tests/run.php → 0 FAIL (SKIPs de red listados).

## 11. SUITE DE TESTS (runner PHP puro ya existente — conserva convenciones)
| ID | Verifica |
|---|---|
| T-SMOKE-01..08 | home, health, login, catálogo, ficha, dashboard, admin, visitanos → 200 + contenido clave |
| T-AUTH-01 | Registro con email crea cuenta pendiente de activación |
| T-AUTH-02 | Login correcto/incorrecto; session_regenerate_id |
| T-AUTH-03 | 6º intento fallido → bloqueo 15 min |
| T-AUTH-04 | Contraseñas con password_hash; nada en texto plano |
| T-GOOG-01 | Sin client_id: botón ausente y /auth/google no redirige a Google |
| T-GOOG-02 | Con config: /auth/google → 302 a accounts.google.com con state |
| T-GOOG-03 | Callback con state inválido → 403, sin sesión, auditado |
| T-GOOG-04 | auth_google_procesar() con email local existente → mismo user_id, 'ambos' |
| T-GOOG-05 | Email nuevo → cuenta google-only; login local denegado con mensaje |
| T-SOCIO-01 | USUARIO pendiente → cualquier página redirige a /activar |
| T-SOCIO-02 | Número válido y libre → vinculado, activado, bono_bienvenida si >0 |
| T-SOCIO-03 | Número inexistente o ya asignado → rechazo claro, sigue pendiente |
| T-SOCIO-04 | Sin activar no puede reservar (denegado server-side) |
| T-CAT-01 | PERSONAL crea/edita libro y ejemplar |
| T-CAT-02 | USUARIO en CRUD de catálogo → 403 |
| T-BUSQ-01 | Búsqueda por título y por autor encuentra resultados |
| T-API-01 | Alta por ISBN real → libro creado con metadatos y portada (SKIP sin red) |
| T-API-02 | ISBN inexistente → formulario manual, sin excepción (SKIP sin red) |
| T-CSV-01 | CSV válido de catálogo → N libros creados + informe correcto |
| T-CSV-02 | Filas erróneas → buenas importadas, informe con nº de línea y motivo |
| T-CSV-03 | Duplicados (ISBN o titulo+autor) → omitidos y listados |
| T-CSV-04 | CSV de números de socio → pool creado, duplicados omitidos |
| T-RESV-01 | Reservar copia disponible → 'activa', limite = ahora + horas_reserva, código+QR |
| T-RESV-02 | max_reservas_activas alcanzado → 4ª denegada; ampliando el límite, permitida |
| T-RESV-03 | Reservar copia no disponible → denegado |
| T-RESV-04 | Expiración → 'expirada', copia 'disponible', notificación |
| T-RESV-05 | Cancelación por el usuario → copia 'disponible' |
| T-ENTR-01 | Entrega con tokens → retiro −coste_libro, copia 'retirado', 'entregada' |
| T-ENTR-02 | Entrega con libro NO admitido sin alta → rechazada, BD sin cambios |
| T-ENTR-03 | Entrega con libro admitido → depósito +bono_deposito Y retiro −coste_libro |
| T-ENTR-04 | Entrega tras fecha_limite → denegada |
| T-DEPO-01 | Depósito de libro admitido → +bono_deposito, copia 'disponible' |
| T-DEPO-02 | Depósito de libro no catalogado → alta al vuelo y aceptar, o rechazar (auditado) |
| T-LEDGER-01 | Tras N operaciones: saldo = SUM(movimientos) exacto por usuario |
| T-LEDGER-02 | Ningún flujo deja saldo negativo (intento → rechazo sin cambios) |
| T-MOST-01 | /mostrador: 200 PERSONAL/ADMIN, 403 USUARIO |
| T-SEC-01 | SQLi en login (' OR 1=1--) no autentica ni rompe |
| T-SEC-02 | USUARIO en endpoint admin → 403 server-side |
| T-SEC-03 | POST sin CSRF → rechazado |
| T-SEC-04 | XSS: <script> en título se muestra escapado |
| T-SEC-05 | /backups/ inaccesible desde fuera (403/404) |
| T-VISIT-01 | /visitanos muestra dirección y teléfono EXACTOS de configuracion |
| T-VISIT-02 | Iframe de mapa según config + enlace "Cómo llegar" correcto |
| T-PRIV-01 | /privacidad → 200, menciona datos y contacto del centro |
| T-AUDIT-01 | Entrega confirmada en registro_auditoria con método de pago |
| T-AUDIT-02 | Cambio de config registra valor anterior→nuevo |
| T-BAK-01 | Backup manual genera .sql con CREATE+INSERT y fila en backups |
| T-BAK-02 | Restaurar sobre BD vacía recupera usuarios y ledger |
| T-BAK-03 | USUARIO/PERSONAL sin acceso a backup/restaurar (403) |
| T-MET-01 | Métricas JSON coherentes con el seed |
| T-RGRC-01..02 | Regresión: T-LEDGER-01 y T-RESV-01 tras cada fase ≥3 |

Notas: T-GOOG-04/05 llaman DIRECTAMENTE a auth_google_procesar() tras require del
helper (sin HTTP). T-API usan has_internet(): si no hay red → SkipTest.

## 12. DESPLIEGUE POR FTP (docs/DEPLOY_FTP.md)
- public/ como docroot recomendado + index.php raíz de respaldo (.htaccess raíz
  protegiendo app/, config/, database/, bin/, backups/, tests/).
- Qué subir y qué NO (docker/, tests/, docs/, .git, .env).
- Checklist post-despliegue: cambiar contraseña admin, permisos de escritura en
  backups/ y uploads/, importar database/*.sql por phpMyAdmin, primer backup
  manual, /backups/ inaccesible, login local y Google OK.
- Google OAuth en producción: Google Cloud Console → consent screen (URL de
  privacidad https://DOMINIO/privacidad) → client ID web con redirect
  https://DOMINIO/auth/google/callback → pegar credenciales en Panel → Config.
- Cron opcional: bin/expirar.php y bin/backup.php (comandos Linux y Windows).

## 13. ORDEN DE ENTREGA
1. tests/run.php actualizado (pending v4.1, regla 6).
2. Fase 0 → 7 en orden. Tras cada fase: archivos creados + salida REAL de
   ./tests/run_all.sh + PROGRESS.md, y continúa sin esperar confirmación salvo
   fallo no resoluble.
```

---

# `database/01_schema.sql` (v4.1 — reemplaza el existente)

```sql
-- ============================================================
-- BookSwap · 01_schema.sql (v4.1) — CANÓNICO
-- Modelo simplificado: catálogo cerrado (CSV / manual / ISBN+APIs),
-- precio único, número de socio, reserva con recogida presencial.
-- Motor: InnoDB · utf8mb4_unicode_ci · MariaDB 10.11 / MySQL 8
-- ============================================================

SET NAMES utf8mb4;

-- ---------- RBAC ----------
CREATE TABLE roles (
  id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
  id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo  VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
  rol_id      TINYINT UNSIGNED  NOT NULL,
  permiso_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Usuarios ----------
CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NULL,                     -- NULL ⇒ cuenta google-only
  rol_id            TINYINT UNSIGNED NOT NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultima_actividad  DATETIME NULL,
  google_sub        VARCHAR(255) NULL UNIQUE,
  auth_provider     ENUM('local','google','ambos') NOT NULL DEFAULT 'local',
  email_verificado  TINYINT(1) NOT NULL DEFAULT 0,
  foto_url          VARCHAR(500) NULL,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Números de socio (pool pre-cargado) ----------
CREATE TABLE numeros_socio (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero            VARCHAR(30) NOT NULL UNIQUE,
  usuario_id        INT UNSIGNED NULL UNIQUE,              -- NULL = libre en el pool
  fecha_creacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_asignacion  DATETIME NULL,
  CONSTRAINT fk_socio_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Catálogo cerrado de libros admitidos ----------
CREATE TABLE libros (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn13          VARCHAR(17) NULL UNIQUE,                 -- opcional; NULL permitido (alta manual)
  titulo          VARCHAR(255) NOT NULL,
  autor           VARCHAR(255) NOT NULL,
  editorial       VARCHAR(120) NULL,
  anio            SMALLINT UNSIGNED NULL,
  genero          VARCHAR(80) NULL,
  idioma          VARCHAR(10) NOT NULL DEFAULT 'es',
  portada_url     VARCHAR(500) NULL,                       -- URL o NULL ⇒ placeholder SVG
  observaciones   VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_libros_titulo (titulo),
  INDEX idx_libros_autor  (autor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ejemplares (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  libro_id        INT UNSIGNED NOT NULL,
  estado          ENUM('disponible','reservado','retirado','baja') NOT NULL DEFAULT 'disponible',
  ubicacion       VARCHAR(30) NULL,
  condicion       ENUM('nuevo','como_nuevo','bueno','aceptable','deteriorado') NOT NULL DEFAULT 'bueno',
  fecha_ingreso   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  depositante_id  INT UNSIGNED NULL,
  CONSTRAINT fk_ejemplares_libro       FOREIGN KEY (libro_id)       REFERENCES libros(id),
  CONSTRAINT fk_ejemplares_depositante FOREIGN KEY (depositante_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_ejemplares_estado (estado),
  INDEX idx_ejemplares_libro  (libro_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Transacciones: depósitos y reservas ----------
CREATE TABLE transacciones (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo            ENUM('deposito','reserva') NOT NULL,
  ejemplar_id     INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  tokens          INT NOT NULL DEFAULT 0,                  -- importe aplicado en su momento
  metodo_pago     ENUM('tokens','libro') NULL,             -- solo entregas de reserva
  codigo          VARCHAR(30) NULL UNIQUE,                 -- código/QR de la reserva
  estado          ENUM('activa','entregada','expirada','cancelada') NOT NULL DEFAULT 'activa',
  fecha_limite    DATETIME NULL,                           -- fin de la reserva
  fecha_entrega   DATETIME NULL,
  gestionada_por  INT UNSIGNED NULL,                       -- PERSONAL/ADMIN que gestionó
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trans_ejemplar  FOREIGN KEY (ejemplar_id)   REFERENCES ejemplares(id),
  CONSTRAINT fk_trans_usuario   FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  CONSTRAINT fk_trans_gestion   FOREIGN KEY (gestionada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_trans_estado_limite (estado, fecha_limite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Ledger de tokens (inmutable) ----------
CREATE TABLE movimientos_tokens (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id        INT UNSIGNED NOT NULL,
  cantidad          INT NOT NULL,                          -- con signo
  tipo              ENUM('deposito','retiro','bono','bono_bienvenida','ajuste') NOT NULL,
  transaccion_id    BIGINT UNSIGNED NULL,
  concepto          VARCHAR(255) NOT NULL,
  saldo_resultante  INT NOT NULL,
  fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id)     REFERENCES usuarios(id),
  CONSTRAINT fk_mov_trans   FOREIGN KEY (transaccion_id) REFERENCES transacciones(id) ON DELETE SET NULL,
  INDEX idx_mov_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Notificaciones / auditoría / sistema ----------
CREATE TABLE notificaciones (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  mensaje     VARCHAR(255) NOT NULL,
  leida       TINYINT(1) NOT NULL DEFAULT 0,
  url         VARCHAR(255) NULL,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_not_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_not_usuario_leida (usuario_id, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registro_auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,                           -- NULL = acciones del sistema
  accion      VARCHAR(80) NOT NULL,
  entidad     VARCHAR(60) NULL,
  entidad_id  BIGINT UNSIGNED NULL,
  detalle     TEXT NULL,                                   -- JSON
  ip          VARCHAR(45) NULL,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_aud_accion_fecha (accion, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intentos_login (
  id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email  VARCHAR(190) NOT NULL,
  ip     VARCHAR(45) NULL,
  fecha  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intentos_email_fecha (email, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  archivo    VARCHAR(255) NOT NULL,
  tamano     INT UNSIGNED NULL,
  tipo       ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT UNSIGNED NULL,
  CONSTRAINT fk_bak_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
  clave  VARCHAR(80) NOT NULL PRIMARY KEY,
  valor  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

# `database/02_seed.sql` (v4.1 — reemplaza el existente)

```sql
-- ============================================================
-- BookSwap · 02_seed.sql (v4.1) — CANÓNICO. Se ejecuta tras 01_schema.sql.
-- Incluye: RBAC, configuración v4.1, datos del centro, usuarios demo,
-- pool de números de socio (2 asignados, 9 libres), 15 libros admitidos,
-- 15 ejemplares (13 disponibles + 1 reservado por la reserva demo + 1 retirado),
-- ledger consistente y 1 reserva activa de demostración.
-- CONTRASEÑAS DEMO: TODAS "password" (bcrypt cost 10).
-- Si un login demo fallara: docker compose exec app php bin/seed_passwords.php
-- IMPORTANTE: no existe el email nuevo.google@example.com (lo usa T-GOOG-05).
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Roles y permisos ----------
INSERT INTO roles (id, nombre) VALUES (1,'ADMIN'), (2,'PERSONAL'), (3,'USUARIO');

INSERT INTO permisos (id, codigo) VALUES
 (1,'usuario.base'),(2,'catalogo.ver'),(3,'reserva.crear'),(4,'mostrador.acceder'),
 (5,'catalogo.editar'),(6,'deposito.registrar'),(7,'entrega.confirmar'),
 (8,'csv.importar'),(9,'socios.gestionar'),(10,'usuarios.gestionar'),
 (11,'config.editar'),(12,'backup.gestionar'),(13,'restaurar.ejecutar'),
 (14,'auditoria.ver'),(15,'metricas.ver');

INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, id FROM permisos;
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
 (2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9);
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
 (3,1),(3,2),(3,3);

-- ---------- Usuarios demo ----------
SET @hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; -- "password"

INSERT INTO usuarios (id, nombre, email, password_hash, rol_id, activo, fecha_registro,
                      ultima_actividad, auth_provider, email_verificado) VALUES
 (1,'Admin Demo','admin@bookswap.local',       @hash,1,1, NOW()-INTERVAL 60 DAY, NOW()-INTERVAL 1 DAY, 'local',1),
 (2,'Personal Demo','personal@bookswap.local', @hash,2,1, NOW()-INTERVAL 55 DAY, NOW()-INTERVAL 2 DAY, 'local',1),
 (3,'Usuaria Demo','usuario@bookswap.local',   @hash,3,1, NOW()-INTERVAL 40 DAY, NOW()-INTERVAL 1 DAY, 'local',1),
 (4,'Cuenta Mixta','mixta@bookswap.local',     @hash,3,1, NOW()-INTERVAL 35 DAY, NOW()-INTERVAL 3 DAY, 'ambos',1),
 (5,'Google Demo','google.demo@bookswap.local', NULL,3,1, NOW()-INTERVAL 10 DAY, NOW()-INTERVAL 10 DAY,'google',1);
-- u5 = USUARIO google-only y SIN número de socio: demo perfecta de /activar.

-- ---------- Pool de números de socio ----------
INSERT INTO numeros_socio (numero, usuario_id, fecha_asignacion) VALUES
 ('SOC-0001', 3, NOW()-INTERVAL 40 DAY),
 ('SOC-0002', 4, NOW()-INTERVAL 35 DAY),
 ('SOC-0003', NULL, NULL),('SOC-0004', NULL, NULL),('SOC-0005', NULL, NULL),
 ('SOC-0006', NULL, NULL),('SOC-0007', NULL, NULL),('SOC-0008', NULL, NULL),
 ('SOC-0009', NULL, NULL),('SOC-0010', NULL, NULL),('SOC-0011', NULL, NULL);

-- ---------- Configuración v4.1 ----------
INSERT INTO configuracion (clave, valor) VALUES
 -- Economía
 ('coste_libro','1'),
 ('bono_deposito','1'),
 ('bono_bienvenida','0'),
 -- Operativa
 ('horas_reserva','72'),
 ('max_reservas_activas','3'),
 -- Backups
 ('dias_backup_auto','7'),
 ('retencion_backups','10'),
 ('ultimo_backup_auto', NULL),
 -- Datos del punto físico (Visítanos + footer global)
 ('centro_nombre','BookSwap — Biblioteca Ciudadana'),
 ('centro_direccion','Calle de los Libros 42, 28004 Madrid'),
 ('centro_telefono','+34 910 123 456'),
 ('centro_email','hola@bookswap.local'),
 ('centro_horario','{"lunes":["09:00-14:00","17:00-20:00"],"martes":["09:00-14:00","17:00-20:00"],"miercoles":["09:00-14:00","17:00-20:00"],"jueves":["09:00-14:00","17:00-20:00"],"viernes":["09:00-14:00","17:00-20:00"],"sabado":["10:00-13:30"],"domingo":[]}'),
 ('centro_mapa_lat','40.4168'),
 ('centro_mapa_lng','-3.7038'),
 ('centro_mapa_proveedor','google'),
 ('centro_como_llegar','Metro: Banco de España (L2) a 5 min a pie. Bus: 14, 27, 45. Aparcamiento en Plaza de las Cortes.'),
 -- Login con Google (vacío = botón oculto, app 100% funcional sin él)
 ('google_client_id',''),
 ('google_client_secret',''),
 ('google_redirect_uri','http://localhost:8080/auth/google/callback');

-- ---------- Catálogo demo: 15 libros admitidos ----------
INSERT INTO libros (id, isbn13, titulo, autor, editorial, anio, genero, portada_url) VALUES
 (1 ,'9788437604197','Don Quijote de la Mancha','Miguel de Cervantes','Cátedra',1605,'Clásicos','https://covers.openlibrary.org/b/isbn/9788437604197-L.jpg?default=false'),
 (2 ,'9780451524935','1984','George Orwell','Signet',1949,'Distopía','https://covers.openlibrary.org/b/isbn/9780451524935-L.jpg?default=false'),
 (3 ,'9781400034986','Cien años de soledad','Gabriel García Márquez','Vintage',1967,'Novela','https://covers.openlibrary.org/b/isbn/9781400034986-L.jpg?default=false'),
 (4 ,'9788478884459','Harry Potter y la piedra filosofal','J. K. Rowling','Salamandra',1997,'Fantasía','https://covers.openlibrary.org/b/isbn/9788478884459-L.jpg?default=false'),
 (5 ,'9788408175695','La sombra del viento','Carlos Ruiz Zafón','Debolsillo',2003,'Novela negra','https://covers.openlibrary.org/b/isbn/9788408175695-L.jpg?default=false'),
 (6 ,'9788445000639','El Hobbit','J. R. R. Tolkien','Minotauro',1937,'Fantasía','https://covers.openlibrary.org/b/isbn/9788445000639-L.jpg?default=false'),
 (7 ,'9780345391803','Guía del autoestopista galáctico','Douglas Adams','Del Rey',1979,'Ciencia ficción','https://covers.openlibrary.org/b/isbn/9780345391803-L.jpg?default=false'),
 (8 ,'9780141439518','Orgullo y prejuicio','Jane Austen','Penguin',1813,'Clásicos','https://covers.openlibrary.org/b/isbn/9780141439518-L.jpg?default=false'),
 (9 ,'9788437610570','Crimen y castigo','Fiódor Dostoyevski','Cátedra',1866,'Clásicos','https://covers.openlibrary.org/b/isbn/9788437610570-L.jpg?default=false'),
 (10,'9788499890953','Un mundo feliz','Aldous Huxley','Debolsillo',1932,'Distopía','https://covers.openlibrary.org/b/isbn/9788499890953-L.jpg?default=false'),
 (11,'9780307454546','Los hombres que no amaban a las mujeres','Stieg Larsson','Vintage',2005,'Novela negra','https://covers.openlibrary.org/b/isbn/9780307454546-L.jpg?default=false'),
 (12,'9780316769488','El guardián entre el centeno','J. D. Salinger','Little, Brown',1951,'Novela','https://covers.openlibrary.org/b/isbn/9780316769488-L.jpg?default=false'),
 (13,'9781451673319','Fahrenheit 451','Ray Bradbury','Simon & Schuster',1953,'Ciencia ficción','https://covers.openlibrary.org/b/isbn/9781451673319-L.jpg?default=false'),
 (14,'9780156012195','El principito','Antoine de Saint-Exupéry','Harcourt',1943,'Infantil','https://covers.openlibrary.org/b/isbn/9780156012195-L.jpg?default=false'),
 (15,'9780061120084','Matar a un ruiseñor','Harper Lee','Harper Perennial',1960,'Novela','https://covers.openlibrary.org/b/isbn/9780061120084-L.jpg?default=false');

-- ---------- Ejemplares: 13 disponibles + 1 reservado (demo) + 1 retirado ----------
INSERT INTO ejemplares (id, libro_id, estado, ubicacion, fecha_ingreso, depositante_id, condicion) VALUES
 (1 ,1 ,'disponible','A-01-02', NOW()-INTERVAL 30 DAY, 3, 'como_nuevo'),
 (2 ,2 ,'disponible','A-02-01', NOW()-INTERVAL 30 DAY, 3, 'bueno'),
 (3 ,2 ,'disponible','A-02-02', NOW()-INTERVAL 25 DAY, 4, 'bueno'),
 (4 ,3 ,'disponible','B-01-04', NOW()-INTERVAL 22 DAY, 3, 'como_nuevo'),
 (5 ,4 ,'disponible','B-01-05', NOW()-INTERVAL 21 DAY, 3, 'bueno'),
 (6 ,5 ,'disponible','B-02-01', NOW()-INTERVAL 20 DAY, 4, 'bueno'),
 (7 ,6 ,'disponible','C-01-01', NOW()-INTERVAL 20 DAY, 4, 'bueno'),
 (8 ,6 ,'disponible','C-01-02', NOW()-INTERVAL 20 DAY, 4, 'aceptable'),
 (9 ,7 ,'disponible','C-02-01', NOW()-INTERVAL 18 DAY, 3, 'bueno'),
 (10,8 ,'disponible','C-02-02', NOW()-INTERVAL 15 DAY, 3, 'bueno'),
 (11,9 ,'disponible','D-01-01', NOW()-INTERVAL 15 DAY, 4, 'aceptable'),
 (12,10,'disponible','D-01-02', NOW()-INTERVAL 12 DAY, 3, 'bueno'),
 (13,11,'disponible','D-02-01', NOW()-INTERVAL 10 DAY, 4, 'bueno'),
 (14,12,'reservado' ,'D-02-02', NOW()-INTERVAL 1  DAY, 3, 'bueno'),   -- copia de la reserva demo
 (15,14,'retirado'  ,'E-01-01', NOW()-INTERVAL 31 DAY, 3, 'como_nuevo'); -- entregada hace 30 días

-- ---------- Transacciones demo ----------
INSERT INTO transacciones (id, tipo, ejemplar_id, usuario_id, tokens, codigo, estado,
                           fecha_limite, fecha_entrega, gestionada_por, created_at) VALUES
 (1,'deposito',15,3,1,NULL,'entregada',NULL,NOW()-INTERVAL 30 DAY,2,NOW()-INTERVAL 31 DAY),
 (2,'reserva' ,14,3,0,'RES-DEMO-0001','activa',NOW()+INTERVAL 72 HOUR,NULL,NULL,NOW()-INTERVAL 1 DAY);

-- ---------- Ledger demo (T-LEDGER-01: saldo = SUM por usuario) ----------
INSERT INTO movimientos_tokens (usuario_id, cantidad, tipo, transaccion_id, concepto, saldo_resultante, fecha) VALUES
 (1, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 60 DAY),
 (2, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 55 DAY),
 (3, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 40 DAY),
 (3, 1, 'deposito', 1,    'Depósito: El principito',               6, NOW()-INTERVAL 30 DAY),
 (4, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 35 DAY);
-- Saldos: u1=5, u2=5, u3=6, u4=5, u5=0 (pendiente de activar, sin movimientos).

-- ---------- Notificaciones demo ----------
INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha) VALUES
 (3,'Reserva creada: El guardián entre el centeno. Recógelo en el mostrador antes de la fecha límite.',0,'/mis-reservas', NOW()-INTERVAL 1 DAY),
 (5,'Bienvenido/a. Introduce tu número de socio para activar tu cuenta.',0,'/activar', NOW()-INTERVAL 10 DAY);

-- ---------- Auditoría inicial ----------
INSERT INTO registro_auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip, fecha) VALUES
 (2,'seed_inicial','sistema',NULL,'{"nota":"Datos de demostración instalados"}','127.0.0.1', NOW());
```

---

# Cómo proceder con Antigravity (paso a paso)

**1. Actualiza los ficheros en tu carpeta del proyecto:**

```
librosbro/
├── BRIEF.md                  ← REEMPLAZA por el PROMPT MAESTRO v4.1 de arriba
├── database/
│   ├── 01_schema.sql         ← REEMPLAZA por el v4.1
│   └── 02_seed.sql           ← REEMPLAZA por el v4.1
└── bin/seed_passwords.php    ← ya lo tienes (sigue valiendo, contraseñas "password")
```

**2. Resetea y verifica la base tú mismo (2 min):**

```bash
cd /media/paspas/.../librosbro
docker compose down -v          # imprescindible: borra el volumen para que MariaDB
docker compose up -d --wait     # re-ejecute los .sql nuevos
docker compose exec app php tests/run.php
```

Esperado: **2 PASS · 0 FAIL · resto SKIP**. Comprueba en http://localhost:8081 (phpMyAdmin, `root`) que existen 13 tablas, 15 libros, 11 números de socio y 2 transacciones.

**3. Arranca el agente con este mensaje (cópialo tal cual):**

```markdown
Lee BRIEF.md (v4.1) y PROGRESS.md y sigue sus reglas. La base está VERIFICADA:
Docker, tema/layout, helpers, database/01_schema.sql + 02_seed.sql (CANÓNICOS, no
los regeneres) y la suite en verde (2 PASS, resto SKIP = tu roadmap). Recuerda el
modelo v4.1: catálogo cerrado con TRES vías de alta (CSV, manual, escáner ISBN +
APIs Google Books/Open Library por cURL server-side); precio único coste_libro;
número de socio obligatorio para usuarios (rol USUARIO) en primer login; pago del
retiro EN MOSTRADOR con tokens o traendo un libro admitido. Empieza por §13:
actualiza tests/run.php con los pending de v4.1 (regla 6 de §0) y construye la
Fase 0 (router + home con coste uniforme). Tras cada fase: ./tests/run_all.sh en
0 FAIL → docs/PHASE_REPORTS/faseN.md → PROGRESS.md, y continúa sola.
```

**4. Si la sesión se corta** en medio del desarrollo, retoma con: *"Lee PROGRESS.md y docs/PHASE_REPORTS/ y continúa por la fase X sin romper tests existentes."*

**5. Aviso práctico del escáner:** funciona en `http://localhost:8080` (contexto seguro permitido), pero **no** desde el móvil por LAN sin HTTPS. Para el mostrador, el flujo robusto es lector USB (~15€) o teclear el ISBN — el prompt obliga a mantener ambas vías junto a la cámara.

Con esto, la única tarea creativa del agente en Fase 0 es el router y la home; todo lo demás (esquema, datos, estilos, tests) ya está cerrado y verificado. Si algo falla en la verificación del paso 2, pégame la salida y lo ajustamos antes de soltar al agente.