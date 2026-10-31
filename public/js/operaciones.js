(() => {
    'use strict';
    const chat = document.querySelector('[data-chat]');
    if (!chat) return;
    const list = chat.querySelector('[data-mensajes]');
    const status = chat.querySelector('[data-chat-estado]');
    const csrf = chat.dataset.csrf;
    let busy = false;
    let seen = 0;
    async function markRead() {
        if (document.hidden || Number(chat.dataset.pagina)!==1 || !isVisible()) return;
        const newest=list.querySelectorAll('[data-mensaje]');
        const lastNode=newest[newest.length-1];
        if (!lastNode) return;
        const bounds=list.getBoundingClientRect();
        const edge=lastNode.getBoundingClientRect();
        if (edge.bottom>Math.min(bounds.bottom,window.innerHeight)+1 || edge.bottom<=Math.max(bounds.top,0)) return;
        const ids = Array.from(list.querySelectorAll('[data-mensaje]'), el => Number(el.dataset.mensaje));
        const last = Math.max(0, ...ids);
        if (last <= seen) return;
        const result = await fetch(chat.dataset.leerUrl, {method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({hasta:last}), signal:AbortSignal.timeout(4000)});
        if (result.ok) seen = last;
    }
    function isVisible() {
        const rect = list.getBoundingClientRect();
        return rect.top < window.innerHeight && rect.bottom > 0;
    }
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            if (Number(chat.dataset.pagina) === 1) {
                const result = await fetch(chat.dataset.url, {headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(4000)});
                if (!result.ok) throw new Error('fetch');
                const page = await result.json();
                const older=chat.querySelector('[data-chat-anteriores]');
                if (older) older.hidden=page.total<=15;
                const messages = page.data.slice().reverse();
                // Reemplazar la ventana de 15 evita duplicados y mantiene el DOM acotado.
                const nodes = messages.map(message => {
                    const li=document.createElement('li'); li.dataset.mensaje=message.id_mensaje;
                    const author=document.createElement('strong'); author.textContent=message.autor;
                    const time=document.createElement('time'); time.textContent=new Intl.DateTimeFormat('es-BO',{timeZone:'America/La_Paz',dateStyle:'short',timeStyle:'short'}).format(new Date(message.fecha_creacion));
                    const text=document.createElement('p'); text.textContent=message.texto;
                    li.append(author,time,text); return li;
                });
                const atEnd=list.scrollHeight-list.scrollTop-list.clientHeight<40;
                const current=Array.from(list.querySelectorAll('[data-mensaje]'),el=>Number(el.dataset.mensaje)).join(',');
                const incoming=messages.map(message=>Number(message.id_mensaje)).join(',');
                if (current!==incoming) list.replaceChildren(...nodes);
                if (atEnd) list.scrollTop=list.scrollHeight;
                if(status.textContent!=='Mensajes actualizados.') status.textContent='Mensajes actualizados.';
            }
            await markRead();
        } catch (_) { status.textContent='No se pudo actualizar. Se reintentará automáticamente; tus mensajes se conservan.'; }
        finally { busy=false; }
    }
    // La paginación usa la página del servidor; al aparecer una página nueva se ofrece recargar el historial.
    setInterval(refresh, 3000);
    document.addEventListener('visibilitychange',refresh);
    refresh();

    const root=document.querySelector('[data-operacion]');
    if (!root?.dataset.estadoUrl) return;
    const notice=root.querySelector('[data-operacion-aviso]');
    const reload=root.querySelector('[data-operacion-actualizar]');
    let signature=root.dataset.firma;
    let checking=false;
    let stateError=false;
    function hasDraft() {
        return Array.from(root.querySelectorAll('form')).some(form=>form.method.toLowerCase()!=='get' && Array.from(form.elements).some(field=>{
            if (['hidden','submit','button'].includes(field.type)) return false;
            if (['checkbox','radio'].includes(field.type)) return field.checked;
            if (!field.name) return false;
            return String(field.value || '').trim()!=='';
        }));
    }
    reload.addEventListener('click',()=>window.location.reload());
    async function refreshState() {
        if (document.hidden || checking) return;
        checking=true;
        try {
            const response=await fetch(root.dataset.estadoUrl,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(4000)});
            if(!response.ok) throw new Error('estado');
            const data=await response.json();
            if(stateError) {
                notice.hidden=reload.hidden;
                if(!reload.hidden) notice.textContent='La operación cambió. Conserva o envía tu borrador antes de actualizar los demás detalles.';
                stateError=false;
            }
            if (data.firma===signature) return;
            signature=data.firma;
            root.querySelector('[data-operacion-estado]').textContent=data.estado;
            for(const key of ['comprador_confirmo','vendedor_confirmo']) {
                root.querySelector(`[data-confirmacion="${key}"]`).textContent=data[key] ? new Intl.DateTimeFormat('es-BO',{timeZone:'America/La_Paz',dateStyle:'short',timeStyle:'short'}).format(new Date(data[key])) : 'Pendiente';
            }
            const funds=root.querySelector('[data-entrega-fondos]');
            if(funds) funds.textContent=data.estado==='Completada' ? 'Pago registrado y fondos acreditados al vendedor.' : data.estado==='Reembolsada' ? 'Reserva devuelta al comprador, sin comisión.' : 'Los fondos siguen retenidos. Se paga cuando ambos confirman la entrega y no hay incidencias abiertas.';
            if (!hasDraft()) { window.location.reload(); return; }
            if (['Completada','Reembolsada'].includes(data.estado)) {
                root.querySelectorAll('#encuentro form button,#incidencias form button,#entrega form button').forEach(button=>button.disabled=true);
                if(data.estado==='Reembolsada')root.querySelectorAll('[data-mensaje-form] button').forEach(button=>button.disabled=true);
            }
            notice.textContent='La operación cambió. El estado ya está actualizado; conserva o envía tu borrador antes de actualizar los demás detalles.';
            notice.hidden=false;reload.hidden=false;
        } catch (_) {
            stateError=true;
            notice.textContent='No se pudo comprobar la entrega. Se reintentará automáticamente; puedes conservar tu borrador.';
            notice.hidden=false;
        } finally {checking=false;}
    }
    setInterval(refreshState,3000);
    document.addEventListener('visibilitychange',refreshState);
    refreshState();
})();
