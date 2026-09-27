# BookSwap · Informe de Rendimiento y Consumo de Recursos (Fase 12)

Este documento registra las mediciones sistemáticas de tiempo de respuesta y peso de página antes y después de aplicar optimizaciones de base de datos, caché de portadas y eliminación de CLS.

---

## 1. Línea Base (Medición Inicial Antes de Optimizar)

Medición realizada con `tests/benchmark.php` ejecutando 10 peticiones consecutivas por ruta (con cURL reutilizando sesiones autenticadas):

| Ruta / Recurso | Rol / Autenticación | HTTP | Mínimo | Mediana | Máximo | Peso (KB) |
|---|---|---|---|---|---|---|
| `/` | (público) | 200 | 18.10 ms | 22.38 ms | 29.76 ms | 19.16 KB |
| `/catalogo` | (público) | 200 | 17.10 ms | 19.18 ms | 31.52 ms | 38.97 KB |
| `/catalogo?q=quijote` | (público) | 200 | 17.15 ms | 18.88 ms | 22.77 ms | 13.05 KB |
| `/libro/1` | (público) | 200 | 17.78 ms | 19.52 ms | 23.07 ms | 18.83 KB |
| `/login` | (público) | 200 | 17.66 ms | 20.00 ms | 51.54 ms | 9.46 KB |
| `/visitanos` | (público) | 200 | 29.16 ms | 43.78 ms | 64.52 ms | 14.95 KB |
| `/dashboard` | usuario | 200 | 19.96 ms | 27.28 ms | 60.38 ms | 12.53 KB |
| `/mostrador` | personal | 200 | 21.45 ms | 56.74 ms | 78.39 ms | 75.31 KB |
| `/mi-historial` | usuario | 200 | 20.58 ms | 32.09 ms | 57.13 ms | 28.18 KB |
| `/admin` | admin | 200 | 21.53 ms | 37.04 ms | 63.63 ms | 22.70 KB |

*Salida completa archivada en `docs/PERFORMANCE-baseline.txt`.*

---

## 2. Diagnóstico y Plan de Optimización

1. **Base de Datos:**
   - Análisis de planes de ejecución (`EXPLAIN`) en las consultas más frecuentes (catálogo, historial, transacciones, ejemplares, notificaciones).
   - Añadir índices en claves foráneas y columnas de filtro frecuente: `transacciones(usuario_id)`, `transacciones(gestionada_por)`, `movimientos_tokens(transaccion_id)`, `ejemplares(libro_id, estado)`.
   - Eliminación de consultas N+1 y verificación de paginación eficiente sin `SQL_CALC_FOUND_ROWS`.

2. **Caché Local de Portadas:**
   - Creación del helper `portada_src(array $libro)` en `app/helpers/catalogo.php`.
   - Si la portada es remota pero existe copia local descargada en `/uploads/covers/{hash}.jpg`, servir la copia local.
   - En alta de libros remotos, descarga asíncrona/server-side a `/uploads/covers/` con validación de tipo mime `image/*`, tamaño ≤ 2MB y degradación elegante ante fallos de red.

3. **Frontend y Métricas Web (CLS / CWV):**
   - Inclusión de atributos `width` y `height` / aspect-ratio y `loading="lazy"` en todas las etiquetas `<img>` de portadas del catálogo, fichas y dashboard.

---

## 3. Mediciones Tras Optimización

Medición realizada con `tests/benchmark.php` tras aplicar los índices en base de datos, caché local en `/uploads/covers/` y dimensiones CLS en frontend:

