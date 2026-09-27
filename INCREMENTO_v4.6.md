Tienes razón, y el diagnóstico es claro: las acciones viven en **módulos** (Catálogo, Mostrador, Admin) cuando deberían vivir en el **contexto** — junto al objeto sobre el que actúan. Y hay una redundancia conceptual que genera la mitad de la confusión: **"Registrar depósito" y "Añadir ejemplar" son la misma operación física** (meter una copia en el almacén), pero la app las trata como dos cosas distintas con dos pantallas distintas.

## 🧠 El nuevo modelo mental (2 verbos, no 6)

| | Qué entra/sale | Quién lo hace | Dónde |
|---|---|---|---|
| **📥 ENTRADA** | Una copia entra al almacén | Personal/Admin | Escáner, ficha del libro, admin |
| **📤 SALIDA** | Una copia sale del almacén | Personal/Admin | Escáner, ficha, mostrador |
| **📖 Consulta** | Ver historial/estado | Todos | Dashboard, fichas |

Una ENTRADA con campo "traído por" = depósito (+tokens). Sin él = alta de stock. **Un solo flujo.** Y el pago "con libro aportado" de una SALIDA es, naturalmente, una ENTRADA anidada. Todo se compone.

**Regla de oro nueva: la ficha del libro es el centro de gestión.** Si estás mirando un ISBN, todas sus acciones están ahí: añadir copias, editar, entregar una copia, ver trazabilidad de cada copia. Cero navegación a otros módulos.

---

# `INCREMENTO_v4.6.md` (guárdalo en la raíz del proyecto)

