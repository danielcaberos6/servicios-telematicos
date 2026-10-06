@extends('layouts.app')
@section('title', $subasta->titulo)
@section('content')
    <div class="container section">
        <a class="breadcrumb" href="{{ route('auctions.index') }}">← Volver al catálogo</a>
        <div class="detail-grid">
            <div>
                <div class="detail-image">
                    @if ($subasta->imagenes->isNotEmpty())
                    <img id="main-photo" src="{{ $subasta->imagenes->first()->url }}" alt="{{ $subasta->titulo }}">@else
                        <div class="image-placeholder"><x-icon name="image" /><span>Este artículo todavía no tiene
                                fotos</span></div>
                    @endif
                </div>
                @if ($subasta->imagenes->count() > 1)
                    <div class="thumbnails">
                        @foreach ($subasta->imagenes as $img)
                            <button type="button" data-photo="{{ $img->url }}"
                                aria-label="Ver fotografía {{ $loop->iteration }}"><img src="{{ $img->url }}"
                                    alt="Fotografía {{ $loop->iteration }}"></button>
                        @endforeach
                    </div>
                @endif
                <div class="panel description-panel">
                    <h2>Acerca de este artículo</h2>
                    <p class="preserve-lines">{{ $subasta->descripcion }}</p>
                </div>
            </div>
            <div class="detail-info">
                <div class="detail-badges"><span class="tag">{{ $subasta->categoria?->nombre_categoria }}</span><span
                        class="tag {{ $subasta->estado === 'Activa' ? 'green' : '' }}">{{ $subasta->estado }}</span></div>
                <h1>{{ $subasta->titulo }}</h1>
                <p class="muted">{{ $subasta->estado_articulo }} · Publicada el
                    {{ $subasta->fecha_inicio->format('d/m/Y H:i') }}</p>
                <div class="panel bid-panel">
                    <small>{{ $subasta->pujas_count ? 'Oferta actual' : 'Monto inicial' }}</small><strong
                        class="detail-price">Bs
                        {{ number_format($subasta->pujas_max_monto ?? $subasta->monto_inicial, 2, ',', '.') }}</strong>
                    <div class="bid-meta"><span>{{ $subasta->pujas_count }} ofertas</span><span><x-icon
                                name="clock" />{{ $subasta->estado === 'Activa' ? 'Cierra ' . $subasta->fecha_fin->diffForHumans() : $subasta->estado }}</span>
                    </div>
                    <p class="muted">Cierre: {{ $subasta->fecha_fin->format('d/m/Y H:i') }} (Bolivia)</p>
                    @if ($subasta->estado === 'Activa')
                        @auth
                            @if (auth()->id() === $subasta->id_usuario)
                                <div class="info-note">Esta es tu subasta. @if (!$subasta->pujas_count)
                                        <a href="{{ route('auctions.edit', $subasta) }}">Editar publicación →</a>
                                    @else
                                        Recibiste ofertas; la edición está cerrada.
                                    @endif
                                </div>
                            @else<form method="post" action="{{ route('bids.store', $subasta) }}"
                                    data-confirm="¿Confirmas esta oferta? Las ofertas registradas no pueden retirarse.">
                                    @csrf<label for="monto">Tu oferta (Bs)</label>
                                    <div class="bid-input"><input id="monto" name="monto" type="number" step="0.01"
                                            min="{{ $subasta->pujas_count ? number_format((float) $subasta->pujas_max_monto + 0.01, 2, '.', '') : $subasta->monto_inicial }}"
                                            max="9999999999.99" value="{{ old('monto') }}"
                                            placeholder="{{ $subasta->pujas_count ? number_format((float) $subasta->pujas_max_monto + 0.01, 2, '.', '') : $subasta->monto_inicial }}"
                                            required><button class="button primary" type="submit">Hacer oferta</button></div>
                                </form>
                            @endif
                        @else<a class="button primary full-width" href="{{ route('login') }}">Inicia sesión para
                            ofertar</a>@endauth
                    @else<p>Esta subasta ya no admite ofertas.</p>
                    @endif
                </div>
                <div class="panel meeting-panel">
                    <h3><x-icon name="pin" />Punto de encuentro</h3>
                    <p>{{ $subasta->ubicacion }}</p><small>El vendedor y el ganador coordinan la entrega y el pago.</small>
                </div>
                <div class="seller"><span
                        class="avatar small">{{ mb_strtoupper(mb_substr($subasta->usuario->nombre, 0, 1)) }}</span>
                    <div><small>Publicado por</small><strong>{{ $subasta->usuario->nombre }}</strong></div>
                </div>
                @if (
                    $subasta->estado === 'Finalizada' &&
                        $ganadora &&
                        auth()->check() &&
                        in_array(auth()->id(), [$subasta->id_usuario, $ganadora->id_usuario], true))
                    @php($contacto = auth()->id() === $subasta->id_usuario ? $ganadora->usuario : $subasta->usuario)
                    <div class="panel">
                        <h3>{{ auth()->id() === $ganadora->id_usuario ? '¡Ganaste esta subasta!' : 'Contacto del ganador' }}
                        </h3>
                        <p>Coordina con {{ $contacto->nombre }}:</p>
                        <p><a href="mailto:{{ $contacto->correo }}">{{ $contacto->correo }}</a></p>
                        @if ($contacto->telefono)
                            <p>{{ $contacto->telefono }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
