# Informe de Fase 12: Rendimiento y Consumo de Recursos (v4.3)

## 1. Estado y Resumen
- **Fase completada:** Fase 12 — Rendimiento y Consumo de Recursos (Línea Base, Índices de Base de Datos, Caché Local de Portadas, Optimización Frontend CLS).
- **Resultado de la suite de tests:** **74 PASS · 0 FAIL · 0 SKIP** (`✔ SUITE VERDE`).
- **Estado de regresión:** 0 regresiones. Se incorporaron y validaron con éxito los tests `T-PERF-01` y `T-PERF-02`.
- **Archivos de medición:**
  - `tests/benchmark.php`: Runner de benchmark en PHP puro sobre 10 rutas clave bajo sesiones concurrentes/autenticadas.
  - `docs/PERFORMANCE-baseline.txt`: Medición inicial antes de optimizar.
  - `docs/PERFORMANCE-after.txt`: Medición final tras aplicar optimizaciones.
  - `docs/PERFORMANCE.md`: Análisis comparativo y conclusiones de rendimiento.

---

## 2. Acciones Realizadas en Fase 12

### 2.1 Línea Base Obligatoria (§1.1)
- Implementación de `tests/benchmark.php` midiendo 10 peticiones cURL por ruta con cálculo de mínimo, mediana, máximo y tamaño en KB.
- Ejecución limpia y captura en `docs/PERFORMANCE-baseline.txt`.
- Resumen inicial de métricas en `docs/PERFORMANCE.md`.
- Test `T-PERF-01` incorporado a `tests/run.php` validando la existencia de `tests/benchmark.php`, que todas las rutas responden HTTP 200 y que la tabla de métricas se imprime correctamente.

### 2.2 Optimización de Base de Datos (§1.2)
- Análisis de planes de ejecución (`EXPLAIN`) sobre las consultas más frecuentes:
  1. Catálogo con filtros y búsqueda: `SELECT l.*, COUNT(...) FROM libros l LEFT JOIN ejemplares e ... WHERE ... GROUP BY l.id`.
  2. Búsqueda por género: `SELECT * FROM libros WHERE genero = ?`.
  3. Historial con JOINs: `SELECT m.*, t.*, e.*, l.* FROM movimientos_tokens m LEFT JOIN transacciones t ... WHERE m.usuario_id = ? ORDER BY m.fecha DESC`.
  4. Transacciones por usuario: `SELECT * FROM transacciones WHERE usuario_id = ? ORDER BY created_at DESC`.
  5. Transacciones por gestor: `SELECT * FROM transacciones WHERE gestionada_por = ?`.
  6. Ledger vinculado a transacción: `SELECT * FROM movimientos_tokens WHERE transaccion_id = ?`.
  7. Auditoría filtrada por entidad: `SELECT * FROM registro_auditoria WHERE entidad = ? AND entidad_id = ?`.
  8. Auditoría filtrada por usuario: `SELECT * FROM registro_auditoria WHERE usuario_id = ? ORDER BY fecha DESC`.
- Se añadieron los índices autorizados en `database/01_schema.sql`:
  - `libros`: `INDEX idx_libros_genero (genero)` (elimina Full Table Scan en filtrado por género).
  - `transacciones`: `INDEX idx_trans_usuario_created (usuario_id, created_at)` (elimina `Using filesort` en historial de reservas) y `INDEX idx_trans_gestionada (gestionada_por)`.
  - `movimientos_tokens`: `INDEX idx_mov_transaccion (transaccion_id)` (búsqueda instantánea de movimientos asociados a entregas/depósitos).
  - `registro_auditoria`: `INDEX idx_aud_entidad (entidad, entidad_id)` (búsqueda directa con `type: ref` en lugar de escaneo completo de 80+ filas) y `INDEX idx_aud_usuario_fecha (usuario_id, fecha)` (elimina `Using filesort`).
