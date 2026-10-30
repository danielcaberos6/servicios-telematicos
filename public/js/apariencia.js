// Se ejecuta antes de cargar los estilos para evitar un destello del tema contrario.
(() => {
    const clave = "subastaya.apariencia";
    const raiz = document.documentElement;
    const sistema = typeof window.matchMedia === "function"
        ? window.matchMedia("(prefers-color-scheme: dark)") : null;
    const valida = (valor) => valor === "claro" || valor === "oscuro";
    let preferencia = null;
    try {
        const guardada = window.localStorage.getItem(clave);
        if (valida(guardada)) preferencia = guardada;
    } catch { /* El almacenamiento bloqueado no debe impedir navegar. */ }

    const actualizarControles = () => {
        const oscuro = raiz.dataset.tema === "oscuro";
        document.querySelectorAll("[data-tema-selector]").forEach((boton) => {
            boton.hidden = false;
            boton.setAttribute("aria-pressed", String(oscuro));
            boton.title = oscuro ? "Cambiar a modo claro" : "Cambiar a modo oscuro";
            const texto = boton.querySelector("[data-tema-texto]");
            if (texto) texto.textContent = oscuro ? "Oscuro" : "Claro";
        });
    };
    const aplicar = () => {
        raiz.dataset.tema = preferencia ?? (sistema?.matches ? "oscuro" : "claro");
        actualizarControles();
    };
    aplicar();
    const iniciar = () => {
        actualizarControles();
        document.querySelectorAll("[data-tema-selector]").forEach((boton) => {
            boton.addEventListener("click", () => {
                preferencia = raiz.dataset.tema === "oscuro" ? "claro" : "oscuro";
                try { window.localStorage.setItem(clave, preferencia); } catch { /* Mantener la elección en esta página. */ }
                aplicar();
            });
        });
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", iniciar, { once: true });
    else iniciar();
    const seguirSistema = () => { if (preferencia === null) aplicar(); };
    if (sistema?.addEventListener) sistema.addEventListener("change", seguirSistema);
    else if (sistema?.addListener) sistema.addListener(seguirSistema);
    window.addEventListener("storage", (evento) => {
        if (evento.key !== clave && evento.key !== null) return;
        preferencia = valida(evento.newValue) ? evento.newValue : null;
        aplicar();
    });
})();
