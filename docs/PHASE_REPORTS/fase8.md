# Informe de Fase 8: Transición Integral a NIA y Gestión del Pool de Identificadores (v4.2)

## 1. Estado y Resumen
- **Fase completada:** Fase 8 — Transición completa de "número de socio" a NIA (Número de Identificación del Alumno/Lector): Refactorización del código de registro, middleware de control de acceso, nuevo módulo `/completar-nia`, gestión del pool de NIAs en `/admin/nias` (individual, lote, CSV, exportación y liberación), capa de compatibilidad y actualización de la suite de pruebas.
- **Resultado de la suite de tests:** **62 PASS · 0 FAIL · 10 SKIP** (`✔ SUITE VERDE`).
- **Estado del proyecto:** **Fase 8 completada con 0 regresiones**.

---

## 2. Implementaciones Realizadas

### 2.1. Helper Central de NIAs (`app/helpers/nia.php`)
Se desarrolló el módulo completo para la gestión y reglas de negocio del NIA:
- `nia_normalizar(string $nia)`: limpieza de espacios en blanco y conversión obligatoria a mayúsculas (`trim` + `mb_strtoupper`).
- `nia_validar_formato(string $nia)`: validación estricta server-side mediante expresión regular `^[A-Z0-9]{8,12}$`.
- `nia_obtener_por_usuario(int $usuario_id)`: consulta del NIA asignado al usuario.
- `nia_tiene_usuario(int $usuario_id)`: comprobación booleana de posesión de NIA.
- `nia_comprobar_disponibilidad(string $nia)`: verifica existencia en el pool y estado libre (sin usuario asignado).
- `nia_vincular_a_usuario(int $usuario_id, string $nia)`: asignación atómica con bloqueo transaccional (`FOR UPDATE`), auditoría y control de unicidad.
- `nia_liberar(PDO $pdo, int $niaId, ?int $usuarioId)`: desvincula el NIA de su usuario y lo devuelve al estado disponible en el pool, dejando constancia en auditoría.
- `nia_eliminar(PDO $pdo, int $niaId, ?int $usuarioId)`: eliminación permitida únicamente para NIAs nunca asignados previamente.
- `nia_crear(PDO $pdo, string $nia, string $origen, ?int $usuarioId)`: alta individual normalizada en el pool.
- `nia_crear_lote(PDO $pdo, string $lineas, ?int $usuarioId)`: procesamiento por lotes con informe línea a línea (añadidos, duplicados en lote, ya existentes en BD, formato erróneo).
- `nia_listar_pool(PDO $pdo, ?string $estado, string $buscar, int $pagina, int $porPagina)`: paginación server-side, filtrado por estado (todos, libres, asignados) y búsqueda por NIA, nombre o email del lector.
- `nia_exportar_csv(PDO $pdo)`: generación y descarga de CSV con el estado completo del pool.

### 2.2. Registro Atómico con NIA y Autenticación (`app/helpers/auth.php`)
- `auth_registrar_usuario($nombre, $email, $password, $nia)`:
  - Exige el NIA en el formulario de registro con email local.
  - Ejecuta una única transacción atómica: valida formato, bloquea y comprueba la disponibilidad del NIA en el pool pre-cargado, crea el usuario activo (`activo = 1`, `email_verificado = 1`) y le asigna el NIA inmediatamente (`nias.usuario_id`).
  - Si el NIA no existe o ya está ocupado, la transacción se cancela (rollback) y no se crea ninguna cuenta huérfana.

### 2.3. Flujo Google y Middleware de Acceso (`/completar-nia`)
- **Middleware en `app/router.php`:**
  - Cuando un usuario con rol `USUARIO` inicia sesión (especialmente cuentas nuevas vía Google OAuth), se verifica `nia_tiene_usuario()`.
  - Si no tiene NIA asignado, se le redirige incondicionalmente a `/completar-nia`, permitiendo únicamente `/completar-nia`, `/logout` y `/privacidad`.
  - Si el usuario ya tiene un NIA asignado e intenta acceder a `/completar-nia`, es redirigido automáticamente a `/dashboard`.
