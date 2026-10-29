@php
    $titulo = null;
    $items = [];
    $local = false;
    $filtrar = function ($ruta, $campo, $opciones, $predeterminado = '') {
        $resultado = [];
        foreach ($opciones as $valor => $etiqueta) {
            $parametros = array_filter(['q' => request('q'), $campo => (string) $valor], fn ($valor) => $valor !== null && $valor !== '');
            $resultado[] = [route($ruta, $parametros), $etiqueta, (string) request($campo, $predeterminado) === (string) $valor];
        }
        return $resultado;
    };
    if (request()->routeIs('perfil.edit')) {
        $titulo = 'Mi perfil';
        $local = true;
        $items = [['#informacion', 'Información personal', true], ['#seguridad', 'Seguridad', false]];
        if (! auth()->user()->esAdministrador()) $items[] = ['#identidad', 'Verificación de identidad', false];
        $items[] = ['#resenas', 'Reseñas y valoraciones', false];
    } elseif (request()->routeIs('subastas.create', 'subastas.edit')) {
        $titulo = 'Tu publicación';
        $local = true;
        $items = [['#articulo', 'Artículo', true], ['#fotografias', 'Fotografías', false], ['#condiciones', 'Condiciones', false], ['#encuentro', 'Ubicación', false]];
    } elseif (request()->routeIs('subastas.mias')) {
        $titulo = 'Mis subastas';
        $items = $filtrar('subastas.mias', 'apartado', ['' => 'Todas', 'revision' => 'En revisión', 'publicadas' => 'Publicadas', 'rechazadas' => 'Rechazadas', 'finalizadas' => 'Finalizadas']);
    } elseif (request()->routeIs('notificaciones.index')) {
        $titulo = 'Notificaciones';
        $items = $filtrar('notificaciones.index', 'solo_no_leidas', ['' => 'Todas', '1' => 'No leídas']);
    } elseif (request()->routeIs('participaciones.index', 'favoritos.index')) {
        $titulo = 'Mis participaciones';
        $items = $filtrar('participaciones.index', 'apartado', \App\Services\ParticipacionService::APARTADOS);
        if (request()->routeIs('favoritos.index')) $items = array_map(fn ($item) => [$item[0], $item[1], false], $items);
        $items[] = [route('favoritos.index'), 'Favoritos', request()->routeIs('favoritos.index')];
    } elseif (request()->routeIs('admin.usuarios.*')) {
        $titulo = 'Usuarios';
        $items = $filtrar('admin.usuarios.index', 'estado', ['' => 'Todos los usuarios', 'activo' => 'Activos', 'inactivo' => 'Desactivados']);
        if (! request()->routeIs('admin.usuarios.index')) $items = array_map(fn ($item) => [$item[0], $item[1], false], $items);
    } elseif (request()->routeIs('admin.categorias.*')) {
        $titulo = 'Categorías';
        $items = $filtrar('admin.categorias.index', 'estado', ['' => 'Todas', 'habilitadas' => 'Habilitadas', 'deshabilitadas' => 'Deshabilitadas']);
        if (! request()->routeIs('admin.categorias.index')) $items = array_map(fn ($item) => [$item[0], $item[1], false], $items);
    } elseif (request()->routeIs('admin.identidad.*', 'identidad.show') && auth()->user()?->esAdministrador()) {
        $titulo = 'Revisión de identidad';
        $items = $filtrar('admin.identidad.index', 'estado', ['En revisión' => 'Pendientes', 'Aprobada' => 'Aprobadas', 'Rechazada' => 'Rechazadas', 'Invalidada' => 'Invalidadas', 'todos' => 'Todas'], 'En revisión');
        if (request()->routeIs('identidad.show')) $items = array_map(fn ($item) => [$item[0], $item[1], false], $items);
    } elseif (request()->routeIs('admin.subastas.*')) {
        $titulo = 'Revisión de subastas';
        $items = $filtrar('admin.subastas.index', 'estado', ['En revisión' => 'Pendientes', 'Aceptada' => 'Aceptadas', 'Rechazada' => 'Rechazadas', 'todos' => 'Todas'], 'En revisión');
        if (request()->routeIs('admin.subastas.show')) $items = array_map(fn ($item) => [$item[0], $item[1], false], $items);
    } elseif (request()->routeIs('reportes.*')) {
        $titulo = 'Reportes';
        $items = [[route('reportes.subastas'), 'Subastas', request()->routeIs('reportes.subastas')], [route('reportes.usuarios'), 'Usuarios', request()->routeIs('reportes.usuarios')]];
    }
@endphp
@if ($titulo)
    <div class="section-workspace" data-espacio-seccion @if ($local) data-navegacion-local @endif data-errores="{{ json_encode($errors->keys()) }}">
        <aside class="section-sidebar">
            <nav aria-label="Apartados de {{ $titulo }}" class="section-nav">
                <p class="section-nav-caption">{{ $titulo }}</p>
                <button class="section-menu-toggle" type="button" aria-label="Apartados de {{ $titulo }}" aria-expanded="false" aria-controls="apartados-seccion" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <span class="section-menu-label"><span class="section-menu-full">Apartados de {{ $titulo }}</span><span class="section-menu-short" aria-hidden="true">Apartados</span></span>
                    <span class="section-menu-action" aria-hidden="true">Abrir</span>
                </button>
                <ul class="section-links" id="apartados-seccion">
                    @foreach ($items as [$url, $etiqueta, $activo])
                        <li><a href="{{ $url }}" @if ($activo) aria-current="{{ $local ? 'location' : 'page' }}" @endif>{{ $etiqueta }}</a></li>
                    @endforeach
                </ul>
                @if ($local)<p class="section-nav-note">Los datos se conservan al cambiar de apartado.</p>@endif
            </nav>
        </aside>
        <div class="section-content">{{ $slot }}</div>
    </div>
@else
    {{ $slot }}
@endif
