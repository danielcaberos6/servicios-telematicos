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

    <div class="reports-layout">
        <details class="report-filter-disclosure"><summary>Filtrar reporte</summary>@include('reportes.filtros')</details>
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
