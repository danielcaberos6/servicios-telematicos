# Simulaciones de pujas en vivo

Con Docker funcionando (`docker compose up -d`), ejecuta desde la carpeta del proyecto
uno de estos tres scripts en PowerShell:

```powershell
.\scripts\simular-camara.ps1
.\scripts\simular-bicicleta.ps1
.\scripts\simular-guitarra.ps1
```

Cada script prepara un producto nuevo con foto, un vendedor y **cuatro pujadores**.
Muestra el enlace de la subasta y las cuentas. Abre ese enlace e inicia sesión como
el vendedor para ver todo el ranking desde la primera oferta. Se dispone de 15 segundos
antes de empezar; después se registran diez pujas, una cada cinco segundos, y se espera
el cierre. Las ofertas aparecen con la actualización automática del detalle, sin
recargar manualmente. Puedes usar una segunda ventana con otra cuenta para observar
su billetera y las notificaciones.

| Script | Categoría | Precio inicial | Incremento | Ganadora prevista* | Disponible inicial mínimo por pujador |
| --- | --- | ---: | ---: | ---: | ---: |
| Cámara | Electrónica e informática | Bs 1.000 | Bs 50 | Bs 1.450 | Bs 2.000 |
| Bicicleta | Deporte | Bs 500 | Bs 20 | Bs 680 | Bs 900 |
| Guitarra | Instrumentos musicales | Bs 250 | Bs 10 | Bs 340 | Bs 450 |

\* Si solo participan los cuatro usuarios del script. También se pueden registrar
ofertas desde la web: cada turno calcula el siguiente importe permitido.

## Cuentas

Contraseña inicial: `Simulacion2026!`. Para cada producto (`camara`, `bicicleta`,
`guitarra`), se crean estas cinco cuentas:

- `sim.PRODUCTO.vendedor@subastaya.test`
- `sim.PRODUCTO.pujador1@subastaya.test` (Elisa Morales)
- `sim.PRODUCTO.pujador2@subastaya.test` (Mateo Flores)
- `sim.PRODUCTO.pujador3@subastaya.test` (Sofía Ríos)
- `sim.PRODUCTO.pujador4@subastaya.test` (Bruno Castro)

Por ejemplo: `sim.camara.vendedor@subastaya.test`. Las contraseñas existentes se
conservan. No se reactivan cuentas deshabilitadas ni se cambian sus roles.

Los usuarios aceptan las normas mediante el registro normal. Las recargas pasan por
solicitud y confirmación de la billetera. Se completa únicamente el disponible que
falte para el presupuesto del escenario, sin borrar movimientos ni reservas anteriores.

## Duración y ejecución sin PowerShell

Para tener más tiempo para abrir el navegador:

```powershell
.\scripts\simular-camara.ps1 -Preparacion 45 -Intervalo 5 -Cierre 10
```

El mismo motor puede ejecutarse en cualquier sistema con Docker:

```sh
docker compose exec -T laravel php artisan subastas:simular camara --preparacion=15 --intervalo=5 --cierre=10
```

Cambia `camara` por `bicicleta` o `guitarra`. Los límites son 0–120 segundos de
preparación, 1–60 entre ofertas y 2–60 al final. No se ejecutan automáticamente al
levantar Docker ni se permiten fuera de `local` o `testing`.

## Resultado

La publicación pasa por la revisión del administrador activo y queda su decisión
registrada. Las pujas utilizan el servicio y los triggers habituales: respetan el
incremento, reservan saldo y liberan la reserva del usuario superado. No se desactivan
restricciones ni se alteran fechas después de publicar.

Al vencer el plazo se ejecuta el finalizador habitual de las subastas vencidas,
igual que el planificador. Se notifica el resultado y se crea la operación del ganador.
Su importe queda **retenido para la entrega**, sin cobrar todavía comisión ni abonar
al vendedor. La entrega se puede continuar manualmente desde Mis operaciones.

Cada repetición genera una subasta nueva y conserva el historial anterior. Se impide
ejecutar dos scripts del mismo producto a la vez; productos diferentes usan cuentas
independientes. Si interrumpes el script, las pujas confirmadas permanecen y el
planificador cerrará la subasta al vencer su plazo.
