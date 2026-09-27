#!/bin/sh
# Prepara los directorios que necesitan escritura (con bind mount desde el host,
# www-data no puede escribir por defecto: esto evita fallos en backups y uploads).
mkdir -p /var/www/html/backups /var/www/html/uploads /var/www/html/uploads/portadas
chown -R www-data:www-data /var/www/html/backups /var/www/html/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/backups /var/www/html/uploads 2>/dev/null || true

# Asegurar que public/ disponga de acceso a assets y uploads
[ ! -e /var/www/html/public/assets ] && ln -s ../assets /var/www/html/public/assets 2>/dev/null || true
[ ! -e /var/www/html/public/uploads ] && ln -s ../uploads /var/www/html/public/uploads 2>/dev/null || true

exec apache2-foreground