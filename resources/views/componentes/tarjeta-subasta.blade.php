@props(['subasta', 'gestionar' => false])
<article class="auction-card">
    <a href="{{ route('subastas.show', $subasta) }}" class="card-image" tabindex="-1" aria-hidden="true">
        @if ($subasta->imagenes->isNotEmpty())
        <img src="{{ $subasta->imagenes->first()->url }}" alt="" loading="lazy">@else<div
                class="image-placeholder"><x-icono nombre="image" /><span>Sin fotografía</span></div>
        @endif
        <span class="condition">{{ $subasta->estado_articulo }}</span>
        @if ($gestionar)
            <span class="auction-state">{{ $subasta->publicada() ? $subasta->estado : 'Sin publicar' }}</span>
        @endif
    </a>
    <div class="card-body">
        @if ($gestionar)
            <p class="revision-status">Solicitud: <x-estado-revision :subasta="$subasta" /></p>
            @if ($subasta->estado_revision === 'Rechazada')
                <p class="revision-reason"><strong>Motivo del rechazo:</strong> {{ $subasta->motivo_rechazo }}</p>
            @elseif ($subasta->estado_revision === 'En revisión')
                <p class="revision-reason">Un administrador revisará tu solicitud. Mientras tanto no aparece en Explorar subastas.</p>
            @endif
        @endif
        <p class="eyebrow">{{ $subasta->categoria?->nombre_categoria ?? 'Sin categoría' }}</p>
        <h3><a href="{{ route('subastas.show', $subasta) }}">{{ $subasta->titulo }}</a></h3>
        <p class="card-description">{{ Str::limit($subasta->descripcion, 75) }}</p>
        <p class="location"><x-icono nombre="pin" />{{ Str::limit($subasta->ubicacion, 42) }}@isset($subasta->distancia)<span class="distance"> · a {{ number_format($subasta->distancia, 1, ',', '.') }} km</span>@endisset</p>
        <div class="card-price">
            <div><small>{{ $subasta->pujas_count ? 'Oferta actual' : 'Monto inicial' }}</small><strong><span>Bs</span>
                    {{ number_format($subasta->pujas_max_monto ?? $subasta->monto_inicial, 2, ',', '.') }}</strong>
            </div><span class="bid-count">{{ $subasta->pujas_count }}
                {{ $subasta->pujas_count === 1 ? 'oferta' : 'ofertas' }}</span>
        </div>
        <div class="card-bottom"><span><x-icono
                    nombre="clock" />{{ $subasta->estado === 'Activa' ? 'Cierra ' . $subasta->fecha_fin->diffForHumans() : $subasta->estado }}</span><a
                href="{{ route('subastas.show', $subasta) }}" aria-label="Ver {{ $subasta->titulo }}">Ver detalle</a></div>
        @if ($gestionar)
            <div class="card-actions">
                @if (!$subasta->pujas_count && $subasta->editable())
                    <a class="text-link" href="{{ route('subastas.edit', $subasta) }}"><x-icono
                            nombre="edit" />{{ $subasta->estado_revision === 'Rechazada' ? 'Corregir y reenviar' : 'Editar' }}</a>
                @endif
                @if (!$subasta->pujas_count)
                    <form method="post" action="{{ route('subastas.destroy', $subasta) }}"
                        data-confirm="¿Eliminar esta subasta y sus imágenes? Esta acción no se puede deshacer.">@csrf
                        @method('DELETE')<button type="submit" class="danger-link"><x-icono
                            nombre="trash" />Eliminar</button></form>@else<span class="muted">Con ofertas · edición
                        cerrada</span>
                @endif
            </div>
        @endif
    </div>
</article>
