@extends('plantillas.principal')
@section('title', 'Explorar subastas')
@section('content')

    <section class="search-band">
        <div class="container">
            <form class="search-bar" action="{{ route('subastas.index') }}" method="get" data-sin-vacios><x-icono nombre="search" /><input
                    type="search" name="q" value="{{ request('q') }}" placeholder="¿Qué estás buscando hoy?"
                    aria-label="Buscar por título o descripción" maxlength="120">
                @foreach (request()->except(['q', 'page']) as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @elseif (is_array($value))
                        @foreach ($value as $item)
                            @if (is_scalar($item))
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endif
                        @endforeach
                    @endif
                @endforeach
                <button class="button primary" type="submit">
                    Buscar</button>
            </form>
        </div>
    </section>
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">EXPLORA Y ENCUENTRA</p>
                <h1 class="page-title">Explorar subastas</h1>
                <p>Artículos activos, listos para recibir tu oferta.</p>
            </div>
        </div>
        <div class="catalog-layout">
            <aside class="filter-panel">  
                <div class="filter-heading">
                    <h2><x-icono nombre="filter" />Filtros</h2><a href="{{ route('subastas.index') }}">Limpiar</a>
                </div>
                <form id="filtros" method="get" action="{{ route('subastas.index') }}" data-sin-vacios>
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    @php
                        $categoriasElegidas = array_map('strval', array_filter((array) request('categoria'), 'is_scalar'));
                    @endphp
                    <fieldset class="filter-categories">
                        <legend>Categorías</legend>
                        <p class="small-label">Sin marcar, se muestran todas.</p>
                        <div class="filter-check-list">
                            @foreach ($categorias as $cat)
                                <label class="checkbox-label"><input type="checkbox" name="categoria[]"
                                        value="{{ $cat->id_categoria }}" @checked(in_array((string) $cat->id_categoria, $categoriasElegidas, true))>
                                    {{ $cat->nombre_categoria }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label for="estado">Estado del artículo</label><select id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        @foreach (\App\Models\Subasta::CONDITIONS as $state)
                            <option @selected(request('estado') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <fieldset>
                        <legend>Fecha de publicación</legend><label class="small-label" for="desde">Desde</label><input
                            id="desde" name="desde" type="date" value="{{ request('desde') }}"><label
                            class="small-label" for="hasta">Hasta</label><input id="hasta" name="hasta"
                            type="date" value="{{ request('hasta') }}">
                    </fieldset>
                    <fieldset>
                        <legend>Monto inicial (Bs)</legend>
                        <div class="form-grid">
                            <div><label class="small-label" for="min">Mínimo</label><input id="min"
                                    name="min" type="number" min="0" step="0.01" placeholder="0"
                                    value="{{ request('min') }}"></div>
                            <div><label class="small-label" for="max">Máximo</label><input id="max"
                                    name="max" type="number" min="0" step="0.01" placeholder="Sin límite"
                                    value="{{ request('max') }}"></div>
                        </div>
                    </fieldset>
                    <label for="ubicacion">Punto de encuentro</label><input id="ubicacion" name="ubicacion"
                        placeholder="Ciudad, zona o lugar" value="{{ request('ubicacion') }}" maxlength="250">
                    <label for="orden">Ordenar por</label><select id="orden" name="orden">
                        @foreach (['recientes' => 'Más recientes', 'finalizan' => 'Próximas a finalizar', 'precio_asc' => 'Menor monto inicial', 'precio_desc' => 'Mayor monto inicial', 'cercanas' => 'Más cercanas'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('orden', 'recientes') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="button primary full-width" type="submit">Aplicar filtros</button>
                </form>
            </aside>
            <section class="catalog-results" aria-label="Resultados de búsqueda">
                @php
                    $puntos = $subastas->getCollection()
                        ->filter(fn ($s) => ! is_null($s->latitud) && ! is_null($s->longitud))
                        ->map(fn ($s) => [
                            'titulo' => $s->titulo,
                            'lat' => (float) $s->latitud,
                            'lng' => (float) $s->longitud,
                            'url' => route('subastas.show', $s),
                            'precio' => 'Bs '.number_format($s->pujas_max_monto ?? $s->monto_inicial, 2, ',', '.'),
                        ])->values();
                @endphp
                <x-leaflet />
                <section class="panel catalog-map" aria-labelledby="catalog-map-title">
                    <div class="catalog-map-heading">
                        <div>
                            <h2 id="catalog-map-title"><x-icono nombre="pin" />Buscar cerca de un punto</h2>
                            <p id="catalog-map-help">Haz clic en el mapa para elegir el centro, indica el radio y busca en esa zona. Se combina con los demás filtros.</p>
                        </div>
                        <span class="map-legend">{{ $puntos->count() }} de {{ $subastas->count() }} en el mapa</span>
                    </div>
                    <div class="location-map" data-mapa-catalogo data-lat="filtro-lat" data-lng="filtro-lng" data-radio="radio"
                        data-estado="centro-estado" data-puntos="{{ $puntos->toJson() }}">
                        <div class="map-canvas" data-mapa-lienzo aria-labelledby="catalog-map-title" aria-describedby="catalog-map-help"></div>
                        <p class="map-unavailable" data-mapa-aviso hidden><x-icono nombre="pin" />
                            <span>El mapa no está disponible en este momento. Puedes usar tu ubicación actual como centro de la búsqueda.</span></p>
                    </div>
                    <div class="radius-controls">
                        <input type="hidden" id="filtro-lat" name="lat" form="filtros" value="{{ old('lat', request('lat')) }}">
                        <input type="hidden" id="filtro-lng" name="lng" form="filtros" value="{{ old('lng', request('lng')) }}">
                        <div class="radius-field">
                            <label for="radio">Radio (km)</label>
                            <input id="radio" name="radio" form="filtros" type="number" min="0.1" max="150" step="0.1"
                                inputmode="decimal" placeholder="Ej. 5" value="{{ old('radio', request('radio')) }}">
                        </div>
                        <button type="button" class="button secondary" data-mapa-gps><x-icono nombre="pin" />Usar mi ubicación</button>
                        <button type="submit" class="button primary" form="filtros"><x-icono nombre="search" />Buscar en esta zona</button>
                        @if ($porRadio)
                            <a class="text-link" href="{{ route('subastas.index', request()->except(['lat', 'lng', 'radio', 'page'])) }}">Quitar filtro de cercanía</a>
                        @endif
                    </div>
                    <p id="centro-estado" class="map-center-status" aria-live="polite">
                        @if (filled(old('lat', request('lat'))) && filled(old('lng', request('lng'))))
                            Centro elegido: {{ old('lat', request('lat')) }}, {{ old('lng', request('lng')) }}
                        @else
                            Todavía no elegiste un centro.
                        @endif
                    </p>
                </section>
                <div class="results-heading">
                    <p><strong>{{ $subastas->total() }}</strong>
                        {{ $subastas->total() === 1 ? 'subasta encontrada' : 'subastas encontradas' }}@if (request('q'))
                            para «{{ request('q') }}»
                        @endif
                    </p>
                    <span class="active-label"><span class="live-dot"></span>Solo activas</span>
                </div>
                @if (collect(request()->except(['page', 'orden']))->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty())
                    <div class="filter-chips">
                        @if ($porRadio)
                            <a href="{{ route('subastas.index', request()->except(['lat', 'lng', 'radio', 'page'])) }}"
                                aria-label="Quitar filtro de cercanía">Cercanía: hasta {{ request('radio') }} km <span>×</span></a>
                        @endif
                        @foreach ($categoriasElegidas as $idCategoria)
                            @if ($nombreCategoria = $categorias->firstWhere('id_categoria', (int) $idCategoria)?->nombre_categoria)
                                <a href="{{ route('subastas.index', [...request()->except(['categoria', 'page']), 'categoria' => array_values(array_diff($categoriasElegidas, [$idCategoria]))]) }}"
                                    aria-label="Quitar categoría {{ $nombreCategoria }}">Categoría: {{ $nombreCategoria }} <span>×</span></a>
                            @endif
                        @endforeach
                        @foreach (request()->except(['page', 'orden', 'lat', 'lng', 'radio', 'categoria']) as $key => $value)
                            @if (is_scalar($value) && $value !== null && $value !== '')
                                <a href="{{ route('subastas.index', request()->except([$key, 'page'])) }}"
                                    aria-label="Quitar filtro {{ $key }}">{{ ['q' => 'Búsqueda', 'estado' => 'Estado', 'desde' => 'Desde', 'hasta' => 'Hasta', 'min' => 'Desde Bs', 'max' => 'Hasta Bs', 'ubicacion' => 'Lugar'][$key] ?? $key }}:
                                    {{ $value }}
                                    <span>×</span></a>
                            @endif
                        @endforeach
                    </div>
                @endif
                <div class="auction-grid catalog-grid">
                    @forelse($subastas as $subasta)
                    <x-tarjeta-subasta :subasta="$subasta" />@empty<div class="empty-state"><x-icono nombre="search" />
                            <h2>No encontramos coincidencias</h2>
                            @if ($porRadio)
                                <p>No hay subastas activas con punto de encuentro a {{ request('radio') }} km o menos del centro elegido. Amplía el radio o mueve el centro.</p>
                                <a class="button secondary" href="{{ route('subastas.index', request()->except(['lat', 'lng', 'radio', 'page'])) }}">Quitar filtro de cercanía</a>
                            @else
                                <p>Prueba con otra búsqueda o amplía los filtros.</p><a class="button secondary"
                                    href="{{ route('subastas.index') }}">Limpiar filtros</a>
                            @endif
                        </div>
                    @endforelse
                </div><x-paginacion :elementos="$subastas" />
            </section>
        </div>
    </div>
@endsection
