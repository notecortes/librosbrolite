# Informe de Fase 7: QA Final, Despliegue FTP y Cierre del Proyecto

## 1. Estado y Resumen
- **Fase completada:** Fase 7 — Control de Calidad Integral (QA), Verificación de Temas y Modo Oscuro, Documentación de Despliegue por FTP sin SSH (`docs/DEPLOY_FTP.md`) y Manual Completo del Proyecto (`README.md`).
- **Resultado final de la suite de tests:** **60 PASS · 0 FAIL · 0 SKIP** (`✔ SUITE VERDE`).
- **Estado del proyecto:** **COMPLETADO AL 100% (Fases 0 a 7)**.

---

## 2. Acciones y Verificaciones Realizadas

### 2.1. Análisis Sintáctico y Linter Global
Se ejecutó una comprobación sintáctica estricta sobre todos los archivos PHP del proyecto (`app/`, `bin/`, `public/`):
```bash
find app bin public -name "*.php" -exec php -l {} \;
```
- **Resultado:** 49 de 49 archivos verificados sin errores sintácticos ni advertencias de deprecación de PHP 8.2 (`No syntax errors detected`).

### 2.2. Verificación de Accesibilidad y Modo Oscuro (Dark Mode)
- Se auditó la coherencia de estilos en `assets/css/theme.css` y las plantillas `app/views/layouts/base.php` y `app/views/partials/footer.php`.
- Se validó el funcionamiento del selector de tema persistente (almacenado en `localStorage` con fallback al tema preferido por el sistema operativo mediante media query `prefers-color-scheme`).
- Los contrastes de texto y fondo en todas las vistas (Catálogo, Mostrador, Modales, Fichas de Libro, Formularios y Paneles de Control) cumplen con los criterios de legibilidad WCAG AA.
- Las tablas y elementos dinámicos emplean clases semánticas de Bootstrap 5.3 (`text-body`, `bg-body-tertiary`, `table-hover`) para una transición fluida entre temas claro y oscuro.

### 2.3. Guía de Despliegue por FTP en Hosting Compartido (`docs/DEPLOY_FTP.md`)
Se elaboró un manual exhaustivo enfocado en entornos de producción tradicionales (cPanel, Plesk, Apache, LiteSpeed, Nginx) sin necesidad de acceso a consola SSH:
- Requisitos mínimos del servidor (PHP 8.1+, extensiones `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`).
- Estrategias de aislamiento de DocumentRoot (`public/` recomendado vs. `public_html/` con `.htaccess` bloqueando carpetas sensibles).
- Matriz clara de archivos a transferir y exclusión de directorios de desarrollo (`docker/`, `tests/`, `.git/`, `.env`).
- Procedimiento de importación de base de datos (`01_schema.sql` y `02_seed.sql`) desde phpMyAdmin.
- Checklist post-despliegue (permisos 755/644, verificación de 403 en `/backups/`, cambio de clave de admin).
- Configuración de Google Cloud Console para OAuth 2.0 en producción.
- Configuración de tareas periódicas Cron (`bin/expirar.php` y `bin/backup.php`) en Linux y Windows, además del mecanismo pseudo-cron oportunista incorporado.
- Documentación técnica sobre la limitación de la cámara web (requisito de HTTPS obligatorio en navegadores móviles/externos) y la recomendación del lector de código de barras USB/Bluetooth en mostrador físico.

### 2.4. Manual Principal del Repositorio (`README.md`)
Se redactó el documento de bienvenida y operación del proyecto incluyendo:
- Instrucciones de inicio rápido con Docker (`docker compose up -d --build`).
- Tabla de credenciales de demostración (`admin@bookswap.local`, `personal@bookswap.local`, `usuario@bookswap.local`, `mixta@bookswap.local`, `google.demo@bookswap.local`).
- Resumen de la suite de 60 tests automatizados y comandos de ejecución.
- Resumen de la arquitectura sin dependencias externas en servidor.
- Árbol estructurado de carpetas y enlaces directos a la documentación.

---

## 3. Resumen Consolidado de Fases (Roadmap v4.1)

| Fase | Descripción | Tests | Estado |
|---|---|---|---|
| **Fase 0** | Andamiaje, enrutador central y home con coste uniforme | `T-SMOKE-01`, `T-SMOKE-07` | **Superada** |
| **Fase 1** | Auth, números de socio, Google OAuth y seguridad | `T-AUTH-*`, `T-GOOG-*`, `T-SOCIO-*`, `T-SEC-*`, `T-PRIV-*`, `T-SMOKE-02/05/06` | **Superada** |
| **Fase 2** | Catálogo, búsqueda y alta asistida por ISBN con APIs | `T-CAT-*`, `T-BUSQ-*`, `T-API-*`, `T-SMOKE-03/04` | **Superada** |
| **Fase 3** | Importadores CSV de catálogo y pool de números de socio | `T-CSV-*` | **Superada** |
| **Fase 4** | Reservas, límite simultáneo, códigos QR y expiración | `T-RESV-*` | **Superada** |
| **Fase 5** | Mostrador presencial, entregas, depósitos y ledger contable | `T-ENTR-*`, `T-DEPO-*`, `T-LEDGER-*`, `T-MOST-*`, `T-AUDIT-01` | **Superada** |
| **Fase 6** | Configuración dinámica auditada, métricas, backups nativos y Visítanos | `T-SMOKE-08`, `T-VISIT-*`, `T-AUDIT-02`, `T-BAK-*`, `T-MET-*` | **Superada** |
| **Fase 7** | QA final, accesibilidad/dark mode, DEPLOY_FTP.md y README.md | `T-RGRC-01..02`, Suite Completa (60 PASS / 0 FAIL) | **Superada** |
