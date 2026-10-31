# HU25 · Reputación de participantes

Numeración del Sprint Planning 3 vigente. Reputación es HU25; apariencia pasó a HU26, aunque su documento anterior conserva el nombre `HU25-apariencia.md`.

Rama de Brayan: `HU-23-25/Gestion-operaciones-reputacion/Brayan`.

## Valoración de una entrega

En **Mis operaciones**, la sección **Valorar esta experiencia** permite valorar a la contraparte cuando la operación está Completada, tanto por confirmación de ambas partes como por resolución administrativa. Ofertar o ganar sin completar la entrega no habilita la valoración; tampoco una operación reembolsada.

La puntuación es un entero de 1 a 5 y el comentario es opcional, de hasta 1.000 caracteres. El servidor obtiene el destinatario y su rol desde la operación; no acepta un destinatario arbitrario. Cada autor puede enviar una única reseña por operación y no puede modificarla. Una operación corresponde a una sola subasta.

El plazo termina exactamente 14 días después de la fecha de resolución, calculados en hora de Bolivia. Un reenvío idéntico conserva la reseña existente, incluso si luego vence el plazo; una nueva reseña fuera de plazo se rechaza.

## Publicación diferida

`resenas_publicadas` publica una reseña verificada solo cuando ambas partes han valorado o cuando termina el plazo. La condición se evalúa al consultar: el vencimiento no necesita una tarea programada.

Hasta entonces, únicamente el autor ve el contenido de su propia reseña en su operación. La contraparte, el perfil público, el perfil propio y los reportes no reciben su puntuación ni comentario reservados. El historial de la operación registra que se envió una valoración, sin incluir su contenido.

La reputación pública muestra promedio y cantidad por separado como comprador y vendedor. Las reseñas previas sin operación asociada conservan sus valores, incluidos los fraccionarios, y se muestran en un apartado histórico; no cuentan en los promedios verificados por rol. Ambas listas de comentarios se paginan de 15 en 15.

## Moderación

Los participantes activos pueden reportar comentarios publicados por insultos, acoso o datos personales. Un mismo usuario no duplica el reporte de una reseña. No se admite «puntuación baja» como categoría de reporte.

El administrador accede a **Reportes de reseñas** desde su menú de cuenta. Registra **Ocultar comentario** o **Sin acción**, junto con un motivo obligatorio. La decisión conserva cuenta administrativa, fecha y motivo en un historial permanente. La operación, la puntuación y los movimientos de fondos no cambian; un comentario oculto se sustituye por una explicación pública, también en las consultas anteriores del perfil. No existe un endpoint para eliminar reseñas o cambiar puntuaciones.

## Perfil público

`/participantes/{id}` se abre desde el nombre del vendedor de una publicación o del autor de una reseña. Expone únicamente el nombre mostrado, foto o iniciales, distintivo de identidad y ciudad cuando su titular activa **Mostrar mi ciudad en el perfil público**. Las cuentas anteriores empiezan con esa opción desactivada.

No publica correo, teléfono, biografía privada, carnet, direcciones personales, solicitudes pendientes/rechazadas ni conversaciones. El catálogo del perfil incluye exclusivamente publicaciones aceptadas, incluso cuando quien consulta es su propietario o un administrador. Las cuentas desactivadas o no disponibles muestran un estado de cuenta no disponible; los perfiles sin reputación muestran un estado vacío sin inventar puntuaciones.

La proyección `perfil_publico` limita los campos desde PostgreSQL, sin serializar el modelo completo de usuario. Los comentarios se muestran escapados.

## Instalación y validación

La migración `2026_10_31_000003` agrega columnas y tablas sin borrar datos previos, instala `Reputacion.sql` y actualiza las consultas anteriores. El instalador habitual de funciones también incluye ese archivo. Las normas se publican bajo la versión `2026-10-31.3`.

`ReputacionParticipantesTest` comprueba permisos, publicación reservada, plazo, duplicados, validaciones, moderación, privacidad, consentimiento de ciudad, catálogo público, XSS y reseñas históricas. `ConcurrenciaReputacionTest` usa dos conexiones PostgreSQL independientes para verificar el envío simultáneo por ambas partes y los reenvíos del mismo autor. Todas las pruebas utilizan exclusivamente `subastaya_test`.
