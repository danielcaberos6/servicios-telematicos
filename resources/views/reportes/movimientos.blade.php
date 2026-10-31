@extends('reportes.base')
@section('reporte-titulo','Movimientos de fondos')
@section('reporte-descripcion','Consulta de movimientos registrados en las billeteras de demostración. Solo lectura; importes en bolivianos.')
@section('indicadores')
<div class="report-kpis report-financial-kpis">
    <article class="report-kpi"><h2>Movimientos del periodo</h2><strong>{{ $datos['indicadores']['movimientos'] }}</strong></article>
    @foreach([['Recargas acreditadas','recargado'],['Pagos de compradores','pagado'],['Devoluciones','devuelto']] as [$label,$clave])<article class="report-kpi"><h2>{{ $label }}</h2><strong>Bs {{ number_format($datos['indicadores'][$clave],2,',','.') }}</strong><small>Según los filtros del periodo.</small></article>@endforeach
</div><p class="muted">Las variaciones indican cuánto cambió el disponible y el reservado en ese movimiento. No representan el saldo actual de la cuenta. Los movimientos conservan su estado Registrado; su operación asociada puede haber cambiado.</p>
@endsection
@section('tabla')
<div class="report-table-heading"><h2 id="detalle-titulo">Libro de movimientos</h2><span>{{ $datos['total'] }} resultados</span></div>
<div class="report-table-scroll" tabindex="0" role="region" aria-label="Movimientos de fondos, tabla desplazable"><table class="report-table">
<thead><tr><th>Registro</th><th>Fecha (Bolivia)</th><th>Titular</th><th>Tipo</th><th>Importe (Bs)</th><th>Variación disponible (Bs)</th><th>Variación reservada (Bs)</th><th>Referencia</th><th>Estado</th></tr></thead>
<tbody>@forelse($datos['tabla'] as $fila)<tr><th scope="row"><a href="{{ route('reportes.movimiento',$fila['id_movimiento']) }}">Movimiento #{{ $fila['id_movimiento'] }}</a></th><td class="report-date">{{ \Carbon\Carbon::parse($fila['fecha_movimiento'])->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</td><td>{{ $fila['titular'] }}<br><small>ID {{ $fila['id_usuario'] }}</small></td><td>{{ $fila['tipo'] }}</td>@foreach(['importe','disponible','reservado'] as $campo)<td class="report-number">{{ number_format($fila[$campo],2,',','.') }}</td>@endforeach<td class="report-reference">{{ $fila['referencia'] }}</td><td>{{ $fila['estado'] }}</td></tr>
@empty<tr><td colspan="9" class="report-empty">No hay movimientos registrados para estos filtros.</td></tr>@endforelse</tbody></table></div>
@endsection
