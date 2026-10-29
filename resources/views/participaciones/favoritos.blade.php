@extends('plantillas.principal')
@section('title', 'Mis favoritos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/participaciones.css') }}">@endpush
@section('content')
<section class="container section">
    <p class="eyebrow">ARTÍCULOS GUARDADOS</p><h1 class="page-title">Mis favoritos</h1>
    <p class="muted">Guardar un artículo no registra una oferta. Te avisaremos cerca del cierre si lo guardaste antes de los últimos 30 minutos.</p>
    <p>{{ $subastas->total() }} {{ $subastas->total() === 1 ? 'favorito disponible' : 'favoritos disponibles' }}</p>
    <ul class="participation-list">
        @forelse ($subastas as $subasta)
            <li class="participation-row">
                @if ($subasta->imagenes->first())<img class="participation-photo" src="{{ $subasta->imagenes->first()->url }}" alt="{{ $subasta->titulo }}" loading="lazy">@endif
                <div class="participation-info">
                    <h2><a href="{{ route('subastas.show', $subasta) }}">{{ $subasta->titulo }}</a></h2>
                    <p>{{ $subasta->estado }} · Cierre: <time datetime="{{ $subasta->fecha_fin->toIso8601String() }}">{{ $subasta->fecha_fin->timezone('America/La_Paz')->format('d/m/Y H:i') }}</time></p>
                    <form method="post" action="{{ route('favoritos.retirar', $subasta) }}">@csrf @method('DELETE')<button type="submit" class="button secondary">Quitar de favoritos</button></form>
                </div>
            </li>
        @empty
            <li class="empty-state"><h2>No tienes favoritos disponibles</h2><p>Guarda artículos desde su detalle para encontrarlos aquí. Las publicaciones retiradas o no disponibles no se muestran.</p><a class="button primary" href="{{ route('subastas.index') }}">Explorar subastas</a></li>
        @endforelse
    </ul>
    <x-paginacion :elementos="$subastas" />
</section>
@endsection
