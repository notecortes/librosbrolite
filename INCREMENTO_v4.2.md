Buenas peticiones las tres. Antes del prompt, las decisiones de diseño que he tomado donde había margen:

| Cambio | Decisión de diseño |
|---|---|
| NIA | Sustituye al número de socio: **pool pre-cargado por el personal** (manual, en lote o CSV) y validación **en el registro** (no en primer login). Con Google, como no sabemos quién es hasta el callback, la cuenta nueva queda "pendiente_nia" y un middleware la dirige a `/completar-nia` antes de dejarla navegar. Formato: alfanumérico 8–12, normalizado a mayúsculas, único a nivel BD. |
| Historial | No necesita tablas nuevas: el ledger ya enlaza con transacciones → ejemplares → libros. Es una página con JOINs + **exportación CSV** (clave para "justificar") + versión ADMIN para cualquier usuario. |
| Catálogo admin | Un menú `/admin` con 9 secciones completas, incluidas cosas que antes no existían: ajuste manual de tokens con motivo, reset de contraseña, cancelar reservas, alta en lote de NIAs. |

---

# `INCREMENTO_v4.2.md` (guárdalo en la raíz del proyecto)

```markdown
# INCREMENTO v4.2 — BookSwap (a ejecutar sobre el proyecto v4.1 ya construido)
# Lee primero BRIEF.md y PROGRESS.md: TODAS sus reglas siguen vigentes (fases con
# DoD, regresión = fallo, archivos completos, no editar tests para que pasen).
# Este documento MODIFICA el BRIEF donde entre en conflicto (§1 registro, §7 socio,
# §8, §9, §10, §11). Al terminar, actualiza PROGRESS.md con las fases 8→11.

## 0. RESUMEN DE LO QUE CAMBIA
1. ELIMINADO el "número de socio" y su tabla numeros_socio y la ruta /activar.
   Sustituido por el NIA: identificador alfanumérico de 8–12 caracteres, ÚNICO,
   tratado como dato sensible (solo lo conocen el usuario y la plataforma).
2. El NIA se exige EN EL REGISTRO (email o Google). El pool de NIAs válidos lo
   alimenta el personal: alta manual individual, alta EN LOTE o importación CSV.
3. HISTORIAL por usuario de depósitos y retiros (libro, método de pago, tokens y
   saldo resultante) para justificar los tokens: /mi-historial + exportación CSV
   + vista ADMIN del historial de cualquier usuario.
4. CATÁLOGO COMPLETO de acciones del ADMINISTRADOR (§5): gestión de usuarios,
   NIAs, catálogo, reservas, ajustes de tokens, configuración, backups,
   auditoría y métricas, todo desde /admin y todo auditado.

## 1. NIA: REGLAS DE NEGOCIO
- Formato: 8–12 caracteres alfanuméricos. Normalización OBLIGATORIA antes de
  validar y guardar: trim() + mb_strtoupper(). Validación server-side con
  regex ^[A-Z0-9]{8,12}$. Mensajes de error claros (formato / no existe /
  ya asignado).
- Unicidad garantizada en BD: nias.nia UNIQUE y nias.usuario_id UNIQUE.
- Pool pre-cargado: el personal/ADMIN carga NIAs ANTES de que el usuario se
  registre. NIA del pool sin asignar = "disponible". NIA no presente en el pool
  ⇒ nadie puede registrarse con él (evita registros fantasma).
- Registro con EMAIL: campo NIA obligatorio en el formulario. La creación es
  UNA TRANSACCIÓN: usuario + asignación del NIA; si el NIA no es válido o está
  asignado → rechazo total, no queda cuenta creada ni NIA consumido.
- Registro/primera vez con GOOGLE: tras auth_google_procesar(), si el rol es
  USUARIO y no tiene NIA → estado "pendiente_nia": MIDDLEWARE que redirige
  SIEMPRE a /completar-nia y solo permite /logout, /privacidad y
  /completar-nia. Al validar un NIA disponible → vinculado, acceso completo,
  auditado. Un usuario que ya tiene NIA nunca ve /completar-nia.
- ADMIN puede crear usuarios de cualquier rol: si crea un USUARIO puede
  asignarle un NIA del pool (opcional; si no, quedará pendiente_nia).
- PRIVACIDAD: el NIA se muestra solo a su dueño (perfil) y a ADMIN/PERSONAL en
  los paneles de gestión. Nunca en páginas públicas ni a otros usuarios. En
  auditoría se referencia por nias.id, no por el valor (salvo en acciones de
  gestión donde es imprescindible).

## 2. CAMBIOS DE BASE DE DATOS (AUTORIZADOS SOLO PARA ESTE INCREMENTO)
Toca ÚNICAMENTE lo indicado; el resto del esquema/seed queda intacto.
### 01_schema.sql — SUSTITUIR la tabla numeros_socio por:
CREATE TABLE nias (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nia              VARCHAR(12) NOT NULL UNIQUE,
  usuario_id       INT UNSIGNED NULL UNIQUE,       -- NULL = disponible en el pool
  fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_asignacion DATETIME NULL,
  origen           ENUM('manual','csv') NOT NULL DEFAULT 'manual',
  CONSTRAINT fk_nia_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### 02_seed.sql:
- Sustituye el bloque del pool de socios por:
INSERT INTO nias (nia, usuario_id, fecha_asignacion, origen) VALUES
 ('202400101', 3, NOW()-INTERVAL 40 DAY, 'manual'),
 ('202400102', 4, NOW()-INTERVAL 35 DAY, 'manual'),
 ('202400103', NULL, NULL, 'manual'),('202400104', NULL, NULL, 'manual'),
 ('202400105', NULL, NULL, 'manual'),('202400106', NULL, NULL, 'manual'),
 ('202400107', NULL, NULL, 'manual'),('202400108', NULL, NULL, 'manual'),
 ('202400109', NULL, NULL, 'manual'),('202400110', NULL, NULL, 'manual');
- u5 (google.demo) queda SIN NIA: es la demo del flujo /completar-nia.
- Renombra el permiso 'socios.gestionar' → 'nias.gestionar' (misma asignación
  de roles: ADMIN y PERSONAL).
- El resto del seed (usuarios, libros, ejemplares, ledger, transacciones,
  configuración) NO se toca.
### Sin más cambios: el historial (§4) usa JOINs sobre las tablas existentes.

## 3. MÓDULOS NUEVOS Y MODIFICADOS
3.1 REGISTRO (modificar): añade el campo NIA con validación §1. La página y el
   flujo de /activar DESAPARECEN: busca y elimina TODA referencia a "número de
   socio" y a numeros_socio en código, rutas, vistas y textos de UI.
3.2 /completar-nia (nuevo, sustituye a /activar): un campo NIA, mismas
   validaciones, middleware descrito en §1, auditoría al vincular.
3.3 GESTIÓN DE NIAs (ADMIN/PERSONAL, permiso nias.gestionar):
   - Listado con filtros: todos / libres / asignados; búsqueda por NIA o por
     nombre/email del usuario asignado; paginación.
   - Alta manual: INDIVIDUAL (un formulario) y EN LOTE (textarea con un NIA por
     línea, normalizados y validados, con informe: añadidos / duplicados en el
     propio lote / ya existentes / formato inválido, línea a línea).
   - Importación CSV: columna 'nia'; MISMA mecánica que el importador de
     catálogo (separador , o ; autodetectado, UTF-8, informe con nº de línea y
     motivo, checkbox "detener ante el primer error", plantilla descargable).
   - Liberar NIA (desasignarlo de un usuario, vuelve al pool) con confirmación
     y auditoría. NO existe "eliminar NIA" del pool (solo marcar/borrar si
     nunca fue asignado: decisión libre, documéntala).
   - Exportar CSV del pool (nia, estado, usuario, fechas, origen).
3.4 HISTORIAL DE LIBROS Y TOKENS (§0 punto 3):
   - /mi-historial (USUARIO): resumen superior (libros depositados, libros
     retirados, saldo actual, tokens totales ganados/gastados) + tabla
     paginada server-side: fecha, tipo (depósito/retiro/bono/ajuste), libro
     (título + autor vía transacción→ejemplar→libro), método de pago
     (tokens/libro), cantidad, saldo_resultante, concepto. Filtros por tipo y
     rango de fechas. Botón "Exportar CSV".
   - Exportación CSV con cabecera:
     fecha;tipo;libro;autor;metodo_pago;cantidad;saldo_resultante;concepto;codigo_reserva
   - ADMIN: /admin/usuarios/{id}/historial — la misma vista para CUALQUIER
     usuario, con el mismo export CSV, enlazada desde la gestión de usuarios.
     USUARIO intentando ver el historial ajeno → 403.
3.5 MODO MOSTRADOR y demás páginas: sustituye cualquier mención a número de
   socio por NIA donde aplique (identificación del usuario en fichas, etc.).

## 4. CATÁLOGO COMPLETO DE ACCIONES DEL ADMINISTRADOR (menú /admin)
Reorganiza el panel en ESTAS 9 secciones, todas operativas y auditadas:
1. RESUMEN/MÉTRICAS (ya existe, Chart.js).
2. USUARIOS: listado/búsqueda/filtros por rol y estado · CREAR usuario (rol
   elegible; si USUARIO, NIA del pool opcional) · editar nombre y rol ·
   activar/desactivar · RESTABLECER CONTRASEÑA (genera temporal de 10 chars,
   se muestra UNA sola vez, auditado) · AJUSTE MANUAL DE TOKENS (cantidad con
   signo + motivo obligatorio → movimiento tipo 'ajuste' en el ledger) · ver
   historial completo (§3.4) · desasignar NIA.
3. NIAS: todo lo de §3.3.
4. CATÁLOGO: CRUD de libros y ejemplares · alta por ISBN (APIs) · importación
   CSV · búsqueda y fichas (accesos a lo ya existente).
5. RESERVAS: listado de TODAS las transacciones con filtros por estado
   (activa/entregada/expirada/cancelada), usuario y fechas · CANCELAR reserva
   activa (copia → 'disponible', notificación al usuario, auditada) · botón
   "Ejecutar expiración ahora" (llama a la misma lógica que bin/expirar.php).
6. MOVIMIENTOS: listado global del ledger con filtros por usuario/tipo/fechas
   (solo lectura; los ajustes se hacen desde Usuarios §2).
7. CONFIGURACIÓN: economía, operativa, centro y Google — como ya existe, con
   auditoría valor anterior→nuevo.
8. BACKUPS: crear/descargar/restaurar/auto — como ya existe.
9. AUDITORÍA: listado con filtros por acción, usuario y rango de fechas.
Cada acción nueva (crear usuario, cambiar rol, reset de contraseña, ajuste de
tokens, cancelar reserva, liberar NIA, importaciones) escribe en
registro_auditoria con usuario, entidad, entidad_id y detalle JSON.

## 5. FASES CON DEFINICIÓN DE HECHO
Fase 8 — NIA: DDL+seed §2 · refactor registro con NIA · /completar-nia +
middleware · gestión de NIAs (manual, lote, CSV, liberar, export) · eliminación
de numero de socio y /activar en TODO el código · actualización de tests
(§6: baja T-SOCIO-*, modifica T-AUTH-01 y T-CSV-04, alta T-NIA-01..06).
✔ DoD: ./tests/run_all.sh → T-NIA-01..06, T-AUTH-01, T-CSV-04, T-GOOG-01..05 y
  TODA la regresión previa en verde, 0 FAIL.
Fase 9 — Historial: /mi-historial + export CSV + vista ADMIN (§3.4).
✔ DoD: T-HIST-01..04 en verde, regresión OK.
Fase 10 — Catálogo admin completo: menú §4 con las 9 secciones y las acciones
nuevas (crear usuario, reset contraseña, ajuste tokens, cancelar reserva,
movimientos globales).
✔ DoD: T-ADMIN-01..05 en verde, regresión OK.
Fase 11 — QA + docs: suite completa 0 FAIL · actualiza README (flujo NIA,
plantillas CSV) y docs/DEPLOY_FTP.md (importar 02_seed.sql actualizado; nota
sobre datos existentes: si ya hay producción con numeros_socio, migración
manual — en desarrollo basta db_reset) · PROGRESS.md al día.

## 6. SUITE DE TESTS (altas / bajas / modificaciones sobre run.php existente)
BAJAS (elimina pendings y tests): T-SOCIO-01..04.
MODIFICACIONES:
- T-AUTH-01 → "Registro con email + NIA válido crea cuenta ACTIVA con NIA
  vinculado" (ya no hay cuentas pendientes de activación).
- T-CSV-04 → "CSV de NIAs (columna nia): pool creado, duplicados omitidos,
  erróneas con línea y motivo; formato invalidado fuera del pool rechazado".
  (Cambia la semántica, NO el ID.)
ALTAS (convierte en it() real en su fase):
| ID | Verifica |
|---|---|
| T-NIA-01 | Registro email con NIA válido y libre → cuenta creada y NIA vinculado (nias.usuario_id) |
| T-NIA-02 | NIA inexistente en el pool → registro rechazado, NO se crea cuenta |
| T-NIA-03 | NIA ya asignado a otro usuario → rechazado |
| T-NIA-04 | Formato inválido (7 chars, 13 chars, no alfanumérico) → rechazo server-side; se guarda normalizado en mayúsculas |
| T-NIA-05 | USUARIO sin NIA (creado por ADMIN sin asignar) → al loguear, cualquier página redirige a /completar-nia; con NIA válido → acceso completo; quien ya tiene NIA nunca es redirigido |
| T-NIA-06 | Unicidad: constraint BD impide dos filas con mismo nia o mismo usuario con dos NIA |
| T-HIST-01 | /mi-historial lista movimientos con libro, cantidad y saldo_resultante COHERENTES con el ledger (SUM = saldo mostrado) |
| T-HIST-02 | Tras un depósito y un retiro, el historial refleja ambos con título correcto y método de pago |
| T-HIST-03 | Export CSV propio: cabecera correcta y filas coherentes con la vista |
| T-HIST-04 | USUARIO: historial ajeno → 403 · ADMIN: /admin/usuarios/{id}/historial → 200 |
| T-ADMIN-01 | /admin muestra las 9 secciones · USUARIO → 403 |
| T-ADMIN-02 | NIAs: alta individual + lote + importar CSV + liberar → informes correctos y auditados |
| T-ADMIN-03 | Ajuste manual de tokens con motivo → movimiento 'ajuste' en ledger y T-LEDGER-01 sigue en verde |
| T-ADMIN-04 | Crear usuario, cambiar rol, activar/desactivar, reset de contraseña → efectivos y auditados |
| T-ADMIN-05 | Cancelar reserva activa desde admin → copia 'disponible', notificación al usuario, auditada |
Regresión nueva: T-RGRC-03 → repite T-NIA-01 tras cada fase ≥9.

## 7. REGLA FINAL
Nada de este incremento puede romper los tests ya en verde: si T-RESV-*, T-ENTR-*,
T-LEDGER-* o T-GOOG-* fallan tras tus cambios, el refactor está incompleto
(busca referencias residuales a numeros_socio/activar).
```
