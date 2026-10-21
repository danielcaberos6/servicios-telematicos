// Al recargar Iniciar sesión o Crear cuenta, volver a la página de inicio.
if (performance.getEntriesByType("navigation")[0]?.type === "reload") {
    location.replace(document.currentScript.dataset.inicio);
}
