@extends('plantillas.principal')
@section('title', 'Control de fondos')
@push('styles')<link rel="stylesheet" href="{{ asset('css/fondos.css') }}">@endpush
@section('content')
<section class="container section funds-page"><p class="eyebrow">ADMINISTRACIÓN</p><h1 class="page-title">Control de fondos</h1><p class="muted">Consulta saldos, recargas y movimientos por cuenta. Los saldos no se editan directamente.</p>
    <form method="get" action="{{ route('admin.fondos.index') }}" class="funds-search"><div><label for="q">Nombre o correo</label><input id="q" name="q" value="{{ request('q') }}" maxlength="120" type="search"></div><button class="button primary" type="submit">Buscar</button><a href="{{ route('admin.fondos.index') }}">Limpiar</a></form>
    <div class="funds-table-scroll" role="region" aria-label="Fondos por cuenta" tabindex="0"><table class="funds-table"><thead><tr><th>Cuenta</th><th>Disponible (Bs)</th><th>Reservado (Bs)</th><th>Acciones</th></tr></thead><tbody>
        @forelse($usuarios as $usuario)<tr><th scope="row">{{ $usuario->nombre }}<br><small>{{ $usuario->correo }}</small></th><td>{{ number_format($usuario->disponible,2,',','.') }}</td><td>{{ number_format($usuario->reservado,2,',','.') }}</td><td><a href="{{ route('admin.fondos.show',$usuario) }}">Consultar movimientos</a></td></tr>@empty<tr><td colspan="4">No hay cuentas que coincidan con la búsqueda.</td></tr>@endforelse
    </tbody></table></div><x-paginacion :elementos="$usuarios" />
</section>
@endsection