```markdown
# INCREMENTO v4.6 — BookSwap: ACCIONES CONTEXTUALES Y FLUJOS UNIFICADOS
# (a ejecutar tras completar v4.5 con suite en verde; añade la Fase 19)
# Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2/3/4/5: TODAS sus reglas siguen
# vigentes (DoD, regresión = fallo, archivos completos). EXCEPCIÓN AUTORIZADA en
# este incremento: si un refactor cambia una ruta o payload, puedes ACTUALIZAR la
# URL/payload del test afectado MANTENIENDO su ID y la semántica de sus
# aserciones, documentándolo en el reporte de fase. Nunca debilite un assert.

## 0. PRINCIPIOS (OBLIGATORIOS, sobreescriben lo anterior si chocan)
C1. ACCIONES EN CONTEXTO: toda entidad mostrada (libro, copia, reserva, usuario)
    lleva INLINE sus acciones disponibles según puede(). Prohibido obligar a
    navegar a un módulo para actuar sobre algo que ya estás viendo.
C2. UN FLUJO POR OPERACIÓN FÍSICA: "Añadir ejemplar" y "Registrar depósito" son
    LA MISMA operación (una copia entra). SE UNIFICAN en "Entrada de copias"
    con campo opcional "Traído por" (usuario): si se rellena → +bono_deposito
    al usuario y transacción tipo 'deposito'; si queda vacío → alta de stock
    (transacción tipo 'deposito' con usuario_id del staff que la registra o
    NULL, SIN tokens). Decide y documenta; los tests T-DEPO-01/02 deben seguir
    verificables (semántica: copia creada + tokens correctos).
C3. REGLA DE LAS 3 INTERACCIONES: toda tarea frecuente debe completarse en ≤3
    interacciones (clic/tecla Enter; escanear cuenta como 1). Debes documentar
    en docs/FLOW_MAP.md la tabla tarea→pasos→ nº interacciones para: entrada
    escaneada, entrada desde ficha, alta de libro nuevo + copias, entrega
    directa, entrega de reserva, reserva de usuario, cancelar reserva, ajuste
    de tokens, dar de baja copia.
C4. NADA MUERE SIN SUSTITUTO: cada pantalla/flujo que elimines redirige o queda
    integrado en otro. Regresión completa verde al cerrar la fase.

## 1. ENTRADA DE COPIAS — FLUJO UNIFICADO (sustituye a los 3 antiguos)
### 1.1 Una sola pantalla /libros/entrada (permiso catalogo.editar)
- Paso único: campo ISBN/código (escaneable, auto-submit con Enter) O búsqueda
  por título/autor con autocompletado.
- CASO A — el libro existe: muestra portada, título, copias actuales por estado
  y el formulario: nº de copias (1-20, default 1), condición (select, default
  'bueno'), ubicación (texto, opcional), "Traído por" (autocomplete de
  usuarios, OPCIONAL). Confirmar → crea N copias 'disponible'; si hay
  depositante → UNA transacción 'deposito' por copia + movimiento
  +bono_deposito por copia (atención: el bono es POR COPIA, documéntalo) y
  notificación al depositante. Auditado (accion 'entrada_copias', detalle JSON
  con libro, N, depositante, condición).
- CASO B — el libro NO existe y puede('catalogo.editar'): botón "Dar de alta
  este libro" → alta asistida (manual o APIs por ISBN) → AL TERMINAR, la misma
  pantalla de copias (CASO A) con el libro precargado. Nunca dejar al usuario
  en una ficha vacía tras crear el libro.
- Entradas a este flujo (todas llevan al mismo sitio): tarjeta "📥 Entrada de
  libros" de /mostrador · acción rápida de /admin · botón "Añadir copias" en
  la ficha del libro (precarga ISBN) · resultado del escáner universal.
### 1.2 Integración con salida pagada con libro
En el cobro "con libro aportado" (entrega directa y de reserva), el libro
aportado se registra con ESTE mismo componente (buscador/alta + 1 copia +
depositante = el receptor). Reutiliza el código; no dupliques formularios.

## 2. FICHA DEL LIBRO = CENTRO DE GESTIÓN CONTEXTUAL
### 2.1 Barra de gestión (solo con permisos, mismo layout para PERSONAL/ADMIN)
En la ficha /libro/{id}, encima de la información, una barra con las acciones
disponibles según puede() (NADA de esto existe hoy):
  ➕ Añadir copias (→ §1.1 con ISBN precargado) · ✏️ Editar libro ·
  📤 Entregar copia (→ entrega directa precargada con el libro; visible solo
  si hay copia 'disponible' y puede('entrega.confirmar')) ·
  📊 Ver movimientos del libro (historial consolidado de sus copias).
Para USUARIO sin permisos la barra no existe; la ficha sigue siendo pública.
### 2.2 Copias en la ficha (tabla con acciones en línea)
Agrupadas por estado con contador ("Disponibles (3) · Reservadas (1) ·
Retiradas (12) · Baja (2)"). Cada fila de copia 'disponible' o 'reservada'
muestra: condición, ubicación, depositante, fecha, y acciones en línea:
  ✏️ Editar (modal: condición + ubicación) · 📤 Entregar (si disponible) ·
  🚫 Dar de baja (modal con motivo obligatorio → estado 'baja', auditado;
  si estaba 'reservada' → denegado: primero cancelar la reserva).
### 2.3 Página de copia /ejemplar/{id} (staff): TRAZABILIDAD
Timeline vertical (componente del tema, sin librerías nuevas) con TODAS las
transacciones de esa copia: alta/entrada (quién la trajo), reservas (códigos,
estados), entrega (método de pago, gestor), bajas. Enlace "Ver copia" desde
cada fila de la tabla 2.2 y desde los resultados del escáner.
### 2.4 Escáner universal (ajuste fino sobre v4.5)
Al escanear un ISBN existente, el resultado ES la ficha del libro (con su barra
de gestión §2.1 y tabla de copias §2.2). Un escaneo = todo el contexto y todas
las acciones. Si no hay copias disponibles, resalta ➕ Añadir copias (probable
motivo del escaneo).

## 3. MOSTRADOR REORGANIZADO EN ENTRADAS/SALIDAS
Reemplaza las tarjetas de tareas por DOS grupos (bajo el escáner universal):
  📥 ENTRADAS: "Añadir copias / Registrar depósito" (→ §1.1)
  📤 SALIDAS: "🚀 Entrega directa" (primera, resaltada) · "📦 Entregar reserva"
Actualiza la afirmación de T-UX-04 (Entrega directa PRIMERA) al nuevo layout:
primera de SALIDAS; documenta el cambio de assert en el reporte (ID intacto).
"Hoy en el mostrador" y "Reservas que expiran hoy" se conservan al final.

## 4. CATÁLOGO ADMIN — TABLA ACCIONABLE
Cada fila de libro: Añadir copias · Editar · Ver ficha · Nº copias por estado
(chip con badge). Acciones rápidas del panel: sustituye "➕ Nuevo libro" por
"📥 Entrada de libros" (mismo flujo unificado).

## 5. TESTS
ALTAS (Fase 19):
| ID | Verifica |
|---|---|
| T-CTX-01 | Ficha /libro/1 con personal (catalogo.editar): barra con Añadir copias/Editar/Entregar; con USUARIO: ninguna de esas acciones |
| T-CTX-02 | Entrada unificada: 3 copias con depositante u3 → 3 copias 'disponible', 3 transacciones 'deposito', ledger de u3 +3×bono_deposito, saldo coherente (T-LEDGER-01), auditado |
| T-CTX-03 | Entrada unificada SIN depositante → copias creadas, CERO movimientos de tokens |
| T-CTX-04 | Alta de libro nuevo (CASO B) termina en el formulario de copias con el libro precargado (no en un listado) |
| T-CTX-05 | /ejemplar/{id}: timeline con entrada y, tras reservar+entregar, reserva y entrega; staff-only (USUARIO → 403) |
| T-CTX-06 | Acciones en línea: editar condición/ubicación de copia efectivo y auditado; dar de baja con motivo → 'baja'; sobre copia 'reservada' → denegado |
| T-CTX-07 | /libros/entrada: 200 con catalogo.editar; 403 sin él; /mostrador muestra grupos ENTRADAS/SALIDAS |
T-DEPO-01/02 y T-DEPO-... ajusta URL/payload si el endpoint unificado lo exige
(IDs y semántica intactos). T-SCAN-04: el resultado del ISBN ahora ES la ficha
del libro — ajusta el assert si hace falta (ID intacto).
REGRESIÓN OBLIGATORIA: T-RESV-*, T-DIR-*, T-LEDGER-*, T-SCAN-*, T-UX-*, T-ROLE-*
en verde al cerrar la fase.

## 6. FASE 19 — DEFINICIÓN DE HECHO
1. Flujos unificados (§1) + ficha contextual (§2) + mostrador ENTRADAS/SALIDAS
   (§3) + tabla admin (§4).
2. docs/FLOW_MAP.md (C3) con la tabla de tareas e interacciones, incluida la
   verificación manual paso a paso de las 9 tareas.
3. Suite completa 0 FAIL (incluidos T-CTX-01..07 y regresión completa).
4. Reporte docs/PHASE_REPORTS/fase19.md (decisiones: §0.C2 entre otras,
   asserts modificados con su ID) + PROGRESS.md.
5. Benchmark sin regresión >20% en rutas clave (actualiza docs/PERFORMANCE.md).
```