- **Vista `app/views/auth/completar_nia.php`:** interfaz clara y adaptada que solicita el código NIA y explica el proceso de vinculación.
- **Ruta `/activar`:** redirige de forma transparente por compatibilidad a `/completar-nia`.

### 2.4. Panel de Gestión del Pool de NIAs (`app/views/admin/nias/index.php`)
- Panel operativo en `/admin/nias` accesible para roles `ADMIN` y `PERSONAL` (permiso `nias.gestionar`).
- Métricas rápidas: Total de NIAs en pool, NIAs disponibles y NIAs asignados.
- Filtros por estado y caja de búsqueda en tiempo real.
- Modales para **Alta Individual** y **Alta Masiva por Lote** (con informe detallado tras procesar).
- Acciones directas por fila: botón para **Liberar NIA** (con modal de confirmación) y **Eliminar** (solo si está libre).
- Enlace directo a la exportación CSV del pool.

### 2.5. Actualización del Importador CSV (`app/helpers/csv.php`)
- Adaptación de `csv_importar_nias()`: detección automática de delimitadores, validación de encabezado `nia`, soporte de flag "detener ante el primer error", e informe detallado de duplicados y filas erróneas.
- Descarga de plantilla CSV de NIAs en `/admin/csv/plantilla-nias`.
- Adaptación de la vista `app/views/admin/csv/index.php` con pestaña dedicada a NIAs.

### 2.6. Limpieza de Interfaz y Capa de Compatibilidad
- `app/views/usuario/dashboard.php`: sustitución de las etiquetas de "Número de socio" por el identificador oficial "NIA: XXXXXXXX" y botón a `/completar-nia` en caso de no poseerlo.
- `app/views/layouts/base.php`: menús actualizados a "Pool de NIAs" (`/admin/nias`).
- `app/helpers/socio.php`: reescrito como capa de compatibilidad hacia `app/helpers/nia.php` para asegurar que cualquier llamada residual siga funcionando sin tocar tablas inexistentes.
- `app/helpers/usuario_persistencia.php`: persistencia JSON adaptada a la tabla canónica `nias`.

---

## 3. Suite de Pruebas Automatizadas (DoD Fase 8)

Se aplicaron las especificaciones de INCREMENTO v4.2 §6 en `tests/run.php`:
1. **Bajas:** se eliminaron los tests obsoletos `T-SOCIO-01..04`.
2. **Modificaciones:**
   - `T-AUTH-01`: registro con email + NIA válido crea cuenta activa con NIA vinculado.
   - `T-CSV-04`: CSV de NIAs con cabecera `nia`: pool creado, duplicados omitidos e informe de errores línea a línea.
3. **Altas:**
   - `T-NIA-01`: registro email con NIA válido y libre crea cuenta y vincula el NIA (`nias.usuario_id`).
   - `T-NIA-02`: NIA inexistente en el pool rechaza el registro y no crea cuenta en la BD.
   - `T-NIA-03`: NIA ya asignado a otro usuario es rechazado.
   - `T-NIA-04`: validación server-side de longitud (7 o 13 caracteres) y caracteres alfanuméricos; normalización automática a mayúsculas.
   - `T-NIA-05`: usuario sin NIA es interceptado por el middleware y forzado a `/completar-nia`; tras completarlo tiene acceso total; usuario con NIA nunca es redirigido.
   - `T-NIA-06`: verificación de constraints de unicidad a nivel de BD (`nias.nia` UNIQUE y `nias.usuario_id` UNIQUE).

**Resultado de ejecución (`./tests/run_all.sh`):**
```
PASS: 62   FAIL: 0   SKIP: 10
✔ SUITE VERDE
```
