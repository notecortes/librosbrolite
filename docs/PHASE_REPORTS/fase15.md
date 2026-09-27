# Reporte de Fase 15 — Entrega Directa Rápida (sin reserva previa)

**Fecha:** 25 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.4)  
**Estado:** Completada (0 FAIL, 0 SKIP, 91 PASS)

---

## 1. Objetivos y Alcance de la Fase 15

La **Fase 15** implementa la operación de **Entrega Directa Rápida** en mostrador sin requerir una reserva digital previa por parte del lector, resolviendo el cuello de botella operativo cuando un usuario acude físicamente al mostrador con tokens disponibles o con un libro físico para canjear en el acto.

Principales objetivos cumplidos:
1. **Modelo de Datos (§2):** Ampliación del enumerado canónico de transacciones para incluir `'entrega_directa'`.
2. **Backend Atómico (§1):** Operación transaccional completa con bloqueos de concurrencia (`FOR UPDATE`), control de elegibilidad (rol `USUARIO`, cuenta activa y NIA vinculado obligatorio), validación de copia `disponible`, control de saldo en el ledger, notificación al lector y registro de auditoría.
3. **Detección de Reserva Activa Existente:** Si el lector ya poseía una reserva sobre esa misma copia física, se detecta y se ofrece de forma proactiva redirigir al flujo de entrega de reserva para evitar duplicidades.
4. **Experiencia de Usuario (§3):**
   - `/mostrador`: Reordenación operativa colocando la **Entrega Directa** como **PRIMERA opción** destacada, seguida de Entregar Reserva, Registrar Depósito y Alta de Catálogo. Flujo visible de 3 pasos en una pantalla única con búsqueda reactiva.
   - `/admin`: Fila de acciones rápidas superior sobre las métricas, encabezada por `🚀 Entrega directa`.
   - Navbar global: Permite el acceso a `/mostrador` a administradores (`ADMIN`) además de al personal (`PERSONAL`).
   - `/mi-historial`: Resolución transparente del título del libro y autor para transacciones de tipo `entrega_directa`.
5. **Suite de Pruebas (§4):** Cobertura exhaustiva con los tests `T-DIR-01..07` y validación de regresión total alcanzando **91 PASS / 0 FAIL / 0 SKIP**.

---

## 2. Modificación Canónica de Base de Datos

De acuerdo con lo estipulado en el §2 de `INCREMENTO_v4.4.md`, se realizó el **único cambio autorizado en ficheros canónicos**:

* **Fichero modificado:** `database/01_schema.sql` (línea 94).
* **Definición anterior:**
  ```sql
  tipo ENUM('deposito','reserva') NOT NULL,
  ```
* **Definición canónica actualizada:**
  ```sql
  tipo ENUM('deposito','reserva','entrega_directa') NOT NULL,
  ```

Este cambio permite registrar transacciones verídicas e independientes sin distorsionar las métricas ni crear "reservas instantáneas" ficticias. El seed (`database/02_seed.sql`) permanece inalterado.

---

## 3. Implementación del Backend Atómico

Se implementó y amplió en `app/helpers/mostrador.php` la función transaccional:

```php
function mostrador_entrega_directa(
    PDO $pdo,
    int $usuarioId,
    int $ejemplarId,
    int $gestionadaPorId,
    string $metodoPago = 'tokens',
    ?int $libroDepositadoId = null,
    string $condicionLibroDepositado = 'bueno'
): array
```

### Reglas de negocio aplicadas:
1. **Validación de Usuario Receptor:**
   - Debe existir en el sistema.
   - Debe tener rol `USUARIO`.
   - Debe estar en estado activo (`activo = 1`).
   - Debe poseer un NIA vinculado (`nias.usuario_id`). Si no lo posee, la operación es rechazada con el mensaje explícito indicando `pendiente_nia`.
2. **Validación y Bloqueo de Ejemplar:**
   - Bloqueo exclusivo con `SELECT ... FOR UPDATE` para evitar condiciones de carrera.
   - Estado obligatorio: `'disponible'`. Cualquier otro estado genera denegación inmediata.
3. **Prevención de Duplicidad de Reservas:**
   - Comprobación mediante `mostrador_verificar_reserva_activa($pdo, $ejemplarId, $usuarioId)`. Si existe una reserva activa para esa copia, se rechaza con la sugerencia: *"El usuario ya tiene una reserva activa para este ejemplar (código: ...). Sugerencia: Ir a entregar su reserva."*
