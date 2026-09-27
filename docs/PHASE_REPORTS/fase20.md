# Reporte de Fase 20 — Eliminación Total del NIA

**Fecha:** 26 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.7)  
**Estado:** Completada (109 PASS, 0 FAIL, 0 SKIP)  
**Suite de Tests:** 100% Verde (`./tests/run_all.sh`)

---

## 1. Objetivos y Alcance de la Fase 20

La **Fase 20** ejecuta la simplificación estructural del acceso a BookSwap eliminando por completo el concepto de NIA (Número de Identificación de Alumno):

1. **Eliminación del modelo de datos y esquemas de BD (§1.1):**
   - `database/01_schema.sql`: Eliminada la tabla `nias` y sus restricciones de clave foránea.
   - `database/02_seed.sql`: Eliminado el bloque del pool de NIAs, el permiso `nias.gestionar` y su asignación al rol `PERSONAL`. Notificación del usuario 5 actualizada para reflejar cuenta Google-only directa sin paso intermedio.
   - `database/usuarios_persistentes.json`: Limpiados los atributos de NIA en la persistencia local de desarrollo.

2. **Refactorización de Autenticación y Rutas (§1.2):**
   - **Registro (`/registro`):** Formulario directo con nombre, email y contraseña. La cuenta se crea de forma inmediata en estado **activo** con rol `USUARIO`.
   - **Acceso con Google OAuth:** El usuario accede directamente tras autenticarse con su proveedor; desaparece el middleware `pendiente_nia` y la redirección forzosa a `/completar-nia`.
   - **Rutas eliminadas:** `/completar-nia`, `/activar`, `/admin/nias` (listado, alta, lote, liberación, exportación), `/admin/csv/nias`, `/admin/csv/plantilla-nias`.
   - **Importador CSV:** Se conserva intacto el importador de catálogo bibliográfico (`/admin/csv/catalogo`), eliminando la opción de importación de NIAs.

3. **Limpieza Integral de Vistas y UX (§1.2):**
   - **Sidebar de administración:** Grupo `PERSONAS` simplificado a "Usuarios · Roles y permisos".
   - **Panel de administración (`/admin`):** Eliminada la acción rápida "Nuevo NIA", la tarjeta "Pool de NIAs" y la alerta de "Usuarios pendientes de NIA".
   - **Mostrador y entregas:** Identificación y búsqueda de lectores exclusivamente mediante correo electrónico o ID. Todos los lectores activos son elegibles para entregas y reservas directas.
   - **Dashboard ciudadano (`/dashboard`):** Eliminada cualquier mención a NIA; si el usuario tiene saldo insuficiente y no tiene reservas, se presenta el bloque informativo *"Deposita tus primeros libros para ganar tokens"*.
   - **Ficha de libro y catálogo:** Formularios y modales de reserva presencial actualizados para admitir correo electrónico o ID del lector.

4. **Actualización Documental y Regla de 0 Ocurrencias:**
   - Limpieza completa en `README.md`, `docs/DEPLOY_FTP.md` y `docs/FLOW_MAP.md`.
   - Verificación de ausencia de la cadena "nia" en código fuente, esquemas y pruebas.

---

## 2. Modificaciones en Tests (DoD §1.3)

| Test ID | Estado | Detalle de la Modificación |
|---|---|---|
| `T-AUTH-01` | MODIFICADO | Verifica que el registro con email y contraseña genera directamente una cuenta activa sin NIA ni pasos intermedios. |
| `T-NIA-01..06` | BAJA | Eliminados al desaparecer la tabla y la lógica de validación de NIAs. |
| `T-CSV-04` | BAJA | Eliminado el test de importación CSV de NIAs. |
| `T-ADMIN-01` | MODIFICADO | Verifica la visualización de las secciones operativas de `/admin` sin la sección de NIAs. |
| `T-ADMIN-02` | MODIFICADO | Verifica el importador CSV de catálogo (alta, informe y auditoría) con ID de test intacto. |
| `T-ADMIN-05` | MODIFICADO | Eliminada la inserción de NIA en la preparación del lector demo para cancelación de reserva. |
| `T-DIR-01..07`| MODIFICADO | Eliminados los JOINs y campos de NIA; `T-DIR-04` valida el rechazo ante lectores inactivos. |
| `T-ROLE-05` | MODIFICADO | Prueba la auditoría de modificación de permisos con `csv.importar` en lugar de `nias.gestionar`. |
| `T-SCAN-05` | MODIFICADO | El escaneo universal de mostrador procesa emails de usuarios y devuelve `tipo => 'usuario'` con saldo. |
| `T-UX-02` | MODIFICADO | Verifica la no presencia de estados pendientes de NIA y valida el aviso de primeros depósitos en `/dashboard`. |
| `T-CTX-02` | MODIFICADO | Entrada de copias asociada al lector mediante su correo electrónico. |
| `T-RGRC-03` | MODIFICADO | Regresión continua que valida el alta activa de lectores con email y contraseña. |

---

## 3. Resultado de la Verificación

- **Suite de Pruebas:** 109 ejecutados, 109 PASS, 0 FAIL, 0 SKIP.
- **DoD Fase 20:** Cumplido al 100%. Cero fallos y código limpio de referencias a NIA.
