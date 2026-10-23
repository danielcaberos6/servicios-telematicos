# SubastaYA

Plataforma de subastas en línea con Laravel 13 (PHP 8.4) y PostgreSQL 16, ejecutada en Docker.

## Requisitos

- Docker con Docker Compose

## Ejecutar

```sh
docker compose up -d
```

Abre **http://localhost:8000**.

Al arrancar, el contenedor instala dependencias, aplica las migraciones, carga las categorías y crea la base de pruebas `subastaya_test`. En `APP_ENV=local` o `testing`, también crea automáticamente las cuentas de demostración que falten:

| Correo | Rol | Contraseña inicial |
|---|---|---|
| `admin@subastaya.test` | Administrador | `Admin2026!` |
| `demo@subastaya.test` | Usuario | `SubastaYA2026!` |
| `vendedor@subastaya.test` | Usuario | `SubastaYA2026!` |
| `participante1@subastaya.test` a `participante5@subastaya.test` | Usuario | `SubastaYA2026!` |
| `comunidad1@subastaya.test` a `comunidad16@subastaya.test` | Usuario | `SubastaYA2026!` |

En una base nueva se cargan automáticamente **24 cuentas, 49 subastas con fotos, 38 pujas y 45 valoraciones** mediante `subastas:cargar-demo`. Incluye subastas activas, finalizadas, canceladas, una solicitud en revisión y otra rechazada. No hace falta ejecutar comandos de carga adicionales: si falta `.env`, el arranque lo crea desde `.env.example`. Los reinicios conservan las contraseñas, el estado y los roles de las cuentas existentes y no duplican los ejemplos ya cargados ni renuevan sus plazos. En otros entornos no se cargan ejemplos. La carga sucede cuando arranca el contenedor; `docker compose up -d` no la repite si el contenedor ya está funcionando. Si cambias el Dockerfile o las dependencias de la imagen, usa `docker compose up -d --build` para reconstruirla.

El servicio `scheduler` finaliza cada minuto las subastas vencidas.

Los visitantes ven Inicio, Subastas (el catálogo con búsqueda y filtros) y **Cómo funciona** (`/como-funciona`), con los pasos para comprar y publicar, consejos de seguridad y preguntas frecuentes. Perfil, Mis subastas y Notificaciones aparecen al iniciar sesión. El botón **Inicia sesión para ofertar** conserva la subasta de origen y vuelve a su detalle después del acceso, sin registrar una oferta automáticamente.

## Datos de demostración

Todas las subastas de demostración incluyen al menos una foto, también las pendientes, rechazadas e históricas. Volver a ejecutar sus seeders completa las fotos faltantes sin reemplazar imágenes existentes ni cambiar los estados de las subastas. Las nuevas fotografías son ilustrativas; sus autores, fuentes y licencias están en [Créditos de fotos de ejemplo](public/imagenes/ejemplos/CREDITOS.md).

```sh
docker compose exec laravel php artisan db:seed --class=ActividadDemoSeeder
```

Crea publicaciones, ofertas y valoraciones de ejemplo. Usuario: `demo@subastaya.test`, contraseña `SubastaYA2026!`. Solo funciona con `APP_ENV=local` o `testing`.

## Administración: HU-08 y HU-09

Las cuentas y las subastas básicas ya se crean al arrancar Docker en local. Para volver a cargar los ejemplos que falten manualmente:

```sh
docker compose exec laravel php artisan db:seed --class=DemoSeeder
```

Administrador: `admin@subastaya.test`, contraseña `Admin2026!`. Los seeders conservan las contraseñas de cuentas que ya existen.

El administrador entra directamente a **Usuarios** y su menú contiene Usuarios, Categorías, Identidad, Notificaciones y Reportes. Conserva su perfil para actualizar sus datos y contraseña, y puede consultar subastas para revisarlas. Inicio, Buscar, Mis subastas, publicar, editar o eliminar publicaciones, ofertar y solicitar verificación personal están reservados a usuarios sin rol administrador. Las páginas reservadas lo devuelven a Usuarios; los envíos y las peticiones JSON se rechazan con 403, incluso si la cuenta tiene ambos roles.

