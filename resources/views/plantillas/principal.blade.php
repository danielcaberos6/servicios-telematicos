<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="SubastaYA: descubre artículos, publica tus subastas y encuentra oportunidades cerca de ti.">
    <title>@yield('title', 'Dale una nueva historia a lo que te gusta') · SubastaYA</title>
    <meta name="color-scheme" content="light dark">
    <script src="{{ asset('js/apariencia.js') }}"></script>
    <link rel="icon" href="{{ asset('imagenes/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/apartados.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/apariencia.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cuenta.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/apartados.js') }}" defer></script>
    @stack('scripts')
    <script src="{{ asset('js/cuenta.js') }}" defer></script>
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
                            ? [['inicio', 'home', 'Inicio'], ['perfil.edit', 'user', 'Perfil'], ['subastas.index', 'search', 'Explorar subastas'], ['subastas.mias', 'gavel', 'Mis subastas'], ['participaciones.index', 'trophy', 'Mis participaciones'], ['notificaciones.index', 'bell', 'Notificaciones']]
                            : [['inicio', 'home', 'Inicio'], ['subastas.index', 'search', 'Explorar subastas'], ['como-funciona', 'book', 'Cómo funciona']]);
                @endphp
                @foreach ($enlaces as [$route, $icon, $label])
                    @php
                        $activo = str_starts_with($route, 'admin.')
                            ? request()->routeIs(Str::beforeLast($route, '.') . '.*')
                            : request()->routeIs($route);
                        $activo = $activo || ($route === 'admin.identidad.index' && request()->routeIs('identidad.show'));
                        $activo = $activo || ($route === 'participaciones.index' && request()->routeIs('favoritos.index'));
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
                    <a href="{{ route('admin.consultas.index') }}" @class(['nav-link', 'active' => request()->routeIs('admin.consultas.*')]) @if(request()->routeIs('admin.consultas.*')) aria-current="page" @endif>Consultas</a>
                    <a href="{{ route('reportes.subastas') }}" @class(['nav-link', 'active' => request()->routeIs('reportes.*')])
                        @if (request()->routeIs('reportes.*')) aria-current="page" @endif>Reportes</a>
                @endif
            </nav>
            <div class="account-nav">
                <button class="theme-toggle" type="button" data-tema-selector aria-label="Modo oscuro" aria-pressed="false" hidden>
                    <svg class="icon theme-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z" /></svg>
                    <svg class="icon theme-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5" /></svg>
                    <span data-tema-texto>Claro</span>
                </button>
                @auth
                    @php
                        $cuenta = auth()->user();
                        $partesNombre = preg_split('/\s+/u', trim($cuenta->nombres ?: $cuenta->nombre), -1, PREG_SPLIT_NO_EMPTY);
                        $nombresCabecera = trim($cuenta->nombres ?? '') ?: ($partesNombre[0] ?? 'Mi perfil');
                        $apellido = trim($cuenta->apellidos ?? '');
                        $iniciales = mb_strtoupper(mb_substr($partesNombre[0] ?? '', 0, 1)
                            . ($apellido !== '' ? mb_substr($apellido, 0, 1) : (count($partesNombre) > 1 ? mb_substr(end($partesNombre), 0, 1) : '')));
                    @endphp
                    <details class="account-menu" data-cuenta-menu>
                    <summary class="account-name" aria-label="Mi cuenta: {{ $cuenta->nombre }}">
                        <span class="account-avatar" aria-hidden="true">
                            @if ($cuenta->imagen)
                                <img src="{{ $cuenta->imagen->url }}" alt="" width="32" height="32">
                            @else
                                {{ $iniciales }}
                            @endif
                        </span>
                        <span class="account-label">{{ $nombresCabecera }}</span>
                    </summary>
                    <nav class="account-popover" aria-label="Opciones de tu cuenta">
                        <p class="account-menu-name">{{ $cuenta->nombre }}</p>
                        <a class="account-option" href="{{ route('perfil.edit') }}"><x-icono nombre="user" />Mi perfil</a>
                        <a class="account-option" href="{{ route($esAdministrador ? 'admin.fondos.index' : 'billetera.index') }}"><x-icono nombre="box" />{{ $esAdministrador ? 'Control de fondos' : 'Mi billetera' }}</a>
                        <button class="account-option" type="button" data-tema-selector aria-label="Modo oscuro" aria-pressed="false" hidden>
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v18M12 3a9 9 0 0 0 0 18M12 3a9 9 0 0 1 0 18" /></svg>
                            <span>Apariencia</span><span class="account-option-state" data-tema-texto>Claro</span>
                        </button>
                        <form class="account-logout" action="{{ route('cerrar-sesion') }}" method="post" data-cerrar-sesion>@csrf
                            <button class="account-option" type="submit"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 4H4v16h5m5-12 4 4-4 4m-6-4h10" /></svg>Cerrar sesión</button>
                        </form>
                    </nav>
                    </details>
                @else
                    <a class="login-link" href="{{ route('iniciar-sesion') }}"><x-icono nombre="user" /> Iniciar sesión</a>
                @endauth
            </div>
        </div>
    </header>
    @auth
        <dialog class="logout-dialog" data-confirmar-salida aria-labelledby="salida-titulo" aria-describedby="salida-descripcion">
            <h2 id="salida-titulo">Tienes cambios sin guardar</h2>
            <p id="salida-descripcion">Si cierras sesión, perderás los cambios pendientes de tus formularios. ¿Quieres cerrar sesión?</p>
            <div class="logout-dialog-actions">
                <button class="button secondary" type="button" data-seguir-editando autofocus>Seguir editando</button>
                <button class="button primary" type="button" data-confirmar-cierre>Cerrar sesión</button>
            </div>
        </dialog>
    @endauth
    <main id="contenido" data-campos-pendientes="{{ json_encode(array_keys(session()->getOldInput())) }}">
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
        <x-espacio-seccion>@yield('content')</x-espacio-seccion>
    </main>
    <footer class="site-footer">
        <div class="container footer-content"><a class="brand footer-brand" href="{{ route($rutaPrincipal) }}"><x-icono
                    nombre="gavel" /><span>subasta<span>YA</span></span></a>
            <p>Una nueva oportunidad para cada artículo.</p>
            <a class="footer-normas" href="{{ route('normas.show') }}">Normas y políticas</a>
            <span>© {{ date('Y') }} SubastaYA · Bolivia</span>
        </div>
    </footer>
</body>

</html>
