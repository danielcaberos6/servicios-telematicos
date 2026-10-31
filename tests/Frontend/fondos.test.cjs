const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(require('node:path').resolve(__dirname,'../../public/js/fondos.js'),'utf8');

function entorno() {
    const handlers = {}, values = ['disponible','reservado','total'].map(clave=>({dataset:{saldo:clave},textContent:'0,00'}));
    const aviso = {textContent:''}; let tick, requests=0, interval;
    const pagina = {dataset:{saldoUrl:'/mi-billetera/saldo'},querySelector:()=>aviso,querySelectorAll:()=>values};
    const document = {hidden:false,querySelector:()=>pagina,addEventListener:(k,f)=>handlers[k]=f};
    const window = {setInterval:(f,t)=>{tick=f;interval=t;},addEventListener:(k,f)=>handlers[k]=f};
    let response = {ok:true,json:async()=>({disponible:'400.00',reservado:'100.00',total:'500.00'})};
    let options;
    const fetch = async (url,o)=>{ requests++; options=o; if(response instanceof Error) throw response; return response; };
    vm.runInNewContext(source,{document,window,fetch,Intl,Number,AbortSignal});
    return {document,values,aviso,handlers,tick,get requests(){return requests;},get interval(){return interval;},get options(){return options;},set response(v){response=v;}};
}
test('actualiza saldos mediante GET privado y consulta antes de cinco segundos',async()=>{
    const e=entorno(); assert.ok(e.interval<5000); await e.tick();
    assert.match(e.values[0].textContent,/400/); assert.match(e.values[1].textContent,/100/);
    assert.equal(e.options.cache,'no-store'); assert.equal(e.options.credentials,'same-origin'); assert.equal(e.options.method,undefined);
});
test('un error conserva el último saldo y avisa sin crear operaciones',async()=>{
    const e=entorno(); await e.tick(); const antes=e.values.map(x=>x.textContent);
    e.response=new Error('sin conexión'); await e.tick(); assert.deepEqual(e.values.map(x=>x.textContent),antes); assert.match(e.aviso.textContent,/No pudimos/);
});
test('no consulta mientras la pestaña esté oculta y actualiza al regresar',async()=>{
    const e=entorno(); e.document.hidden=true; await e.tick(); assert.equal(e.requests,0);
    e.document.hidden=false; await e.handlers.visibilitychange(); assert.equal(e.requests,1);
});
