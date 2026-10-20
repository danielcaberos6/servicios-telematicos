@extends('plantillas.principal')
@section('title')@yield('reporte-titulo')@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reportes.css') }}">
@endpush
@section('content')
<div class="container section reports-page">
    <header class="reports-heading">
        <div><p class="eyebrow">ADMINISTRACIÓN</p><h1 class="page-title">@yield('reporte-titulo')</h1><p>@yield('reporte-descripcion')</p></div>
        <!-- <span class="admin-badge">Acceso de administrador</span> -->
    </header>
    <nav class="report-tabs" aria-label="Reportes administrativos">
        <a href="{{ route('reportes.subastas') }}" @if ($tipo === 'subastas') aria-current="page" @endif>Subastas</a>
        <a href="{{ route('reportes.usuarios') }}" @if ($tipo === 'usuarios') aria-current="page" @endif>Usuarios</a>
    </nav>
    <div class="reports-layout">
        @include('reportes.filtros')
        <div class="reports-results">
            @yield('indicadores')
            @yield('graficos')
            <section class="panel report-detail" aria-labelledby="detalle-titulo">
                @yield('tabla')
                <x-paginacion :elementos="$datos['tabla']" />
            </section>
        </div>
    </div>
</div>
@endsection
