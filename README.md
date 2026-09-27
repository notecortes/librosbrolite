# BookSwap — Biblioteca Ciudadana (v4.7)

Plataforma comunitaria de gestión e intercambio de libros para bibliotecas ciudadanas y centros educativos. Desarrollada en **PHP 8.2 puro** con arquitectura modular sin frameworks, diseño responsivo accesible y soporte completo para modo claro y modo oscuro.

---

## 🚀 Inicio Rápido con Docker

El entorno incluye contenedores listos para ejecución con **PHP 8.2 Apache**, **MariaDB 10.11** y **phpMyAdmin**:

```bash
# 1. Clonar el repositorio y acceder a la carpeta
cd librosbrolite

# 2. Levantar los contenedores en segundo plano
docker compose up -d --build

# 3. Acceso a las aplicaciones en su navegador
# Aplicación web principal:  http://localhost:8088
# Gestor de base de datos:    http://localhost:8091
#   - Usuario root:     root / root_secret_dev
#   - Usuario app:      bookswap / bookswap_pass
```

---

## 👥 Cuentas de Acceso Demostrativas

La base de datos se inicializa automáticamente con datos de demostración y cinco perfiles con diferentes roles y estados:

| Rol / Tipo | Correo Electrónico | Contraseña | Descripción / Propósito |
|---|---|---|---|
| **Administrador** | `admin@bookswap.local` | `password` | Control total: configuración, matriz de permisos (/admin/roles), métricas, gestión de usuarios, catálogo, backups y auditoría. |
| **Personal** | `personal@bookswap.local` | `password` | Operativa diaria: escáner universal 1D, mostrador tablet-first, entregas directas y gestión de catálogo. |
| **Usuario Lector** | `usuario@bookswap.local` | `password` | Lector activo, dashboard personal como home, saldo de tokens, reserva en 1 clic y códigos de barras Code 128. |
| **Cuenta Mixta** | `mixta@bookswap.local` | `password` | Lector con doble autenticación (`local` y `google`). |
| **Usuario Google** | `google.demo@bookswap.local` | *(Google OAuth)* | Cuenta de demostración para el flujo de acceso con Google OAuth. |

---

## 🧪 Suite de Pruebas Automatizadas

El proyecto cuenta con una suite integral de **120 tests automatizados** (120 PASS, 0 FAIL, 0 SKIP) desarrollada en PHP puro (`tests/run.php`), que valida la integridad de cada módulo sin herramientas externas:

```bash
# Ejecución directa desde el host:
./tests/run_all.sh

# O ejecutándolo dentro del contenedor de la aplicación:
docker compose exec app php tests/run.php
```

**Resultado de la suite:**
```text
════════ RESUMEN ════════
  PASS: 120   FAIL: 0   SKIP: 0

✔ SUITE VERDE
```

---

## 👤 Flujo de Identificación y Registro

El acceso se realiza mediante correo electrónico y contraseña o mediante autenticación con Google:

1. **Registro Directo (`/registro`):**
   - Durante el registro con nombre, correo electrónico y contraseña, la cuenta se crea de forma inmediata en estado **activo** con rol de Lector (USUARIO).
   - El sistema otorga de forma atómica el bono de bienvenida en tokens si está configurado.

2. **Acceso con Google OAuth:**
   - Permite a los usuarios autenticarse con su cuenta institucional o personal directamente sin pasos intermedios.

---

## 🔑 Recuperación, Enlaces de Acceso y Gestión de Cuentas (v4.7)

LibrosBro ofrece un flujo integral adaptado tanto al autoservicio como a la atención presencial de centros educativos y bibliotecas:

