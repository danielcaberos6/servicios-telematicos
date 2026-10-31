(() => {
    const pagina = document.querySelector('[data-billetera]');
    if (!pagina) return;
    const formato = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let pendiente = false;
    async function actualizar() {
        if (pendiente || document.hidden) return;
        pendiente = true;
        const aviso = pagina.querySelector('[data-saldo-estado]');
        try {
            const respuesta = await fetch(pagina.dataset.saldoUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(4000) });
            if (!respuesta.ok) throw new Error('No disponible');
            const saldo = await respuesta.json();
            for (const clave of ['disponible', 'reservado', 'total']) {
                if (!Number.isFinite(Number(saldo[clave]))) throw new Error('Saldo inválido');
            }
            for (const nodo of pagina.querySelectorAll('[data-saldo]')) nodo.textContent = formato.format(Number(saldo[nodo.dataset.saldo]));
            aviso.textContent = 'Saldo actualizado. El historial muestra los movimientos al cargar la página.';
        } catch {
            aviso.textContent = 'No pudimos actualizar el saldo. Se conserva el último valor recibido; vuelve a cargar para reintentar.';
        } finally { pendiente = false; }
    }
    window.setInterval(actualizar, 2000);
    document.addEventListener('visibilitychange', actualizar);
    window.addEventListener('focus', actualizar);
})();
