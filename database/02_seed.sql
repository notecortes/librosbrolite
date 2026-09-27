-- ============================================================
-- BookSwap · 02_seed.sql (v4.7) — CANÓNICO. Se ejecuta tras 01_schema.sql.
-- El resto (usuarios, libros, ejemplares, transacciones, ledger, config)
-- es IDÉNTICO al v4.1: no alterar números que los tests ya usan.
-- CONTRASEÑAS DEMO: TODAS "password" (bcrypt cost 10).
-- Si un login demo fallara: docker compose exec app php bin/seed_passwords.php
-- IMPORTANTE: no existe el email nuevo.google@example.com (lo usa T-GOOG-05).
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Roles y permisos ----------
INSERT INTO roles (id, nombre) VALUES (1,'ADMIN'), (2,'PERSONAL'), (3,'USUARIO');

INSERT INTO permisos (id, codigo) VALUES
 (1,'usuario.base'),(2,'catalogo.ver'),(3,'reserva.crear'),(4,'mostrador.acceder'),
 (5,'catalogo.editar'),(6,'deposito.registrar'),(7,'entrega.confirmar'),
 (8,'csv.importar'),(10,'usuarios.gestionar'),
 (11,'config.editar'),(12,'backup.gestionar'),(13,'restaurar.ejecutar'),
 (14,'auditoria.ver'),(15,'metricas.ver');

INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT 1, id FROM permisos;
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
 (2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8);
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
 (3,1),(3,2),(3,3);

INSERT INTO permisos (id, codigo) VALUES (16,'roles.gestionar');
INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (1,16);

-- ---------- Usuarios demo ----------
SET @hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; -- "password"

INSERT INTO usuarios (id, nombre, email, password_hash, rol_id, activo, fecha_registro,
                      ultima_actividad, auth_provider, email_verificado) VALUES
 (1,'Admin Demo','admin@bookswap.local',       @hash,1,1, NOW()-INTERVAL 60 DAY, NOW()-INTERVAL 1 DAY, 'local',1),
 (2,'Personal Demo','personal@bookswap.local', @hash,2,1, NOW()-INTERVAL 55 DAY, NOW()-INTERVAL 2 DAY, 'local',1),
 (3,'Usuaria Demo','usuario@bookswap.local',   @hash,3,1, NOW()-INTERVAL 40 DAY, NOW()-INTERVAL 1 DAY, 'local',1),
 (4,'Cuenta Mixta','mixta@bookswap.local',     @hash,3,1, NOW()-INTERVAL 35 DAY, NOW()-INTERVAL 3 DAY, 'ambos',1),
 (5,'Google Demo','google.demo@bookswap.local', NULL,3,1, NOW()-INTERVAL 10 DAY, NOW()-INTERVAL 10 DAY,'google',1);
-- u5 = USUARIO google-only corriente.

-- ---------- Configuración v4.2 ----------
INSERT INTO configuracion (clave, valor) VALUES
 -- Economía
 ('coste_libro','1'),
 ('bono_deposito','1'),
 ('bono_bienvenida','0'),
 ('penalizar_expiracion','0'),
 -- Operativa
 ('horas_reserva','72'),
 ('max_reservas_activas','3'),
 ('reset_horas','1'),
 -- Backups
 ('dias_backup_auto','7'),
 ('retencion_backups','10'),
 ('ultimo_backup_auto', NULL),
 -- Datos del punto físico (Visítanos + footer global)
 ('centro_nombre','BookSwap — Biblioteca Ciudadana'),
 ('centro_direccion','Calle de los Libros 42, 28004 Madrid'),
 ('centro_telefono','+34 910 123 456'),
 ('centro_email','hola@bookswap.local'),
 ('centro_horario','{"lunes":["09:00-14:00","17:00-20:00"],"martes":["09:00-14:00","17:00-20:00"],"miercoles":["09:00-14:00","17:00-20:00"],"jueves":["09:00-14:00","17:00-20:00"],"viernes":["09:00-14:00","17:00-20:00"],"sabado":["10:00-13:30"],"domingo":[]}'),
 ('centro_mapa_lat','40.4168'),
 ('centro_mapa_lng','-3.7038'),
 ('centro_mapa_proveedor','google'),
 ('centro_como_llegar','Metro: Banco de España (L2) a 5 min a pie. Bus: 14, 27, 45. Aparcamiento en Plaza de las Cortes.'),
 -- Login con Google (vacío = botón oculto, app 100% funcional sin él)
 ('google_client_id',''),
 ('google_client_secret',''),
 ('google_redirect_uri','http://localhost:8080/auth/google/callback');