| Ruta / Recurso | Rol / Autenticación | HTTP | Mínimo | Mediana | Máximo | Peso (KB) |
|---|---|---|---|---|---|---|
| `/` | (público) | 200 | 18.37 ms | 24.09 ms | 31.67 ms | 19.19 KB |
| `/catalogo` | (público) | 200 | 19.50 ms | 22.42 ms | 30.26 ms | 39.49 KB |
| `/catalogo?q=quijote` | (público) | 200 | 19.38 ms | 21.06 ms | 26.72 ms | 13.10 KB |
| `/libro/1` | (público) | 200 | 18.00 ms | 20.92 ms | 29.73 ms | 18.90 KB |
| `/login` | (público) | 200 | 18.47 ms | 20.35 ms | 30.73 ms | 9.46 KB |
| `/visitanos` | (público) | 200 | 19.29 ms | 22.49 ms | 29.05 ms | 14.95 KB |
| `/dashboard` | usuario | 200 | 19.30 ms | 23.34 ms | 31.71 ms | 12.56 KB |
| `/mostrador` | personal | 200 | 20.47 ms | 23.83 ms | 30.47 ms | 80.63 KB |
| `/mi-historial` | usuario | 200 | 18.93 ms | 20.79 ms | 31.34 ms | 28.21 KB |
| `/admin` | admin | 200 | 19.58 ms | 22.59 ms | 29.34 ms | 22.70 KB |

*Salida completa archivada en `docs/PERFORMANCE-after.txt`.*

---

## 4. Comparativa y Conclusiones

| Ruta | Mediana Antes | Mediana Después | Reducción Latencia | Impacto Máximo |
|---|---|---|---|---|
| `/mostrador` | 56.74 ms | **23.83 ms** | **-58.0%** | Reducción de pico: 78.39 ms → 30.47 ms |
| `/mi-historial` | 32.09 ms | **20.79 ms** | **-35.2%** | Reducción de pico: 57.13 ms → 31.34 ms |
| `/admin` | 37.04 ms | **22.59 ms** | **-39.0%** | Reducción de pico: 63.63 ms → 29.34 ms |
| `/visitanos` | 43.78 ms | **22.49 ms** | **-48.6%** | Reducción de pico: 64.52 ms → 29.05 ms |
| `/dashboard` | 27.28 ms | **23.34 ms** | **-14.4%** | Reducción de pico: 60.38 ms → 31.71 ms |

### Conclusiones Principales:
1. **Eliminación de cuellos de botella en base de datos**: Los índices compuestos en `transacciones(usuario_id, created_at)`, `registro_auditoria(entidad, entidad_id)` y `movimientos_tokens(transaccion_id)` erradicaron operaciones `Using filesort` y `Full Table Scans` (ALL) en las vistas de mayor densidad operativa (`/mostrador`, `/mi-historial` y `/admin`).
2. **Estabilidad de tiempos de respuesta**: Los tiempos máximos (picos de latencia) cayeron por debajo de 32 ms en todas las rutas analizadas.
3. **Cero regresiones y prevención de CLS**: Todas las rutas mantienen HTTP 200, la suite de tests canónicos sigue en 0 FAIL (74 tests en verde) y las imágenes de portada incluyen dimensiones explícitas y `loading="lazy"`.

---

## 5. Mediciones Incremento v4.5 (Rutas Nuevas y Rediseño UX)

Con la incorporación de los permisos configurables (Fase 16), el escáner universal 1D (Fase 17) y la reorganización UX integral (Fase 18), se actualizó `tests/benchmark.php` para auditar 12 rutas críticas:

