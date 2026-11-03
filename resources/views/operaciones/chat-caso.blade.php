@extends('plantillas.principal')
@section('title','Conversación de la incidencia')
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/fecha-chat.js') }}" defer></script><script src="{{ asset('js/chat-medios.js') }}" defer></script>@endpush
@section('content')
<section class="container section ops-page"><a href="{{ route('admin.operaciones.caso',$incidencia) }}">Volver a la incidencia</a><h1>Conversación de la incidencia #{{ $incidencia }}</h1><p class="muted">Solo lectura. Esta consulta quedó registrada para la revisión del caso.</p><div class="ops-chat" data-chat-interfaz><ol class="ops-messages">@forelse(collect($mensajes->items())->reverse() as $m)@include('operaciones.mensaje')@empty<li>No hay mensajes.</li>@endforelse</ol>@include('operaciones.visor-foto')</div><x-paginacion :elementos="$mensajes" /></section>
@endsection
