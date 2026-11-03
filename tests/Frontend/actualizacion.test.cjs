const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {readFileSync}=require('node:fs');
const source=readFileSync(require('node:path').resolve(__dirname,'../../public/js/actualizacion.js'),'utf8');

function entorno() {
    const status={textContent:''},handlers={};
    let tick,requests=0,updates=0,error=false,received=[];
    const field={type:'number',value:'',defaultValue:''};
    const region={dataset:{liveRegion:'oferta'},innerHTML:'original',isConnected:true,
        contains:()=>false,querySelectorAll:()=>[field],
        replaceChildren(...nodes){updates++;this.innerHTML=nodes.join('');}};
    const document={hidden:false,activeElement:null,querySelectorAll:s=>s==='[data-live-region]'?[region]:[status],
        importNode:n=>n,addEventListener:(k,f)=>handlers[k]=f};
    const window={location:{href:'http://localhost/subastas/1'},setInterval:(f)=>tick=f,addEventListener:(k,f)=>handlers[k]=f,setTimeout(){}};
    const fetch=async()=>{requests++;if(error)throw new Error('offline');return {ok:true,text:async()=>''};};
    class DOMParser {parseFromString(){return {querySelectorAll:()=>received};}}
    vm.runInNewContext(source,{document,window,fetch,DOMParser,AbortSignal,WeakMap});
    return {document,region,field,status,handlers,tick,get requests(){return requests;},get updates(){return updates;},
        set html(value){received=[{dataset:{liveRegion:'oferta'},innerHTML:value,childNodes:[value]}];},set error(value){error=value;},empty(){received=[];}};
}

test('renueva solo la región modificada y conserva sus nodos si los datos no cambian',async()=>{
    const e=entorno();e.html='original';await e.tick();assert.equal(e.updates,0);
    e.html='nueva oferta';await e.tick();assert.equal(e.region.innerHTML,'nueva oferta');assert.equal(e.updates,1);
    await e.tick();assert.equal(e.updates,1);
});
test('conserva una oferta escrita y actualiza cuando se libera el borrador',async()=>{
    const e=entorno();e.field.value='505';e.html='oferta 510';await e.tick();
    assert.equal(e.updates,0);assert.equal(e.field.value,'505');assert.match(e.status.textContent,/Conservamos/);
    e.field.value='';await e.tick();assert.equal(e.updates,1);
});
test('sin conexión o ante una respuesta de sesión expirada conserva los datos y se recupera',async()=>{
    const e=entorno();e.error=true;await e.tick();assert.equal(e.updates,0);assert.match(e.status.textContent,/Sin conexión/);
    e.error=false;e.empty();await e.tick();assert.equal(e.updates,0);
    e.html='recuperado';await e.tick();assert.equal(e.region.innerHTML,'recuperado');assert.match(e.status.textContent,/actualizados/);
});
test('no consulta con la pestaña oculta ni durante un envío',async()=>{
    const e=entorno();e.document.hidden=true;await e.tick();assert.equal(e.requests,0);
    e.document.hidden=false;e.handlers.submit();await e.tick();assert.equal(e.requests,0);
});
