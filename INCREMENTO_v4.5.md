He revisado el flujo completo de la aplicación con ojos de usuario y el diagnóstico es claro. Antes del prompt, esto es lo que he encontrado y las decisiones que he tomado:

## 🔍 Por qué no resulta intuitiva (y cómo lo resuelvo)

| Problema | Causa raíz | Solución en el incremento |
|---|---|---|
| Navegación sin jerarquía por tareas | Menús por "conceptos técnicos" (transacciones, ledger) mezclados entre roles | IA nueva: cada rol tiene **una entrada y un objetivo claro**; dashboard = home del usuario logueado |
| Las tareas frecuentes exigen navegar demasiado | Reservar = catálogo→ficha→formulario; entregar = mostrador→pestaña→código→ficha→cobro | **Reservar en 1 clic desde la card** (modal de confirmación) y **"Escáner universal"** en mostrador: un solo input al que apuntas cualquier cosa |
| Panel admin = lista plana de 9 secciones | Sin agrupación ni acciones contextuales | **Sidebar agrupada** (Operación / Personas / Sistema) + acciones rápidas + lista de "pendientes de atención" |
| Menús fijos por rol | El personal no puede adaptarse a tu organización | **Matriz de permisos editable**: el ADMIN activa/desactiva capacidades del PERSONAL con checkboxes (se apoya en el RBAC que ya existe, sin cambiar el esquema) |
| ⚠️ El confirmar-reserva usaba **QR** y vuestro escáner es **de barras (1D)** | Incompatibilidad física | Los códigos de reserva pasan a **Code 128** (JsBarcode, CDN) escaneables con vuestro lector; el ISBN de los propios libros (EAN-13 impreso) se usa para identificar ejemplares en mostrador |

**La joya del mostrador — "Escáner universal"**: un único input siempre enfocado donde el personal escanea o teclea cualquier cosa. El sistema detecta qué es: código de reserva (`RES-…`) → abre la ficha de esa reserva; 13 dígitos (ISBN del lomo del libro) → muestra el libro con sus acciones (entregar copia / registrar depósito / dar de alta si no existe). Cero navegación: escanear y decidir.

---

# `INCREMENTO_v4.5.md` (guárdalo en la raíz del proyecto)

