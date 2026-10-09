<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="SubastaYA: descubre artículos, publica tus subastas y encuentra oportunidades cerca de ti.">
    <title>@yield('title', 'Dale una nueva historia a lo que te gusta') · SubastaYA</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>

<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand" href="{{ route('home') }}" aria-label="SubastaYA, inicio"><span class="brand-icon"><x-icon
                        name="gavel" /></span><span>subasta<span class="brand-accent">YA</span><small>ENCUENTRA.
                        OFERTA. GANA.</small></span></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="navigation">Menú ☰</button>
            <nav id="navigation" class="navigation" aria-label="Navegación principal">
                @foreach ([['home', 'home', 'Inicio'], ['profile.edit', 'user', 'Perfil'], ['auctions.index', 'search', 'Buscar'], ['auctions.mine', 'gavel', 'Mis Subastas'], ['notifications.index', 'bell', 'Notificaciones']] as [$route, $icon, $label])
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($route)])
                        @if (request()->routeIs($route)) aria-current="page" @endif><x-icon
                            :name="$icon" />{{ $label }}@if (
                                $route === 'notifications.index' &&
                                    auth()->check() &&
                                    ($unread = auth()->user()->notificaciones()->where('leido', false)->count()))
                            <span class="nav-count">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <div class="account-nav">
                @auth
                    <a class="account-name" href="{{ route('profile.edit') }}" aria-label="Mi perfil: {{ auth()->user()->nombre }}"><x-icon
                            name="user" /><span>{{ Str::limit(auth()->user()->nombre, 16) }}</span></a>
                    <form action="{{ route('logout') }}" method="post">@csrf<button class="logout"
                            type="submit">Salir</button></form>
                @else
                    <a class="login-link" href="{{ route('login') }}"><x-icon name="user" /> Iniciar sesión</a>
                @endauth
            </div>
        </div>
    </header>
    <main id="contenido">
        @if (session('status'))
            <div class="container">
                <div class="alert success" role="status"><x-icon name="check" />{{ session('status') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="container">
                <div class="alert error" role="alert"><strong>Revisa los siguientes datos:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="container footer-content"><a class="brand footer-brand" href="{{ route('home') }}"><x-icon
                    name="gavel" /><span>subasta<span>YA</span></span></a>
            <p>Una nueva oportunidad para cada artículo.</p><span>© {{ date('Y') }} SubastaYA · Bolivia</span>
        </div>
    </footer>
</body>

</html>
