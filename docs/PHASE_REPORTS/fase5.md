# Informe de Fase 5: Mostrador y Economía

## 1. Estado y Resumen
- **Fase completada:** Fase 5 — Operativa de Mostrador, Depósitos, Entregas y Ledger de Tokens.
- **Resultado de la suite de tests:** 52 PASS, 0 FAIL, 8 SKIP (`SUITE VERDE`).
- **Tests activados y superados:**
  - `T-ENTR-01`: Entrega con tokens → retiro −coste_libro, copia 'retirado', 'entregada'.
  - `T-ENTR-02`: Entrega con libro NO admitido sin alta → rechazada, BD sin cambios.
  - `T-ENTR-03`: Entrega con libro admitido → depósito +bono_deposito Y retiro −coste_libro.
  - `T-ENTR-04`: Entrega tras fecha_limite → denegada.
  - `T-DEPO-01`: Depósito de libro admitido → +bono_deposito, copia 'disponible'.
  - `T-DEPO-02`: Depósito de libro no catalogado → alta al vuelo y aceptar, o rechazar (auditado).
  - `T-LEDGER-01`: Tras N operaciones: saldo = SUM(movimientos) exacto por usuario.
  - `T-LEDGER-02`: Ningún flujo deja saldo negativo (intento → rechazo sin cambios).
  - `T-MOST-01`: `/mostrador`: 200 PERSONAL/ADMIN, 403 USUARIO.
  - `T-AUDIT-01`: Entrega confirmada en `registro_auditoria` con método de pago.
  - `T-RGRC-01`: Regresión contable: `saldo_resultante` del último movimiento = `SUM(cantidad)` en `movimientos_tokens` por usuario.
  - `T-RGRC-02`: Regresión: ciclo de reserva y cancelación mantiene coherencia de ejemplar y transacción.

---

## 2. Componentes Implementados

### 2.1. Helpers
- [`app/helpers/ledger.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/ledger.php):
  - `ledger_obtener_saldo(PDO $pdo, int $usuarioId)`:
    - Cálculo matemático exacto mediante `SUM(cantidad)` sobre la tabla inmutable `movimientos_tokens`.
  - `ledger_registrar_movimiento(PDO $pdo, int $usuarioId, int $cantidad, string $tipo, ?int $transaccionId, string $concepto)`:
    - Inserción atómica con bloqueo pesimista `FOR UPDATE` sobre el último movimiento.
    - Validación inviolable: si `saldo_resultante < 0`, aborta con excepción y realiza rollback.
    - Soporte para tipos canónicos: `deposito`, `retiro`, `bono`, `bono_bienvenida`, `ajuste`.
  - `ledger_listar_movimientos(PDO $pdo, int $usuarioId, int $limite, int $offset)`:
    - Consulta paginada del historial del usuario vinculada a transacciones.
- [`app/helpers/mostrador.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/mostrador.php):
  - `mostrador_buscar_reserva(PDO $pdo, string $codigo)`:
    - Búsqueda por código de reserva o payload QR, verificación de vencimiento de fecha límite y consulta de saldo actual del socio.
  - `mostrador_completar_entrega(PDO $pdo, int $transaccionId, string $metodoPago, ?int $libroDepositadoId, ?string $condicion, int $gestionadaPorId)`:
    - Ejecución bajo transacción PDO atómica.
    - Control estricto de fecha límite (vencida lanza excepción impidiendo la entrega).
    - Opción `tokens`: Retiro de tokens (`-coste_libro`), comprobando que el usuario tenga saldo suficiente.
    - Opción `libro`: Depósito del libro aportado (`+bono_deposito`, nueva copia en `ejemplares` en estado `disponible`) y retiro (`-coste_libro`).
    - Actualización de ejemplar reservado a `retirado` y transacción a `entregada`.
    - Registro en `registro_auditoria` con clave `'metodo_pago'`.
  - `mostrador_registrar_deposito(PDO $pdo, int $usuarioId, int $libroId, string $condicion, string $ubicacion, int $gestionadaPorId)`:
    - Creación de nuevo ejemplar en estado `disponible`, transacción de tipo `deposito` y abono en ledger (`+bono_deposito`).
  - `mostrador_rechazar_deposito(PDO $pdo, int $usuarioId, ?string $tituloLibro, string $motivo, int $gestionadaPorId)`:
    - Registro motivado en `registro_auditoria` bajo la acción `deposito_rechazado`.

### 2.2. Vistas de Modo Mostrador
- [`app/views/admin/mostrador.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/mostrador.php):
  - Panel responsive protegido por roles `PERSONAL` y `ADMIN`.
  - Buscador de reservas por código alfanumérico o escaneo QR.
  - Ficha de reserva con alertas en caso de expiración, datos del libro y del socio (número de socio y saldo disponible).
  - Selector de pago con tokens (deshabilitado si saldo insuficiente) o intercambio libro por libro.
  - Panel de recepción de depósitos ciudadanos con alta y acreditación de bonos.
  - Modal colapsable para rechazar libros deteriorados con registro de motivo.
  - Integración nativa con `window.BS.confeti()` al completar entregas con éxito.

### 2.3. Enrutamiento (`app/router.php`)
- `/mostrador` (GET): Pantalla principal de mostrador para `PERSONAL` y `ADMIN`.
- `/mostrador/entregar` y `/mostrador/confirmar` (POST): Procesamiento de entrega y cobro.
- `/mostrador/deposito` y `/mostrador/deposito-directo` (POST): Registro de nuevo depósito en sala.
- `/mostrador/rechazar-deposito` (POST): Registro de rechazo en auditoría.
- `/api/mostrador/buscar` (GET): Endpoint JSON para validación asíncrona de códigos.

---

## 3. Verificación
- Ejecutado `./tests/run_all.sh` obteniendo:
  `PASS: 52 | FAIL: 0 | SKIP: 8`
- `SUITE VERDE` confirmada sin errores.
