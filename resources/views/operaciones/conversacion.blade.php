<section id="conversacion" data-apartado class="ops-block ops-chat" data-chat-interfaz data-chat data-actor="{{ auth()->id() }}" data-csrf="{{ csrf_token() }}" data-url="{{ route('operaciones.mensajes',$op->id_operacion) }}" data-leer-url="{{ route('operaciones.accion',[$op->id_operacion,'leer']) }}" data-pagina="{{ $mensajes->currentPage() }}">
    <header class="chat-heading">
        <span class="account-avatar" aria-hidden="true">@if($interlocutor->imagen)<img src="{{ $interlocutor->imagen->url }}" alt="">@else{{ mb_strtoupper(mb_substr($interlocutor->nombre,0,1)) }}@endif</span>
        <div><h2>{{ $interlocutor->nombre }}</h2><p>{{ $comprador ? 'Vendedor' : 'Comprador' }} · Conversación privada</p></div>
    </header>
    <p class="chat-privacy">Solo ustedes pueden conversar. Un administrador asignado podrá consultar el historial si hay una incidencia activa.</p>
    <ol class="ops-messages" data-mensajes aria-label="Mensajes de la conversación">@foreach(collect($mensajes->items())->reverse() as $m)@include('operaciones.mensaje')@endforeach</ol>
    <x-paginacion :elementos="$mensajes" />
    <a data-chat-anteriores href="{{ route('operaciones.show',[$op->id_operacion,'mensajes'=>2]) }}#conversacion" @if($mensajes->total()<=15 || $mensajes->currentPage()!==1) hidden @endif>Consultar mensajes anteriores</a>
    @if($op->estado!=='Reembolsada')
        <form method="POST" enctype="multipart/form-data" class="chat-composer" data-mensaje-form action="{{ route('operaciones.accion',[$op->id_operacion,'mensaje']) }}">
            @csrf<input name="referencia" type="hidden" value="{{ $referencia }}">
            <div class="chat-preview" data-chat-preview hidden><div data-chat-preview-media></div><button type="button" data-chat-quitar>Quitar adjunto</button></div>
            <label class="sr-only" for="texto">Mensaje</label><textarea id="texto" name="texto" maxlength="2000" rows="2" placeholder="Escribe un mensaje…">{{ old('texto') }}</textarea>
            <div class="chat-compose-actions">
                <label class="chat-tool" for="archivo-chat" aria-label="Adjuntar foto o audio" title="Adjuntar foto o audio"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m21 11-8 8a6 6 0 0 1-8-8l9-9a4 4 0 0 1 6 6l-9 9a2 2 0 0 1-3-3l8-8"/></svg><span>Adjuntar</span></label>
                <input id="archivo-chat" class="chat-file" type="file" name="archivo" accept="image/jpeg,image/png,image/webp,audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/webm,.m4a,.webm">
                <button type="button" class="chat-tool" data-chat-grabar aria-label="Grabar audio"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2M12 19v3M8 22h8"/></svg><span data-grabar-texto>Grabar</span></button>
                <button type="button" class="chat-tool" data-chat-cancelar-audio hidden>Descartar grabación</button>
                <button type="submit" class="button primary" data-chat-enviar>Enviar</button>
            </div>
            <p class="chat-help">Texto hasta 2.000 caracteres · Fotos o audios hasta 5 MB.</p>
            <p class="chat-feedback" role="status" data-chat-envio></p>
        </form>
    @else<p>Conversación cerrada. El historial se conserva.</p>@endif
    @include('operaciones.visor-foto')
</section>
