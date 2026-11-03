document.querySelectorAll('[data-chat-interfaz]').forEach(chat => {
    const visor=chat.querySelector('[data-chat-visor]'),imagen=chat.querySelector('[data-chat-imagen]');
    let zoom=1;
    chat.addEventListener('click',event=>{
        const foto=event.target.closest('[data-chat-photo]');
        if(!foto || !visor?.showModal) return;
        event.preventDefault();zoom=1;imagen.style.width='100%';imagen.src=foto.href;visor.showModal();
    });
    chat.querySelector('[data-chat-cerrar]')?.addEventListener('click',()=>visor.close());
    chat.querySelectorAll('[data-chat-zoom]').forEach(button=>button.addEventListener('click',()=>{
        zoom=Math.min(4,Math.max(1,zoom+Number(button.dataset.chatZoom)*0.5));imagen.style.width=(zoom*100)+'%';
    }));
    const form=chat.querySelector('[data-mensaje-form]');
    if(!form) return;
    const file=form.querySelector('[name=archivo]'),text=form.querySelector('[name=texto]'),send=form.querySelector('[data-chat-enviar]');
    const preview=form.querySelector('[data-chat-preview]'),media=form.querySelector('[data-chat-preview-media]'),feedback=form.querySelector('[data-chat-envio]');
    const record=form.querySelector('[data-chat-grabar]'),recordText=form.querySelector('[data-grabar-texto]'),cancel=form.querySelector('[data-chat-cancelar-audio]');
    let objectUrl=null,recorder=null,stream=null,timer=null,discard=false,busy=false,started=0,closed=false;
    function clearPreview() {
        if(objectUrl) URL.revokeObjectURL(objectUrl);objectUrl=null;media.replaceChildren();preview.hidden=true;
    }
    function showPreview() {
        clearPreview();feedback.textContent='';const selected=file.files[0];if(!selected)return;
        if(selected.size>5*1024*1024 || selected.size===0) {file.value='';feedback.textContent='El archivo debe tener contenido y pesar hasta 5 MB.';return;}
        const photo=selected.type.startsWith('image/');
        objectUrl=URL.createObjectURL(selected);const element=document.createElement(photo?'img':'audio');
        element.src=objectUrl;if(photo)element.alt='Foto lista para enviar';else{element.controls=true;element.preload='metadata';}
        const name=document.createElement('small');name.textContent=selected.name;media.append(element,name);preview.hidden=false;
    }
    file.addEventListener('change',showPreview);
    form.querySelector('[data-chat-quitar]').addEventListener('click',()=>{file.value='';clearPreview();});
    function release() {window.clearInterval(timer);stream?.getTracks().forEach(track=>track.stop());stream=null;form.dataset.grabando='false';record.setAttribute('aria-pressed','false');record.setAttribute('aria-label','Grabar audio');recordText.textContent='Grabar';cancel.hidden=true;send.disabled=closed;file.disabled=closed;}
    function stopRecording(remove=false) {discard=remove;if(recorder?.state==='recording')recorder.stop();else release();}
    if(!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {record.disabled=true;record.title='La grabación no está disponible en este navegador. Puedes adjuntar un audio.';}
    record.addEventListener('click',async()=>{
        if(recorder?.state==='recording'){stopRecording();return;}
        if(form.dataset.grabando==='true' || busy || closed)return;
        if(file.files.length){feedback.textContent='Quita el adjunto actual antes de grabar.';return;}
        form.dataset.grabando='true';record.disabled=true;discard=false;feedback.textContent='Esperando permiso para usar el micrófono…';
        try {
            stream=await navigator.mediaDevices.getUserMedia({audio:true});
            if(closed || discard){release();return;}
            const mime=['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/mp4'].find(type=>MediaRecorder.isTypeSupported(type));
            recorder=new MediaRecorder(stream,mime?{mimeType:mime}:undefined);const chunks=[];let bytes=0;
            recorder.addEventListener('dataavailable',event=>{if(event.data.size){chunks.push(event.data);bytes+=event.data.size;if(bytes>5*1024*1024){feedback.textContent='La grabación superó 5 MB. Intenta una más corta.';stopRecording(true);}}});
            recorder.addEventListener('error',()=>{feedback.textContent='No se pudo grabar el audio. Puedes volver a intentarlo.';stopRecording(true);});
            recorder.addEventListener('stop',()=>{
                const type=recorder.mimeType.split(';')[0],extension=type.includes('mp4')?'m4a':type.includes('ogg')?'ogg':'webm';
                const guardar=!discard && !closed && bytes>0 && bytes<=5*1024*1024;
                release();recorder=null;
                if(guardar){const transfer=new DataTransfer();transfer.items.add(new File(chunks,'nota-de-voz.'+extension,{type}));file.files=transfer.files;showPreview();feedback.textContent='Audio listo. Puedes escucharlo antes de enviarlo.';}
            });
            recorder.start(500);started=performance.now();record.setAttribute('aria-pressed','true');record.setAttribute('aria-label','Detener grabación');recordText.textContent='Detener';cancel.hidden=false;send.disabled=true;file.disabled=true;
            feedback.textContent='Grabando · 0:00';
            timer=window.setInterval(()=>{const seconds=Math.floor((performance.now()-started)/1000);feedback.textContent='Grabando · '+Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0');if(seconds>=120)stopRecording();},500);
        } catch {release();feedback.textContent='No se pudo acceder al micrófono. Revisa el permiso o adjunta un audio.';}
        finally {record.disabled=closed;}
    });
    cancel.addEventListener('click',()=>{stopRecording(true);feedback.textContent='Grabación descartada.';});
    document.addEventListener('visibilitychange',()=>{if(document.hidden && form.dataset.grabando==='true')stopRecording(recorder?.state!=='recording');});
    document.addEventListener('chat-cerrado',()=>{closed=true;stopRecording(true);record.disabled=true;send.disabled=true;file.disabled=true;feedback.textContent='La conversación se cerró. El historial se conserva.';});
    window.addEventListener('pagehide',()=>{discard=true;stream?.getTracks().forEach(track=>track.stop());window.clearInterval(timer);if(objectUrl)URL.revokeObjectURL(objectUrl);});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy || closed || form.dataset.grabando==='true')return;
        if(!text.value.trim() && !file.files.length){feedback.textContent='Escribe un mensaje o adjunta una foto o un audio.';text.focus();return;}
        busy=true;send.disabled=true;record.disabled=true;feedback.textContent='Enviando…';
        try {
            const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin',redirect:'error',signal:AbortSignal.timeout(30000)});
            const data=await response.json();
            if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):'No se pudo enviar. Conservamos tu mensaje para reintentar.');
            text.value='';file.value='';clearPreview();form.querySelector('[name=referencia]').value=crypto.randomUUID();feedback.textContent='Mensaje enviado.';
            if(Number(chat.dataset.pagina)!==1)window.location.assign(form.action.replace(/\/mensaje$/,'')+'#conversacion');
            else document.dispatchEvent(new CustomEvent('chat-enviado'));
        } catch(error){feedback.textContent=error.message && error.name==='Error'?error.message:'No se pudo enviar. Conservamos tu mensaje para reintentar.';}
        finally {busy=false;send.disabled=closed;record.disabled=closed || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder;}
    });
});
