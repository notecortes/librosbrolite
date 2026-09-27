# Guía de Puesta en Producción — BookSwap (v4.7)

Esta guía documenta los pasos necesarios para desplegar **BookSwap** en un servidor web de producción (Hosting compartido con cPanel/Plesk, VPS con Ubuntu/Debian, o servidor dedicado con Apache/Nginx, PHP 8.2+ y MySQL/MariaDB).

---

## Índice
1. [Requisitos del Servidor](#1-requisitos-del-servidor)
2. [Despliegue de Archivos y Permisos](#2-despliegue-de-archivos-y-permisos)
3. [Configuración de Entorno (.env / config.php)](#3-configuración-de-entorno-env--configphp)
4. [SQL Completo de Producción](#4-sql-completo-de-producción)
5. [Cómo Borrar Libros y Usuarios de Prueba](#5-cómo-borrar-libros-y-usuarios-de-prueba)
6. [Funcionamiento de las Notificaciones de Lista de Deseos](#6-funcionamiento-de-las-notificaciones-de-lista-de-deseos)
7. [Tareas Programadas (Cron)](#7-tareas-programadas-cron)
8. [Verificación de Seguridad Post-Despliegue](#8-verificación-de-seguridad-post-despliegue)

---

## 1. Requisitos del Servidor

- **PHP:** Versión **8.2** o superior.
- **Extensiones de PHP obligatorias:**
  - `pdo_mysql` (conexión a la base de datos)
  - `curl` (consulta a Google Books / OpenLibrary y autenticación Google OAuth)
  - `mbstring` (manipulación segura de cadenas UTF-8)
  - `fileinfo` y `gd` (procesamiento seguro de portadas de libros e imágenes de perfil)
  - Función nativa `mail()` habilitada (para el restablecimiento de contraseñas)
- **Base de datos:** **MySQL 8.0+** o **MariaDB 10.5+** con soporte `utf8mb4_unicode_ci` y motor `InnoDB`.
- **Servidor Web:**
  - **Apache** con módulo `mod_rewrite` habilitado (para soporte de URLs amigables vía `.htaccess`).
  - O **Nginx** configurado para redirigir las peticiones a `public/index.php`.
- **Certificado SSL / HTTPS:** Obligatorio para cookies seguras y Google OAuth.

---

## 2. Despliegue de Archivos y Permisos

### 2.1. Estructura y Subida de Archivos
Sube los archivos del proyecto al directorio raíz del servidor (por ejemplo, `/var/www/html` o `public_html`).

- **Opción recomendada (Raíz web en `/public`):**  
  Configura el DocumentRoot del dominio apuntando directamente a la subcarpeta `public/`.
- **Opción para hosting compartido tradicional:**  
  Si el hosting sirve directamente desde la raíz del espacio FTP, el archivo `.htaccess` incluido en la raíz del proyecto redirigirá de manera transparente todo el tráfico hacia `public/index.php`, protegiendo carpetas internas como `app/`, `config/` y `backups/`.

### 2.2. Permisos de Escritura (CHMOD)
El usuario del servidor web (`www-data`, `nobody` o el usuario FTP) debe tener permisos de escritura (`755` o `775`) en los siguientes directorios:
```bash
chmod -R 755 uploads/
chmod -R 755 uploads/covers/
chmod -R 755 backups/
```

---

## 3. Configuración de Entorno (.env / config.php)

Crea el archivo `.env` en la raíz del proyecto (o edita directamente [config/config.php](file:///config/config.php)):

```dotenv
APP_ENV=production
DB_HOST=localhost
DB_NAME=nombre_de_tu_base_de_datos
DB_USER=usuario_mysql
DB_PASS=tu_contrasena_segura
MAIL_FROM=no-reply@tudominio.com
```

> [!IMPORTANT]
> Establecer `APP_ENV=production` oculta trazas de errores PHP al usuario final, desactiva avisos de depuración en pantalla y activa el modo de seguridad estricto.

---

## 4. SQL Completo de Producción

A continuación se detalla el script SQL completo y limpio para inicializar la base de datos de producción desde cero en phpMyAdmin o la consola MySQL.

Incluye:
- Todas las **15 tablas relacionales** (`roles`, `permisos`, `rol_permiso`, `usuarios`, `libros`, `ejemplares`, `transacciones`, `movimientos_tokens`, `wishlist`, `notificaciones`, `registro_auditoria`, `intentos_login`, `password_resets`, `backups`, `configuracion`).
- Claves foráneas con integridad referencial (`ON DELETE CASCADE` / `ON DELETE SET NULL`) e índices de alto rendimiento.
- Semilla de **roles** y **matriz de permisos RBAC** completa.
- **Configuración central** del centro de intercambio.
- **Usuario Administrador inicial:**
  - **Email:** `admin@bookswap.local` (puedes cambiarlo tras la importación o desde la pantalla de perfil)
  - **Contraseña inicial:** `password` (debe cambiarse inmediatamente en el primer inicio de sesión)

```sql
-- ============================================================
-- BookSwap · Base de Datos Completa para Producción (v4.7)
-- Motor: InnoDB · Juego de caracteres: utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. TABLAS DEL SISTEMA DE ROLES Y PERMISOS (RBAC)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS rol_permiso;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
  id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
  id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo  VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
  rol_id      TINYINT UNSIGNED  NOT NULL,
  permiso_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. TABLA DE USUARIOS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NULL,
  rol_id            TINYINT UNSIGNED NOT NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultima_actividad  DATETIME NULL,
  google_sub        VARCHAR(255) NULL UNIQUE,
  auth_provider     ENUM('local','google','ambos') NOT NULL DEFAULT 'local',
  email_verificado  TINYINT(1) NOT NULL DEFAULT 0,
  foto_url          VARCHAR(500) NULL,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. CATÁLOGO DE LIBROS Y EJEMPLARES FÍSICOS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS ejemplares;
DROP TABLE IF EXISTS libros;

CREATE TABLE libros (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn13          VARCHAR(17) NULL UNIQUE,
  titulo          VARCHAR(255) NOT NULL,
  autor           VARCHAR(255) NOT NULL,
  editorial       VARCHAR(120) NULL,
  anio            SMALLINT UNSIGNED NULL,
  genero          VARCHAR(80) NULL,
  idioma          VARCHAR(10) NOT NULL DEFAULT 'es',
  portada_url     VARCHAR(500) NULL,
  observaciones   VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_libros_titulo (titulo),
  INDEX idx_libros_autor  (autor),
  INDEX idx_libros_genero (genero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ejemplares (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  libro_id        INT UNSIGNED NOT NULL,
  estado          ENUM('disponible','reservado','retirado','baja') NOT NULL DEFAULT 'disponible',
  ubicacion       VARCHAR(30) NULL,
  condicion       ENUM('nuevo','como_nuevo','bueno','aceptable','deteriorado') NOT NULL DEFAULT 'bueno',
  fecha_ingreso   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  depositante_id  INT UNSIGNED NULL,
  CONSTRAINT fk_ejemplares_libro       FOREIGN KEY (libro_id)       REFERENCES libros(id),
  CONSTRAINT fk_ejemplares_depositante FOREIGN KEY (depositante_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_ejemplares_estado (estado),
  INDEX idx_ejemplares_libro  (libro_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. TRANSACCIONES Y ECONOMÍA DE TOKENS (LEDGER INMUTABLE)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS transacciones;
CREATE TABLE transacciones (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo            ENUM('deposito','reserva','entrega_directa') NOT NULL,
  ejemplar_id     INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  tokens          INT NOT NULL DEFAULT 0,
  metodo_pago     ENUM('tokens','libro') NULL,
  codigo          VARCHAR(30) NULL UNIQUE,
  estado          ENUM('activa','entregada','expirada','cancelada') NOT NULL DEFAULT 'activa',
  fecha_limite    DATETIME NULL,
  fecha_entrega   DATETIME NULL,
  gestionada_por  INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trans_ejemplar  FOREIGN KEY (ejemplar_id)   REFERENCES ejemplares(id),
  CONSTRAINT fk_trans_usuario   FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  CONSTRAINT fk_trans_gestion   FOREIGN KEY (gestionada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_trans_estado_limite (estado, fecha_limite),
  INDEX idx_trans_usuario_created (usuario_id, created_at),
  INDEX idx_trans_gestionada (gestionada_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS movimientos_tokens;
CREATE TABLE movimientos_tokens (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id        INT UNSIGNED NOT NULL,
  cantidad          INT NOT NULL,
  tipo              ENUM('deposito','retiro','bono','bono_bienvenida','ajuste','bloqueo_reserva','liberacion_reserva') NOT NULL,
  transaccion_id    BIGINT UNSIGNED NULL,
  concepto          VARCHAR(255) NOT NULL,
  saldo_resultante  INT NOT NULL,
  fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id)     REFERENCES usuarios(id),
  CONSTRAINT fk_mov_trans   FOREIGN KEY (transaccion_id) REFERENCES transacciones(id) ON DELETE SET NULL,
  INDEX idx_mov_usuario_fecha (usuario_id, fecha),
  INDEX idx_mov_transaccion (transaccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. LISTA DE DESEOS, NOTIFICACIONES Y AUDITORÍA
-- ------------------------------------------------------------
DROP TABLE IF EXISTS wishlist;
CREATE TABLE wishlist (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  libro_id    INT UNSIGNED NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notificado  TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_wishlist_usuario_libro (usuario_id, libro_id),
  CONSTRAINT fk_wishlist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_libro   FOREIGN KEY (libro_id)   REFERENCES libros(id)   ON DELETE CASCADE,
  INDEX idx_wishlist_libro (libro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS notificaciones;
CREATE TABLE notificaciones (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NOT NULL,
  mensaje     VARCHAR(255) NOT NULL,
  leida       TINYINT(1) NOT NULL DEFAULT 0,
  url         VARCHAR(255) NULL,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_not_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_not_usuario_leida (usuario_id, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS registro_auditoria;
CREATE TABLE registro_auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,
  accion      VARCHAR(80) NOT NULL,
  entidad     VARCHAR(60) NULL,
  entidad_id  BIGINT UNSIGNED NULL,
  detalle     TEXT NULL,
  ip          VARCHAR(45) NULL,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_aud_accion_fecha (accion, fecha),
  INDEX idx_aud_entidad (entidad, entidad_id),
  INDEX idx_aud_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS intentos_login;
CREATE TABLE intentos_login (
  id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email  VARCHAR(190) NOT NULL,
  ip     VARCHAR(45) NULL,
  fecha  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intentos_email_fecha (email, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira     DATETIME NOT NULL,
  usado      TINYINT(1) NOT NULL DEFAULT 0,
  creado     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_pr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS backups;
CREATE TABLE backups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  archivo    VARCHAR(255) NOT NULL,
  tamano     INT UNSIGNED NULL,
  tipo       ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT UNSIGNED NULL,
  CONSTRAINT fk_bak_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS configuracion;
CREATE TABLE configuracion (
  clave  VARCHAR(80) NOT NULL PRIMARY KEY,
  valor  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 6. DATOS INICIALES (SEMILLA DE PRODUCCIÓN)
-- ============================================================

-- Roles canónicos
INSERT INTO roles (id, nombre) VALUES
  (1, 'ADMIN'),
  (2, 'PERSONAL'),
  (3, 'USUARIO');

-- Permisos RBAC
INSERT INTO permisos (id, codigo) VALUES
  (1,  'usuario.base'),
  (2,  'catalogo.ver'),
  (3,  'reserva.crear'),
  (4,  'mostrador.acceder'),
  (5,  'catalogo.editar'),
  (6,  'deposito.registrar'),
  (7,  'entrega.confirmar'),
  (8,  'csv.importar'),
  (10, 'usuarios.gestionar'),
  (11, 'config.editar'),
  (12, 'backup.gestionar'),
  (13, 'restaurar.ejecutar'),
  (14, 'auditoria.ver'),
  (15, 'metricas.ver'),
  (16, 'roles.gestionar');

-- Asignación de permisos a roles
-- ADMIN: Todos los permisos
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, id FROM permisos;

-- PERSONAL: Operaciones de mostrador y catálogo
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
  (2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8);

-- USUARIO: Funcionalidades básicas de lector
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
  (3, 1), (3, 2), (3, 3);

-- Configuración inicial del sistema
INSERT INTO configuracion (clave, valor) VALUES
  ('coste_libro',          '1'),
  ('bono_deposito',        '1'),
  ('bono_bienvenida',      '0'),
  ('penalizar_expiracion', '0'),
  ('horas_reserva',        '72'),
  ('max_reservas_activas', '3'),
  ('reset_horas',          '1'),
  ('dias_backup_auto',     '7'),
  ('retencion_backups',    '10'),
  ('ultimo_backup_auto',   NULL),
  ('centro_nombre',        'BookSwap — Biblioteca Ciudadana'),
  ('centro_direccion',     'Calle Mayor 1, 46001 Valencia'),
  ('centro_telefono',      '960 000 000'),
  ('centro_email',         'info@bookswap.local'),
  ('centro_horario',       'Lunes a Viernes de 9:00 a 14:00 y de 16:00 a 20:00'),
  ('centro_mapa_lat',      '39.4699'),
  ('centro_mapa_lng',      '-0.3763'),
  ('google_client_id',     '');

-- Usuario Administrador principal inicial
-- Contraseña predeterminada: "password" (bcrypt). Cambiar tras el primer login.
INSERT INTO usuarios (id, nombre, email, password_hash, rol_id, activo, fecha_registro, ultima_actividad, auth_provider, email_verificado)
VALUES (
  1,
  'Administrador',
  'admin@bookswap.local',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  1,
  1,
  NOW(),
  NOW(),
  'local',
  1
);
```

---

## 5. Cómo Borrar Libros y Usuarios de Prueba

Si has estado utilizando la aplicación en fase de pruebas o desarrollo y deseas **limpiar todos los datos ficticios** (libros de prueba, copias, reservas, transacciones, histórico de tokens y lectores de prueba), manteniendo intactos al Administrador, los roles, los permisos y la configuración, ejecuta la siguiente consulta en phpMyAdmin o MySQL:

```sql
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Vaciar transacciones, reservas y contabilidad de tokens
TRUNCATE TABLE transacciones;
TRUNCATE TABLE movimientos_tokens;
TRUNCATE TABLE wishlist;
TRUNCATE TABLE notificaciones;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE intentos_login;
TRUNCATE TABLE registro_auditoria;

-- 2. Vaciar catálogo de libros y ejemplares físicos
TRUNCATE TABLE ejemplares;
TRUNCATE TABLE libros;

-- 3. Eliminar todos los usuarios excepto el Administrador principal (ID = 1)
DELETE FROM usuarios WHERE id > 1;
ALTER TABLE usuarios AUTO_INCREMENT = 2;

SET FOREIGN_KEY_CHECKS = 1;
```

> [!TIP]
> Si también deseas eliminar las imágenes de portadas de prueba descargadas en disco, vacía la carpeta `uploads/covers/` (asegurándote de mantener la carpeta con permisos de escritura).

---

## 6. Funcionamiento de las Notificaciones de Lista de Deseos

BookSwap cuenta con un sistema reactivo de avisos automáticos para libros sin existencias:

1. **Suscripción del lector:**
   - Cuando un libro del catálogo tiene **0 ejemplares disponibles**, la ficha del libro (`/libro/{id}`) muestra el botón **«Avisarme cuando esté disponible»**.
   - Al pulsarlo, el lector queda registrado en la tabla `wishlist` y el botón cambia a **«Aviso activado (Cancelar aviso)»**.
2. **Generación automática del aviso:**
   - En cualquier momento en que un ejemplar de ese libro pasa a estado `'disponible'` (bien sea por un **depósito de un lector en mostrador**, un **alta de biblioteca**, la **cancelación o expiración de una reserva previa**, o una **importación CSV**), el sistema dispara la función `wishlist_notificar_disponibilidad($pdo, $libroId)`.
3. **Recepción por parte del lector:**
   - Se crea de manera instantánea una notificación en el panel del lector:  
     *«¡El libro '{Título}' que esperabas ya tiene ejemplares disponibles en la biblioteca!»*
   - La campana de la barra superior incrementa el contador de notificaciones no leídas.
   - Al hacer clic sobre el aviso, el usuario es dirigido directamente a la ficha del libro (`/libro/{id}`), donde el botón **«Reservar ahora»** ya estará habilitado para que pueda retirar o apartar su copia.
   - El aviso en la lista de deseos queda marcado como notificado para no duplicar alertas.

---

## 7. Tareas Programadas (Cron)

Para que las reservas que superen las horas límite (por defecto 72 horas) expiren automáticamente liberando los libros y notificando tanto al usuario como a los lectores en lista de espera, se recomienda añadir una tarea cron en el servidor:

```bash
# Ejecutar cada hora la comprobación de expiración de reservas
0 * * * * curl -s -k "https://tudominio.com/cron/expirar" > /dev/null 2>&1
```

O bien mediante script CLI:
```bash
0 * * * * php /ruta-al-proyecto/bin/cron_expirar.php > /dev/null 2>&1
```

---

## 8. Verificación de Seguridad Post-Despliegue

1. **Cambiar la contraseña del Administrador:** Accede a `https://tudominio.com/login` con `admin@bookswap.local` / `password` y cambia la clave inmediatamente.
2. **Personalizar datos del centro:** En el panel de administración (`/admin` → *Configuración*), actualiza el nombre del centro, dirección, teléfono, coordenadas del mapa y horario.
3. **Comprobar envío de correos:** Solicita un restablecimiento de contraseña desde `/olvidar` para certificar que el servidor envía correos correctamente a bandejas reales.
4. **Verificar permisos de carpetas sensibles:** Comprueba que no sea posible listar el contenido de `/backups/` ni `/app/` directamente desde el navegador web.
