@extends('plantillas.principal')
@section('title', 'Crear cuenta')
@section('content')
    <div class="auth-wrap">
        <div class="auth-story"><x-icono nombre="gavel" />
            <p class="eyebrow">HAZ ESPACIO PARA LO NUEVO</p>
            <h1>Buenas oportunidades.<br>Nuevas historias.</h1>
            <p>Únete para publicar tus artículos, participar en subastas y conectar con otras personas.</p>
        </div>
        <div class="panel auth-panel">
            <h2>Crea tu cuenta</h2>
            <p class="muted">El primer paso para encontrar algo especial.</p>
            <form method="post" action="{{ route('registro') }}">@csrf<label for="nombre">Nombre completo</label><input
                    id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="100"
                    autocomplete="name"><label for="correo">Correo electrónico</label><input id="correo" name="correo"
                    type="email" value="{{ old('correo') }}" required maxlength="254" autocomplete="email"><label
                    for="password">Contraseña</label><input id="password" name="password" type="password" required
                    minlength="8" maxlength="72" autocomplete="new-password"><small>Usa al menos 8
                    caracteres.</small><label for="password_confirmation">Repetir contraseña</label><input
                    id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                    maxlength="72" autocomplete="new-password"><button class="button primary full-width"
                    type="submit">Crear mi cuenta <x-icono nombre="arrow" /></button></form>
            <p class="auth-switch">¿Ya tienes una cuenta? <a href="{{ route('iniciar-sesion') }}">Inicia sesión</a></p>
        </div>
    </div>
@endsection
