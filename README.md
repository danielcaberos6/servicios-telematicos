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

## Revisión de publicaciones

Cada subasta nueva se registra **En revisión**, asociada a su vendedor, y no se publica automáticamente. Las subastas que existían antes de esta función se conservaron como **Aceptadas**.

El cierre indicado al enviar la solicitud define un plazo de referencia respecto de su fecha de inicio. Al aprobarla, PostgreSQL fija el inicio en el instante de aprobación y desplaza el cierre conservando íntegramente ese plazo. La espera administrativa no consume tiempo de exposición, incluso si el cierre de referencia ya pasó. Rechazar no cambia las fechas y una segunda decisión no vuelve a desplazar el plazo. Las publicaciones ya aceptadas conservan sus fechas.

- **Privacidad**: una solicitud En revisión o Rechazada no aparece en el inicio ni en el catálogo y no admite pujas. Su detalle y sus fotos responden 404 a terceros; solo los ven el propietario y los administradores. La base de datos también rechaza las pujas sobre subastas no aceptadas, y las solicitudes no publicadas no se finalizan.
- **Mis subastas**: cada publicación muestra En revisión, Aceptada o Rechazada; si fue rechazada, también el motivo (de 1 a 1000 caracteres, exigido por la base de datos).
- **Avisos**: al registrar la solicitud, el vendedor recibe "Solicitud en revisión" y cada administrador activo recibe "Nueva solicitud de publicación", ambos con enlace a la solicitud. Solo el destinatario puede consultar, marcar o eliminar su aviso. Los administradores tienen **Notificaciones** en su menú.

- **Bandeja y resolución administrativa (HU-12)**: desde `/admin/subastas` los administradores activos consultan la bandeja de solicitudes con filtros por estado (`En revisión`, `Aceptada`, `Rechazada`, `todos`) y búsqueda por texto. En el detalle (`/admin/subastas/{id}`), el administrador puede inspeccionar fotos, vendedor y mapa, y tomar una decisión: **Aceptar** (publica la subasta en el catálogo y notifica al vendedor) o **Rechazar** (exige un motivo obligatorio de 1 a 1000 caracteres y notifica al vendedor con el motivo). La resolución está protegida contra concurrencia con bloqueos en PostgreSQL. Los datos de demostración incluyen solicitudes de ejemplo para evaluar este flujo.

## Mapas y búsqueda por cercanía

Los mapas usan Leaflet 1.9.4 (unpkg, con verificación de integridad) y las teselas de OpenStreetMap, por lo que el navegador necesita acceso a internet para mostrarlos.

- **Publicar**: además del punto de encuentro escrito, que es obligatorio, el vendedor puede marcar el lugar en el mapa con un clic, arrastrando el marcador o con **Usar mi ubicación actual**. Las coordenadas son opcionales, pero deben enviarse en pares. El servidor rechaza los pares incompletos y los valores fuera de rango (latitud entre -90 y 90, longitud entre -180 y 180); la base de datos aplica la misma restricción.
- **Detalle**: muestra el marcador con zoom y la atribución de OpenStreetMap. Si la publicación no tiene coordenadas o las teselas no cargan, se informa que el mapa no está disponible y se mantiene la ubicación escrita.
- **Catálogo** (`/buscar`): se elige un centro en el mapa o con la ubicación actual y se indica un radio en kilómetros (mayor que 0 y hasta 500). Solo se muestran las subastas activas y vigentes cuyo punto de encuentro está a esa distancia o menos. Las publicaciones sin coordenadas quedan fuera mientras el radio esté activo y vuelven a aparecer al quitarlo. El filtro se combina con el texto, la categoría y los demás filtros. Al paginar se conservan el centro, el radio y el orden, que incluye la opción **Más cercanas**. El mapa dibuja el círculo y los resultados de la página actual. Los parámetros son `lat`, `lng` y `radio`; si faltan o son inválidos, el catálogo muestra el error y descarta solo ese filtro.

La distancia se calcula en PostgreSQL con la fórmula de haversine sobre una esfera de 6371 km (`distancia_km` en `database/sql/BusquedaGeografica.sql`). Los datos de demostración incluyen coordenadas aproximadas de cada plaza, excepto la colección de libros, que representa una publicación anterior sin mapa.

## Pruebas

```sh
docker compose exec laravel php artisan test
```

Las pruebas fijan explícitamente la conexión a `subastaya_test` y comprueban su nombre antes de cualquier reinicio de tablas. Así, la variable `DB_DATABASE` de Docker no puede hacer que las pruebas reinicien la base local. La base de pruebas se crea al arrancar; si falta, ejecuta `docker compose exec laravel php artisan subastas:crear-bd-pruebas`.

## Base de datos

La lógica de negocio vive en PostgreSQL: el esquema inicial está en `database/sql/Script.sql` y las funciones generales en `database/sql/Funciones.sql`. Las ampliaciones de gestión se organizan en `GestionUsuarios.sql`, `RolesUsuarios.sql`, `GestionCategorias.sql`, `NombresUsuarios.sql`, `IdentidadUsuarios.sql`, `RevisionIdentidad.sql`, `BusquedaGeografica.sql`, `RevisionSubastas.sql`, `PublicacionSubastas.sql` y `RevisionSubastasAdmin.sql`, instaladas por sus migraciones. Para actualizar un entorno existente, ejecuta `docker compose exec laravel php artisan migrate`.

Tras modificar funciones o triggers, crea una migración que instale el cambio. El comando `php artisan subastas:instalar-funciones` reinstala, en este orden, `Funciones.sql`, `BusquedaGeografica.sql` y `PublicacionSubastas.sql`. Los dos últimos solo contienen funciones; `PublicacionSubastas.sql` redefine el catálogo (revisión y filtro por radio), la validación de pujas, la finalización y el aviso de nuevas solicitudes, por lo que debe ejecutarse al final. Los archivos que contienen ampliaciones de esquema se ejecutan mediante migraciones, una sola vez.

## Detener

```sh
docker compose down
```
