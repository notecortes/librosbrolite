# Reporte de Fase 19 — Acciones Contextuales y Flujos Unificados

**Fecha:** 25 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.6)  
**Estado:** Completada (115 PASS, 0 FAIL, 0 SKIP)  
**Suite de Tests:** 100% Verde (`./tests/run_all.sh`)

---

## 1. Objetivos y Alcance de la Fase 19

La **Fase 19** culmina el Incremento v4.6 con la premisa de simplificar radicalmente la interacción física del sistema y transformar la navegación fragmentada en una experiencia contextual fluida:

1. **Unificación del flujo físico de entrada de copias (§0.C2 y §1):**
   - Fusión completa de «Añadir ejemplar» y «Registrar depósito» en una única interfaz unificada: `/libros/entrada`.
   - Incorporación de stock tanto para el centro como para depósitos de usuarios mediante el campo opcional *"Traído por"* (búsqueda por NIA, ID o email).
   - Acreditación de $+N \times \text{bono\_deposito}$ tokens únicamente si se especifica un depositante válido, o alta de stock de biblioteca sin impacto en el ledger si queda vacío.
   - Manejo transparente de los dos escenarios físicos: **Caso A** (el libro ya existe en catálogo) y **Caso B** (el libro es nuevo, con derivación directa a alta asistida y precarga automática al terminar).
   - Redirecciones 302 permanentes desde las antiguas rutas fragmentadas (`/admin/ejemplares/nuevo` y `/admin/deposito`) hacia `/libros/entrada`.

2. **Ficha del libro como centro neurálgico de gestión (§2):**
   - Implementación de la barra de herramientas para personal (`#barra-gestion-libro`), visible exclusivamente bajo `puede('catalogo.editar') || puede('entrega.confirmar')`.
   - Acciones contextuales en la barra: *Añadir copias*, *Ver movimientos*, *Editar ficha*, *Búsqueda en Google Books*, *Imprimir ficha*.
   - Rediseño de la tabla de ejemplares con chips visuales de estado y acciones inline por copia: *Ver copia* (trazabilidad), *Editar ubicación/condición* (modal), *Entregar en mostrador* y *Dar de baja* (modal con motivo obligatorio).
   - Modal contextual `#modalMovimientosLibro` que expone el historial cronológico completo de préstamos, depósitos y entregas del libro.

3. **Página de trazabilidad vertical por ejemplar (§2.3):**
   - Nueva ruta `/ejemplar/{id}` protegida para staff (HTTP 403 para usuarios estándar).
   - Línea temporal vertical (*timeline*) con todos los hitos del ciclo de vida de la copia: fecha de ingreso, depositante/origen, reservas, entregas, cambios de condición/ubicación y bajas motivadas.
   - Endpoints de acción rápida en línea: `POST /ejemplar/{id}/editar` y `POST /ejemplar/{id}/baja`.

4. **Reorganización de Mostrador en ENTRADAS y SALIDAS (§3):**
   - Agrupación semántica clara en dos bloques de tarjetas:
     - **SALIDAS:** *Entrega directa rápida* (primera opción visible para satisfacer `posDirecta < posReserva`) y *Entrega de reserva activa*.
     - **ENTRADAS:** *Entrada de copias* (unificada con depósito de usuarios).
   - El escáner universal deriva los códigos de ISBN a la ficha contextual del libro (`/libro/{id}`) o a `/libros/entrada`.

5. **Regla de las 3 Interacciones documentada (§0.C3):**
   - Publicación de `docs/FLOW_MAP.md` auditando paso a paso las 9 tareas frecuentes del centro, demostrando que ninguna requiere más de 3 interacciones (clicks o `Enter`).

---

## 2. Decisiones de Arquitectura y Negocio (§0.C2)

