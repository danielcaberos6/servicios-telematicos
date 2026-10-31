# HU22: Gestión de fondos

Responsable y autoría: RFM-10 (Rodrigo). Rama: `HU-22/Gestion-fondos/Rodrigo`, creada desde `develop`.

## Flujo

La billetera utiliza bolivianos simulados. No hay QR manual, comprobantes, API bancaria ni dinero real. La interfaz de defensa usa los nombres Mi billetera, Saldo disponible y Saldo reservado sin un anuncio permanente; las normas y este documento explican el alcance simulado.

Cada cuenta recibe una billetera vacía. Solicitar una recarga entera entre 5 y 10.000 Bs genera una referencia UUID pendiente y todavía no acredita saldo. Su titular puede confirmar aprobación, rechazo o cancelación mediante POST con CSRF y aceptación de normas vigente. El resultado final no cambia ni vuelve a acreditar al repetir una petición o abrir la página de detalle. No se confía en importes o propietarios enviados durante la confirmación.

Los saldos se derivan de un libro de movimientos inmutable. No existe campo editable de saldo. Cada movimiento conserva tipo, importe exacto decimal, referencia, hora y efecto en disponible y reservado; el historial y las recargas se paginan cada 15 filas. La billetera consulta su saldo cada dos segundos mientras está visible, recupera al volver a la pestaña y conserva el último valor si falla la red. Las respuestas privadas no se almacenan en caché.

## Integración de pujas

Las publicaciones creadas desde esta actualización mediante `crear_subasta` tienen `requiere_fondos=true`. Las anteriores conservan false, incluidos los ejemplos históricos, sus cantidades y reglas; no se les crean reservas retroactivas. El campo no puede modificarse después de crear la publicación.

La validación bloquea primero la subasta, comprueba normas, identidad de cuenta, vigencia e incremento y después bloquea las billeteras afectadas en orden ascendente de usuario:

1. Primera puja: pasa el importe completo de disponible a reservado.
2. Cambio de líder: libera íntegramente la reserva anterior y reserva el importe nuevo.
3. El líder aumenta su propia puja: reserva únicamente la diferencia.
4. Fondos insuficientes: la transacción revierte puja, reservas, movimientos y avisos.
5. Finalización: la reserva cambia a Retenida; no se acredita todavía al vendedor.
6. Cancelación de una subasta activa con fondos: el cambio de estado libera la reserva una sola vez. Esta historia prepara la consistencia del evento; no añade una pantalla de resolución de incidencias ni permite al vendedor retirar pujas. Las operaciones posteriores al cierre corresponden a HU23.

Las reservas referencian la puja mediante una clave foránea diferida hasta el fin de la transacción. HU23 ya integra liquidación al vendedor, comisión del 5 % y devolución tras revisar incidencias; consulta `HU23-operaciones.md`.

## Accesos

- Usuarios: menú de cuenta, Mi billetera (`/mi-billetera`), recargas y detalle propio.
- Administración: menú de cuenta, Control de fondos (`/admin/fondos`), búsqueda de cuentas y consulta de movimientos de solo lectura.
- Visitantes, cuentas inactivas y terceros no acceden a las operaciones privadas. Un administrador no puede recargar cuentas mediante las rutas de usuario.

La versión de normas `2026-10-31.1` describe el cambio; se conservan aceptaciones previas y se solicita la versión vigente antes de recargar o pujar.

## Instalación y pruebas

La migración `2026_10_31_000001_agregar_gestion_fondos` añade billeteras, recargas, movimientos y reservas sin borrar cuentas ni ofertas. El arranque local de Docker ejecuta las migraciones habituales. El comando `subastas:instalar-funciones` incluye `Fondos.sql`.

`GestionFondosTest` comprueba recargas, idempotencia, límites, privacidad, conservación, reservas, reversión, cierre y paginación. `ConcurrenciaFondosTest` utiliza dos conexiones reales a `subastaya_test` para probar doble confirmación y uso del mismo saldo en dos subastas. `ConcurrenciaPujasTest` sigue verificando el bloqueo del siguiente monto en una misma subasta. Las cuentas de pruebas preparadas con `conFondos()` se financian mediante recargas aprobadas, nunca modificando directamente un saldo.

```sh
docker compose exec -T laravel php artisan test --compact
```

Las pruebas de navegador de `tests/Frontend/fondos.test.cjs` verifican actualización periódica, recuperación al volver a la pestaña y conservación del último saldo ante errores, sin peticiones que modifiquen datos.
