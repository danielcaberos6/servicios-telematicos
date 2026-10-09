#!/bin/sh
set -eu
test -f .env || cp .env.example .env
composer install --no-interaction --prefer-dist
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi
php artisan migrate --force
php artisan db:seed --force
# Servidor PHP directo: `artisan serve` no pasa DB_HOST/DB_PORT de Compose a su
# proceso hijo, y la web terminaría usando los valores de .env para fuera de Docker.
cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
