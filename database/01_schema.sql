-- ============================================================
-- BookSwap · 01_schema.sql (v4.7) — CANÓNICO
-- Motor: InnoDB · utf8mb4_unicode_ci · MariaDB 10.11 / MySQL 8
-- ============================================================

SET NAMES utf8mb4;

-- ---------- RBAC ----------
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

-- ---------- Usuarios ----------
CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(120) NOT NULL,
  email             VARCHAR(190) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NULL,                     -- NULL ⇒ cuenta google-only
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



-- ---------- Catálogo cerrado de libros admitidos ----------
CREATE TABLE libros (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn13          VARCHAR(17) NULL UNIQUE,                 -- opcional; NULL permitido (alta manual)
  titulo          VARCHAR(255) NOT NULL,
  autor           VARCHAR(255) NOT NULL,
  editorial       VARCHAR(120) NULL,
  anio            SMALLINT UNSIGNED NULL,
  genero          VARCHAR(80) NULL,
  idioma          VARCHAR(10) NOT NULL DEFAULT 'es',
  portada_url     VARCHAR(500) NULL,                       -- URL o NULL ⇒ placeholder SVG
  observaciones   VARCHAR(500) NULL,
  estado          ENUM('activo','baja') NOT NULL DEFAULT 'activo',
  motivo_baja     VARCHAR(255) NULL,
  fecha_baja      DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_libros_titulo (titulo),
  INDEX idx_libros_autor  (autor),
  INDEX idx_libros_genero (genero),
  INDEX idx_libros_estado (estado)
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

-- ---------- Transacciones: depósitos y reservas ----------
CREATE TABLE transacciones (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo            ENUM('deposito','reserva','entrega_directa') NOT NULL,
  ejemplar_id     INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  tokens          INT NOT NULL DEFAULT 0,                  -- importe aplicado en su momento
  metodo_pago     ENUM('tokens','libro') NULL,             -- solo entregas de reserva
  codigo          VARCHAR(30) NULL UNIQUE,                 -- código/QR de la reserva
  estado          ENUM('activa','entregada','expirada','cancelada') NOT NULL DEFAULT 'activa',
  fecha_limite    DATETIME NULL,                           -- fin de la reserva
  fecha_entrega   DATETIME NULL,
  gestionada_por  INT UNSIGNED NULL,                       -- PERSONAL/ADMIN que gestionó
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trans_ejemplar  FOREIGN KEY (ejemplar_id)   REFERENCES ejemplares(id),
  CONSTRAINT fk_trans_usuario   FOREIGN KEY (usuario_id)    REFERENCES usuarios(id),
  CONSTRAINT fk_trans_gestion   FOREIGN KEY (gestionada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_trans_estado_limite (estado, fecha_limite),
  INDEX idx_trans_usuario_created (usuario_id, created_at),
  INDEX idx_trans_gestionada (gestionada_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Ledger de tokens (inmutable) ----------
CREATE TABLE movimientos_tokens (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id        INT UNSIGNED NOT NULL,
  cantidad          INT NOT NULL,                          -- con signo
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

-- ---------- Notificaciones / auditoría / sistema ----------
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

CREATE TABLE registro_auditoria (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED NULL,                           -- NULL = acciones del sistema
  accion      VARCHAR(80) NOT NULL,
  entidad     VARCHAR(60) NULL,
  entidad_id  BIGINT UNSIGNED NULL,
  detalle     TEXT NULL,                                   -- JSON
  ip          VARCHAR(45) NULL,
  fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_aud_accion_fecha (accion, fecha),
  INDEX idx_aud_entidad (entidad, entidad_id),
  INDEX idx_aud_usuario_fecha (usuario_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intentos_login (
  id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email  VARCHAR(190) NOT NULL,
  ip     VARCHAR(45) NULL,
  fecha  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intentos_email_fecha (email, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE backups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  archivo    VARCHAR(255) NOT NULL,
  tamano     INT UNSIGNED NULL,
  tipo       ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id INT UNSIGNED NULL,
  CONSTRAINT fk_bak_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
  clave  VARCHAR(80) NOT NULL PRIMARY KEY,
  valor  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;