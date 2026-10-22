@extends('plantillas.principal')
@section('title', 'Solicitud de identidad')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <a href="{{ route('perfil.edit') }}" class="text-link">← Mi perfil</a>
    <h1>Solicitud de verificación de identidad</h1>
    <p><strong>{{ $verificacion->estado }}{{ !$verificacion->vigente ? ' · Sin vigencia' : '' }}</strong> · Enviada el {{ $verificacion->fecha_envio->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>
    @if ($verificacion->motivo)<div class="panel"><strong>Motivo de rechazo</strong><p>{{ $verificacion->motivo }}</p></div>@endif
    @if ($verificacion->motivo_invalidacion)<p>{{ $verificacion->motivo_invalidacion }}</p>@endif
    @if ($verificacion->revisor)<p>Revisó {{ $verificacion->revisor->nombre }} el {{ $verificacion->fecha_decision->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>@endif
    <div class="gestion-identidad-grid">
        <div class="panel">
            <h2>Datos enviados</h2>
            <p><strong>Nombres:</strong> {{ $verificacion->nombres }}</p><p><strong>Apellidos:</strong> {{ $verificacion->apellidos }}</p>
            <h3>Perfil actual</h3>
            <p>{{ $verificacion->usuario->nombre }}</p>
            @if ($verificacion->usuario->imagen)<img class="avatar" src="{{ $verificacion->usuario->imagen->url }}" alt="Fotografía actual de perfil">@else<p>Sin fotografía de perfil.</p>@endif
        </div>
        <div class="panel"><h2>Carnet privado</h2>
            <img class="gestion-documento" src="{{ route('identidad.documento', $verificacion) }}" alt="Carnet enviado para revisión manual">
        </div>
    </div>
    @yield('decision-identidad')
</section>
@endsection
