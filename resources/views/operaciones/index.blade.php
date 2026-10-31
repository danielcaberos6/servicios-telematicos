@extends('plantillas.principal')
@section('title','Mis operaciones')
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">@endpush
@section('content')
<section class="container section ops-page">
    <p class="eyebrow">DESPUÉS DE LA SUBASTA</p><h1>Mis operaciones</h1>
    <p class="muted">Coordina tus entregas, conversa con la otra parte y consulta el estado del pago.</p>
    <form method="GET" class="ops-filter">
        <label for="rol">Participación</label><select id="rol" name="rol"><option value="">Compras y ventas</option><option value="compras" @selected(request('rol')==='compras')>Mis compras</option><option value="ventas" @selected(request('rol')==='ventas')>Mis ventas</option></select>
        <label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option>@foreach(['Pendiente','En revisión','Completada','Reembolsada'] as $estado)<option @selected(request('estado')===$estado)>{{ $estado }}</option>@endforeach</select><button class="button secondary">Filtrar</button>
    </form>
    <ul class="ops-list">@forelse($operaciones as $op)
        <li><div><span class="tag">{{ $op->estado }}</span><h2><a href="{{ route('operaciones.show',$op->id_operacion) }}">{{ $op->titulo }}</a></h2><p>{{ auth()->id()===$op->id_comprador ? 'Compraste a '.$op->vendedor : 'Vendiste a '.$op->comprador }} · Bs {{ number_format($op->importe,2,',','.') }}</p>@if($op->sin_leer)<strong>{{ $op->sin_leer }} mensaje(s) sin leer</strong>@endif</div><a class="button secondary" href="{{ route('operaciones.show',$op->id_operacion) }}">Ver operación</a></li>
    @empty<li><p>Aún no tienes operaciones. Aparecerán al finalizar una subasta con ganador.</p><a href="{{ route('participaciones.index') }}">Ver mis participaciones</a></li>@endforelse</ul>
    <x-paginacion :elementos="$operaciones" />
</section>
@endsection
