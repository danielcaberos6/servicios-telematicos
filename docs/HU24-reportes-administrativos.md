# HU24 · Reportes administrativos

Numeración vigente del Sprint Planning 3: HU24 corresponde a reportes; el documento anterior `HU24-consultas-articulos.md` corresponde a consultas, hoy HU20.

Rama de Rodrigo: `HU-22-24/Gestion-fondos-reportes/Rodrigo`.

## Accesos

En **Reportes**, el menú lateral mantiene **Subastas** y **Usuarios**, e incorpora **Ventas** y **Movimientos**. Todos requieren administrador activo, también al solicitar JSON o ejecutar las funciones de consulta PostgreSQL.

Los filtros permanecen visibles en móvil y escritorio. Los reportes de subastas conservan gráficos, paginación y ganador: Pendiente si sigue activa, nombre y monto si finalizó con ofertas, Sin ofertas si no hubo pujas y No aplica si se canceló. Se conserva la selección de mayor oferta válida sin multiplicar filas por cada puja.

## Ventas respaldadas por fondos

`/reportes/ventas` filtra por **fecha de cierre de la subasta**, estado de la operación y texto de artículo/comprador/vendedor. El intervalo incluye ambos días completos según la zona `America/La_Paz`.

Solo incluye operaciones y subastas con fondos de HU22. Las coordinaciones anteriores, sin reservas, no se presentan como ventas respaldadas ni como comisiones cobradas.

La tabla muestra comprador, vendedor, importe ganador, comisión prevista, neto previsto, estado, importe retenido, abono efectivo, comisión registrada y devolución. Se pagina de 15 en 15. El acceso a movimientos filtra exactamente la subasta, conservando los registros de reserva y resolución.

Los indicadores se calculan sobre **todo el conjunto filtrado**, antes de paginar, y reflejan los registros actuales de esas operaciones:

- **Retenido:** reservas en estado Retenida del comprador de la operación.
- **Abonado al vendedor:** movimientos Liquidación vinculados a esa operación y vendedor.
- **Comisiones registradas:** registros efectivos de `comision_operacion`.
- **Total liquidado:** movimientos Pago del comprador; coincide con el neto abonado más la comisión.
- **Devuelto:** movimientos Devolución asociados a la operación y comprador.

Una orden pendiente no genera ingresos ni comisiones cobradas. Una operación reembolsada no cobra comisión. Las columnas previstas no se usan para calcular ingresos efectivos. Ejemplo: una entrega de Bs 500 liquidada produce Bs 475 de neto y Bs 25 de comisión; una devolución devuelve Bs 500 sin comisión.

Este reporte selecciona operaciones por su cierre; no atribuye un abono tardío a la fecha del movimiento. Para consultar por fecha efectiva de registro se utiliza Movimientos.

## Libro y detalle de movimientos

`/reportes/movimientos` filtra por fecha de registro, tipo, titular y referencia/nombre/artículo. También admite filtros exactos de subasta u operación para seguir los enlaces de trazabilidad, sin confundir referencias como `operacion:1` y `operacion:10`.

Incluye recargas acreditadas, reservas, liberaciones, pagos, liquidaciones y devoluciones. Las recargas pendientes, rechazadas o canceladas no se presentan como acreditaciones porque no tienen movimiento de fondos aprobado.

`/reportes/movimientos/{id}` muestra referencia, titular, fecha de Bolivia, importe, variación disponible/reservada y estado Registrado. Muestra el estado y fechas de la recarga asociada, cuando existe, y la operación de la subasta. El estado actual de una reserva se muestra solo cuando corresponde a ese titular, para no atribuirle la reserva de otro pujador. Los movimientos históricos no cambian si cambia la reserva actual.

El administrador puede abrir el detalle también desde Control de fondos. Los importes disponible y reservado de cada fila son **variaciones del movimiento**, no el saldo actual. Los enlaces no habilitan edición, eliminación o escritura de saldos. No se expone contenido del chat, documentos ni datos de contacto de las partes.

## Instalación y comprobaciones

La migración `2026_10_31_000004` instala `ReportesEconomicos.sql` e índices de fecha para las consultas nuevas. No borra ni modifica movimientos, subastas o cuentas. El instalador de funciones incluye el mismo archivo.

`ReportesEconomicosTest` cubre estados financieros, exclusión del modelo anterior, conservación de importes, varias pujas, límites de fecha de Bolivia, 16 ventas paginadas, filtros, referencias, detalles, acceso restringido, fechas inválidas, ceros sin actividad e inmutabilidad de los datos tras consultas repetidas. Las pruebas usan exclusivamente `subastaya_test`.