- Se verificó que todas las listas paginadas utilicen consultas `COUNT(*)` dedicadas con `LIMIT` y `OFFSET` estándar, sin emplear la cláusula obsoleta `SQL_CALC_FOUND_ROWS`.

### 2.3 Portadas: Caché Local y Descarga Server-Side (§1.3)
- Helper `portada_src(array $libro)` en `app/helpers/catalogo.php`:
  - Si `portada_url` es remota y existe copia local en `/uploads/covers/{isbn o hash}.jpg`, sirve la ruta local `/uploads/covers/...`.
  - Si no existe copia local, sirve la remota de forma transparente.
  - Integrado de forma retrocompatible con `catalogo_resolver_url_portada(...)`.
- Descarga server-side desatendida mediante `portada_descargar_cache(string $url, ?string $isbn = null)`:
  - cURL con timeout estricto de 5s y validación de cabeceras HTTP.
  - Validación de `Content-Type: image/*` y límite máximo de tamaño de 2MB.
  - Almacenamiento seguro en `/uploads/covers/{isbn o hash}.jpg` sin re-descargas redundantes.
  - Integración en altas por ISBN (`/api/isbn`), altas manuales (`catalogo_guardar_libro`) e importación CSV (`csv_importar_catalogo`).
- Test `T-PERF-02` añadido a `tests/run.php`: valida que el alta por ISBN con red descarga la portada en `/uploads/covers/`, almacena la ruta local en la base de datos y el archivo físico existe en el servidor.

### 2.4 Frontend y Prevención de CLS (§1.4)
- Se añadieron atributos explícitos `width`, `height` y `loading="lazy"` en todas las etiquetas `<img>` de portadas editoriales y avatares:
  - `app/views/home.php`: `width="240" height="340"` con `loading="lazy"`.
  - `app/views/catalogo/index.php`: `width="300" height="225"` con `loading="lazy"`.
  - `app/views/catalogo/ficha.php`: `width="280" height="373"` con `loading="lazy"`.
  - `app/views/admin/libros/editar.php`: `width="220" height="293"` con `loading="lazy"`.
  - `app/views/admin/libros/nuevo.php`: `width="200" height="267"` con `loading="lazy"`.
  - `app/views/admin/mostrador.php`: `width="80" height="120"` con `loading="lazy"`.
  - `app/views/reservas/confirmar.php`: `width="70" height="105"` con `loading="lazy"`.
  - `app/views/usuario/reservas.php`: `width="100" height="150"` con `loading="lazy"`.
  - `app/views/layouts/base.php`: avatar con `width="32" height="32" loading="lazy"`.

---

## 3. Resultados del Benchmark (Antes vs Después)

| Ruta | Mediana Antes | Mediana Después | Reducción Latencia | Pico Antes | Pico Después |
|---|---|---|---|---|---|
| `/mostrador` | 56.74 ms | **23.83 ms** | **-58.0%** | 78.39 ms | **30.47 ms** |
| `/mi-historial` | 32.09 ms | **20.79 ms** | **-35.2%** | 57.13 ms | **31.34 ms** |
| `/admin` | 37.04 ms | **22.59 ms** | **-39.0%** | 63.63 ms | **29.34 ms** |
| `/visitanos` | 43.78 ms | **22.49 ms** | **-48.6%** | 64.52 ms | **29.05 ms** |
| `/dashboard` | 27.28 ms | **23.34 ms** | **-14.4%** | 60.38 ms | **31.71 ms** |

Todas las rutas del sistema operan ahora en una mediana de entre **20 y 24 ms**, con una reducción drástica de la desviación estándar y picos de latencia.

---

## 4. Conclusión y Paso a Fase 13
La Fase 12 se cierra con **74 PASS / 0 FAIL / 0 SKIP**. El sistema cuenta con mediciones empíricas comparadas, índices optimizados y caché local de portadas. Se procede de inmediato a la Fase 13 (Auditoría Integral de Seguridad OWASP y Hardening).
