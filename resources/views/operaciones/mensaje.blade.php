<li data-mensaje="{{ $m->id_mensaje }}" @class(['chat-bubble','chat-bubble-own'=> $m->id_autor===auth()->id()])>
    <strong class="chat-author">{{ $m->id_autor===auth()->id() ? 'Tú' : $m->autor }}</strong>
    @if($m->tipo==='Foto')
        <a class="chat-photo" href="{{ $m->archivo_url }}" data-chat-photo aria-label="Ampliar foto de {{ $m->autor }}"><img src="{{ $m->archivo_url }}" alt="Foto enviada por {{ $m->autor }}" loading="lazy"></a>
    @elseif($m->tipo==='Audio')
        <audio controls preload="none" src="{{ $m->archivo_url }}" aria-label="Audio de {{ $m->autor }}"></audio>
    @endif
    @if($m->texto!=='')<p>{{ $m->texto }}</p>@endif
    @php
        $fechaMensaje = \Carbon\Carbon::parse($m->fecha_creacion)->timezone('America/La_Paz');
        $hoyMensaje = now('America/La_Paz');
        $diaMensaje = $fechaMensaje->isSameDay($hoyMensaje) ? 'Hoy' : ($fechaMensaje->isSameDay($hoyMensaje->copy()->subDay()) ? 'Ayer' : $fechaMensaje->format('d/m/y'));
    @endphp
    <time data-chat-fecha datetime="{{ $fechaMensaje->toIso8601String() }}">{{ $diaMensaje }} {{ $fechaMensaje->format('H:i') }}</time>
</li>
