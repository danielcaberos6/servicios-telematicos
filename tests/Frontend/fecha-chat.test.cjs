const {test}=require('node:test');
const assert=require('node:assert/strict');
const {formatear}=require('../../public/js/fecha-chat.js');

test('Hoy y Ayer siguen los días de Bolivia aunque en UTC ya sea otro día',()=>{
    const ahora=new Date('2026-11-03T03:30:00Z');
    assert.equal(formatear('2026-11-03T00:44:00Z',ahora),'Hoy 20:44');
    assert.equal(formatear('2026-11-02T03:30:00Z',ahora),'Ayer 23:30');
});
test('desde dos días atrás muestra día, mes y año de dos dígitos',()=>{
    assert.equal(formatear('2026-10-31T16:00:00Z',new Date('2026-11-03T03:30:00Z')),'31/10/26 12:00');
});
test('el cambio de año y la medianoche local conservan las etiquetas correctas',()=>{
    assert.equal(formatear('2026-12-31T23:59:00-04:00',new Date('2027-01-01T04:01:00Z')),'Ayer 23:59');
    assert.equal(formatear('2027-01-01T04:00:00Z',new Date('2027-01-01T04:01:00Z')),'Hoy 00:00');
    assert.equal(formatear('2026-12-30T16:00:00Z',new Date('2027-01-01T04:01:00Z')),'30/12/26 12:00');
});