1. **Autoservicio por Correo Electrónico (`/olvidar` y `/reset/{token}`):**
   - El usuario introduce su correo electrónico registrado.
   - **Anti-enumeración estricta:** el sistema responde siempre con el mismo mensaje genérico: *"Si ese correo está registrado, recibirás las instrucciones"*, independientemente de si la cuenta existe, no existe o está inactiva.
   - **Control de abusos (Rate limiting):** máximo 3 solicitudes por email en una ventana de 1 hora mediante `intentos_login`.
   - **Seguridad del token:** se genera un token criptográfico aleatorio de 64 caracteres hexadecimales (`random_bytes(32)`). En base de datos únicamente se almacena su hash SHA-256 (`token_hash`), nunca el token en claro.
   - **Expiración y uso único:** el token expira automáticamente tras `reset_horas` (configurable en el panel, por defecto 1 hora) y queda invalidado tras su primer uso. Al completarse el cambio, se invalidan todos los tokens pendientes del usuario.
   - **Envío mediante `mail()` nativo de PHP:** no requiere dependencias pesadas ni servidores SMTP externos; funciona de forma transparente en hosting compartido tradicional. La dirección `From` es configurable mediante `MAIL_FROM` en `config.php` o el correo del centro en la configuración.
   - **Cuentas vinculadas a Google:** si se solicita recuperación para un usuario cuya cuenta utiliza Google OAuth (`password_hash` es `NULL`), no se crea ningún token en base de datos y se remite un correo explicativo indicando que debe iniciar sesión mediante el botón de Google.
   - **Degradación controlada en desarrollo:** en entornos locales o de pruebas (`APP_ENV=development` o Docker sin MTA configurado), el enlace directo se muestra en pantalla para permitir la verificación sin depender de un servidor de correo. En producción este aviso nunca se muestra.

2. **Generación Manual con Código QR In Situ (`/admin/usuarios`):**
   - Pensada para el mostrador presencial cuando el alumno o lector está presente y no tiene acceso a su correo en ese momento.
   - El administrador (con permiso `usuarios.gestionar`) pulsa **"🔑 Generar enlace de acceso"** en la fila del usuario.
   - Se despliega en pantalla un **código QR de alta definición** generado en el cliente (`qrcode.min.js`, 100% offline y privado).
   - El usuario enfoca con la cámara de su móvil y accede de inmediato para establecer su nueva contraseña in situ.
   - Incluye botón **«Ampliar QR»** para pantallas lejanas o mamparas de mostrador y botón **«Imprimir Pase»** para generar un comprobante impreso temporal.

3. **Eliminación Definitiva de Cuenta e Historial (`/admin/usuarios`):**
   - Permite al Administrador borrar por completo una cuenta de usuario a petición del interesado o por gestión administrativa.
   - **Purga total de historial:** en una única transacción atómica se liberan sus reservas activas (los ejemplares regresan a estado `disponible`), se borran sus movimientos en el ledger de tokens (`movimientos_tokens`), todas sus transacciones (`transacciones`), su lista de deseos (`wishlist`), sus notificaciones (`notificaciones`), tokens de reseteo (`password_resets`) y sus registros de rate-limit (`intentos_login`). Si aportó libros físicos a la biblioteca, los ejemplares permanecen en el fondo general sin depositante asociado.
   - **Liberación inmediata del correo:** al eliminarse la cuenta por completo, el correo electrónico queda 100% libre para que la persona pueda volver a registrarse desde cero en cualquier momento si lo desea.
   - **Seguridad y auditoría:** no permite el auto-borrado del administrador con sesión activa ni el borrado del superadministrador principal (`id=1`). La acción queda registrada en `registro_auditoria` (`usuario.eliminar`).

---

## 📑 Plantillas de Archivos CSV

BookSwap soporta carga y descarga estructurada en formato CSV para diversos flujos operativos:

### 1. CSV para Catálogo de Libros (`/admin/importar-catalogo`)
Admite separadores por coma (`,`) o punto y coma (`;`). Puede utilizar cabeceras estándar o extendidas del centro:
```csv
titulo,autor,isbn,editorial,categoria,descripcion
"Cien años de soledad","Gabriel García Márquez","9788439732471","Literatura Random House","Novela","Obra maestra del realismo mágico"
```
O formato de exportación de centros educativos:
```csv
Curso,Título,Autor,Editorial,Comentarios,Cantidad en la red
"4º ESO","La metamorfosis","Franz Kafka","Alianza","Lectura trimestral",3
```



### 3. CSV para Exportación del Historial (`/mi-historial/csv` y `/admin/usuarios/{id}/historial/csv`)
Exporta el registro completo de operaciones con delimitador punto y coma (`;`) y codificación UTF-8 con BOM para máxima compatibilidad con Microsoft Excel y LibreOffice:
```csv
fecha;tipo;libro;autor;metodo_pago;cantidad;saldo_resultante;concepto;codigo_reserva
"2026-09-24 10:15:00";"depósito";"Rayuela";"Julio Cortázar";"bono_deposito";1;4;"Depósito voluntario";""
"2026-09-24 11:30:20";"retiro";"1984";"George Orwell";"tokens";-1;3;"Retiro en mostrador";"RES-908123"
```

---

## 🛠️ Panel de Administración (10 Secciones Operativas)

