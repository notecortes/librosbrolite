Entendido: hoy la entrega exige que el usuario haya reservado antes online (código → ficha → cobro), así que si alguien llega al mostrador con sus tokens y se lleva un libro *sin reserva previa*, el personal tiene que hacer malabares. Añadimos la operación **"Entrega directa"** (entrega sin reserva, en una sola pantalla) como **primera opción** del Mostrador y con acceso de 1 clic desde el panel ADMIN.

Decisiones de diseño que he tomado:

- **Flujo de 3 pasos en una pantalla**: elegir copia (búsqueda con autocompletado) → elegir usuario (muestra saldo y NIA) → cobro (tokens o libro aportado). Un solo POST atómico al confirmar.
- **Nuevo tipo de transacción** `entrega_directa` en BD (autorizado expresamente en el incremento, como hicimos con `nias`): así no falseamos estadísticas creando "reservas instantáneas".
- Si el usuario seleccionado ya tiene **una reserva activa para esa misma copia**, el sistema lo avisa y ofrece redirigir al flujo de reserva normal (evita entregas duplicadas).

---

# `INCREMENTO_v4.4.md` (guárdalo en la raíz del proyecto)

```markdown
# INCREMENTO v4.4 — BookSwap: ENTREGA DIRECTA RÁPIDA (sin reserva previa)
# (a ejecutar tras completar v4.3 con suite en verde; añade la Fase 15)
# Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2/v4.3: TODAS sus reglas siguen
# vigentes (DoD por fase, regresión = fallo, archivos completos, no editar tests
# para que pasen). No se elimina ni altera ninguna funcionalidad existente.

## 0. PROBLEMA Y OBJETIVO
Hoy solo se puede entregar un libro si existe una RESERVA previa del usuario.
Si una persona llega al mostrador con tokens (o con un libro) y quiere llevarse
una copia disponible, el personal no tiene un camino corto. OBJETIVO: crear la
operación ENTREGA DIRECTA y ponerla como PRIMERA opción del Modo Mostrador,
con acceso en un clic desde el panel ADMIN. Debe poder realizarla PERSONAL y
ADMIN (permiso entrega.confirmar).

## 1. REGLAS DE NEGOCIO DE LA ENTREGA DIRECTA
1. Solo copias en estado 'disponible'. Una copia 'reservada' (por nadie) → denegado.
2. Solo usuarios destino con rol USUARIO, activos y CON NIA vinculado
   (pendiente_nia → denegado con mensaje claro).
3. Pago (idéntico a la entrega de reserva):
   a) TOKENS: movimiento 'retiro' de −coste_libro. Si saldo disponible
      < coste_libro → denegado sin cambios.
   b) LIBRO: el personal registra el libro aportado (búsqueda en catálogo o
      alta al vuelo por ISBN/manual) → DEPÓSITO (+bono_deposito, copia nueva
      'disponible') Y RETIRO (−coste_libro): dos movimientos del ledger.
      Si bono_deposito < coste_libro, se exige saldo disponible suficiente
      para la diferencia.
4. ATOMICIDAD: todo el proceso es UNA transacción de BD. Cualquier fallo →
   rollback total (sin copia movida, sin ledger, sin notificación).
5. Registro: nueva fila en transacciones con tipo 'entrega_directa', estado
   'entregada', fecha_entrega = ahora, metodo_pago (tokens|libro),
   gestionada_por = staff, usuario_id = receptor, ejemplar_id = copia
   entregada. NOTA: si el pago es con libro, el depósito del libro aportado
   es SU PROPIA transacción tipo 'deposito' (como ya existe).
6. Notificación al receptor: "Entrega directa registrada: {titulo}. Método:
   {tokens|libro aportado}." + registro en auditoría (accion
   'entrega_directa', detalle JSON con metodo_pago, ejemplar y receptor).
7. Si el usuario seleccionado tiene UNA RESERVA ACTIVA para ESA MISMA copia →
   la UI lo advierte y ofrece botón "Ir a entregar su reserva" (flujo
   existente). No bloquea otra copia distinta del mismo libro.

## 2. CAMBIO DE BD (ÚNICO CAMBIO AUTORIZADO en ficheros canónicos)
En database/01_schema.sql, la tabla transacciones cambia SOLO su columna tipo:
  tipo ENUM('deposito','reserva','entrega_directa') NOT NULL
(documenta el cambio en el reporte de fase). Nada más en los .sql: el seed
NO se modifica. En Fase 15 NO hace falta db_reset para producción, pero los
tests siguen usando db_reset (sin problema).

## 3. INTERFAZ — ENTREGA DIRECTA PRIMERA OPCIÓN
3.1 /mostrador — REORDENAR las operaciones. Orden final (tarjetas/pestañas
    grandes, tablet-first):
    1) 🚀 ENTREGA DIRECTA (nueva, PRIMERA y resaltada como acción principal)
    2) 📦 ENTREGAR RESERVA (código) — flujo existente intacto
    3) ➕ REGISTRAR DEPÓSITO — flujo existente intacto
    4) 📚 ALTA DE CATÁLOGO — flujo existente intacto
3.2 Pantalla de Entrega Directa (una sola página, 3 pasos visibles, validación
    en servidor de todo):
    PASO 1 · LIBRO: buscador con autocompletado (título/autor/ISBN) que lista
    SOLO copias 'disponible'; cada resultado muestra portada, título, autor,
    condición y ubicación. Selección con un clic (o Enter).
    PASO 2 · USUARIO: buscador por nombre o email; el resultado muestra saldo
    disponible (tokens no bloqueados) y estado de activación. Si el usuario
    tiene reserva activa para esa copia → aviso + botón "Ir a entregar su
    reserva" (§1.7). Si no tiene NIA → marcado como no elegible.
    PASO 3 · COBRO: dos botones grandes — "🪙 Cobrar con tokens" (deshabilitado
    con motivo si saldo < coste_libro) y "📚 Cobra con libro aportado" (abre
    inline el buscador/alta por ISBN + selector de condición, igual que en
    entrega de reserva). Resumen final: copia, usuario, importe, neto de
    tokens. Botón CONFIRMAR (POST con CSRF) → confetti + toast de éxito y
    formulario listo para la siguiente operación.
3.3 /admin — ACCESO EN UN CLIC: fila de ACCIONES RÁPIDAS al principio del
    panel (encima de las métricas) con botones: "🚀 Entrega directa"
    (enlaza a la misma pantalla de /mostrador), "➕ Nuevo libro", "📥
    Importar CSV", "🧾 Nuevo NIA". El botón de Entrega directa es el primero.
3.4 NAVEGACIÓN: en el navbar, el rol ADMIN debe ver TAMBIÉN el enlace
    "Mostrador" (hoy solo lo ve PERSONAL). PERSONAL conserva el suyo.
3.5 El historial (/mi-historial y la vista ADMIN) debe mostrar las entregas
    directas como retiros normales (ya lo hace vía movimientos) y el campo
    libro debe resolverse correctamente para tipo 'entrega_directa'.

## 4. TESTS NUEVOS (añadir al runner; el resto queda intacto)
| ID | Verifica |
|---|---|
| T-DIR-01 | Entrega directa con tokens: copia 'retirado', transaccion 'entrega_directa' 'entregada' con metodo_pago y gestionada_por, ledger con retiro −coste_libro y saldo_resultante correcto, notificación al receptor, auditado |
| T-DIR-02 | Entrega directa con libro aportado: DOS movimientos (deposito +bono_deposito del libro aportado con su copia 'disponible' Y retiro −coste_libro), copia entregada 'retirado' |
| T-DIR-03 | Saldo insuficiente (tokens) → denegado sin ningún cambio en BD (copia intacta, ledger intacto) |
| T-DIR-04 | Copia 'reservada' → denegado; receptor pendiente_nia → denegado con mensaje |
| T-DIR-05 | USUARIO llamando al endpoint de entrega directa → 403; PERSONAL y ADMIN → 200 |
| T-DIR-06 | /mostrador pinta Entrega Directa como PRIMERA opción; /admin contiene la acción rápida "Entrega directa"; navbar ADMIN incluye Mostrador |
| T-DIR-07 | El aviso de reserva activa: usuario con reserva activa sobre esa copia → la respuesta incluye sugerencia de ir al flujo de reserva |
Regresión: T-LEDGER-01, T-ENTR-01..03, T-MOST-01 y T-HIST-02 deben seguir en
verde tras la fase.

## 5. FASE 15 — DEFINICIÓN DE HECHO
Fase 15 — Entrega directa: cambio de ENUM (§2), backend (§1), UI (§3), tests
  T-DIR-01..07.
✔ DoD: ./tests/run_all.sh → 0 FAIL incluidos T-DIR-01..07 y TODA la regresión
  (funcional + PERF + SEC); reporte en docs/PHASE_REPORTS/fase15.md; PROGRESS.md
  actualizado. Verificación manual documentada en el reporte: recorrer el flujo
  completo en /mostrador con datos del seed y capturar la salida del benchmark
  de la nueva pantalla (tests/benchmark.php debe seguir funcionando).
```

---
