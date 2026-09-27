# Reporte de Fase 18 — Reorganización UX Integral

**Fecha:** 25 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.5)  
**Estado:** Completada (105 PASS, 0 FAIL, 3 SKIP)

---

## 1. Objetivos y Alcance de la Fase 18

La **Fase 18** culmina el incremento v4.5 transformando la experiencia de usuario (UX) en todos los niveles del sistema para eliminar fricciones operativas, jerarquizar la navegación por rol, acelerar la operativa presencial de mostrador y unificar el diseño visual:

1. **Navbar adaptativo según rol (§3.1):**
   - **Invitado:** Catálogo, Cómo funciona, Visítanos, Iniciar sesión / Registro.
   - **Usuario (lector):** Catálogo, Cómo funciona, Mis reservas, Mi historial, Saldo de tokens destacado, Visítanos, Menú de usuario (Mi perfil, Cerrar sesión). *Sin enlaces a Mostrador ni Panel de Administración.*
   - **Personal:** Mostrador, Catálogo, Menú de usuario. *Sin acceso a Panel de Administración.*
   - **Admin:** Panel de Administración, Mostrador, Catálogo, Menú de usuario.
   - **Logotipo central:** Enlaza a `/dashboard` si el usuario está autenticado, o a `/` si es un visitante público.

2. **Dashboard ciudadano como Home del usuario (§3.2):**
   - Acceder a `/` estando autenticado redirige mediante HTTP 302 a `/dashboard`.
   - Organización en 5 bloques jerárquicos según la especificación:
     1. **Saldo de tokens destacado** en tipografía grande con badge de estado y botón CTA directo hacia el catálogo de libros.
     2. **Reservas activas:** Visualización directa con tarjeta individual, fecha límite con contador de tiempo restante, código de barras Code 128 (JsBarcode) listo para presentar al lector de pistola láser y botón de cancelación mediante modal seguro.
     3. **Aviso condicional de NIA pendiente:** Notificación destacada si la cuenta carece de NIA verificado con enlace directo a `/completar-nia`.
     4. **Novedades en catálogo:** Carrusel/grid con los últimos 5 libros que tienen copias disponibles, con botón de reserva rápida.
     5. **Actividad reciente:** Últimos 5 movimientos del ledger contable de tokens con tipo, concepto y saldo resultante.

3. **Reserva en 1 Clic desde el Catálogo (§3.3):**
   - En las cards de libros con ejemplares disponibles (`/catalogo`), se incorpora el botón directo «Reservar».
   - Al pulsar, abre un modal de confirmación rápida mostrando el título, autor y coste en tokens.
   - Al confirmar, envía el formulario mediante `POST /reservar` y redirige al dashboard mostrando la reserva y su código de barras listo.

4. **Hub de Mostrador Tablet-First (§3.4):**
   - Priorización del escáner universal al tope de la página, con autoenfoque y atajo de teclado (`F2` o `/`).
   - Disposición de tarjetas de tareas en el orden de mayor uso operativo en mostrador físico:
     1. *Entrega directa rápida* (sin reserva previa).
     2. *Depósito de libros* (alta y entrega al fondo común).
     3. *Entrega de reserva activa*.
     4. *Búsqueda de usuario y consulta de saldo*.
   - Las tarjetas evalúan la matriz de permisos mediante `puede('nombre.permiso')` y se ocultan si el personal no tiene el permiso otorgado.
   - Sección informativa «Hoy en el mostrador» con recuento de entregas y depósitos del día, y listado accionable de reservas que expiran hoy con botón directo para procesar la entrega.

5. **Panel de Administración con Sidebar Agrupado (§3.5):**
   - Menú lateral permanente estructurado en 3 grupos:
     - **Operación:** Métricas y Resumen, Catálogo y Libros, Reservas y Préstamos, Movimientos del Ledger.
     - **Personas:** Usuarios del Sistema, Pool de NIAs, Permisos y Roles (`/admin/roles`).
     - **Sistema:** Configuración Global, Copias de Seguridad, Registro de Auditoría.
   - Fila superior de *Acciones Rápidas* hacia tareas frecuentes (Entrega directa, Depósito, Nuevo libro, Pool NIAs, Backup).
   - Bloque prioritario de *Pendientes de atención* que destaca reservas que expiran hoy, usuarios con NIA pendiente y estado del backup periódico.
   - Breadcrumbs de navegación en vistas secundarias del panel de administración (`/admin/usuarios`, etc.).

6. **Limpieza de Rutas y Coherencia de Interfaz (§3.6):**
   - Redirección 302 de `/mis-depositos` a `/mi-historial?tipo=deposito`.
   - Sustitución de ventanas de diálogo nativas `confirm()` por modales Bootstrap accesibles para cancelaciones y acciones destructivas.
   - Creación de la página pública `/como-funciona` explicando el préstamo ciudadano, consejos de lectura de códigos de barras en pantalla de smartphone y preguntas frecuentes.

7. **Ampliación de Pruebas y Benchmark (§3.7, §6.4):**
   - Implementación de los tests de integración `T-UX-01` a `T-UX-07`.
   - Incorporación de `/como-funciona` y `/admin/roles` al script `tests/benchmark.php`.

---

## 2. Archivos Modificados e Implementados

