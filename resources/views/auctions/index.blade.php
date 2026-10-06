@extends('layouts.app')
@section('title', 'Buscar subastas')
@section('content')
    <section class="search-band">
        <div class="container">
            <form class="search-bar" action="{{ route('auctions.index') }}" method="get"><x-icon name="search" /><input
                    type="search" name="q" value="{{ request('q') }}" placeholder="¿Qué estás buscando hoy?"
                    aria-label="Buscar por título o descripción" maxlength="120">
                @foreach (request()->except(['q', 'page']) as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
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
                <h1 class="page-title">Catálogo de subastas</h1>
                <p>Artículos activos, listos para recibir tu oferta.</p>
            </div>@auth<a href="{{ route('auctions.create') }}" class="button primary"><x-icon name="plus" />Crear
                subasta</a>@endauth
        </div>
        <div class="catalog-layout">
            <aside class="filter-panel">
                <div class="filter-heading">
                    <h2><x-icon name="filter" />Filtros</h2><a href="{{ route('auctions.index') }}">Limpiar</a>
                </div>
                <form method="get" action="{{ route('auctions.index') }}">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <label for="categoria">Categoría</label><select id="categoria" name="categoria">
                        <option value="">Todas las categorías</option>
                        @foreach ($categorias as $cat)
                            <option value="{{ $cat->id_categoria }}" @selected(request('categoria') == $cat->id_categoria)>
                                {{ $cat->nombre_categoria }}</option>
                        @endforeach
                    </select>
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
                        @foreach (['recientes' => 'Más recientes', 'finalizan' => 'Próximas a finalizar', 'precio_asc' => 'Menor monto inicial', 'precio_desc' => 'Mayor monto inicial'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('orden', 'recientes') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="button primary full-width" type="submit">Aplicar filtros</button>
                </form>
            </aside>
            <section class="catalog-results" aria-label="Resultados de búsqueda">
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
                        @foreach (request()->except(['page', 'orden']) as $key => $value)
                            @if (is_scalar($value) && $value !== null && $value !== '')
                                <a href="{{ route('auctions.index', request()->except([$key, 'page'])) }}"
                                    aria-label="Quitar filtro {{ $key }}">{{ ['q' => 'Búsqueda', 'categoria' => 'Categoría', 'estado' => 'Estado', 'desde' => 'Desde', 'hasta' => 'Hasta', 'min' => 'Desde Bs', 'max' => 'Hasta Bs', 'ubicacion' => 'Lugar'][$key] ?? $key }}:
                                    {{ $key === 'categoria' ? $categorias->firstWhere('id_categoria', $value)?->nombre_categoria : $value }}
                                    <span>×</span></a>
                            @endif
                        @endforeach
                    </div>
                @endif
                <div class="auction-grid catalog-grid">
                    @forelse($subastas as $subasta)
                    <x-auction-card :subasta="$subasta" />@empty<div class="empty-state"><x-icon name="search" />
                            <h2>No encontramos coincidencias</h2>
                            <p>Prueba con otra búsqueda o amplía los filtros.</p><a class="button secondary"
                                href="{{ route('auctions.index') }}">Limpiar filtros</a>
                        </div>
                    @endforelse
                </div><x-pagination :items="$subastas" />
            </section>
        </div>
    </div>
@endsection
