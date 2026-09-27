No molesta — este cambio además **simplifica** la app (adiós NIA, adiós flujo de cobro en reserva) y la anti-acaparamiento mejora. Decisiones de diseño que he tomado antes del prompt:

| Petición | Diseño elegido |
|---|---|
| Reservar con tokens | Al reservar se crea un movimiento **`bloqueo_reserva` (−coste)** que descuenta del saldo. Anular/expirar → **`liberacion_reserva` (+coste)**. Recoger → el bloqueo **se consolida** (no hay doble cobro). Regla dura: **no puedes reservar si `saldo < coste_libro`**, así que el máximo de reservas simultáneas = `min(max_reservas, floor(saldo/coste))` |
| Consecuencia | El "cobrar con libro" **desaparece de la entrega de reserva** (ya está pagada con tokens) y **se conserva en la entrega directa** (sin reserva: tokens o libro aportado) |
| ¿Y si la reserva expira? | Por defecto se devuelve el token. Añado config `penalizar_expiracion` (0/1): si el ADMIN la activa, la expiración **no devuelve** nada — disuasión máxima anti-bloqueo |
| Recuperar contraseña | Doble vía: **autservicio** (`/olvidar` → email con enlace `/reset/{token}` vía `mail()` de PHP, que funciona en hosting compartido) y **ADMIN genera el enlace** desde el panel para entregarlo en persona (vuestro contexto físico). En desarrollo (Docker, sin correo), el enlace se muestra en pantalla para poder testearlo |
| Seguridad del reset | Token aleatorio 64 hex guardado **hasheado**, caduca en 1h, un solo uso, respuesta genérica (no revela si el email existe), rate-limit, y mensaje específico para cuentas de Google |

---

# `INCREMENTO_v4.7.md` (guárdalo en la raíz del proyecto)