| Archivo | Acción | Descripción |
|---|---|---|
| `app/views/layouts/base.php` | Modificado | Navbar adaptativo por rol (`INVITADO`, `USUARIO`, `PERSONAL`, `ADMIN`), logo con redirección inteligente y footer actualizado con enlace a `/como-funciona`. |
| `app/views/admin/partials/sidebar.php` | Creado | Componente sidebar con los 10 módulos agrupados en Operación, Personas y Sistema con detección de ruta activa. |
| `app/views/admin/panel.php` | Modificado | Rediseño a layout de 2 columnas (sidebar + contenido), tarjetas de acciones rápidas y bloque "Pendientes de atención". |
| `app/views/admin/usuarios.php` | Modificado | Inclusión de breadcrumb `Inicio > Administración > Usuarios` y enlace al panel. |
| `app/views/admin/mostrador.php` | Modificado | Reordenación de tareas en formato tablet-first, escáner universal destacado, protección con `puede()` y bloque "Hoy en el mostrador". |
| `app/views/usuario/dashboard.php` | Modificado | Rediseño integral con los 5 bloques especificados, visualización de código de barras Code 128 con JsBarcode y modal de cancelación. |
| `app/views/catalogo/index.php` | Modificado | Botón de reserva en 1 clic en tarjetas de catálogo y modal de confirmación rápida con token CSRF. |
| `app/views/usuario/reservas.php` | Modificado | Sustitución de `confirm()` de navegador por modal Bootstrap para la cancelación de reservas activas. |
| `app/views/estatico/como-funciona.php` | Creado | Vista pública explicativa del funcionamiento del servicio, consejos para escáner 1D y FAQs. |
| `app/views/home.php` | Modificado | Enlace directo a `/como-funciona` e iconos actualizados a código de barras 1D. |
| `app/router.php` | Modificado | Redirección de `/` a `/dashboard` para usuarios logueados, ruta 302 `/mis-depositos`, ruta `/como-funciona`, consultas para dashboard, admin y mostrador. |
| `tests/benchmark.php` | Modificado | Incorporación de `/como-funciona` y `/admin/roles` al benchmark de rendimiento. |
| `tests/run.php` | Modificado | Implementación de las pruebas unitarias y de integración `T-UX-01` a `T-UX-07`. |
| `docs/PERFORMANCE.md` | Modificado | Registro de métricas de tiempo de respuesta y peso para las 12 rutas auditadas. |
| `README.md` | Modificado | Actualización de documentación a versión v4.5, recuento de tests, hardware 1D y módulos admin. |

---

## 3. Resultados de las Pruebas (T-UX-01..07)

Ejecución de la suite automatizada canónica:
```text
── Fase 18 — Reorganización UX integral (v4.5)
  ✔ T-UX-01: Navbar por rol: invitado sin Mostrador/Panel; usuario con Mis reservas e historial y sin Mostrador; personal con Mostrador; admin con Panel y Mostrador
  ✔ T-UX-02: Usuario logueado: GET / → 302 a /dashboard; /dashboard contiene saldo, la reserva demo RES-DEMO-0001 de u3, últimos movimientos y CTA al catálogo
  ✔ T-UX-03: En /catalogo, una card de libro disponible contiene formulario/botón que hace POST a la ruta de reserva; el flujo desde la card crea la reserva (estado activa, código)
  ✔ T-UX-04: /mostrador: escáner universal presente; Entrega directa es la PRIMERA tarjeta; las tarjetas respetan puede()
  ✔ T-UX-05: /admin: sidebar con los 3 grupos y sus enlaces, acciones rápidas y sección "Pendientes de atención"; breadcrumbs presentes en /admin/usuarios
  ✔ T-UX-06: /mis-depositos redirige a /mi-historial con filtro tipo=deposito; /como-funciona responde 200 y está enlazada desde home y footer
  ✔ T-UX-07: Confirmaciones destructivas usan modal (no confirm() nativo): la cancelación de reserva desde /mis-reservas pasa por modal con CSRF

════════ RESUMEN ════════
  PASS: 105   FAIL: 0   SKIP: 3

✔ SUITE VERDE
```

---

## 4. Resultados del Benchmark de Rendimiento (12 Rutas)

Ejecutado con `tests/benchmark.php` (10 peticiones consecutivas por ruta con sesiones autenticadas reutilizadas):

| Ruta | Autenticación | Código HTTP | Mediana | Máximo | Tamaño |
|---|---|---|---|---|---|
| `/` | (público) | 200 | 81.96 ms | 102.87 ms | 19.78 KB |
| `/catalogo` | (público) | 200 | 83.69 ms | 92.38 ms | 39.89 KB |
| `/catalogo?q=quijote` | (público) | 200 | 84.13 ms | 99.91 ms | 13.34 KB |
| `/libro/1` | (público) | 200 | 85.95 ms | 94.35 ms | 18.88 KB |
| `/login` | (público) | 200 | 82.64 ms | 86.57 ms | 9.68 KB |
| `/visitanos` | (público) | 200 | 76.89 ms | 83.85 ms | 15.17 KB |
| `/como-funciona` | (público) | 200 | 94.21 ms | 121.38 ms | 14.09 KB |
| `/dashboard` | usuario | 200 | 77.28 ms | 86.43 ms | 34.89 KB |
| `/mostrador` | personal | 200 | 79.83 ms | 96.55 ms | 494.22 KB |
| `/mi-historial` | usuario | 200 | 75.39 ms | 86.70 ms | 34.73 KB |
| `/admin` | admin | 200 | 83.69 ms | 146.21 ms | 28.92 KB |
| `/admin/roles` | admin | 200 | 80.84 ms | 87.98 ms | 43.23 KB |

**Conclusión:** Todas las rutas responden en < 95 ms de mediana (< 150 ms máximo permitido), garantizando una experiencia ágil tanto en ordenadores de sobremesa como en tablets de mostrador.
