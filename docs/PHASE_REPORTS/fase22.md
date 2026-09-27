# Reporte de Fase 22 — Recuperación de Contraseña (v4.7)

**Fecha:** 26 de septiembre de 2026  
**Estado:** COMPLETADA  
**Resultado de tests:** 120 PASS / 0 FAIL / 0 SKIP (Suite 100% verde)

---

## 1. Objetivos Alcanzados

1. **Doble Vía de Recuperación:**
   - **Autoservicio por Correo (`/olvidar` → `/reset/{token}`):** Los lectores pueden solicitar el restablecimiento mediante su correo electrónico registrado.
   - **Vía Presencial de Administrador (`/admin/usuarios`):** El administrador puede generar un enlace de acceso directo para entregar en mano al usuario cuando este se encuentra físicamente en el mostrador o carece de acceso a su buzón.

2. **Seguridad y Anti-Enumeración:**
   - **Respuesta genérica uniforme:** Ante cualquier solicitud en `POST /olvidar`, el sistema responde siempre con el mismo mensaje genérico: *"Si ese correo está registrado, recibirás las instrucciones"*, independientemente de si la cuenta existe, no existe o está inactiva.
   - **Criptografía robusta:** Se genera un token criptográfico de 64 caracteres hexadecimales con entropía de 32 bytes (`random_bytes(32)`). La base de datos almacena exclusivamente el hash SHA-256 (`token_hash`), garantizando que una filtración de base de datos nunca comprometa los tokens en tránsito.
   - **Caducidad y un solo uso:** Los tokens expiran tras `reset_horas` (configurable en base de datos, por defecto 1 hora) y se marcan como usados tras su primer empleo. Cualquier solicitud posterior con el mismo token es rechazada con un aviso amable.
   - **Invalidación en cascada:** Al solicitar un nuevo token o al completar el cambio de contraseña, todos los tokens activos previos del usuario se invalidan de inmediato (`usado = 1`).
   - **Control de frecuencia (Rate-limiting):** Máximo 3 peticiones por correo electrónico en 1 hora mediante `intentos_login` con prefijo `reset:`. La 4ª petición es rechazada con HTTP 429.

3. **Compatibilidad con Cuentas de Google:**
   - Para cuentas asociadas a Google OAuth (`password_hash` es `NULL`):
     - `POST /olvidar` responde con el mensaje genérico y **NO crea token** en la tabla `password_resets`.
     - Se remite un correo explicativo indicando que la cuenta se autentica a través de Google y cómo acceder con el botón oficial.
     - En el panel de administración (`/admin/usuarios`), el botón de generación de enlace de acceso no aparece para estas cuentas.

4. **Integración con Hosting Compartido y Degradación Controlada:**
   - Envío mediante `mail()` nativo de PHP con cabeceras estándar (`From`, `Reply-To`, `MIME-Version`, `Content-Type: text/plain; charset=UTF-8`), compatible con cualquier hosting compartido.
   - Dirección remitente configurable mediante constante `MAIL_FROM` o correo del centro en la configuración.
   - En entornos locales o de desarrollo (`APP_ENV=development` o contenedores Docker sin MTA), el sistema detecta la ausencia de servidor de correo y muestra en pantalla una tarjeta destacada con el enlace directo para facilitar las pruebas funcionales sin comprometer la seguridad en producción.

---

## 2. Cambios en Base de Datos

- **`database/01_schema.sql`**:
  - Creación de la tabla `password_resets`:
    ```sql
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
    ```
- **`database/02_seed.sql`**:
  - Añadida configuración en la tabla `configuracion`:
    ```sql
    ('reset_horas', '1')
    ```

---

## 3. Asistentes, Controladores y Vistas Desarrollados

- **`app/helpers/password_reset.php`**:
  - `email_enviar($destinatario, $asunto, $cuerpo)`: Envío seguro mediante `mail()` con soporte para persistencia temporal en tests.
  - `email_ultimo_enviado()`: Inspección del último mensaje despachado.
  - `password_reset_intentos($email)` y `password_reset_registrar_intento($email, $ip)`: Control de rate-limiting (3/hora).
  - `password_reset_generar_enlace($token)`: Construcción de URL absoluta.
  - `password_reset_solicitar($pdo, $email, $ip)`: Validación, anti-enumeración, token SHA-256 y despacho.
  - `password_reset_validar_token($pdo, $token)`: Comprobación de existencia, no expiración y no uso.
  - `password_reset_completar($pdo, $token, $nuevaPassword)`: Política de contraseñas (mínimo 8 caracteres), actualización de hash BCRYPT, invalidación de tokens y auditoría `password_reset_completado`.
  - `admin_usuario_generar_enlace_reset($pdo, $targetUserId, $adminId)`: Generación presencial con auditoría `password_reset_enlace`.
- **`app/router.php`**:
  - Ruta pública `GET /olvidar` y `POST /olvidar`.
  - Ruta dinámica `GET /reset/{token}` y `POST /reset/{token}` con validación CSRF y regeneración de sesión.
  - Ruta protegida `POST /admin/usuarios/reset-enlace` (solo administradores con `usuarios.gestionar`).
- **Vistas:**
  - `app/views/auth/olvidar.php`: Formulario de recuperación con degradación controlada en desarrollo.
  - `app/views/auth/reset.php`: Formulario de nueva contraseña con tarjeta amable ante tokens caducados.
  - `app/views/auth/login.php`: Enlace añadido `¿Olvidaste tu contraseña?` junto a la etiqueta del campo.
  - `app/views/admin/usuarios/index.php`: Acción `🔑 Generar enlace de acceso` (oculta para cuentas de Google) y modal de visualización única con copia al portapapeles.

---

## 4. Pruebas Automatizadas

- **Nuevas pruebas implementadas (`T-RESET-01..06`):**
  - `T-RESET-01`: POST `/olvidar` con email existente genera fila en `password_resets` (con hash SHA-256, expiración a 1 hora y `usado=0`), devuelve respuesta genérica y muestra el enlace en desarrollo.
  - `T-RESET-02`: Flujo integral completo: `/reset/{token}` → formulario → nueva contraseña → login correcto con la nueva contraseña e incorrecto con la anterior; reintento de uso del mismo token rechazado amablemente.
  - `T-RESET-03`: Token con `expira` en el pasado muestra página amable de enlace caducado con botón para pedir uno nuevo.
  - `T-RESET-04`: Email inexistente produce la misma respuesta genérica sin filas en base de datos; la 4ª petición dentro de la misma hora activa el rate-limiting (HTTP 429).
  - `T-RESET-05`: Administrador genera enlace de acceso desde el panel; el enlace permite cambiar la contraseña una sola vez y queda auditado (`password_reset_enlace`); los roles PERSONAL y USUARIO reciben 403 Forbidden.
  - `T-RESET-06`: Cuenta google-only: no se genera token en base de datos y se despacha correo explicativo orientado a la autenticación con Google.
- **Actualización de prueba existente:**
  - `T-ADMIN-04`: Actualizada de la antigua clave temporal en texto plano a la nueva semántica de generación de enlace de acceso único con hash SHA-256 y auditoría `password_reset_enlace`.

---

## 5. Resumen de la Suite

```text
════════ RESUMEN ════════
  PASS: 120   FAIL: 0   SKIP: 0

✔ SUITE VERDE
```
