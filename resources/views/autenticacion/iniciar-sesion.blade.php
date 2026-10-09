@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
    <div class="auth-wrap">
        <div class="auth-story"><x-icon name="gavel" />
            <p class="eyebrow">BIENVENIDO DE NUEVO</p>
            <h1>Tu próxima gran<br>oportunidad te espera.</h1>
            <p>Gestiona tus subastas, haz ofertas y mantente al tanto de tus artículos favoritos.</p>
        </div>
        <div class="panel auth-panel">
            <h2>Iniciar sesión</h2>
            <p class="muted">Entra a tu cuenta de SubastaYA.</p>
            <form method="post" action="{{ route('login') }}">@csrf<label for="correo">Correo electrónico</label><input
                    id="correo" name="correo" type="email" value="{{ old('correo') }}" required autocomplete="email"
                    placeholder="tu@correo.com"><label for="password">Contraseña</label><input id="password"
                    name="password" type="password" required autocomplete="current-password"><label
                    class="checkbox-label"><input type="checkbox" name="remember" value="1"
                        @checked(old('remember'))>Recordarme en este equipo</label><button
                    class="button primary full-width" type="submit">Iniciar sesión <x-icon name="arrow" /></button>
            </form>
            <p class="auth-switch">¿Aún no tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></p>
        </div>
    </div>
@endsection
