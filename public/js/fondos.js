(() => {
    const cabecera = document.querySelector('[data-saldo-cabecera]');
    const pagina = document.querySelector('[data-billetera]');
    if (!cabecera) return;
    const formato = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let pendiente = false;
    async function actualizar() {
        if (pendiente || document.hidden) return;
        pendiente = true;
        const aviso = pagina?.querySelector('[data-saldo-estado]');
        try {
            const respuesta = await fetch(cabecera.dataset.saldoUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', redirect: 'error', cache: 'no-store', signal: AbortSignal.timeout(4000) });
            if (!respuesta.ok) throw new Error('No disponible');
            const saldo = await respuesta.json();
            for (const clave of ['disponible', 'reservado', 'total']) {
                if (saldo[clave] == null || !Number.isFinite(Number(saldo[clave]))) throw new Error('Saldo inválido');
            }
            cabecera.querySelector('[data-disponible-cabecera]').textContent = formato.format(Number(saldo.disponible));
            cabecera.title = 'Saldo disponible actualizado, sin el importe reservado';
            if (pagina) for (const nodo of pagina.querySelectorAll('[data-saldo]')) nodo.textContent = formato.format(Number(saldo[nodo.dataset.saldo]));
            if (aviso) aviso.textContent = 'Saldo actualizado automáticamente.';
        } catch {
            cabecera.title = 'No se pudo actualizar. Se muestra el último saldo recibido; consulta Mi billetera antes de ofertar.';
            if (aviso) aviso.textContent = 'Sin conexión: se conserva el último saldo recibido. Se reintentará automáticamente.';
        } finally { pendiente = false; }
    }
    window.setInterval(actualizar, 2000);
    document.addEventListener('visibilitychange', actualizar);
    window.addEventListener('focus', actualizar);
    actualizar();
})();
