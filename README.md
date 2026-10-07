# SubastaYA

Sistema de subastas en **Laravel 13 + Blade + PostgreSQL**. Trabajo realizado en la rama `rodrigo`. El CSS y el JavaScript se sirven desde `public/`; estas pantallas no requieren Vite ni Node para ejecutarse.

## Abrir en este equipo

**http://localhost:8000**

Se preparó PHP 8.4 portátil en `.runtime/php` y una instancia independiente de PostgreSQL en `.runtime/pgdata`, escuchando solo en `127.0.0.1:5433`. Se utilizan los binarios de PostgreSQL ya instalados en este equipo. La base existente del puerto 5432 y XAMPP no se modifican. `.runtime`, `.env`, las dependencias y las fotos subidas están excluidos de Git.

Si reinicias el equipo, desde la raíz del proyecto:

```powershell
.\scripts\start-local.ps1
```

Para detener la aplicación y su planificador sin borrar datos:

```powershell
.\scripts\stop-local.ps1
# Agrega -StopDatabase para detener también la instancia PostgreSQL del proyecto.
```

Cuenta de demostración local:

- Correo: `demo@subastaya.test`
- Contraseña: `SubastaYA2026!`
- Otra cuenta para probar ofertas entre usuarios: `vendedor@subastaya.test`, misma contraseña.

También puedes registrar una cuenta nueva desde `/registro`. Los ocho artículos e ilustraciones del seeder son ejemplos; no son publicaciones reales. El seeder de demostración solo se permite en entornos `local` y `testing`, es opcional y no modifica publicaciones existentes al repetirse.

## Funciones implementadas

- Inicio público con navegación basada en el prototipo, categorías, próximas subastas a cerrar y explicación de uso. Crear subasta solo se muestra a usuarios autenticados.
- Registro, inicio y cierre de sesión, recordatorio de sesión, contraseñas cifradas mediante hash y límite de intentos de acceso.
- Perfil editable: nombre, correo, biografía, ciudad, teléfono y foto. Cambiar correo o contraseña requiere la contraseña actual. Visualización de reseñas existentes.
- Catálogo público con búsqueda por título/descripción y filtros combinables: categoría, estado del artículo, rango de fechas de publicación, rango del monto inicial y ubicación. Ordenación y paginación conservan los filtros. Solo incluye subastas activas cuyo periodo ya comenzó y aún no terminó.
- Crear, consultar, editar y eliminar subastas propias. Título, descripción, categoría, condición, monto, fecha de cierre, punto de encuentro y hasta cinco imágenes. La fecha de inicio se fija en el servidor al publicar; fechas y horas se interpretan en Bolivia.
- Las imágenes de subastas y perfiles se guardan en `storage/app/public/subastas/{id}` y `storage/app/public/perfiles/{id}`. Se admiten JPG, PNG y WebP de hasta 5 MB. Se sirven por `/imagenes/{id}`, sin depender de enlaces simbólicos en Windows. Las ilustraciones SVG incluidas en los ejemplos son recursos propios del seeder; el formulario no acepta SVG.
- Ofertas autenticadas, validación de monto, bloqueo de autopujas y transacciones con bloqueo de fila. Las subastas con ofertas ya no se pueden editar ni eliminar.
- Notificaciones de bienvenida, publicación, nuevas ofertas, oferta superada y cierre. Búsqueda, filtro de no leídas, marcado y eliminación restringidos al destinatario.
- Cierre automático cada minuto y notificación al ganador. El correo y teléfono de contacto solo se revelan al vendedor y al ganador en el detalle de la subasta finalizada.

La ubicación sigue siendo texto: **ciudad, zona y lugar público de encuentro**. No se agregó un mapa ni se requieren claves de servicios externos. Entrega y pago se coordinan entre las personas; no hay pasarela de pago.

Fuera de esta entrega: recuperación de contraseña por correo, verificación de correo, panel administrativo, publicación de nuevas reseñas y chat. La tabla de reseñas y roles se conserva para esas ampliaciones.

## Base de datos y Script.sql

`Script.sql` es la fuente del esquema PostgreSQL. La migración inicial lo ejecuta conservando las tablas originales: `categoria`, `rol`, `usuario`, `usuario_rol`, `subasta`, `puja`, `imagen`, `resenas` y `notificacion`. Laravel agrega sus tablas de sesiones, caché y trabajos.

