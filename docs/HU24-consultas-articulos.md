# HU24: Consultas sobre artículos

## Funcionalidad

El detalle de una subasta publicada incluye un apartado público «Preguntas sobre el artículo». Las consultas aparecen en orden de publicación, paginadas de 15 en 15, con nombre mostrado y fecha y hora de Bolivia. Los visitantes pueden leerlas; para preguntar o reportar deben iniciar sesión.

Un usuario normal activo distinto del vendedor puede enviar preguntas de 1 a 1.000 caracteres mientras la subasta esté aceptada, activa y dentro del plazo, con vendedor habilitado. Solo el vendedor puede responder, con 1 a 2.000 caracteres. Hay una única respuesta por pregunta y no se permite editarla. El cierre o cancelación conserva el contenido permitido como historial de solo lectura.

El vendedor recibe un aviso interno por pregunta nueva y el autor recibe uno al publicarse la respuesta. No se envían correos ni mensajes externos. Las consultas no registran pujas, reservas o movimientos económicos.

## Privacidad y moderación

- Los textos se muestran escapados; no se interpreta HTML.
- Se rechazan espacios en blanco, excesos de longitud, patrones comunes de correo, teléfonos y enlaces de contacto privado. Esto no reemplaza la revisión de contenido reportado.
- Las solicitudes privadas no exponen consultas. Los listados públicos no incluyen correo, teléfono, documentos ni motivos de reportes. Tampoco seleccionan el texto de una respuesta oculta.
- Se puede reportar la pregunta o una respuesta visible mediante un motivo de hasta 500 caracteres. Un reenvío del mismo reporte no lo duplica.
- Administración accede a `/admin/consultas` desde el enlace «Consultas» y dispone de pendientes y resueltos, con paginación.
- Ocultar o desestimar exige un motivo de hasta 500 caracteres. La decisión guarda moderador, fecha y motivo; se conserva el texto original para revisión administrativa.
- Ocultar la pregunta retira el hilo de la vista pública. Ocultar solo la respuesta conserva la pregunta y muestra un aviso de moderación. Los reportes pendientes del mismo contenido se resuelven juntos y una decisión ya registrada no se sobrescribe al reenviar.

## Implementación

Las migraciones nuevas añaden `consulta_articulo` y `reporte_consulta` e instalan `database/sql/ConsultasArticulos.sql`. No se modifican cuentas, imágenes ni ofertas anteriores. Las funciones SQL comprueban actor, rol, estado de cuenta, propietario, publicación, fecha de cierre y contenido. El actor siempre proviene de la sesión del servidor.

Una clave UUID por envío, única por usuario, evita duplicar preguntas y avisos ante reintentos. La respuesta bloquea la consulta y es idempotente si se repite el mismo texto. El bloqueo de la subasta comparte las reglas de cierre y cancelación; se revalida la hora tras las esperas relevantes. La moderación bloquea primero la consulta y después sus reportes para mantener decisiones consistentes.

Las credenciales de PostgreSQL son exclusivas del servidor. Los archivos SQL no son una API pública y las inserciones directas de administración o seeders no sustituyen las funciones que utiliza la aplicación.

## Verificación

Ejecutar las pruebas exclusivamente en `subastaya_test`, conservando `tests/TestCase.php`. `ConsultasArticulosTest` cubre lectura pública, orden y paginación, privacidad, HTML, límites, contactos, permisos, reenvíos, avisos, publicación privada, cierre, cancelación y moderación. `ConcurrenciaConsultasTest` ejecuta dos conexiones reales para comprobar que preguntas, respuestas y avisos no se dupliquen.

La suite completa aprobó 219 pruebas y 2182 comprobaciones. Se revisaron visualmente las consultas públicas y el formulario de respuesta en móvil de 375 × 812 y escritorio de 1440 × 900, y la moderación en móvil y escritorio de 1440 × 1000. La vista auxiliar utilizó únicamente `subastaya_test` y fue retirada tras la revisión. Se añadió acceso directo a las preguntas junto a la información de la oferta. Las migraciones nuevas están aplicadas localmente sin reconstruir la base ni alterar el historial existente.
