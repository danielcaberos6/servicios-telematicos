@extends('plantillas.principal')
@section('title', $categoria->exists ? 'Editar categoría' : 'Nueva categoría')
@section('content')
<section class="container section">
    <a class="text-link" href="{{ route('admin.categorias.index') }}">← Volver a categorías</a>
    <h1>{{ $categoria->exists ? 'Editar categoría' : 'Nueva categoría' }}</h1>
    <form class="panel" method="post" action="{{ $categoria->exists ? route('admin.categorias.update', $categoria) : route('admin.categorias.store') }}">
        @csrf @if ($categoria->exists) @method('PUT') @endif
        <label for="nombre_categoria">Nombre *</label>
        <input id="nombre_categoria" name="nombre_categoria" required maxlength="100" value="{{ old('nombre_categoria', $categoria->nombre_categoria) }}">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="4" maxlength="1000">{{ old('descripcion', $categoria->descripcion) }}</textarea>
        <p class="muted">Los nombres son únicos, sin distinguir mayúsculas ni espacios en los extremos.</p>
        <button class="button primary" type="submit">Guardar categoría</button>
    </form>
</section>
@endsection
