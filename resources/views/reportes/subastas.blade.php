@extends('reportes.base')
@section('reporte-titulo', 'Reporte de Subastas')
@section('reporte-descripcion', 'Consulta las publicaciones, sus categorías y su estado actual.')
@section('indicadores')
<div class="report-kpis">
    @foreach ([['Total de subastas', 'total', ''], ['Activas', 'activas', 'green'], ['Finalizadas', 'finalizadas', ''], ['Canceladas', 'canceladas', 'red']] as [$etiqueta, $clave, $color])
        <article class="report-kpi">
            <h2>{{ $etiqueta }} @if ($clave === 'activas')<span class="live-dot" aria-hidden="true"></span>@endif</h2>
            <strong class="{{ $color }}">{{ number_format($datos['indicadores'][$clave], 0, ',', '.') }}</strong>
        </article>
    @endforeach
</div>
@endsection
@section('graficos')
<div class="report-charts">
    <section class="panel report-chart"><h2>Subastas por categoría</h2>
        @if ($datos['categorias'])
            @php
                $colores = array_map(fn ($indice) => 'var(--chart-'.$indice.')', range(1, 12));
                $acumulado = 0; $segmentos = [];
                foreach ($datos['categorias'] as $i => $categoria) {
                    $fin = $acumulado + $categoria['cantidad'] / max(1, $datos['total']) * 100;
                    $segmentos[] = $colores[$i % count($colores)].' '.$acumulado.'% '.$fin.'%';
                    $acumulado = $fin;
                }
            @endphp
            <div class="report-donut-wrap">
                <div class="report-donut" style="background: conic-gradient({{ implode(', ', $segmentos) }})" role="img" aria-label="Distribución por categoría. El detalle se presenta en la leyenda."><div><strong>{{ $datos['total'] }}</strong><span>subastas</span></div></div>
                <ul class="report-legend">@foreach ($datos['categorias'] as $categoria)
                    <li><span class="legend-dot" style="background: {{ $colores[$loop->index % count($colores)] }}" aria-hidden="true"></span><span>{{ $categoria['etiqueta'] }}</span><strong>{{ $categoria['cantidad'] }}</strong></li>
                @endforeach</ul>
            </div>
        @else<p class="report-empty">Sin datos para el periodo seleccionado</p>@endif
    </section>
    <section class="panel report-chart"><h2>Subastas publicadas por mes</h2><p class="chart-caption">Cantidad de publicaciones · incluye meses sin actividad.</p><x-grafico-barras :datos="$datos['meses']" /></section>
</div>
@endsection
@section('tabla')
<div class="report-table-heading"><h2 id="detalle-titulo">Detalle de subastas en el periodo</h2><span>{{ $datos['total'] }} resultados</span></div>
<div class="report-table-scroll"><table class="report-table">
    <thead><tr><th scope="col">Título</th><th scope="col">Vendedor</th><th scope="col">Categoría</th><th scope="col">Monto inicial (Bs)</th><th scope="col">Pujas</th><th scope="col">Estado</th><th scope="col">Ganador</th><th scope="col">Puja ganadora (Bs)</th><th scope="col">Fecha de cierre</th></tr></thead>
    <tbody>@forelse ($datos['tabla'] as $fila)
        <tr>
            <th scope="row"><a href="{{ route('subastas.show', $fila['id_subasta']) }}">{{ $fila['titulo'] }}</a></th>
            <td>{{ $fila['vendedor'] }}</td><td>{{ $fila['categoria'] }}</td>
            <td class="report-number">{{ number_format($fila['monto_inicial'], 2, ',', '.') }}</td><td>{{ $fila['pujas'] }}</td>
            <td><span @class(['report-state', 'state-active' => $fila['estado'] === 'Activa', 'state-cancelled' => $fila['estado'] === 'Cancelada'])>{{ $fila['estado'] }}</span></td>
            <td>{{ match ($fila['estado']) { 'Activa' => 'Pendiente', 'Cancelada' => 'No aplica', default => $fila['ganador'] ?? 'Sin ofertas' } }}</td>
            <td class="report-number">{{ $fila['monto_ganador'] !== null ? number_format($fila['monto_ganador'], 2, ',', '.') : '—' }}</td>
            <td class="report-date">{{ \Carbon\Carbon::parse($fila['fecha_fin'])->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
        </tr>
    @empty<tr><td colspan="9" class="report-empty">Sin datos para el periodo seleccionado</td></tr>@endforelse</tbody>
</table></div>
@endsection
