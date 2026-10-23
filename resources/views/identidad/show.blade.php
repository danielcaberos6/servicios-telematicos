@extends('plantillas.principal')
@section('title', 'Solicitud de identidad')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container gestion-page">
    <a href="{{ route('perfil.edit') }}" class="text-link">← Mi perfil</a>
    @if (auth()->user()->esAdministrador())<a href="{{ route('admin.identidad.index') }}" class="text-link">Volver a la bandeja de identidad</a>@endif
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
            @if ($verificacion->usuario->imagen)
                <x-visor-carnet :src="$verificacion->usuario->imagen->url" :perfil="true" />
            @else
                <p>Sin fotografía de perfil.</p>
            @endif
        </div>
        <div class="panel"><h2>Carnet privado</h2>
            <x-visor-carnet :src="route('identidad.documento', $verificacion)" />
        </div>
    </div>
    @if (auth()->user()->esAdministrador() && $verificacion->estado === 'En revisión' && $verificacion->vigente)
        <section class="panel" aria-labelledby="revision-titulo">
            <h2 id="revision-titulo">Revisión manual</h2>
            <p>Comprueba que el documento sea legible y que los nombres y apellidos coincidan con el perfil. Compara también la fotografía de perfil, si existe.</p>
            <form method="post" action="{{ route('admin.identidad.resolver', $verificacion) }}">
                @csrf @method('PATCH')<input type="hidden" name="decision" value="Aprobada">
                <label class="checkbox-label"><input type="checkbox" required name="revision_confirmada" value="1">Revisé el documento y los datos del perfil; no observé discrepancias.</label>
                <button class="button primary" type="submit">Aprobar identidad</button>
            </form>
            <form class="spaced-heading" method="post" action="{{ route('admin.identidad.resolver', $verificacion) }}">
                @csrf @method('PATCH')<input type="hidden" name="decision" value="Rechazada">
                <label for="motivo">Motivo de rechazo *</label><textarea id="motivo" name="motivo" required minlength="1" maxlength="1000" rows="3">{{ old('motivo') }}</textarea>
                <button class="button secondary" type="submit">Rechazar solicitud</button>
            </form>
        </section>
    @endif
</section>
@endsection