4. **Modalidad de Pago y Ledger Contable:**
   - **Pago con Tokens:** Se verifica saldo suficiente no bloqueado (`saldo_disponible >= coste_libro`). Se registra el movimiento contable `'retiro'` de `-coste_libro`.
   - **Pago con Libro Aportado (Canje):** Se da de alta el nuevo ejemplar físico en `'disponible'`, se registra la transacción de `'deposito'`, se añade el movimiento contable de depósito (`+bono_deposito`) y se aplica el retiro del libro llevado (`-coste_libro`). Si el bono es inferior al coste, se exige saldo previo suficiente para cubrir la diferencia neta.
5. **Transacción y Registro:**
   - Se inserta la transacción con `tipo = 'entrega_directa'`, `estado = 'entregada'`, `metodo_pago`, `fecha_entrega = NOW()` y `gestionada_por = $gestionadaPorId`.
   - La copia entregada pasa a estado `'retirado'`.
6. **Notificación y Auditoría:**
   - Notificación al receptor: `"Entrega directa registrada: {titulo}. Método: {tokens|libro aportado}."`
   - Registro en `registro_auditoria`: acción `'entrega_directa'` con detalle JSON exhaustivo (ejemplar, receptor, método de pago, libro y tokens).
7. **Atomicidad:**
   - Todo el proceso transcurre bajo una transacción PDO explícita. Si cualquier paso falla, se ejecuta `rollBack()` completo sin dejar huellas parciales en ejemplares, transacciones ni ledger.

---

## 4. Interfaz de Usuario y Navegación

### 4.1 Reordenación en Modo Mostrador (`/mostrador`)
Las operaciones se reordenaron en tarjetas y pestañas tablet-first:
1. 🚀 **Entrega Directa** (primera posición, destacada visualmente con gradiente principal).
2. 📦 **Entregar Reserva** (flujo con código o escaneo de código de barras).
3. ➕ **Registrar Depósito** (recepción y tasación de donaciones).
4. 📚 **Alta de Catálogo** (incorporación rápida al catálogo).

### 4.2 Flujo de 3 Pasos en Pantalla Única
* **Paso 1 · Copia a entregar:** Buscador reactivo que filtra en tiempo real solo copias `disponible`, mostrando carátula, título, autor, condición física y ubicación. Selección mediante un clic.
* **Paso 2 · Lector receptor:** Buscador por nombre, email o NIA. Muestra saldo disponible de tokens y distintivo de elegibilidad por NIA. Verifica en tiempo real mediante `/api/mostrador/verificar-reserva` si el lector ya tiene reserva sobre esa copia, desplegando un aviso asistencial con botón de enlace directo a la entrega de la reserva.
* **Paso 3 · Método de cobro:** Selector entre "🪙 Cobrar con tokens" o "📚 Cobrar con libro aportado (canje)". Si el saldo es insuficiente, se bloquea el botón informando del motivo. Incluye caja resumen final con el neto de tokens y botón de confirmación con token CSRF, retroalimentación mediante `toast` flotante y animación de confetti.

### 4.3 Acceso Rápido en Administración (`/admin`)
En la parte superior del panel administrativo, por encima de las métricas agregadas, se integró una barra de accesos rápidos con botones de acceso directo:
* `🚀 Entrega directa` (primer botón con estilo prioritario).
* `➕ Nuevo libro`.
* `📥 Importar CSV`.
* `🧾 Nuevo NIA`.

### 4.4 Navegación Global
En `app/views/layouts/base.php`, la comprobación de visibilidad de `/mostrador` se extendió para incluir al rol `ADMIN` en la barra superior y en el menú desplegable de usuario, manteniendo el acceso para `PERSONAL`.

---

## 5. Verificación Manual con Datos de Seed

Se realizó la verificación integral con datos iniciales del seed:

1. **Escenario:** Operador de mostrador `personal@bookswap.local` (ID 2) atiende a `usuario@bookswap.local` (ID 3, NIA `202400101`, saldo inicial de 5 tokens).
2. **Selección de Libro:** Ejemplar `#4` correspondiente a *«Cien años de soledad»* de Gabriel García Márquez (estado inicial: `disponible`).
3. **Ejecución de Entrega Directa con Tokens:**
   ```text
   Resultado entrega directa: {
     "ok": true,
     "transaccion_id": 24,
     "ejemplar_id": 4,
     "titulo": "Cien años de soledad",
     "usuario_id": 3,
     "usuario_nombre": "Usuaria Demo",
     "metodo_pago": "tokens",
     "tokens_cobrados": 1,
     "deposito_tx_id": null
   }
   ```
4. **Verificación de Efectos en BD:**
   - **Ejemplar #4:** Estado final actualizado a `'retirado'`.
   - **Saldo del Lector:** Decrementado exactamente en 1 token (5 → 4 tokens).
   - **Transacción registrada:**
     ```json
     {
       "id": 24,
       "tipo": "entrega_directa",
       "metodo_pago": "tokens",
       "tokens": -1,
       "estado": "entregada",
       "gestionada_por": 2
     }
     ```
   - **Notificación generada:** `Entrega directa registrada: Cien años de soledad. Método: tokens.`
   - **Auditoría registrada:** Acción `'entrega_directa'` con detalle JSON completo.
   - **Historial (`/mi-historial`):** El movimiento refleja correctamente el concepto `"Entrega directa en mostrador: «Cien años de soledad»"`, tipo de transacción `'entrega_directa'`, método de pago `'tokens'`, libro y autor resueltos.

