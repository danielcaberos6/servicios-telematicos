# HU25: Apariencia del sistema

## Uso

La cabecera incluye un botón de apariencia para visitantes, usuarios y administradores. Alterna entre modo claro y oscuro sin recargar la página, enviar formularios ni modificar la sesión. En escritorio muestra icono y modo actual; en móvil conserva un icono con nombre accesible «Modo oscuro», estado pulsado y descripción de la acción disponible. Al ser un botón nativo, funciona con teclado.

En la primera visita se sigue `prefers-color-scheme` del sistema. Una elección manual se guarda con la clave `subastaya.apariencia` y prevalece en ese navegador y origen al recargar o cambiar de página. Los cambios se sincronizan entre pestañas. Si no hay elección manual, los cambios de apariencia del dispositivo se siguen automáticamente. Borrar los datos de navegación elimina la elección guardada.

Si el almacenamiento local está bloqueado, el cambio continúa funcionando durante esa página. Al navegar nuevamente, puede volver a utilizarse la preferencia del dispositivo. Esto no bloquea la navegación, el inicio de sesión ni los formularios.

## Estilos

`public/js/apariencia.js` se carga antes de los estilos y aplica `data-tema` a la raíz del documento para evitar el destello evitable del tema contrario. `public/css/apariencia.css` define la paleta y se carga después de los estilos de componentes.

Las superficies, textos, campos, bordes, estados, foco y colores de gráficos utilizan variables compartidas. Se conserva la identidad azul. Se adaptan catálogo, publicaciones, perfil, normas, consultas, administración, notificaciones, reportes y visor de identidad. El cambio de tema no aplica filtros ni mezclas sobre fotografías, carnets o mapas.

Los gráficos conservan etiquetas y valores numéricos, y la serie de usuarios mantiene su alternativa textual. Sus colores cambian con el tema mediante variables CSS, sin reconstruir los datos ni depender de JavaScript para actualizar cada gráfico.

Los componentes que se abren o cargan después, como el visor de identidad, heredan las variables del tema vigente. Al incorporar nuevas pantallas se deben reutilizar `--surface`, `--paper`, `--ink`, `--muted`, `--blue`, `--control-border` y las variables de estado. Los fondos de acciones utilizan `--action-bg`, con texto blanco, y la cabecera conserva `--brand-navy`.

La mensajería privada de HU19 todavía no existe: no se añadió esa funcionalidad como parte de esta historia. Las vistas de consultas de HU24 sí están incluidas en la adaptación.

## Verificación

- `node --test tests/Frontend/apariencia.test.cjs`: siete pruebas de preferencia inicial y guardada, prioridad de elección manual, cambios del dispositivo, navegación sin recarga, almacenamiento bloqueado, sincronización y contraste de la paleta en ambos modos.
- `docker compose exec -T laravel php artisan test --compact`: suite completa con 222 pruebas y 2308 comprobaciones aprobadas, exclusivamente en `subastaya_test` y conservando la protección de `tests/TestCase.php`.
- Revisión visual en 375 × 812, 1024 × 900 y 1440 × 900, incluyendo inicio, acceso, formulario de publicación, perfil, reportes y visor de identidad. Se comprobó el cambio con teclado, persistencia al navegar y recargar, conservación de campos escritos y ausencia de desbordes en los tamaños revisados.
- Medición del contraste del texto renderizado en reportes, en ambos modos, además de las parejas de colores de texto y estados con mínimo 4,5:1 y controles, foco y gráficos con mínimo 3:1. No se atribuye esta comprobación a una auditoría completa de accesibilidad de todas las pantallas.

La revisión visual utiliza un servidor auxiliar conectado a `subastaya_test`, cuentas sintéticas e imágenes vacías de prueba; no utiliza documentos personales reales.
