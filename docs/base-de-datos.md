# Estructura de la base de datos

El dominio utiliza nombres en español: `usuario`, `subasta`, `puja`, `reserva_fondos`,
`operacion_subasta`, `movimiento_fondos`, entre otros. Se conservan las convenciones
internas de Laravel para sesiones, caché, colas, migraciones y `remember_token`.
MIME, SHA-256 y bytes son identificadores técnicos y unidades, no nombres de negocio.

## Normalización e integridad

Las entidades se separan en tablas con claves primarias y relaciones mediante claves
foráneas. Las relaciones de usuarios con roles, favoritos y aceptaciones de normas
utilizan claves compuestas para impedir duplicados. Las fotos de subastas se almacenan
en filas independientes, sin listas de imágenes dentro de una columna.

La migración `2026_11_02_000004_reducir_redundancia_importes` elimina el atributo
almacenado `operacion_subasta.neto`. El servicio y los reportes lo calculan como
`importe - comision` para operaciones con fondos; las operaciones históricas sin
fondos mantienen un neto mostrado de cero. Antes de migrar se comprueba que los valores
existentes coincidan. Se conservan importe y comisión pactados, participantes,
confirmaciones, mensajes y movimientos.

La reserva conserva usuario, subasta y monto para gestionar su ciclo financiero.
Una clave foránea compuesta exige que coincidan con la puja referenciada. Es diferida
porque la reserva se prepara antes de insertar la puja dentro de la misma transacción.
Una reserva inconsistente impide confirmar la transacción completa.

El saldo se obtiene de los movimientos, sin mantener otra columna de saldo editable.
La comisión cobrada y los movimientos constituyen registros históricos de hechos
financieros; no deben recalcularse aplicando reglas nuevas a operaciones anteriores.

## Redundancias deliberadas

No se afirma que todas las tablas cumplan estrictamente tercera forma normal.
`usuario.nombre` conserva el nombre de presentación compatible con cuentas antiguas
y se sincroniza con `nombres` y `apellidos` mediante un trigger. La reserva mantiene
una copia validada de los datos de su puja. Las solicitudes de identidad guardan los
datos presentados en ese momento y los registros de auditoría conservan valores
anteriores. Estas decisiones se mantienen por compatibilidad e historial.

No se requiere borrar volúmenes ni volver a cargar datos. Las migraciones se aplican
en el arranque habitual del contenedor. Los cambios de esquema se verifican con
pruebas sobre `subastaya_test`, separada de la base de desarrollo.
