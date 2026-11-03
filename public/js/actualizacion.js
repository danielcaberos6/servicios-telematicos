(() => {
    const regiones = [...document.querySelectorAll('[data-live-region]')];
    if (!regiones.length) return;
    let pendiente = false;
    let enviando = false;
    const firmas = new WeakMap(regiones.map(region => [region, region.innerHTML]));
    function editando(region) {
        if (region.contains(document.activeElement)) return true;
        return [...region.querySelectorAll('input,textarea,select')].some(campo => {
            if (['hidden', 'submit', 'button'].includes(campo.type)) return false;
            if (['checkbox', 'radio'].includes(campo.type)) return campo.checked !== campo.defaultChecked;
            if (campo.options) return [...campo.options].some(opcion => opcion.selected !== opcion.defaultSelected);
            return campo.value !== campo.defaultValue;
        });
    }
    document.addEventListener('submit', () => { enviando = true; window.setTimeout(() => { enviando = false; }, 1000); });
    async function actualizar() {
        if (pendiente || document.hidden || enviando) return;
        pendiente = true;
        const direccion = window.location.href;
        const avisos = document.querySelectorAll('[data-live-status]');
        try {
            const respuesta = await fetch(direccion, { headers: { Accept: 'text/html', 'X-Live-Update': '1' }, credentials: 'same-origin', redirect: 'error', cache: 'no-store', signal: AbortSignal.timeout(4000) });
            if (!respuesta.ok) throw new Error('No disponible');
            const html = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
            if (window.location.href !== direccion || enviando) return;
            const recibidas = [...html.querySelectorAll('[data-live-region]')];
            if (!recibidas.length) throw new Error('Sin datos');
            let aplazado = false;
            for (const region of regiones) {
                if (!region.isConnected) continue;
                const nueva = recibidas.find(nodo => nodo.dataset.liveRegion === region.dataset.liveRegion);
                if (!nueva) continue;
                if (firmas.get(region) === nueva.innerHTML) continue;
                if (editando(region)) { aplazado = true; continue; }
                region.replaceChildren(...[...nueva.childNodes].map(nodo => document.importNode(nodo, true)));
                firmas.set(region, nueva.innerHTML);
            }
            for (const aviso of avisos) aviso.textContent = aplazado
                ? 'Hay cambios disponibles. Conservamos lo que estás escribiendo; se actualizará al terminar.'
                : 'Datos actualizados automáticamente.';
        } catch {
            for (const aviso of avisos) aviso.textContent = 'Sin conexión: se conservan los últimos datos. Se reintentará automáticamente.';
        } finally { pendiente = false; }
    }
    window.setInterval(actualizar, 5000);
    document.addEventListener('visibilitychange', actualizar);
    window.addEventListener('focus', actualizar);
})();
