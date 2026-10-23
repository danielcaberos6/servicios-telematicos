<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="SubastaYA: descubre artículos, publica tus subastas y encuentra oportunidades cerca de ti.">
    <title>@yield('title', 'Dale una nueva historia a lo que te gusta') · SubastaYA</title>
    <link rel="icon" href="{{ asset('imagenes/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</head>

<body>
    @php
        $esAdministrador = auth()->user()?->esAdministrador() ?? false;
        $rutaPrincipal = $esAdministrador ? 'admin.usuarios.index' : 'inicio';
    @endphp
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand" href="{{ route($rutaPrincipal) }}" aria-label="{{ $esAdministrador ? 'SubastaYA, administración' : 'SubastaYA, inicio' }}"><span class="brand-icon"><x-icono
                        nombre="gavel" /></span><span>subasta<span class="brand-accent">YA</span><small>ENCUENTRA.
                        OFERTA. GANA.</small></span></a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="navigation">Menú ☰</button>
            <nav id="navigation" class="navigation" aria-label="Navegación principal">
                @php
                    $enlaces = $esAdministrador
                        ? [['admin.subastas.index', 'gavel', 'Subastas'], ['admin.usuarios.index', 'user', 'Usuarios'], ['admin.categorias.index', 'box', 'Categorías'], ['admin.identidad.index', 'shield', 'Identidad'], ['notificaciones.index', 'bell', 'Notificaciones']]
                        : (auth()->check()
                            ? [['inicio', 'home', 'Inicio'], ['perfil.edit', 'user', 'Perfil'], ['subastas.index', 'search', 'Subastas'], ['subastas.mias', 'gavel', 'Mis Subastas'], ['notificaciones.index', 'bell', 'Notificaciones']]
                            : [['inicio', 'home', 'Inicio'], ['subastas.index', 'search', 'Subastas'], ['como-funciona', 'book', 'Cómo funciona']]);
                @endphp
                @foreach ($enlaces as [$route, $icon, $label])
                    @php
                        $activo = str_starts_with($route, 'admin.')
                            ? request()->routeIs(Str::beforeLast($route, '.') . '.*')
                            : request()->routeIs($route);
                        $activo = $activo || ($route === 'admin.identidad.index' && request()->routeIs('identidad.show'));
                    @endphp
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => $activo])
                        @if ($activo) aria-current="page" @endif><x-icono
                            :nombre="$icon" />{{ $label }}@if (
                                $route === 'notificaciones.index' &&
                                    auth()->check() &&
                                    ($unread = auth()->user()->notificaciones()->where('leido', false)->count()))
                            <span class="nav-count">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </a>
                @endforeach
                @if ($esAdministrador)
                    <a href="{{ route('reportes.subastas') }}" @class(['nav-link', 'active' => request()->routeIs('reportes.*')])
                        @if (request()->routeIs('reportes.*')) aria-current="page" @endif>Reportes</a>
                @endif
            </nav>
            <div class="account-nav">
                @auth
                    @php
                        $cuenta = auth()->user();
                        $partesNombre = preg_split('/\s+/u', trim($cuenta->nombres ?: $cuenta->nombre), -1, PREG_SPLIT_NO_EMPTY);
                        $apellido = trim($cuenta->apellidos ?? '');
                        $iniciales = mb_strtoupper(mb_substr($partesNombre[0] ?? '', 0, 1)
                            . ($apellido !== '' ? mb_substr($apellido, 0, 1) : (count($partesNombre) > 1 ? mb_substr(end($partesNombre), 0, 1) : '')));
                    @endphp
                    <a class="account-name" href="{{ route('perfil.edit') }}" aria-label="Mi perfil: {{ $cuenta->nombre }}">
                        <span class="account-avatar" aria-hidden="true">
                            @if ($cuenta->imagen)
                                <img src="{{ $cuenta->imagen->url }}" alt="" width="32" height="32">
                            @else
                                {{ $iniciales }}
                            @endif
                        </span>
                        <span class="account-label">{{ Str::limit($cuenta->nombre, 16) }}</span>
                    </a>
                    <form action="{{ route('cerrar-sesion') }}" method="post">@csrf<button class="logout"
                            type="submit">Salir</button></form>
                @else
                    <a class="login-link" href="{{ route('iniciar-sesion') }}"><x-icono nombre="user" /> Iniciar sesión</a>
                @endauth
            </div>
        </div>
    </header>
    <main id="contenido">
        @if (session('status'))
            <div class="container" data-aviso-exito>
                <div class="alert success" role="status">
                    <x-icono nombre="check" /><span class="alert-message">{{ session('status') }}</span>
                    <button class="alert-close" type="button" aria-label="Cerrar aviso" hidden>&times;</button>
                </div>
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
        <div class="container footer-content"><a class="brand footer-brand" href="{{ route($rutaPrincipal) }}"><x-icono
                    nombre="gavel" /><span>subasta<span>YA</span></span></a>
            <p>Una nueva oportunidad para cada artículo.</p><span>© {{ date('Y') }} SubastaYA · Bolivia</span>
        </div>
    </footer>
</body>

</html>
