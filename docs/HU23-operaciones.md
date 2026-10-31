# HU23 · Gestión de operaciones

Rama: `HU-23-25/Gestion-operaciones-reputacion/Brayan`. Comisión aprobada: **5 %**.

## Flujo

1. El finalizador crea una operación única cuando una subasta aceptada termina con ganador. No hay operación sin ofertas. Las nuevas publicaciones mantienen retenida la reserva del ganador.
2. Comprador y vendedor acceden desde **Mis operaciones**, en el menú de cuenta, o **Coordinar entrega**, en el detalle de la subasta. Pueden proponer un lugar público y una fecha en hora de Bolivia. La otra parte acepta o solicita un cambio. El acuerdo no libera fondos.
3. Cada parte confirma únicamente su propia entrega o recepción. Cuando existen ambas confirmaciones y ninguna incidencia abierta, se registra el pago una sola vez: reserva del comprador consumida, 95 % disponible para el vendedor y 5 % en el registro de comisiones. La comisión se redondea a dos decimales y el neto es la diferencia, sin perder centavos.
4. Una incidencia anterior al pago retiene los fondos. En **Incidencias de entrega**, el administrador se asigna el caso y registra una resolución motivada: liquidar o cancelar y devolver toda la reserva, sin comisión. No se confiscan fondos.
5. El planificador revisa operaciones pendientes de 72 horas. Genera un único aviso para ambas partes y los administradores, y deriva el caso a revisión. Nunca paga o devuelve por vencimiento del plazo.

Ejemplo: una puja ganadora de Bs 500 produce Bs 475 para el vendedor y Bs 25 de comisión. Si se cancela mediante una incidencia, el comprador recupera los Bs 500 disponibles.

## Conversación y privacidad

- Una conversación por operación, exclusivamente entre vendedor y ganador. No hay conversaciones para subastas activas, canceladas o sin ofertas.
- Texto de 1 a 2.000 caracteres, sin archivos. El servidor valida autor y pertenencia; las vistas escapan el texto y el actualizador usa `textContent`.
- Historial de 15 mensajes por página, fechas de Bolivia e indicador de mensajes sin leer en Mis operaciones. La página más reciente consulta novedades cada tres segundos cuando está visible; un error conserva mensajes y borrador. La lectura se registra mediante POST con CSRF.
- UUID único por envío para no duplicar mensajes al reintentar. No hay endpoints para editar o borrar mensajes.
- El administrador solo consulta la conversación de una incidencia activa asignada a su cuenta. Cada acceso, incluyendo paginación, registra cuenta, fecha y motivo. No puede escribir mensajes ni acceder a una bandeja general de chats. Al resolver, pierde ese acceso.

## Compatibilidad e instalación

La migración `2026_10_31_000002` agrega las tablas sin borrar datos existentes. `docker compose up -d` conserva el procedimiento automático de migraciones e instalación de funciones del proyecto.

Las subastas anteriores a HU22 permiten crear su coordinación al pulsar **Coordinar entrega**. No se inventan reservas, cobros ni créditos. Su plazo de seguimiento empieza al crear la coordinación; para las subastas nuevas empieza en su fecha de cierre. Finalizar la subasta y completar la entrega son estados independientes.

Los saldos siguen siendo simulados; no hay pagos bancarios. Las normas publican el flujo de entrega y la comisión bajo la versión `2026-10-31.2`.

Comando automático cada minuto: `php artisan subastas:revisar-entregas`. El comando de instalación de funciones incluye `Operaciones.sql`.

## Integridad y comprobaciones

Las funciones PostgreSQL bloquean la operación antes de confirmar, reportar, acordar o resolver. La liquidación bloquea las billeteras por ID ordenado y ejecuta reserva, pago y comisión dentro de la misma transacción. Los movimientos y el historial no admiten modificaciones ni eliminación; los participantes e importes de la operación quedan fijados.

Las pruebas de `GestionOperacionesTest` cubren comisión, conservación del saldo, idempotencia, devolución, autorización administrativa, plazo de 72 horas, propuestas, privacidad, XSS, historial y compatibilidad. `ConcurrenciaOperacionesTest` utiliza conexiones independientes contra `subastaya_test` para comprobar confirmaciones simultáneas y una incidencia que compite con la confirmación final. Ninguna prueba usa la base de desarrollo.
