const { test } = require('node:test');
const assert = require('node:assert/strict');
const { esFormularioEditable, huellaFormulario } = require('../../public/js/cuenta.js');
const input = (name, value = '') => ({ name, value, defaultValue: value, type: 'text', disabled: false });
const form = (elements, method = 'post', logout = false) => ({ elements, method, hasAttribute: () => logout });

test('los cambios de texto se detectan y volver al valor original elimina el cambio', () => {
    const titulo = input('titulo', 'Cámara');
    const f = form([titulo]); const initial = huellaFormulario(f);
    titulo.value = 'Cámara con accesorios'; assert.notEqual(huellaFormulario(f), initial);
    titulo.value = 'Cámara'; assert.equal(huellaFormulario(f), initial);
});
test('la rotación de CSRF y los botones no generan advertencias', () => {
    const token = input('_token', 'uno'), submit = { ...input('accion', 'Guardar'), type: 'submit' };
    const f = form([token, submit, input('_method', 'PUT')]); const initial = huellaFormulario(f);
    token.value = 'dos'; submit.value = 'Procesando'; assert.equal(huellaFormulario(f), initial);
});
test('no se consideran filtros GET ni el propio formulario de salida', () => {
    assert.equal(esFormularioEditable(form([], 'get')), false);
    assert.equal(esFormularioEditable(form([], 'post', true)), false);
    assert.equal(esFormularioEditable(form([], 'post')), true);
});
test('se detectan casillas y opciones de radio sin confundir campos repetidos', () => {
    const check = { ...input('quitar_foto', '1'), type: 'checkbox', checked: false, defaultChecked: false };
    const radio = { ...input('estado', 'Usado'), type: 'radio', checked: true, defaultChecked: true };
    const f = form([check, radio]); const initial = huellaFormulario(f);
    check.checked = true; assert.notEqual(huellaFormulario(f), initial);
    check.checked = false; radio.checked = false; assert.notEqual(huellaFormulario(f), initial);
});
test('cambiar un archivo se detecta aunque tenga el mismo nombre', () => {
    const original = { name: 'articulo.jpg', size: 300, type: 'image/jpeg', lastModified: 100 };
    const file = { ...input('imagenes[]'), type: 'file', files: [original] };
    const f = form([file]); const initial = huellaFormulario(f);
    file.files = [{ ...original, size: 400 }]; assert.notEqual(huellaFormulario(f), initial);
    assert.notEqual(huellaFormulario(f), huellaFormulario(f, true));
    file.files = []; assert.equal(huellaFormulario(f), huellaFormulario(f, true));
});
test('los selectores de formularios dinámicos parten de sus valores predeterminados', () => {
    const select = { ...input('categoria'), multiple: false, options: [{ value: 'a', selected: true, defaultSelected: false }, { value: 'b', selected: false, defaultSelected: false }] };
    const f = form([select]); assert.equal(huellaFormulario(f), huellaFormulario(f, true));
    select.options[0].selected = false; select.options[1].selected = true;
    assert.notEqual(huellaFormulario(f), huellaFormulario(f, true));
});
test('se detectan selecciones múltiples y se ignoran campos deshabilitados', () => {
    const disabled = { ...input('bloqueado', 'a'), disabled: true };
    const multi = { ...input('categorias[]'), multiple: true, options: [{ value: 'a', selected: true, defaultSelected: true }, { value: 'b', selected: false, defaultSelected: false }] };
    const f = form([disabled, multi]); const initial = huellaFormulario(f);
    disabled.value = 'b'; assert.equal(huellaFormulario(f), initial);
    multi.options[1].selected = true; assert.notEqual(huellaFormulario(f), initial);
});