El panel `/admin` cuenta con un sidebar lateral estructurado en tres grupos lógicos (**Operación**, **Personas** y **Sistema**) con acceso a 10 módulos y alertas en tiempo real ("Pendientes de atención"):

1. **📊 Resumen y Métricas (`/admin/metricas`):**
   - Cuadros de mando interactivos con Chart.js: desglose de ejemplares por estado, volumen de tokens en circulación y actividad reciente.
2. **👥 Gestión de Usuarios (`/admin/usuarios`):**
   - Listado, búsqueda y filtros por rol y estado.
   - Alta de usuarios con rol elegible.
   - Modificación de nombre y rol.
   - Activación / desactivación de cuentas con protección de auto-bloqueo.
   - **Restablecimiento de contraseña temporal:** Genera una clave aleatoria segura de 10 caracteres que se muestra una sola vez con botón de copiado rápido, auditando el evento.
   - **Ajuste manual de tokens:** Posibilidad de sumar o restar saldo con motivo obligatorio, reflejado inmediatamente en el libro mayor (`ledger`) como movimiento de tipo `ajuste`.
   - Acceso directo a la vista de historial de cada usuario.
4. **📚 Catálogo y Libros (`/admin/libros`):**
   - CRUD de libros y ejemplares físicos, alta asistida por ISBN (APIs de Open Library / Google Books) e importador masivo CSV.
5. **🎟️ Gestión de Reservas (`/admin/reservas`):**
   - Listado global de transacciones con filtros por estado (`activa`, `entregada`, `expirada`, `cancelada`), fechas y usuario.
   - **Cancelación administrativa de reservas activas:** Retorna de inmediato el ejemplar a estado `disponible`, notifica al usuario y registra la acción en auditoría.
   - **Botón "Ejecutar Expiración Ahora":** Dispara la liberación inmediata de reservas caducadas sin esperar al cron de servidor.
6. **📈 Movimientos Globales (`/admin/movimientos`):**
   - Libro mayor contable en modo solo lectura con filtros por usuario, tipo de operación y rango temporal.
7. **🔐 Permisos y Roles (`/admin/roles`):**
   - Matriz configurable de permisos del personal agrupada por categorías funcionales.
   - Protección de permisos base inmutables requeridos para la operatividad mínima.
   - Control de versión de permisos (`permisos_version`) con invalidación en caliente de sesiones activas.
   - Botón de restauración rápida al estado canónico y trazabilidad completa en auditoría.
8. **⚙️ Configuración del Sistema (`/admin/configuracion`):**
   - Parámetros de economía de tokens, tiempos de reserva, datos del centro para la página Visítanos y credenciales de Google OAuth, con auditoría de valor anterior frente a valor nuevo.
9. **💾 Copias de Seguridad (`/admin/backups`):**
   - Generación manual de volcados SQL sin dependencias de consola, descarga protegida, restauración segura y purga desatendida.
10. **🛡️ Registro de Auditoría (`/admin/auditoria`):**
    - Visor inmutable de eventos del sistema con filtrado avanzado por tipo de acción, entidad, usuario y rango de fechas.

---

## 📟 Hardware de Mostrador: Escáner de Códigos de Barras 1D y Escáner Universal

En bibliotecas y centros educativos, el hardware de mostrador habitual es un **lector de códigos de barras 1D USB/HID** (pistola láser/CCD) que emula teclado enviando la cadena escaneada seguida de Enter. Este lector **no lee códigos QR 2D**, por lo que BookSwap v4.5 adapta toda su operativa física a esta realidad:

1. **Códigos de Reserva en Formato 1D (Code 128):**
   - Las reservas generan identificadores normalizados con formato `RES-[A-Z0-9]{10}`.
   - En el dashboard y en `/mis-reservas`, se renderizan como códigos de barras lineales Code 128 mediante la biblioteca `JsBarcode` (eliminando la dependencia de códigos QR).
   - Se incluye botón "Imprimir resguardo" con vista optimizada en CSS para ticket o papel térmico.

2. **Caja de Escaneo Universal (`POST /mostrador/escanear`):**
   - El mostrador cuenta con un input de texto único autoseleccionado al cargar la página.
   - Discrimina de forma inteligente el patrón escaneado:
     - `RES-XXXXXXXXXX`: Localiza la reserva activa y prepara la entrega inmediata.
     - `978...` / `84...` (ISBN 10/13): Muestra el libro, stock disponible y permite registrar depósito o entrega directa.
     - Correo electrónico del usuario: Carga la ficha del lector, saldo de tokens y reservas pendientes.

---

