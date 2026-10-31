@extends('plantillas.principal')
@section('title','Reportes de reseñas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}"><link rel="stylesheet" href="{{ asset('css/reputacion.css') }}">@endpush
@section('content')
<section class="container section reputation-page"><p class="eyebrow">ADMINISTRACIÓN</p><h1>Reportes de reseñas</h1><p>Revisa insultos, acoso o datos personales. Una puntuación baja no justifica ocultar el comentario. La moderación conserva la puntuación y no modifica la operación.</p>
    <form method="GET" class="ops-filter"><label for="estado">Estado</label><select id="estado" name="estado"><option value="pendientes" @selected(request('estado','pendientes')==='pendientes')>Pendientes</option><option value="resueltos" @selected(request('estado')==='resueltos')>Resueltos</option></select><button class="button secondary">Filtrar</button></form>
    @forelse($reportes as $reporte)<article class="ops-block"><h2>Reporte #{{ $reporte->id_reporte }} · {{ $reporte->categoria }}</h2><p>Reseña sobre <a href="{{ route('participantes.show',$reporte->id_usuario_resenado) }}">{{ $reporte->titular }}</a> · {{ number_format($reporte->calificacion,1,',','.') }} / 5</p>
        <blockquote class="reputation-comment">{{ $reporte->comentario ?: 'Sin comentario.' }}</blockquote><p><strong>Motivo del reporte:</strong> {{ $reporte->detalle }}</p>
        @if($reporte->comentario_oculto)<p class="muted">Este comentario ya está oculto para el público.</p>@endif
        @if($reporte->accion)<p><strong>{{ $reporte->accion }}:</strong> {{ $reporte->motivo_resolucion }}</p><p class="muted">Administrador #{{ $reporte->id_administrador }} · {{ \Carbon\Carbon::parse($reporte->fecha_resolucion)->timezone('America/La_Paz')->format('d/m/Y H:i') }} (Bolivia)</p>
        @else<form class="ops-form" method="POST" action="{{ route('admin.resenas.moderar',$reporte->id_reporte) }}">@csrf
            <label for="accion-{{ $reporte->id_reporte }}">Decisión</label><select id="accion-{{ $reporte->id_reporte }}" name="accion" required><option value="">Selecciona una decisión</option><option>Ocultar comentario</option><option>Sin acción</option></select>
            <label for="motivo-{{ $reporte->id_reporte }}">Motivo de la decisión</label><textarea id="motivo-{{ $reporte->id_reporte }}" name="motivo" required minlength="5" maxlength="2000"></textarea><button class="button primary">Registrar decisión</button>
        </form>@endif
    </article>@empty<p class="muted">No hay reportes en este apartado.</p>@endforelse<x-paginacion :elementos="$reportes" />
</section>
@endsection
