FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl libpq-dev libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip zip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql mbstring zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf 'upload_max_filesize=5M\npost_max_size=30M\ndate.timezone=America/La_Paz\n' > /usr/local/etc/php/conf.d/subastaya.ini

# El contenedor corre con un usuario sin privilegios. En desarrollo se usa el
# UID/GID del anfitrión: los archivos creados en el proyecto montado y en el
# volumen vendor pertenecen al usuario, no a root.
ARG UID=1000
ARG GID=1000
ENV HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
RUN chown ${UID}:${GID} /app
USER ${UID}:${GID}

# Dependencias primero: si solo cambia el código, Docker reutiliza esta capa.
COPY --chown=${UID}:${GID} composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY --chown=${UID}:${GID} . .

# Las carpetas de archivos subidos existen en la imagen con el usuario sin
# privilegios como dueño: así los volúmenes con nombre de Compose heredan ese
# dueño en lugar de crearse como root.
RUN mkdir -p storage/app/public \
    storage/app/private \
    storage/app/identidad \
    storage/app/conversaciones \
    storage/framework/views \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && sed -i 's/\r$//' scripts/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["sh", "/app/scripts/docker-entrypoint.sh"]
