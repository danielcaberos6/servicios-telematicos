document.querySelectorAll('[data-espacio-seccion]').forEach((espacio) => {
    const nav = espacio.querySelector('.section-nav');
    const toggle = nav.querySelector('.section-menu-toggle');
    nav.classList.add('is-enhanced');
    toggle.hidden = false;
    const cambiarEstado = (abierto) => {
        toggle.setAttribute('aria-expanded', String(abierto));
        nav.classList.toggle('is-open', abierto);
        const accion = toggle.querySelector('.section-menu-action');
        if (accion) accion.textContent = abierto ? 'Cerrar' : 'Abrir';
    };
    toggle.addEventListener('click', () => {
        cambiarEstado(toggle.getAttribute('aria-expanded') !== 'true');
    });
    if (!espacio.hasAttribute('data-navegacion-local')) return;
    const paneles = [...espacio.querySelectorAll('[data-apartado]')];
    const enlaces = [...nav.querySelectorAll('a[href^="#"]')];
    const mostrar = (id, enfocar = false) => {
        if (!paneles.some((panel) => panel.id === id)) id = paneles[0]?.id;
        if (!id) return;
        paneles.forEach((panel) => { panel.hidden = panel.id !== id; });
        enlaces.forEach((enlace) => {
            if (enlace.hash === `#${id}`) enlace.setAttribute('aria-current', 'location');
            else enlace.removeAttribute('aria-current');
        });
        cambiarEstado(false);
        // Los mapas necesitan recalcular su tamaño al revelar su apartado.
        window.dispatchEvent(new Event('resize'));
        if (enfocar) {
            const titulo = document.getElementById(id)?.querySelector('h2');
            if (titulo) {
                titulo.tabIndex = -1;
                titulo.focus({ preventScroll: true });
                titulo.scrollIntoView({ block: 'nearest' });
            }
        }
    };
    enlaces.forEach((enlace) => enlace.addEventListener('click', (event) => {
        event.preventDefault();
        history.pushState(null, '', enlace.hash);
        mostrar(enlace.hash.slice(1), true);
    }));
    window.addEventListener('popstate', () => mostrar(location.hash.slice(1)));
    window.addEventListener('hashchange', () => mostrar(location.hash.slice(1)));
    // Mostrar el campo inválido antes de que el navegador intente enfocarlo.
    let validando = false;
    espacio.addEventListener('invalid', (event) => {
        if (validando) { event.preventDefault(); return; }
        validando = true;
        window.setTimeout(() => { validando = false; }, 0);
        const panel = event.target.closest('[data-apartado]');
        if (panel) {
            history.replaceState(null, '', `#${panel.id}`);
            if (panel.hidden) mostrar(panel.id);
        }
    }, true);
    const errores = JSON.parse(espacio.dataset.errores || '[]');
    const campo = [...espacio.querySelectorAll('[name]')].find((input) => errores.some((error) => error.split('.')[0] === input.name.replace(/\[.*$/, '')));
    mostrar(campo?.closest('[data-apartado]')?.id || location.hash.slice(1));
});
