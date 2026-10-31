@extends('reportes.base')
@section('reporte-titulo','Seguimiento de ventas')
@section('reporte-descripcion','Operaciones de demostración respaldadas por fondos. Periodo según el cierre de la subasta; estados e importes consultados ahora.')
@section('indicadores')
<div class="report-kpis report-financial-kpis report-sales-kpis">
    <article class="report-kpi"><h2>Operaciones del periodo</h2><strong>{{ $datos['indicadores']['ordenes'] }}</strong><small>Según los filtros.</small></article>
    @foreach([['Fondos retenidos','retenido'],['Abonado al vendedor','neto_liquidado'],['Comisiones registradas','comisiones'],['Total liquidado','total_liquidado'],['Devuelto al comprador','devuelto']] as [$label,$clave])
    <article class="report-kpi"><h2>{{ $label }}</h2><strong>Bs {{ number_format($datos['indicadores'][$clave],2,',','.') }}</strong><small>{{ $clave==='total_liquidado' ? 'Neto del vendedor más comisión.' : 'Importes efectivamente registrados.' }}</small></article>@endforeach
</div>
<p class="muted">Las órdenes pendientes o en revisión conservan la reserva; no generan comisión cobrada. Las devoluciones no cobran comisión. Las subastas anteriores a la billetera no se incluyen como ventas respaldadas.</p>
@endsection
@section('tabla')
<div class="report-table-heading"><h2 id="detalle-titulo">Ventas del periodo</h2><span>{{ $datos['total'] }} resultados</span></div>
<p class="muted">Comisión y neto previstos describen la orden. Las columnas registradas muestran lo abonado. Para seguir una referencia, abre los movimientos de la subasta.</p>
<div class="report-table-scroll" tabindex="0" role="region" aria-label="Ventas del periodo, tabla desplazable"><table class="report-table">
    <thead><tr><th>Artículo</th><th>Comprador</th><th>Vendedor</th><th>Cierre (Bolivia)</th><th>Estado</th><th>Importe ganador (Bs)</th><th>Comisión prevista (Bs)</th><th>Neto previsto (Bs)</th><th>Retenido (Bs)</th><th>Abonado al vendedor (Bs)</th><th>Comisión registrada (Bs)</th><th>Devuelto (Bs)</th><th>Trazabilidad</th></tr></thead>
    <tbody>@forelse($datos['tabla'] as $fila)<tr><th scope="row"><a href="{{ route('subastas.show',$fila['id_subasta']) }}">{{ $fila['titulo'] }}</a></th><td>{{ $fila['comprador'] }}</td><td>{{ $fila['vendedor'] }}</td><td class="report-date">{{ \Carbon\Carbon::parse($fila['fecha_fin'])->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td><td>{{ $fila['estado'] }}</td>
        @foreach(['importe','comision_prevista','neto_previsto','retenido','neto_liquidado','comision_registrada','devuelto'] as $clave)<td class="report-number">{{ number_format($fila[$clave],2,',','.') }}</td>@endforeach
        <td><a href="{{ route('reportes.movimientos',['subasta'=>$fila['id_subasta'],'desde'=>'1900-01-01','hasta'=>'2100-12-31']) }}">Ver movimientos</a><br><a href="{{ route('subastas.show',$fila['id_subasta']) }}">Subasta #{{ $fila['id_subasta'] }}</a></td></tr>
    @empty<tr><td colspan="13" class="report-empty">No hay ventas respaldadas por fondos para estos filtros.</td></tr>@endforelse</tbody>
</table></div>
@endsection
