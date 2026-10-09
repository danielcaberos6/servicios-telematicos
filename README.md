# SubastaYA

Plataforma de subastas en línea hecha con Laravel 13, Blade y PostgreSQL.

## Requisitos

- Docker con Docker Compose

O bien, para instalar sin Docker:

- PHP 8.4 con las extensiones `pdo_pgsql`, `mbstring` y `gd`
- Composer 2
- PostgreSQL 16

## Instalación con Docker

```sh
cp .env.example .env
docker compose up -d --build
```

Abre **http://localhost:8000**.

Al arrancar, el contenedor instala las dependencias, genera `APP_KEY`, ejecuta las migraciones y carga las categorías y los roles.

En Linux, pon en `.env` tu `UID` y `GID` (`id -u` y `id -g`) para que los archivos creados por Docker te pertenezcan.

### Datos de demostración (opcional)

```sh
docker compose exec laravel php artisan db:seed --class=ActividadDemoSeeder
```

| Correo | Contraseña |
|---|---|
| `demo@subastaya.test` | `SubastaYA2026!` |
| `vendedor@subastaya.test` | `SubastaYA2026!` |

### Detener

```sh
docker compose down
```

Los datos se conservan en el volumen `pgdata`.

## Instalación sin Docker

1. Crea una base de datos vacía en PostgreSQL.
2. Copia `.env.example` a `.env` y ajusta las variables `DB_*`.
3. Ejecuta:

```sh
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

En otra terminal, inicia el planificador, que cierra las subastas vencidas:

```sh
php artisan schedule:work
```

## Pruebas

Las pruebas usan una base separada llamada `subastaya_test`. Créala una sola vez:

```sh
docker compose exec postgres createdb -U subastaya subastaya_test
docker compose exec laravel php artisan test
```

Sin Docker, crea la base `subastaya_test` y ejecuta `php artisan test`.

## Comandos útiles

| Comando | Para qué sirve |
|---|---|
| `php artisan subastas:finalizar` | Cierra manualmente las subastas vencidas |
| `php artisan subastas:instalar-funciones` | Reinstala las funciones y triggers de `database/sql/Funciones.sql` |
| `vendor/bin/pint` | Formatea el código |

Con Docker, antepón `docker compose exec laravel` a cada comando.

## Puertos

| Servicio | Dirección |
|---|---|
| Aplicación | http://localhost:8000 (`APP_PORT`) |
| PostgreSQL | localhost:5434 (`POSTGRES_PORT`) |
