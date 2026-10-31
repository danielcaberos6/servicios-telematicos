@extends('plantillas.principal')
@section('title','Incidencias de entrega')
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">@endpush
@section('content')
<section class="container section ops-page"><p class="eyebrow">ADMINISTRACIÓN</p><h1>Incidencias de entrega</h1><p class="muted">Atiende los problemas de coordinación. Los fondos permanecen retenidos hasta registrar una resolución.</p>
<ul class="ops-list">@forelse($casos as $caso)<li><div><span>{{ $caso->estado }}</span><h2>{{ $caso->titulo }}</h2><p>{{ $caso->motivo }}</p></div><a class="button secondary" href="{{ route('admin.operaciones.caso',$caso->id_incidencia) }}">Revisar incidencia #{{ $caso->id_incidencia }}</a></li>@empty<li>No hay incidencias registradas.</li>@endforelse</ul><x-paginacion :elementos="$casos" /></section>
@endsection
