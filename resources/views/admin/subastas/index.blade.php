@extends('plantillas.principal')
@section('title', 'Revisión de subastas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <p class="eyebrow">ADMINISTRACIÓN</p>
    <h1>Revisión de subastas</h1>
    <p class="muted">Evalúa y resuelve las publicaciones enviadas por los vendedores antes de habilitarlas en el catálogo.</p>

    <form class="panel gestion-filtros" method="get" action="{{ route('admin.subastas.index') }}">
        <input type="hidden" name="estado" value="{{ $estado }}">
        <div>
            <label for="busqueda">Búsqueda</label>
            <input type="search" name="q" id="busqueda" value="{{ request('q') }}" maxlength="120" placeholder="Título o descripción...">
        </div>
        <button class="button primary" type="submit">Consultar</button>
        <a class="text-link" href="{{ route('notificaciones.index') }}">Mis notificaciones</a>
    </form>

    <section class="panel">
        <h2>Solicitudes de subasta · {{ $subastas->total() }}</h2>
        <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Solicitudes de subasta">
            <table class="ranking-table gestion-tabla">
                <thead>
                    <tr>
                        <th scope="col">Artículo</th>
                        <th scope="col">Vendedor</th>
                        <th scope="col">Categoría</th>
                        <th scope="col">Monto inicial</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Envío / publicación</th>
                        <th scope="col">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subastas as $subasta)
                        <tr>
                            <th scope="row">
                                <a href="{{ route('admin.subastas.show', $subasta) }}">{{ $subasta->titulo }}</a>
                            </th>
                            <td>{{ $subasta->usuario->nombre }}</td>
                            <td>{{ $subasta->categoria?->nombre_categoria ?? 'Sin categoría' }}</td>
                            <td>Bs {{ number_format($subasta->monto_inicial, 2, ',', '.') }}</td>
                            <td><x-estado-revision :subasta="$subasta" /></td>
                            <td>{{ $subasta->fecha_inicio->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.subastas.show', $subasta) }}">Revisar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="gestion-vacio">No hay solicitudes de subasta con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :elementos="$subastas" />
    </section>
</section>
@endsection
