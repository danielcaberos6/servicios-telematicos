@extends('plantillas.principal')
@section('title', 'Recarga de billetera')
@push('styles')<link rel="stylesheet" href="{{ asset('css/fondos.css') }}">@endpush
@section('content')
<section class="container section funds-payment">
    <a href="{{ route('billetera.index') }}">Volver a Mi billetera</a>
    <p class="eyebrow">RECARGA DE BILLETERA</p><h1 class="page-title">{{ $recarga->estado === 'Pendiente' ? 'Confirma tu recarga' : 'Detalle de recarga' }}</h1>
    <p class="funds-payment-amount">Bs {{ number_format($recarga->monto,2,',','.') }}</p>
    <dl><dt>Estado</dt><dd>{{ $recarga->estado }}</dd><dt>Referencia</dt><dd class="funds-reference">{{ $recarga->id_recarga }}</dd><dt>Fecha de solicitud</dt><dd>{{ \Carbon\Carbon::parse($recarga->fecha_creacion)->timezone('America/La_Paz')->format('d/m/Y H:i:s') }} (Bolivia)</dd></dl>
    @if($recarga->estado === 'Pendiente')
        <p>Elige el resultado del pago. Solo una recarga aprobada aumenta tu saldo.</p>
        <form method="post" action="{{ route('billetera.resolver',$recarga->id_recarga) }}">@csrf
            <div class="funds-payment-actions"><button class="button primary" type="submit" name="resultado" value="Aprobada">Confirmar pago</button><button class="button secondary" type="submit" name="resultado" value="Rechazada">Rechazar pago</button><button class="button secondary" type="submit" name="resultado" value="Cancelada">Cancelar recarga</button></div>
        </form>
    @elseif($recarga->estado === 'Aprobada')<p class="info-note">El importe ya se acreditó en tu billetera. Esta recarga no vuelve a acreditarse al actualizar la página.</p>
    @else<p class="info-note">La recarga no modificó tu saldo. Puedes iniciar otra desde Mi billetera.</p>@endif
</section>
@endsection
