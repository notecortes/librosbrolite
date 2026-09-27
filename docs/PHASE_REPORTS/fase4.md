# Informe de Fase 4: Reservas

## 1. Estado y Resumen
- **Fase completada:** Fase 4 — Reservas de Libros y Gestión de Expiraciones.
- **Resultado de la suite de tests:** 41 PASS, 0 FAIL, 19 SKIP (`SUITE VERDE`).
- **Tests activados y superados:**
  - `T-RESV-01`: Reservar copia disponible → 'activa', limite = ahora + horas_reserva, código + QR.
  - `T-RESV-02`: max_reservas_activas alcanzado → 4ª denegada; ampliando el límite, permitida.
  - `T-RESV-03`: Reservar copia no disponible → denegado.
  - `T-RESV-04`: Expiración → 'expirada', copia 'disponible', notificación.
  - `T-RESV-05`: Cancelación por el usuario → copia 'disponible'.
  - `T-RGRC-02`: Regresión: ciclo de reserva y cancelación mantiene coherencia de ejemplar y transacción.

---

## 2. Componentes Implementados

### 2.1. Helpers
- [`app/helpers/reservas.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/reservas.php):
  - `reserva_crear(PDO $pdo, int $usuarioId, int $libroId, ?int $ejemplarId = null)`:
    - Validación estricta de que el usuario posea un número de socio activo vinculado antes de reservar.
    - Comprobación del límite configurable de reservas activas simultáneas (`max_reservas_activas`, por defecto 3).
    - Asignación atómica de ejemplar con bloqueo pesimista `FOR UPDATE`.
    - Generación de código de recogida único `RES-YYMMDD-XXXXXX`.
    - Cálculo de fecha límite según el parámetro dinámico `horas_reserva` (por defecto 72h).
    - Creación de transacción con estado `activa` y `tokens = 0` (el coste uniforme se liquida en el mostrador físico).
    - Actualización del estado del ejemplar a `reservado`.
    - Envío de notificación in-app y registro de auditoría (`reserva_creada`).
  - `reserva_cancelar(PDO $pdo, int $transaccionId, int $usuarioId, bool $esAdminOPersonal = false)`:
    - Verificación de propiedad o permisos de personal.
    - Cambio de estado de la transacción a `cancelada`.
    - Retorno inmediato del ejemplar a estado `disponible`.
    - Notificación in-app y auditoría (`reserva_cancelada`).
  - `reserva_listar_usuario(PDO $pdo, int $usuarioId)`:
    - Consulta detallada de reservas del usuario (tanto activas como historial de entregadas/expiradas/canceladas) con datos bibliográficos del libro.
  - `reserva_buscar_por_codigo(PDO $pdo, string $codigo)`:
    - Búsqueda para personal y mostrador por código alfanumérico.
  - `reserva_expirar_vencidas(PDO $pdo)`:
    - Detección y transición a `expirada` de reservas activas cuya `fecha_limite <= NOW()`.
    - Liberación atómica de los ejemplares afectados a estado `disponible`.
    - Generación de notificación informativa a los usuarios afectados y auditoría (`reserva_expirada`).

### 2.2. Script CLI y Pseudo-Cron
- [`bin/expirar.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/bin/expirar.php):
  - Ejecutable desde terminal o cron del sistema (`php bin/expirar.php`).
  - Muestra recuento y detalles de reservas expiradas.
- **Pseudo-cron web**:
  - Enrutador invoca `reserva_expirar_vencidas(db())` al acceder a `/mis-reservas` para garantizar que la expiración se ejecute incluso sin un cron configurado en el servidor web.

### 2.3. Vistas
- [`app/views/reservas/confirmar.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/reservas/confirmar.php):
  - Pantalla previa de confirmación con las condiciones del préstamo ciudadano: coste 0 al reservar, coste configurable al retirar en mostrador, y plazo de recogida en horas.
  - Botón de confirmación con protección CSRF.
- [`app/views/usuario/reservas.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/usuario/reservas.php):
  - Sección de reservas activas con tarjetas destacadas, badge de estado, cuenta atrás visual hasta la fecha límite, botón para cancelar y botón para mostrar código QR de recogida mediante modal (utilizando QRCode.js sin dependencias pesadas).
  - Tabla de historial con estado de reservas pasadas (`entregada`, `cancelada`, `expirada`).

### 2.4. Enrutamiento (`app/router.php`)
- `/reservar` (GET): Muestra la vista de confirmación del libro a reservar.
- `/reservar` (POST): Procesa la creación de la reserva y redirige a `/mis-reservas`.
- `/mis-reservas` (GET): Ejecuta pseudo-cron y renderiza la pantalla de reservas del usuario.
- `/mis-reservas/cancelar` (POST): Procesa la cancelación voluntaria de la reserva.

---

## 3. Verificación
- Ejecutado `./tests/run_all.sh` obteniendo:
  `PASS: 41 | FAIL: 0 | SKIP: 19`
- `SUITE VERDE` confirmada sin errores.
