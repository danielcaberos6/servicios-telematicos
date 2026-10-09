@extends('plantillas.principal')
@section('title', $subasta->titulo)
@section('content')

    <div class="container section">
        <a class="breadcrumb" href="{{ route('subastas.index') }}">← Volver al catálogo</a>
        <div class="detail-grid">
            <div>
                <div class="detail-image">
                    @if ($subasta->imagenes->isNotEmpty())
                    <img id="main-photo" src="{{ $subasta->imagenes->first()->url }}" alt="{{ $subasta->titulo }}">@else
                        <div class="image-placeholder"><x-icono nombre="image" /><span>Este artículo todavía no tiene
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
                @if ($canViewRanking)
                    <section class="panel description-panel" aria-labelledby="ranking-title">
                        <h2 id="ranking-title">Ranking de participantes</h2>
                        <p class="muted">La mejor oferta de cada usuario. {{ count($ranking) }} participantes.</p>
                        @if ($ranking)
                            <div class="ranking-scroll"><table class="ranking-table">
                                <thead><tr><th scope="col">Puesto</th><th scope="col">Usuario</th><th scope="col">Mejor oferta</th><th scope="col">Pujas</th></tr></thead>
                                <tbody>@foreach ($ranking as $participant)
                                    <tr @class(['ranking-leader' => $participant->posicion === 1])>
                                        <td>#{{ $participant->posicion }}</td>
                                        <th scope="row">{{ $participant->nombre }}
                                            @if ($participant->id_usuario === auth()->id())<small>(Tú)</small>@endif
                                            @if ($participant->posicion === 1)<span class="tag green">{{ $subasta->estado === 'Finalizada' ? 'Ganador' : 'Líder' }}</span>@endif
                                        </th>
                                        <td>Bs {{ number_format($participant->mejor_oferta, 2, ',', '.') }}</td><td>{{ $participant->ofertas }}</td>
                                    </tr>
                                @endforeach</tbody>
                            </table></div>
                        @else
                            <p>Todavía no hay participantes. El ranking aparecerá al recibir la primera oferta.</p>
                        @endif
                    </section>
                @endif
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
                    <div class="bid-meta"><span>{{ $subasta->pujas_count }} ofertas</span><span><x-icono
                                nombre="clock" />{{ $subasta->estado === 'Activa' ? 'Cierra ' . $subasta->fecha_fin->diffForHumans() : $subasta->estado }}</span>
                    </div>
                    <div @class(['auction-deadline', 'auction-ended' => $subasta->estado === 'Finalizada'])>
                        <x-icono nombre="clock" />
                        <div><strong>{{ $subasta->estado === 'Finalizada' ? 'Subasta finalizada' : ($subasta->estado === 'Cancelada' ? 'Subasta cancelada' : 'Finaliza el') }}</strong>
                            <time datetime="{{ $subasta->fecha_fin->toIso8601String() }}">{{ $subasta->fecha_fin->format('d/m/Y \a \l\a\s H:i') }} (Bolivia)</time>
                            @if ($subasta->estado === 'Finalizada')<small>{{ $ganadora ? 'La oferta ganadora está confirmada.' : 'La subasta terminó sin ofertas.' }}</small>@endif
                        </div>
                    </div>
                    @if ($subasta->estado === 'Activa')
                        @auth
                            @if (auth()->id() === $subasta->id_usuario)
                                <div class="info-note">Esta es tu subasta. @if (!$subasta->pujas_count)
                                        <a href="{{ route('subastas.edit', $subasta) }}">Editar publicación →</a>
                                    @else
                                        Recibiste ofertas; la edición está cerrada.
                                    @endif
                                </div>
                            @else<form method="post" action="{{ route('pujas.store', $subasta) }}"
                                    data-confirm="¿Confirmas esta oferta? Las ofertas registradas no pueden retirarse.">
                                    @csrf<label for="monto">Tu oferta (Bs)</label>
                                    <div class="bid-input"><input id="monto" name="monto" type="number" step="0.01"
                                            min="{{ $subasta->pujas_count ? number_format((float) $subasta->pujas_max_monto + 0.01, 2, '.', '') : $subasta->monto_inicial }}"
                                            max="9999999999.99" value="{{ old('monto') }}"
                                            placeholder="{{ $subasta->pujas_count ? number_format((float) $subasta->pujas_max_monto + 0.01, 2, '.', '') : $subasta->monto_inicial }}"
                                            required><button class="button primary" type="submit">Hacer oferta</button></div>
                                </form>
                            @endif
                        @else<a class="button primary full-width" href="{{ route('iniciar-sesion') }}">Inicia sesión para
                            ofertar</a>@endauth
                    @else<p>Esta subasta ya no admite ofertas.</p>
                    @endif
                </div>
                <div class="panel meeting-panel">
                    <h3><x-icono nombre="pin" />Punto de encuentro</h3>
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