```markdown
# INCREMENTO v4.5 — BookSwap: UX + PERMISOS DEL PERSONAL + ESCÁNER DE BARRAS
# (a ejecutar tras completar v4.4 con suite en verde; añade las fases 16 → 18)
# Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2/v4.3/v4.4: TODAS sus reglas siguen
# vigentes (DoD por fase, regresión = fallo, archivos completos, no editar tests
# para que pasen). No se elimina funcionalidad de negocio: se reorganiza y se
# adapta a escáner de códigos de barras 1D.

## 0. CONTEXTO Y PRINCIPIOS DE DISEÑO (OBLIGATORIOS EN CADA DECISIÓN)
El hardware del mostrador es un LECTOR DE CÓDIGOS DE BARRAS 1D (no lee QR).
Principios que gobiernan este incremento:
P1. UNA PANTALLA POR TAREA: cada operación frecuente (reservar, entregar,
    depositar) es ejecutable desde su punto de entrada en ≤3 interacciones.
P2. EL ESCÁNER MANDA: en mostrador, un ÚNICO input universal recibe cualquier
    código (reserva o ISBN) y el sistema ofrece la acción correcta. Nunca se
    pide al usuario que adivine dónde escribir.
P3. MENÚS SEGÚN PERMISOS REALES: la UI se oculta/muestra con puede($codigo),
    no con el rol. La denegación real es siempre server-side.
P4. LENGUAJE HUMANO: "Tokens", "Entregas y depósitos", "Mi historial". Nada de
    jerga (ledger, transacciones) en la UI. Tooltips (?) donde haga falta.
P5. Consistencia visual con el tema canónico (theme.css): cards, modales,
    toasts, empty states. Nada de estilos fuera de tokens.

## 1. FASE 16 — PERMISOS DEL PERSONAL CONFIGURABLES POR EL ADMIN
### 1.1 Base (sin cambios de esquema)
Se usa el RBAC existente (permisos, rol_permiso). ÚNICO cambio autorizado en
ficheros canónicos, en database/02_seed.sql:
  INSERT INTO permisos (id, codigo) VALUES (16,'roles.gestionar');
  INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (1,16);
### 1.2 Diccionario de permisos — nuevo app/helpers/permisos.php
puede(string $codigo): bool — permisos del usuario en sesión (cacheados en
$_SESSION y regenerados en login y tras guardar la matriz).
permisos_dict(): array — mapa codigo => [nombre, descripcion, grupo]. Grupos:
  BASE (siempre activas, NO editables, checkbox disabled):
    usuario.base (Usar la aplicación) · catalogo.ver (Ver el catálogo) ·
    reserva.crear (Reservar libros)
  CATÁLOGO: catalogo.editar (Crear y editar libros y ejemplares) ·
    csv.importar (Importar CSV: catálogo y NIAs)
  MOSTRADOR: mostrador.acceder (Acceder al Modo Mostrador) ·
    entrega.confirmar (Entregar libros: reservas y entrega directa) ·
    deposito.registrar (Registrar depósitos)
  PERSONAS: nias.gestionar (Gestionar NIAs) · usuarios.gestionar (Gestionar
    usuarios: crear, editar, roles, ajustes de tokens)
  SISTEMA: config.editar · backup.gestionar · restaurar.ejecutar ·
    auditoria.ver · metricas.ver · roles.gestionar (Configurar permisos del personal)
### 1.3 Página /admin/roles (permiso roles.gestionar, solo ADMIN)
- Matriz: filas = permisos agrupados (nombre + descripción); columnas: ADMIN
  (read-only, todo activo), PERSONAL (checkboxes editables), USUARIO
  (read-only, solo BASE).
- Guardar: transacción, reemplaza rol_permiso del rol PERSONAL (BASE siempre
  incluida), un registro de auditoría por cada cambio (permiso, anterior→nuevo),
  regenera la caché de permisos de las sesiones activas de PERSONAL (al menos,
  marca para regeneración en el próximo request).
- Botón "Restaurar valores por defecto" (reaplica la matriz seed v4.2/§1.2),
  con modal de confirmación y auditoría.
- AVISO no bloqueante al guardar si PERSONAL queda sin ninguna operación de
  mostrador (sin mostrador.acceder, o accediendo pero sin ninguna acción).
### 1.4 Sustitución de controles por puede()
Reemplaza los chequeos de rol por puede() en: tarjetas/pestañas de /mostrador
(entrega directa y entrega de reserva ⇒ entrega.confirmar; depósito ⇒
deposito.registrar; alta de catálogo ⇒ catalogo.editar), importadores CSV,
gestión de NIAs, secciones /admin/*, y acciones rápidas del panel. Acceso a
/mostrador requiere mostrador.acceder; a /admin, cualquier permiso de SISTEMA
o PERSONAS o csv.importar. Endpoint denegado ⇒ 403 con página amable "No tienes
permiso para esta acción; contacta con el administrador".
### 1.5 Tests nuevos
| ID | Verifica |
|---|---|
| T-ROLE-01 | /admin/roles: 200 con roles.gestionar, 403 para PERSONAL y USUARIO |
| T-ROLE-02 | Desmarcar csv.importar a PERSONAL → para personal: /admin/csv 403 y enlace ausente; volver a marcar → 200 |
| T-ROLE-03 | Quitar entrega.confirmar → en /mostrador la card Entrega directa desaparece para personal y el endpoint responde 403; restaurado → visible y 200 |
| T-ROLE-04 | Los permisos BASE no se pueden desactivar ni por POST directo (se ignoran/rechazan) |
| T-ROLE-05 | Cada cambio queda auditado (anterior→nuevo) y "Restaurar por defecto" reaplica el seed |
✔ DoD Fase 16: suite 0 FAIL con T-ROLE-01..05 y regresión completa verde;
reporte fase16.md; PROGRESS.md.

## 2. FASE 17 — ESCÁNER DE BARRAS (1D) Y ESCÁNER UNIVERSAL
### 2.1 Códigos de reserva como Code 128 (NO QR)
- ELIMINA toda generación/lectura de QR del proyecto (qrcode.js fuera de los
  CDN del layout; si alguna página lo usaba, se sustituye).
- Añade JsBarcode por CDN (https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/
  JsBarcode.all.min.js) SOLO en las páginas que lo usan.
- /mis-reservas: cada reserva ACTIVA muestra su código como Code 128 GRANDE
  (SVG generado client-side con JsBarcode) + el código en texto grande debajo
  (para lectura/tecleado manual) + cuenta atrás + botón Cancelar. La pantalla
  de confirmación tras reservar muestra lo mismo.
- Formato del código generado: 'RES-' + 10 caracteres aleatorios [A-Z0-9]
  (random_bytes/random_int). El prefijo fijo es válido (permite el enrutado
  del escáner universal); lo aleatorio es el sufijo.
- El usuario puede mostrarlo en el móvil (consejo de brillo alto documentado
  en /como-funciona y README) o imprimirlo; el lector 1D del mostrador lo lee
  en el input enfocado.
### 2.2 Escáner universal en /mostrador (P2)
- Input único persistente arriba de todo (id="escaner-universal"), AUTOfocus,
  re-enfocado tras cada operación; el lector USB escribe y envía Enter ⇒
  auto-submit. Botón alternativo de cámara (html5-qrcode) configurado SOLO con
  formatos 1D: EAN_13, EAN_8, UPC_A, CODE_128, CODE_39 (nota: la cámara exige
  HTTPS fuera de localhost; el lector USB es la vía primaria — documéntalo).
- Endpoint POST /mostrador/escanear (CSRF, permiso mostrador.acceder), lógica:
  1) normaliza: trim, mayúsculas;
  2) si casa ^RES-[A-Z0-9]{6,12}$ → busca la reserva por codigo:
     activa ⇒ ficha de la reserva con acciones (Cobrar con tokens / con libro)
     según puede('entrega.confirmar'); expirada/cancelada/entregada ⇒ estado
     con explicación; inexistente ⇒ "código no reconocido" sin romper;
  3) elseif ^\d{9,13}$ → trata como ISBN (quita guiones): busca en libros:
     encontrado ⇒ página del libro con copias disponibles y acciones según
     permisos (Entregar copia —entrega directa—, Registrar depósito, Editar);
     no encontrado y puede('catalogo.editar') ⇒ botón "Dar de alta este libro"
     que abre el alta asistida con el ISBN precargado; no encontrado sin
     permiso ⇒ mensaje claro;
  4) else ⇒ "Código no reconocido" (nunca excepción).
- Cada acción ofrecida verifica puede() (P3). Todo auditado como ya existe.
### 2.3 ISBN escaneado en los flujos existentes
- Entrega directa (paso 1) y Registrar depósito: el buscador acepta escaneo
  directo del ISBN del lomo del libro (mismo input, auto-submit) — además del
  autocompletado por título/autor.
- Alta de catálogo: el campo ISBN acepta escáner (auto-búsqueda de APIs al
  recibir 13 dígitos + Enter).
### 2.4 Tests nuevos y modificados
| ID | Verifica |
|---|---|
| T-SCAN-01 | /mis-reservas y la confirmación de reserva muestran JsBarcode + código en texto y NO referencian qrcode.js |
| T-SCAN-02 | Dos reservas nuevas generan códigos distintos con sufijo aleatorio y prefijo RES- (MODIFICA el assertion de T-SEC-08: se permite el prefijo fijo, se exige aleatoriedad del sufijo; NO cambies el ID) |
| T-SCAN-03 | Escanear código de reserva activa → ficha con acciones (personal con entrega.confirmar); USUARIO → 403 |
| T-SCAN-04 | Escanear 9788437604197 → página del libro id 1 con acciones; ISBN inexistente con catalogo.editar → botón de alta precargada; código raro → mensaje amable, sin excepción |
| T-SCAN-05 | En /mostrador existe el input universal autosenfocado y las acciones del resultado respetan puede() (p. ej. sin deposito.registrar no aparece Registrar depósito) |
✔ DoD Fase 17: suite 0 FAIL con T-SCAN-01..05 y T-SEC-08 actualizado; regresión
completa verde (T-RESV-01..05, T-DIR-01..07, T-MOST-01); reporte fase17.md.

## 3. FASE 18 — REORGANIZACIÓN UX INTEGRAL
### 3.1 Navegación por rol (navbar, con puede())
- INVITADO: Catálogo · Visítanos · Cómo funciona · [Entrar] [Crear cuenta]
- USUARIO: Catálogo · Mis reservas · Mi historial · campana · saldo pill · avatar
  (Mi perfil, Cerrar sesión). NO ve Mostrador ni Panel.
- PERSONAL: [Mostrador] como botón primario · Catálogo · avatar. NO ve Panel
  (salvo que tenga permisos de administración, en cuyo caso sí: Panel).
- ADMIN: Panel · [Mostrador] · Catálogo · avatar.
- Usuario logueado que entra a / ⇒ redirige a /dashboard (302). El logo enlaza
  a /dashboard si hay sesión, a / si no.
### 3.2 /dashboard = "Mi BookSwap" (home del usuario)
Bloques en este orden: (1) Saldo grande + botón "Ver catálogo"; (2) Reservas
activas: cards con barcode Code 128, código en texto, cuenta atrás y Cancelar;
si no hay, empty state con CTA al catálogo; (3) "Pendiente de atención": si el
usuario está pendiente_nia ⇒ aviso para /completar-nia; (4) Últimos ingresos en
el catálogo (5 cards disponibles); (5) Últimos movimientos (5) con enlace a
/mi-historial.
### 3.3 Catálogo y ficha (P1)
- Barra de búsqueda persistente arriba + filtros (género, "solo disponibles")
  y orden; chips de género clicables.
- CARD con botón "Reservar" directo cuando hay copia disponible y el usuario
  tiene reserva.crear: abre MODAL de confirmación ("Reservar es gratis; pagarás
  1 🪙 al recogerla en el mostrador") → POST → toast con enlace "Ver mi código
  de barras" (a /mis-reservas). Reservar queda en 2 interacciones.
- Ficha de libro: CTA grande de Reservar (o "Sin copias ahora" con hint "¿Lo
  tienes en casa? Tráelo al mostrador y gana tokens"), copias con ubicación y
  condición, coste uniforme visible.
### 3.4 /mostrador = hub de tareas con escáner universal
Layout final (tablet-first, cards grandes ≥48px, en este orden):
  1) ESCÁNER UNIVERSAL (input gigante autosenfocado + botón cámara) — P2.
  2) TARJETAS DE TAREA (solo si puede()): 🚀 Entrega directa · 📦 Entregar
     reserva · ➕ Registrar depósito · 📚 Alta de catálogo.
  3) "Hoy en el mostrador": entregas y depósitos de hoy (contadores) + lista
     ACCIONABLE "Reservas que expiran hoy" (enlace directo a cada ficha).
  4) Cada tarea es UNA pantalla enfocada (los flujos v4.4 se conservan; se
     integra el escaneo ISBN según §2.3).
### 3.5 /admin = panel con sidebar (ADMIN y personal con permisos de gestión)
- ENCABEZADO: fila de ACCIONES RÁPIDAS (según puede()): 🚀 Entrega directa ·
  ➕ Nuevo libro · 📥 Importar CSV · 🧾 Nuevo NIA · 🔐 Permisos del personal.
- "Pendientes de atención": reservas que expiran hoy · usuarios pendientes_nia ·
  backup automático vencido (si dias_backup_auto activo y ultimo_backup_auto
  antiguo) — cada item con enlace a su gestión.
- SIDEBAR izquierda (colapsable en móvil) con 3 grupos y breadcrumbs:
  OPERACIÓN: Reservas · Catálogo · Movimientos
  PERSONAS: Usuarios · NIAs · Roles y permisos
  SISTEMA: Configuración · Backups · Auditoría · Métricas
- Componente de tabla estándar (filtros + búsqueda + paginación + acciones en
  fila) y MODAL de confirmación para toda acción destructiva. Nada de
  confirm() nativo.
### 3.6 Simplificación de menús del usuario
- /mis-depositos desaparece como elemento de navegación: REDIRIGE (301/302) a
  /mi-historial con filtro tipo=deposito activado. El historial ya lista
  depósitos y retiros.
- /como-funciona: página completa (3 pasos con iconos: Busca → Reserva → Recoge
  en mostrador con tus tokens o trayendo un libro; mini-FAQ; consejo del
  código de barras en el móvil). Enlazada desde home, navbar de invitado y footer.
### 3.7 Rendimiento y accesibilidad
- Re-ejecuta tests/benchmark.php tras la reorganización: ninguna ruta clave
  debe empeorar >20% frente a docs/PERFORMANCE.md; actualiza el documento.
- Foco visible en el escáner universal; aria-labels en tarjetas de tarea;
  contraste AA en ambos temas; navegación por teclado de la matriz de permisos.
### 3.8 Tests nuevos
| ID | Verifica |
|---|---|
| T-UX-01 | Navbar por rol: invitado sin Mostrador/Panel; usuario con Mis reservas e historial y sin Mostrador; personal con Mostrador; admin con Panel y Mostrador |
| T-UX-02 | Usuario logueado: GET / → 302 a /dashboard; /dashboard contiene saldo, la reserva demo RES-DEMO-0001 de u3, últimos movimientos y CTA al catálogo |
| T-UX-03 | En /catalogo, una card de libro disponible contiene formulario/botón que hace POST a la ruta de reserva; el flujo desde la card crea la reserva (estado activa, código) |
| T-UX-04 | /mostrador: escáner universal presente; Entrega directa es la PRIMERA tarjeta; las tarjetas respetan puede() |
| T-UX-05 | /admin: sidebar con los 3 grupos y sus enlaces, acciones rápidas y sección "Pendientes de atención"; breadcrumbs presentes en /admin/usuarios |
| T-UX-06 | /mis-depositos redirige a /mi-historial con filtro tipo=deposito; /como-funciona responde 200 y está enlazada desde home y footer |
| T-UX-07 | Confirmaciones destructivas usan modal (no confirm() nativo): la cancelación de reserva desde /mis-reservas pasa por modal con CSRF |
✔ DoD Fase 18: suite 0 FAIL con T-UX-01..07; regresión COMPLETA (funcional +
PERF + SEC + ROLE + SCAN) verde; benchmark actualizado sin regresión >20%;
reporte fase18.md; README (secciones "Escáner de barras", "Permisos del
personal") y DEPLOY_FTP.md actualizados; PROGRESS.md.

## 4. ORDEN DE TRABAJO Y REGLA FINAL
Fase 16 (permisos) → Fase 17 (escáner 1D) → Fase 18 (UX integral sobre lo
anterior). Tras cada fase: 0 FAIL, reporte en docs/PHASE_REPORTS/, PROGRESS.md,
y continúa sin esperar confirmación. Si un cambio de UI exige tocar un fichero
canónico no autorizado, detente, documéntalo como decisión y elige la
alternativa menos invasiva. Nada de este incremento puede dejar en rojo los
tests ya verdes: regresión = fase no terminada.
```
