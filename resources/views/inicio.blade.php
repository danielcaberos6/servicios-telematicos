@extends('plantillas.principal')
{{-- Estilos propios de la página de inicio. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inicio.css') }}">
@endpush
{{-- Cada categoría utiliza un icono, sin fotografías decorativas. --}}
@php
    $iconosCategorias = [
        'Antigüedades y coleccionables' => 'trophy',
        'Deporte' => 'ball',
        'Electrónica e informática' => 'laptop',
        'Instrumentos musicales' => 'music',
        'Juguetes y juegos' => 'puzzle',
        'Libros, películas y música' => 'book',
        'Muebles' => 'sofa',
        'Ropa, calzado y accesorios' => 'shirt',
        'Varios' => 'box',
        'Vehículos' => 'car',
        'Videojuegos' => 'gamepad',
    ];
@endphp
@section('content')
    <section class="ini-hero" aria-labelledby="inicio-titulo">
        <img class="ini-hero-image" src="{{ asset('imagenes/ejemplos/inicio-subasta.png') }}" alt="" aria-hidden="true" fetchpriority="high" width="2172" height="724">
        <div class="ini-hero-copy">
            <h1 id="inicio-titulo">Tu próxima oportunidad <span>empieza aquí.</span></h1>
            <p>Descubre artículos únicos, haz tu mejor oferta y dale una nueva vida a lo que alguien más ya no necesita.</p>
        </div>
    </section>

    {{-- Categorías con desplazamiento horizontal. --}}
    <section class="ini-browse" aria-labelledby="categorias-titulo">
        <div class="container">
            <div class="section-heading ini-browse-heading">
                <div>
                    <p class="eyebrow">ENCUENTRA LO QUE TE MUEVE</p>
                    <h2 id="categorias-titulo">Explora por categoría</h2>
                    <p class="muted">Un buen hallazgo empieza por lo que te gusta.</p>
                </div>
            </div>
            {{-- La barra reutiliza los controles del carrusel existente. --}}
            <div class="ini-carousel ini-category-carousel" data-carrusel>
                <button type="button" class="ini-nav ini-nav-prev" data-carrusel-anterior disabled aria-label="Ver categorías anteriores" aria-controls="categorias-pista"><x-icono nombre="chevron-left" /></button>
                <div class="ini-categories" id="categorias-pista" data-carrusel-pista tabindex="0" role="region" aria-label="Categorías de subastas">
                    {{-- Los enlaces mantienen el filtro por categoría del catálogo. --}}
                    @foreach ($categorias as $categoria)
                        <a class="ini-category" href="{{ route('subastas.index', ['categoria' => $categoria->id_categoria]) }}">
                            <span class="ini-category-icon"><x-icono :nombre="$iconosCategorias[$categoria->nombre_categoria] ?? 'box'" /></span>
                            <strong>{{ $categoria->nombre_categoria }}</strong>
                        </a>
                    @endforeach
                </div>
                <button type="button" class="ini-nav ini-nav-next" data-carrusel-siguiente aria-label="Ver más categorías" aria-controls="categorias-pista"><x-icono nombre="chevron-right" /></button>
            </div>
        </div>
    </section>

    {{-- Subastas destacadas. --}}
    <section class="container ini-section ini-featured" aria-labelledby="destacadas-titulo">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="live-dot"></span> {{ $totalActivas }} SUBASTAS ACTIVAS</p>
                <h2 id="destacadas-titulo">Subastas destacadas</h2>
            </div>
            <a href="{{ route('subastas.index', ['orden' => 'finalizan']) }}" class="text-link">Ver todas <x-icono nombre="chevron-right" /></a>
        </div>
        @if ($subastas->isNotEmpty())
            <div class="ini-carousel" data-carrusel>
                <button type="button" class="ini-nav ini-nav-prev" data-carrusel-anterior disabled aria-label="Ver subastas anteriores"><x-icono nombre="chevron-left" /></button>
                <div class="ini-track" data-carrusel-pista tabindex="0" aria-label="Subastas destacadas">
                    @foreach ($subastas as $subasta)
                        <x-tarjeta-destacada :subasta="$subasta" />
                    @endforeach
                </div>
                <button type="button" class="ini-nav ini-nav-next" data-carrusel-siguiente aria-label="Ver más subastas"><x-icono nombre="chevron-right" /></button>
            </div>
        @else
            {{-- Mensaje visible cuando no hay publicaciones activas. --}}
            <div class="empty-state"><x-icono nombre="gavel" />
                <h3>Las próximas oportunidades empiezan contigo</h3>
                <p>Todavía no hay subastas activas. Vuelve pronto para descubrir novedades.</p>
                @guest
                    <a class="button primary" href="{{ route('registro') }}">Registrarme</a>
                @endguest
            </div>
        @endif
    </section>
@endsection
