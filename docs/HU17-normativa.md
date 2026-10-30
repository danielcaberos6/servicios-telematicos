# HU17: Normativa y políticas

## Alcance implementado

- Página pública `/normas-y-politicas`, con índice por secciones y enlace permanente en el pie de página.
- Reglas de cuenta, publicaciones veraces, artículos prohibidos, pujas, entrega, desacuerdos y tratamiento de datos.
- Aceptación explícita con casilla inicialmente desmarcada; usuario autenticado, versión y fecha del servidor.
- Una aceptación por usuario y versión, sin sobrescribir su fecha al reenviar. No se aceptan normas en nombre de las cuentas existentes.
- Protección para crear o editar publicaciones y registrar pujas. El catálogo, favoritos e historial siguen accesibles según los permisos anteriores.
- Al aceptar se vuelve a una ruta local permitida. La publicación o puja debe confirmarse después; nunca se repite automáticamente una petición anterior.
- Los administradores pueden leer las normas, pero no aceptarlas como participantes. Las cuentas desactivadas tampoco pueden hacerlo.

## Reglas económicas pendientes

Por decisión del responsable del proyecto, no se implementan billetera, recargas, reservas, liquidaciones ni comisión del 5 %. La página describe el alcance actual y no pide aceptar condiciones económicas todavía no aprobadas. Tampoco se presenta como disponible el formulario de incidencias o el seguimiento de entrega de HU19.

HU17 queda implementada en su alcance no económico. El criterio 3 y la parte económica del criterio 4 del Sprint Planning continúan pendientes, al igual que la aceptación antes de recargar cuando exista esa función.

## Persistencia y protección

La migración `2026_10_29_000005_agregar_aceptacion_normas.php` añade `aceptacion_normas`, sin modificar usuarios, fotografías ni ofertas existentes. Su clave primaria es `(id_usuario, version)` y la fecha procede de PostgreSQL.

`database/sql/Normativa.sql` define la versión vigente, la aceptación idempotente y la exigencia de aceptación. `crear_subasta`, `actualizar_subasta` y `registrar_puja` verifican las normas desde SQL, además de la protección HTTP. El actor procede de la sesión, nunca de los campos del formulario. Las credenciales de la base siguen siendo exclusivas del servidor; las inserciones de datos sintéticos de los seeders no representan acciones ni aceptaciones de usuarios reales.

La versión inicial es `2026-10-29.1`. Para cambiar las reglas se debe actualizar el texto y su fecha, crear una migración nueva que actualice la función de versión y conservar las aceptaciones anteriores. Una aceptación antigua deja de habilitar publicaciones y pujas; enviar un formulario con la versión anterior tampoco permite aceptar la nueva.

El historial de aceptación no tiene un endpoint de edición ni borrado. Los seeders no registran aceptaciones automáticamente. La fábrica de usuarios prepara participantes con aceptación únicamente en `testing`, para conservar el propósito de las pruebas anteriores; `sinAceptarNormas()` permite comprobar expresamente las restricciones y el flujo inicial. El registro real y los seeders no utilizan ese comportamiento.

## Verificación

Ejecutar las pruebas exclusivamente en `subastaya_test`, con la protección original de `tests/TestCase.php`. Las pruebas de HU17 cubren lectura pública, casilla obligatoria, identidad del actor, fecha del servidor, reenvíos, conservación de versiones, peticiones HTTP y SQL sin aceptación, permisos y redirecciones externas.

La revisión visual debe incluir escritorio y móvil, enlaces del índice, pie de página y lectura de las secciones. El contenido no ofrece garantías legales o financieras ni promete servicios que todavía no existen.

Resultado de esta entrega: 209 pruebas y 2033 comprobaciones aprobadas con `docker compose exec -T laravel php artisan test --compact`; formato PHP verificado con Pint. Revisión visual realizada en escritorio de 1440 × 900 y móvil de 390 × 844 y 375 × 812, incluyendo índice, lectura y pie de página, sin desbordamiento horizontal. La migración nueva fue aplicada localmente con `migrate --force`, sin reconstruir la base local. `tests/TestCase.php` permanece intacto.
