# HU18: Participación en subastas

Implementación según la HU18 del [Sprint Planning 3](https://docs.google.com/document/d/1IoMK64xf4MLwxhcprvJxlVfLy0m4D3gGSDM9XY6tS6A/edit).

## Alcance

| Criterios | Comportamiento |
| --- | --- |
| 1 a 7 | Monto inicial múltiplo positivo de 5; incrementos de 5, 10, 20, 50 o 100; primera puja exactamente igual al monto inicial; siguientes exactamente la oferta vigente más el incremento. Confirmación sin importe libre, rechazo de peticiones alteradas o desactualizadas, bloqueo en PostgreSQL ante concurrencia y condiciones fijadas desde la primera aceptación. |
| 8 | Las publicaciones ya existentes quedan en `modelo_puja=1`, conservando importes, centavos, reglas e historial. Las nuevas se crean en el modelo 2. No se transforman pujas antiguas ni se les atribuyen reservas. |
| 9 a 13 | Lista privada agrupada por artículo con mayor oferta propia, oferta vigente, foto, cierre y resultado. Apartados Todas, Voy ganando, Me superaron, Ganadas, No ganadas y Canceladas. Paginación de 15; privacidad por cuenta y restricciones de rol. El resultado del cierre no confirma la entrega. |
| 14 a 17 | Favoritos privados, idempotentes y sin puja; lista de 15 elementos; exclusión de publicaciones no disponibles; recordatorio interno único durante los últimos 30 minutos para favoritos guardados antes del umbral. |

La integración de fondos quedó habilitada tras la autorización del modelo simulado y se implementa en HU22, según la numeración vigente. Las nuevas publicaciones requieren saldo y reservas; las anteriores no adquieren reservas retroactivas. El criterio 12 queda integrado con HU23: cada fila ganada ofrece Conversación y Seguimiento de entrega, junto con el estado actual de esa entrega. Una subasta anterior ya finalizada permite crear su coordinación con un POST autorizado, sin inventar fondos. Si el cierre efectivo todavía espera al planificador, se explica la espera y se conserva el acceso al detalle, sin ofrecer un enlace de coordinación que devolvería 404. La consulta de la lista no crea operaciones ni modifica fondos.

## Rutas

- `/mis-participaciones?apartado=ganando`: seguimiento de la cuenta autenticada, sin parámetro de propietario.
- `/favoritos`: lista privada.
- `PUT /favoritos/{subasta}`: guardar sin duplicados.
- `DELETE /favoritos/{subasta}`: retirar solo de la propia lista.

Los campos adicionales de propietario enviados por un cliente no se utilizan. Las funciones de PostgreSQL de seguimiento y favoritos comprueban también que la cuenta esté activa y no tenga rol administrador.

## Migraciones y planificador

Las migraciones `2026_10_29_000003` y `2026_10_29_000004` añaden versiones, incrementos, condiciones fijadas, favoritos y registro de recordatorios. El arranque local habitual de Docker las aplica sin borrar los datos existentes. Los ejemplos históricos se identifican como modelo anterior.

El comando `subastas:recordar-favoritos` se ejecuta cada minuto junto con el finalizador existente. La clave única de `recordatorio_favorito` impide duplicar avisos, incluso si se repite el comando o se retira y vuelve a guardar el artículo. Los recordatorios y las notificaciones se registran en la misma transacción. No hay correos, pagos ni mensajes externos.

## Verificación

Las pruebas de `IncrementosPujaTest`, `ConcurrenciaPujasTest` y `SeguimientoParticipacionesTest` cubren importes, vistas desactualizadas, concurrencia, conservación del modelo anterior, condiciones fijadas, agrupación, privacidad, estados, paginación, duplicados y recordatorios. Se ejecutan exclusivamente en `subastaya_test` con la protección de `Tests\TestCase`.

Para probar el nuevo flujo manualmente, crea una publicación con monto inicial Bs 100 e incremento Bs 10, apruébala como administrador y entra con otra cuenta: la primera confirmación debe ser Bs 100 y la siguiente Bs 110. Dos pantallas que confirmen Bs 100 no deben registrar dos pujas. Las participaciones y los favoritos se consultan desde el menú de la cuenta normal.


## Cierre de integración de HU17 y HU18

Rama vigente de Juan: `HU-17-18/Normas-participacionSubastas/Juan`. Las dependencias de aceptación (HU17), fondos (HU22) y conversación/entrega (HU23) están implementadas; no quedan esas integraciones pendientes en HU18.

Los criterios 1 a 17 se cubren mediante la base existente y el acceso por fila a las operaciones. Las pruebas adicionales verifican enlaces privados del ganador, ausencia de acceso para otros pujadores, coordinación de publicaciones anteriores sin fondos y transición desde el cierre efectivo hasta el cierre registrado por el planificador. La paginación y los filtros originales se conservan.
