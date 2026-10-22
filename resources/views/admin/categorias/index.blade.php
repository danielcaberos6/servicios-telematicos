@extends('plantillas.principal')
@section('title', 'Gestión de categorías')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <div class="section-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Gestión de categorías</h1><p class="muted">Organiza las nuevas publicaciones sin perder las subastas existentes.</p></div>
        <a class="button primary" href="{{ route('admin.categorias.create') }}">Nueva categoría</a>
    </div>
    <div class="panel">
        <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Categorías">
            <table class="ranking-table gestion-tabla">
                <thead><tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>@forelse ($categorias as $categoria)
                    <tr><th scope="row">{{ $categoria->nombre_categoria }}</th><td>{{ $categoria->descripcion ?: 'Sin descripción' }}</td>
                        <td>{{ $categoria->habilitada ? 'Habilitada' : 'Deshabilitada' }}</td>
                        <td class="gestion-acciones"><a href="{{ route('admin.categorias.edit', $categoria) }}">Editar</a>
                            <form method="post" action="{{ route('admin.categorias.estado', $categoria) }}">@csrf @method('PATCH')
                                <input type="hidden" name="habilitada" value="{{ $categoria->habilitada ? 0 : 1 }}">
                                <button class="button secondary" type="submit">{{ $categoria->habilitada ? 'Deshabilitar' : 'Habilitar' }}</button>
                            </form>
                        </td></tr>
                @empty<tr><td colspan="4">No hay categorías registradas.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <x-paginacion :elementos="$categorias" />
    </div>
</section>
@endsection
