const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {readFileSync}=require('node:fs');
const source=readFileSync(require('node:path').resolve(__dirname,'../../public/js/operaciones.js'),'utf8');

async function entorno(draft=false) {
    const ticks=[],notice={hidden:true,textContent:''},reload={hidden:true,addEventListener(){}},badge={textContent:'Pendiente'},funds={textContent:'Retenido'},dates={};
    let count=0,reloads=0,response={firma:'inicial',estado:'Pendiente'};
    const field={name:'texto',type:'textarea',value:draft?'Borrador sin enviar':''};
    const list={querySelectorAll:()=>[],getBoundingClientRect:()=>({top:10,bottom:100})};
    const chat={dataset:{pagina:'2',csrf:'csrf'},querySelector:s=>s==='[data-mensajes]'?list:{textContent:''}};
    const root={dataset:{estadoUrl:'/estado',firma:'inicial'},querySelector:s=>{
        if(s==='[data-operacion-aviso]')return notice;
        if(s==='[data-operacion-actualizar]')return reload;
        if(s==='[data-operacion-estado]')return badge;
        if(s==='[data-entrega-fondos]')return funds;
        return dates[s]??=( {textContent:''} );
    },querySelectorAll:s=>s==='form'?[{method:'post',elements:[field]}]:[]};
    const document={hidden:false,querySelector:s=>s==='[data-chat]'?chat:root,addEventListener(){}};
    const fetch=async()=>{count++;if(response instanceof Error)throw response;return {ok:true,json:async()=>response};};
    vm.runInNewContext(source,{document,window:{innerHeight:844,location:{reload(){reloads++;}}},fetch,Intl,Date,Number,Math,Array,AbortSignal,setInterval:(f)=>ticks.push(f)});
    await new Promise(setImmediate);
    return {document,notice,reload,badge,funds,field,tick:ticks[1],get requests(){return count;},get reloads(){return reloads;},set response(v){response=v;}};
}

test('una confirmación externa actualiza la entrega y renueva la vista si no hay borrador',async()=>{
    const e=await entorno();assert.equal(e.reloads,0);
    e.response={firma:'completada',estado:'Completada',comprador_confirmo:'2026-10-31T15:00:00Z',vendedor_confirmo:'2026-10-31T15:00:00Z'};
    await e.tick();assert.equal(e.reloads,1);assert.equal(e.badge.textContent,'Completada');assert.match(e.funds.textContent,/acreditados/);
});
test('con borrador actualiza el estado sin borrar texto ni recargar',async()=>{
    const e=await entorno(true);e.response={firma:'devuelta',estado:'Reembolsada'};
    await e.tick();assert.equal(e.reloads,0);assert.equal(e.field.value,'Borrador sin enviar');assert.equal(e.reload.hidden,false);assert.match(e.funds.textContent,/sin comisión/);
});
test('sin conexión conserva el estado; una pestaña oculta no consulta',async()=>{
    const e=await entorno(true);e.response=new Error('offline');await e.tick();assert.equal(e.badge.textContent,'Pendiente');assert.equal(e.reloads,0);
    const before=e.requests;e.document.hidden=true;await e.tick();assert.equal(e.requests,before);
});

test('al recuperar conexión desaparece el aviso de error sin perder el aviso de borrador',async()=>{
    const e=await entorno(true);e.response={firma:'nueva',estado:'Completada'};await e.tick();
    e.response=new Error('offline');await e.tick();assert.match(e.notice.textContent,/No se pudo/);
    e.response={firma:'nueva',estado:'Completada'};await e.tick();
    assert.equal(e.notice.hidden,false);assert.doesNotMatch(e.notice.textContent,/No se pudo/);assert.equal(e.field.value,'Borrador sin enviar');
});