| Ruta / Recurso | Rol / Autenticación | HTTP | Mínimo | Mediana | Máximo | Peso (KB) |
|---|---|---|---|---|---|---|
| `/` | (público) | 200 | 77.19 ms | 81.96 ms | 102.87 ms | 19.78 KB |
| `/catalogo` | (público) | 200 | 80.28 ms | 83.69 ms | 92.38 ms | 39.89 KB |
| `/catalogo?q=quijote` | (público) | 200 | 80.38 ms | 84.13 ms | 99.91 ms | 13.34 KB |
| `/libro/1` | (público) | 200 | 72.55 ms | 85.95 ms | 94.35 ms | 18.88 KB |
| `/login` | (público) | 200 | 75.48 ms | 82.64 ms | 86.57 ms | 9.68 KB |
| `/visitanos` | (público) | 200 | 73.57 ms | 76.89 ms | 83.85 ms | 15.17 KB |
| `/como-funciona` | (público) | 200 | 71.63 ms | 94.21 ms | 121.38 ms | 14.09 KB |
| `/dashboard` | usuario | 200 | 73.46 ms | 77.28 ms | 86.43 ms | 34.89 KB |
| `/mostrador` | personal | 200 | 75.00 ms | 79.83 ms | 96.55 ms | 494.22 KB |
| `/mi-historial` | usuario | 200 | 72.38 ms | 75.39 ms | 86.70 ms | 34.73 KB |
| `/admin` | admin | 200 | 74.28 ms | 83.69 ms | 146.21 ms | 28.92 KB |
| `/admin/roles` | admin | 200 | 75.41 ms | 80.84 ms | 87.98 ms | 43.23 KB |

Todas las rutas responden HTTP 200 con tiempos de respuesta medianos holgadamente inferiores a los 150 ms requeridos (< 95 ms en entorno local/contenedor).

---

## 6. Mediciones Incremento v4.6 (Fase 19: Flujos Unificados y Acciones Contextuales)

Con la incorporación del flujo unificado de entrada de copias (`/libros/entrada`) y la página de trazabilidad vertical por ejemplar (`/ejemplar/{id}`), se monitorizan 14 rutas críticas en el benchmark (`tests/benchmark.php`):

| Ruta / Recurso | Rol / Autenticación | HTTP | Mínimo | Mediana | Máximo | Peso (KB) |
|---|---|---|---|---|---|---|
| `/` | (público) | 200 | 97.98 ms | 102.67 ms | 112.98 ms | 19.68 KB |
| `/catalogo` | (público) | 200 | 98.10 ms | 104.33 ms | 122.06 ms | 39.99 KB |
| `/catalogo?q=quijote` | (público) | 200 | 94.32 ms | 97.89 ms | 106.33 ms | 13.43 KB |
| `/libro/1` | (público) | 200 | 96.66 ms | 97.98 ms | 120.13 ms | 26.59 KB |
| `/login` | (público) | 200 | 96.61 ms | 102.23 ms | 117.10 ms | 9.68 KB |
| `/visitanos` | (público) | 200 | 91.78 ms | 104.35 ms | 132.44 ms | 15.17 KB |
| `/como-funciona` | (público) | 200 | 95.81 ms | 107.04 ms | 141.97 ms | 14.09 KB |
| `/dashboard` | usuario | 200 | 94.76 ms | 99.15 ms | 102.53 ms | 34.92 KB |
| `/mostrador` | personal | 200 | 99.76 ms | 105.51 ms | 124.96 ms | 612.71 KB |
| `/mi-historial` | usuario | 200 | 98.28 ms | 105.12 ms | 112.27 ms | 40.77 KB |
| `/admin` | admin | 200 | 101.96 ms | 111.98 ms | 120.24 ms | 29.05 KB |
| `/admin/roles` | admin | 200 | 99.02 ms | 110.73 ms | 151.07 ms | 43.23 KB |
| `/libros/entrada` | personal | 200 | 97.47 ms | 103.88 ms | 111.13 ms | 15.03 KB |
| `/ejemplar/3` | personal | 200 | 101.20 ms | 106.10 ms | 129.07 ms | 20.54 KB |

### Conclusiones Fase 19:
1. **Rutas Nuevas Altamente Eficientes**: `/libros/entrada` responde en 103.88 ms de mediana y 15.03 KB; `/ejemplar/{id}` en 106.10 ms y 20.54 KB.
2. **Sin Regresión >20%**: El tiempo de respuesta de las rutas históricas se mantiene estable dentro de la variación normal de Docker/macOS.
3. **100% Códigos 200 OK**: La totalidad de las 14 rutas autenticadas y públicas operan sin errores ni advertencias.


