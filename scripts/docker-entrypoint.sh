#!/bin/sh
set -eu
export GIT_CONFIG_GLOBAL="${GIT_CONFIG_GLOBAL:-/tmp/subastaya.gitconfig}"
git config --global --add safe.directory /app
test -f .env || cp .env.example .env
composer install --no-interaction --prefer-dist
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=CuentasDemoSeeder --force
php artisan subastas:crear-bd-pruebas
cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
