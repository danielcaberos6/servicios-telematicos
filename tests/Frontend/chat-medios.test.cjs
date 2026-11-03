const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const {readFileSync}=require('node:fs');
const source=readFileSync(require('node:path').resolve(__dirname,'../../public/js/chat-medios.js'),'utf8');

function entorno() {
    const handlers={},events={},windowEvents={},nodes={};let request,stopped=0,mediaRecorder;
    function element(){return {hidden:false,value:'',textContent:'',disabled:false,style:{},children:[],files:[],
        addEventListener(name,fn){(this.events??={})[name]=fn;},setAttribute(){},append(...children){this.children.push(...children);},replaceChildren(){this.children=[];},focus(){}};}
    for(const key of ['[data-chat-visor]','[data-chat-imagen]','[name=archivo]','[name=texto]','[data-chat-enviar]','[data-chat-preview]','[data-chat-preview-media]','[data-chat-envio]','[data-chat-grabar]','[data-grabar-texto]','[data-chat-cancelar-audio]','[data-chat-quitar]','[name=referencia]','[data-chat-cerrar]'])nodes[key]=element();
    const form={dataset:{},action:'/operaciones/1/mensaje',querySelector:s=>nodes[s],addEventListener:(name,fn)=>handlers[name]=fn};
    const chat={dataset:{pagina:'1'},querySelector:s=>s==='[data-mensaje-form]'?form:nodes[s],querySelectorAll:()=>[],addEventListener:(name,fn)=>events[name]=fn};
    const document={hidden:false,querySelectorAll:()=>[chat],createElement:()=>element(),addEventListener:(name,fn)=>events[name]=fn,dispatchEvent(){}};
    class Recorder {static isTypeSupported(){return true;}constructor(){this.state='inactive';this.mimeType='audio/webm';this.events={};mediaRecorder=this;}addEventListener(name,fn){this.events[name]=fn;}start(){this.state='recording';}stop(){this.state='inactive';this.events.dataavailable({data:new Blob(['audio'],{type:this.mimeType})});this.events.stop();}}
    class Transfer {constructor(){this.files=[];this.items={add:file=>this.files.push(file)};}}
    let response={ok:true,json:async()=>({ok:true})};
    const window={MediaRecorder:Recorder,setInterval(){return 1;},clearInterval(){},addEventListener:(name,fn)=>windowEvents[name]=fn,location:{assign(){}}};
    vm.runInNewContext(source,{document,window,navigator:{mediaDevices:{getUserMedia:async()=>({getTracks:()=>[{stop(){stopped++;}}]})}},
        MediaRecorder:Recorder,DataTransfer:Transfer,File,Blob,URL:{createObjectURL:()=>'/preview',revokeObjectURL(){}},performance:{now:()=>0},
        AbortSignal,CustomEvent:class {},crypto:{randomUUID:()=> 'nueva-referencia'},FormData:class {},
        fetch:async(url,options)=>{request={url,options};return response;}});
    return {nodes,form,handlers,events,windowEvents,document,get request(){return request;},get recorder(){return mediaRecorder;},get stopped(){return stopped;},set response(value){response=value;}};
}

test('un envío fallido conserva el borrador y su referencia para reintentar',async()=>{
    const e=entorno();e.nodes['[name=texto]'].value='No perder este mensaje';e.nodes['[name=referencia]'].value='misma-referencia';
    e.response={ok:false,json:async()=>({errors:{texto:['La conversación está cerrada.']}})};
    await e.handlers.submit({preventDefault(){}});assert.equal(e.nodes['[name=texto]'].value,'No perder este mensaje');assert.equal(e.nodes['[name=referencia]'].value,'misma-referencia');
    assert.match(e.nodes['[data-chat-envio]'].textContent,/cerrada/);assert.equal(e.nodes['[data-chat-enviar]'].disabled,false);
    e.response={ok:true,json:async()=>({ok:true})};await e.handlers.submit({preventDefault(){}});
    assert.equal(e.nodes['[name=texto]'].value,'');assert.equal(e.nodes['[name=referencia]'].value,'nueva-referencia');assert.equal(e.request.options.method,'POST');
});
test('grabar no envía automáticamente; detener libera el micrófono y permite previsualizar',async()=>{
    const e=entorno();await e.nodes['[data-chat-grabar]'].events.click();assert.equal(e.form.dataset.grabando,'true');assert.equal(e.request,undefined);
    await e.nodes['[data-chat-grabar]'].events.click();assert.equal(e.stopped,1);assert.equal(e.form.dataset.grabando,'false');
    assert.equal(e.nodes['[name=archivo]'].files[0].type,'audio/webm');assert.equal(e.nodes['[data-chat-preview]'].hidden,false);assert.equal(e.request,undefined);
});
test('descartar y cerrar la página liberan el micrófono sin enviar mensajes',async()=>{
    const e=entorno();await e.nodes['[data-chat-grabar]'].events.click();e.nodes['[data-chat-cancelar-audio]'].events.click();
    assert.equal(e.stopped,1);assert.equal(e.nodes['[name=archivo]'].files.length,0);assert.equal(e.request,undefined);
    await e.nodes['[data-chat-grabar]'].events.click();e.windowEvents.pagehide();assert.equal(e.stopped,2);
});

test('conserva el borrador escrito durante un envio pendiente',async()=>{
 const e=entorno();e.nodes['[name=texto]'].value='Enviado';
 e.response={ok:true,json:async()=>{e.nodes['[name=texto]'].value='Nuevo borrador';return {ok:true};}};
 await e.handlers.submit({preventDefault(){}});
 assert.equal(e.nodes['[name=texto]'].value,'Nuevo borrador');
});
