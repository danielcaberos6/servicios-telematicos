const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {readFileSync}=require('node:fs');
const app=readFileSync(require('node:path').resolve(__dirname,'../../public/js/app.js'),'utf8');
const source=app.slice(app.indexOf('const formatBoliviaDateTime'),app.indexOf('document.querySelectorAll("[data-duration-presets]")'));

function entorno() {
    const handlers={},error={textContent:''};
    let elapsed=0,tick,min='2026-11-02T18:01',writes=0,validity='',reported=0;
    const input={dataset:{serverNow:Date.parse('2026-11-02T22:00:00Z')},value:'',partial:'',
        get min(){return min;},set min(value){min=value;writes++;this.partial='';},
        setCustomValidity:value=>validity=value,setAttribute(){},addEventListener:(name,fn)=>handlers[name]=fn,
        checkValidity:()=>!validity,reportValidity(){reported++;},form:{addEventListener:(name,fn)=>handlers.submit=fn}};
    const document={activeElement:null,querySelectorAll:()=>[input],getElementById:()=>error};
    const window={addEventListener:(name,fn)=>handlers[name]=fn,setInterval:fn=>tick=fn};
    vm.runInNewContext(source,{document,window,performance:{now:()=>elapsed},Date,Intl,Object,Number});
    return {input,document,handlers,error,tick,get writes(){return writes;},get validity(){return validity;},get reported(){return reported;},set elapsed(value){elapsed=value;}};
}

test('escribir segmentos incompletos no los borra, ni siquiera al avanzar el minuto',()=>{
    const e=entorno();e.document.activeElement=e.input;e.input.partial='03';e.handlers.input();
    e.elapsed=120000;e.tick();e.handlers.pageshow();
    assert.equal(e.input.partial,'03');assert.equal(e.writes,0);assert.equal(e.error.textContent,'');
});
test('el mínimo no se reasigna si no cambió y se actualiza al terminar de editar',()=>{
    const e=entorno();e.tick();e.tick();assert.equal(e.writes,0);
    e.input.value='2026-11-08T15:30';e.elapsed=120000;e.handlers.blur();
    assert.equal(e.writes,1);assert.equal(e.input.value,'2026-11-08T15:30');assert.equal(e.validity,'');
    e.tick();assert.equal(e.writes,1);
});
test('el envío verifica la hora actual aunque el campo tenga el foco y un mínimo anterior',()=>{
    const e=entorno();e.document.activeElement=e.input;e.input.value='2026-11-02T18:01';e.elapsed=120000;
    let prevented=false;e.handlers.submit({preventDefault(){prevented=true;}});
    assert.equal(prevented,true);assert.equal(e.reported,1);assert.match(e.validity,/posteriores/);assert.equal(e.writes,0);
});
test('corregir una fecha elimina el error anterior y valida la nueva fecha al terminar',()=>{
    const e=entorno();e.input.value='2026-11-01T18:00';e.handlers.blur();assert.match(e.validity,/posteriores/);
    e.document.activeElement=e.input;e.input.value='';e.input.partial='08';e.handlers.input();
    assert.equal(e.validity,'');assert.equal(e.input.partial,'08');
    e.input.value='2026-11-08T15:30';e.handlers.change();assert.equal(e.validity,'');
});
