# SubastaYA

Plataforma de subastas en línea con Laravel 13 (PHP 8.4) y PostgreSQL 16, ejecutada en Docker.

## Requisitos

- Docker con Docker Compose

## Ejecutar

```sh
cp .env.example .env
docker compose up -d --build
```

Abre **http://localhost:8000**.

Al arrancar, el contenedor instala dependencias, aplica las migraciones, carga las categorías y crea la base de pruebas `subastaya_test`. El servicio `scheduler` finaliza cada minuto las subastas vencidas.

## Datos de demostración

```sh
docker compose exec laravel php artisan db:seed --class=ActividadDemoSeeder
```

Crea publicaciones, ofertas y valoraciones de ejemplo. Usuario: `demo@subastaya.test`, contraseña `SubastaYA2026!`. Solo funciona con `APP_ENV=local` o `testing`.

## Pruebas

```sh
docker compose exec laravel php artisan test
```

Las pruebas usan la base `subastaya_test` (se crea al arrancar; si falta, ejecuta `php artisan subastas:crear-bd-pruebas`).

## Base de datos

La lógica de negocio vive en PostgreSQL: el esquema está en `database/sql/Script.sql` y las funciones y triggers en `database/sql/Funciones.sql`. Tras modificar `Funciones.sql`, crea una migración que lo reinstale o ejecuta `php artisan subastas:instalar-funciones`.

## Detener

```sh
docker compose down
```
