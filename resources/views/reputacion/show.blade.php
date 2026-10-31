@extends('plantillas.principal')
@section('title','Perfil de '.$perfil->nombre)
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}"><link rel="stylesheet" href="{{ asset('css/reputacion.css') }}">@endpush
@section('content')
<section class="container section reputation-page">
    <p class="eyebrow">PERFIL PÚBLICO</p>
    <header class="reputation-header">
        @if($perfil->id_imagen)<a href="{{ route('imagenes.show',$perfil->id_imagen) }}" target="_blank" rel="noopener" aria-label="Ver foto completa de {{ $perfil->nombre }}"><img class="reputation-avatar" src="{{ route('imagenes.show',$perfil->id_imagen) }}" alt="Foto de {{ $perfil->nombre }}"></a>@else<span class="reputation-avatar reputation-initials" aria-label="Iniciales de {{ $perfil->nombre }}">{{ collect(explode(' ',trim($perfil->nombre)))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->join('') }}</span>@endif
        <div><h1>{{ $perfil->nombre }}</h1>@if($perfil->ciudad)<p>{{ $perfil->ciudad }}</p>@endif<x-distintivo-identidad :usuario="$perfil" /></div>
    </header>
    <p class="muted">Las valoraciones verificadas corresponden a entregas completadas. Se publican cuando ambas partes valoran o termina el plazo de 14 días.</p>
    @include('reputacion.resumen')
    <nav class="ops-tabs" aria-label="Secciones del perfil"><a href="#valoraciones">Valoraciones verificadas</a><a href="#historicas">Reseñas históricas</a><a href="#publicaciones">Publicaciones</a></nav>
    <section class="ops-block" id="valoraciones"><h2>Experiencias verificadas</h2>@include('reputacion.resenas',['historica'=>false])</section>
    <section class="ops-block" id="historicas"><h2>Reseñas históricas</h2><p class="muted">Estos registros son anteriores al vínculo con operaciones. Se conservan aparte y no cuentan en los promedios por rol.</p>@include('reputacion.resenas',['resenas'=>$historicas,'historica'=>true])</section>
    <section class="ops-block" id="publicaciones"><h2>Publicaciones de {{ $perfil->nombre }}</h2><div class="auction-grid">@forelse($subastas as $subasta)<x-tarjeta-subasta :subasta="$subasta" />@empty<p class="muted">No hay publicaciones disponibles.</p>@endforelse</div><x-paginacion :elementos="$subastas" /></section>
</section>
@endsection
