@extends('plantillas.principal')
@section('title', 'Mis participaciones')
@push('styles')<link rel="stylesheet" href="{{ asset('css/participaciones.css') }}">@endpush
@section('content')
<section class="container section">
    <p class="eyebrow">TUS OFERTAS</p>
    <h1 class="page-title">Mis participaciones</h1>
    <p class="muted">Sigue las subastas en las que participas. Cada artículo aparece una sola vez, aunque hayas ofertado varias veces.</p>
    <p><a href="{{ route('operaciones.index') }}">Coordinar entregas en Mis operaciones</a></p>
    <p>{{ $subastas->total() }} {{ $subastas->total() === 1 ? 'participación' : 'participaciones' }} · {{ \App\Services\ParticipacionService::APARTADOS[request('apartado', '')] }}</p>
    <ul class="participation-list">
        @forelse ($subastas as $subasta)
            <li class="participation-row">
                @if ($subasta->imagenes->first())<img class="participation-photo" src="{{ $subasta->imagenes->first()->url }}" alt="{{ $subasta->titulo }}" loading="lazy">@endif
                <div class="participation-info">
                    <h2><a href="{{ route('subastas.show', $subasta) }}">{{ $subasta->titulo }}</a></h2>
                    <p class="participation-status">{{ \App\Services\ParticipacionService::RESULTADOS[$subasta->resultado] }}</p>
                    <dl class="participation-amounts">
                        <div><dt>Tu mayor oferta</dt><dd>Bs {{ number_format($subasta->oferta_propia, 2, ',', '.') }}</dd></div>
                        <div><dt>Oferta vigente</dt><dd>Bs {{ number_format($subasta->oferta_actual, 2, ',', '.') }}</dd></div>
                        <div><dt>Cierre de la subasta</dt><dd><time datetime="{{ $subasta->fecha_fin->toIso8601String() }}">{{ $subasta->fecha_fin->timezone('America/La_Paz')->format('d/m/Y H:i') }}</time></dd></div>
                    </dl>
                    @if ($subasta->resultado === 'ganadas')<p class="muted">Ganaste esta subasta. El resultado del cierre no confirma la entrega del artículo.</p>@endif
                    <a class="text-link" href="{{ route('subastas.show', $subasta) }}">Ver subasta</a>
                    @if($subasta->resultado === 'ganadas')
                        @if($subasta->id_operacion)
                            <p class="muted">Entrega: {{ $subasta->estado_entrega }}.</p>
                            <div class="participation-actions"><a class="button secondary" href="{{ route('operaciones.show',$subasta->id_operacion) }}#conversacion">Conversación</a><a class="button secondary" href="{{ route('operaciones.show',$subasta->id_operacion) }}#entrega">Seguimiento de entrega</a></div>
                        @elseif($subasta->estado_subasta === 'Finalizada')
                            <form method="POST" action="{{ route('operaciones.abrir',$subasta->id_subasta) }}">@csrf<button class="button secondary" type="submit">Coordinar entrega</button></form>
                        @else
                            <p class="muted">Se está procesando el cierre. La coordinación estará disponible cuando termine.</p>
                        @endif
                    @endif
                </div>
            </li>
        @empty
            <li class="empty-state"><h2>{{ request('apartado') ? 'No tienes participaciones en este apartado' : 'Todavía no participaste en una subasta' }}</h2><p>Tus ofertas aparecerán aquí para que puedas seguir su resultado.</p><a class="button primary" href="{{ route('subastas.index') }}">Explorar subastas</a></li>
        @endforelse
    </ul>
    <x-paginacion :elementos="$subastas" />
</section>
@endsection