---

## 6. Benchmark de Rendimiento Post-Implementación

Se ejecutó `tests/benchmark.php` contra la suite local con 10 repeticiones por endpoint:

```text
═══════════════════════════════════════════════════════════════════════════════════════════════════════
 BookSwap · Benchmark de Rendimiento y Consumo (Fase 12 / Fase 15)
 Base: http://127.0.0.1  ·  Peticiones por ruta: 10  ·  Fecha: 2026-09-25 07:42:51
═══════════════════════════════════════════════════════════════════════════════════════════════════════

+------------------------+-------------+--------+------------+------------+------------+------------+
| Ruta / Recurso         | Autenticado | HTTP   |    Mínimo |    Mediana |    Máximo |  Peso (KB) |
+------------------------+-------------+--------+------------+------------+------------+------------+
| /                      | (público)  | 200    |   28.68 ms |   35.59 ms |   81.82 ms |   19.19 KB |
| /catalogo              | (público)  | 200    |   36.27 ms |   47.59 ms |   93.83 ms |   39.48 KB |
| /catalogo?q=quijote    | (público)  | 200    |   34.02 ms |   64.49 ms |   94.45 ms |   13.09 KB |
| /libro/1               | (público)  | 200    |   39.62 ms |   74.79 ms |   99.48 ms |   18.66 KB |
| /login                 | (público)  | 200    |   41.43 ms |   62.58 ms |  122.90 ms |    9.46 KB |
| /visitanos             | (público)  | 200    |   31.71 ms |   88.30 ms |  106.27 ms |   14.94 KB |
| /dashboard             | usuario     | 200    |   54.12 ms |   92.62 ms |  110.57 ms |   12.56 KB |
| /mostrador             | personal    | 200    |   35.51 ms |   86.13 ms |  113.97 ms |  373.23 KB |
| /mi-historial          | usuario     | 200    |   30.22 ms |   89.46 ms |  109.73 ms |   34.58 KB |
| /admin                 | admin       | 200    |   35.26 ms |   57.24 ms |  101.61 ms |   23.91 KB |
+------------------------+-------------+--------+------------+------------+------------+------------+

Estado general: OK (todas las rutas responden 200)
Benchmark completado con éxito.
```

Las medianas de respuesta se mantienen estables por debajo de los 95 ms en todas las rutas autenticadas, y el peso de respuesta de `/mostrador` incluye el conjunto de datos necesario para el autocompletado en memoria local sin penalizar la experiencia.

---

## 7. Matriz de Pruebas T-DIR-01..07 y Estado de la Suite

Los 7 nuevos tests añadidos a `tests/run.php` verifican cada una de las condiciones del incremento:

| Identificador | Descripción del Test | Resultado |
|---|---|:---:|
| **T-DIR-01** | Entrega directa con tokens: copia `'retirado'`, transacción `'entrega_directa'` `'entregada'`, ledger de retiro, notificación y auditoría | **PASS** |
| **T-DIR-02** | Entrega directa con libro aportado: dos movimientos contables (depósito + retiro), copia entregada `'retirado'` y aportada `'disponible'` | **PASS** |
| **T-DIR-03** | Saldo insuficiente (tokens): denegado atómicamente sin cambios en BD | **PASS** |
| **T-DIR-04** | Copia reservada denegada; receptor `pendiente_nia` denegado con mensaje explicativo | **PASS** |
| **T-DIR-05** | Control de acceso: rol USUARIO en endpoint de entrega directa → `403`; PERSONAL y ADMIN → `200` | **PASS** |
| **T-DIR-06** | UI: `/mostrador` muestra Entrega Directa como primera opción; `/admin` incluye acción rápida; navbar ADMIN incluye Mostrador | **PASS** |
| **T-DIR-07** | Detección de reserva activa: advertencia y sugerencia de redirección al flujo de entrega de reserva | **PASS** |

### Resumen Global de Regresión

```text
════════ RESUMEN ════════
  PASS: 91   FAIL: 0   SKIP: 0

✔ SUITE VERDE
```

* **Tests previos v4.3:** 84 PASS
* **Tests nuevos Fase 15:** 7 PASS
* **Total ejecutado:** 91 PASS / 0 FAIL / 0 SKIP
* **Regresión funcional y de seguridad:** 0 incidencias. Todas las fases anteriores permanecen en verde.
