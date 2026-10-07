@props(['subasta', 'manage' => false])
<article class="auction-card">
    <a href="{{ route('auctions.show', $subasta) }}" class="card-image" tabindex="-1" aria-hidden="true">
        @if ($subasta->imagenes->isNotEmpty())
        <img src="{{ $subasta->imagenes->first()->url }}" alt="" loading="lazy">@else<div
                class="image-placeholder"><x-icon name="image" /><span>Sin fotografía</span></div>
        @endif
        <span class="condition">{{ $subasta->estado_articulo }}</span>
        @if ($manage)
            <span class="auction-state">{{ $subasta->estado }}</span>
        @endif
    </a>
    <div class="card-body">
        <p class="eyebrow">{{ $subasta->categoria?->nombre_categoria ?? 'Sin categoría' }}</p>
        <h3><a href="{{ route('auctions.show', $subasta) }}">{{ $subasta->titulo }}</a></h3>
        <p class="card-description">{{ Str::limit($subasta->descripcion, 75) }}</p>
        <p class="location"><x-icon name="pin" />{{ Str::limit($subasta->ubicacion, 42) }}</p>
        <div class="card-price">
            <div><small>{{ $subasta->pujas_count ? 'Oferta actual' : 'Monto inicial' }}</small><strong><span>Bs</span>
                    {{ number_format($subasta->pujas_max_monto ?? $subasta->monto_inicial, 2, ',', '.') }}</strong>
            </div><span class="bid-count">{{ $subasta->pujas_count }}
                {{ $subasta->pujas_count === 1 ? 'oferta' : 'ofertas' }}</span>
        </div>
        <div class="card-bottom"><span><x-icon
                    name="clock" />{{ $subasta->estado === 'Activa' ? 'Cierra ' . $subasta->fecha_fin->diffForHumans() : $subasta->estado }}</span><a
                href="{{ route('auctions.show', $subasta) }}" aria-label="Ver {{ $subasta->titulo }}"><x-icon
                    name="arrow" /></a></div>
        @if ($manage)
            <div class="card-actions">
                @if (!$subasta->pujas_count && $subasta->estado === 'Activa')
                    <a class="text-link" href="{{ route('auctions.edit', $subasta) }}"><x-icon
                            name="edit" />Editar</a>
                @endif
                @if (!$subasta->pujas_count)
                    <form method="post" action="{{ route('auctions.destroy', $subasta) }}"
                        data-confirm="¿Eliminar esta subasta y sus imágenes? Esta acción no se puede deshacer.">@csrf
                        @method('DELETE')<button type="submit" class="danger-link"><x-icon
                            name="trash" />Eliminar</button></form>@else<span class="muted">Con ofertas · edición
                        cerrada</span>
                @endif
            </div>
        @endif
    </div>
</article>
