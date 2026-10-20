@extends('reportes.base')
@section('reporte-titulo', 'Reporte de Usuarios')
@section('reporte-descripcion', 'Conoce el crecimiento, la participación y las valoraciones de la comunidad.')
@section('indicadores')
@php $kpi = $datos['indicadores']; @endphp
<div class="report-kpis">
    <article class="report-kpi">
        <h2>Total de usuarios</h2>
        <strong>{{ number_format($kpi['total'], 0, ',', '.') }}</strong>
    </article>
    <article class="report-kpi">
        <h2>Nuevos en el periodo</h2>
        <strong>{{ $kpi['nuevos'] }}</strong>
    </article>
    <article class="report-kpi">
        <h2>Usuarios con publicaciones</h2>
        <strong>{{ $kpi['publicadores'] }}</strong>
    </article>
    <article class="report-kpi">
        <h2>Promedio de valoración</h2>
        <strong class="rating-value">★ {{ $kpi['valoracion'] === null ? '—' : number_format($kpi['valoracion'], 2, ',', '.') }}</strong>
    </article>
</div>
@endsection
@section('graficos')
<div class="report-charts users-charts">
    <section class="panel report-chart report-growth"><h2>Registro de usuarios por mes</h2><p class="chart-caption">Usuarios acumulados hasta cada mes. El último punto respeta la fecha final.</p>
        @php
            $meses = $datos['meses']; $maximo = max(2, ceil(collect($meses)->max('cantidad') / 2) * 2); $puntos = [];
            foreach ($meses as $i => $mes) { $puntos[] = (50 + $i / max(1, count($meses) - 1) * 470).','.round(210 - $mes['cantidad'] / $maximo * 160, 2); }
            $paso = max(1, (int) ceil(count($meses) / 6));
        @endphp
        <svg class="report-line" viewBox="0 0 560 265" role="img" aria-labelledby="line-title line-description">
            <title id="line-title">Registro acumulado de usuarios</title><desc id="line-description">{{ implode('; ', array_map(fn ($m) => $m['mes'].': '.$m['cantidad'].' usuarios', $meses)) }}</desc>
            @foreach ([0, .5, 1] as $proporcion)
                <line x1="50" x2="520" y1="{{ 210 - $proporcion * 160 }}" y2="{{ 210 - $proporcion * 160 }}" class="line-grid" />
                <text x="40" y="{{ 214 - $proporcion * 160 }}" text-anchor="end">{{ round($maximo * $proporcion) }}</text>
            @endforeach
            <polyline points="{{ implode(' ', $puntos) }}" fill="none" stroke="#3478c6" stroke-width="3" />
            @foreach ($meses as $i => $mes)
                @php [$x, $y] = explode(',', $puntos[$i]); @endphp
                <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#3478c6"><title>{{ $mes['mes'] }}: {{ $mes['cantidad'] }} usuarios</title></circle>
                @if ($i % $paso === 0 || $loop->last)<text x="{{ $x }}" y="240" text-anchor="middle">{{ \Carbon\Carbon::parse($mes['mes'].'-01')->translatedFormat('M y') }}</text>@endif
            @endforeach
        </svg>
        <details class="report-chart-data"><summary>Ver valores del gráfico</summary><ul>@foreach ($meses as $mes)<li>{{ \Carbon\Carbon::parse($mes['mes'].'-01')->translatedFormat('F Y') }}: <strong>{{ $mes['cantidad'] }}</strong></li>@endforeach</ul></details>
    </section>
    <section class="panel report-chart"><h2>Usuarios por ciudad</h2><p class="chart-caption">Acumulado hasta la fecha final.</p>
        @if ($datos['ciudades'])
            @php $maxCiudad = max(1, collect($datos['ciudades'])->max('cantidad')); @endphp
            <ul class="report-city-bars">@foreach ($datos['ciudades'] as $ciudad)<li><span>{{ $ciudad['etiqueta'] }}</span><div aria-hidden="true"><i style="width: {{ $ciudad['cantidad'] / $maxCiudad * 100 }}%"></i></div><strong>{{ $ciudad['cantidad'] }}</strong></li>@endforeach</ul>
        @else<p class="report-empty">Sin datos para el periodo seleccionado</p>@endif
    </section>
    <section class="panel report-chart"><h2>Distribución de valoraciones</h2><p class="chart-caption">Reseñas recibidas hasta la fecha final. Fracciones agrupadas a la estrella más cercana.</p><x-grafico-barras :datos="$datos['valoraciones']" :estrellas="true" /></section>
</div>
@endsection
@section('tabla')
<div class="report-table-heading"><div><h2 id="detalle-titulo">Usuarios más activos</h2><p class="chart-caption">Ordenados por publicaciones + pujas, acumuladas hasta la fecha final.</p></div><span>{{ $datos['total'] }} resultados</span></div>
<div class="report-table-scroll"><table class="report-table">
    <thead><tr><th scope="col">Nombre</th><th scope="col">Ciudad</th><th scope="col">Subastas publicadas</th><th scope="col">Pujas realizadas</th><th scope="col">Valoración promedio</th><th scope="col">Fecha de registro</th></tr></thead>
    <tbody>@forelse ($datos['tabla'] as $fila)
        <tr><th scope="row">{{ $fila['nombre'] }}</th><td>{{ $fila['ciudad'] }}</td><td>{{ $fila['publicaciones'] }}</td><td>{{ $fila['pujas'] }}</td><td class="report-number">{{ $fila['valoracion'] === null ? 'Sin reseñas' : '★ '.number_format($fila['valoracion'], 2, ',', '.') }}</td><td class="report-date">{{ \Carbon\Carbon::parse($fila['fecha_registro'])->timezone('America/La_Paz')->format('d/m/Y') }}</td></tr>
    @empty<tr><td colspan="6" class="report-empty">Sin datos para el periodo seleccionado</td></tr>@endforelse</tbody>
</table></div>
@endsection
