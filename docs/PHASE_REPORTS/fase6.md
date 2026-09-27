# Informe de Fase 6: Configuración, Métricas, Backups y Visítanos

## 1. Estado y Resumen
- **Fase completada:** Fase 6 — Gestión de Configuración Dinámica, Métricas con Gráficos, Copias de Seguridad y Restauración SQL en PHP Puro, y Página Pública de Información del Centro ("Visítanos").
- **Resultado de la suite de tests:** 60 PASS, 0 FAIL, 0 SKIP (`SUITE VERDE`).
- **Tests activados y superados:**
  - `T-SMOKE-08`: `/visitanos` responde HTTP 200 y muestra la información del centro físico.
  - `T-VISIT-01`: La página pública `/visitanos` renderiza los valores exactos de `centro_direccion` y `centro_telefono`.
  - `T-VISIT-02`: Renderizado de `<iframe>` de mapa interactivo y enlace "Cómo llegar" con parámetros `google.com/maps/dir/?api=1&destination=`.
  - `T-AUDIT-02`: Actualización de configuración auditada en `registro_auditoria` con `valor_anterior` y `valor_nuevo`.
  - `T-BAK-01`: Creación de backup en PHP puro generando volcado SQL completo (`CREATE TABLE` e `INSERT INTO`) y registro en la tabla `backups`.
  - `T-BAK-02`: Restauración atómica de backup que restablece tablas y datos sin pérdida de integridad.
  - `T-BAK-03`: Restricción estricta de seguridad: acceso a `/admin/backups` restringido únicamente al rol `ADMIN` (403 para `USUARIO` y `PERSONAL`).
  - `T-MET-01`: Endpoint y helper de métricas estructurado con `copias_por_estado`, `tokens_en_circulacion` y `totales`.
  - `T-RGRC-01` y `T-RGRC-02`: Regresiones contables y de ciclo de reserva y cancelación verificadas con éxito tras la restauración.

---

## 2. Componentes Implementados

### 2.1. Helpers
- [`app/helpers/configuracion.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/configuracion.php):
  - `config_actualizar_clave(PDO $pdo, string $clave, ?string $valorNuevo, ?int $usuarioId)`:
    - Actualización en la tabla `configuracion` con soporte `ON DUPLICATE KEY UPDATE`.
    - Detección de cambios reales frente al valor anterior.
    - Registro inmutable en `registro_auditoria` bajo la acción `config_modificada` con `valor_anterior` y `valor_nuevo`.
  - `config_actualizar_multiples(PDO $pdo, array $valores, ?int $usuarioId)`:
    - Actualización masiva transaccional para formularios de administración.
- [`app/helpers/backup.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/backup.php):
  - `backup_crear(PDO $pdo, string $tipo, ?int $usuarioId)`:
    - Generación nativa en PHP sin dependencia de binarios externos (`mysqldump`).
    - Volcado ordenado de tablas (`SHOW FULL TABLES`), esquemas (`SHOW CREATE TABLE`) y datos en bloques seguros de 50 filas (`INSERT INTO`).
    - Almacenamiento en directorio `/backups/` protegido por `.htaccess`.
    - Registro en tabla `backups` y auditoría del sistema con acción `backup_creado`.
  - `backup_restaurar(PDO $pdo, string $contenidoOArchivo, ?int $usuarioId)`:
    - Parser SQL seguro para ejecución de bloques de sentencias DDL y DML desactivando temporalmente foreign keys.
    - Registro en `registro_auditoria` con acción `backup_restaurado`.
  - `backup_listar(PDO $pdo)`:
    - Listado paginado de backups con tamaños legibles, fechas y usuario generador.
  - `backup_aplicar_retencion(PDO $pdo, int $retencion)`:
    - Purga de copias antiguas en disco y en base de datos según el parámetro `retencion_backups`.
- [`app/helpers/metricas.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/metricas.php):
  - `metricas_obtener_datos(PDO $pdo)`:
    - Distribución de ejemplares por estado (`disponible`, `reservado`, `retirado`, `baja`).
    - Cálculo de tokens en circulación según el saldo positivo real de los socios en el ledger.
    - Histórico mensual de depósitos y entregas efectivas.
    - Totales agregados de libros, ejemplares, socios activos y reservas en curso.
- [`bin/backup.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/bin/backup.php):
  - Script CLI ejecutable para cron (`frecuencia_backup_dias` y `retencion_backups`).

### 2.2. Vistas Implementadas
- [`app/views/paginas/visitanos.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/paginas/visitanos.php):
  - Página pública con chip de estado en tiempo real (`Abierto ahora / Cerrado` vía `abierto_ahora()`).
  - Dirección, teléfono de llamada directa, email de contacto e indicaciones de transporte público.
  - Tabla de horario semanal formateada.
  - Embed interactivo (`<iframe>`) compatible con Google Maps u OpenStreetMap y botón directo "Cómo llegar".
- [`app/views/admin/configuracion.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/configuracion.php):
  - Panel administrativo por pestañas temáticas: Reglas Económicas, Punto Físico y Horarios, Integración Google OAuth y Mantenimiento/Backups.
  - Validación de campos y retroalimentación de cambios auditados.
- [`app/views/admin/backups.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/backups.php):
  - Gestor de copias de seguridad con botón de generación manual inmediata.
  - Tabla de copias disponibles con opciones de descarga segura, restauración con confirmación y eliminación.
- [`app/views/admin/metricas.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/metricas.php):
  - Cuadro de mandos analítico con tarjetas KPI y gráficos interactivos con Chart.js (donut de estados y barras de flujo mensual de libros).

### 2.3. Rutas y Controladores en `app/router.php`
- `/visitanos` (GET): Acceso público.
- `/admin/configuracion` (GET / POST): Gestión de parámetros (solo `ADMIN`).
- `/admin/backups` (GET): Listado de backups (solo `ADMIN`).
- `/admin/backups/generar` (POST): Creación de copia (solo `ADMIN`).
- `/admin/backups/descargar` (GET): Descarga protegida con stream seguro (solo `ADMIN`).
- `/admin/backups/restaurar` (POST): Restauración desde subida de archivo (solo `ADMIN`).
- `/admin/backups/restaurar-id` (POST): Restauración desde histórico local (solo `ADMIN`).
- `/admin/metricas` (GET): Visualización de métricas (solo `ADMIN`).
- `/api/admin/metricas` (GET): API JSON para gráficos dinámicos (solo `ADMIN`).

---

## 3. Próximos Pasos (Fase 7)
- **Fase 7:** Cierre, QA final, auditoría de tema/accesibilidad, guía de despliegue FTP sin SSH (`docs/DEPLOY_FTP.md`) y actualización del `README.md`.