- **Usuarios** (`/admin/usuarios`): búsqueda por nombre o correo, filtro de estado, activación y desactivación, cambios de rol e historial de decisiones. No se puede desactivar la propia cuenta administrativa ni dejar el sistema sin un administrador activo. Una cuenta desactivada pierde el acceso en su siguiente petición y conserva sus registros. Sus subastas dejan de aparecer en el inicio y en Buscar y no admiten nuevas ofertas hasta que se reactive; quienes ya ofertaron conservan el detalle y el cierre sigue su curso.
- **Categorías** (`/admin/categorias`): creación, edición y disponibilidad. Los nombres son únicos sin distinguir mayúsculas ni espacios en los extremos. Deshabilitar una categoría impide nuevas publicaciones y futuras aprobaciones en ella; las subastas existentes conservan su asociación.
- **Identidad** (`/admin/identidad`): bandeja de solicitudes y revisión manual del carnet, nombres, apellidos y foto de perfil, si existe. La aprobación y el rechazo registran revisor y fecha; el rechazo exige un motivo de 1 a 1000 caracteres. Las decisiones simultáneas no duplican resultados ni notificaciones.

Desde **Mi perfil**, un usuario puede completar sus nombres y apellidos y, opcionalmente, enviar un carnet JPG o PNG de hasta 5 MB. Para solicitar la verificación debe haber guardado una foto de perfil donde se vea claramente su rostro; el administrador la compara con el carnet y los nombres. El registro y el uso de la cuenta no requieren foto ni carnet. Cambiar los nombres, apellidos o la foto invalida la solicitud pendiente o la verificación vigente; se conserva el historial y se permite otra solicitud. El requisito de foto se aplica a nuevas solicitudes y conserva las decisiones anteriores. El distintivo vigente también aparece junto al vendedor en el detalle público de sus subastas.

Los carnets se guardan en `storage/app/identidad`, fuera del almacenamiento público. Solo el titular y administradores activos acceden mediante rutas autenticadas; las respuestas no permiten caché. Esta carpeta debe incluirse en las copias privadas de respaldo, sin enlaces ni publicación web.

Al elegir el carnet aparece una vista previa local, antes de enviarlo. **Ampliar carnet** abre un visor con zoom, giro de 90° y ajuste a pantalla; al ampliar se puede recorrer la imagen con barras de desplazamiento, teclado, panel táctil o el dedo. El titular y los administradores disponen del mismo visor en el detalle privado de la solicitud. El giro solo cambia la visualización, no el archivo enviado.

## Revisión de publicaciones

Una solicitud rechazada permite **Corregir y reenviar** desde Mis subastas o su detalle. El formulario muestra el motivo y exige un cierre futuro. **Guardar y reenviar a revisión** guarda datos e imágenes en una transacción, devuelve la solicitud a En revisión y avisa al vendedor y a los administradores activos. El plazo de referencia se calcula desde este nuevo envío y comienza íntegro al aprobarse. El historial conserva las decisiones previas, sus motivos, responsables y fechas, visibles solo para el propietario y los administradores. Las decisiones anteriores sin responsable conocido también se conservan sin inventar datos. Un formulario de reenvío que ya fue procesado exige recargar antes de continuar.

Las nuevas aprobaciones y rechazos registran el ID del administrador autenticado y la fecha de decisión en PostgreSQL, dentro de la misma operación que cambia el estado y notifica al vendedor. El detalle administrativo muestra el responsable y la hora de Bolivia. Las decisiones anteriores conservan estos campos vacíos, sin atribuirles un responsable o una fecha que no se conocen.

Cada subasta nueva se registra **En revisión**, asociada a su vendedor, y no se publica automáticamente. Las subastas que existían antes de esta función se conservaron como **Aceptadas**.

Las nuevas solicitudes requieren entre **1 y 5 fotos del artículo** (JPG, PNG o WebP, hasta 5 MB por foto). Al editar o reenviar, el total de fotos conservadas y nuevas también debe quedar entre 1 y 5: no se permite eliminar la última sin reemplazarla. Las publicaciones anteriores se conservan; si se edita una que no tiene fotos, deberá añadirse al menos una. La foto del perfil sigue siendo independiente.

- **Privacidad**: una solicitud En revisión o Rechazada no aparece en el inicio, en el catálogo ni en los reportes administrativos y no admite pujas. Su detalle y sus fotos responden 404 a terceros; solo los ven el propietario y los administradores. La base de datos también rechaza las pujas sobre subastas no aceptadas, y las solicitudes no publicadas no se finalizan.
- **Edición**: editar los datos o las fotos de una subasta Aceptada o Rechazada la devuelve a En revisión (se borra el motivo de rechazo) y se vuelve a avisar al vendedor y a los administradores. Ningún cambio se publica sin una nueva decisión.
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
