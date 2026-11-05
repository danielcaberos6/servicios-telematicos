# Despliegue en Laravel Cloud

El entorno de producción sigue la rama `master`: cada push a esa rama inicia un deploy.

## Configuración inicial

Haz estos pasos antes del primer deploy, en este orden.

### 1. Aplicación y base de datos

- Crea la aplicación desde `danielcaberos6/servicios-telematicos`, sin activar **Run as a static website**.
- Confirma que el entorno use la rama `master`.
- Elige PHP 8.4 en **General Settings > Runtime**.
- Agrega una base **Laravel Serverless Postgres** en la misma región. MySQL no sirve: la primera migración se detiene si la base no es PostgreSQL.
- No uses el host con `-pooler`. La aplicación fija la zona horaria al abrir cada conexión y el pooler puede compartir sesiones.

### 2. Buckets

Crea tres buckets **privados** y conéctalos al entorno. El nombre de disco debe ser exactamente el de esta tabla:

| Disco | Contenido |
| --- | --- |
| `public` | Fotos de subastas y de perfil |
| `identidad` | Carnets |
| `conversaciones` | Adjuntos del chat |

`public` también es privado porque la aplicación sirve las fotos por `/imagenes/{id}` y comprueba quién puede verlas. No marques ninguno como disco predeterminado.

Sin estos buckets, los archivos quedan en el disco del entorno y se pierden en cada deploy.

### 3. Comandos

**Build commands** (sin `npm`: el proyecto no tiene `package.json`):

```
composer install --no-dev --no-interaction --optimize-autoloader
php artisan optimize
```

**Deploy command:**

```
php artisan migrate --force
```

No agregues `db:seed`: la migración ya crea las categorías y los roles. Tampoco agregues `storage:link`, `queue:restart` ni `optimize:clear`.

### 4. Planificador

Activa **Scheduler** en el App cluster. Sin él, las subastas vencidas no se cierran, no salen los recordatorios de favoritos y las entregas vencidas no pasan a revisión.

### 5. Variables de entorno

```
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
```

Tras el primer deploy, confirma que `APP_KEY` y `APP_URL` estén definidas en el entorno. Si `APP_KEY` cambia, se cierran las sesiones abiertas.

No uses `APP_ENV=local` ni `testing`: habilitan los datos y las simulaciones de demostración, cuyas contraseñas están publicadas en el README.

## Primer administrador

La base empieza vacía, sin cuentas.

1. Regístrate en `/registro` con tu correo real.
2. En la pestaña **Commands** del entorno, ejecuta:

   ```
   php artisan subastas:primer-admin tu-correo@ejemplo.com
   ```

3. Vuelve a iniciar sesión y entra a `/admin/usuarios`.

El comando se niega si ya hay un administrador activo. Los siguientes se asignan desde `/admin/usuarios`.

## Comprobaciones después del primer deploy

Estas comprobaciones solo se pueden hacer en Cloud:

- [ ] Sube una foto de cerca de 5 MB y una subasta con 5 fotos. La documentación de Cloud no indica sus límites de subida (`upload_max_filesize`, `post_max_size`), y la aplicación necesita 5 MB por archivo. Para verlos, ejecuta `php -i` en **Commands**.
- [ ] Sube un carnet y ábrelo como administrador.
- [ ] Envía un audio por el chat y reprodúcelo, si es posible también en un iPhone.
- [ ] Publica una subasta con cierre cercano y confirma que se cierre sola.
- [ ] Haz otro deploy y confirma que las fotos sigan ahí.

## Costo

Las tareas del planificador corren cada minuto y consultan la base, así que ni la aplicación ni la base llegan a dormirse. Según los ejemplos de precios de Cloud (noviembre de 2026), son unos 6 USD al mes de cómputo Flex 512 MB y unos 19 USD de Postgres a 0,25 CU, más almacenamiento. Es una estimación: revisa el consumo con el panel de Cloud.

Detener el entorno cuando no se use apaga el cómputo y permite que la base duerma. No borra datos, pero el almacenamiento se sigue cobrando y las subastas vencidas se cierran recién al volver a encenderlo.

Si el entorno pasa a tener más de una réplica, agrega `->onOneServer()` a las tareas de `routes/console.php`.

## Datos

- Un deploy solo aplica las migraciones pendientes y conserva los datos.
- No ejecutes `migrate:fresh` ni `db:wipe` en producción.
- Los datos de desarrollo no se copian a Cloud.

## Qué se ensayó

Antes del primer deploy se ensayó localmente una copia limpia del commit: instalación sin dependencias de desarrollo, `optimize`, migraciones en PostgreSQL 18 con un rol sin superusuario y almacenamiento compatible con S3 (SeaweedFS) con la configuración de discos de Cloud. Se recorrieron por HTTP el registro, el primer administrador, la publicación con foto, la aprobación y el audio del chat con rangos de bytes, además de la caída del almacenamiento. El ensayo no reemplaza a Cloudflare R2 ni a la infraestructura real de Laravel Cloud; el disco de carnets solo se probó con escritura y lectura directas.