-- ---------- Catálogo demo: 15 libros admitidos ----------
INSERT INTO libros (id, isbn13, titulo, autor, editorial, anio, genero, portada_url) VALUES
 (1 ,'9788437604197','Don Quijote de la Mancha','Miguel de Cervantes','Cátedra',1605,'Clásicos','https://covers.openlibrary.org/b/isbn/9788437604197-L.jpg?default=false'),
 (2 ,'9780451524935','1984','George Orwell','Signet',1949,'Distopía','https://covers.openlibrary.org/b/isbn/9780451524935-L.jpg?default=false'),
 (3 ,'9781400034986','Cien años de soledad','Gabriel García Márquez','Vintage',1967,'Novela','https://covers.openlibrary.org/b/isbn/9781400034986-L.jpg?default=false'),
 (4 ,'9788478884459','Harry Potter y la piedra filosofal','J. K. Rowling','Salamandra',1997,'Fantasía','https://covers.openlibrary.org/b/isbn/9788478884459-L.jpg?default=false'),
 (5 ,'9788408175695','La sombra del viento','Carlos Ruiz Zafón','Debolsillo',2003,'Novela negra','https://covers.openlibrary.org/b/isbn/9788408175695-L.jpg?default=false'),
 (6 ,'9788445000639','El Hobbit','J. R. R. Tolkien','Minotauro',1937,'Fantasía','https://covers.openlibrary.org/b/isbn/9788445000639-L.jpg?default=false'),
 (7 ,'9780345391803','Guía del autoestopista galáctico','Douglas Adams','Del Rey',1979,'Ciencia ficción','https://covers.openlibrary.org/b/isbn/9780345391803-L.jpg?default=false'),
 (8 ,'9780141439518','Orgullo y prejuicio','Jane Austen','Penguin',1813,'Clásicos','https://covers.openlibrary.org/b/isbn/9780141439518-L.jpg?default=false'),
 (9 ,'9788437610570','Crimen y castigo','Fiódor Dostoyevski','Cátedra',1866,'Clásicos','https://covers.openlibrary.org/b/isbn/9788437610570-L.jpg?default=false'),
 (10,'9788499890953','Un mundo feliz','Aldous Huxley','Debolsillo',1932,'Distopía','https://covers.openlibrary.org/b/isbn/9788499890953-L.jpg?default=false'),
 (11,'9780307454546','Los hombres que no amaban a las mujeres','Stieg Larsson','Vintage',2005,'Novela negra','https://covers.openlibrary.org/b/isbn/9780307454546-L.jpg?default=false'),
 (12,'9780316769488','El guardián entre el centeno','J. D. Salinger','Little, Brown',1951,'Novela','https://covers.openlibrary.org/b/isbn/9780316769488-L.jpg?default=false'),
 (13,'9781451673319','Fahrenheit 451','Ray Bradbury','Simon & Schuster',1953,'Ciencia ficción','https://covers.openlibrary.org/b/isbn/9781451673319-L.jpg?default=false'),
 (14,'9780156012195','El principito','Antoine de Saint-Exupéry','Harcourt',1943,'Infantil','https://covers.openlibrary.org/b/isbn/9780156012195-L.jpg?default=false'),
 (15,'9780061120084','Matar a un ruiseñor','Harper Lee','Harper Perennial',1960,'Novela','https://covers.openlibrary.org/b/isbn/9780061120084-L.jpg?default=false');

