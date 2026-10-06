@extends('layouts.app')
@section('title', 'Notificaciones')
@section('content')
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">AL DÍA CON TUS SUBASTAS</p>
                <h1 class="page-title">Notificaciones</h1>
                <p>Tus publicaciones, ofertas y novedades en un solo lugar.</p>
            </div>
            <form action="{{ route('notifications.readAll') }}" method="post">@csrf @method('PATCH')<button
                    class="button secondary" type="submit"><x-icon name="check" />Marcar todas como leídas</button></form>
        </div>
        <form class="notification-search" method="get" action="{{ route('notifications.index') }}">
            <div class="search-bar"><x-icon name="search" /><input type="search" name="q"
                    value="{{ request('q') }}" maxlength="120" placeholder="Buscar en tus notificaciones"
                    aria-label="Buscar notificaciones"><button class="button primary" type="submit">Buscar</button></div>
            <label class="checkbox-label"><input type="checkbox" name="solo_no_leidas" value="1"
                    @checked(request('solo_no_leidas'))>Solo no leídas</label>
        </form>
        <div class="notification-list">
            @forelse($notificaciones as $item)
                <article @class(['notification', 'unread' => !$item->leido])><span class="notification-icon"><x-icon name="bell" /></span>
                    <div class="notification-body">
                        <h2>{{ $item->titulo }}@unless ($item->leido)
                            <span class="unread-dot" title="No leída"></span>
                        @endunless
                    </h2>
                    <p>{{ $item->contenido }}</p>
                    @if ($item->id_subasta)<p><a href="{{ route('auctions.show', $item->id_subasta) }}">Ver subasta →</a></p>@endif
                    <time
                        datetime="{{ $item->fecha_notificacion->toIso8601String() }}">{{ $item->fecha_notificacion->diffForHumans() }}</time>
                </div>
                <div class="notification-actions">
                    @unless ($item->leido)
                        <form method="post" action="{{ route('notifications.read', $item) }}">@csrf
                            @method('PATCH')<button type="submit" class="icon-button"
                                aria-label="Marcar como leída: {{ $item->titulo }}" title="Marcar como leída"><x-icon
                                    name="check" /></button></form>
                    @endunless
                    <form method="post" action="{{ route('notifications.destroy', $item) }}">
                        @csrf @method('DELETE')<button type="submit" class="icon-button danger-link"
                            aria-label="Eliminar notificación: {{ $item->titulo }}" title="Eliminar"><x-icon
                                name="trash" /></button></form>
                </div>
            </article>@empty<div class="empty-state"><x-icon name="bell" />
                    <h2>Todo tranquilo por aquí</h2>
                    <p>No hay notificaciones que mostrar con estos filtros.</p>
                </div>
            @endforelse
        </div><x-pagination :items="$notificaciones" />
    </div>
@endsection
