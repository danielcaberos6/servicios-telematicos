@extends('plantillas.principal')
@section('title', 'Revisar: ' . $subasta->titulo)
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <a href="{{ route('admin.subastas.index') }}" class="text-link">← Volver a la bandeja de subastas</a>
        <a href="{{ route('subastas.show', $subasta) }}" class="text-link" target="_blank">Ver vista de catálogo ↗</a>
    </div>

    <p class="eyebrow">REVISIÓN ADMINISTRATIVA</p>
    <h1>{{ $subasta->titulo }}</h1>
    <p>
        <strong>Estado:</strong> <x-estado-revision :subasta="$subasta" /> · 
        Enviada el {{ $subasta->fecha_inicio->timezone('America/La_Paz')->format('d/m/Y H:i') }} · 
        Cierre programado: {{ $subasta->fecha_fin->timezone('America/La_Paz')->format('d/m/Y H:i') }}
    </p>

    @if ($subasta->estado_revision === 'Rechazada' && $subasta->motivo_rechazo)
        <div class="panel" style="border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.05); margin-bottom: 1.5rem;">
            <h3 style="color:#ef4444; margin-top:0;">Motivo del rechazo</h3>
            <p>{{ $subasta->motivo_rechazo }}</p>
        </div>
    @endif

    <div class="detail-grid">
        <div>
            <div class="detail-image">
                @if ($subasta->imagenes->isNotEmpty())
                    <img id="main-photo" src="{{ $subasta->imagenes->first()->url }}" alt="{{ $subasta->titulo }}">
                @else
                    <div class="image-placeholder">
                        <x-icono nombre="image" />
                        <span>Esta subasta no incluye fotos</span>
                    </div>
                @endif
            </div>

            @if ($subasta->imagenes->count() > 1)
                <div class="thumbnails" style="margin-top:0.75rem;">
                    @foreach ($subasta->imagenes as $img)
                        <button type="button" data-photo="{{ $img->url }}" aria-label="Fotografía {{ $loop->iteration }}">
                            <img src="{{ $img->url }}" alt="Fotografía {{ $loop->iteration }}">
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="panel description-panel" style="margin-top:1.5rem;">
                <h2>Descripción del artículo</h2>
                <p class="preserve-lines">{{ $subasta->descripcion }}</p>
            </div>
        </div>

        <div class="detail-info">
            <div class="panel">
                <h2>Detalles de la publicación</h2>
                <p><strong>Categoría:</strong> {{ $subasta->categoria?->nombre_categoria ?? 'Sin categoría' }}</p>
                <p><strong>Condición del artículo:</strong> {{ $subasta->estado_articulo }}</p>
                <p><strong>Monto inicial:</strong> <span style="font-size:1.25rem; font-weight:700; color:var(--primary, #0284c7);">Bs {{ number_format($subasta->monto_inicial, 2, ',', '.') }}</span></p>
                <p><strong>Periodo de subasta:</strong> {{ $subasta->fecha_inicio->diffInDays($subasta->fecha_fin) }} días</p>
            </div>

            <div class="panel">
                <h2>Vendedor</h2>
                <p><strong>Nombre:</strong> {{ $subasta->usuario->nombre }} <x-distintivo-identidad :usuario="$subasta->usuario" /></p>
                <p><strong>Correo:</strong> {{ $subasta->usuario->correo }}</p>
                <p><strong>Cuenta:</strong> {{ $subasta->usuario->activo ? 'Activa' : 'Desactivada' }}</p>
            </div>

            <div class="panel meeting-panel">
                <h3><x-icono nombre="pin" /> Punto de encuentro</h3>
                <p>{{ $subasta->ubicacion }}</p>
                @if (! is_null($subasta->latitud) && ! is_null($subasta->longitud))
                    <x-leaflet />
                    <div class="location-map detail-map" data-mapa-detalle data-lat="{{ $subasta->latitud }}"
                        data-lng="{{ $subasta->longitud }}" data-titulo="{{ $subasta->ubicacion }}">
                        <div class="map-canvas" data-mapa-lienzo role="region"
                            aria-label="Mapa del punto de encuentro: {{ $subasta->ubicacion }}"></div>
                        <p class="map-unavailable" data-mapa-aviso hidden>
                            <x-icono nombre="pin" />
                            <span>No pudimos cargar el mapa. Usa la dirección escrita.</span>
                        </p>
                    </div>
                @else
                    <p class="muted">Sin coordenadas en el mapa.</p>
                @endif
            </div>

            @if ($subasta->estado_revision === 'En revisión')
                <section class="panel" aria-labelledby="decision-titulo" style="border: 2px solid var(--border, #e2e8f0);">
                    <h2 id="decision-titulo">Decisión de revisión</h2>
                    <p class="muted">Verifica que las imágenes y la descripción correspondan a un artículo lícito. Al aceptar, la subasta se publicará en el catálogo.</p>

                    <form method="post" action="{{ route('admin.subastas.resolver', $subasta) }}" style="margin-bottom:1.5rem;">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="Aceptada">
                        <button class="button primary" type="submit" style="width:100%; justify-content:center;">
                            <x-icono nombre="check" /> Aceptar y publicar subasta
                        </button>
                    </form>

                    <form method="post" action="{{ route('admin.subastas.resolver', $subasta) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="Rechazada">
                        <label for="motivo" style="font-weight:600; display:block; margin-bottom:0.25rem;">
                            Motivo del rechazo <span style="color:#ef4444;">*</span>
                        </label>
                        <textarea id="motivo" name="motivo" required minlength="1" maxlength="1000" rows="3"
                            placeholder="Indica al vendedor qué debe corregir (ej. fotografías poco claras, precio irreal, artículo prohibido)..."
                            style="width:100%; margin-bottom:0.75rem;">{{ old('motivo') }}</textarea>
                        <button class="button secondary" type="submit" style="width:100%; justify-content:center; color:#ef4444; border-color:#ef4444;">
                            Rechazar solicitud
                        </button>
                    </form>
                </section>
            @else
                <section class="panel" style="background: rgba(0,0,0,0.02);">
                    <h3>Estado resuelto</h3>
                    <p class="muted">Esta solicitud ya fue resuelta como <strong>{{ $subasta->estado_revision }}</strong> y no admite nuevas modificaciones.</p>
                </section>
            @endif
        </div>
    </div>
</section>
@endsection