| Aspecto | Decisión Adoptada | Justificación y Reglas |
|---|---|---|
| **Unificación de Entrada** | Una sola pantalla `/libros/entrada` | Elimina la duplicidad entre "alta de ejemplar de biblioteca" y "depósito de lector". Ambos comparten la misma realidad física: una copia entra al almacén. |
| **Tratamiento del Depositante** | Campo opcional `depositante` | Si se ingresa un NIA o ID de usuario válido: se crea una transacción inmutable `deposito`, se abonan $+N \times \text{bono\_deposito}$ tokens al usuario en el ledger contable y se le notifica. Si queda vacío: se registra la transacción con `usuario_id` del personal operador (o null) y `tokens = 0` (sin movimiento contable). |
| **Bono de Depósito Multicopia** | Por ejemplar ($N \times \text{bono}$) | Si un usuario deposita 3 libros válidos, recibe 3 veces el bono configurado en el sistema, incentivando el aporte de fondos. |
| **Transición de Caso B** | Redirección con estado precargado | Si el libro no existe, el CTA a `/admin/libros/nuevo?isbn=...` realiza el alta y redirige de vuelta a `/libros/entrada?libro_id={nuevoId}` para que el operador no tenga que reiniciar la búsqueda ni se quede en una ficha vacía. |
| **Restricción de Baja de Copia** | Bloqueo si está `reservado` | Para evitar inconsistencias de inventario, si una copia tiene una reserva activa, la acción de baja es rechazada; el personal debe cancelar previamente la reserva activa. |

---

## 3. Modificaciones y Verificaciones de Tests

De acuerdo con la cláusula de autorización del incremento v4.6 (*«si un refactor cambia una ruta o payload, puedes ACTUALIZAR la URL/payload del test afectado MANTENIENDO su ID y la semántica de sus aserciones»*):

1. **Nuevos Tests de Fase 19 (`T-CTX-01` a `T-CTX-07`):**
   - `T-CTX-01`: Ficha de libro con `#barra-gestion-libro` visible para personal y oculta para usuarios estándar. (PASS)
   - `T-CTX-02`: Entrada unificada con depositante: crea N copias, acredita tokens al lector, registra transacciones y audita en `registro_auditoria`. (PASS)
   - `T-CTX-03`: Entrada unificada sin depositante: crea copias de stock de centro sin alterar el saldo de tokens. (PASS)
   - `T-CTX-04`: Redirección tras alta de libro nuevo aterrizando en `/libros/entrada?libro_id=...` con los metadatos precargados (Caso B). (PASS)
   - `T-CTX-05`: Trazabilidad vertical por copia en `/ejemplar/{id}`: HTTP 200 con timeline para personal, HTTP 403 para usuarios. (PASS)
   - `T-CTX-06`: Acciones en línea sobre ejemplar: edición de condición/ubicación y baja motivada; denegación ante copia reservada. (PASS)
   - `T-CTX-07`: Control de acceso y reorganización de mostrador: `/libros/entrada` restringido con `catalogo.editar`; mostrador estructurado en grupos `ENTRADAS` y `SALIDAS`. (PASS)

2. **Compatibilidad Retrospectiva y Regresión Completa:**
   - `T-DIR-06`: Mantiene la presencia de `card-operacion-deposito` y el texto literal `Registrar Depósito`.
   - `T-UX-04`: Verifica que la tarjeta de Entrega Directa precede a la de Reserva en el orden visual del DOM.
   - `T-DEPO-01` / `T-DEPO-02`: Conservan su semántica intacta (creación de copias y asignación de tokens/rechazo motivado).
   - `T-SCAN-04`: El escáner de ISBN resuelve la URL de la ficha del libro (`/libro/{id}`) y sugiere la acción contextual correspondiente.

---

## 4. Rendimiento y Benchmark

Se ejecutó el benchmark de rendimiento (`tests/benchmark.php`) monitorizando 14 rutas en total (incluyendo las nuevas `/libros/entrada` y `/ejemplar/{id}`):

- **Latencia Mediana `/libros/entrada`:** `103.88 ms` (Peso: `15.03 KB`).
- **Latencia Mediana `/ejemplar/3`:** `106.10 ms` (Peso: `20.54 KB`).
- **Rutas clave preexistentes:** Sin regresión significativa respecto a la línea base (< 112 ms en contenedor Docker/macOS).
- **Consistencia HTTP:** 100% de las rutas evaluadas responden con código `200 OK`.
- Documentado formalmente en `docs/PERFORMANCE.md` (§6).

---

## 5. Resumen de Ejecución y DoD

- **Tests Totales:** 115 tests.
- **Resultado:** **PASS: 115, FAIL: 0, SKIP: 0**.
- **Documentación Entregada:**
  - `docs/FLOW_MAP.md` (Verificación paso a paso y matriz de las 9 tareas críticas con $\le 3$ interacciones).
  - `docs/PERFORMANCE.md` (Ampliación con benchmark de Fase 19).
  - `docs/PHASE_REPORTS/fase19.md` (Este informe).
  - `PROGRESS.md` (Actualizado con estado final de Fase 19).
