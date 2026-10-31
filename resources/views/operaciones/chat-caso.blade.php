@extends('plantillas.principal')
@section('title','Conversación de la incidencia')
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">@endpush
@section('content')
<section class="container section ops-page"><a href="{{ route('admin.operaciones.caso',$incidencia) }}">Volver a la incidencia</a><h1>Conversación de la incidencia #{{ $incidencia }}</h1><p class="muted">Solo lectura. Esta consulta quedó registrada para la revisión del caso.</p><ol class="ops-messages">@forelse(collect($mensajes->items())->reverse() as $m)<li><strong>{{ $m->autor }}</strong><time>{{ \Carbon\Carbon::parse($m->fecha_creacion)->timezone('America/La_Paz')->format('d/m/Y H:i') }}</time><p>{{ $m->texto }}</p></li>@empty<li>No hay mensajes.</li>@endforelse</ol><x-paginacion :elementos="$mensajes" /></section>
@endsection
