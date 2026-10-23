@extends('plantillas.principal')
@section('title', 'Mis Subastas')
@section('content')
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">TUS PUBLICACIONES</p>
                <h1 class="page-title">Mis Subastas</h1>
                <p>Administra tus artículos y sigue las ofertas que recibes.</p>
            </div><a class="button primary" href="{{ route('subastas.create') }}"><x-icono nombre="plus" />Crear subasta</a>
        </div>
        <p class="muted">{{ $subastas->total() }} publicaciones · Cada solicitud queda En revisión hasta que un
            administrador la acepte o la rechace. Puedes editar o eliminar las que aún no recibieron ofertas.</p>
        <div class="auction-grid">
            @forelse($subastas as $subasta)
            <x-tarjeta-subasta :subasta="$subasta" :gestionar="true" />@empty<div class="empty-state"><x-icono
                        nombre="gavel" />
                    <h2>{{ request('apartado') ? 'No hay publicaciones en este apartado' : 'Tu primera subasta empieza aquí' }}</h2>
                    <p>{{ request('apartado') ? 'Puedes consultar tus otras publicaciones desde el menú lateral.' : 'Publica ese artículo que merece una nueva historia.' }}</p><a class="button primary"
                        href="{{ route('subastas.create') }}">Crear subasta</a>
                </div>
            @endforelse
        </div><x-paginacion :elementos="$subastas" />
    </div>
@endsection
