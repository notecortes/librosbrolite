# Reporte de Fase 21 — Economía de reserva con bloqueo de tokens (v4.7)

**Fecha:** 26 de septiembre de 2026  
**Estado:** COMPLETADA  
**Resultado de tests:** 114 PASS / 0 FAIL / 0 SKIP (Suite 100% verde)

---

## 1. Objetivos Alcanzados

1. **Bloqueo de Tokens al Reservar:**
   - La reserva de un libro exige y bloquea atómicamente $coste\_libro$ tokens mediante el nuevo tipo de movimiento `bloqueo_reserva` con cantidad negativa ($-1$).
   - El límite efectivo de reservas simultáneas del lector pasa a ser $\min(max\_reservas\_activas, \lfloor saldo / coste\_libro \rfloor)$.
   - Prevención estricta de condiciones de carrera con transacciones ACID y bloqueos pesimistas `SELECT ... FOR UPDATE` en `usuarios` y `ejemplares`.

2. **Consolidación de Entrega en Mostrador:**
   - Al recoger un ejemplar reservado en el mostrador dentro del plazo, el bloqueo realizado previamente constituye el pago definitivo.
   - La entrega se consolida **SIN movimiento adicional en el libro mayor (ledger)**.
   - El cobro con libro desaparece del flujo de entrega de reservas (la reserva ya está prepagada); la interfaz del mostrador para reservas activas ofrece exclusivamente `Confirmar entrega (pagada: X 🪙)` y `Cancelar reserva`.
   - Se reduce en 1 interacción física el flujo de entrega de reserva (exactamente 2 interacciones: escanear → confirmar entrega pagada).
   - La entrega directa (sin reserva previa) se mantiene idéntica, admitiendo retiro con tokens o intercambio con libro aportado.

3. **Liberación y Penalización:**
   - Al cancelar una reserva (por usuario o personal/admin), se liberan los tokens bloqueados mediante el tipo de movimiento `liberacion_reserva` ($+1$).
   - Al expirar reservas vencidas (`bin/expirar.php`):
     - Con `penalizar_expiracion = '0'` (por defecto): se liberan los tokens bloqueados al saldo del lector (`liberacion_reserva`).
     - Con `penalizar_expiracion = '1'`: los tokens bloqueados no se reembolsan (se pierden), auditando el motivo y notificando claramente al lector.

4. **Interfaz de Usuario (UI) y Transparencia:**
   - **Navbar:** El pill de saldo muestra el saldo disponible con tooltip indicando `«X 🪙 comprometidos en N reserva(s) activa(s)»`.
   - **Dashboard:** Bloque de saldo enriquecido con Disponible, Comprometido en reservas y nota explicativa: *«Cada reserva descuenta 1 🪙 hasta que la recoges o la anulas»*.
   - **Modal de Reserva:** Texto actualizado a *«Se descontará temporalmente 1 🪙 hasta que recojas o anules la reserva»*.
   - **Mostrador:** Ficha de reserva limpia sin opciones de cobro redundantes ni falsos avisos de saldo insuficiente.

---

## 2. Cambios en Base de Datos

- **`database/01_schema.sql`**:
  - `movimientos_tokens.tipo` ENUM actualizado:
    ```sql
    ENUM('deposito','retiro','bono','bono_bienvenida','ajuste','bloqueo_reserva','liberacion_reserva') NOT NULL
    ```
- **`database/02_seed.sql`**:
  - Añadida configuración `('penalizar_expiracion', '0')`.
  - Transacción demo `RES-DEMO-0001` (u3, ejemplar 14) actualizada con `tokens = 1`.
  - Añadido movimiento en `movimientos_tokens` para u3 ($-1$, `bloqueo_reserva`, `transaccion_id = 2`, `saldo_resultante = 5`). Saldos demo conservados: u1=5, u2=5, u3=5, u4=5, u5=0.

---

## 3. Pruebas y Validación

- **Pruebas modificadas (IDs intactos):**
  - `T-RESV-01`: Valida registro de `bloqueo_reserva` con `saldo_resultante` coherente y descuento en saldo.
  - `T-RESV-02`: Setup con saldo suficiente para 4 reservas; deniega la 4ª por `max_reservas_activas` y la permite al elevar el límite.
  - `T-RESV-04`: Expiración con devolución bajo default (`penalizar_expiracion=0`) y retención sin movimiento con `penalizar_expiracion=1` más auditoría.
  - `T-RESV-05`: Cancelación por usuario registra `liberacion_reserva` y restaura saldo exacto.
  - `T-ENTR-01`: Entrega consolidada sin nuevo movimiento en ledger, `metodo_pago = 'tokens'`, ejemplar `retirado`.
  - `T-SCAN-03`: Ficha de reserva en mostrador muestra `Confirmar entrega (pagada)` y `Cancelar`, sin botones de cobro.
  - `T-ADMIN-05`: Cancelación administrativa libera tokens al lector y audita la acción.
  - `T-CTX-08`: Panel de mostrador actualizado con la semántica de entrega prepagada.

- **Nuevas Pruebas de Economía de Tokens (`T-TOK-01..05`):**
  - `T-TOK-01`: Saldo menor a `coste_libro` deniega la reserva sin alterar la BD; con saldo exacto la permite.
  - `T-TOK-02`: Ciclo completo (reserva → cancelación) mantiene invariante de suma en el ledger.
  - `T-TOK-03`: Con 2 tokens, 2 reservas OK y 3ª denegada por saldo aunque `max_reservas_activas=3`.
  - `T-TOK-04`: Entrega de reserva consolida sin movimiento contable adicional.
  - `T-TOK-05`: Doble reserva concurrente sobre la última copia con saldo para una: atomicidad garantizada.

- **Resultado final:** 114 pruebas ejecutadas, 114 superadas con éxito (0 fallos).
