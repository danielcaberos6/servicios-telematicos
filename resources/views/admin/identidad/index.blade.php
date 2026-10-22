@extends('plantillas.principal')
@section('title', 'Verificación de identidad')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <p class="eyebrow">ADMINISTRACIÓN</p><h1>Verificación de identidad</h1>
    <p class="muted">Revisa manualmente el carnet y los datos del perfil. Las solicitudes más antiguas aparecen primero.</p>
    <form class="panel gestion-filtros" method="get" action="{{ route('admin.identidad.index') }}">
        <div><label for="estado">Estado</label><select name="estado" id="estado">
            @foreach (['En revisión', 'Aprobada', 'Rechazada', 'Invalidada', 'todos'] as $opcion)
                <option value="{{ $opcion }}" @selected($estado === $opcion)>{{ $opcion === 'todos' ? 'Todos los estados' : $opcion }}</option>
            @endforeach
        </select></div><button class="button primary" type="submit">Consultar</button>
        <a class="text-link" href="{{ route('notificaciones.index') }}">Mis notificaciones</a>
    </form>
    <section class="panel">
        <h2>Solicitudes de identidad · {{ $solicitudes->total() }}</h2>
        <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Solicitudes de identidad">
            <table class="ranking-table gestion-tabla">
                <thead><tr><th scope="col">Titular</th><th scope="col">Nombres enviados</th><th scope="col">Estado</th><th scope="col">Envío</th><th scope="col">Acción</th></tr></thead>
                <tbody>@forelse ($solicitudes as $solicitud)
                    <tr><th scope="row">{{ $solicitud->usuario->nombre }}</th><td>{{ $solicitud->nombres }} {{ $solicitud->apellidos }}</td>
                        <td>{{ $solicitud->estado }}{{ !$solicitud->vigente ? ' · Sin vigencia' : '' }}</td><td>{{ $solicitud->fecha_envio->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                        <td><a href="{{ route('identidad.show', $solicitud) }}">Ver solicitud</a></td></tr>
                @empty<tr><td colspan="5" class="gestion-vacio">No hay solicitudes con este estado.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <x-paginacion :elementos="$solicitudes" />
    </section>
</section>
@endsection