## 🎨 Reorganización UX v4.5

- **Navbar Inteligente por Rol:** Adapta opciones para `INVITADO`, `USUARIO`, `PERSONAL` y `ADMIN`. El logo central enlaza a `/dashboard` si el usuario está autenticado.
- **Dashboard Ciudadano como Home:** Al acceder como usuario, `/` redirige a `/dashboard`, estructurado en 5 bloques de alta prioridad: (1) Saldo de tokens + CTA a catálogo, (2) Reservas activas con código de barras y cancelación en modal, (3) Aviso de primeros depósitos si no tiene reservas ni saldo, (4) Últimas novedades disponibles, (5) Movimientos recientes del ledger.
- **Reserva en 1 Clic desde el Catálogo:** Botón directo en la tarjeta del libro sin necesidad de entrar a la ficha detallada, con confirmación rápida modal.
- **Hub de Mostrador Tablet-First:** Tarjetas de tareas dispuestas en el orden de uso prioritario (Entrega directa, Depósito, Entrega con reserva, Buscar usuario), respetando los permisos configurados mediante `puede()`.
- **Página Informativa `/como-funciona`:** Explicación en 3 sencillos pasos, consejos de brillo de pantalla para lectores 1D y preguntas frecuentes.

---

## 📖 Arquitectura y Características Principales

1. **Cero Dependencias en Servidor:**
   - Construido en PHP 8.2 sin dependencias de Composer, npm ni compiladores CSS.
   - Interfaz construida con Bootstrap 5.3 + Bootstrap Icons, Vanilla CSS optimizado y Vanilla JS.
   - Compatible con cualquier alojamiento compartido básico por FTP/SFTP.

2. **Catálogo Cerrado y Tres Vías de Alta:**
   - Importación masiva por lotes mediante archivos CSV con reporte de errores por línea (`/admin/importar-catalogo`).
   - Alta asistida por código ISBN conectada por cURL server-side con Google Books y Open Library (`/admin/libros/alta-isbn`).
   - Alta manual con generación automática de portadas SVG en caso de no disponer de imagen.

3. **Economía de Tokens y Ledger Inmutable:**
   - Modelo de coste uniforme parametrizable (`coste_libro = 1 token`).
   - Saldo de los lectores calculado dinámicamente mediante la suma de movimientos inmutables en la tabla `movimientos_tokens`.
   - Bloqueo pesimista `FOR UPDATE` para garantizar que ningún usuario pueda obtener saldo negativo.

4. **Operativa Presencial de Mostrador y Economía de Reservas:**
   - Panel de mostrador para el personal con escáner universal y búsqueda por código de barras (`/mostrador`).
   - Las reservas bloquean temporalmente los tokens correspondientes (`bloqueo_reserva`); al recoger el libro, la entrega se consolida sin movimiento nuevo adicional.
   - Ficha de reserva prepagada con opciones de confirmación directa y anulación (con liberación inmediata de tokens).
   - Entrega directa en mano (sin reserva previa) mediante tokens o intercambio físico de libro por libro.
   - Recepción de depósitos ciudadanos y acreditación inmediata de bonos en el ledger inmutable.

5. **Historial Ciudadano y Transparencia:**
   - Página `/mi-historial` para lectores con métricas de libros entregados/retirados, tabla paginada con filtros y exportación CSV.
   - Vista administrativa réplica en `/admin/usuarios/{id}/historial`.

6. **Copias de Seguridad Nativas en PHP:**
   - Generación de volcados SQL completos (DDL + DML) sin requerir `mysqldump`.
   - Restauración segura y gestión de retención automática (`/admin/backups`).
   - Directorio `/backups/` estrictamente protegido frente a accesos HTTP externos.

7. **Punto Físico y Visítanos:**
   - Página pública `/visitanos` con cálculo en servidor del horario en tiempo real (*"Abierto ahora / Cerrado"*), tabla semanal, mapa interactivo y botón *"Cómo llegar"*.

---

## 📁 Estructura del Proyecto

