@extends('layouts.app')
@section('content')
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><span class="live-dot"></span> GRANDES HALLAZGOS, NUEVAS HISTORIAS</p>
                <h1>Tu próxima oportunidad<br>empieza <span>aquí.</span></h1>
                <p class="hero-description">Descubre artículos únicos, haz tu mejor oferta y dale una nueva vida a lo que
                    alguien más ya no necesita.</p>
                <div class="button-row"><a class="button primary" href="{{ route('auctions.index') }}">Explorar subastas
                        <x-icon name="arrow" /></a>@auth<a class="button secondary"
                        href="{{ route('auctions.create') }}"><x-icon name="plus" />Crear subasta</a>@else<a
                        class="button secondary" href="{{ route('register') }}">Crear una cuenta</a>@endauth
                </div>
                <div class="hero-proof"><x-icon name="shield" /><span>Ofertas transparentes</span><i></i><span>Encuentros
                        en tu ciudad</span></div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="orbit orbit-one"></div>
                <div class="orbit orbit-two"></div>
                <div class="floating-label top-label"><span class="live-dot"></span> Una nueva oportunidad</div>
                <div class="hero-auction">
                    <div class="hero-gavel"><x-icon name="gavel" /></div><span class="eyebrow">LO QUE BUSCAS, TE ESTÁ
                        ESPERANDO</span><strong>Que gane tu mejor oferta.</strong>
                    <div class="hero-auction-bottom"><span>Descubre algo especial</span><span class="round-arrow"><x-icon
                                name="arrow" /></span></div>
                </div>
                <div class="floating-label bottom-label"><x-icon name="check" /> Encuentra. Oferta. Gana.</div>
            </div>
        </div>
    </section>
    <div class="benefit-strip">
        <div class="container"><span><x-icon name="search" />Explora por categoría</span><span><x-icon
                    name="gavel" />Publica y recibe ofertas</span><span><x-icon name="pin" />Coordina el punto de
                encuentro</span></div>
    </div>
    <section class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">ENCUENTRA LO QUE TE MUEVE</p>
                <h2>Un mundo de posibilidades</h2>
            </div><a href="{{ route('auctions.index') }}" class="text-link">Todas las categorías <x-icon
                    name="arrow" /></a>
        </div>
        <div class="category-grid">
            @foreach ($categorias as $categoria)
                <a class="category-tile"
                    href="{{ route('auctions.index', ['categoria' => $categoria->id_categoria]) }}"><span
                        class="category-icon"><x-icon
                            :name="match ($categoria->nombre_categoria) {
                                'Tecnología' => 'laptop',
                                'Deportes' => 'bike',
                                'Moda y accesorios' => 'watch',
                                'Hogar' => 'home',
                                default => 'box',
                            }" /></span><strong>{{ $categoria->nombre_categoria }}</strong><x-icon
                        name="arrow" /></a>
            @endforeach
        </div>
    </section>
    <section class="container section opportunities">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="live-dot"></span> {{ $totalActivas }} SUBASTAS ACTIVAS</p>
                <h2>Oportunidades que no esperan</h2>
                <p>Estas subastas están por cerrar. Encuentra tu favorita.</p>
            </div><a href="{{ route('auctions.index') }}" class="text-link">Ver todas <x-icon name="arrow" /></a>
        </div>
        <div class="auction-grid">
            @forelse($subastas as $subasta)
            <x-auction-card :subasta="$subasta" />@empty<div class="empty-state"><x-icon name="gavel" />
                    <h3>Las próximas oportunidades empiezan contigo</h3>
                    <p>Todavía no hay subastas activas. Vuelve pronto para descubrir novedades.</p>@auth<a
                        class="button primary" href="{{ route('auctions.create') }}">Crear subasta</a>@else<a
                        class="button primary" href="{{ route('register') }}">Registrarme</a>@endauth
                </div>
            @endforelse
        </div>
    </section>
    <section class="how-section">
        <div class="container section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">ASÍ DE SENCILLO</p>
                    <h2>De un hallazgo a una nueva historia</h2>
                </div><span class="muted">Tú decides tu siguiente paso.</span>
            </div>
            <div class="steps">
                <article><span class="step-number">01</span>
                    <h3>Encuentra tu artículo</h3>
                    <p>Busca por categoría, estado, presupuesto o punto de encuentro.</p>
                </article>
                <article><span class="step-number">02</span>
                    <h3>Haz tu mejor oferta</h3>
                    <p>Crea tu cuenta y participa. Tus notificaciones te mantienen al tanto.</p>
                </article>
                <article><span class="step-number">03</span>
                    <h3>Coordina la entrega</h3>
                    <p>Si ganas, contacta al vendedor y acuerda la transacción en el lugar indicado.</p>
                </article>
            </div>
        </div>
    </section>
    <section class="container section">
        <div class="sell-banner">
            <div>
                <p class="eyebrow">DALE OTRA VIDA</p>
                <h2>¿Tienes algo que alguien más va a querer?</h2>
                <p>Agrega unas fotos, elige un monto inicial y publica tu subasta.</p>
            </div>@auth<a class="button light" href="{{ route('auctions.create') }}"><x-icon name="plus" />Crear
                subasta</a>@else<a class="button light" href="{{ route('register') }}">Únete a SubastaYA <x-icon
                    name="arrow" /></a>@endauth
        </div>
    </section>
@endsection
