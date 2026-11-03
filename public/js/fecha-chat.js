(function () {
    const partes = fecha => Object.fromEntries(new Intl.DateTimeFormat('es-BO', {
        timeZone: 'America/La_Paz', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(fecha).map(({type,value}) => [type,value]));
    function formatear(valor, ahora = new Date()) {
        const fecha = partes(new Date(valor));
        const hoy = partes(ahora);
        const dia = p => Date.UTC(Number(p.year), Number(p.month)-1, Number(p.day));
        const diferencia = (dia(hoy)-dia(fecha))/86400000;
        const etiqueta = diferencia===0 ? 'Hoy' : diferencia===1 ? 'Ayer' : `${fecha.day}/${fecha.month}/${fecha.year.slice(-2)}`;
        return `${etiqueta} ${fecha.hour}:${fecha.minute}`;
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {formatear};
    if (typeof window === 'undefined') return;
    window.fechaChat = {formatear};
    function actualizar() {
        if (document.hidden) return;
        document.querySelectorAll('time[data-chat-fecha]').forEach(time => {
            const texto = formatear(time.dateTime);
            if (time.textContent !== texto) time.textContent = texto;
        });
    }
    window.setInterval(actualizar, 30000);
    document.addEventListener('visibilitychange', actualizar);
    actualizar();
})();
