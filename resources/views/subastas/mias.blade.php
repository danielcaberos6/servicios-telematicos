@extends('layouts.app')
@section('title', 'Mis Subastas')
@section('content')
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">TUS PUBLICACIONES</p>
                <h1 class="page-title">Mis Subastas</h1>
                <p>Administra tus artículos y sigue las ofertas que recibes.</p>
            </div><a class="button primary" href="{{ route('auctions.create') }}"><x-icon name="plus" />Crear subasta</a>
        </div>
        <p class="muted">{{ $subastas->total() }} publicaciones · Puedes editar o eliminar las que aún no recibieron
            ofertas.</p>
        <div class="auction-grid">
            @forelse($subastas as $subasta)
            <x-auction-card :subasta="$subasta" :manage="true" />@empty<div class="empty-state"><x-icon
                        name="gavel" />
                    <h2>Tu primera subasta empieza aquí</h2>
                    <p>Publica ese artículo que merece una nueva historia.</p><a class="button primary"
                        href="{{ route('auctions.create') }}">Crear subasta</a>
                </div>
            @endforelse
        </div><x-pagination :items="$subastas" />
    </div>
@endsection
