const {test}=require('node:test');
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const source=readFileSync(require('node:path').resolve(__dirname,'../../public/js/operaciones.js'),'utf8');

async function entorno(pagina='1') {
    let tick,interval,response={ok:true,json:async()=>({total:17,data:[{id_mensaje:12,autor:'Ana',texto:'<img src=x onerror=alert(1)>',fecha_creacion:'2026-10-31T15:00:00Z'}]})};
    const requests=[],handlers={},status={textContent:''},older={hidden:true};
    const list={children:[],scrollHeight:20,scrollTop:0,clientHeight:20,
        getBoundingClientRect:()=>({top:10,bottom:100}),
        querySelectorAll(){return this.children;},replaceChildren(...nodes){this.children=nodes;}};
    const draft={value:'Un mensaje todavía sin enviar.'};
    const chat={dataset:{pagina,url:'/mensajes',leerUrl:'/leer',csrf:'test-csrf'},matches:()=>false,contains:()=>false,
        querySelector:s=>({'[data-mensajes]':list,'[data-chat-estado]':status,'[data-chat-anteriores]':older}[s])};
    const document={hidden:false,querySelector:()=>chat,addEventListener:(k,f)=>handlers[k]=f,
        createElement(tag){return {tag,dataset:{},textContent:'',children:[],append(...nodes){this.children.push(...nodes);},set innerHTML(_){throw new Error('El texto no debe interpretarse como HTML.');}};}};
    const fetch=async(url,options)=>{
        requests.push({url,options});
        if(url==='/leer')return {ok:true};
        if(response instanceof Error)throw response;
        return response;
    };
    vm.runInNewContext(source,{document,window:{innerHeight:844},fetch,Intl,Date,Number,Math,Array,AbortSignal,setInterval:(f,t)=>{tick=f;interval=t;}});
    await new Promise(setImmediate);
    return {tick,interval,requests,document,list,status,older,draft,handlers,set response(value){response=value;}};
}

test('consulta en menos de cinco segundos, muestra texto seguro y no duplica mensajes',async()=>{
    const e=await entorno();
    assert.ok(e.interval<5000);
    assert.equal(e.list.children.length,1);
    assert.equal(e.list.children[0].children[2].textContent,'<img src=x onerror=alert(1)>');
    assert.equal(e.older.hidden,false);
    await e.tick();assert.equal(e.list.children.length,1);
    const lectura=e.requests.find(r=>r.url==='/leer');
    assert.equal(lectura.options.headers['X-CSRF-TOKEN'],'test-csrf');
    assert.equal(JSON.parse(lectura.options.body).hasta,12);
});

test('un error conserva los mensajes y el borrador; la pestaña oculta no consulta',async()=>{
    const e=await entorno();const before=e.list.children[0];
    e.response=new Error('sin conexión');await e.tick();
    assert.equal(e.list.children[0],before);assert.equal(e.draft.value,'Un mensaje todavía sin enviar.');
    assert.match(e.status.textContent,/reintentará/);
    const count=e.requests.length;e.document.hidden=true;await e.tick();assert.equal(e.requests.length,count);
});

test('la actualización no reemplaza el historial de una página anterior',async()=>{
    const e=await entorno('2');await e.tick();
    assert.equal(e.requests.filter(r=>r.url==='/mensajes').length,0);
});
