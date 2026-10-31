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
        if (document.hidden || !chat.matches(':hover') && !chat.contains(document.activeElement) && !isVisible()) return;
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
                list.replaceChildren(...nodes);
                if (atEnd) list.scrollTop=list.scrollHeight;
                status.textContent='Mensajes actualizados.';
            }
            await markRead();
        } catch (_) { status.textContent='No se pudo actualizar. Se reintentará automáticamente; tus mensajes se conservan.'; }
        finally { busy=false; }
    }
    // La paginación usa la página del servidor; al aparecer una página nueva se ofrece recargar el historial.
    setInterval(refresh, 3000);
    document.addEventListener('visibilitychange',refresh);
    refresh();
})();
