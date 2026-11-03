const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(require('node:path').resolve(__dirname, '../../public/js/app.js'), 'utf8');

function entorno() {
    const eventos = {}, eventosCerrar = {}, timers = new Map();
    let siguiente = 0, eliminado = false;
    const cerrar = { hidden: true, addEventListener: (nombre, fn) => { eventosCerrar[nombre] = fn; } };
    const aviso = {
        querySelector: () => cerrar,
        matches: () => true, // El cursor ya está encima al volver del cierre de sesión.
        contains: (nodo) => nodo === cerrar,
        addEventListener: (nombre, fn) => { eventos[nombre] = fn; },
        remove: () => { eliminado = true; },
    };
    const document = {
        activeElement: null,
        querySelector: () => null,
        querySelectorAll: (selector) => selector === '[data-aviso-exito]' ? [aviso] : [],
        addEventListener() {},
    };
    const window = {
        setTimeout: (fn, ms) => { timers.set(++siguiente, { fn, ms }); return siguiente; },
        clearTimeout: (id) => timers.delete(id),
        addEventListener() {},
    };
    vm.runInNewContext(source, { document, window });
    const avanzar = (ms) => {
        for (const [id, timer] of [...timers]) {
            if (timer.ms <= ms) { timers.delete(id); timer.fn(); }
        }
    };
    return { document, cerrar, eventos, eventosCerrar, avanzar, get eliminado() { return eliminado; } };
}

test('el aviso desaparece a los seis segundos aunque el cursor esté encima', () => {
    const e = entorno();
    e.eventos.mouseenter?.();
    e.avanzar(5999);
    assert.equal(e.eliminado, false);
    e.avanzar(6000);
    assert.equal(e.eliminado, true);
});

test('permite cerrar manualmente antes del vencimiento', () => {
    const e = entorno();
    assert.equal(e.cerrar.hidden, false);
    e.eventosCerrar.click();
    assert.equal(e.eliminado, true);
});

test('conserva el aviso mientras su botón tiene foco y retoma el plazo al salir', () => {
    const e = entorno();
    e.document.activeElement = e.cerrar;
    e.eventos.focusin();
    e.avanzar(6000);
    assert.equal(e.eliminado, false);
    e.document.activeElement = null;
    e.eventos.focusout({ relatedTarget: null });
    e.avanzar(0);
    e.avanzar(6000);
    assert.equal(e.eliminado, true);
});
