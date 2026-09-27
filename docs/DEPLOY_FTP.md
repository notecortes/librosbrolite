# Guía de Despliegue en Hosting Compartido vía FTP (v4.7)

Esta guía describe el procedimiento para desplegar **BookSwap** en un entorno de alojamiento compartido tradicional (cPanel, Plesk, DirectAdmin o Apache con soporte PHP 8.2+ y MySQL/MariaDB) utilizando transferencia de archivos mediante FTP/SFTP.

---

## 1. Requisitos Previos del Servidor

- **PHP 8.2 o superior** con las siguientes extensiones habilitadas:
  - `pdo_mysql`
  - `curl` (para portadas remotas y Google OAuth)
  - `mbstring`
  - `gd` o `fileinfo` (para procesamiento de imágenes y descargas seguras)
  - Función nativa `mail()` habilitada (ver sección 5)
- **MySQL 8.0+ o MariaDB 10.5+** con soporte `utf8mb4_unicode_ci`.
- **Servidor web Apache** con `mod_rewrite` habilitado (soporte de `.htaccess`).

---

## 2. Preparación de Archivos y Subida por FTP/SFTP

1. **Estructura del Proyecto:**
   - La raíz pública del hosting suele ser `public_html` o `www`.
   - Se recomienda colocar el contenido de `public/` dentro de la raíz web, o apuntar el DocumentRoot a `public/`.
   - Si el hosting sirve directamente desde la raíz, el archivo `.htaccess` del proyecto redirige de forma transparente todas las peticiones a `public/`.

2. **Permisos de Escritura (CHMOD):**
   Asegurar que el usuario de Apache/PHP tenga permisos de escritura (`755` o `775`) en:
   - `uploads/` y `uploads/covers/` (portadas de libros y avatares)
   - `backups/` (copias de seguridad de base de datos)

---

## 3. Configuración de Base de Datos y Entorno

1. **Importación de Esquema Inicial:**
   - Crear una base de datos MySQL en el panel de control del hosting (ej: `u123456_bookswap`).
   - Crear un usuario de base de datos con todos los privilegios sobre dicha base de datos.
   - Importar `database/01_schema.sql` y posteriormente `database/02_seed.sql`.

2. **Ajuste de Credenciales en `config/config.php`:**
   En entornos sin variables de entorno del sistema, configurar los valores de conexión:
   ```php
   define('APP_ENV',      'production'); // Desactiva visualización de errores y avisos de desarrollo
   define('DB_HOST',      'localhost');
   define('DB_NAME',      'u123456_bookswap');
   define('DB_USER',      'u123456_bsuser');
   define('DB_PASS',      'TuPasswordSeguro123!');
   define('MAIL_FROM',    'no-reply@tudominio.edu.es'); // Dominio autorizado en el servidor
   ```

---

## 4. Verificación de Seguridad Post-Despliegue

1. **Comprobar que `APP_ENV` está fijado en `'production'`:**
   - Evita la fuga de trazas de error y desactiva las tarjetas de depuración locales (como el enlace de recuperación en pantalla de `/olvidar`).
2. **Probar el inicio de sesión del Administrador:**
   - Acceder a `https://tudominio.com/login` con `admin@bookswap.local` (o la cuenta configurada).
   - Cambiar inmediatamente la contraseña por defecto en el panel de perfil.
3. **Verificar HTTPS:**
   - Asegurarse de que el certificado SSL (Let's Encrypt o corporativo) esté activo y redireccionando tráfico HTTP a HTTPS.

---

## 5. Pruebas del Servicio de Correo Electrónico y Fallback Manual

BookSwap utiliza la función nativa `mail()` de PHP para el envío de instrucciones de restablecimiento de contraseña (`/olvidar`), garantizando compatibilidad nativa con servidores compartidos sin requerir librerías externas.

### 5.1. Prueba de Envío Real tras el Despliegue
1. Acceder a `https://tudominio.com/olvidar`.
2. Introducir una dirección de correo real de prueba registrada en la plataforma.
3. Comprobar la recepción del correo en la bandeja de entrada:
   - **Remitente:** Debe coincidir con la configuración `MAIL_FROM` o el correo del centro en la configuración.
   - **Enlace de restablecimiento:** Verificar que el enlace `https://tudominio.com/reset/{token}` abre el formulario de nueva contraseña.
   - **Revisar carpeta de Spam:** Si el correo llega a la carpeta de correo no deseado, verificar que el dominio del servidor cuenta con registros **SPF** y **DKIM** autorizados para el host del servidor web.

### 5.2. Procedimiento de Contingencia (Fallback Manual vía ADMIN)
En centros educativos o entornos donde:
- El servidor compartido tiene bloqueada la función `mail()`.
- Hay retrasos en la cola de salida del servidor de correo.
- Los filtros antispam del centro bloquean mensajes automatizados.
- El usuario (alumno, profesor o lector) no dispone de acceso inmediato a su buzón de correo.

**Procedimiento operativo alternativo:**
1. El usuario se presenta físicamente en el mostrador o contacta con el administrador.
2. El administrador accede al panel de gestión: `/admin/usuarios`.
3. Localiza la fila del usuario y pulsa en el menú de acciones: **"🔑 Generar enlace de acceso"**.
4. El sistema genera de forma atómica un token de 64 caracteres hex con expiración temporal (1 hora) y hash SHA-256 en BD.
5. El sistema presenta una alerta con el enlace completo y el botón **"Copiar Enlace"**.
6. El administrador entrega el enlace directamente en persona o por canal interno seguro al usuario para que defina su nueva contraseña en `/reset/{token}`.
