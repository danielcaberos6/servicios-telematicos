@extends('plantillas.principal')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inicio.css') }}">
@endpush
@php
    $ilustraciones = [
        'Antigüedades y coleccionables' => ['foto' => 'camiseta-firmada.jpg', 'icono' => 'trophy'],
        'Deporte' => ['foto' => 'bicicleta.png', 'icono' => 'ball'],
        'Electrónica e informática' => ['foto' => 'laptop.png', 'icono' => 'laptop'],
        'Instrumentos musicales' => ['foto' => 'guitarra-firmada.jpg', 'icono' => 'music'],
        'Juguetes y juegos' => ['icono' => 'puzzle'],
        'Libros, películas y música' => ['foto' => 'libros.png', 'icono' => 'book'],
        'Muebles' => ['icono' => 'sofa'],
        'Ropa, calzado y accesorios' => ['icono' => 'shirt'],
        'Varios' => ['foto' => 'camara.jpg', 'icono' => 'box'],
        'Vehículos' => ['icono' => 'car'],
        'Videojuegos' => ['foto' => 'consola.jpg', 'icono' => 'gamepad'],
    ];
@endphp
@section('content')
    <section class="ini-hero">
        <div class="container ini-hero-grid">
            <div class="ini-hero-copy">
                <p class="ini-kicker"><span class="live-dot"></span> GRANDES HALLAZGOS, NUEVAS HISTORIAS</p>
                <h1>Tu próxima oportunidad<br>empieza <span>aquí.</span></h1>
                <p class="ini-hero-text">Descubre artículos únicos, haz tu mejor oferta y dale una nueva vida a lo que
                    alguien más ya no necesita.</p>
                <div class="button-row">
                    <a class="button ini-btn-dark" href="{{ route('subastas.index') }}">Explorar subastas <x-icono nombre="arrow" /></a>
                    @auth
                        <a class="button ini-btn-light" href="{{ route('subastas.create') }}"><x-icono nombre="plus" />Crear subasta</a>
                    @else
                        <a class="button ini-btn-light" href="{{ route('registro') }}">Crear una cuenta</a>
                    @endauth
                </div>
                <div class="ini-hero-proof"><x-icono nombre="shield" /><span>Ofertas transparentes</span><i></i><span>Encuentros en tu ciudad</span></div>
            </div>
            <div class="ini-hero-art" aria-hidden="true">
                <div class="ini-globe"></div>
                <div class="ini-orbit ini-orbit-1"></div>
                <div class="ini-orbit ini-orbit-2"></div>
                @foreach (['camera', 'car', 'trophy', 'gamepad', 'music', 'watch'] as $icono)
                    <span class="ini-bubble ini-bubble-{{ $loop->iteration }}"><x-icono :nombre="$icono" /></span>
                @endforeach
                <div class="ini-tag ini-tag-top"><span class="live-dot"></span> Una nueva oportunidad</div>
                <div class="ini-card">
                    <div class="ini-card-media"><x-icono nombre="gavel" /></div>
                    <span class="ini-card-kicker">LO QUE BUSCAS, TE ESTÁ ESPERANDO</span>
                    <strong>Que gane tu mejor oferta.</strong>
                    <div class="ini-card-foot"><span>Descubre algo especial</span><span class="ini-round"><x-icono nombre="arrow" /></span></div>
                </div>
                <div class="ini-tag ini-tag-bottom"><x-icono nombre="check" /> Encuentra. Oferta. Gana.</div>
            </div>
        </div>
    </section>

    <section class="container ini-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">ENCUENTRA LO QUE TE MUEVE</p>
                <h2>Un mundo de posibilidades</h2>
            </div>
            <a href="{{ route('subastas.index') }}" class="text-link">Ver todas las categorías <x-icono nombre="arrow" /></a>
        </div>
        <div class="ini-categories">
            @foreach ($categorias as $categoria)
                @php($ilustracion = $ilustraciones[$categoria->nombre_categoria] ?? ['icono' => 'box'])
                <a class="ini-category" href="{{ route('subastas.index', ['categoria' => $categoria->id_categoria]) }}">
                    <span class="ini-category-chip"><x-icono :nombre="$ilustracion['icono']" /></span>
                    <strong>{{ $categoria->nombre_categoria }}</strong>
                    @isset($ilustracion['foto'])
                        <img src="{{ asset('imagenes/ejemplos/' . $ilustracion['foto']) }}" alt="" loading="lazy">
                    @else
                        <span class="ini-category-art"><x-icono :nombre="$ilustracion['icono']" /></span>
                    @endisset
                </a>
            @endforeach
            <a class="ini-category ini-category-all" href="{{ route('subastas.index') }}">
                <strong>Explorar todo</strong><span>Todas las subastas activas</span><x-icono nombre="arrow" />
            </a>
        </div>
    </section>

    <section class="container ini-section ini-featured">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="live-dot"></span> {{ $totalActivas }} SUBASTAS ACTIVAS</p>
                <h2>Subastas destacadas</h2>
            </div>
            <a href="{{ route('subastas.index', ['orden' => 'finalizan']) }}" class="text-link">Ver todas <x-icono nombre="arrow" /></a>
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
            <div class="empty-state"><x-icono nombre="gavel" />
                <h3>Las próximas oportunidades empiezan contigo</h3>
                <p>Todavía no hay subastas activas. Vuelve pronto para descubrir novedades.</p>
                @auth
                    <a class="button primary" href="{{ route('subastas.create') }}">Crear subasta</a>
                @else
                    <a class="button primary" href="{{ route('registro') }}">Registrarme</a>
                @endauth
            </div>
        @endif
    </section>

    <section class="ini-band">
        <div class="container ini-band-grid">
            <div>
                <p class="eyebrow">NO LO DEJES PARA DESPUÉS</p>
                <h2>Oportunidades que no esperan</h2>
                <p class="muted">Estas subastas están por cerrar. Encuentra tu favorita y haz tu primera puja.</p>
            </div>
            <div class="ini-band-actions">
                <a class="ini-band-card" href="{{ route('subastas.index', ['orden' => 'finalizan']) }}">
                    <span class="ini-band-icon"><x-icono nombre="search" /></span>
                    <span><strong>Explorar subastas para empezar tu primera puja.</strong><small>Ordenadas por las que cierran primero</small></span>
                    <x-icono nombre="arrow" />
                </a>
                @auth
                    <a class="ini-band-card" href="{{ route('subastas.create') }}">
                        <span class="ini-band-icon"><x-icono nombre="plus" /></span>
                        <span><strong>¿Tienes algo que alguien más va a querer?</strong><small>Crear subasta con fotos y monto inicial</small></span>
                        <x-icono nombre="arrow" />
                    </a>
                @else
                    <a class="ini-band-card" href="{{ route('registro') }}">
                        <span class="ini-band-icon"><x-icono nombre="user" /></span>
                        <span><strong>¿Tienes algo que alguien más va a querer?</strong><small>Únete a SubastaYA y publica tu artículo</small></span>
                        <x-icono nombre="arrow" />
                    </a>
                @endauth
            </div>
        </div>
    </section>
@endsection