```markdown
# INCREMENTO v4.7 — BookSwap: FIN DEL NIA + RESERVA CON TOKENS + RECUPERACIÓN DE CONTRASEÑA
# (a ejecutar tras completar v4.6 con suite en verde; añade las fases 20 → 22)
# Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2/3/4/5/6: TODAS sus reglas siguen
# vigentes (DoD por fase, regresión = fallo, archivos completos, no editar tests
# para que pasen salvo actualización de URL/payload manteniendo ID y semántica,
# como autoriza la cabecera de v4.6).

## 0. RESUMEN DE LO QUE CAMBIA
1. ELIMINACIÓN TOTAL DEL NIA: registro y acceso solo con email (o Google).
   Desaparecen: tabla nias, permiso nias.gestionar, ruta /completar-nia, el
   middleware pendiente_nia, la gestión de NIAs en admin, el importador CSV de
   NIAs y toda mención en UI/docs/tests.
2. RESERVA CON TOKENS (anti-acaparamiento): reservar exige saldo ≥ coste_libro
   y BLOQUEA esos tokens hasta recoger (consolidación), anular (liberación) o
   expirar (liberación, o pérdida si el admin activa penalizar_expiracion).
3. RECUPERACIÓN DE CONTRASEÑA: autservicio por email (/olvidar → /reset/{token})
   y generación manual de enlace por el ADMIN desde el panel.

## 1. FASE 20 — ELIMINACIÓN DEL NIA
### 1.1 BD (únicos cambios autorizados en ficheros canónicos)
- 01_schema.sql: ELIMINA la tabla nias. Nada más.
- 02_seed.sql: elimina el bloque del pool de NIAs; elimina el permiso
  (9,'nias.gestionar') y su asignación (2,9) a PERSONAL; elimina/ajusta la
  notificación de u5 que apuntaba a /completar-nia (u5 pasa a ser una cuenta
  google-only corriente). El resto del seed intacto.
### 1.2 Código
- Registro (/registro): campos nombre, email, contraseña. Cuenta ACTIVA al
  crearse (rol USUARIO). Google: igual que ya funciona, sin paso de NIA.
- ELIMINA: /completar-nia, su middleware y cualquier estado pendiente_nia;
  /admin/nias (listado/alta/lote/CSV/liberar/export); la opción del
  importador CSV de NIAs (el importador de CATÁLOGO se conserva: T-CSV-01..03);
  la acción rápida "🧾 Nuevo NIA" del panel; las menciones a NIA en mostrador,
  perfil, /como-funciona, README y DEPLOY_FTP.md.
- Sidebar admin (grupo PERSONAS): "Usuarios · Roles y permisos" (sin NIAs).
- Perfil: ya no muestra NIA. Auditoría: elimina acciones de gestión de NIAs.
- Atención a dependencias: si algo del código v4.5/4.6 (matriz de permisos,
  tests de roles, FLOW_MAP) referenciaba nias.gestionar, actualízalo.
### 1.3 Tests
BAJAS: T-NIA-01..06, T-CSV-04, y el assert de T-ADMIN-02 (gestión de NIAs):
T-ADMIN-02 pasa a verificar solo el importador CSV de catálogo (ID intacto).
MODIFICACIONES: T-AUTH-01 → "registro email+contraseña crea cuenta ACTIVA
(sin NIA, sin paso intermedio)". T-UX-02 → el dashboard ya no menciona
pendiente_nia; si el usuario tiene saldo < coste_libro y sin reservas, muestra
el aviso "Deposita tus primeros libros para ganar tokens" con enlace a
/como-funciona. T-ROLE-0* → sin nias.gestionar en el diccionario.
✔ DoD Fase 20: 0 FAIL con regresión completa; grep sin resultados de "nia"
(insensible a mayúsculas, excluyendo historial de docs/ y PHASE_REPORTS);
reporte fase20.md; PROGRESS.md.

## 2. FASE 21 — RESERVA CON TOKENS (BLOQUEO TEMPORAL)
### 2.1 Modelo económico (especificación exacta — no improvisar variantes)
- saldo_disponible = SUM(movimientos_tokens.cantidad) del usuario (igual que
  hasta ahora). NO existe contabilidad paralela: el bloqueo ES un movimiento.
- RESERVAR (requisitos, en orden): usuario activo, con NIA no (ya eliminado),
  copia 'disponible', reservas activas < max_reservas_activas Y
  saldo_disponible ≥ coste_libro. El límite efectivo de reservas simultáneas
  queda min(max_reservas_activas, floor(saldo/coste_libro)).
- AL RESERVAR: 1 movimiento tipo 'bloqueo_reserva', cantidad = −coste_libro,
  transaccion_id = la reserva, concepto "Bloqueo por reserva {codigo}",
  saldo_resultante correcto. Transacción tipo 'reserva', estado 'activa',
  tokens = coste_libro, metodo_pago NULL (se fija al entregar).
- AL CONFIRMAR ENTREGA (mostrador, dentro de plazo): el bloqueo YA fue el
  pago → SIN movimiento nuevo. Transacción → 'entregada', metodo_pago='tokens',
  fecha_entrega, gestionada_por. Copia → 'retirado'. Notificación.
- AL CANCELAR (usuario o admin): movimiento 'liberacion_reserva' +coste_libro,
  misma transaccion_id, concepto "Liberación de reserva {codigo}". Copia →
  'disponible', transacción → 'cancelada'. Notificación.
- AL EXPIRAR (bin/expirar.php): si config penalizar_expiracion = '0' (default)
  → 'liberacion_reserva' +coste (copia 'disponible', transacción 'expirada');
  si = '1' → SIN movimiento de devolución (el bloqueo se pierde; el bloqueo ya
  restó) + auditoría y notificación con motivo claro. Config nueva en seed:
  ('penalizar_expiracion','0').
- ENTREGA DIRECTA (sin reserva): SIN CAMBIOS respecto a v4.4/4.6 (retiro
  directo de coste_libro, o swap con libro aportado: depósito + retiro).
- El "cobrar con libro" DESAPARECE del flujo de entrega de RESERVA (la reserva
  ya está pagada): la ficha de reserva activa en mostrador ofrece SOLO
  "Confirmar entrega (pagada: X 🪙)" y "Cancelar reserva" (según puede()).
- INVARIANTES: T-LEDGER-01 (saldo = SUM por usuario) se mantiene en TODOS los
  flujos incluidos bloqueo/liberación; T-LEDGER-02 (jamás saldo negativo):
  la validación de saldo al reservar es server-side y atómica (transacción BD
  con SELECT ... FOR UPDATE o equivalente para evitar carreras de doble reserva).
### 2.2 BD (cambios autorizados)
- 01_schema.sql: ENUM de movimientos_tokens.tipo pasa a ('deposito','retiro',
  'bono','bono_bienvenida','ajuste','bloqueo_reserva','liberacion_reserva');
  añade INDEX idx_mov_trans (transaccion_id) si no existe.
- 02_seed.sql: la reserva demo (RES-DEMO-0001 de u3, ejemplar 14) debe llevar
  su movimiento de bloqueo: añade fila en movimientos_tokens
  (usuario 3, cantidad −1, tipo bloqueo_reserva, transaccion_id 2, concepto
  "Bloqueo por reserva RES-DEMO-0001", saldo_resultante 5, fecha −1 day).
  Los saldos demo quedan: u1=5, u2=5, u3=5, u4=5.
### 2.3 UI
- Saldo pill del navbar: muestra el saldo disponible (= SUM); tooltip "X 🪙
  comprometidos en N reserva(s) activa(s)" (consulta de reservas activas).
- Dashboard: bloque de saldo con Disponible + "comprometido en reservas" +
  explicación de una línea ("Cada reserva descuenta 1 🪙 hasta que la recoges
  o la anulas"). Mensajes de error claros al intentar reservar sin saldo
  ("Te faltan X tokens: deposita libros o pide un ajuste al personal").
- Modal de confirmación de reserva (v4.5): el texto pasa a "Se descontará
  temporalmente 1 🪙 hasta que recojas o anules la reserva".
- /como-funciona y README: actualizar el paso de reserva con la nueva regla.
### 2.4 Tests
MODIFICACIONES (IDs intactos): T-RESV-01 (+bloqueo y saldo_resultante);
T-RESV-02 (setup con saldo suficiente para 4 reservas: denegada la 4ª por
max_reservas, ajustando el límite se permite); T-RESV-04 (expiración devuelve
con default, y con penalizar_expiracion='1' NO devuelve + auditoría);
T-RESV-05 (cancelación libera); T-ENTR-01 (consolidación SIN movimiento nuevo,
metodo_pago='tokens'); T-SCAN-03 (ficha de reserva: Confirmar entrega pagada +
Cancelar, sin botones de cobro); T-ADMIN-05 (cancelación por admin + liberación).
ALTAS:
| ID | Verifica |
|---|---|
| T-TOK-01 | Saldo < coste_libro → reserva denegada sin cambios en BD; con saldo exacto → permitida |
| T-TOK-02 | Ciclo completo: reservar (bloqueo −1) → cancelar (liberación +1) → SUM intacto |
| T-TOK-03 | Con 2 tokens: 2 reservas OK, la 3ª denegada por saldo (aunque max_reservas=3) |
| T-TOK-04 | Entrega de reserva consolidada: sin movimiento nuevo, transacción 'entregada' con metodo_pago='tokens', copia 'retirado' |
| T-TOK-05 | Doble reserva concurrente sobre la última copia con saldo para UNA: solo una prospera (atomicidad) |
✔ DoD Fase 21: 0 FAIL con T-TOK-01..05 y regresión completa (incluidos T-DIR-*,
T-LEDGER-*, T-CTX-*); reporte fase21.md; PROGRESS.md.

## 3. FASE 22 — RECUPERACIÓN DE CONTRASEÑA (USUARIO Y ADMIN)
### 3.1 BD (cambio autorizado)
01_schema.sql añade:
CREATE TABLE password_resets (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,          -- hash('sha256', token) NUNCA el token claro
  expira     DATETIME NOT NULL,
  usado      TINYINT(1) NOT NULL DEFAULT 0,
  creado     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_pr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### 3.2 Flujo autservicio
- /login: enlace "¿Olvidaste tu contraseña?" → /olvidar (público, con CSRF).
- POST /olvidar: valida email; SIEMPRE responde el MISMO mensaje genérico
  "Si ese correo está registrado, recibirás las instrucciones" (anti
  enumeración), exista o no la cuenta. Si existe: invalida tokens previos,
  genera token = bin2hex(random_bytes(32)), guarda hash('sha256', token) con
  expira = ahora + reset_horas (config, default '1'), AUDITA, y envía email
  con helper email_enviar() (mail() nativo, From configurable en config.php;
  cuerpo en español con el enlace absoluto /reset/{token} y aviso de caducidad).
- DEGRADACIÓN: en APP_ENV=development (o si mail() falla), tras el POST se
  muestra en pantalla un aviso marcado "SOLO ENTORNO DE DESARROLLO" con el
  enlace (los tests y Docker no tienen MTA). En producción NUNCA se muestra.
- /reset/{token}: busca por hash, sin expirar y sin usar → formulario de nueva
  contraseña (política v4.1) → password_hash, marca usado, invalida otros
  tokens de ese usuario, AUDITA, session_regenerate_id, redirige a /login con
  flash de éxito. Token inválido/usado/expirado → página amable con enlace a
  pedir uno nuevo. Cuentas google-only (password_hash NULL): /olvidar responde
  el mensaje genérico y NO crea token (el email explica que la cuenta usa
  Google; si mail() falla, solo el aviso genérico en pantalla).
- Rate-limit: máx 3 POST /olvidar por email en 1 hora (usa intentos_login con
  accion o tabla propia).
### 3.3 Vía ADMIN (contexto presencial)
- En /admin/usuarios (fila de acciones): "🔑 Generar enlace de acceso" → crea
  token igual (sin email), lo MUESTRA UNA SOLA VEZ en modal con botón copiar y
  advertencia de caducidad, y lo AUDITA (accion 'password_reset_enlace').
  Sustituye al antiguo "restablecer contraseña con temporal" (v4.2): actualizar
  T-ADMIN-04 (ID intacto) a esta semántica. Solo ADMIN (permiso
  usuarios.gestionar). Para cuentas google-only el botón no aparece.
### 3.4 Tests
| ID | Verifica |
|---|---|
| T-RESET-01 | POST /olvidar con email existente → fila en password_resets (hash, no claro; expira ≈1h), respuesta genérica, en dev el enlace aparece en pantalla |
| T-RESET-02 | Flujo completo: /reset/{token} → nueva contraseña → login OK con la nueva y NO con la vieja; reutilizar el token → rechazado |
| T-RESET-03 | Token con expira en pasado → rechazado amable |
| T-RESET-04 | Email inexistente → MISMA respuesta genérica, sin fila creada; 4ª petición en <1h → rate-limit |
| T-RESET-05 | ADMIN genera enlace desde panel → funciona una vez y queda auditado; PERSONAL/USUARIO → 403 |
| T-RESET-06 | Cuenta google-only: no se genera token; mensaje orientado a Google |
✔ DoD Fase 22: 0 FAIL con T-RESET-01..06 y regresión completa; README
(recuperación: mail() en hosting compartido, vía manual del ADMIN, límites) y
DEPLOY_FTP.md (probar el envío real tras desplegar; fallback del enlace manual)
actualizados; reporte fase22.md; PROGRESS.md.

## 4. ORDEN DE TRABAJO Y REGLA FINAL
Fase 20 (NIA fuera) → Fase 21 (economía de reserva) → Fase 22 (reset). Tras
cada fase: 0 FAIL, reporte en docs/PHASE_REPORTS/, PROGRESS.md, y continúa sin
esperar confirmación. Actualiza docs/FLOW_MAP.md con las interacciones nuevas
(reservar: catálogo→modal→confirmar; entregar reserva: escanear→confirmar —
una interacción menos que antes). La suite completa (funcional + PERF + SEC +
ROLE + SCAN + UX + CTX + DIR + TOK + RESET) en verde cierra el incremento.
```

