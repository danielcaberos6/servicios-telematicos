# HU13 · Participación en pujas e historial

Responsable: Nicolás. Referencia: [Sprint Planning 2](https://docs.google.com/document/d/1UezzPNPFsb3ABTmt7cl_rH2FZSoe6NJnpM96rinTr6w/edit?tab=t.0).

La base existente valida las ofertas mediante funciones y disparadores de PostgreSQL. Se conserva: primera oferta desde el monto inicial, siguientes estrictamente superiores, prohibición de autopujas y rechazo de publicaciones no aprobadas o fuera de plazo. Las cuentas desactivadas se bloquean en las peticiones web. Los avisos al vendedor y al líder desplazado forman parte de la misma operación.

El detalle incorpora ahora el historial completo, separado del ranking que resume la mejor oferta por persona. Presenta montos descendentes, fecha y hora de Bolivia, identifica las ofertas propias y pagina cada 15 registros. El vendedor y los participantes tienen acceso; visitantes y terceros no reciben el historial. No incluye datos de contacto. Sin ofertas se muestra un mensaje explicativo.

## Comprobaciones

- `ParticipacionPujasTest`: historial, fechas, orden, privacidad, paginación, estado vacío, ofertas inválidas y avisos.
- `ConcurrenciaPujasTest`: dos procesos con conexiones independientes. Comprueba que la segunda operación espera un bloqueo real y revalida el monto al liberarse; prueba ofertas iguales, inferiores y superiores, incluidos sus avisos.
- Las pruebas existentes cubren cuentas desactivadas, permisos administrativos, publicaciones pendientes o rechazadas y ofertas vencidas.

Ejecutar la suite con `docker compose exec -T laravel php artisan test --compact`.

Todas las pruebas usan exclusivamente `subastaya_test`, con la protección de `tests/TestCase.php`. No se requieren migraciones nuevas para este cambio.
