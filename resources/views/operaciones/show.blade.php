@extends('plantillas.principal')
@section('title','Entrega de '.$op->titulo)
@push('styles')<link rel="stylesheet" href="{{ asset('css/operaciones.css') }}"><link rel="stylesheet" href="{{ asset('css/reputacion.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/fecha-chat.js') }}" defer></script><script src="{{ asset('js/operaciones.js') }}" defer></script><script src="{{ asset('js/chat-medios.js') }}" defer></script>@endpush
@section('content')
@php($comprador=auth()->id()===$op->id_comprador)
@php($abierta=!in_array($op->estado,['Completada','Reembolsada']))
@php($fecha=fn($v)=>$v ? \Carbon\Carbon::parse($v)->timezone('America/La_Paz')->format('d/m/Y H:i') : 'Pendiente')
<section class="container section ops-page" data-operacion data-estado-url="{{ route('operaciones.estado',$op->id_operacion) }}" data-firma="{{ $seguimiento['firma'] }}">
    <a href="{{ route('operaciones.index') }}">Volver a mis operaciones</a>
    <p class="eyebrow">OPERACIÓN #{{ $op->id_operacion }} · <span data-operacion-estado>{{ $op->estado }}</span></p><h1>{{ $op->titulo }}</h1>
    <p>{{ $comprador ? 'Vendedor: '.$op->vendedor : 'Comprador: '.$op->comprador }} · <a href="{{ route('subastas.show',$op->id_subasta) }}">Ver subasta</a></p>
    <p role="status" data-operacion-aviso hidden></p><button class="button secondary" type="button" data-operacion-actualizar hidden>Actualizar detalles</button>
    <section id="entrega" data-apartado class="ops-block"><h2>Entrega del artículo</h2>
        <dl class="ops-summary"><div><dt>Puja ganadora</dt><dd>Bs {{ number_format($op->importe,2,',','.') }}</dd></div>@if($op->con_fondos)<div><dt>Comisión (5 %)</dt><dd>Bs {{ number_format($op->comision,2,',','.') }}</dd></div><div><dt>Neto del vendedor</dt><dd>Bs {{ number_format($op->neto,2,',','.') }}</dd></div>@endif</dl>
        @if($op->con_fondos)<p data-entrega-fondos>{{ $op->estado==='Completada' ? 'Pago registrado y fondos acreditados al vendedor.' : ($op->estado==='Reembolsada' ? 'Reserva devuelta al comprador, sin comisión.' : 'Los fondos siguen retenidos. Se paga cuando ambos confirman la entrega y no hay incidencias abiertas.') }}</p>@else<p>Esta subasta es anterior a la billetera. Permite coordinar la entrega, sin movimientos de fondos ni comisión.</p>@endif
        <p>Recepción del comprador: <strong data-confirmacion="comprador_confirmo">{{ $fecha($op->fecha_confirmacion_comprador) }}</strong><br>Entrega del vendedor: <strong data-confirmacion="vendedor_confirmo">{{ $fecha($op->fecha_confirmacion_vendedor) }}</strong></p>
        @if($abierta && !($comprador ? $op->fecha_confirmacion_comprador : $op->fecha_confirmacion_vendedor))
            <form method="POST" action="{{ route('operaciones.accion',[$op->id_operacion,'confirmar']) }}" class="ops-form">@csrf
                <label><input type="checkbox" required> {{ $comprador ? 'Ya recibí el artículo y revisé su estado.' : 'Ya entregué el artículo al comprador.' }}</label>
                <button class="button primary">{{ $comprador ? 'Confirmar recepción' : 'Confirmar entrega' }}</button>
            </form>
        @endif
    @include('reputacion.valorar')
    </section>
    <section id="encuentro" data-apartado class="ops-block"><h2>Coordinar encuentro</h2><p class="muted">Elige un lugar público. Aceptar una propuesta no confirma la entrega ni libera fondos. Horarios de Bolivia.</p>
        @if($abierta)<form method="POST" class="ops-form" action="{{ route('operaciones.accion',[$op->id_operacion,'proponer']) }}">@csrf
            <label for="lugar">Lugar público</label><input id="lugar" name="lugar" minlength="5" maxlength="250" required value="{{ old('lugar') }}">
            <label for="fecha">Fecha y hora</label><input id="fecha" type="datetime-local" name="fecha" required value="{{ old('fecha') }}"><button class="button secondary">Proponer encuentro</button>
        </form>@endif
        <ul class="ops-history">@forelse($acuerdos as $a)<li><strong>{{ $a->lugar }}</strong><p>{{ $fecha($a->fecha_encuentro) }} · {{ $a->estado === 'Cambio solicitado' ? 'Lugar rechazado' : $a->estado }}</p>@if($a->respuesta)<p>@if($a->estado === 'Cambio solicitado')<strong>Motivo:</strong> @endif{{ $a->respuesta }}</p>@endif
            @if($abierta && $a->estado==='Propuesto' && $a->id_proponente!==auth()->id())
            <form class="ops-form" method="POST" action="{{ route('operaciones.accion',[$op->id_operacion,'responder']) }}">@csrf<input type="hidden" name="acuerdo" value="{{ $a->id_acuerdo }}"><button name="aceptar" value="1" class="button secondary">Aceptar encuentro</button><label for="cambio-{{ $a->id_acuerdo }}">Motivo para rechazar lugar propuesto</label><textarea id="cambio-{{ $a->id_acuerdo }}" name="motivo" maxlength="1000"></textarea><button name="aceptar" value="0" class="button secondary">Rechazar lugar propuesto</button></form>
            @endif
        </li>@empty<li>Aún no se propuso un encuentro.</li>@endforelse</ul><x-paginacion :elementos="$acuerdos" />
    </section>
    <section id="incidencias" data-apartado class="ops-block"><h2>Problemas con la entrega</h2><p>Si no hay respuesta, no asisten al encuentro o el artículo no coincide, abre una incidencia antes de que se registre el pago.</p>
        @foreach($casos as $caso)<article class="ops-case"><strong>Incidencia #{{ $caso->id_incidencia }} · {{ $caso->estado }}</strong><p>{{ $caso->motivo }}</p>@if($caso->resolucion)<p>{{ $caso->decision }}: {{ $caso->resolucion }}</p>@endif</article>@endforeach
        @if($abierta && !$casos->contains(fn($c)=>$c->estado!=='Resuelta'))<form class="ops-form" method="POST" action="{{ route('operaciones.accion',[$op->id_operacion,'incidencia']) }}">@csrf<label for="motivo">Describe el problema</label><textarea id="motivo" name="motivo" required minlength="5" maxlength="2000">{{ old('motivo') }}</textarea><button class="button secondary">Abrir incidencia</button></form>@endif
    </section>
    @include('operaciones.conversacion')
    <section id="historial" data-apartado class="ops-block"><h2>Historial de la operación</h2><ul class="ops-history">@foreach($eventos as $e)<li><strong>{{ $e->accion }}</strong> · {{ $fecha($e->fecha_creacion) }}<p>{{ $e->detalle }}</p></li>@endforeach</ul><x-paginacion :elementos="$eventos" /></section>
</section>
@endsection