Las operaciones CRUD se realizan con Eloquent, validación y transacciones. No es necesario duplicar cada operación CRUD en un procedimiento almacenado. Se añadieron funciones/triggers donde aportan integridad:

- `validar_puja`: bloquea la subasta y valida fechas, propietario y monto.
- `proteger_subasta`: impide modificar condiciones o eliminar subastas con pujas.
- `notificar_publicacion` y `notificar_puja`: generan notificaciones dentro de la misma transacción.
- `finalizar_subastas`: cierra subastas vencidas, obtiene al ganador y notifica una sola vez.

Se agregaron índices, unicidad del correo sin distinguir mayúsculas, token de sesión persistente, fecha de puja, validación de estados y una única imagen de perfil por usuario. `ubicacion` permanece como `text`.

El script es de instalación sobre una base vacía. **Usa `php artisan migrate`, sin importar Script.sql manualmente antes**, para incluir también las tablas internas y el historial de migraciones. Esta migración no es una conversión automática de una base existente con datos. Las siguientes ampliaciones deben ir en nuevas migraciones.

También se actualizó `E:\Downloads\Script.sql`. Se conservó una copia previa en `.runtime/Script.original.sql`.

## Docker (Laravel y BD separados)

Requiere Docker con Compose. La configuración incluye tres servicios: `laravel`, `postgres` (PostgreSQL 16) y `scheduler`. Conserva los datos en un volumen independiente y limita los puertos al equipo local.

```powershell
# Solo si todavía no existe .env:
Copy-Item .env.example .env
docker compose up -d --build
# Opcional: cargar las cuentas y publicaciones de demostración:
docker compose exec laravel php artisan db:seed --class=DemoSeeder
```

Abre **http://localhost:8000**. Dentro de Docker, Compose configura `DB_HOST=postgres` y `DB_PORT=5432`. PostgreSQL se expone al equipo en `localhost:5434` para evitar conflictos con las instancias locales. La aplicación genera la clave si falta, ejecuta migraciones y carga categorías y roles al arrancar.

Detén primero el servidor portátil si está ocupando el puerto 8000, o establece `APP_PORT=8001` en `.env`. Para detener los contenedores conservando la información:

```powershell
docker compose down
```

Docker no estaba disponible durante esta implementación: la aplicación y los triggers se verificaron con PostgreSQL local; no se ejecutaron los contenedores.

## Instalar sin Docker en otro equipo

Necesitas PHP 8.4 (compatible con el lockfile), Composer 2 y PostgreSQL. Extensiones: PDO PostgreSQL, mbstring, openssl, fileinfo, DOM/XML, cURL y GD (para las pruebas de imágenes). Crea una base vacía y configura sus credenciales en `.env`.

```sh
composer install
# Copia .env.example a .env y configura tu PostgreSQL.
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder  # opcional, solo en local
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal ejecuta `php artisan schedule:work`. También puedes ejecutar el cierre manual con `php artisan subastas:finalizar`. Aunque el planificador esté detenido, las consultas excluyen las subastas vencidas y ya no admiten ofertas.

## Pruebas

Las pruebas utilizan exclusivamente la base **`subastaya_test`**, con el mismo host/usuario de `.env`. Crea esa base de pruebas antes de ejecutarlas. `RefreshDatabase` reconstruye su esquema; no cambies ese nombre por una base con datos reales.

```powershell
.\.runtime\php\php.exe artisan test
.\.runtime\php\php.exe vendor/bin/pint --test
```

En otra instalación utiliza `php` en lugar de la ruta portátil. En Docker crea primero la base con `docker compose exec postgres createdb -U subastaya subastaya_test` y ejecuta `docker compose exec laravel php artisan test`.

Las pruebas cubren autenticación, restricciones de invitados y propietarios, fechas/montos, filtros combinados, paginación, carga y eliminación de imágenes, archivos no válidos, privacidad de notificaciones/contactos, ofertas, triggers, cierre idempotente, perfil y escape HTML.

Referencias de implementación: [autenticación Laravel](https://laravel.com/docs/13.x/authentication), [validación](https://laravel.com/docs/13.x/validation) y [archivos](https://laravel.com/docs/13.x/filesystem).
