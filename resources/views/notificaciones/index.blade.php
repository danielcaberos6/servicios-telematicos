@extends('plantillas.principal')
@section('title', 'Notificaciones')
@section('content')
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">AL DÍA CON TUS SUBASTAS</p>
                <h1 class="page-title">Notificaciones</h1>
                <p>Tus publicaciones, ofertas y novedades en un solo lugar.</p>
            </div>
            <form action="{{ route('notificaciones.leerTodas') }}" method="post">@csrf @method('PATCH')<button
                    class="button secondary" type="submit"><x-icono nombre="check" />Marcar todas como leídas</button></form>
        </div>
        <form class="notification-search" method="get" action="{{ route('notificaciones.index') }}">
            <div class="search-bar"><x-icono nombre="search" /><input type="search" name="q"
                    value="{{ request('q') }}" maxlength="120" placeholder="Buscar en tus notificaciones"
                    aria-label="Buscar notificaciones"><button class="button primary" type="submit">Buscar</button></div>
            <label class="checkbox-label"><input type="checkbox" name="solo_no_leidas" value="1"
                    @checked(request('solo_no_leidas'))>Solo no leídas</label>
        </form>
        <div class="notification-list">
            @forelse($notificaciones as $item)
                <article @class(['notification', 'unread' => !$item->leido])><span class="notification-icon"><x-icono nombre="bell" /></span>
                    <div class="notification-body">
                        <h2>{{ $item->titulo }}@unless ($item->leido)
                            <span class="unread-dot" title="No leída"></span>
                        @endunless
                    </h2>
                    <p>{{ $item->contenido }}</p>
                    @if ($item->id_subasta)<p><a href="{{ route('subastas.show', $item->id_subasta) }}">Ver subasta →</a></p>@endif
                    <time
                        datetime="{{ $item->fecha_notificacion->toIso8601String() }}">{{ $item->fecha_notificacion->diffForHumans() }}</time>
                </div>
                <div class="notification-actions">
                    @unless ($item->leido)
                        <form method="post" action="{{ route('notificaciones.leer', $item) }}">@csrf
                            @method('PATCH')<button type="submit" class="icon-button"
                                aria-label="Marcar como leída: {{ $item->titulo }}" title="Marcar como leída"><x-icono
                                    nombre="check" /></button></form>
                    @endunless
                    <form method="post" action="{{ route('notificaciones.destroy', $item) }}">
                        @csrf @method('DELETE')<button type="submit" class="icon-button danger-link"
                            aria-label="Eliminar notificación: {{ $item->titulo }}" title="Eliminar"><x-icono
                                nombre="trash" /></button></form>
                </div>
            </article>@empty<div class="empty-state"><x-icono nombre="bell" />
                    <h2>Todo tranquilo por aquí</h2>
                    <p>No hay notificaciones que mostrar con estos filtros.</p>
                </div>
            @endforelse
        </div><x-paginacion :elementos="$notificaciones" />
    </div>
@endsection
