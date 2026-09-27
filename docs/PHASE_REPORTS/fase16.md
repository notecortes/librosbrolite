# Reporte de Fase 16 — Permisos del Personal Configurables

**Fecha:** 25 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.5)  
**Estado:** Completada (0 FAIL, 0 SKIP, 96 PASS)

---

## 1. Objetivos y Alcance de la Fase 16

La **Fase 16** implementa la gestión granular y configurable de los permisos del personal (`PERSONAL`) mediante una matriz interactiva accesible para administradores en `/admin/roles`, reemplazando todas las comprobaciones hardcodeadas de rol por chequeos basados en permisos (`puede()`), protegiendo los permisos base inmutables y registrando cada cambio en la auditoría del sistema.

Principales objetivos cumplidos:
1. **Diccionario y Matriz de Permisos (§1.1):** Registro canónico de los 16 permisos agrupados por ámbito (BASE, CATÁLOGO, MOSTRADOR, PERSONAS, SISTEMA), incluyendo el nuevo permiso `roles.gestionar`.
2. **Helper de Permisos `puede()` y `exigir_permiso()` (§1.2):**
   - Comprobaciones seguras mediante sesión con caché invalidable globalmente (`permisos_version`).
   - El rol `ADMIN` dispone de todos los permisos.
   - El rol `USUARIO` dispone exclusivamente de los permisos base (`usuario.base`, `catalogo.ver`, `reserva.crear`).
   - El rol `PERSONAL` consulta y evalúa los permisos activos asignados en `rol_permiso`.
3. **Inmutabilidad de Permisos Base:** Los permisos base (`usuario.base`, `catalogo.ver`, `reserva.crear`) nunca pueden ser desactivados ni por formulario ni por peticiones HTTP forzadas.
4. **Sustitución Sistemática de Chequeos:** Sustitución de `exigir_rol` por `exigir_permiso` en todos los controladores y rutas administrativas y de mostrador.
5. **Interfaz de Administración `/admin/roles` (§1.3):**
   - Vista en matriz visual tipo tabla con estados de `ADMIN` (fijo/todo marcado), `PERSONAL` (checkboxes editables) y `USUARIO` (fijo/solo base).
   - Banner de advertencia no bloqueante si el personal queda sin operativa completa en mostrador (`mostrador.acceder` y al menos `entrega.confirmar` o `deposito.registrar`).
   - Botón y modal de confirmación «Restaurar por defecto» que restablece la matriz exacta del seed v4.2.
6. **Auditoría Exhaustiva:** Cada cambio en la matriz genera un evento `rol_permiso.actualizar` en `registro_auditoria` detallando el permiso modificado, estado anterior y nuevo.
7. **Suite de Pruebas `T-ROLE-01..05` (§1.4):** Cobertura al 100% de los requisitos con 96 pruebas superadas sin fallos ni skips.

---

## 2. Modificaciones en Base de Datos y Seed

1. **Permiso añadido:**
   - Se registró en `database/02_seed.sql` el permiso 16: `roles.gestionar` ('Configurar permisos del personal').
   - Se vinculó al rol 1 (`ADMIN`) en `rol_permiso`.
2. **Sincronización:** Se ejecutó la inserción equivalente en la base de datos MySQL activa en el contenedor Docker.

---

## 3. Implementación del Asistente `app/helpers/permisos.php`

Se creó el asistente central con las siguientes funciones:
- `permisos_dict()`: Diccionario de 16 permisos categorizados con títulos y descripciones.
- `permisos_base()`: Array inmutable `['usuario.base', 'catalogo.ver', 'reserva.crear']`.
- `permisos_seed_personal()`: Lista canónica del rol personal en v4.2.
- `permisos_version_actual()` y `permisos_marcar_regeneracion()`: Mecanismo de versionado para forzar la recarga automática de sesión en todas las cuentas activas sin requerir logout.
- `puede(string $codigo): bool`: Comprobación reactiva y cached en sesión del usuario autenticado.
- `exigir_permiso(string|array $permisos): void`: Aborta con HTTP 403 y pantalla descriptiva si el usuario carece de los permisos requeridos.
- `permisos_actualizar_personal(PDO $pdo, array $nuevosCodigos, int $operadorId)`: Actualización transaccional atómica, preserva permisos base, audita cada diferencia y regenera la versión de caché.
- `permisos_restaurar_defaults(PDO $pdo, int $operadorId)`: Restaura la asignación original del seed.

---

## 4. Adaptación de Rutas y Vistas

1. **Router (`app/router.php`):**
   - Sustitución de `exigir_rol` por `exigir_permiso` en rutas de catálogo (`catalogo.editar`), CSV (`csv.importar`), NIAs (`nias.gestionar`), depósitos (`deposito.registrar`), mostrador (`mostrador.acceder`), entregas directas (`entrega.confirmar`), usuarios (`usuarios.gestionar`), backups (`backup.gestionar`, `restaurar.ejecutar`), auditoría (`auditoria.ver`) y métricas (`metricas.ver`).
   - Registro de rutas `GET /admin/roles`, `POST /admin/roles` y `POST /admin/roles/restaurar` protegidas por `roles.gestionar`.
2. **Navegación y Vistas:**
   - `app/views/layouts/base.php`: Enlace a `/admin/csv` condicionado a `puede('csv.importar')`. Enlace a `/admin/roles` en el menú de administración si `puede('roles.gestionar')`.
   - `app/views/admin/panel.php`: Acciones rápidas y secciones condicionadas a `puede('csv.importar')` y adición de la tarjeta Sección 10 Matriz de Permisos para `puede('roles.gestionar')`.
   - `app/views/admin/mostrador.php`: Tarjeta y sección de Entrega Directa condicionadas a `puede('entrega.confirmar')`.

---

## 5. Resultados de Pruebas (T-ROLE-01..05)

Se incorporaron a `tests/run.php` los 5 tests de la fase:
- **T-ROLE-01:** Acceso a `/admin/roles` devuelve 200 para `ADMIN`, 403 para `PERSONAL` y `USUARIO`. (PASS)
- **T-ROLE-02:** Desmarcar `csv.importar` para `PERSONAL` provoca 403 en `/admin/csv` y oculta enlaces en navbar; al reasignarlo devuelve 200 y el enlace reaparece. (PASS)
- **T-ROLE-03:** Desmarcar `entrega.confirmar` oculta la tarjeta en `/mostrador` y devuelve 403 en `/mostrador/entrega-directa`; al reasignar reaparece y devuelve 200. (PASS)
- **T-ROLE-04:** Intento de eliminar permisos base mediante POST directo a `/admin/roles` es ignorado: los permisos base permanecen activos inmutablemente. (PASS)
- **T-ROLE-05:** Modificaciones de la matriz quedan auditadas en `registro_auditoria` con valores `anterior` vs `nuevo`; la opción «Restaurar por defecto» restablece exactamente los permisos del seed canónico. (PASS)

**Resultado Global:** `PASS: 96, FAIL: 0, SKIP: 0`.
Suite completamente en verde.
