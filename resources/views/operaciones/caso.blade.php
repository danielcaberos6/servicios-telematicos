@extends('plantillas.principal')
@section('title','Incidencia #'.$caso->id_incidencia)
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}">@endpush
@section('content')
<section class="container section ops-page"><a href="{{ route('admin.operaciones.index') }}">Volver a incidencias</a><p class="eyebrow">{{ $caso->estado }}</p><h1>Incidencia #{{ $caso->id_incidencia }}</h1><h2>{{ $op->titulo }}</h2><p>{{ $caso->motivo }}</p>
<dl class="ops-summary"><div><dt>Comprador</dt><dd>{{ $op->comprador }}</dd></div><div><dt>Vendedor</dt><dd>{{ $op->vendedor }}</dd></div><div><dt>Importe</dt><dd>Bs {{ number_format($op->importe,2,',','.') }}</dd></div></dl>
<p>Comprador confirmó: {{ $op->fecha_confirmacion_comprador ? 'Sí' : 'No' }} · Vendedor confirmó: {{ $op->fecha_confirmacion_vendedor ? 'Sí' : 'No' }}</p>
@if($caso->estado==='Resuelta')<section class="ops-block"><h2>{{ $caso->decision }}</h2><p>{{ $caso->resolucion }}</p><p>Resuelta el {{ \Carbon\Carbon::parse($caso->fecha_resolucion)->timezone('America/La_Paz')->format('d/m/Y H:i') }} (Bolivia).</p></section>
@elseif(!$caso->id_administrador)<form method="POST" action="{{ route('admin.operaciones.gestionar',$caso->id_incidencia) }}">@csrf<button class="button primary" name="accion" value="asignar">Atender esta incidencia</button></form>
@elseif($caso->id_administrador===auth()->id())
<p><a class="button secondary" href="{{ route('admin.operaciones.chat',$caso->id_incidencia) }}">Consultar conversación del caso</a></p><p class="muted">La consulta queda registrada con tu cuenta, fecha y motivo de la incidencia.</p>
<section class="ops-block"><h2>Resolver incidencia</h2><p>{{ $op->con_fondos ? 'Liquidar acredita el 95 % al vendedor y registra el 5 % de comisión. Devolver restituye la reserva completa al comprador, sin comisión.' : 'Esta operación anterior no tiene fondos reservados; la resolución solo cierra la coordinación.' }}</p>
<form class="ops-form" method="POST" action="{{ route('admin.operaciones.gestionar',$caso->id_incidencia) }}">@csrf<input type="hidden" name="accion" value="resolver"><label for="decision">Decisión</label><select id="decision" name="decision" required><option value="">Selecciona una resolución</option><option value="Liquidar">Autorizar liquidación</option><option value="Devolver">Cancelar y devolver fondos</option></select><label for="motivo">Motivo de la resolución</label><textarea id="motivo" name="motivo" required minlength="5" maxlength="2000">{{ old('motivo') }}</textarea><label><input type="checkbox" required> Revisé el caso y confirmo esta resolución.</label><button class="button primary">Registrar resolución</button></form></section>
@else<p>Esta incidencia está siendo atendida por otro administrador.</p>@endif
</section>
@endsection
