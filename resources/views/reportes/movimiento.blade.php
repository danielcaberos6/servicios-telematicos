@extends('plantillas.principal')
@section('title','Movimiento #'.$datos['id_movimiento'])
@push('styles')<link rel="stylesheet" href="{{ asset('css/reportes.css') }}">@endpush
@section('content')
@php($fecha=fn($valor)=>$valor ? \Carbon\Carbon::parse($valor)->timezone('America/La_Paz')->format('d/m/Y H:i:s') : 'Sin resolución')
<section class="container section reports-page">
    <a href="{{ route('reportes.movimientos') }}">Volver a movimientos</a><p class="eyebrow">TRAZABILIDAD DE FONDOS</p><h1>Movimiento #{{ $datos['id_movimiento'] }}</h1><p class="muted">Operación de demostración. Registro inmutable, de solo lectura. Fechas y horas de Bolivia.</p>
    <dl class="report-movement-details">
        <div><dt>Tipo</dt><dd>{{ $datos['tipo'] }}</dd></div><div><dt>Estado del movimiento</dt><dd>{{ $datos['estado'] }}</dd></div><div><dt>Titular</dt><dd>{{ $datos['titular'] }} · ID {{ $datos['id_usuario'] }}</dd></div><div><dt>Fecha del registro</dt><dd>{{ $fecha($datos['fecha_movimiento']) }}</dd></div><div><dt>Importe</dt><dd>Bs {{ number_format($datos['importe'],2,',','.') }}</dd></div><div><dt>Variación disponible</dt><dd>Bs {{ number_format($datos['disponible'],2,',','.') }}</dd></div><div><dt>Variación reservada</dt><dd>Bs {{ number_format($datos['reservado'],2,',','.') }}</dd></div><div><dt>Referencia</dt><dd class="report-reference">{{ $datos['referencia'] }}</dd></div>
    </dl>
    @if($datos['id_recarga'])<section class="report-movement-section"><h2>Recarga asociada</h2><p class="report-reference">{{ $datos['id_recarga'] }}</p><p>Estado: <strong>{{ $datos['estado_recarga'] }}</strong></p><p>Solicitud: {{ $fecha($datos['fecha_recarga']) }}<br>Resolución: {{ $fecha($datos['fecha_resolucion_recarga']) }}</p></section>@endif
    @if($datos['id_subasta'])<section class="report-movement-section"><h2>Subasta asociada</h2><p><a href="{{ route('subastas.show',$datos['id_subasta']) }}">{{ $datos['titulo'] }} · #{{ $datos['id_subasta'] }}</a></p>
        @if($datos['estado_reserva_actual'])<p>Estado actual de la reserva de este titular: <strong>{{ $datos['estado_reserva_actual'] }}</strong> · Bs {{ number_format($datos['monto_reserva_actual'],2,',','.') }}.</p>@else<p class="muted">Este titular no figura en la reserva actual de la subasta. El movimiento histórico conserva sus importes.</p>@endif
        @if($datos['id_operacion'])<p>Operación de la subasta #{{ $datos['id_operacion'] }}: <strong>{{ $datos['estado_operacion'] }}</strong></p><p>Fecha de resolución: {{ $fecha($datos['fecha_resolucion_operacion']) }}.</p><a href="{{ route('reportes.movimientos',['operacion'=>$datos['id_operacion'],'desde'=>'1900-01-01','hasta'=>'2100-12-31']) }}">Ver movimientos del pago o devolución</a>@endif
    </section>@endif
    <section class="report-movement-section"><h2>Consulta de la cuenta</h2><a href="{{ route('admin.fondos.show',$datos['id_usuario']) }}">Ver billetera del titular</a></section>
</section>
@endsection
