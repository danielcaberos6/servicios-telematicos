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

## Administración: HU-08 y HU-09

Para cargar la cuenta administrativa y las subastas básicas de demostración:

```sh
docker compose exec laravel php artisan db:seed --class=DemoSeeder
```

Administrador: `admin@subastaya.test`, contraseña `Admin2026!`. Los seeders conservan las contraseñas de cuentas que ya existen.

- **Usuarios** (`/admin/usuarios`): búsqueda por nombre o correo, filtro de estado, activación y desactivación, cambios de rol e historial de decisiones. No se puede desactivar la propia cuenta administrativa ni dejar el sistema sin un administrador activo. Una cuenta desactivada pierde el acceso en su siguiente petición y conserva sus registros.
- **Categorías** (`/admin/categorias`): creación, edición y disponibilidad. Los nombres son únicos sin distinguir mayúsculas ni espacios en los extremos. Deshabilitar una categoría impide nuevas publicaciones y futuras aprobaciones en ella; las subastas existentes conservan su asociación.
- **Identidad** (`/admin/identidad`): bandeja de solicitudes y revisión manual del carnet, nombres, apellidos y foto de perfil, si existe. La aprobación y el rechazo registran revisor y fecha; el rechazo exige un motivo de 1 a 1000 caracteres. Las decisiones simultáneas no duplican resultados ni notificaciones.

Desde **Mi perfil**, un usuario puede completar sus nombres y apellidos y, opcionalmente, enviar un carnet JPG o PNG de hasta 5 MB. El registro y el uso de la cuenta no requieren carnet. Cambiar los nombres, apellidos o la foto invalida la solicitud pendiente o la verificación vigente; se conserva el historial y se permite otra solicitud. El distintivo vigente también aparece junto al vendedor en el detalle público de sus subastas.

Los carnets se guardan en `storage/app/identidad`, fuera del almacenamiento público. Solo el titular y administradores activos acceden mediante rutas autenticadas; las respuestas no permiten caché. Esta carpeta debe incluirse en las copias privadas de respaldo, sin enlaces ni publicación web.

## Pruebas

```sh
docker compose exec laravel php artisan test
```

Las pruebas fijan explícitamente la conexión a `subastaya_test` y comprueban su nombre antes de cualquier reinicio de tablas. Así, la variable `DB_DATABASE` de Docker no puede hacer que las pruebas reinicien la base local. La base de pruebas se crea al arrancar; si falta, ejecuta `docker compose exec laravel php artisan subastas:crear-bd-pruebas`.

## Base de datos

La lógica de negocio vive en PostgreSQL: el esquema inicial está en `database/sql/Script.sql` y las funciones generales en `database/sql/Funciones.sql`. Las ampliaciones de gestión se organizan en `GestionUsuarios.sql`, `RolesUsuarios.sql`, `GestionCategorias.sql`, `NombresUsuarios.sql`, `IdentidadUsuarios.sql` y `RevisionIdentidad.sql`, instaladas por sus migraciones. Para actualizar un entorno existente, ejecuta `docker compose exec laravel php artisan migrate`.

Tras modificar funciones o triggers, crea una migración que instale el cambio. El comando `php artisan subastas:instalar-funciones` solo reinstala `Funciones.sql`; los archivos que contienen ampliaciones de esquema se ejecutan mediante migraciones, una sola vez.

## Detener

```sh
docker compose down
```
