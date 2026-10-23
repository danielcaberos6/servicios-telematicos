@extends('plantillas.principal')
@section('title', 'Gestión de usuarios')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/gestion.css') }}">
@endpush
@section('content')
    <section class="container gestion-page">
        <div class="section-heading">
            <div>
                <p class="eyebrow">ADMINISTRACIÓN</p>
                <h1>Gestión de usuarios</h1>
                <p class="muted">Consulta las cuentas registradas, sus roles y su estado de acceso.</p>
            </div>
        </div>
        <form action="{{ route('admin.usuarios.index') }}" method="get" class="panel gestion-filtros" role="search" aria-label="Buscar usuarios">
            <div>
                <label for="q">Nombre o correo</label>
                <input id="q" name="q" type="search" maxlength="120" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar una cuenta">
            </div>
            <input type="hidden" name="estado" value="{{ $filtros['estado'] ?? '' }}">
            <button type="submit" class="button primary">Buscar</button>
            <a href="{{ route('admin.usuarios.index') }}" class="text-link">Limpiar filtros</a>
        </form>
        <section class="panel" aria-labelledby="cuentas-titulo">
            <div class="section-heading">
                <h2 id="cuentas-titulo">Cuentas registradas</h2>
                <span class="muted">{{ $usuarios->total() }} resultados</span>
            </div>
            <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Lista de usuarios">
                <table class="ranking-table gestion-tabla">
                    <thead><tr>
                        <th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Rol</th>
                        <th scope="col">Estado</th><th scope="col">Fecha de registro</th><th scope="col">Acciones</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($usuarios as $usuario)
                            <tr>
                                <th scope="row">{{ $usuario['nombre'] }}</th>
                                <td>{{ $usuario['correo'] }}</td>
                                <td>{{ $usuario['administrador'] ? 'Administrador' : 'Usuario' }}</td>
                                <td><span class="gestion-estado {{ $usuario['activo'] ? 'gestion-activa' : 'gestion-inactiva' }}">{{ $usuario['activo'] ? 'Activa' : 'Desactivada' }}</span></td>
                                <td>{{ \Carbon\Carbon::parse($usuario['fecha_registro'])->timezone('America/La_Paz')->format('d/m/Y') }}</td>
                                <td class="gestion-acciones">
                                    @if ($usuario['id_usuario'] !== auth()->id())
                                        <form method="post" action="{{ route('admin.usuarios.update', $usuario['id_usuario']) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="operacion" value="estado">
                                            <input type="hidden" name="valor" value="{{ $usuario['activo'] ? 0 : 1 }}">
                                            <button type="submit" class="button secondary">{{ $usuario['activo'] ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.usuarios.update', $usuario['id_usuario']) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="operacion" value="administrador">
                                        <input type="hidden" name="valor" value="{{ $usuario['administrador'] ? 0 : 1 }}">
                                        <button type="submit" class="button secondary">{{ $usuario['administrador'] ? 'Retirar administrador' : 'Hacer administrador' }}</button>
                                    </form>
                                    <a href="{{ route('admin.usuarios.historial', $usuario['id_usuario']) }}">Ver historial</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="gestion-vacio">No se encontraron usuarios con estos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-paginacion :elementos="$usuarios" />
        </section>
    </section>
@endsection