-- ---------- Ejemplares: 13 disponibles + 1 reservado (demo) + 1 retirado ----------
INSERT INTO ejemplares (id, libro_id, estado, ubicacion, fecha_ingreso, depositante_id, condicion) VALUES
 (1 ,1 ,'disponible','A-01-02', NOW()-INTERVAL 30 DAY, 3, 'como_nuevo'),
 (2 ,2 ,'disponible','A-02-01', NOW()-INTERVAL 30 DAY, 3, 'bueno'),
 (3 ,2 ,'disponible','A-02-02', NOW()-INTERVAL 25 DAY, 4, 'bueno'),
 (4 ,3 ,'disponible','B-01-04', NOW()-INTERVAL 22 DAY, 3, 'como_nuevo'),
 (5 ,4 ,'disponible','B-01-05', NOW()-INTERVAL 21 DAY, 3, 'bueno'),
 (6 ,5 ,'disponible','B-02-01', NOW()-INTERVAL 20 DAY, 4, 'bueno'),
 (7 ,6 ,'disponible','C-01-01', NOW()-INTERVAL 20 DAY, 4, 'bueno'),
 (8 ,6 ,'disponible','C-01-02', NOW()-INTERVAL 20 DAY, 4, 'aceptable'),
 (9 ,7 ,'disponible','C-02-01', NOW()-INTERVAL 18 DAY, 3, 'bueno'),
 (10,8 ,'disponible','C-02-02', NOW()-INTERVAL 15 DAY, 3, 'bueno'),
 (11,9 ,'disponible','D-01-01', NOW()-INTERVAL 15 DAY, 4, 'aceptable'),
 (12,10,'disponible','D-01-02', NOW()-INTERVAL 12 DAY, 3, 'bueno'),
 (13,11,'disponible','D-02-01', NOW()-INTERVAL 10 DAY, 4, 'bueno'),
 (14,12,'reservado' ,'D-02-02', NOW()-INTERVAL 1  DAY, 3, 'bueno'),   -- copia de la reserva demo
 (15,14,'retirado'  ,'E-01-01', NOW()-INTERVAL 31 DAY, 3, 'como_nuevo'); -- depósito de u3, ya retirado

-- ---------- Transacciones demo ----------
INSERT INTO transacciones (id, tipo, ejemplar_id, usuario_id, tokens, codigo, estado,
                           fecha_limite, fecha_entrega, gestionada_por, created_at) VALUES
 (1,'deposito',15,3,1,NULL,'entregada',NULL,NOW()-INTERVAL 30 DAY,2,NOW()-INTERVAL 31 DAY),
 (2,'reserva' ,14,3,1,'RES-DEMO-0001','activa',NOW()+INTERVAL 72 HOUR,NULL,NULL,NOW()-INTERVAL 1 DAY);

-- ---------- Ledger demo (T-LEDGER-01: saldo = SUM por usuario) ----------
INSERT INTO movimientos_tokens (usuario_id, cantidad, tipo, transaccion_id, concepto, saldo_resultante, fecha) VALUES
 (1, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 60 DAY),
 (2, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 55 DAY),
 (3, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 40 DAY),
 (3, 1, 'deposito', 1,    'Depósito: El principito',               6, NOW()-INTERVAL 30 DAY),
 (3,-1, 'bloqueo_reserva', 2, 'Bloqueo por reserva RES-DEMO-0001', 5, NOW()-INTERVAL 1 DAY),
 (4, 5, 'bono',     NULL, 'Bono inicial otorgado por el personal', 5, NOW()-INTERVAL 35 DAY);
-- Saldos: u1=5, u2=5, u3=5, u4=5, u5=0 (sin movimientos).

-- ---------- Notificaciones demo ----------
INSERT INTO notificaciones (usuario_id, mensaje, leida, url, fecha) VALUES
 (3,'Reserva creada: El guardián entre el centeno. Recógelo en el mostrador antes de la fecha límite.',0,'/mis-reservas', NOW()-INTERVAL 1 DAY),
 (5,'Bienvenido/a a BookSwap.',0,'/catalogo', NOW()-INTERVAL 10 DAY);

-- ---------- Auditoría inicial ----------
INSERT INTO registro_auditoria (usuario_id, accion, entidad, entidad_id, detalle, ip, fecha) VALUES
 (2,'seed_inicial','sistema',NULL,'{"nota":"Datos de demostración instalados (v4.2)"}','127.0.0.1', NOW());