```text
librosbrolite/
├── app/
│   ├── bootstrap.php            # Inicialización global, sesión y autocarga de helpers
│   ├── router.php               # Enrutador central y controladores de la aplicación
│   ├── helpers/                 # Biblioteca modular de lógica de negocio
│   │   ├── admin.php            # Operaciones administrativas: usuarios, reservas y ledger
│   │   ├── auth.php             # Control de sesiones y RBAC
│   │   ├── auth_google.php      # Integración con Google OAuth 2.0 (cURL server-side)
│   │   ├── auditoria.php        # Registro y consulta inmutable de eventos
│   │   ├── backup.php           # Motor nativo de backup y restauración SQL
│   │   ├── catalogo.php         # Búsqueda, filtrado, paginación y caché de portadas
│   │   ├── configuracion.php    # Gestión y auditoría de parámetros dinámicos
│   │   ├── csv.php              # Importador y exportador de catálogo
│   │   ├── funciones.php        # Utilidades generales, CSRF, sanitización y fechas
│   │   ├── historial.php        # Historial de libros y tokens, métricas y exportación CSV
│   │   ├── isbn.php             # Validación de ISBN y consulta a APIs Open Library/Google
│   │   ├── ledger.php           # Contabilidad inmutable de tokens
│   │   ├── metricas.php         # Estadísticas agregadas y cuadros de mando
│   │   ├── mostrador.php        # Operativa presencial, entregas directas y escáner universal
│   │   ├── permisos.php         # Matriz de permisos, helper puede() y versión de roles
│   │   ├── reservas.php         # Ciclo de vida de reservas, caducidades y códigos Code 128
│   │   └── usuario_persistencia.php # Almacén persistente de usuarios locales
│   └── views/                   # Vistas desacopladas en PHP/HTML5
│       ├── layouts/base.php     # Plantilla estructural con navbar por rol y Bootstrap 5.3
│       ├── partials/footer.php  # Pie de página dinámico con datos del centro
│       ├── admin/               # Paneles de gestión, roles, usuarios, reservas, etc.
│       │   └── partials/sidebar.php # Sidebar agrupado del panel de administración (10 módulos)
│       ├── auth/                # Login y registro
│       ├── catalogo/            # Catálogo, fichas de libro y mis reservas
│       ├── estatico/            # Páginas estáticas informativas (/como-funciona)
│       ├── paginas/             # Home y visítanos
│       └── usuario/             # Dashboard principal e historial personal
├── assets/
│   ├── css/theme.css            # Hoja de estilos con variables de color y soporte dark mode
│   └── js/app.js                # Scripts de interacción, selector de tema y modales
├── backups/                     # Almacén de volcados SQL protegidos con .htaccess
├── bin/                         # Scripts de ejecución CLI y cron jobs
│   ├── backup.php               # Generador desatendido de copias y retención
│   ├── expirar.php              # Liberador periódico de reservas caducadas
│   └── seed_passwords.php       # Utilidad para regenerar hashes de contraseñas demo
├── config/
│   └── config.php               # Carga del entorno .env y conexión a la base de datos
├── database/
│   ├── 01_schema.sql            # Esquema relacional canónico (14 tablas InnoDB)
│   └── 02_seed.sql              # Datos iniciales canónicos y configuración v4.5
├── docs/
│   ├── DEPLOY_FTP.md            # Guía detallada para despliegue en hostings compartidos
│   ├── PERFORMANCE.md           # Análisis comparativo de rendimiento (12 rutas benchmark)
│   ├── SECURITY_AUDIT.md        # Informe de auditoría integral OWASP Top 10 y hardening
│   └── PHASE_REPORTS/           # Informes de verificación fase a fase (fase0 a fase18)
├── public/                      # DocumentRoot público del servidor web
│   ├── index.php                # Punto de entrada HTTP único
│   ├── health.php               # Endpoint de comprobación de salud técnica
│   └── .htaccess                # Reglas de reescritura hacia index.php
├── tests/                       # Suite de pruebas automatizadas
│   ├── run.php                  # Ejecutor de tests unitarios y de integración (108 tests)
│   ├── benchmark.php            # Script de benchmarking de latencia y pesos por ruta (12 rutas)
│   └── run_all.sh               # Script de verificación y arranque de Docker
├── uploads/covers/              # Caché local de portadas descargadas server-side
├── BRIEF.md                     # Documento maestro de especificaciones funcionales
├── INCREMENTO_v4.5.md           # Especificaciones detalladas del incremento v4.5
└── PROGRESS.md                  # Registro y estado del desarrollo por fases
```

---

## 📄 Despliegue en Producción

Para instalar BookSwap en un hosting compartido sin terminal SSH (cPanel, Plesk, Apache, LiteSpeed), consulte la guía completa en:
👉 [**docs/DEPLOY_FTP.md**](docs/DEPLOY_FTP.md)

---

## ⚖️ Licencia

Proyecto de código abierto desarrollado para la dinamización cultural y el intercambio libre de libros en comunidades ciudadanas y centros educativos.

