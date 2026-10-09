FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git curl libpq-dev libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip zip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql mbstring zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN mkdir -p storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN printf 'upload_max_filesize=5M\npost_max_size=30M\ndate.timezone=America/La_Paz\n' > /usr/local/etc/php/conf.d/subastaya.ini
RUN sed -i 's/\r$//' /app/scripts/docker-entrypoint.sh && chmod +x /app/scripts/docker-entrypoint.sh
EXPOSE 8000

ENTRYPOINT ["sh", "/app/scripts/docker-entrypoint.sh"]
