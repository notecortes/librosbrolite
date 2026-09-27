FROM php:8.2-apache

# Extensiones y utilidades. El paquete apt "curl" instala el CLI del healthcheck.
# La EXTENSIÓN PHP curl ya viene en la imagen: NO recompilarla (fallaría).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN { \
      echo "display_errors = On"; \
      echo "error_reporting = E_ALL"; \
      echo "upload_max_filesize = 20M"; \
      echo "post_max_size = 25M"; \
      echo "memory_limit = 256M"; \
      echo "max_execution_time = 120"; \
    } > /usr/local/etc/php/conf.d/bookswap.ini

# PERMISOS DE DESARROLLO: www-data pasa a usar el UID/GID del usuario del host
# (necesario con bind mounts, sobre todo en discos externos montados).
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

COPY docker/entrypoint.sh /usr/local/bin/bookswap-entrypoint
RUN chmod +x /usr/local/bin/bookswap-entrypoint

WORKDIR /var/www/html
CMD ["bookswap-entrypoint"]