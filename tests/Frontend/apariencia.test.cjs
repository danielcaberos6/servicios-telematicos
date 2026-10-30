const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');
const root = resolve(__dirname, '../..');
const source = readFileSync(resolve(root, 'public/js/apariencia.js'), 'utf8');

function browser({ stored = null, dark = false, blocked = false } = {}) {
    const handlers = {}, buttonHandlers = {}, mediaHandlers = {};
    const values = new Map(stored === null ? [] : [['subastaya.apariencia', stored]]);
    const root = { dataset: {} }, label = { textContent: '' };
    const button = { hidden: true, attributes: {}, setAttribute(k, v) { this.attributes[k] = v; }, querySelector() { return label; }, addEventListener(k, v) { buttonHandlers[k] = v; } };
    let loaded = false;
    const media = { matches: dark, addEventListener(k, v) { mediaHandlers[k] = v; } };
    const document = { documentElement: root, readyState: 'loading', querySelectorAll() { return loaded ? [button] : []; }, addEventListener(k, v) { handlers[k] = v; } };
    const window = { matchMedia() { return media; }, localStorage: {
        getItem(k) { if (blocked) throw new Error('Storage blocked'); return values.get(k) ?? null; },
        setItem(k, v) { if (blocked) throw new Error('Storage blocked'); values.set(k, v); },
    }, addEventListener(k, v) { handlers[k] = v; } };
    Object.defineProperty(window, 'location', { get() { throw new Error('El tema no debe navegar ni recargar'); } });
    vm.runInNewContext(source, { window, document });
    return {
        root, button, label, values,
        load() { loaded = true; handlers.DOMContentLoaded(); },
        click() { buttonHandlers.click(); },
        system(value) { media.matches = value; mediaHandlers.change(); },
        storage(value) { handlers.storage({ key: 'subastaya.apariencia', newValue: value }); },
    };
}

test('el tema inicial sigue el dispositivo antes de crear el contenido', () => {
    for (const dark of [false, true]) {
        const b = browser({ dark });
        assert.equal(b.root.dataset.tema, dark ? 'oscuro' : 'claro');
        b.load();
        assert.equal(b.button.hidden, false);
        assert.equal(b.button.attributes['aria-pressed'], String(dark));
    }
});
test('una elección guardada prevalece sobre el dispositivo y sobre sus cambios', () => {
    const b = browser({ stored: 'claro', dark: true });
    b.load(); b.system(true);
    assert.equal(b.root.dataset.tema, 'claro');
    b.click(); b.system(false);
    assert.equal(b.root.dataset.tema, 'oscuro');
    assert.equal(b.values.get('subastaya.apariencia'), 'oscuro');
    assert.equal(b.button.attributes['aria-pressed'], 'true');
    assert.equal(b.label.textContent, 'Oscuro');
    const nextPage = browser({ stored: b.values.get('subastaya.apariencia') });
    assert.equal(nextPage.root.dataset.tema, 'oscuro');
});
test('se pueden elegir ambos modos sin navegar', () => {
    const b = browser(); b.load(); b.click(); b.click();
    assert.equal(b.root.dataset.tema, 'claro');
    assert.equal(b.values.get('subastaya.apariencia'), 'claro');
    assert.equal(b.button.title, 'Cambiar a modo oscuro');
});
test('el almacenamiento bloqueado no impide cambiar de tema', () => {
    const b = browser({ blocked: true, dark: true }); b.load();
    assert.equal(b.root.dataset.tema, 'oscuro'); b.click();
    assert.equal(b.root.dataset.tema, 'claro');
    b.system(true);
    assert.equal(b.root.dataset.tema, 'claro');
});
test('sin elección manual se siguen los cambios del sistema', () => {
    const b = browser(); b.load(); b.system(true);
    assert.equal(b.root.dataset.tema, 'oscuro');
    b.system(false); assert.equal(b.root.dataset.tema, 'claro');
});
test('las preferencias inválidas se ignoran y las otras pestañas se sincronizan', () => {
    const b = browser({ stored: 'valor-invalido', dark: true }); b.load();
    assert.equal(b.root.dataset.tema, 'oscuro'); b.storage('claro');
    assert.equal(b.root.dataset.tema, 'claro'); b.storage(null);
    assert.equal(b.root.dataset.tema, 'oscuro');
});

function declarations(css) {
    return Object.fromEntries([...css.matchAll(/--([\w-]+)\s*:\s*(#[\da-f]+)/gi)].map(m => [m[1], m[2]]));
}
function luminance(hex) {
    const values = hex.slice(1).match(/../g).map(x => parseInt(x, 16) / 255).map(x => x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4);
    return values[0] * .2126 + values[1] * .7152 + values[2] * .0722;
}
function contrast(a, b) { const x = luminance(a), y = luminance(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); }
test('las parejas de texto, controles y gráficos cumplen sus contrastes en ambos modos', () => {
    const css = readFileSync(resolve(root, 'public/css/apariencia.css'), 'utf8');
    const base = readFileSync(resolve(root, 'public/css/app.css'), 'utf8').match(/:root\s*\{([^}]+)/)[1];
    const light = { ...declarations(base), ...declarations(css.match(/:root\s*\{([^}]+)/)[1]) };
    const dark = { ...light, ...declarations(css.match(/html\[data-tema="oscuro"\]\s*\{([^}]+)/)[1]) };
    for (const [mode, p] of [['claro', light], ['oscuro', dark]]) {
        for (const background of ['surface', 'paper', 'info-surface']) {
            for (const foreground of ['ink', 'navy', 'muted', 'blue']) {
                assert.ok(contrast(p[foreground], p[background]) >= 4.5, `${mode}: ${foreground} sobre ${background}`);
            }
            assert.ok(contrast(p['control-border'], p[background]) >= 3, `${mode}: borde sobre ${background}`);
            assert.ok(contrast(p.focus, p[background]) >= 3, `${mode}: foco sobre ${background}`);
        }
        for (const [fg, bg] of [['green', 'success-surface'], ['danger', 'danger-surface'], ['warning', 'warning-surface']]) {
            assert.ok(contrast(p[fg], p[bg]) >= 4.5, `${mode}: ${fg}`);
        }
        assert.ok(contrast('#ffffff', p['action-bg']) >= 4.5, `${mode}: botón principal`);
        for (let i = 1; i <= 12; i++) assert.ok(contrast(p[`chart-${i}`], p.surface) >= 3, `${mode}: gráfico ${i}`);
    }
});
