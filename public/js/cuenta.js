(() => {
    const esFormularioEditable = (formulario) => formulario.method.toLowerCase() !== "get"
        && !formulario.hasAttribute("data-cerrar-sesion");
    const huellaFormulario = (formulario, predeterminados = false) => JSON.stringify(
        Array.from(formulario.elements).filter((campo) => campo.name && !campo.disabled
            && !["_token", "_method"].includes(campo.name)
            && !["submit", "button", "reset", "image"].includes(campo.type))
            .map((campo) => {
                if (campo.type === "file") return [campo.name, predeterminados ? []
                    : Array.from(campo.files).map((archivo) => [archivo.name, archivo.size, archivo.type, archivo.lastModified])];
                if (["checkbox", "radio"].includes(campo.type)) return [campo.name, campo.value, predeterminados ? campo.defaultChecked : campo.checked];
                if (campo.options) {
                    const opciones = Array.from(campo.options);
                    let elegidas = opciones.filter((opcion) => predeterminados ? opcion.defaultSelected : opcion.selected);
                    if (predeterminados && !campo.multiple && elegidas.length === 0) elegidas = opciones.filter((opcion) => !opcion.disabled).slice(0, 1);
                    return [campo.name, elegidas.map((opcion) => opcion.value)];
                }
                return [campo.name, predeterminados ? campo.defaultValue : campo.value];
            })
    );
    // Las pruebas de comparación no necesitan un navegador ni una base de datos.
    if (typeof module !== "undefined" && module.exports) module.exports = { esFormularioEditable, huellaFormulario };
    if (typeof document === "undefined") return;

    const menu = document.querySelector("[data-cuenta-menu]");
    const formularioSalida = document.querySelector("[data-cerrar-sesion]");
    if (!menu || !formularioSalida) return;
    const activador = menu.querySelector("summary");
    const dialogo = document.querySelector("[data-confirmar-salida]");
    const iniciales = new WeakMap();
    const pendientes = new Set(JSON.parse(document.querySelector("[data-campos-pendientes]")?.dataset.camposPendientes || "[]"));
    const formulariosConErrores = new WeakSet();
    for (const form of document.forms) {
        if (!esFormularioEditable(form)) continue;
        iniciales.set(form, huellaFormulario(form));
        if (Array.from(form.elements).some((campo) => pendientes.has(campo.name))) formulariosConErrores.add(form);
    }
    let ultimoCampo = null;
    let salidaConfirmada = false;
    document.addEventListener("focusin", (evento) => {
        if (evento.target.matches("input, select, textarea") && evento.target.form && esFormularioEditable(evento.target.form)) ultimoCampo = evento.target;
    });
    document.addEventListener("click", (evento) => { if (!menu.contains(evento.target)) menu.open = false; });
    menu.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape" && menu.open) {
            evento.preventDefault(); menu.open = false; activador.focus();
        }
    });
    menu.addEventListener("focusout", (evento) => {
        if (evento.relatedTarget && !menu.contains(evento.relatedTarget)) menu.open = false;
    });
    const hayCambios = () => Array.from(document.forms).some((form) => esFormularioEditable(form)
        && (formulariosConErrores.has(form) || huellaFormulario(form) !== (iniciales.get(form) ?? huellaFormulario(form, true))));
    const seguirEditando = () => {
        dialogo.close();
        if (ultimoCampo?.isConnected && ultimoCampo.getClientRects().length) ultimoCampo.focus();
        else activador.focus();
    };
    dialogo?.querySelector("[data-seguir-editando]").addEventListener("click", seguirEditando);
    dialogo?.addEventListener("cancel", (evento) => { evento.preventDefault(); seguirEditando(); });
    dialogo?.querySelector("[data-confirmar-cierre]").addEventListener("click", () => {
        salidaConfirmada = true; dialogo.close(); formularioSalida.requestSubmit();
    });
    formularioSalida.addEventListener("submit", (evento) => {
        menu.open = false;
        if (salidaConfirmada || !hayCambios()) return;
        if (typeof dialogo?.showModal !== "function") {
            if (!window.confirm("Tienes cambios sin guardar. ¿Quieres cerrar sesión?")) evento.preventDefault();
            return;
        }
        evento.preventDefault(); dialogo.showModal();
    });
})();
