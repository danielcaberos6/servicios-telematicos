# Menú de cuenta y cierre de sesión

La foto o las iniciales y el nombre de la cabecera abren un menú con «Mi perfil», «Apariencia» y «Cerrar sesión». La salida está separada al final y conserva el formulario POST con protección CSRF. El selector de apariencia también permanece visible en la cabecera.

El menú utiliza un desplegable nativo, funciona con teclado y se cierra con Escape, al pulsar fuera o al mover el foco fuera de sus opciones. Escape devuelve el foco al activador. Los colores y el tamaño de las opciones siguen los modos claro y oscuro, con objetivos táctiles de al menos 44 px.

Al solicitar el cierre se comparan los valores actuales de los formularios de escritura con sus valores iniciales. Si existen cambios pendientes, se abre un diálogo con «Seguir editando» y «Cerrar sesión». La opción inicial es seguir editando. Cancelar o pulsar Escape conserva los datos y devuelve el foco al último campo disponible. Confirmar envía el formulario original de salida.

La comparación incluye texto, casillas, radios, selectores simples y múltiples, y archivos. Restaurar el valor original elimina el cambio detectado. Los filtros GET, botones, campos deshabilitados y cambios del token CSRF no provocan advertencias. Los campos restaurados por una validación fallida siguen considerándose pendientes. Los formularios incorporados después de cargar la página se comparan con sus valores predeterminados.

Las comparaciones se mantienen únicamente en memoria de la página. No se envían valores a un servicio adicional ni se guardan copias en el almacenamiento del navegador. La confirmación se aplica al cierre de sesión; no añade bloqueos generales a todos los enlaces de navegación.

## Verificación

- `node --test tests/Frontend/*.test.cjs`: comportamiento de apariencia y siete casos de comparación de formularios.
- `MenuCuentaTest`: cuenta de visitante, opciones autenticadas, cierre POST y reconocimiento de campos pendientes tras errores de validación.
- `NavegacionVisitanteTest` conserva las comprobaciones de permisos y actualiza el nombre accesible a «Mi cuenta».
- Las pruebas PHP utilizan exclusivamente `subastaya_test` con la protección original de `tests/TestCase.php`.
- Suite completa: 225 pruebas PHP y 2332 comprobaciones; 14 pruebas JavaScript aprobadas. Pint y `git diff --check` sin incidencias.
- Revisión visual en escritorio de 1440 × 900 y móvil de 375 × 812, en modos claro y oscuro. Se verificaron el menú, el diálogo, Escape, la conservación de campos y el retorno del foco al cancelar.
- Comprobación real de cierre: confirmar termina la sesión; sin cambios pendientes, la salida es directa desde el menú. El servidor temporal de revisión usó `subastaya_test` y se retiró al finalizar.
