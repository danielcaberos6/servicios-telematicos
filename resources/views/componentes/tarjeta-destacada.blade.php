@props(['subasta'])
<article class="ini-auction">
    <a class="ini-auction-media" href="{{ route('subastas.show', $subasta) }}" tabindex="-1" aria-hidden="true">
        @if ($subasta->imagenes->isNotEmpty())
            <img src="{{ $subasta->imagenes->first()->url }}" alt="" loading="lazy">
        @else
            <span class="image-placeholder"><x-icono nombre="image" /></span>
        @endif
        <span class="ini-auction-condition">{{ $subasta->estado_articulo }}</span>
    </a>
    <div class="ini-auction-body">
        <p class="ini-auction-category">{{ $subasta->categoria?->nombre_categoria ?? 'Sin categoría' }}</p>
        <h3><a href="{{ route('subastas.show', $subasta) }}">{{ $subasta->titulo }}</a></h3>
        <div class="ini-auction-foot">
            <div>
                <small>{{ $subasta->pujas_count ? 'Oferta actual' : 'Monto inicial' }}</small>
                <strong>Bs {{ number_format($subasta->pujas_max_monto ?? $subasta->monto_inicial, 2, ',', '.') }}</strong>
                <span class="ini-auction-time"><x-icono nombre="clock" />Cierra {{ $subasta->fecha_fin->diffForHumans() }}</span>
            </div>
            <a class="ini-auction-button" href="{{ route('subastas.show', $subasta) }}">Ver subasta</a>
        </div>
    </div>
</article>
