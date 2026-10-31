# Estabilización previa al despliegue

Revisión de las historias implementadas del Sprint Planning 3. No reemplaza las comprobaciones de infraestructura de HU19 ni de continuidad de HU21.

| Historia | Revisión y resultado |
| --- | --- |
| HU17 · Normativa | Se conservan la lectura pública, la aceptación versionada y el bloqueo de publicar, ofertar y recargar sin aceptación. No se modifican condiciones ni se aceptan normas automáticamente. |
| HU18 · Participación | El detalle de la subasta muestra la espera del planificador cuando venció pero aún no se registró el cierre. No ofrece entonces el POST de coordinación que respondería 404. La fila ganada mantiene los accesos privados de conversación y entrega. |
| HU20 · Consultas | Se conservan las preguntas públicas, permisos de respuesta y moderación. Las pruebas de regresión cubren sus restricciones. |
| HU22 · Fondos | No se modifican importes ni movimientos. Se comprueba su integración con pago y devolución y su coincidencia con los reportes. |
| HU23 · Operaciones | Filtro combinado de compras/ventas y estado. Sincronización privada del estado de entrega cada tres segundos. Notificaciones hacia la operación o incidencia concreta. Corrección de lectura del chat y conservación de borradores. |
| HU24 · Reportes | Se comprueba que el flujo liquidado registre neto más comisión y que la devolución no genere comisión ni liquidación. Las consultas siguen siendo de solo lectura y exclusivas de administradores. |
| HU25 · Reputación | Se comprueba la publicación diferida tras completar la entrega. Una operación reembolsada no habilita valoración. |
| HU26 · Apariencia | Se reutilizan estilos y colores de ambos temas; filtros y acciones se adaptan a pantallas pequeñas. Las actualizaciones no vuelven a crear mensajes que no cambiaron. |

## Entregas sincronizadas

`GET /operaciones/{id}/estado` comprueba que el actor sea una de las partes activas, sin rol administrador. Devuelve el estado, fechas de confirmación y una firma de las novedades de la operación. No devuelve conversaciones, motivos de incidencias ni datos de contacto. La respuesta es privada y no se almacena en caché.

El navegador actualiza el resumen y las confirmaciones. Si no hay un formulario sin enviar, renueva los detalles; si hay texto, selecciones o confirmaciones pendientes, conserva el borrador y ofrece actualizar explícitamente. En una resolución terminal deshabilita las acciones de entrega que ya no corresponden. Los errores de conexión mantienen la información anterior y reintentan; no cambian movimientos de fondos.

## Lectura de mensajes

Se registra lectura solo en la página de mensajes recientes, con la pestaña visible y cuando el mensaje más reciente alcanza la parte visible del panel. Los mensajes nuevos que todavía quedan por debajo no se marcan automáticamente como leídos. Si los identificadores no cambiaron, se conservan los nodos para no interrumpir la selección o lectura del texto.

## Notificaciones

`GET /notificaciones/{id}/destino` comprueba la propiedad de la notificación antes de resolver el destino. Una parte abre su operación; el administrador abre la incidencia asociada. El enlace no concede acceso al chat administrativo: se mantienen la asignación de caso y el registro de accesos. Consultar el destino no marca el aviso como leído ni ejecuta una resolución.

## Comprobaciones

Las pruebas funcionales recorren coordinación, confirmaciones, liquidación, valoración y conciliación del reporte; también incidencia, resolución motivada y devolución sin comisión. Las pruebas de JavaScript comprueban la actualización del estado, borradores, pérdida de conexión, pestaña oculta y mensajes fuera de la zona visible. Son comprobaciones automatizadas de HTTP, servicios, PostgreSQL y DOM simulado, no una afirmación de pruebas manuales entre dos personas.

La revisión y las pruebas utilizan `subastaya_test`; no se reinicia ni se carga saldo en la base de desarrollo. No se modifican migraciones ni la política de comisión del 5 %.
