#!/bin/sh
set -eu

# Variables opcionales del contenedor:
#   COMPOSER_INSTALL=true  instala dependencias al arrancar (desarrollo, con el
#                          proyecto montado y el volumen vendor).
#   CREATE_TEST_DB=true    crea la base subastaya_test que usan las pruebas.
#   APP_KEY                clave fija; si falta, se genera una en .env.
#   APP_ENV=production     guarda en caché configuración, rutas y vistas.

test -f .env || cp .env.example .env

if [ "${COMPOSER_INSTALL:-false}" = "true" ]; then
    export GIT_CONFIG_GLOBAL="${GIT_CONFIG_GLOBAL:-/tmp/subastaya.gitconfig}"
    git config --global --add safe.directory /app
    composer install --no-interaction --prefer-dist
fi

if [ -z "${APP_KEY:-}" ]; then
    # Una APP_KEY vacía en el entorno taparía la clave guardada en .env.
    unset APP_KEY
    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "Aviso: APP_KEY no está definida; se genera una clave nueva en .env." >&2
        php artisan key:generate --force
    fi
fi

php artisan migrate --force
php artisan db:seed --force
# Solo carga ejemplos con APP_ENV=local o testing; en otros entornos no hace nada.
php artisan subastas:cargar-demo

if [ "${CREATE_TEST_DB:-false}" = "true" ]; then
    php artisan subastas:crear-bd-pruebas
fi

if [ "${APP_ENV:-}" = "production" ]; then
    php artisan optimize
fi

cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
