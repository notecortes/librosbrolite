# Informe de Fase 3: CSV y Pool de Socios

## 1. Estado y Resumen
- **Fase completada:** Fase 3 — CSV y Pool de Socios.
- **Resultado de la suite de tests:** 36 PASS, 0 FAIL, 24 SKIP (`SUITE VERDE`).
- **Tests activados y superados:**
  - `T-CSV-01`: CSV válido de catálogo → N libros creados + informe correcto.
  - `T-CSV-02`: Filas erróneas → buenas importadas, informe con nº de línea y motivo.
  - `T-CSV-03`: Duplicados (ISBN o titulo+autor) → omitidos y listados.
  - `T-CSV-04`: CSV de números de socio → pool creado, duplicados omitidos.
  - `T-RGRC-01`: Regresión contable: `saldo_resultante` del último movimiento = `SUM(cantidad)` en `movimientos_tokens` por usuario.

---

## 2. Componentes Implementados

### 2.1. Helpers
- [`app/helpers/csv.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/csv.php):
  - `csv_detectar_delimitador(string $primeraLinea)`: Detección automática inteligente entre comas (`,`) y puntos y comas (`;`).
  - `csv_limpiar_bom(string $texto)`: Limpieza de marcas de orden de bytes UTF-8 (`\xEF\xBB\xBF`).
  - `csv_importar_catalogo(PDO $pdo, string $contenidoCsv, bool $detenerAnteError, ?int $usuarioId)`:
    - Validación de cabecera obligatoria (`titulo`, `autor`).
    - Detección de duplicados por ISBN-13 y por combinación insensible a mayúsculas de `titulo + autor`.
    - Validación de campos (año, etc.) y recolección detallada de filas con número de línea humana (`L-X`) y motivo.
    - Soporte de transacción y rollback en modo "todo-o-nada" si `$detenerAnteError = true`.
    - Registro de auditoría `importacion_csv_catalogo` con detalle de filas leídas, importadas, duplicadas y erróneas.
  - `csv_importar_socios(PDO $pdo, string $contenidoCsv, bool $detenerAnteError, ?int $usuarioId)`:
    - Validación de columna `numero`.
    - Verificación de duplicados contra `numeros_socio`.
    - Inserción con `usuario_id = NULL` (libres en el pool).
    - Auditoría `importacion_csv_socios`.
  - `csv_generar_plantilla_catalogo()` y `csv_generar_plantilla_socios()`: Plantillas estándar descargables.
- [`app/helpers/socio.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/helpers/socio.php):
  - `socio_listar_pool(PDO $pdo, ?string $estado, int $limite, int $offset)`: Listado paginado con filtro de estado (todos, libres, asignados).
  - `socio_crear_numero(PDO $pdo, string $numero, ?int $usuarioId)`: Creación manual individual de código en el pool con auditoría.

### 2.2. Vistas de Administración
- [`app/views/admin/csv/index.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/csv/index.php):
  - Pestañas intuitivas para importación de Catálogo y Números de Socio.
  - Selector de archivo `.csv` y área de texto directa para pegar contenido.
  - Casilla de verificación para parada ante el primer error (todo-o-nada).
  - Enlaces de descarga directa de plantillas CSV.
  - Informe post-importación con métricas destacadas, tablas de duplicados con número de línea (`L-X`), filas erróneas con causa específica y registros creados exitosamente.
- [`app/views/admin/socios/index.php`](file:///media/paspas/RocknRoll/NOBORRAR/ONEDRIVE/CLASES/000.ClasesActuales/00.PROYECTOS/2026/librosbrolite/app/views/admin/socios/index.php):
  - Panel de control del pool con métricas de totales, libres y vinculados.
  - Filtrado por estado con chips visuales.
  - Modal para alta manual directa de números de socio individuales.

### 2.3. Enrutamiento (`app/router.php`)
- `/admin/csv` (GET): Muestra el formulario e informe guardado en sesión tras redirección POST-Redirect-GET.
- `/admin/csv/catalogo` (POST): Procesa la importación de catálogo con CSRF y auditoría.
- `/admin/csv/socios` (POST): Procesa la importación al pool de socios con CSRF y auditoría.
- `/admin/csv/plantilla-catalogo` (GET): Descarga fichero `plantilla_catalogo.csv`.
- `/admin/csv/plantilla-socios` (GET): Descarga fichero `plantilla_socios.csv`.
- `/admin/socios` (GET): Vista y gestión del pool de socios.
- `/admin/socios/nuevo` (POST): Creación manual de un número de socio.

---

## 3. Verificación
- Ejecución limpia de `./tests/run_all.sh`:
  - 36 pruebas ejecutadas y superadas.
  - Cero fallos (`FAIL: 0`).
  - Cumplimiento de la regla de regresión contable `T-RGRC-01` sobre el ledger inmutable.
