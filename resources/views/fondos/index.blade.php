@extends('plantillas.principal')
@section('title', $soloLectura ? 'Detalle de fondos' : 'Mi billetera')
@push('styles')<link rel="stylesheet" href="{{ asset('css/fondos.css') }}">@endpush
@unless($soloLectura)
    @push('scripts')<script src="{{ asset('js/fondos.js') }}" defer></script>@endpush
@endunless
@section('content')
<section class="container section funds-page" @unless($soloLectura) data-billetera data-saldo-url="{{ route('billetera.saldo') }}" @endunless>
    <p class="eyebrow">{{ $soloLectura ? 'CONTROL DE FONDOS' : 'TU CUENTA' }}</p>
    <h1 class="page-title">{{ $soloLectura ? 'Fondos de '.$titular->nombre : 'Mi billetera' }}</h1>
    @if($soloLectura)<p class="muted">Consulta de movimientos y recargas. Solo lectura.</p><a href="{{ route('admin.fondos.index') }}">Volver al control de fondos</a>@else<p class="muted">Consulta tu saldo, recarga y sigue los importes reservados en tus subastas.</p>@endif
    <dl class="funds-balances" aria-label="Saldos en bolivianos">
        <div class="funds-available"><dt>Saldo disponible</dt><dd>Bs <span data-saldo="disponible">{{ number_format($saldo->disponible, 2, ',', '.') }}</span></dd><p>Para nuevas ofertas.</p></div>
        <div><dt>Saldo reservado</dt><dd>Bs <span data-saldo="reservado">{{ number_format($saldo->reservado, 2, ',', '.') }}</span></dd><p>Comprometido en ofertas y entregas.</p></div>
        <div><dt>Saldo total</dt><dd>Bs <span data-saldo="total">{{ number_format($saldo->total, 2, ',', '.') }}</span></dd><p>Disponible más reservado.</p></div>
    </dl>
    @unless($soloLectura)
        <p class="muted funds-sync" data-saldo-estado role="status" aria-live="polite">El saldo se actualiza automáticamente.</p>
        <section class="funds-recharge" id="recargar" aria-labelledby="recargar-titulo">
            <div><h2 id="recargar-titulo">Recargar mi billetera</h2><p class="muted">Ingresa entre Bs 5 y Bs 10.000. El importe se acredita al confirmar el resultado del pago.</p></div>
            <form method="post" action="{{ route('billetera.solicitar') }}">
                @csrf<input type="hidden" name="referencia" value="{{ old('referencia', $referencia) }}">
                <div><label for="monto">Monto de recarga (Bs)</label><input id="monto" name="monto" type="number" inputmode="numeric" min="5" max="10000" step="1" required value="{{ old('monto') }}" placeholder="100" aria-describedby="recarga-help">@error('monto')<p class="field-error">{{ $message }}</p>@enderror<small id="recarga-help">Bolivianos enteros, sin centavos.</small></div>
                <button class="button primary" type="submit">Continuar recarga</button>
            </form>
        </section>
    @endunless
    <section class="funds-history" id="movimientos" aria-labelledby="movimientos-titulo">
        <h2 id="movimientos-titulo">Movimientos</h2><p class="muted">Fecha y hora de Bolivia. Los cambios de disponible y reservado se muestran por separado.</p>
        <div class="funds-table-scroll" role="region" aria-label="Movimientos de la billetera" tabindex="0"><table class="funds-table">
            <thead><tr><th>Fecha</th><th>Movimiento</th><th>Importe (Bs)</th><th>Disponible (Bs)</th><th>Reservado (Bs)</th><th>Referencia</th></tr></thead>
            <tbody>@forelse($movimientos as $movimiento)
                <tr><td class="funds-nowrap">{{ \Carbon\Carbon::parse($movimiento->fecha_movimiento)->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</td><th scope="row">{{ $movimiento->tipo }}</th><td>{{ number_format($movimiento->importe,2,',','.') }}</td><td>{{ $movimiento->disponible > 0 ? '+' : '' }}{{ number_format($movimiento->disponible,2,',','.') }}</td><td>{{ $movimiento->reservado > 0 ? '+' : '' }}{{ number_format($movimiento->reservado,2,',','.') }}</td><td class="funds-reference">{{ $movimiento->referencia }}@if($movimiento->id_subasta)<br><a href="{{ route('subastas.show',$movimiento->id_subasta) }}">Ver subasta</a>@endif</td></tr>
            @empty<tr><td colspan="6" class="funds-empty">Todavía no hay movimientos.</td></tr>@endforelse</tbody>
        </table></div><x-paginacion :elementos="$movimientos" />
    </section>
    <section class="funds-history" id="recargas" aria-labelledby="recargas-titulo">
        <h2 id="recargas-titulo">Recargas</h2><p class="muted">Las recargas pendientes no se suman al saldo disponible.</p>
        <ul class="funds-recharges">@forelse($recargas as $recarga)
            <li><div><strong>Bs {{ number_format($recarga->monto,2,',','.') }}</strong><span>{{ $recarga->estado }} · {{ \Carbon\Carbon::parse($recarga->fecha_creacion)->timezone('America/La_Paz')->format('d/m/Y H:i') }}</span><small class="funds-reference">{{ $recarga->id_recarga }}</small></div>@unless($soloLectura)<a class="button secondary" href="{{ route('billetera.recarga',$recarga->id_recarga) }}">{{ $recarga->estado === 'Pendiente' ? 'Continuar' : 'Ver detalle' }}</a>@endunless</li>
        @empty<li class="funds-empty">Todavía no hay recargas.</li>@endforelse</ul><x-paginacion :elementos="$recargas" />
    </section>
</section>
@endsection
